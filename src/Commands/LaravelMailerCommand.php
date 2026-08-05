<?php

namespace Devlab\LaravelMailer\Commands;

use Devlab\LaravelMailer\Database\Seeders\EmailSendersTableSeeder;
use Illuminate\Console\Command;

class LaravelMailerCommand extends Command
{
    public $signature = 'laravel-mailer';

    public $description = 'My command';

    public function handle(): int
    {
        $host = $this->ask('Introduce el host SMTP');
        $port = $this->ask('Introduce el puerto SMTP', 587);
        $scheme = $this->ask('Introduce el protocolo de encriptación SMTP (tls, ssl, null)', 'tls');
        $user = $this->ask('Introduce el usuario SMTP');
        $password = $this->ask('Introduce la contraseña SMTP');
        $address = $this->ask('Introduce el email del remitente');
        $name = $this->ask('Introduce el nombre del remitente');

        config([
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => $host,
                'port' => $port,
                'encryption' => $scheme,
                'username' => $user,
                'password' => $password
            ],
            'mail.from' => [
                'address' => $address,
                'name' => $name,
            ],
        ]);

        $this->info('Configuración SMTP aplicada correctamente en memoria.');

        app(EmailSendersTableSeeder::class)->run();
        $this->info('Seeder EmailSendersTableSeeder ejecutado correctamente.');
        return self::SUCCESS;
    }
}
