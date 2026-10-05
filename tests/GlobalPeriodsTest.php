<?php
declare(strict_types=1);
require_once __DIR__.'/../tools/migrate_global_periods.php';
require_once __DIR__.'/../app/BookExporter.php';
require_once __DIR__.'/../app/LreExporter.php';
$user=null;require_once __DIR__.'/../app/PayrollReports.php';

function check(bool $condition,string $message): void {if (!$condition) throw new RuntimeException($message);}
function runSql(PDO $pdo,string $path): void {
    $sql=preg_replace('/^--.*$/m','',(string)file_get_contents($path));
    foreach (explode(';',$sql) as $statement) if (trim($statement)!=='') $pdo->exec($statement);
}
function blocked(callable $operation,string $message): void {
    try {$operation();} catch (RuntimeException $e) {check(str_contains($e->getMessage(),'cerrado')||str_contains($e->getMessage(),'Reabre'),$message.': '.$e->getMessage());return;}
    throw new RuntimeException($message);
}

$server=getenv('PERIOD_TEST_SERVER_DSN')?:'mysql:host=127.0.0.1;port=33317;charset=utf8mb4';
$username=getenv('PERIOD_TEST_USER')?:'root';$password=getenv('PERIOD_TEST_PASSWORD')?:'';
$db='payroll_periods_test_'.bin2hex(random_bytes(6));
$pdo=new PDO($server,$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$pdo->exec('CREATE DATABASE '.$db.' CHARACTER SET utf8mb4');$pdo->exec('USE '.$db);$dsn=$server.';dbname='.$db;
$request=static function(int $userId,int $companyId,string $page,array $get=[],array $post=[],array $files=[]) use ($dsn,$username,$password): array {
    $process=proc_open([PHP_BINARY,__DIR__.'/PeriodRequest.php'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
    fwrite($pipes[0],json_encode(['dsn'=>$dsn,'username'=>$username,'password'=>$password,'user_id'=>$userId,'company_id'=>$companyId,'get'=>['page'=>$page]+$get,'post'=>$post,'files'=>$files],JSON_THROW_ON_ERROR));fclose($pipes[0]);
    $body=stream_get_contents($pipes[1]);fclose($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[2]);$exit=proc_close($process);
    check($exit===0,'Fallo PHP en '.$page.': '.$error);check(!str_contains($error,'Warning')&&!str_contains($error,'Fatal')&&!str_contains($error,'Notice'),'Diagnóstico PHP en '.$page.': '.$error);
    preg_match('/HTTP_STATUS=(\d+)/',$error,$status);return [(int)($status[1]??0),$body];
};
try {
    runSql($pdo,__DIR__.'/../database/schema.sql');
    foreach (['migration_attendance.sql','migration_attendance_allowances.sql','migration_attendance_redesign.sql'] as $file) runSql($pdo,__DIR__.'/../database/'.$file);
    $pdo->exec('ALTER TABLE payroll_periods ADD COLUMN status ENUM("draft","review","closed") NOT NULL DEFAULT "draft", ADD COLUMN closed_at TIMESTAMP NULL');
    $pdo->exec("INSERT INTO companies(name,rut) VALUES('Empresa A','A'),('Empresa B','B')");
    $pdo->exec("INSERT INTO users(company_id,name,email,password_hash,role,global_role) VALUES(1,'Admin','admin@example.test','unused','admin','admin'),(1,'Operador','operator@example.test','unused','operator','none'),(2,'Operador B','operatorb@example.test','unused','operator','none')");
    $pdo->exec("INSERT INTO user_companies(user_id,company_id,role) VALUES(1,1,'admin'),(2,1,'operator'),(3,2,'operator')");
    $pdo->exec("INSERT INTO employees(company_id,rut,full_name,hire_date,base_salary,afp,commune,region) VALUES(1,'111111111','Trabajador original','2025-01-01',700000,'Modelo','Comuna','Región'),(2,'222222222','Trabajador B','2025-01-01',800000,'Modelo','Comuna','Región')");
    $p=['uf'=>40000,'utm'=>71000,'minimum_wage'=>553553,'afp_cap_uf'=>90,'unemployment_cap_uf'=>135,'health_cap'=>252000,'mutual'=>.009,'sis'=>.0153,'sc_worker'=>.006,'sc_employer_indefinite'=>.024,'sc_employer_fixed'=>.03,'sanna'=>.0003,'reform_afp'=>.001,'reform_ssp'=>.009,'family_allowance'=>15000,'afp_rates'=>['Modelo'=>.1058],'tax_brackets'=>[[0,999999999,0,0]]];
    $pdo->prepare('INSERT INTO global_parameter_versions(period,values_json,created_by) VALUES("2026-01",?,1)')->execute([json_encode($p)]);
    $pdo->exec('INSERT INTO payroll_periods(company_id,period,status) VALUES(1,"2026-01","closed")');
    $old=(new PayrollCalculator())->calculate($pdo->query('SELECT * FROM employees WHERE id=1')->fetch(),array_fill_keys(PayrollPeriods::VARIABLE_FIELDS,0),$p);$old['net']=123456;
    $pdo->prepare('INSERT INTO payslips(period_id,employee_id,calculation_json) VALUES(1,1,?)')->execute([json_encode($old)]);
    migrateGlobalPeriods($pdo);$periods=new PayrollPeriods($pdo);
    check($periods->isClosed('2026-01'),'Cierre antiguo no globalizado');
    check((int)$pdo->query('SELECT COUNT(*) FROM payroll_periods')->fetchColumn()===2,'Períodos antiguos no sincronizados');
    check($periods->data(1,'2026-01')['workers'][0]['calculation']['net']===123456,'Migración alteró cálculo histórico');
    check((int)$pdo->query('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name="payroll_periods" AND column_name="status"')->fetchColumn()===0,'No se retiró el estado por empresa');
    $periods->setClosed('2026-01',1,false);migrateGlobalPeriods($pdo);check(!$periods->isClosed('2026-01'),'Reejecutar migración volvió a cerrar el mes');
    $periods->publish('2026-09',$p,1);$periods->publish('2026-09',$p,1);
    check((int)$pdo->query('SELECT COUNT(*) FROM payroll_periods WHERE period="2026-09"')->fetchColumn()===2,'Publicación no disponible en todas las empresas');
    $pp=$periods->companyPeriod(1,'2026-09');$ppB=$periods->companyPeriod(2,'2026-09');
    $pdo->exec("INSERT INTO employees(company_id,rut,full_name,hire_date,termination_date,base_salary,afp) VALUES(1,'333333333','Ingreso parcial','2026-09-16',NULL,600000,'Modelo'),(1,'444444444','Término parcial','2025-01-01','2026-09-10',600000,'Modelo'),(1,'555555555','Futuro','2027-01-01',NULL,600000,'Modelo'),(1,'666666666','Terminado','2025-01-01','2026-08-31',600000,'Modelo')");
    $workers=$periods->data(1,'2026-09')['workers'];check(count($workers)===3,'Vigencia de trabajadores incorrecta');
    $byName=[];foreach ($workers as $w) $byName[$w['employee']['full_name']]=$w['calculation']['days'];
    check($byName['Trabajador original']===30&&$byName['Ingreso parcial']===15&&$byName['Término parcial']===10,'Días pagados incorrectos');
    [$status,$body]=$request(3,2,'variables-grid',['period'=>'2026-09']);check($status===200&&str_contains($body,'Trabajador B'),'Empresa B no puede consultar período publicado');
    [$status,$body]=$request(2,1,'parameters');check($status===200&&!str_contains($body,'Cerrar período'),'Operador ve acciones administrativas');
    [$status]=$request(2,1,'period-new');check($status===403,'Operador puede crear períodos');
    [$status]=$request(2,1,'variables-save',[],['period'=>'2026-09','period_id'=>$pp['id'],'medical_leave_days'=>[1=>2],'unpaid_leave_days'=>[1=>1],'viatico'=>[1=>10000]]);check($status===302,'Variables abiertas no guardadas');
    [$status]=$request(2,1,'variables-save',[],['period'=>'2026-09','period_id'=>$pp['id'],'medical_leave_days'=>[2=>0]]);check($status===422,'Se permitió trabajador de otra empresa');
    [$status]=$request(2,1,'variables-save',[],['period'=>'2026-09','period_id'=>$ppB['id'],'medical_leave_days'=>[1=>0]]);check($status===422,'Se permitió period_id de otra empresa');
    [$status]=$request(2,1,'attendance-day-save',[],['period'=>'2026-09','work_date'=>'2026-09-01','status'=>[1=>'present',4=>'medical_leave'],'check_in'=>[1=>'08:00'],'check_out'=>[1=>'17:00'],'lunch_amount'=>[1=>2500],'snack_amount'=>[1=>1000]]);check($status===302,'Asistencia abierta no guardada');
    $live=$periods->data(1,'2026-09');$normal=null;foreach ($live['workers'] as $w) if ((int)$w['employee']['id']===1) $normal=$w;
    check($normal['calculation']['days']===27&&$normal['calculation']['attendanceLunch']===2500,'Asistencia reemplazó días legales o no acumuló almuerzo');
    [$status,$body]=$request(1,1,'parameters');check($status===200&&str_contains($body,'Cerrar período'),'Falta acción de cierre');preg_match('/name="csrf" value="([^"]+)"/',$body,$token);
    [$status]=$request(2,1,'period-close',[],['period'=>'2026-09','csrf'=>$token[1]]);check($status===403,'Operador pudo cerrar');
    [$status]=$request(1,1,'period-close',[],['period'=>'2026-09','csrf'=>'invalid']);check($status===422,'Cierre sin token válido');
    [$status]=$request(1,1,'period-close',[],['period'=>'2026-09','csrf'=>$token[1]]);check($status===302&&$periods->isClosed('2026-09'),'Cierre global falló');
    $frozen=$periods->data(1,'2026-09');$hash=hash('sha256',json_encode($frozen));$book=payrollBookRows($frozen);$lre=LreExporter::csv(payrollBookRows($frozen,true));
    foreach ([1,2] as $cid) blocked(fn()=>$periods->recalculate($cid,'2026-09'),'Se recalculó mes cerrado');
    blocked(fn()=>$periods->publish('2026-09',$p,1),'Se modificaron parámetros cerrados');
    $attendanceId=$pdo->query('SELECT id FROM attendance_records LIMIT 1')->fetchColumn();
    foreach ([['variables-save',['period_id'=>$pp['id'],'medical_leave_days'=>[1=>0]]],['attendance-day-save',['work_date'=>'2026-09-01']],['attendance-save',['work_date'=>'2026-09-01','employee_id'=>1]],['attendance-delete',['id'=>$attendanceId]],['parameter-save',$p],['parameter-advanced-save',['afp_rates'=>json_encode($p['afp_rates']),'tax_min'=>[0],'tax_max'=>[999999999],'tax_rate'=>[0],'tax_factor'=>[0]]]] as [$page,$post]) {
        [$status]=$request(1,1,$page,[],['period'=>'2026-09']+$post);check($status===422,'Solicitud directa no bloqueada: '.$page);
    }
    [$status]=$request(1,1,'calculate',['period'=>'2026-09']);check($status===422,'Ruta calculate alteró mes cerrado');
    $csv=tempnam(sys_get_temp_dir(),'period-workers-');$fp=fopen($csv,'w');
    fputcsv($fp,['RUT','Nombre completo','Fecha ingreso','Fecha término','Cargo','Sueldo base','Tipo contrato','Tipo gratificación','Institución salud','Plan Isapre UF','AFP','Colación','Movilización','Cargas familiares','CCAF','Comuna','Región','Tasa mutual','Cotiza AFP','Estado'],';');
    fputcsv($fp,['111111111','Nombre cambiado','2025-01-01','2026-08-31','Cargo',999999,'Indefinido','Art.50','Fonasa',0,'Otra',0,0,0,'','Comuna','Región',.009,1,'Activo'],';');fclose($fp);
    try {[$status]=$request(2,1,'employee-csv-import',[],['import'=>'1'],['worker_csv'=>['tmp_name'=>$csv]]);check($status===302,'Importación de ficha falló');} finally {unlink($csv);}
    check($pdo->query('SELECT full_name FROM employees WHERE id=1')->fetchColumn()==='Nombre cambiado','CSV no modificó la ficha actual');
    check(hash('sha256',json_encode($periods->data(1,'2026-09')))===$hash,'Cambios de ficha alteraron fotografía');
    check(payrollBookRows($periods->data(1,'2026-09'))===$book&&LreExporter::csv(payrollBookRows($periods->data(1,'2026-09'),true))===$lre,'Cambios alteraron libro/exportación');
    foreach (['payslips','payslip','book','book-excel','lre','variables-grid','attendance','attendance-summary','summary','payslip-pdf','payslips-all-pdf'] as $page) {
        [$status,$body]=$request(2,1,$page,['period'=>'2026-09','employee'=>1]);check($status===200,'Consulta histórica falló: '.$page);
        if (str_ends_with($page,'pdf')) check(str_starts_with($body,'%PDF-'),'PDF inválido: '.$page);
        elseif ($page!=='summary') check(str_contains($body,'Trabajador original')&&!str_contains($body,'Nombre cambiado'),'Consulta no usa fotografía: '.$page);
        if ($page==='variables-grid') check((bool)preg_match('/<input disabled[^>]+form="excel-grid-form"/',$body),'Grilla histórica no es de solo lectura');
    }
    [$status]=$request(1,1,'company-save',[],['name'=>'Empresa nueva','rut'=>'C','giro'=>'Prueba']);check($status===302,'Nueva empresa falló');
    check(count($periods->data(3,'2026-09')['workers'])===0,'Empresa nueva incluye trabajadores en mes ya cerrado');
    check((int)$pdo->query('SELECT COUNT(*) FROM payroll_periods WHERE company_id=3')->fetchColumn()===2,'Nueva empresa sin períodos globales');
    [$status]=$request(2,1,'period-reopen',[],['period'=>'2026-09','csrf'=>$token[1]]);check($status===403,'Operador pudo reabrir');
    [$status]=$request(1,1,'period-reopen',[],['period'=>'2026-09','csrf'=>$token[1]]);check($status===302&&!$periods->isClosed('2026-09'),'Reapertura administrativa falló');
    $periods->publish('2026-09',$p,1);$periods->recalculate(1,'2026-09');
    check(count($periods->data(1,'2026-09')['workers'])===2,'Reapertura no permite recalcular datos corregidos');
    $periods->setClosed('2026-09',1,true);check(hash('sha256',json_encode($periods->data(1,'2026-09')))!==$hash,'Nuevo cierre no actualizó fotografía');
    check((int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='period-global-reopen'")->fetchColumn()>=2,'Reapertura no auditada');
    $create=$p;$create['afp_rates']=json_encode($p['afp_rates']);$create['tax_brackets']=json_encode($p['tax_brackets']);
    [$status]=$request(1,1,'period-save',[],['period'=>'2026-10']+$create);check($status===302,'Guardar Previred desde pantalla no publica el mes');
    check((int)$pdo->query('SELECT COUNT(*) FROM payroll_periods WHERE period="2026-10"')->fetchColumn()===3,'Guardar Previred no habilitó todas las empresas');
    $dump=getenv('PERIOD_TEST_DUMP')?:__DIR__.'/../tools/remuneraciones_before_external.sql';
    if (is_file($dump)) {
        $copy='payroll_periods_test_'.bin2hex(random_bytes(6));
        $copyPdo=new PDO($server,$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$copyPdo->exec('CREATE DATABASE '.$copy.' CHARACTER SET utf8mb4');$copyPdo->exec('USE '.$copy);
        try {
            $dumpSql=(string)file_get_contents($dump);check(!preg_match('/^\s*(CREATE DATABASE|USE\s)/mi',$dumpSql),'Respaldo contiene un cambio de base no permitido');
            $copyPdo->exec($dumpSql);$before=$copyPdo->query('SELECT id,calculation_json FROM payslips ORDER BY id')->fetchAll();
            migrateGlobalPeriods($copyPdo);check($before===$copyPdo->query('SELECT id,calculation_json FROM payslips ORDER BY id')->fetchAll(),'Migración de respaldo alteró liquidaciones existentes');
            migrateGlobalPeriods($copyPdo);echo "Migration on a disposable copy of the local SQL backup passed.\n";
        } finally {$copyPdo->exec('DROP DATABASE '.$copy);}
    }
    echo "Global period migration, publication, requests, snapshots and PDF/export tests passed.\n";
} finally {
    // This name was generated by this test; no existing database is used or removed.
    if (preg_match('/^payroll_periods_test_[a-f0-9]+$/D',$db)) $pdo->exec('DROP DATABASE '.$db);
}
