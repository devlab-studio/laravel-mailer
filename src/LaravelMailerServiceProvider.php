<?php

namespace Devlab\LaravelMailer;

use Devlab\LaravelMailer\Commands\LaravelMailerCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelMailerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-mailer')
            ->hasConfigFile()
            ->runsMigrations('create_email_senders_table')
            ->runsMigrations('create_emails_emails_attachments_table')
            ->runsMigrations('create_emails_table')
            ->hasCommand(LaravelMailerCommand::class);    }

    public function boot()
    {
        parent::boot();
        $required = [
            config('mail.mailers.smtp.host'),
            config('mail.mailers.smtp.port'),
            config('mail.mailers.smtp.username'),
            config('mail.mailers.smtp.password'),
            config('mail.mailers.from.name'),
        ];
        $missing = false;
        foreach ($required as $value) {
            if (empty($value)) {
                $missing = true;
                break;
            }
        }
        if ($missing) {
            if (app()->runningInConsole()) {
                $this->outputMissingConfigMessage();
            }
        }
    }

    protected function outputMissingConfigMessage()
    {
        echo "\n[laravel-mailer] Falta configuración del SMTP. Ejecuta: php artisan laravel-mailer para configurarlo y ejecutar el seeder.\n";
    }
}
