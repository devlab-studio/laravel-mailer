
<p align="center">
  <a href="https://dev-lab.es">
    <img src="https://dev-lab.es/assets/logos/main-light.svg" alt="Devlab Logo" width="400"/>
  </a>
</p>

<p align="right">
  <a href="README.es.md"><img src="https://img.shields.io/badge/Espa%C3%B1ol-Ver%20en%20ES-blue.svg?style=flat-square" alt="Español"/></a>
</p>

# Laravel Mailer

Laravel package for advanced email sending with support for multiple SMTP senders, email logging and attachment management.

## Summary

- Registers a custom channel to send notifications via SMTP configured per sender.
- Persists sent emails (body, metadata, status) and attachments to the database and storage (`storage/app/attachments/...`).
- Allows configuring SMTP senders via an interactive command or using `.env` and a seeder.

## Installation

Install via Composer:

```bash
composer require devlab-studio/laravel-mailer
```

Publish configuration (if applicable) and run migrations:

```bash
php artisan vendor:publish --tag=laravel-mailer-config
php artisan migrate
```

## SMTP Configuration (.env)

Before using the package, fill in your SMTP credentials in `.env` or use the interactive command:

- `MAIL_MAILER=smtp`
- `MAIL_HOST=your.smtp.host`
- `MAIL_PORT=587`
- `MAIL_USERNAME=your@smtp.user`
- `MAIL_PASSWORD=secret`
- `MAIL_ENCRYPTION=tls`  # tls, ssl or null
- `MAIL_FROM_ADDRESS=from@example.com`
- `MAIL_FROM_NAME="Your Name"`

Or run the interactive setup (it will save runtime config and run the seeder):

```bash
php artisan laravel-mailer
```

The command will ask for host, port, protocol, user, password, sender address and name and will run `EmailSendersTableSeeder`.

## Senders Seeder

The `EmailSendersTableSeeder` inserts a sender into the `email_senders` table using current `mail` configuration (normally from `.env`). Run it manually:

```bash
php artisan db:seed --class=\\Devlab\\LaravelMailer\\Database\\Seeders\\EmailSendersTableSeeder
```

File: database/seeders/EmailSendersTableSeeder.php

## Custom channel and send flow

The main channel is `CustomMailChannel` (`src/CustomMail/CustomMailChannel.php`) and does the following:

- Retrieves the recipient from the `notifiable`. Supports `AnonymousNotifiable`.
- Resolves the sender (`from`) from the message or from config (`devlab.MAIL_FROM_ADDRESS`).
- Logs the email in the `emails` table storing HTML body, subject, to/cc/bcc and metadata.
- Stores attachments in `storage/app/attachments/YYYY/M/D/` and creates records in `emails_attachments`.
- Selects which mailer to use by querying `email_senders` table:
  - If the sender exists, it dynamically creates a mailer `custom{ID}` with the sender credentials (password decrypted) and uses it.
  - Otherwise it falls back to the default `smtp` mailer.
- Sends the message using the selected mailer and updates the email record with status, sent date and errors if any.

Key files:

- `src/CustomMail/CustomMailChannel.php`
- `src/Models/Email.php` (logging and filters)
- `src/Models/EmailsAttachment.php` (attachment metadata)
- `src/Models/EmailSender.php` (SMTP senders)

## Database structure

The package includes migrations in `database/migrations` to create:

- `email_senders` — SMTP senders and credentials (password encrypted)
- `emails` — records of sent emails (body, status, to/cc/bcc, sent_at, etc.)
- `emails_attachments` — attachment metadata and storage paths

Check `database/migrations` for exact columns.

## Attachments storage

Physical attachments are copied to `storage/app/attachments/{year}/{month}/{day}/` and the path is recorded in the DB. The channel supports:

- `Illuminate\\Mail\\Attachment`
- `Illuminate\\Http\\UploadedFile`
- Disk paths (strings)

## Usage (quick example)

Inside a notification, implement `toCustomMail()` and return a `Mailable` or `MailMessage` with attachments and rawAttachments when needed. When notifying, the package:

```php
// Notification
public function toCustomMail($notifiable)
{
    $mailable = new \\Illuminate\\Mail\\Mailable();
    // configure view, subject, attachments, etc.
    return $mailable;
}

// Send
$user->notify(new \\App\\Notifications\\MyNotification());
```

## Errors and logging

On exception during sending, the channel captures the error, stores it on the email record and writes to the application log:

- Look for logs with `Log::error('CustomMailChannel error: ...')`

## Best practices & security

- Ensure SMTP credentials stored in the database are encrypted (the seeder uses `encrypt()` during insert).
- Protect access to `email_senders` if users can modify senders.

## Useful commands

```bash
# Interactive setup and run seeder
php artisan laravel-mailer

# Run only the seeder
php artisan db:seed --class=\\Devlab\\LaravelMailer\\Database\\Seeders\\EmailSendersTableSeeder

# Run migrations (if not yet executed)
php artisan migrate
```

## Package entry file

The service provider registers config, migrations and the command:

- `src/LaravelMailerServiceProvider.php`

## Support

- Site: https://dev-lab.es/contact

---

© 2026 Devlab Studio
