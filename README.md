# Remuneraciones Chile

Aplicación web PHP/MySQL para gestionar remuneraciones multiempresa, basada en `Liquidaciones_.xlsx`.

## Instalación en cPanel

1. Crear una base MySQL y copiar `.env.example` como `.env` con sus credenciales.
2. Ejecutar `database/schema.sql` en phpMyAdmin.
3. Apuntar el dominio o subdominio a `public/` (o mover su contenido al `public_html` y ajustar `APP_ROOT`).
4. Abrir `/index.php?page=setup` para crear la primera empresa y usuario administrador.
5. Iniciar sesión y crear períodos, trabajadores y parámetros.

La aplicación no consulta sitios externos automáticamente. Los parámetros previsionales y tributarios se cargan por período y quedan registrados en la base de datos.

La descarga de liquidaciones usa Dompdf y sus dependencias se incluyen en `vendor/`. En cPanel no es necesario ejecutar Composer: el archivo `.cpanel.yml` copia `vendor/` durante el despliegue.

## Estructura

- `public/index.php`: front controller, autenticación y pantallas.
- `app/PayrollCalculator.php`: motor de cálculo puro y auditable.
- `app/Database.php`: conexión PDO.
- `database/schema.sql`: esquema MySQL.
- `database/seed_example.sql`: datos del Excel de referencia.
- `tests/PayrollCalculatorTest.php`: pruebas de cálculo ejecutables con PHP CLI.

## Validación legal

Antes de usar para declaraciones oficiales, validar tasas, tablas SII, formato LRE y resultados con un contador. El CSV LRE se genera con separador `;` y columnas configurables en `app/LreExporter.php`.
