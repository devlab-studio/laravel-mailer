<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Prueba de envío</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="margin-bottom: 4px;">Correo de prueba de Laravel Mailer</h2>
    <p style="margin-top: 0;">Si estás leyendo esto, el envío ha funcionado.</p>

    <table cellpadding="6" style="border-collapse: collapse;">
        <tr><td><strong>Cuenta configurada</strong></td><td>{{ $sender->name }} &lt;{{ $sender->address }}&gt;</td></tr>
        <tr><td><strong>Mailer</strong></td><td>{{ $sender->mailer }}</td></tr>
        <tr><td><strong>ID en email_senders</strong></td><td>{{ $sender->id }}</td></tr>
        <tr><td><strong>Aplicación</strong></td><td>{{ config('app.name') }} ({{ config('app.url') }})</td></tr>
        <tr><td><strong>Enviado el</strong></td><td>{{ $sentAt }}</td></tr>
    </table>

    <p>Comprueba que el remitente que ves en tu cliente de correo coincide con la cuenta configurada.</p>
</body>
</html>
