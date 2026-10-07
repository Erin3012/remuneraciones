<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once __DIR__.'/../app/Database.php';
require_once __DIR__.'/../app/PayrollPeriods.php';

function migrateCompanyLoans(PDO $pdo): void {
    $periods=new PayrollPeriods($pdo);$periods->acquireMutex();
    try {
        foreach(explode(';',(string)file_get_contents(__DIR__.'/../database/migration_company_loans.sql')) as $sql) if(trim($sql)!=='') $pdo->exec($sql);
    } finally {$periods->releaseMutex();}
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) {
    migrateCompanyLoans(Database::connection());
    echo "Módulo de préstamos empresa instalado. No se modificaron variables ni liquidaciones existentes.\n";
}
