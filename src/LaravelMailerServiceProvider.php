<?php

namespace Devlab\LaravelMailer;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Devlab\LaravelMailer\Commands\LaravelMailerCommand;

class LaravelMailerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-mailer')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_email_senders_table')
            ->hasMigration('create_emails_emails_attachments_table')
            ->hasMigration('create_emails_table')
            ->hasCommand(Commands\LaravelMailerCommand::class);
    }

    public function boot()
    {
        parent::boot();
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/Traits/WithExtensions.php' => base_path('app/Traits/WithExtensions.php'),
                __DIR__.'/Classes/dlSign.php' => base_path('app/Classes/dlSign.php'),
            ], 'laravel-mailer-extensions');
        }
        $auth_user = config('mailer.EMAIL_AUTH_USER');
        $auth_password = config('mailer.EMAIL_AUTH_PASSWORD');
        if (empty($auth_user) || empty($auth_password)) {
            if (app()->runningInConsole()) {
                $this->outputMissingConfigMessage();
            }
        }
    }

    protected function outputMissingConfigMessage()
    {
        echo "\n[laravel-mailer] Falta configuración EMAIL_AUTH_USER o EMAIL_AUTH_PASSWORD. Ejecuta: php artisan laravel-mailer para configurarlo.\n";
    }
}
