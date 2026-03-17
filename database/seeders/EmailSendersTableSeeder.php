<?php

namespace Devlab\LaravelMailer\Database\Seeders;

use Illuminate\Database\Seeder;
use Devlab\LaravelMailer\Models\EmailSender;

class EmailSendersTableSeeder extends Seeder
{
    public function run()
    {
        EmailSender::create([
            'id' => 1,
            'address' => 'soporte@dev-lab.es',
            'name' => 'Soporte DevLab',
            'server' => 'email-smtp.eu-west-3.amazonaws.com',
            'port' => 587,
            'use_auth' => 1,
            'auth_protocol' => 'tls',
            'auth_user' => encrypt(config('mailer.EMAIL_AUTH_USER')),
            'auth_password' => encrypt(config('mailer.EMAIL_AUTH_PASSWORD')),
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }
}
