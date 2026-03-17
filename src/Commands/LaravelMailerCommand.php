<?php

namespace Devlab\LaravelMailer\Commands;

use Illuminate\Console\Command;

class LaravelMailerCommand extends Command
{
    public $signature = 'laravel-mailer';

    public $description = 'My command';

    public function handle(): int
    {
        $user = $this->ask('Introduce el valor para EMAIL_AUTH_USER');
        $password = $this->ask('Introduce el valor para EMAIL_AUTH_PASSWORD');

        $configPath = config_path('mailer.php');
        $config = file_exists($configPath) ? include $configPath : [];
        $config['EMAIL_AUTH_USER'] = $user;
        $config['EMAIL_AUTH_PASSWORD'] = $password;

        $configContent = '<?php\n\nreturn ' . var_export($config, true) . ';';
        file_put_contents($configPath, $configContent);

        $this->info('Configuración guardada correctamente en config/mailer.php');
        return self::SUCCESS;
    }
}
