<?php
declare(strict_types=1);
require_once __DIR__.'/PayrollPeriods.php';

function periodAdministrator(): bool {
    global $user;
    return ($user['role']??'')==='admin' || ($user['global_role']??'')==='admin';
}

function periodRequireAdministrator(): void {
    if (!periodAdministrator()) periodError(new RuntimeException('Solo el administrador puede realizar esta acción.'),403);
}

function periodEmployees(string $period,int $companyId): array {
    global $periodService,$pdo;
    if ($periodService->isClosed($period)) {
        $data=$periodService->data($companyId,$period);
        return $data['all_employees']??array_column($data['workers'],'employee');
    }
    $q=$pdo->prepare('SELECT * FROM employees WHERE company_id=? ORDER BY full_name');$q->execute([$companyId]);return $q->fetchAll();
}

function periodAttendance(string $table,string $period,int $companyId): array {
    global $periodService,$pdo;
    if (!in_array($table,['attendance_records','attendance_days'],true)) throw new LogicException('Tabla de asistencia inválida.');
    if ($periodService->isClosed($period)) return $periodService->data($companyId,$period)[$table]??[];
    if (!$periodService->tableExists($table)) return [];
    $q=$pdo->prepare("SELECT * FROM $table WHERE company_id=? AND work_date BETWEEN ? AND LAST_DAY(?)");$q->execute([$companyId,$period.'-01',$period.'-01']);return $q->fetchAll();
}

function periodError(Throwable $error,int $status=422): never {
    http_response_code($status);
    layout('Períodos globales','<section class="card"><h1>No se pudo completar la acción</h1><p class="error">'.h($error->getMessage()).'</p><a class="button secondary" href="?page=parameters">Parámetros Previred</a></section>');exit;
}

$periodService=new PayrollPeriods($pdo);
if ($user) {
    // Refresh permissions from the database, including the role in the selected company.
    $q=$pdo->prepare('SELECT * FROM users WHERE id=? AND active=1');$q->execute([(int)$user['id']]);$fresh=$q->fetch();
    if (!$fresh) {unset($_SESSION['user']);go('login');}
    $companyId=(int)($user['company_id']??$fresh['company_id']);$role=$fresh['role'];
    if ($periodService->tableExists('user_companies')) {
        $q=$pdo->prepare('SELECT company_id,role FROM user_companies WHERE user_id=? AND active=1 ORDER BY company_id');$q->execute([(int)$fresh['id']]);$memberships=$q->fetchAll();
        if ($memberships) {
            $selected=$memberships[0];foreach ($memberships as $membership) if ((int)$membership['company_id']===$companyId) $selected=$membership;
            $companyId=(int)$selected['company_id'];$role=$selected['role'];
        } else $companyId=(int)$fresh['company_id'];
    }
    if (isset($_SESSION['portal_role'])) {
        $centralRole=(string)$_SESSION['portal_role'];
        $role=in_array($centralRole,['developer','admin'],true)?'admin':'operator';
        $fresh['global_role']=in_array($centralRole,['developer','admin'],true)?'admin':'none';
    }
    $user=array_replace($fresh,['company_id'=>$companyId,'role'=>$role]);$_SESSION['user']=$user;
    $monthlyPages=['parameters','parameters-edit','parameter-advanced','period-new','period-save','parameter-save','parameter-advanced-save','parameter-example','periods','period-status','period-close','period-reopen','variables','variables-grid','variables-save','calculate','payslips','payslips-all','payslips-all-pdf','payslip','payslip-pdf','book','book-excel','summary','lre','attendance','attendance-summary','attendance-save','attendance-day-save','attendance-delete'];
    if (in_array($page,$monthlyPages,true)) {
        try {$periodService->assertReady();} catch (Throwable $e) {periodError($e,503);}
    }
    if ($_SERVER['REQUEST_METHOD']!=='POST' && in_array($page,['variables-grid','variables','calculate','payslips','payslip','payslip-pdf','payslips-all','payslips-all-pdf','book','book-excel','summary','lre','attendance','attendance-summary'],true)) {
        $_GET['period']??=(string)($pdo->query('SELECT period FROM global_parameter_versions ORDER BY period DESC LIMIT 1')->fetchColumn()?:'');
        try {$periodService->companyPeriod($companyId,(string)$_GET['period']);} catch (Throwable $e) {periodError($e);}
    }
    // Serialise master-data changes with global close so the snapshot sees complete updates.
    if ($_SERVER['REQUEST_METHOD']==='POST' && in_array($page,['employee-save','employees-grid-save','employee-delete','employee-restore','employee-csv-import','excel-import','company-save'],true)) {
        try {
            $periodService->assertReady();
            if ($page==='company-save') periodRequireAdministrator();
            if ($page==='excel-import') {
                periodRequireAdministrator();
            }
            $periodService->acquireMutex();
            register_shutdown_function(static function() use ($periodService) {$periodService->releaseMutex();});
            if ($page==='excel-import' && $periodService->isClosed((string)($_POST['period']??''))) throw new RuntimeException('Reabre el período antes de importar sus parámetros.');
        } catch (Throwable $e) {periodError($e,403);}
    }
    if (in_array($page,['company-new','period-new'],true) && !periodAdministrator()) periodError(new RuntimeException('Esta acción está disponible solo para el administrador.'),403);

    if (in_array($page,['period-close','period-reopen','period-status'],true)) {
        if ($_SERVER['REQUEST_METHOD']!=='POST') periodError(new RuntimeException('Usa la acción de Parámetros Previred.'),405);
        try {
            periodRequireAdministrator();
            if ($page==='period-status') throw new RuntimeException('El avance por estados ya no se utiliza. Cierra o reabre el mes desde Parámetros Previred.');
            if (empty($_SESSION['period_csrf'])||!hash_equals($_SESSION['period_csrf'],(string)($_POST['csrf']??''))) throw new RuntimeException('La sesión del formulario venció. Vuelve a Parámetros Previred.');
            $periodService->setClosed((string)($_POST['period']??''),(int)$user['id'],$page==='period-close');
            $_SESSION['flash']=$page==='period-close'?'Período cerrado para todas las empresas.':'Período reabierto para todas las empresas.';go('parameters');
        } catch (Throwable $e) {periodError($e);}
    }
    if ($_SERVER['REQUEST_METHOD']==='POST' && in_array($page,['period-save','parameter-save','parameter-advanced-save','parameter-example'],true)) {
        try {
            periodRequireAdministrator();$period=(string)($_POST['period']??'');PayrollPeriods::validatePeriod($period);
            if ($page==='parameter-example') throw new RuntimeException('Los parámetros de ejemplo ya no se cargan. Guarda los valores Previred del mes.');
            if ($page==='period-save') {
                $q=$pdo->query('SELECT values_json FROM global_parameter_versions ORDER BY period DESC LIMIT 1');$p=json_decode((string)($q->fetchColumn()?:'{}'),true);
                $p['afp_rates']=json_decode((string)($_POST['afp_rates']??'[]'),true,512,JSON_THROW_ON_ERROR);
                $p['tax_brackets']=json_decode((string)($_POST['tax_brackets']??'[]'),true,512,JSON_THROW_ON_ERROR);
                if (!is_array($p['afp_rates'])||!$p['afp_rates']||!is_array($p['tax_brackets'])||!$p['tax_brackets']) throw new RuntimeException('Completa las tasas AFP y los tramos de impuesto único del período.');
            } else $p=$periodService->parameters($period);
            if ($page==='parameter-advanced-save') {
                $rates=json_decode((string)($_POST['afp_rates']??''),true,512,JSON_THROW_ON_ERROR);
                if (!is_array($rates)||!$rates) throw new RuntimeException('Ingresa las tasas AFP.');
                foreach ($rates as $name=>$rate) if (!is_numeric($rate)||(float)$rate<0) throw new RuntimeException('Tasa AFP inválida: '.$name);
                $tax=[];foreach ($_POST['tax_min']??[] as $i=>$min) $tax[]=[(float)$min,(float)($_POST['tax_max'][$i]??0),(float)($_POST['tax_rate'][$i]??0),(float)($_POST['tax_factor'][$i]??0)];
                if (!$tax) throw new RuntimeException('Ingresa los tramos de impuesto único.');$p['afp_rates']=$rates;$p['tax_brackets']=$tax;
            } else {
                foreach (['uf','utm','minimum_wage','afp_cap_uf','unemployment_cap_uf','health_cap','mutual','sis','sc_worker','sc_employer_indefinite','sc_employer_fixed','sanna','reform_afp','reform_ssp','family_allowance'] as $key) {
                    if (!isset($_POST[$key])||!is_numeric($_POST[$key])||(float)$_POST[$key]<0) throw new RuntimeException('Valor inválido: '.$key);
                    $p[$key]=(float)$_POST[$key];
                }
            }
            $periodService->publish($period,$p,(int)$user['id']);$_SESSION['flash']='Parámetros guardados. El período está disponible para todas las empresas.';go('parameters');
        } catch (Throwable $e) {periodError($e);}
    }
    if ($page==='parameters') {
        $_SESSION['period_csrf']??=bin2hex(random_bytes(24));$rows='';
        foreach ($pdo->query('SELECT gp.period,gp.values_json,g.closed_at FROM global_parameter_versions gp JOIN global_period_closures g ON g.period=gp.period ORDER BY gp.period DESC') as $x) {
            $p=json_decode($x['values_json'],true);$closed=(bool)$x['closed_at'];
            $action='<a class="button secondary" href="?page=parameters-edit&period='.h($x['period']).'">'.(periodAdministrator()&&!$closed?'Editar':'Consultar').'</a>';
            if ($closed) $action.=' <span class="muted">Cerrado para todas las empresas</span>';
            if (periodAdministrator()) $action.=' <form class="inline-form" method="post" action="?page='.($closed?'period-reopen':'period-close').'"><input type="hidden" name="period" value="'.h($x['period']).'"><input type="hidden" name="csrf" value="'.h($_SESSION['period_csrf']).'"><button type="submit" onclick="return confirm(\''.($closed?'¿Reabrir este mes para todas las empresas?':'¿Cerrar este mes y conservar las liquidaciones de todas las empresas?').'\')">'.($closed?'Reabrir período':'Cerrar período').'</button></form>';
            $rows.='<tr><td>'.h($x['period']).'</td><td>$'.number_format((float)($p['uf']??0),0,',','.').'</td><td>$'.number_format((float)($p['utm']??0),0,',','.').'</td><td>$'.number_format((float)($p['minimum_wage']??0),0,',','.').'</td><td>'.$action.'</td></tr>';
        }
        $flash=isset($_SESSION['flash'])?'<p class="notice">'.h($_SESSION['flash']).'</p>':'';unset($_SESSION['flash']);
        layout('Parámetros Previred','<div class="head"><div><h1>01 · Parámetros Previred</h1><p class="muted">Guardar un mes lo habilita para todas las empresas. El cierre protege su información histórica.</p></div>'.(periodAdministrator()?'<a class="button" href="?page=period-new">Nuevo período</a>':'').'</div>'.$flash.'<section class="card table-scroll"><table><thead><tr><th>Período</th><th>UF</th><th>UTM</th><th>Ingreso mínimo</th><th>Acción</th></tr></thead><tbody>'.$rows.'</tbody></table></section>');exit;
    }
    if ($page==='periods') {
        $available=$pdo->query('SELECT period FROM global_parameter_versions ORDER BY period DESC')->fetchAll(PDO::FETCH_COLUMN);
        $period=in_array($_GET['period']??'',$available,true)?$_GET['period']:($available[0]??'');
        $target=['variables'=>'variables-grid','variables-grid'=>'variables-grid','payslips'=>'payslips','summary'=>'summary','book'=>'book','lre'=>'lre'][$_GET['sheet']??'']??null;
        if ($period && $target) go($target.'&period='.rawurlencode($period));
        $rows='';foreach ($available as $p) $rows.='<tr><td>'.h($p).'</td><td><a href="?page=variables-grid&period='.h($p).'">Variables y cálculos</a> · <a href="?page=payslips&period='.h($p).'">Liquidaciones</a></td></tr>';
        layout('Períodos','<h1>Períodos disponibles</h1><section class="card"><table><tr><th>Período</th><th>Acciones</th></tr>'.$rows.'</table></section>');exit;
    }
    if ($page==='variables-save' && $_SERVER['REQUEST_METHOD']==='POST') {
        try {
            $period=(string)($_POST['period']??'');$pp=$periodService->companyPeriod($companyId,$period);
            if ((int)($_POST['period_id']??0)!==(int)$pp['id']) throw new RuntimeException('El período no corresponde a la empresa seleccionada.');
            $periodService->editable($period,function() use ($pdo,$periodService,$companyId,$period,$pp) {
                $allowed=array_column($periodService->rows($companyId,$period),null,'id');
                $fields=array_merge(PayrollPeriods::VARIABLE_FIELDS,PayrollPeriods::ALLOWANCE_OVERRIDE_FIELDS);$columns=implode(',',$fields);$updates=implode(',',array_map(fn($f)=>$f.'=VALUES('.$f.')',$fields));
                $save=$pdo->prepare('INSERT INTO payroll_variables(period_id,employee_id,'.$columns.') VALUES('.implode(',',array_fill(0,count($fields)+2,'?')).') ON DUPLICATE KEY UPDATE '.$updates);
                foreach ($_POST['medical_leave_days']??[] as $id=>$unused) {
                    if (!isset($allowed[$id])) throw new RuntimeException('El trabajador no pertenece a esta empresa y período.');
                    $values=[(int)$pp['id'],(int)$id];foreach ($fields as $key) {
                        if (in_array($key,PayrollPeriods::ALLOWANCE_OVERRIDE_FIELDS,true)) {
                            $value=$_POST[$key][$id]??($allowed[$id][$key]??null);
                            $values[]=PayrollPeriods::allowanceOverride($value);continue;
                        }
                        $value=$_POST[$key][$id]??0;if (!is_numeric($value)) throw new RuntimeException('Ingresa un valor numérico válido.');$values[]=(int)round((float)$value);
                    }
                    $save->execute($values);
                }
            });go('variables-grid&period='.rawurlencode($period));
        } catch (Throwable $e) {periodError($e);}
    }
    if (in_array($page,['attendance-day-save','attendance-save','attendance-delete'],true) && $_SERVER['REQUEST_METHOD']==='POST') {
        try {
            if (!$periodService->tableExists('attendance_records')) throw new RuntimeException('Falta la migración de asistencia.');
            $date=(string)($_POST['work_date']??'');
            if ($page==='attendance-delete') {$q=$pdo->prepare('SELECT work_date FROM attendance_records WHERE id=? AND company_id=?');$q->execute([(int)($_POST['id']??0),$companyId]);$date=(string)$q->fetchColumn();}
            $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if (!$dt||$dt->format('Y-m-d')!==$date) throw new RuntimeException('Fecha de asistencia inválida.');
            $period=substr($date,0,7);if ($period!==(string)($_POST['period']??'')) throw new RuntimeException('La fecha no pertenece al período seleccionado.');
            $periodService->companyPeriod($companyId,$period);
            $periodService->editable($period,function() use ($pdo,$periodService,$page,$date,$companyId) {
                if ($page==='attendance-delete') {$pdo->prepare('DELETE FROM attendance_records WHERE id=? AND company_id=?')->execute([(int)$_POST['id'],$companyId]);return;}
                $q=$pdo->prepare('SELECT * FROM employees WHERE company_id=? ORDER BY full_name');$q->execute([$companyId]);$employees=array_filter($q->fetchAll(),fn($e)=>attendanceEmployeeIsActive($e,$date));
                if ($page==='attendance-save') {$id=(int)($_POST['employee_id']??0);$employees=array_filter($employees,fn($e)=>(int)$e['id']===$id);if (!$employees) throw new RuntimeException('El trabajador no está vigente para esta fecha.');}
                if ($page==='attendance-day-save') {
                    if (!$periodService->tableExists('attendance_days')) throw new RuntimeException('Falta la migración de calendario de asistencia.');
                    $type=in_array($_POST['day_type']??'', ['working','non_working'],true)?$_POST['day_type']:'working';$status=in_array($_POST['day_status']??'', ['open','complete','closed'],true)?$_POST['day_status']:'open';
                    $pdo->prepare('INSERT INTO attendance_days(company_id,work_date,day_type,status,notes,closed_by,closed_at) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE day_type=VALUES(day_type),status=VALUES(status),notes=VALUES(notes),closed_by=VALUES(closed_by),closed_at=VALUES(closed_at)')->execute([$companyId,$date,$type,$status,trim((string)($_POST['day_notes']??''))?:null,$status==='closed'?(int)$_SESSION['user']['id']:null,$status==='closed'?date('Y-m-d H:i:s'):null]);
                }
                $save=$pdo->prepare('INSERT INTO attendance_records(company_id,employee_id,work_date,check_in,check_out,status,absence_reason,is_exception,lunch_amount,snack_amount,notes,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE check_in=VALUES(check_in),check_out=VALUES(check_out),status=VALUES(status),absence_reason=VALUES(absence_reason),is_exception=VALUES(is_exception),lunch_amount=VALUES(lunch_amount),snack_amount=VALUES(snack_amount),notes=VALUES(notes),created_by=VALUES(created_by)');
                foreach ($employees as $e) {
                    $id=(int)$e['id'];$value=static fn($key,$default='')=>$page==='attendance-save'?($_POST[$key]??$default):($_POST[$key][$id]??$default);
                    $status=$value('status','present');if (!in_array($status,['present','absent','medical_leave','permission','vacation','other'],true)) throw new RuntimeException('Situación de asistencia inválida.');
                    $in=$status==='present'?trim((string)$value('check_in')):'';$out=$status==='present'?trim((string)$value('check_out')):'';
                    foreach ([$in,$out] as $time) if ($time!==''&&!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$time)) throw new RuntimeException('Hora inválida.');
                    if ($in!==''&&$out!==''&&$out<=$in) throw new RuntimeException('La salida debe ser posterior al ingreso.');
                    $save->execute([$companyId,$id,$date,$in!==''?$in.':00':null,$out!==''?$out.':00':null,$status,trim((string)$value('absence_reason'))?:null,$status!=='present'?1:0,max(0,(int)round((float)$value('lunch_amount',0))),max(0,(int)round((float)$value('snack_amount',0))),trim((string)$value('notes'))?:null,(int)$_SESSION['user']['id']]);
                }
            });$_SESSION['flash']='Asistencia actualizada.';go('attendance&period='.rawurlencode($period).'&date='.rawurlencode($date));
        } catch (Throwable $e) {periodError($e);}
    }
}
require_once __DIR__.'/PayrollReports.php';
