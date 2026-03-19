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
            'address' => config('mail.mailers.from.address'),
            'name' => config('mail.mailers.from.name'),
            'server' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'use_auth' => 1,
            'auth_protocol' => config('mail.mailers.smtp.encryption', 'tls'),
            'auth_user' => config('mail.mailers.smtp.username'),
            'auth_password' => encrypt(config('mail.mailers.smtp.password')),
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }
}
