<?php

namespace Devlab\LaravelMailer;

use Devlab\LaravelMailer\Commands\LaravelMailerCommand;
use Devlab\LaravelMailer\Listeners\ValidateSmtpConfiguration;
use Illuminate\Mail\Events\MessageSending;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelMailerServiceProvider extends PackageServiceProvider
{
    protected static bool $missingConfigMessageShown = false;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-mailer')
            ->hasConfigFile()
            ->runsMigrations('create_email_senders_table')
            ->runsMigrations('create_emails_emails_attachments_table')
            ->runsMigrations('create_emails_table')
            ->hasCommand(LaravelMailerCommand::class);
    }

    public function packageRegistered()
    {
        if (self::$missingConfigMessageShown) {
            return;
        }

        if (! app()->runningInConsole()) {
            return;
        }

        if (file_exists($this->missingConfigNoticePath())) {
            return;
        }

        $required = [
            config('mail.mailers.smtp.host'),
            config('mail.mailers.smtp.port'),
            config('mail.mailers.smtp.username'),
            config('mail.mailers.smtp.password'),
            config('mail.from.name'),
        ];

        $isMissing = false;
        foreach ($required as $value) {
            if (empty($value)) {
                $isMissing = true;
                break;
            }
        }
        if ($isMissing) {
            self::$missingConfigMessageShown = true;
            $this->rememberMissingConfigMessageShown();
            $this->outputMissingConfigMessage();
        }
    }

    public function boot()
    {
        parent::boot();

        // Charge migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function outputMissingConfigMessage()
    {
        echo "\n[laravel-mailer] Falta configuración del SMTP. Ejecuta: php artisan laravel-mailer para configurarlo y ejecutar el seeder.\n";
    }

    protected function missingConfigNoticePath(): string
    {
        return storage_path('framework/laravel-mailer-notice-shown');
    }

    protected function rememberMissingConfigMessageShown(): void
    {
        $directory = dirname($this->missingConfigNoticePath());

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        @file_put_contents($this->missingConfigNoticePath(), '');
    }
}
