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
            .'&redirect_uri=' . env('APP_URL') . '/auth/google-mail/callback'
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
            'redirect_uri' => env('APP_URL') . '/auth/google-mail/callback'
        ]);

        $response_data = $response->json();

        if (isset($response_data['access_token'])) {
            $sender_data = $sender->mailer_data;
            $sender_data['refresh_token'] = $response_data['refresh_token'] ?? $sender->mailer_data['refresh_token'];
            $sender_data['access_token'] = $response_data['access_token'] ?? $sender->mailer_data['access_token'];
            $sender_data['expires_at'] = now()->addSeconds($response_data['expires_in'] ?? 3600);
            $sender->mailer_data = $sender_data;
            $sender->save();
        }
        return response('Mail authorization successful');
    }
    public function microsoftMailLogin(Request $request)
    {
        $provider = 'microsoft';
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
            .'&scope=offline_access%20email%20profile%20openid%20https://graph.microsoft.com/Mail.Send'
            .'&response_mode=query'
            .'&prompt=consent'
            .'&redirect_uri=' . env('APP_URL') . '/auth/microsoft-mail/callback'
            ;

            // dd($url);
        return response()->redirectTo($url);
    }
    public function microsoftMailCallback(Request $request)
    {
        $provider = 'microsoft';
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
            'redirect_uri' => env('APP_URL') . '/auth/microsoft-mail/callback'
        ]);

        $response_data = $response->json();

        if (isset($response_data['access_token'])) {
            $sender_data = $sender->mailer_data;
            $sender_data['refresh_token'] = $response_data['refresh_token'] ?? $sender->mailer_data['refresh_token'];
            $sender_data['access_token'] = $response_data['access_token'] ?? $sender->mailer_data['access_token'];
            $sender_data['expires_at'] = now()->addSeconds($response_data['expires_in'] ?? 3600);
            $sender->mailer_data = $sender_data;
            $sender->save();
        }
        return response('Mail authorization successful');
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
