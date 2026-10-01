<?php

namespace Devlab\LaravelMailer\Commands;

use Devlab\LaravelMailer\Models\EmailSender;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class ManageMailersCommand extends Command
{
    public $signature = 'laravel-mailer:mailers
        {--mailer= : Tipo de mailer (smtp, google, microsoft)}';

    public $description = 'Registra o modifica cuentas remitentes (SMTP, Google, Microsoft)';

    protected const MAILERS = [
        'smtp' => 'SMTP',
        'google' => 'Google (Gmail API)',
        'microsoft' => 'Microsoft (Graph API)',
    ];

    protected const ENCRYPTIONS = [
        'tls' => 'TLS (normalmente puerto 587)',
        'ssl' => 'SSL (normalmente puerto 465)',
        'none' => 'Ninguna',
    ];

    public function handle(): int
    {
        $mailer = $this->option('mailer');

        if ($mailer !== null && ! array_key_exists($mailer, self::MAILERS)) {
            $this->error("Mailer no válido: {$mailer}. Opciones: ".implode(', ', array_keys(self::MAILERS)));

            return self::FAILURE;
        }

        intro(' Laravel Mailer · Gestión de mailers ');

        do {
            $type = $mailer ?? select('¿Qué tipo de mailer quieres gestionar?', self::MAILERS);

            $senders = $this->listSenders($type);

            $action = $senders->isEmpty() ? 'create' : select('¿Qué quieres hacer?', [
                'create' => 'Registrar un nuevo mailer',
                'update' => 'Modificar un mailer existente',
            ]);

            if ($action === 'create') {
                $sender = new EmailSender;
                $sender->mailer = $type;
            } else {
                $sender = $this->selectSender($senders);
            }

            $this->fill($sender);
            $this->showSummary($sender);

            if (confirm('¿Guardar los cambios?', true)) {
                $sender->save();

                info(($action === 'create' ? 'Mailer registrado' : 'Mailer actualizado')." correctamente (ID {$sender->id}).");
            } else {
                warning('Cambios descartados.');
            }
        } while (confirm('¿Quieres realizar otra operación?', false));

        outro(' ¡Listo! ');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, EmailSender>
     */
    protected function listSenders(string $type): Collection
    {
        $senders = EmailSender::where('mailer', $type)->orderBy('address')->get();

        if ($senders->isEmpty()) {
            note('Aún no hay mailers de tipo '.self::MAILERS[$type].'. Vamos a registrar el primero.');

            return $senders;
        }

        table(
            ['ID', 'Email', 'Nombre', $type === 'smtp' ? 'Servidor' : 'Autorizado'],
            $senders->map(fn (EmailSender $sender) => [
                $sender->id,
                $sender->address,
                $sender->name,
                $type === 'smtp'
                    ? "{$sender->server}:{$sender->port}"
                    : (filled($sender->mailer_data['refresh_token'] ?? null) ? 'Sí' : 'No'),
            ])->all(),
        );

        return $senders;
    }

    /**
     * @param  Collection<int, EmailSender>  $senders
     */
    protected function selectSender(Collection $senders): EmailSender
    {
        $id = select(
            label: 'Selecciona el mailer a modificar',
            options: $senders->mapWithKeys(fn (EmailSender $sender) => [
                $sender->id => "{$sender->address} ({$sender->name})",
            ])->all(),
            scroll: 10,
        );

        return $senders->firstWhere('id', $id);
    }

    protected function fill(EmailSender $sender): void
    {
        $sender->address = text(
            label: 'Email del remitente',
            placeholder: 'notificaciones@empresa.com',
            default: (string) $sender->address,
            required: true,
            validate: fn (string $value) => $this->validateAddress($value, $sender),
        );

        $sender->name = text(
            label: 'Nombre del remitente',
            default: (string) $sender->name,
            required: true,
            validate: fn (string $value) => mb_strlen($value) > 150 ? 'Máximo 150 caracteres.' : null,
        );

        if ($sender->mailer === 'smtp') {
            $this->fillSmtp($sender);
        } else {
            $this->fillOAuth($sender);
        }
    }

    protected function fillSmtp(EmailSender $sender): void
    {
        $sender->server = text(
            label: 'Host SMTP',
            placeholder: 'smtp.empresa.com',
            default: (string) $sender->server,
            required: true,
            validate: fn (string $value) => mb_strlen($value) > 45 ? 'Máximo 45 caracteres.' : null,
        );

        $sender->port = (int) text(
            label: 'Puerto SMTP',
            default: (string) ($sender->port ?: 587),
            required: true,
            validate: fn (string $value) => ctype_digit($value) && (int) $value >= 1 && (int) $value <= 65535
                ? null
                : 'Introduce un puerto válido (1-65535).',
        );

        $encryption = select(
            label: 'Encriptación',
            options: self::ENCRYPTIONS,
            default: $sender->exists ? ($sender->auth_protocol ?: 'none') : 'tls',
        );
        $sender->auth_protocol = $encryption === 'none' ? '' : $encryption;

        $sender->use_auth = confirm('¿El servidor requiere autenticación?', $sender->exists ? (bool) $sender->use_auth : true) ? 1 : 0;

        if (! $sender->use_auth) {
            $sender->auth_user = '';
            $sender->auth_password = encrypt('');
            $sender->mailer_data = null;

            return;
        }

        $sender->auth_user = text(
            label: 'Usuario SMTP',
            default: (string) ($sender->auth_user ?: $sender->address),
            required: true,
            validate: fn (string $value) => mb_strlen($value) > 45 ? 'Máximo 45 caracteres.' : null,
        );

        $keepPassword = $sender->exists && filled($sender->auth_password);
        $password = password(
            label: 'Contraseña SMTP',
            required: ! $keepPassword,
            hint: $keepPassword ? 'Déjala vacía para mantener la actual.' : '',
        );

        if (filled($password)) {
            $sender->auth_password = encrypt($password);
        }

        $sender->mailer_data = null;
    }

    protected function fillOAuth(EmailSender $sender): void
    {
        $data = $sender->mailer_data ?? [];

        if (! $sender->exists && $source = $this->selectCredentialsSource($sender->mailer)) {
            $data = Arr::only($source->mailer_data, ['client_id', 'client_secret', 'tenant_id']);
        }

        $credentials = [];

        $credentials['client_id'] = text(
            label: 'Client ID',
            default: (string) ($data['client_id'] ?? ''),
            required: true,
        );

        $keepSecret = filled($data['client_secret'] ?? null);
        $clientSecret = password(
            label: 'Client secret',
            required: ! $keepSecret,
            hint: $keepSecret ? 'Déjalo vacío para mantener el actual.' : '',
        );
        $credentials['client_secret'] = filled($clientSecret) ? $clientSecret : $data['client_secret'];

        if ($sender->mailer === 'microsoft') {
            $credentials['tenant_id'] = text(
                label: 'Tenant ID',
                default: (string) ($data['tenant_id'] ?? 'common'),
                required: true,
                hint: 'Usa "common" si la app es multi-tenant.',
            );
        }

        $credentialsChanged = array_diff_assoc($credentials, $data) !== [];

        $data = array_merge($data, $credentials);

        // Los tokens emitidos con otras credenciales dejan de ser válidos
        if ($credentialsChanged || ! array_key_exists('refresh_token', $data)) {
            $data['access_token'] = null;
            $data['refresh_token'] = null;
            $data['expires_at'] = null;
        }

        $sender->mailer_data = $data;

        // Columnas SMTP obligatorias en la tabla que no aplican a OAuth
        if (! $sender->exists) {
            $sender->server = '';
            $sender->port = 0;
            $sender->use_auth = 1;
            $sender->auth_protocol = '';
            $sender->auth_user = $sender->address;
            $sender->auth_password = '';
        }
    }

    protected function selectCredentialsSource(string $type): ?EmailSender
    {
        $sources = EmailSender::where('mailer', $type)
            ->orderBy('address')
            ->get()
            ->filter(fn (EmailSender $sender) => filled($sender->mailer_data['client_id'] ?? null))
            ->unique(fn (EmailSender $sender) => $sender->mailer_data['client_id']);

        if ($sources->isEmpty()) {
            return null;
        }

        $choice = select(
            'Credenciales OAuth',
            ['new' => 'Introducir credenciales nuevas']
                + $sources->mapWithKeys(fn (EmailSender $sender) => [
                    $sender->id => "Reutilizar las de {$sender->address}",
                ])->all(),
        );

        return $choice === 'new' ? null : $sources->firstWhere('id', $choice);
    }

    protected function validateAddress(string $value, EmailSender $sender): ?string
    {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'Introduce un email válido.';
        }

        if (mb_strlen($value) > 75) {
            return 'Máximo 75 caracteres.';
        }

        $exists = EmailSender::where('address', $value)
            ->when($sender->exists, fn ($query) => $query->whereKeyNot($sender->getKey()))
            ->exists();

        return $exists ? 'Ya existe un mailer registrado con ese email.' : null;
    }

    protected function showSummary(EmailSender $sender): void
    {
        $rows = [
            ['Tipo', self::MAILERS[$sender->mailer]],
            ['Email', $sender->address],
            ['Nombre', $sender->name],
        ];

        if ($sender->mailer === 'smtp') {
            $rows[] = ['Host', $sender->server];
            $rows[] = ['Puerto', (string) $sender->port];
            $rows[] = ['Encriptación', $sender->auth_protocol ?: 'ninguna'];
            $rows[] = ['Usuario', $sender->use_auth ? $sender->auth_user : '(sin autenticación)'];

            table(['Campo', 'Valor'], $rows);

            return;
        }

        $rows[] = ['Client ID', $sender->mailer_data['client_id']];

        if ($sender->mailer === 'microsoft') {
            $rows[] = ['Tenant ID', $sender->mailer_data['tenant_id']];
        }

        $rows[] = ['Autorizado', filled($sender->mailer_data['refresh_token'] ?? null) ? 'Sí' : 'No'];

        table(['Campo', 'Valor'], $rows);
    }
}
