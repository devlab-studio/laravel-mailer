<?php

namespace Devlab\LaravelMailer\Classes;

use Devlab\LaravelMailer\Models\EmailSender;

class CustomMailer {

    public static function setMailer($from) {
        $default_mailer = ($from != config('devlab.MAIL_FROM_ADDRESS')) ? false : true;
        $mailer = 'smtp';

        if (!$default_mailer) {
            $sender = EmailSender::where('address', $from)->get()->first();
            if ($sender) {
                config([
                    'mail.mailers.custom'.$sender->id => [
                        'transport' => 'smtp',
                        'host' => $sender->server,
                        'port' => $sender->port,
                        'username' => decrypt($sender->auth_user),
                        'password' => decrypt($sender->auth_password),
                        'encryption' => $sender->auth_protocol,
                    ],
                ]);
                $mailer = 'custom'.$sender->id;
            }
        }
        return $mailer;
    }
}
