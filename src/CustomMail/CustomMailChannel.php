<?php

namespace Devlab\LaravelMailer\CustomMail;


use Devlab\LaravelMailer\CustomMail\Exceptions\EmptyDestinationsException;
use Devlab\LaravelMailer\Models\Email;
use Devlab\LaravelMailer\Models\EmailsAttachment;
use Devlab\LaravelMailer\Models\EmailSender;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class CustomMailChannel
{
    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $message = $notification->toCustomMail($notifiable);
        // dd($message);

        $to = get_class($notifiable) == AnonymousNotifiable::class
            ? $notifiable->routes['mail'] ?? null : $notifiable->email;

        if (app()->environment('local') && config('devlab.MAIL_DEV_TO')) {
            $to = config('devlab.MAIL_DEV_TO');
        }

        if (get_class($message) == Mailable::class) {
            $from = $message->from[0]['address'] ?? null;
        }
        if (get_class($message) == MailMessage::class) {
            $from = $message->from[0] ?? null;
        }

        if (empty($from)) {
            $from = config('devlab.MAIL_FROM_ADDRESS');
        }

        $bd_email = $this->logMail($from, $to, $message);

        if (empty($to) && empty($message->cc) && empty($message->bcc)) {
            $bd_email->error = 'No destinations provided for the notification.';
            $bd_email->sent = 1;
            $bd_email->retries = 1;
            $bd_email->state = -1;
            $bd_email->sent_at = now();
            $bd_email->queued_at = now();
            $bd_email->save();
            throw EmptyDestinationsException::emptyDestinationsError();
        }

        // Send notification to the $notifiable instance...
        $mailer_info = $this->setMailer($from ?? null);
        $mailer = Mail::mailer($mailer_info['mailer']);
        $from_name = $mailer_info['from_name'];
        try{
            $mailer->send($message->view, $message->viewData, function ($mail) use ($from, $from_name, $to, $message, $bd_email) {
                $mail->from($from, $from_name);
                if(!empty($to)) {
                    if (is_string($to)) {
                        $address = str_replace(',', ';', $to);
                        $address = str_replace(' ', '', $address);
                        $address = explode(';', $address);
                    } else {
                        $address = $to;
                    }
                    $mail->to($address);
                }
                if(!empty($message->cc)){
                    if (is_string($message->cc)) {
                        $address = str_replace(',', ';', $message->cc);
                        $address = str_replace(' ', '', $address);
                        $address = explode(';', $address);
                    } else {
                        $address = $message->cc;
                    }
                    $mail->cc($address);
                }
                if(!empty($message->bcc)){
                    if (is_string($message->bcc)) {
                        $address = str_replace(',', ';', $message->bcc);
                        $address = str_replace(' ', '', $address);
                        $address = explode(';', $address);
                    } else {
                        $address = $message->bcc;
                    }
                    $mail->bcc($address);
                }
                $mail->subject($message->subject ?? null);
                foreach ($bd_email->attachments as $attachment) {
                    $mail->attach(Storage::path($attachment->path), [
                        'as' => $attachment->name,
                        'mime' => $attachment->mime_type,
                    ]);
                }
            });
        } catch(\Exception $e) {
            $bd_email->error = $e->getMessage();
            $bd_email->sent = 1;
            $bd_email->retries = 1;
            $bd_email->state = -1;
            $bd_email->sent_at = now();
            $bd_email->queued_at = now();
            $bd_email->save();
            Log::error('CustomMailChannel error: '.$e->getMessage());
            return;
        }

        $bd_email->sent = 1;
        $bd_email->retries = 1;
        $bd_email->state = 1;
        $bd_email->sent_at = now();
        $bd_email->queued_at = now();
        $bd_email->save();
    }

    protected function logMail($from, $to, $message)
    {

        $htmlBody = (string) $message->render();

        $bd_email = new Email();
        $bd_email->send_at = now();
        $bd_email->sent = 0;
        $bd_email->retries = 0;
        $bd_email->error = null;
        $bd_email->from = $from ?? null;
        $bd_email->to = is_array($to) ? implode(';', $to) : $to;
        $bd_email->cc = empty($message->cc) ? null : implode(';', $message->cc);
        $bd_email->bcc = empty($message->bcc) ? null : implode(';', $message->bcc);

        $bd_email->body = $htmlBody;
        $bd_email->subject = $message->subject ?? null;
        $bd_email->created_user = config('constants.users.system');
        $bd_email->save();

        $path = 'private/attachments/' . today()->year . '/'  . today()->month . '/'. today()->day;
        if (!Storage::exists($path)){
            Storage::makeDirectory($path);
        }

        foreach ($message->attachments as $attachment) {
            // dd($attachment);
            if ($attachment['file'] instanceof \Illuminate\Mail\Attachment) {
                $file_path = $attachment['file']->getFilePath();
                $file_name = $attachment['file']->getName();
                $mime_type = $attachment['file']->getMimeType();

                $attachment_path = $path . '/' .now()->format('His') . '-' . $file_name;
                Storage::copy($this->getCleanPath($file_path), $attachment_path);
            } elseif ($attachment['file'] instanceof \Illuminate\Http\UploadedFile) {
                $file_path = $attachment['file']->getPathname();
                $file_name = $attachment['file']->getClientOriginalName();
                $mime_type = $attachment['file']->getClientMimeType();

                $attachment_path = $path . '/' .now()->format('His') . '-' . $file_name;
                Storage::copy($this->getCleanPath($file_path), $attachment_path);
            } elseif (is_string($attachment['file'])) {
                $file_path = $attachment['file'];
                $file_name = basename($attachment['file']);
                $mime_type = mime_content_type($attachment['file']);

                $attachment_path = $path . '/' .now()->format('His') . '-' . $file_name;
                Storage::copy($this->getCleanPath($file_path), $attachment_path);
            } else {

                continue; // Unknown attachment type
            }

            $attachment = new EmailsAttachment();
            $attachment->name = $file_name;
            $attachment->path = $attachment_path;
            $attachment->extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $attachment->mime_type = $mime_type;
            $attachment->created_user = config('constants.users.system');
            $attachment->email_id = $bd_email->id;
            $attachment->save();
        }
        foreach ($message->rawAttachments as $attachment) {
            if (isset($attachment['data'])) {
                $file_name = $attachment['name'];
                $mime_type = $attachment['options']['mime'] ?? 'application/octet-stream';

                $attachment_path = $path . '/' .now()->format('His') . '-' . $file_name;
                Storage::put($attachment_path, $attachment['data']);

                $attachment = new EmailsAttachment();
                $attachment->name = $file_name;
                $attachment->path = $attachment_path;
                $attachment->extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $attachment->mime_type = $mime_type;
                $attachment->created_user = config('constants.users.system');
                $attachment->email_id = $bd_email->id;
                $attachment->save();
            }
        }


        return $bd_email;
    }

    protected function setMailer($from)
    {
        $mailer_name = 'smtp';
        $from_name = config('devlab.MAIL_FROM_NAME');

        $sender = EmailSender::where('address', $from)->get()->first();
        if ($sender) {
            config([
                'mail.mailers.custom'.$sender->id => [
                    'transport' => 'smtp',
                    'host' => $sender->server,
                    'port' => $sender->port,
                    'username' => $sender->auth_user,
                    'password' => decrypt($sender->auth_password),
                    'encryption' => $sender->auth_protocol,
                ],
            ]);
            $mailer_name = 'custom'.$sender->id;
            $from_name = $sender->name;
        }

        return [
            'mailer' => $mailer_name,
            'from_name' => $from_name,
        ];
    }

    protected function getCleanPath($file_path)
    {

        $base = rtrim(str_replace('\\', '/', base_path()), '/') . '/storage/app/';
        $full = str_replace('\\', '/', $file_path);

        if (str_starts_with($full, $base)) {
            $file_path = ltrim(substr($full, strlen($base)), '/');
        }

        return $file_path;
    }
}
