# Aplicación de inventarios

Base inicial de la aplicación, construida con Laravel 12, Vite y MySQL. El alcance funcional y las decisiones vigentes están documentados en `../docs`.

El acceso permite inicio/cierre de sesión, recuperación y cambio de contraseña; limita intentos, impide autenticación de cuentas pendientes, bloqueadas o inactivas, y registra resultados de acceso. No existe registro público. Compras e inventario aplican permisos por operación y alcance de bodegas; los permisos logísticos se definirán junto con ese módulo.

El panel de administración permite gestionar usuarios mediante invitaciones, asignar perfiles, activar/bloquear/desactivar cuentas, editar los permisos de perfiles, configurar los datos de la única empresa del MVP y consultar auditoría administrativa. El primer usuario recibe el perfil Administración.

La estructura organizacional incluye sedes, bodegas, ubicaciones dentro de bodegas y centros de costo. Los códigos son únicos dentro de su empresa o entidad padre; los registros se desactivan en lugar de eliminarse y las acciones quedan auditadas. Configure primero los datos de la empresa.

Los datos maestros incluyen productos (código, nombre, descripción, unidad y control de vencimiento), unidades de medida y proveedores. La identificación tributaria del proveedor es opcional porque su formato depende del país. Los cambios se auditan y los registros se desactivan, no se eliminan. La importación de productos está disponible como opción **Importación de datos maestros** en el menú lateral del grupo Datos maestros. Acepta plantillas CSV o XLSX; la primera fila debe contener `code,name,description,unit_code,tracks_expiration,is_active`, en ese orden. `unit_code` identifica la unidad; los campos booleanos aceptan `sí/no`, `true/false`, `1/0` y, para el estado, `activo/inactivo`. Se admiten archivos de hasta 5 MB y 1000 filas: se importan las filas válidas, los códigos duplicados se omiten y el resultado descargable detalla el resultado de cada fila. La importación requiere el permiso `catalogs.manage`.

El flujo de compras permite configurar áreas solicitantes, asignar a cada área un jefe activo con permiso de decisión, y crear, editar, enviar, aprobar o rechazar requisiciones con sus líneas de productos. Cada usuario solicitante pertenece a un área; la requisición queda asignada al jefe de esa área al enviarse. El rechazo requiere motivo, permite corrección y reenvío, y cada cambio conserva su auditoría. En las requisiciones aprobadas, Compras registra cotizaciones de proveedores por producto; cada producto requiere ofertas de al menos dos proveedores antes de seleccionarse. El comparador convierte el total con impuestos a moneda base, muestra plazo, marca y calificación vigente, y exige justificar la selección de una oferta que no tenga el menor costo total. La adjudicación puede hacerse parcialmente y en etapas; cada etapa genera órdenes borrador agrupadas por proveedor y cada orden requiere aprobación antes de recibir. El costo trasladado al inventario permanece antes de impuestos. La opción Calificación de proveedores, protegida con `catalogs.manage`, conserva el historial y el promedio vigente de las evaluaciones. La moneda base no puede cambiarse después de registrar cotizaciones u órdenes para conservar comparables y costos históricos consistentes.

## Requisitos

- PHP 8.2 o superior con extensiones PDO MySQL, OpenSSL, Mbstring, XML y Ctype.
- Composer 2.
- Node.js compatible con Vite 6 y npm.

## Configuración local

Desde PowerShell, en esta carpeta:

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
npm.cmd install
npm.cmd run build
```

La configuración de ejemplo se conecta a MySQL en `localhost:3306` con el usuario `root` y contraseña vacía. Crea una base de datos llamada `inventarios` en MySQL/MariaDB (por ejemplo, desde phpMyAdmin de XAMPP). Si tu usuario root tiene contraseña, actualiza `DB_PASSWORD` en `.env`. Después ejecuta las migraciones:

```powershell
php artisan migrate
php artisan db:seed --class=AccessControlSeeder
php artisan app:create-first-user
```

El primer usuario se crea mediante el comando interactivo (no hay registro público). Se solicita contraseña en modo oculto, con mínimo de 12 caracteres. El comando solo funciona mientras no exista ningún usuario.

La configuración de correo de ejemplo usa el canal de registro local de Laravel; configura un transporte SMTP para que se entreguen invitaciones y enlaces de recuperación.

Para desarrollo, abre una terminal para Laravel y otra para Vite:

```powershell
php artisan serve
```

```powershell
npm.cmd run dev
```

Si PowerShell bloquea `npm.ps1`, se puede invocar `npm.cmd` como en los ejemplos.

## Validación

```powershell
php artisan test
npm.cmd run build
```

Las reglas operativas pendientes se concretarán con el responsable funcional antes de implementar cada módulo. No se deben asumir durante la construcción.
