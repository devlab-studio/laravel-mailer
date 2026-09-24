<?php

namespace Devlab\LaravelMailer\Contracts;

use Devlab\LaravelMailer\Data\EmailMessage;
use Devlab\LaravelMailer\Models\EmailSender;

interface EmailProvider
{
    public function send(
        EmailSender $account,
        EmailMessage $message
    ): void;

    public function refreshAccessToken(
        EmailSender $account
    ): void;
}
