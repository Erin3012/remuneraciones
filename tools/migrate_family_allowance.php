<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../app/Database.php';
require_once __DIR__.'/../app/PayrollPeriods.php';
function migrateFamilyAllowance(PDO $pdo): void {
    $service=new PayrollPeriods($pdo);$service->acquireMutex();
    try {
        foreach(explode(';',(string)file_get_contents(__DIR__.'/../database/migration_family_allowance.sql')) as $sql)if(trim($sql)!=='')$pdo->exec($sql);
    }finally{$service->releaseMutex();}
}
if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__){migrateFamilyAllowance(Database::connection());echo "Tramos acreditados e ingresos históricos habilitados. Datos existentes conservados.\n";}
