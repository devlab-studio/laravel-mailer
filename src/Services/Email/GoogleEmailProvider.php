<?php

namespace Devlab\LaravelMailer\Services\Email;

use Devlab\LaravelMailer\Contracts\EmailProvider;
use Devlab\LaravelMailer\Data\EmailMessage;
use Devlab\LaravelMailer\Models\EmailSender;
use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Illuminate\Support\Facades\Cache;

class GoogleEmailProvider implements EmailProvider
{
    public function send(
        EmailSender $account,
        EmailMessage $message
    ): void {
        $client = $this->client($account);

        $gmail = new Gmail($client);

        $rawMessage = $this->buildRawMessage(
            $account,
            $message
        );

        $gmailMessage = new Message();

        $gmailMessage->setRaw(
            $this->base64UrlEncode($rawMessage)
        );

        $gmail->users_messages->send(
            'me',
            $gmailMessage
        );
    }

    public function refreshAccessToken(
        EmailSender $account
    ): void {
        $client = $this->client($account, true);

        $token = $client->fetchAccessTokenWithRefreshToken(
            $account->mailer_data['refresh_token']
        );

        if (isset($token['access_token'])) {
            $mailer_data = $account->mailer_data;
            $mailer_data['refresh_token'] = $token['refresh_token'] ?? $account->mailer_data['refresh_token'];
            $mailer_data['access_token'] = $token['access_token'] ?? $account->mailer_data['access_token'];
            $mailer_data['expires_at'] = now()->addSeconds($token['expires_in'] ?? 3600);
            $account->mailer_data = $mailer_data;
            $account->save();
        }
    }

    private function client(
        EmailSender $account,
        bool $refresh = false,
    ): Client {
        $client = new Client();

        $client->setClientId(
            $account->mailer_data['client_id'] ?? ''
        );

        $client->setClientSecret(
            $account->mailer_data['client_secret'] ?? ''
        );

        $client->setAccessToken([
            'access_token' => $account->mailer_data['access_token'] ?? '',
            'expires_in' => max(
                0,
                now()->diffInSeconds($account->mailer_data['expires_at'] ?? now(), false)
            ),
        ]);

        if (!$refresh) {
            Cache::lock("email-account:{$account->id}:token-refresh", 10)
                ->block(2, function () use ($client, $account, $refresh) {
                if (
                    (!$account->mailer_data['expires_at'] ||
                    now()->isAfter($account->mailer_data['expires_at']))
                ) {
                    $this->refreshAccessToken($account);

                    $account->fresh();
                    $client->setAccessToken([
                        'access_token' => $account->mailer_data['access_token'],
                    ]);
                }
            });
        }

        return $client;
    }

    private function buildRawMessage(
        EmailSender $account,
        EmailMessage $message
    ): string {
        $headers = [
            'From: ' . $account->email,
            'To: ' . $message->to,
            'Subject: ' . $message->subject,
            'MIME-Version: 1.0',
        ];

        if (empty($message->attachments)) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';

            return implode("\r\n", $headers)
                . "\r\n\r\n"
                . $message->html;
        }

        $boundary = uniqid('boundary_', true);

        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

        $body = "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
            . $message->html . "\r\n";

        foreach ($message->attachments as $attachment) {
            $body .= "--{$boundary}\r\n"
                . $this->buildAttachmentPart($attachment);
        }

        $body .= "--{$boundary}--";

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private function buildAttachmentPart(array $attachment): string
    {
        $path = $attachment['path'] ?? null;
        $name = $attachment['name'] ?? ($path ? basename($path) : 'attachment');
        $content = $attachment['content'] ?? ($path && is_readable($path) ? file_get_contents($path) : '');
        $mimeType = $attachment['mime']
            ?? ($path && is_readable($path) ? (mime_content_type($path) ?: null) : null)
            ?? 'application/octet-stream';

        return 'Content-Type: ' . $mimeType . '; name="' . $name . '"' . "\r\n"
            . 'Content-Disposition: attachment; filename="' . $name . '"' . "\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($content))
            . "\r\n";
    }

    private function base64UrlEncode(
        string $data
    ): string {
        return rtrim(
            strtr(
                base64_encode($data),
                '+/',
                '-_'
            ),
            '='
        );
    }
}
