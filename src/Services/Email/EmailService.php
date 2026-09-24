<?php

namespace Devlab\LaravelMailer\Services\Email;

use Devlab\LaravelMailer\Data\EmailMessage;
use Devlab\LaravelMailer\Models\EmailSender;
use Devlab\LaravelMailer\Services\Email\EmailProviderFactory;

class EmailService
{
    public function __construct(
        private EmailProviderFactory $factory,
    ) {
    }

    public function send(
        EmailSender $account,
        EmailMessage $message
    ): void {
        $provider = $this->factory->make($account);

        $provider->send(
            $account,
            $message
        );
    }
}
