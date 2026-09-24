<?php

namespace Devlab\LaravelMailer\Services\Email;

use Devlab\LaravelMailer\Contracts\EmailProvider;
use Devlab\LaravelMailer\Models\EmailSender;
use Devlab\LaravelMailer\Services\Email\GoogleEmailProvider;
use Devlab\LaravelMailer\Services\Email\MicrosoftEmailProvider;
use InvalidArgumentException;

class EmailProviderFactory
{
    public function make(
        EmailSender $account
    ): EmailProvider {
        return match ($account->mailer) {
            'google' => app(GoogleEmailProvider::class),

            'microsoft' => app(MicrosoftEmailProvider::class),

            default => throw new InvalidArgumentException(
                "Proveedor desconocido: {$account->mailer}"
            ),
        };
    }
}
