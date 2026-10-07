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

### Horas faltadas

En Variables y cálculos se registra el saldo de horas no trabajadas después de compensar los excesos dentro de cada semana. Para la jornada de 42 horas semanales que utiliza el cálculo actual, el descuento es el sueldo mensual completo (incluido el ajuste al mínimo del período) dividido por 180, multiplicado por las horas y redondeado a pesos enteros. Los días trabajados se conservan. El descuento se presenta como haber negativo y reduce la base del artículo 50 y las bases imponibles; no vuelve a restarse en otros descuentos. La gratificación garantizada conserva el monto declarado. La revisión de jornadas distintas requiere adaptar el divisor al contrato. Prueba: `php tests/AbsentHoursTest.php`.

### Tramo de asignación familiar

Desde Trabajadores → **Asignación familiar: tramos e ingresos**, selecciona el trabajador y el período. Se usa primero el tramo acreditado A/B/C/D del ciclo julio–junio, con referencia del certificado Caja/IPS. D es un tramo válido sin pago, no un dato faltante. No se importan ni infieren tramos desde liquidaciones externas.

Sin acreditación, se usa enero–junio del año del ciclo; para enero–junio se utiliza el ciclo del año anterior. Obra/faena y plazo fijo hasta seis meses utilizan julio–junio (12 meses). La duración de un plazo fijo debe confirmarse en el formulario, no inferirse desde la fecha de término de la ficha. Los ingresos se promedian entre los meses con ingresos, no entre meses inexistentes. Las liquidaciones guardadas aportan haberes brutos excluyendo asignación familiar; no se recalculan con el sueldo actual. Los antecedentes declarados reemplazan el total del mes para incluir subsidios y otras fuentes, sin duplicar ingresos. Un mes con licencia no se considera completo sin ese complemento. Revisar todas las fuentes de ingreso con la Caja/IPS.

Un mes faltante no es un mes sin ingresos. Si falta información, parámetros o la revisión de casos con menos de 30 días con ingresos históricos, se informa **pendiente** y se bloquean generación de liquidaciones y cierre, en vez de usar el imponible actual. La vista previa puede mostrar asignación 0 con advertencia; no es una liquidación definitiva. Los casos especiales de nuevos beneficiarios con menos de 30 días de ingresos deben resolverse con el tramo acreditado. Las fotografías de períodos cerrados no cambian.

Instalaciones existentes: ejecutar `php tools/migrate_family_allowance.php` antes de utilizar la nueva pantalla. La migración crea únicamente tablas de antecedentes, conserva datos actuales y es reutilizable. No recalcula ni cambia liquidaciones guardadas.

Prueba unitaria: `php tests/FamilyAllowanceTest.php`.

Antes de usar para declaraciones oficiales, validar tasas, tablas SII, formato LRE y resultados con un contador. El CSV LRE se genera con separador `;` y columnas configurables en `app/LreExporter.php`.
## Préstamos de empresa

El módulo `Préstamos empresa` registra un préstamo por trabajador, el período de inicio y un plan definido por número de cuotas o monto mensual. Cada cuota se descuenta automáticamente al calcular su período y queda marcada como descontada; la pantalla muestra el historial y saldo pendiente. El campo `Prést. empresa manual` de Variables y cálculos sigue disponible para descuentos puntuales. Para instalar el módulo en una base existente, ejecutar `database/migration_company_loans.sql` una sola vez.
