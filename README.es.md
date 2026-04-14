<p align="center">
  <a href="https://dev-lab.es">
    <img src="https://dev-lab.es/assets/logos/main-light.svg" alt="Devlab Logo" width="400"/>
  </a>
</p>

# Laravel Mailer

Paquete Laravel para envío avanzado de emails con soporte de múltiples remitentes SMTP, registro de emails y gestión de adjuntos.

## Resumen

- Registra un canal personalizado para enviar notificaciones mediante SMTP configurables por remitente.
- Guarda en base de datos los emails enviados (cuerpo, meta, estado) y los adjuntos en `storage/app/attachments/...`.
- Permite configurar remitentes SMTP desde el comando interactivo o mediante `.env` y un seeder.

## Instalación

Instala el paquete vía Composer:

```bash
composer require devlab-studio/laravel-mailer
```

Publica (si aplica) la configuración y ejecuta migraciones del paquete:

```bash
php artisan vendor:publish --tag=laravel-mailer-config
php artisan migrate
```

## Configuración SMTP (.env)

Antes de usar el paquete, rellena las credenciales SMTP en tu archivo `.env` o usa el comando interactivo:

- `MAIL_MAILER=smtp`
- `MAIL_HOST=tu.smtp.host`
- `MAIL_PORT=587`
- `MAIL_USERNAME=usuario@smtp`
- `MAIL_PASSWORD=secret`
- `MAIL_ENCRYPTION=tls`  # tls, ssl o null
- `MAIL_FROM_ADDRESS=from@example.com`
- `MAIL_FROM_NAME="Tu Nombre"`

Si prefieres configurar interactivamente (el comando guardará valores en runtime y ejecutará el seeder):

```bash
php artisan laravel-mailer
```

Esto te pedirá host, puerto, protocolo, usuario, contraseña, dirección y nombre del remitente y ejecutará `EmailSendersTableSeeder`.

## Seeder de remitentes

El seeder `EmailSendersTableSeeder` inserta un remitente en la tabla `email_senders` usando la configuración de `mail` actual (habitualmente tomada de `.env`). Puedes ejecutarlo manualmente:

```bash
php artisan db:seed --class=\\Devlab\\LaravelMailer\\Database\\Seeders\\EmailSendersTableSeeder
```

Archivo: database/seeders/EmailSendersTableSeeder.php

## Canal personalizado y flujo de envío

El canal principal es `CustomMailChannel` (en `src/CustomMail/CustomMailChannel.php`) y hace lo siguiente:

- Obtiene el destinatario desde el `notifiable`. Soporta `AnonymousNotifiable`.
- Determina el remitente (`from`) desde el mensaje o desde la configuración (`devlab.MAIL_FROM_ADDRESS`).
- Registra el email en la tabla `emails` guardando cuerpo HTML, asunto, to/cc/bcc y metadatos.
- Guarda adjuntos en `storage/app/attachments/YYYY/M/D/` y crea registros en `emails_attachments`.
- Selecciona el mailer a usar consultando la tabla `email_senders`:
  - Si el remitente existe en la BD, crea dinámicamente un mailer `custom{ID}` con las credenciales (contraseña desencriptada) y lo usa.
  - En caso contrario usa el mailer `smtp` por defecto.
- Envía el mensaje usando el mailer seleccionado y actualiza el registro del email con estado, fecha de envío y errores si los hay.

Los archivos clave:

- `src/CustomMail/CustomMailChannel.php`
- `src/Models/Email.php` (registro y filtros)
- `src/Models/EmailsAttachment.php` (metadatos de adjuntos)
- `src/Models/EmailSender.php` (remitentes SMTP)

## Estructura de base de datos

El paquete incluye migraciones en `database/migrations` para crear las tablas:

- `email_senders` — remitentes SMTP y credenciales (contraseña encriptada)
- `emails` — registros de emails enviados (cuerpo, estado, to/cc/bcc, sent_at, etc.)
- `emails_attachments` — metadatos de adjuntos y rutas en `storage`

Revisa las migraciones en `database/migrations` para ver campos exactos.

## Almacenamiento de adjuntos

Adjuntos físicos se copian a `storage/app/attachments/{year}/{month}/{day}/` y se registra su ruta en la BD. El canal soporta:

- `Illuminate\\Mail\\Attachment`
- `Illuminate\\Http\\UploadedFile`
- Rutas en disco (strings)

## Uso (ejemplo rápido)

Dentro de una notificación, implementa `toCustomMail()` y devuelve un `Mailable` o `MailMessage` con `attachments` y `rawAttachments` si hace falta. Al enviar la notificación, el paquete:

```php
// Notificación
public function toCustomMail($notifiable)
{
    $mailable = new \\Illuminate\\Mail\\Mailable();
    // configurar vista, subject, attachments, etc.
    return $mailable;
}

// Enviar
$user->notify(new \\App\\Notifications\\MiNotificacion());
```

## Errores y logging

En caso de excepción durante el envío, el canal captura el error, guarda el mensaje en el registro del email y escribe en el log de aplicación:

- Busca en los logs con `Log::error('CustomMailChannel error: ...')`

## Buenas prácticas y seguridad

- Asegúrate de que las credenciales SMTP en la base de datos están encriptadas (el seeder usa `encrypt()` al insertar).
- Protege el acceso a la tabla `email_senders` si tus usuarios pueden editar remitentes.

## Comandos útiles

```bash
# Configurar interactivamente y ejecutar seeder
php artisan laravel-mailer

# Ejecutar solo el seeder
php artisan db:seed --class=\\Devlab\\LaravelMailer\\Database\\Seeders\\EmailSendersTableSeeder

# Migraciones (si no se han ejecutado)
php artisan migrate
```

## Archivo principal del paquete

El Service Provider registra config, migrations y el comando:

- `src/LaravelMailerServiceProvider.php`

## Soporte

- Sitio: https://dev-lab.es/contact

---

© 2026 Devlab Studio
