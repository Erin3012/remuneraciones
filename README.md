# Remuneraciones Chile

Aplicación web PHP/MySQL para gestionar remuneraciones multiempresa, basada en `Liquidaciones_.xlsx`.

## Instalación en cPanel

1. Crear una base MySQL y copiar `.env.example` como `.env` con sus credenciales.
2. Ejecutar `database/schema.sql` en phpMyAdmin.
3. Apuntar el dominio o subdominio a `public/` (o mover su contenido al `public_html` y ajustar `APP_ROOT`).
4. Abrir `/index.php?page=setup` para crear la primera empresa y usuario administrador.
5. Iniciar sesión y crear períodos, trabajadores y parámetros.

La aplicación no consulta sitios externos automáticamente. Los parámetros previsionales y tributarios se cargan por período y quedan registrados en la base de datos.

## Períodos globales y cierre

Guardar un mes en **Parámetros Previred** lo habilita para todas las empresas. Las empresas nuevas reciben automáticamente los meses existentes. El administrador puede cerrar o reabrir un mes desde esa misma pantalla; cada acción se registra por empresa en auditoría.

El cierre conserva una fotografía de los datos del trabajador, parámetros, variables, asistencia y cálculo. Las consultas, PDFs, libro y exportaciones del mes cerrado usan esa fotografía. Cambiar posteriormente una ficha no modifica el histórico. Para corregir un mes hay que reabrirlo, modificarlo y volver a cerrarlo.

Para actualizar una instalación existente, ejecutar desde su carpeta, con el `.env` de esa instalación:

```bash
cd /home/qlccl/remuneraciones
php tools/migrate_global_periods.php
```

El comando sincroniza los períodos en todas las empresas, convierte en global un mes cerrado por cualquier empresa y retira los estados antiguos. Es reutilizable y no sobrescribe fotografías ya capturadas. Los importes de liquidaciones antiguas se conservan; los datos laborales anteriores que nunca se versionaron se capturan con la información disponible al migrar. Ejecutar el comando completo, no solamente el SQL, para terminar de conservar los históricos.

La migración y el despliegue deben realizarse juntos. Mientras falte la migración, las pantallas mensuales mostrarán el comando necesario en vez de permitir cambios sin protección.

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
