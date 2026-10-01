<?php

namespace Devlab\LaravelMailer\Http\Controllers;

use Devlab\LaravelMailer\Models\EmailSender;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;


class OAuth2MailerController extends Controller
{
    public function googleMailLogin(Request $request)
    {
        $provider = 'google';
        $url = $this->getProviderUrls($provider, 'common', 'auth_url');
        if (empty($url)) {
            abort(500, 'Authorization URL not found for provider: ' . $provider);
        }

        $sender_email = $request->input('sender_email', null);
        if (empty($sender_email)) {
            abort(400, 'Sender email is required');
        }
        $sender = EmailSender::where('address', $sender_email)->first();
        if (empty($sender)) {
            abort(404, 'Sender not found');
        }

        session(['sender_email' => $sender_email]);
        $url = $url
            .'?client_id=' . $sender->mailer_data['client_id']
            .'&response_type=code'
            .'&scope=email%20profile%20openid%20https://www.googleapis.com/auth/gmail.send'
            .'&access_type=offline'
            .'&prompt=consent'
            .'&redirect_uri=' . rtrim(config('app.url'), '/') . '/auth/google-mail/callback'
            ;

            // dd($url);
        return response()->redirectTo($url);
    }
    public function googleMailCallback(Request $request)
    {
        $provider = 'google';
        $url = $this->getProviderUrls($provider, 'common', 'token_url');

        $sender_email = session('sender_email', null);
        if (empty($sender_email)) {
            abort(400, 'Sender email is required');
        }
        $sender = EmailSender::where('address', $sender_email)->first();
        if (empty($sender)) {
            abort(404, 'Sender not found');
        }

            // dd($url);
        $response = Http::asForm()->post($url, [
            'client_id' => $sender->mailer_data['client_id'],
            'client_secret' => $sender->mailer_data['client_secret'],
            'grant_type' => 'authorization_code',
            'code' => $request->input('code'),
            'redirect_uri' => rtrim(config('app.url'), '/') . '/auth/google-mail/callback'
        ]);

        $response_data = $response->json();

        if (isset($response_data['access_token'])) {
            $sender_data = $sender->mailer_data;
            $sender_data['refresh_token'] = $response_data['refresh_token'] ?? $sender->mailer_data['refresh_token'];
            $sender_data['access_token'] = $response_data['access_token'] ?? $sender->mailer_data['access_token'];
            $sender_data['expires_at'] = now()->addSeconds($response_data['expires_in'] ?? 3600);
            $sender->mailer_data = $sender_data;
            $this->applyAuthorizedIdentity($sender, $response_data);
            $sender->save();
        }
        return response('Mail authorization successful: ' . $sender->name . ' <' . $sender->address . '>', 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
    public function microsoftMailLogin(Request $request)
    {
        $provider = 'microsoft';

        $sender_email = $request->input('sender_email', null);
        if (empty($sender_email)) {
            abort(400, 'Sender email is required');
        }
        $sender = EmailSender::where('address', $sender_email)->first();
        if (empty($sender)) {
            abort(404, 'Sender not found');
        }

        $url = $this->getProviderUrls($provider, $sender->mailer_data['tenant_id'] ?? 'common', 'auth_url');
        if (empty($url)) {
            abort(500, 'Authorization URL not found for provider: ' . $provider);
        }

        session(['sender_email' => $sender_email]);
        $url = $url
            .'?client_id=' . $sender->mailer_data['client_id']
            .'&response_type=code'
            .'&scope=offline_access%20email%20profile%20openid%20https://graph.microsoft.com/Mail.Send'
            .'&response_mode=query'
            .'&prompt=consent'
            .'&redirect_uri=' . rtrim(config('app.url'), '/') . '/auth/microsoft-mail/callback'
            ;

            // dd($url);
        return response()->redirectTo($url);
    }
    public function microsoftMailCallback(Request $request)
    {
        $provider = 'microsoft';

        $sender_email = session('sender_email', null);
        if (empty($sender_email)) {
            abort(400, 'Sender email is required');
        }
        $sender = EmailSender::where('address', $sender_email)->first();
        if (empty($sender)) {
            abort(404, 'Sender not found');
        }

        $url = $this->getProviderUrls($provider, $sender->mailer_data['tenant_id'] ?? 'common', 'token_url');

            // dd($url);
        $response = Http::asForm()->post($url, [
            'client_id' => $sender->mailer_data['client_id'],
            'client_secret' => $sender->mailer_data['client_secret'],
            'grant_type' => 'authorization_code',
            'code' => $request->input('code'),
            'redirect_uri' => rtrim(config('app.url'), '/') . '/auth/microsoft-mail/callback'
        ]);

        $response_data = $response->json();

        if (isset($response_data['access_token'])) {
            $sender_data = $sender->mailer_data;
            $sender_data['refresh_token'] = $response_data['refresh_token'] ?? $sender->mailer_data['refresh_token'];
            $sender_data['access_token'] = $response_data['access_token'] ?? $sender->mailer_data['access_token'];
            $sender_data['expires_at'] = now()->addSeconds($response_data['expires_in'] ?? 3600);
            $sender->mailer_data = $sender_data;
            $this->applyAuthorizedIdentity($sender, $response_data);
            $sender->save();
        }
        return response('Mail authorization successful: ' . $sender->name . ' <' . $sender->address . '>', 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Sobrescribe email y nombre del remitente con los de la cuenta que ha autorizado,
     * que es desde la que Google y Microsoft envían realmente los correos.
     */
    private function applyAuthorizedIdentity(EmailSender $sender, array $response_data): void
    {
        $claims = $this->idTokenClaims($response_data['id_token'] ?? null);

        $email = $claims['email'] ?? $claims['preferred_username'] ?? null;
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $taken = EmailSender::where('address', $email)->whereKeyNot($sender->getKey())->exists();
            if ($taken) {
                abort(409, 'The account ' . $email . ' is already registered as another sender');
            }

            $sender->address = $email;
            $sender->auth_user = $email;
        }

        if (! empty($claims['name'])) {
            $sender->name = mb_substr($claims['name'], 0, 150);
        }
    }

    /**
     * El id_token llega directamente del endpoint de token por TLS, así que se puede
     * leer sin verificar la firma (OpenID Connect Core, 3.1.3.7).
     */
    private function idTokenClaims(?string $id_token): array
    {
        $payload = explode('.', (string) $id_token)[1] ?? null;
        if (empty($payload)) {
            return [];
        }

        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

        return is_array($claims) ? $claims : [];
    }

    private function getProviderUrls($provider, $tenant = 'common', $url_type = 'auth_url')
    {
        $provider_urls = [
            'google'=> [
                'auth_url' => 'https://accounts.google.com/o/oauth2/auth',
                'token_url' => 'https://www.googleapis.com/oauth2/v4/token'
            ],
            'microsoft' => [
                'auth_url' => "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize",
                'token_url' => "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
            ]
        ];

        return $provider_urls[$provider][$url_type] ?? null;
    }
}
