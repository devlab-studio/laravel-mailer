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
        $port = $this->ask('Introduce el puerto SMTP');
        $scheme = $this->ask('Introduce el protocolo (scheme) SMTP');
        $user = $this->ask('Introduce el usuario SMTP');
        $password = $this->ask('Introduce la contraseña SMTP');
        $name = $this->ask('Introduce el nombre del remitente');

        $config_path = config_path('mail.mailers.php');
        if (!file_exists($config_path)) {
            $config = [
                'mail' => [
                    'mailers' => [
                        'smtp' => [
                            'host' => $host,
                            'port' => $port,
                            'scheme' => $scheme,
                            'username' => $user,
                            'password' => $password,
                        ],
                            'from' => [
                            'name' => $name,
                        ],
                    ],

                ]

            ];
        } else {
            $config = include $config_path;
            $config['mail']['mailers']['smtp']['host'] = $host;
            $config['mail']['mailers']['smtp']['port'] = $port;
            $config['mail']['mailers']['smtp']['scheme'] = $scheme;
            $config['mail']['mailers']['smtp']['username'] = $user;
            $config['mail']['mailers']['smtp']['password'] = $password;
            $config['mail']['mailers']['from']['name'] = $name;
        }

        $content = '<?php\n\nreturn ' . var_export($config, true) . ';';
        file_put_contents($config_path, $content);

        $this->info('Configuración guardada correctamente en config/mailer.php');

        $this->call(EmailSendersTableSeeder::class);
        $this->info('Seeder EmailSendersTableSeeder ejecutado correctamente.');
        return self::SUCCESS;
    }
}
