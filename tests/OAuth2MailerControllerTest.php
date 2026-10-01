<?php

use Devlab\LaravelMailer\Models\EmailSender;
use Devlab\LaravelMailer\Services\Email\MicrosoftEmailProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    config()->set('app.url', 'https://app.example.com');

    $this->sender = new EmailSender;
    $this->sender->forceFill([
        'address' => 'gmail@example.com',
        'name' => 'Gmail',
        'server' => '',
        'port' => 0,
        'auth_protocol' => '',
        'auth_user' => 'gmail@example.com',
        'auth_password' => '',
        'mailer' => 'google',
        'mailer_data' => [
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
        ],
    ])->save();
});

it('redirects to google and remembers the sender in session', function () {
    $response = $this->get('/auth/google-mail/login?sender_email=gmail%40example.com');

    $response->assertRedirectContains('https://accounts.google.com/o/oauth2/auth?client_id=client-id')
        ->assertRedirectContains('redirect_uri=https://app.example.com/auth/google-mail/callback')
        ->assertSessionHas('sender_email', 'gmail@example.com');
});

it('stores the tokens on callback', function () {
    Http::fake([
        'www.googleapis.com/*' => Http::response([
            'access_token' => 'new-access',
            'refresh_token' => 'new-refresh',
            'expires_in' => 3600,
        ]),
    ]);

    $this->withSession(['sender_email' => 'gmail@example.com'])
        ->get('/auth/google-mail/callback?code=auth-code')
        ->assertOk();

    Http::assertSent(fn ($request) => $request['code'] === 'auth-code'
        && $request['redirect_uri'] === 'https://app.example.com/auth/google-mail/callback');

    expect($this->sender->refresh()->mailer_data)
        ->access_token->toBe('new-access')
        ->refresh_token->toBe('new-refresh')
        ->expires_at->not->toBeNull();
});

it('uses the web middleware group', function () {
    expect(app('router')->getRoutes()->getByName('auth.google-mail-login')->gatherMiddleware())
        ->toContain('web');
});

function microsoftSender(array $mailerData = []): EmailSender
{
    $sender = new EmailSender;
    $sender->forceFill([
        'address' => 'ms@example.com',
        'name' => 'Microsoft',
        'server' => '',
        'port' => 0,
        'auth_protocol' => '',
        'auth_user' => 'ms@example.com',
        'auth_password' => '',
        'mailer' => 'microsoft',
        'mailer_data' => array_merge([
            'client_id' => 'ms-client-id',
            'client_secret' => 'ms-client-secret',
            'tenant_id' => 'tenant-id',
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
        ], $mailerData),
    ])->save();

    return $sender;
}

it('redirects to the microsoft tenant and remembers the sender in session', function () {
    microsoftSender();

    $this->get('/auth/microsoft-mail/login?sender_email=ms%40example.com')
        ->assertRedirectContains('https://login.microsoftonline.com/tenant-id/oauth2/v2.0/authorize?client_id=ms-client-id')
        ->assertRedirectContains('redirect_uri=https://app.example.com/auth/microsoft-mail/callback')
        ->assertSessionHas('sender_email', 'ms@example.com');
});

it('falls back to the common tenant when none is stored', function () {
    microsoftSender(['tenant_id' => null]);

    $this->get('/auth/microsoft-mail/login?sender_email=ms%40example.com')
        ->assertRedirectContains('https://login.microsoftonline.com/common/oauth2/v2.0/authorize');
});

it('stores the microsoft tokens on callback', function () {
    $sender = microsoftSender();

    Http::fake([
        'login.microsoftonline.com/tenant-id/oauth2/v2.0/token' => Http::response([
            'access_token' => 'ms-access',
            'refresh_token' => 'ms-refresh',
            'expires_in' => 3600,
        ]),
    ]);

    $this->withSession(['sender_email' => 'ms@example.com'])
        ->get('/auth/microsoft-mail/callback?code=auth-code')
        ->assertOk();

    Http::assertSent(fn ($request) => $request['code'] === 'auth-code'
        && $request['redirect_uri'] === 'https://app.example.com/auth/microsoft-mail/callback');

    expect($sender->refresh()->mailer_data)
        ->access_token->toBe('ms-access')
        ->refresh_token->toBe('ms-refresh')
        ->expires_at->not->toBeNull();
});

it('refreshes the microsoft token against the stored tenant', function () {
    $sender = microsoftSender(['refresh_token' => 'old-refresh']);

    Http::fake([
        'login.microsoftonline.com/tenant-id/oauth2/v2.0/token' => Http::response([
            'access_token' => 'refreshed-access',
            'expires_in' => 3600,
        ]),
    ]);

    app(MicrosoftEmailProvider::class)->refreshAccessToken($sender);

    expect($sender->refresh()->mailer_data)
        ->access_token->toBe('refreshed-access')
        ->refresh_token->toBe('old-refresh');
});

function fakeIdToken(array $claims): string
{
    $encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

    return $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode($claims).'.signature';
}

it('overwrites the sender identity with the authorized google account', function () {
    Http::fake([
        'www.googleapis.com/*' => Http::response([
            'access_token' => 'new-access',
            'refresh_token' => 'new-refresh',
            'expires_in' => 3600,
            'id_token' => fakeIdToken(['email' => 'real.account@gmail.com', 'name' => 'Cuenta Real']),
        ]),
    ]);

    $this->withSession(['sender_email' => 'gmail@example.com'])
        ->get('/auth/google-mail/callback?code=auth-code')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Cuenta Real <real.account@gmail.com>', false);

    expect($this->sender->refresh())
        ->address->toBe('real.account@gmail.com')
        ->auth_user->toBe('real.account@gmail.com')
        ->name->toBe('Cuenta Real')
        ->and($this->sender->mailer_data['refresh_token'])->toBe('new-refresh');
});

it('overwrites the sender identity with the authorized microsoft account', function () {
    $sender = microsoftSender();

    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'access_token' => 'ms-access',
            'refresh_token' => 'ms-refresh',
            'expires_in' => 3600,
            'id_token' => fakeIdToken(['preferred_username' => 'roberto@empresa.onmicrosoft.com', 'name' => 'Roberto Simón']),
        ]),
    ]);

    $this->withSession(['sender_email' => 'ms@example.com'])
        ->get('/auth/microsoft-mail/callback?code=auth-code')
        ->assertOk();

    expect($sender->refresh())
        ->address->toBe('roberto@empresa.onmicrosoft.com')
        ->name->toBe('Roberto Simón');
});

it('rejects the authorization when the account is already another sender', function () {
    microsoftSender(['client_id' => 'other']);
    EmailSender::where('address', 'ms@example.com')->update(['mailer' => 'google']);

    Http::fake([
        'www.googleapis.com/*' => Http::response([
            'access_token' => 'new-access',
            'refresh_token' => 'new-refresh',
            'expires_in' => 3600,
            'id_token' => fakeIdToken(['email' => 'ms@example.com', 'name' => 'Duplicada']),
        ]),
    ]);

    $this->withSession(['sender_email' => 'gmail@example.com'])
        ->get('/auth/google-mail/callback?code=auth-code')
        ->assertStatus(409);

    expect($this->sender->refresh())
        ->address->toBe('gmail@example.com')
        ->and($this->sender->mailer_data['refresh_token'])->toBeNull();
});

it('keeps the sender identity when no id_token is returned', function () {
    Http::fake([
        'www.googleapis.com/*' => Http::response([
            'access_token' => 'new-access',
            'refresh_token' => 'new-refresh',
            'expires_in' => 3600,
        ]),
    ]);

    $this->withSession(['sender_email' => 'gmail@example.com'])
        ->get('/auth/google-mail/callback?code=auth-code')
        ->assertOk();

    expect($this->sender->refresh())
        ->address->toBe('gmail@example.com')
        ->name->toBe('Gmail');
});
