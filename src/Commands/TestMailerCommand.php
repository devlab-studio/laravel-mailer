<?php

namespace Devlab\LaravelMailer\Commands;

use Devlab\LaravelMailer\Models\Email;
use Devlab\LaravelMailer\Models\EmailSender;
use Devlab\LaravelMailer\Notifications\TestMailNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class TestMailerCommand extends Command
{
    public $signature = 'mailer:test
        {--from= : Email de la cuenta remitente registrada}
        {--to= : Email de destino}';

    public $description = 'Envía un correo de prueba desde una cuenta remitente para comprobar que funciona';

    protected const MAILERS = [
        'smtp' => 'SMTP',
        'google' => 'Google (Gmail API)',
        'microsoft' => 'Microsoft (Graph API)',
    ];

    public function handle(): int
    {
        intro(' Laravel Mailer · Prueba de envío ');

        $sender = $this->resolveSender();

        if (! $sender) {
            return self::FAILURE;
        }

        if ($sender->mailer !== 'smtp' && ! config("mail.mailers.{$sender->mailer}")) {
            error("CustomMailChannel necesita un mailer '{$sender->mailer}' definido en config/mail.php (mail.mailers.{$sender->mailer}).");

            return self::FAILURE;
        }

        if ($sender->mailer !== 'smtp' && blank($sender->mailer_data['refresh_token'] ?? null)) {
            warning("La cuenta {$sender->address} no está autorizada todavía. Ejecuta mailer:config y abre la URL de autorización.");

            if (! confirm('¿Intentar el envío de todas formas?', false)) {
                return self::FAILURE;
            }
        }

        $to = $this->option('to') ?? text(
            label: 'Email de destino',
            placeholder: 'tu-correo@empresa.com',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Introduce un email válido.',
        );

        if (app()->environment('local') && config('devlab.MAIL_DEV_TO')) {
            warning('Entorno local: el canal redirige todos los correos a '.config('devlab.MAIL_DEV_TO').'.');
        }

        $lastEmailId = Email::max('id') ?? 0;

        try {
            spin(
                fn () => Notification::route('mail', $to)->notifyNow(new TestMailNotification($sender)),
                "Enviando desde {$sender->address}...",
            );
        } catch (Throwable $e) {
            error('El envío ha fallado antes de completarse: '.$e->getMessage());

            return self::FAILURE;
        }

        $email = Email::where('id', '>', $lastEmailId)->latest('id')->first();

        table(['Campo', 'Valor'], [
            ['Remitente', "{$sender->name} <{$sender->address}>"],
            ['Mailer', self::MAILERS[$sender->mailer] ?? $sender->mailer],
            ['Destino', (string) $email?->to],
            ['Asunto', (string) $email?->subject],
            ['Registro en emails', $email ? "ID {$email->id}" : '(no encontrado)'],
        ]);

        if ($email?->state != 1) {
            error('El envío ha fallado: '.($email?->error ?: 'sin detalle del error.'));

            return self::FAILURE;
        }

        info('Correo enviado correctamente.');
        note("Debería llegar desde {$sender->address}. "
            .($sender->mailer === 'smtp'
                ? 'Si llega desde otra dirección, revisa que el servidor SMTP permita enviar como esa cuenta.'
                : 'Si llega desde otra cuenta, se autorizó con un usuario distinto: vuelve a autorizarla con la cuenta correcta.'));

        return self::SUCCESS;
    }

    protected function resolveSender(): ?EmailSender
    {
        if ($from = $this->option('from')) {
            $sender = EmailSender::where('address', $from)->first();

            if (! $sender) {
                error("No hay ninguna cuenta registrada con el email {$from}.");
            }

            return $sender;
        }

        $senders = EmailSender::orderBy('mailer')->orderBy('address')->get();

        if ($senders->isEmpty()) {
            error('No hay cuentas registradas. Créalas con: php artisan mailer:config');

            return null;
        }

        $id = select(
            label: '¿Desde qué cuenta quieres enviar?',
            options: $senders->mapWithKeys(fn (EmailSender $sender) => [
                $sender->id => "{$sender->address} · ".(self::MAILERS[$sender->mailer] ?? $sender->mailer),
            ])->all(),
            scroll: 10,
        );

        return $senders->firstWhere('id', $id);
    }
}
