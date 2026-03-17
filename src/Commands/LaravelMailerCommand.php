<?php

namespace Devlab\LaravelMailer\Commands;

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

        $configPath = config_path('mailer.php');
        $config = file_exists($configPath) ? include $configPath : [];
        $config['smtp_host'] = $host;
        $config['smtp_port'] = $port;
        $config['smtp_scheme'] = $scheme;
        $config['smtp_username'] = $user;
        $config['smtp_password'] = $password;
        $config['smtp_name'] = $name;

        $configContent = '<?php\n\nreturn ' . var_export($config, true) . ';';
        file_put_contents($configPath, $configContent);

        $this->info('Configuración guardada correctamente en config/mailer.php');
        return self::SUCCESS;
    }
}
