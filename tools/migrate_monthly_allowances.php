<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once __DIR__.'/../app/Database.php';
require_once __DIR__.'/../app/PayrollPeriods.php';
function migrateMonthlyAllowances(PDO $pdo): void {
    $periods=new PayrollPeriods($pdo);$periods->acquireMutex();
    try {
        // The migration must run before deploying the updated PayrollPeriods class.
        foreach(['meal_allowance_override','transport_allowance_override'] as $field) {
            $q=$pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name="payroll_variables" AND column_name=?');$q->execute([$field]);
            if (!$q->fetchColumn()) $pdo->exec('ALTER TABLE payroll_variables ADD COLUMN '.$field.' DECIMAL(14,0) NULL DEFAULT NULL');
        }
    } finally {$periods->releaseMutex();}
}
if (realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) {
    migrateMonthlyAllowances(Database::connection());echo "Colación y movilización mensuales habilitadas; valores existentes conservados.\n";
}
