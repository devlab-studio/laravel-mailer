<?php

use Devlab\LaravelMailer\Http\Controllers\OAuth2MailerController;
use Illuminate\Support\Facades\Route;

// OAuth2 mail login (necesita sesión para recordar el remitente entre login y callback)
Route::middleware('web')->group(function () {
    Route::get('/auth/microsoft-mail/login', [OAuth2MailerController::class, 'microsoftMailLogin'])->name('auth.microsoft-mail-login');
    Route::get('/auth/microsoft-mail/callback', [OAuth2MailerController::class, 'microsoftMailCallback'])->name('auth.microsoft-mail-callback');
    Route::get('/auth/google-mail/login', [OAuth2MailerController::class, 'googleMailLogin'])->name('auth.google-mail-login');
    Route::get('/auth/google-mail/callback', [OAuth2MailerController::class, 'googleMailCallback'])->name('auth.google-mail-callback');
});
