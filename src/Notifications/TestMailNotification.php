<?php

namespace Devlab\LaravelMailer\Notifications;

use Devlab\LaravelMailer\CustomMail\CustomMailChannel;
use Devlab\LaravelMailer\Models\EmailSender;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TestMailNotification extends Notification
{
    public function __construct(
        public EmailSender $sender,
    ) {}

    public function via(object $notifiable): array
    {
        return [CustomMailChannel::class];
    }

    public function toCustomMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->from($this->sender->address, $this->sender->name)
            ->subject("Prueba de envío · {$this->sender->address}")
            ->view('laravel-mailer::test-mail', [
                'sender' => $this->sender,
                'sentAt' => now()->format('d/m/Y H:i:s'),
            ]);

        // Formato que espera CustomMailChannel para Google y Microsoft
        $message->replyTo = '';
        $message->body = (string) $message->render();

        return $message;
    }
}
