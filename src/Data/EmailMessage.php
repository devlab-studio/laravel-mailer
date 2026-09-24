<?php

namespace Devlab\LaravelMailer\Data;

final readonly class EmailMessage
{
    public function __construct(
        public string $to,
        public string $replyTo,
        public string $subject,
        public string $html,
        public ?string $text = null,
        public ?string $fromName = null,
        public array $cc = [],
        public array $bcc = [],
        public array $attachments = [],
    ) {
    }
}
