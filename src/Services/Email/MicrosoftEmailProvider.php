<?php

namespace Devlab\LaravelMailer\Services\Email;

use Devlab\LaravelMailer\Contracts\EmailProvider;
use Devlab\LaravelMailer\Data\EmailMessage;
use Devlab\LaravelMailer\Models\EmailSender;
use Illuminate\Support\Facades\Http;

class MicrosoftEmailProvider implements EmailProvider
{
    public function send(
        EmailSender $account,
        EmailMessage $message
    ): void {
        $this->ensureValidToken($account);

         $payload = [
            'subject' => $message->subject,

            'body' => [
                'contentType' => 'HTML',
                'content' => $message->html,
            ],

            'toRecipients' => [
                [
                    'emailAddress' => [
                        'address' => $message->to,
                    ],
                ],
            ],
        ];

        if (!empty($message->attachments)) {
            $payload['attachments'] = array_map(
                fn (array $attachment) => $this->buildAttachment($attachment),
                $message->attachments
            );
        }

        $response = Http::withToken(
            $account->mailer_data['access_token']
        )->post(
            'https://graph.microsoft.com/v1.0/me/sendMail',
            [
                'message' => $payload,
            ]
        );

        $response->throw();
    }

    private function buildAttachment(array $attachment): array
    {
        $path = $attachment['path'] ?? null;
        $name = $attachment['name'] ?? ($path ? basename($path) : 'attachment');
        $content = $attachment['content'] ?? ($path && is_readable($path) ? file_get_contents($path) : '');
        $mimeType = $attachment['mime']
            ?? ($path && is_readable($path) ? (mime_content_type($path) ?: null) : null)
            ?? 'application/octet-stream';

        return [
            '@odata.type' => '#microsoft.graph.fileAttachment',
            'name' => $name,
            'contentType' => $mimeType,
            'contentBytes' => base64_encode($content),
        ];
    }

    public function refreshAccessToken(
        EmailSender $account
    ): void {
        $response = Http::asForm()->post(
            'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            [
                'client_id' => $account->mailer_data['client_id'] ?? '',
                'client_secret' => $account->mailer_data['client_secret'] ?? '',
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->mailer_data['refresh_token'],
                'scope' => 'https://graph.microsoft.com/.default',
            ]
        );

        $response->throw();

        $data = $response->json();

        $mailer_data = $account->mailer_data;
        $mailer_data['refresh_token'] = $data['refresh_token'] ?? $account->mailer_data['refresh_token'];
        $mailer_data['access_token'] = $data['access_token'] ?? $account->mailer_data['access_token'];
        $mailer_data['expires_at'] = now()->addSeconds($data['expires_in'] ?? 3600);
        $account->mailer_data = $mailer_data;
        $account->save();
    }

    private function ensureValidToken(
        EmailSender $account
    ): void {
        if (
            !$account->mailer_data['expires_at'] ||
            now()->isAfter($account->mailer_data['expires_at'])
        ) {
            $this->refreshAccessToken($account);
        }
    }
}
