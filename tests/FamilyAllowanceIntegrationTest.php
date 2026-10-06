<?php
declare(strict_types=1);
require_once __DIR__.'/../app/PayrollCalculator.php';
require_once __DIR__.'/../tools/migrate_family_allowance.php';
function faCheck(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$server=getenv('PERIOD_TEST_SERVER_DSN')?:'mysql:host=127.0.0.1;port=33317;charset=utf8mb4';$username='root';$password='';
$pdo=new PDO($server,$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db='payroll_periods_test_'.bin2hex(random_bytes(6));$pdo->exec('CREATE DATABASE '.$db.' CHARACTER SET utf8mb4');$pdo->exec('USE '.$db);$dsn=$server.';dbname='.$db;
$request=static function(string $page,array $get=[],array $post=[])use($dsn,$username,$password):array{
 $proc=proc_open([PHP_BINARY,__DIR__.'/PeriodRequest.php'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
 fwrite($pipes[0],json_encode(['dsn'=>$dsn,'username'=>$username,'password'=>$password,'user_id'=>1,'company_id'=>1,'get'=>['page'=>$page]+$get,'post'=>$post],JSON_THROW_ON_ERROR));fclose($pipes[0]);
 $body=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);faCheck($exit===0&&!preg_match('/Warning|Fatal|Notice/',$err),'Request '.$page.': '.$err);preg_match('/HTTP_STATUS=(\d+)/',$err,$m);return [(int)$m[1],$body];
};
try {
 foreach(explode(';',(string)file_get_contents(__DIR__.'/../database/schema.sql')) as $sql)if(trim($sql)!=='')$pdo->exec($sql);
 migrateFamilyAllowance($pdo);migrateFamilyAllowance($pdo);
 $pdo->exec("INSERT INTO companies(name,rut) VALUES('Prueba','FA1'),('Ajena','FA2')");
 $pdo->exec("INSERT INTO users(company_id,name,email,password_hash,role) VALUES(1,'Usuario','family@test.invalid','unused','operator')");
 $pdo->exec("INSERT INTO employees(company_id,rut,full_name,hire_date,base_salary,contract_type,afp,family_loads) VALUES(1,'1-9','Trabajador','2025-01-01',1000000,'Indefinido','Modelo',1),(2,'2-7','Ajeno','2025-01-01',800000,'Indefinido','Modelo',1)");
 require __DIR__.'/PayrollCalculatorTest.php';$p['family_brackets']=[[649039,22601],[947990,13870],[1478539,4382]];
 $ps=new PayrollPeriods($pdo);$ps->publish('2026-09',$p,1);
 $blocked=false;try{$ps->recalculate(1,'2026-09');}catch(RuntimeException $ex){$blocked=true;}faCheck($blocked,'Missing history must block final payroll');
 [$status,$html]=$request('employees');faCheck($status===200&&str_contains($html,'Asignación familiar: tramos e ingresos'),'Workers entry point');
 [$status,$html]=$request('family-allowances',['period'=>'2026-09','employee_id'=>1]);faCheck($status===200&&str_contains($html,'faltan ingresos completos'),'Pending page');preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$m);$csrf=$m[1];
 $post=['period'=>'2026-09','employee_id'=>1,'csrf'=>$csrf,'band'=>'C','basis'=>'auto','reference'=>'IPS certificado prueba'];
 [$status]=$request('family-allowances',[],$post);faCheck($status===302,'Save accreditation');$ps->recalculate(1,'2026-09');faCheck($ps->data(1,'2026-09')['workers'][0]['calculation']['family']===4382,'Accredited band applied');
 [$status]=$request('family-allowances',[],array_replace($post,['employee_id'=>2]));faCheck($status===422,'Cross-company POST blocked');
 [$status]=$request('family-allowances',[],array_replace($post,['csrf'=>'wrong']));faCheck($status===422,'CSRF blocked');
 $pdo->exec('UPDATE employees SET family_loads=0 WHERE id=2');
 $ps->setClosed('2026-09',1,true);$snapshot=$ps->data(1,'2026-09');
 [$status]=$request('family-allowances',[],array_replace($post,['band'=>'A']));faCheck($status===422,'Closed period POST blocked');
 $pdo->exec("UPDATE employees SET base_salary=2000000,family_loads=3 WHERE id=1");faCheck($ps->data(1,'2026-09')===$snapshot,'Closed snapshot unchanged');
 $ps->setClosed('2026-09',1,false);$pdo->exec('UPDATE employees SET base_salary=1000000,family_loads=1 WHERE id=1');
 $post['band']='';$post['reference']='';foreach(FamilyAllowance::months('2026-09') as $month){$post['income'][$month]='1000000';$post['income_days'][$month]='30';$post['income_reference'][$month]='Declaración de ingresos prueba';}
 [$status]=$request('family-allowances',[],$post);faCheck($status===302,'Historical income POST');$ps->recalculate(1,'2026-09');$calc=$ps->data(1,'2026-09')['workers'][0]['calculation'];faCheck($calc['family']===4382&&$calc['familyAssessment']['source']==='history','Historical fallback');
 $post['income']['2026-01']='-1';[$status]=$request('family-allowances',[],$post);faCheck($status===422,'Negative input blocked');faCheck($ps->data(1,'2026-09')['workers'][0]['calculation']['family']===4382,'Failed POST rolls back');
 $pdo->exec("DELETE FROM family_income_history WHERE employee_id=1");
 foreach(FamilyAllowance::months('2026-09') as $month){$ps->publish($month,$p,1);$pid=$ps->companyPeriod(1,$month)['id'];$r=['haberes'=>1100000,'family'=>100000,'days'=>30,'lic'=>0];$pdo->prepare('INSERT INTO payslips(period_id,employee_id,calculation_json) VALUES(?,1,?)')->execute([$pid,json_encode($r)]);}
 $calc=$ps->data(1,'2026-09')['workers'][0]['calculation'];faCheck($calc['familyAssessment']['average']==1000000,'Saved gross excludes family allowance: '.json_encode(FamilyAllowance::context($pdo,1,'2026-09')));
 $pid=$ps->companyPeriod(1,'2026-02')['id'];$pdo->prepare('INSERT INTO payroll_variables(period_id,employee_id,medical_leave_days) VALUES(?,1,30)')->execute([$pid]);faCheck(!$ps->data(1,'2026-09')['workers'][0]['calculation']['familyAssessment']['ready'],'Missing subsidy blocks saved historical fallback');
 echo "Family allowance migration, requests, authorization, historical payroll and snapshot tests passed\n";
}finally{if(preg_match('/^payroll_periods_test_[a-f0-9]+$/D',$db))$pdo->exec('DROP DATABASE '.$db);}
