<?php

use Devlab\LaravelMailer\Data\EmailMessage;
use Devlab\LaravelMailer\Models\EmailSender;
use Devlab\LaravelMailer\Services\Email\GoogleEmailProvider;

function buildGmailRawMessage(EmailMessage $message): string
{
    $account = new EmailSender;
    $account->address = 'roberto.simon@dev-lab.es';

    return (fn () => $this->buildRawMessage($account, $message))->call(new GoogleEmailProvider);
}

function rawHeader(string $raw, string $name): ?string
{
    preg_match('/^'.preg_quote($name, '/').': (.*)$/m', $raw, $matches);

    return isset($matches[1]) ? rtrim($matches[1], "\r") : null;
}

it('encodes a non-ascii subject so gmail shows it correctly', function () {
    $raw = buildGmailRawMessage(new EmailMessage(
        to: 'dest@example.com',
        replyTo: '',
        subject: 'Prueba de envío · roberto.simon@dev-lab.es',
        html: '<p>Hola</p>',
    ));

    $subject = rawHeader($raw, 'Subject');

    expect(mb_check_encoding($subject, 'ASCII'))->toBeTrue()
        ->and(mb_decode_mimeheader($subject))->toBe('Prueba de envío · roberto.simon@dev-lab.es');
});

it('leaves an ascii subject untouched', function () {
    $raw = buildGmailRawMessage(new EmailMessage(
        to: 'dest@example.com',
        replyTo: '',
        subject: 'Factura 2026-10',
        html: '<p>Hola</p>',
    ));

    expect(rawHeader($raw, 'Subject'))->toBe('Factura 2026-10');
});

it('encodes non-ascii attachment names', function () {
    $raw = buildGmailRawMessage(new EmailMessage(
        to: 'dest@example.com',
        replyTo: '',
        subject: 'Adjunto',
        html: '<p>Hola</p>',
        attachments: [['name' => 'factura_año.pdf', 'content' => 'pdf', 'mime' => 'application/pdf']],
    ));

    preg_match('/filename="([^"]+)"/', $raw, $matches);

    expect(mb_decode_mimeheader($matches[1]))->toBe('factura_año.pdf');
});
