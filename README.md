<br></br>
<p align="center">
  <a href="https://dev-lab.es">
    <img src="https://dev-lab.es/assets/logos/main-light.svg" alt="Devlab Logo" width="400"/>
  </a>
</p>
<br></br>


**Laravel Mailer** es un paquete Laravel para el envío avanzado de emails, gestión de adjuntos y configuración flexible de remitentes SMTP.

- Envío de emails personalizados y con adjuntos
- Gestión de múltiples remitentes SMTP
- Seeders para datos iniciales de email
- Helpers y traits para integración rápida


## Instalación


Instala el paquete vía composer:

```bash
composer require devlab-studio/laravel-mailer
```

Configura el usuario y contraseña SMTP ejecutando:

```bash
php artisan laravel-mailer
```

### Ejecutar Seeder de Remitentes

Para poblar la tabla de mailers con datos iniciales, ejecuta el siguiente comando:

```bash
php artisan db:seed --class=EmailSendersTableSeeder
```

## Recursos


- [Soporte y contacto](https://dev-lab.es/contact)


---

<div align="center">
  © 2026 <a href="https://dev-lab.es">Devlab Studio</a>
</div>
