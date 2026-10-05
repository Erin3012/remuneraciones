<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once __DIR__.'/../app/Database.php';
require_once __DIR__.'/../app/PayrollCalculator.php';
require_once __DIR__.'/../app/PayrollPeriods.php';
function migrateGlobalPeriods(PDO $pdo): void {
  $periods=new PayrollPeriods($pdo);
  $periods->acquireMutex();
  try {
    $sql=(string)file_get_contents(__DIR__.'/../database/migration_global_period_closure.sql');
    $sql=preg_replace('/^--.*$/m','',$sql);
    foreach (explode(';',$sql) as $statement) if (trim($statement)!=='') $pdo->exec($statement);
    $missing=$pdo->query('SELECT DISTINCT pp.period FROM payroll_periods pp LEFT JOIN global_parameter_versions gp ON gp.period=pp.period WHERE gp.id IS NULL')->fetchAll(PDO::FETCH_COLUMN);
    if ($missing) throw new RuntimeException('Faltan parámetros para conservar estos meses: '.implode(', ',$missing).'. Completa sus parámetros antes de terminar la migración.');
    $periods->backfillSnapshots();
    foreach (['status','closed_at'] as $column) {
        $q=$pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name="payroll_periods" AND column_name=?');$q->execute([$column]);
        if ($q->fetchColumn()) $pdo->exec('ALTER TABLE payroll_periods DROP COLUMN '.$column);
    }
  } finally {$periods->releaseMutex();}
}
if (realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) {
    migrateGlobalPeriods(Database::connection());
    echo "Períodos globales sincronizados; cierres antiguos y fotografías históricas conservados.\n";
}
