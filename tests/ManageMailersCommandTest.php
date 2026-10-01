<?php

use Devlab\LaravelMailer\Models\EmailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
});

it('registers a new smtp mailer', function () {
    $this->artisan('mailer:config', ['--mailer' => 'smtp'])
        ->expectsQuestion('Email del remitente', 'info@example.com')
        ->expectsQuestion('Nombre del remitente', 'Info')
        ->expectsQuestion('Host SMTP', 'smtp.example.com')
        ->expectsQuestion('Puerto SMTP', '465')
        ->expectsQuestion('Encriptación', 'ssl')
        ->expectsConfirmation('¿El servidor requiere autenticación?', 'yes')
        ->expectsQuestion('Usuario SMTP', 'info@example.com')
        ->expectsQuestion('Contraseña SMTP', 'secret')
        ->expectsConfirmation('¿Guardar los cambios?', 'yes')
        ->expectsConfirmation('¿Quieres realizar otra operación?', 'no')
        ->assertSuccessful();

    $sender = EmailSender::sole();

    expect($sender->mailer)->toBe('smtp')
        ->and($sender->server)->toBe('smtp.example.com')
        ->and($sender->port)->toBe(465)
        ->and($sender->auth_protocol)->toBe('ssl')
        ->and(decrypt($sender->auth_password))->toBe('secret');
});

it('registers a new google mailer', function () {
    $this->artisan('mailer:config')
        ->expectsQuestion('¿Qué tipo de mailer quieres gestionar?', 'google')
        ->expectsQuestion('Email del remitente', 'gmail@example.com')
        ->expectsQuestion('Nombre del remitente', 'Gmail')
        ->expectsQuestion('Client ID', 'client-id')
        ->expectsQuestion('Client secret', 'client-secret')
        ->expectsConfirmation('¿Guardar los cambios?', 'yes')
        ->expectsOutputToContain('http://localhost/auth/google-mail/login?sender_email=gmail%40example.com')
        ->expectsOutputToContain('http://localhost/auth/google-mail/callback')
        ->expectsConfirmation('¿Quieres realizar otra operación?', 'no')
        ->assertSuccessful();

    $sender = EmailSender::sole();

    expect($sender->mailer)->toBe('google')
        ->and($sender->mailer_data)->toBe([
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
        ]);
});

function oauthSender(string $mailer, string $address, array $mailerData): EmailSender
{
    $sender = new EmailSender;
    $sender->forceFill([
        'address' => $address,
        'name' => 'Test',
        'server' => 'test.'.$mailer.'.com',
        'port' => 587,
        'auth_protocol' => 'tls',
        'auth_user' => 'test',
        'auth_password' => 'test',
        'mailer' => $mailer,
        'mailer_data' => $mailerData,
    ])->save();

    return $sender;
}

it('registers a google mailer reusing credentials from another account', function () {
    $source = oauthSender('google', 'first@example.com', [
        'client_id' => 'shared-id',
        'client_secret' => 'shared-secret',
        'access_token' => 'access',
        'refresh_token' => 'refresh',
        'expires_at' => '2026-09-25T12:18:54.454825Z',
    ]);

    $this->artisan('mailer:config', ['--mailer' => 'google'])
        ->expectsQuestion('¿Qué quieres hacer?', 'create')
        ->expectsQuestion('Email del remitente', 'second@example.com')
        ->expectsQuestion('Nombre del remitente', 'Second')
        ->expectsQuestion('Credenciales OAuth', $source->id)
        ->expectsQuestion('Client ID', 'shared-id')
        ->expectsQuestion('Client secret', '')
        ->expectsConfirmation('¿Guardar los cambios?', 'yes')
        ->expectsConfirmation('¿Quieres realizar otra operación?', 'no')
        ->assertSuccessful();

    $sender = EmailSender::where('address', 'second@example.com')->sole();

    expect($sender->mailer_data)->toBe([
        'client_id' => 'shared-id',
        'client_secret' => 'shared-secret',
        'access_token' => null,
        'refresh_token' => null,
        'expires_at' => null,
    ]);
});

it('updates a microsoft mailer keeping secret, tokens and smtp columns', function () {
    $sender = oauthSender('microsoft', 'ms@example.com', [
        'client_id' => 'client-id',
        'tenant_id' => 'tenant-id',
        'client_secret' => 'client-secret',
        'access_token' => 'access',
        'refresh_token' => 'refresh',
        'expires_at' => '2026-09-22T12:21:06.667272Z',
    ]);

    $this->artisan('mailer:config', ['--mailer' => 'microsoft'])
        ->expectsQuestion('¿Qué quieres hacer?', 'update')
        ->expectsQuestion('Selecciona el mailer a modificar', $sender->id)
        ->expectsQuestion('Email del remitente', 'ms@example.com')
        ->expectsQuestion('Nombre del remitente', 'Microsoft')
        ->expectsQuestion('Client ID', 'client-id')
        ->expectsQuestion('Client secret', '')
        ->expectsQuestion('Tenant ID', 'tenant-id')
        ->expectsConfirmation('¿Guardar los cambios?', 'yes')
        ->expectsOutputToContain('http://localhost/auth/microsoft-mail/login?sender_email=ms%40example.com')
        ->expectsConfirmation('¿Quieres realizar otra operación?', 'no')
        ->assertSuccessful();

    $sender->refresh();

    expect($sender->name)->toBe('Microsoft')
        ->and($sender->server)->toBe('test.microsoft.com')
        ->and($sender->mailer_data['client_secret'])->toBe('client-secret')
        ->and($sender->mailer_data['refresh_token'])->toBe('refresh');
});

it('resets tokens when microsoft tenant changes', function () {
    $sender = oauthSender('microsoft', 'ms@example.com', [
        'client_id' => 'client-id',
        'tenant_id' => 'tenant-id',
        'client_secret' => 'client-secret',
        'access_token' => 'access',
        'refresh_token' => 'refresh',
        'expires_at' => '2026-09-22T12:21:06.667272Z',
    ]);

    $this->artisan('mailer:config', ['--mailer' => 'microsoft'])
        ->expectsQuestion('¿Qué quieres hacer?', 'update')
        ->expectsQuestion('Selecciona el mailer a modificar', $sender->id)
        ->expectsQuestion('Email del remitente', 'ms@example.com')
        ->expectsQuestion('Nombre del remitente', 'Microsoft')
        ->expectsQuestion('Client ID', 'client-id')
        ->expectsQuestion('Client secret', '')
        ->expectsQuestion('Tenant ID', 'other-tenant')
        ->expectsConfirmation('¿Guardar los cambios?', 'yes')
        ->expectsConfirmation('¿Quieres realizar otra operación?', 'no')
        ->assertSuccessful();

    expect($sender->refresh()->mailer_data)
        ->tenant_id->toBe('other-tenant')
        ->refresh_token->toBeNull();
});

it('discards changes when not confirmed', function () {
    $this->artisan('mailer:config', ['--mailer' => 'google'])
        ->expectsQuestion('Email del remitente', 'gmail@example.com')
        ->expectsQuestion('Nombre del remitente', 'Gmail')
        ->expectsQuestion('Client ID', 'client-id')
        ->expectsQuestion('Client secret', 'client-secret')
        ->expectsConfirmation('¿Guardar los cambios?', 'no')
        ->expectsConfirmation('¿Quieres realizar otra operación?', 'no')
        ->assertSuccessful();

    expect(EmailSender::count())->toBe(0);
});

it('fails when the mailer migration has not been run', function () {
    Schema::dropColumns('email_senders', ['mailer', 'mailer_data']);

    $this->artisan('mailer:config')
        ->expectsOutputToContain('php artisan migrate')
        ->assertFailed();
});

it('rejects an unknown mailer type', function () {
    $this->artisan('mailer:config', ['--mailer' => 'sendgrid'])
        ->assertFailed();
});
