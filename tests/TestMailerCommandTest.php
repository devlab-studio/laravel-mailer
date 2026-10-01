<?php

use Devlab\LaravelMailer\Models\Email;
use Devlab\LaravelMailer\Models\EmailSender;
use Devlab\LaravelMailer\Notifications\TestMailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

    $this->sender = new EmailSender;
    $this->sender->forceFill([
        'address' => 'ms@example.com',
        'name' => 'Roberto Simón',
        'server' => '',
        'port' => 0,
        'auth_protocol' => '',
        'auth_user' => 'ms@example.com',
        'auth_password' => '',
        'mailer' => 'microsoft',
        'mailer_data' => [
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'tenant_id' => 'tenant-id',
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'expires_at' => now()->addHour()->toIso8601String(),
        ],
    ])->save();
});

it('sends through the channel with the sender account mailer', function () {
    Http::fake(['graph.microsoft.com/*' => Http::response(null, 202)]);

    Notification::route('mail', 'dest@example.com')->notifyNow(new TestMailNotification($this->sender));

    Http::assertSent(fn ($request) => $request->url() === 'https://graph.microsoft.com/v1.0/me/sendMail'
        && $request->hasHeader('Authorization', 'Bearer access')
        && $request['message']['toRecipients'][0]['emailAddress']['address'] === 'dest@example.com'
        && $request['message']['subject'] === 'Prueba de envío · ms@example.com'
        && str_contains($request['message']['body']['content'], 'ms@example.com'));

    expect(Email::sole())
        ->from->toBe('ms@example.com')
        ->to->toBe('dest@example.com')
        ->state->toBe(1)
        ->error->toBeNull();
});

it('records the provider error when sending fails', function () {
    Http::fake(['graph.microsoft.com/*' => Http::response(['error' => ['code' => 'InvalidAuthenticationToken']], 401)]);

    Notification::route('mail', 'dest@example.com')->notifyNow(new TestMailNotification($this->sender));

    expect(Email::sole())
        ->state->toBe(-1)
        ->error->toContain('401');
});

it('sends a test mail from the command', function () {
    Http::fake(['graph.microsoft.com/*' => Http::response(null, 202)]);

    $this->artisan('mailer:test', ['--from' => 'ms@example.com', '--to' => 'dest@example.com'])
        ->expectsOutputToContain('Correo enviado correctamente.')
        ->expectsOutputToContain('Debería llegar desde ms@example.com')
        ->assertSuccessful();
});

it('lets you pick the sender and destination interactively', function () {
    Http::fake(['graph.microsoft.com/*' => Http::response(null, 202)]);

    $this->artisan('mailer:test')
        ->expectsQuestion('¿Desde qué cuenta quieres enviar?', $this->sender->id)
        ->expectsQuestion('Email de destino', 'dest@example.com')
        ->assertSuccessful();

    Http::assertSentCount(1);
});

it('reports the failure from the command', function () {
    Http::fake(['graph.microsoft.com/*' => Http::response(['error' => ['code' => 'InvalidAuthenticationToken']], 401)]);

    $this->artisan('mailer:test', ['--from' => 'ms@example.com', '--to' => 'dest@example.com'])
        ->expectsOutputToContain('El envío ha fallado')
        ->assertFailed();
});

it('fails when the mailer is not defined in config/mail.php', function () {
    config()->set('mail.mailers.microsoft', null);

    $this->artisan('mailer:test', ['--from' => 'ms@example.com', '--to' => 'dest@example.com'])
        ->expectsOutputToContain('mail.mailers.microsoft')
        ->assertFailed();
});

it('fails for an unknown sender', function () {
    $this->artisan('mailer:test', ['--from' => 'nobody@example.com', '--to' => 'dest@example.com'])
        ->expectsOutputToContain('No hay ninguna cuenta registrada con el email nobody@example.com')
        ->assertFailed();
});
