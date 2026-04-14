<?php

namespace Devlab\LaravelMailer;

use Devlab\LaravelMailer\Commands\LaravelMailerCommand;
use Devlab\LaravelMailer\Listeners\ValidateSmtpConfiguration;
use Illuminate\Mail\Events\MessageSending;
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

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Show configuration warning only on first installation (in console)
        if (app()->runningInConsole()) {
            $this->checkAndNotifyMissingConfiguration();
        }
    }

    protected function checkAndNotifyMissingConfiguration()
    {
        $markerFile = storage_path('.laravel-mailer-notified');

        // Only show message if it hasn't been shown before
        if (file_exists($markerFile)) {
            return;
        }

        $required = [
            config('mail.mailers.smtp.host'),
            config('mail.mailers.smtp.port'),
            config('mail.mailers.smtp.username'),
            config('mail.mailers.smtp.password'),
            config('mail.mailers.from.name'),
        ];

        $isMissing = false;
        foreach ($required as $value) {
            if (empty($value)) {
                $isMissing = true;
                break;
            }
        }

        if ($isMissing) {
            echo "\n[laravel-mailer] Falta configuración del SMTP. Ejecuta: php artisan laravel-mailer para configurarlo y ejecutar el seeder.\n";
            // Create marker file to prevent showing this message again
            @file_put_contents($markerFile, '');
        }
    }
}
