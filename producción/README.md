# Paquete de publicación de AlmacenaLite

Este paquete contiene la aplicación privada y dos copias equivalentes del document root:

```text
/laravel       Aplicación Laravel, dependencias y configuración privada
/public_html   Document root recomendado para Hostinger
/public_http   Copia alternativa si el document root se configura con este nombre
```

En Hostinger usa `public_html` como document root. Sube `laravel/` y `public_html/` como carpetas hermanas en el directorio de la cuenta; no subas la carpeta envolvente `producción/`. Si hPanel ya creó `public_html`, copia allí el **contenido** de la carpeta `public_html` del paquete. Usa `public_http` solo si cambiaste explícitamente el document root a ese nombre. Mantén `laravel` fuera del directorio público. Ambos `index.php` buscan `laravel` en rutas cercanas y validan `artisan`, `bootstrap/app.php` y `vendor/autoload.php`.

## Si el dominio muestra 404

Se comprobó `https://ivory-oryx-459040.hostingersite.com/`: Hostinger responde con su página genérica `404 This Page Does Not Exist`. También responden 404 `/index.php`, `/public_http/` y `/favicon.ico`. Esto ocurre antes de que Laravel arranque y normalmente indica que el sitio apunta a otra carpeta, o que los archivos del paquete todavía no están en la raíz pública configurada.

En hPanel, revisa la carpeta raíz/document root asignada a ese sitio y confirma que contiene `index.php`, `.htaccess` y `build/`. El document root habitual de Hostinger es `public_html`; el contenido de la carpeta pública debe estar directamente allí, no dentro de una subcarpeta `public_http`.

Después de subir los archivos, comprueba primero que `/favicon.ico` ya no devuelve el 404 genérico y que `/index.php` llega a Laravel. Si entonces aparece un error HTTP 500, revisa `laravel/storage/logs/laravel.log` y confirma que `.env` contiene el dominio real y las credenciales de Hostinger; los valores de ejemplo no permiten conectarse a la base de datos ni al correo.

## Antes de subir

1. En `laravel/.env`, `APP_URL` ya está configurado para `https://ivory-oryx-459040.hostingersite.com`. Reemplaza los valores de ejemplo de `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` y SMTP con los datos de hPanel. `DB_HOST` podría no ser `localhost`: usa el host MySQL que muestra hPanel.
2. No subas el `.env` a un repositorio ni lo copies dentro de `public_http`. La clave `APP_KEY` de este paquete ya fue generada; conserva la misma en despliegues posteriores. Si vuelves a generar la clave después de tener usuarios o datos cifrados, estos dejarán de poder leerse.
3. Usa PHP 8.2 o superior y habilita las extensiones requeridas por Laravel, PDO MySQL, OpenSSL, Mbstring, XML y Ctype.
4. Sube `laravel` y `public_html` al directorio de la cuenta, respetando sus nombres y relación de carpetas. Si usas `public_http` como document root personalizado, sube esa carpeta en vez de `public_html`. Los assets compilados están en `build/` dentro de cada raíz pública.
5. Asegura permisos de escritura para `laravel/storage` y `laravel/bootstrap/cache`; no concedas permisos de escritura al resto de la aplicación.

## Inicializar una base de datos nueva

Desde una terminal/SSH en `laravel`, ejecuta:

```sh
php artisan migrate --force
php artisan db:seed --class=AccessControlSeeder
php artisan app:create-first-user
php artisan optimize
```

El último comando de creación configura el primer usuario administrativo y solo funciona mientras no exista ningún usuario. La cola está configurada como síncrona, por lo que este paquete no necesita un worker. Las invitaciones y recuperación de contraseña requieren que el SMTP de `.env` esté correctamente configurado.

No uses las credenciales de ejemplo tal cual ni expongas errores de producción: mantén `APP_ENV=production` y `APP_DEBUG=false`.
