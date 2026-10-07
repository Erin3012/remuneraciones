<?php
declare(strict_types=1);

function companyLoanRequireTables(): void {
    global $periodService;
    if (!$periodService->tableExists('company_loans') || !$periodService->tableExists('company_loan_installments')) {
        throw new RuntimeException('Falta instalar el módulo de préstamos. Ejecuta database/migration_company_loans.sql en la base de datos.');
    }
}

if ($user && in_array($page,['loans','loan-create','loan-cancel'],true)) {
    try { companyLoanRequireTables(); } catch (Throwable $e) { periodError($e,503); }
    $_SESSION['loan_csrf']??=bin2hex(random_bytes(32));
    $period=(string)($_REQUEST['period']??($_GET['period']??''));
    if ($period==='') $period=(string)($pdo->query('SELECT period FROM global_parameter_versions ORDER BY period DESC LIMIT 1')->fetchColumn()?:date('Y-m'));
    try { PayrollPeriods::validatePeriod($period);$periodService->companyPeriod((int)$user['company_id'],$period); } catch(Throwable $e) { periodError($e); }

    if ($_SERVER['REQUEST_METHOD']==='POST' && in_array($page,['loan-create','loan-cancel'],true)) {
        try {
            if (empty($_SESSION['loan_csrf']) || !hash_equals($_SESSION['loan_csrf'],(string)($_POST['csrf']??''))) throw new RuntimeException('El formulario venció. Recarga la página.');
            periodRequireAdministrator();
            $period=(string)($_POST['period']??'');PayrollPeriods::validatePeriod($period);
            $companyId=(int)$user['company_id'];$periodService->companyPeriod($companyId,$period);
            $periodService->editable($period,function() use ($pdo,$period,$companyId,$page,$user) {
                if ($page==='loan-cancel') {
                    $loanId=(int)($_POST['loan_id']??0);
                    $q=$pdo->prepare('SELECT id,employee_id,status FROM company_loans WHERE id=? AND company_id=? FOR UPDATE');$q->execute([$loanId,$companyId]);$loan=$q->fetch();
                    if (!$loan || $loan['status']!=='active') throw new RuntimeException('El préstamo no existe o ya no está activo.');
                    $pdo->prepare("UPDATE company_loan_installments SET status='cancelled' WHERE loan_id=? AND status='pending'")->execute([$loanId]);
                    $pdo->prepare("UPDATE company_loans SET status='cancelled' WHERE id=? AND company_id=?")->execute([$loanId,$companyId]);
                    $payload=['loan_id'=>$loanId,'period'=>$period,'employee_id'=>(int)$loan['employee_id']];
                    $pdo->prepare('INSERT INTO audit_logs(company_id,user_id,action,entity_type,entity_id,payload_json) VALUES(?,?,?,?,?,?)')->execute([$companyId,(int)$user['id'],'company-loan-cancel','company_loan',$loanId,json_encode($payload,JSON_THROW_ON_ERROR)]);
                    return;
                }

                $employeeId=(int)($_POST['employee_id']??0);$principalRaw=(string)($_POST['principal']??'');$mode=(string)($_POST['schedule_mode']??'count');
                if (!preg_match('/^[1-9]\d{0,13}$/D',$principalRaw)) throw new RuntimeException('El monto total debe ser un número entero de pesos mayor que cero.');
                $principal=(int)$principalRaw;if ($principal>9000000000000) throw new RuntimeException('El monto total supera el máximo permitido.');
                $lastDay=date('Y-m-t',strtotime($period.'-01'));$q=$pdo->prepare("SELECT id FROM employees WHERE id=? AND company_id=? AND (status='Activo' OR termination_date BETWEEN ? AND ?) AND (hire_date IS NULL OR hire_date<=?) AND (termination_date IS NULL OR termination_date>=?)");$q->execute([$employeeId,$companyId,$period.'-01',$lastDay,$lastDay,$period.'-01']);
                if (!$q->fetchColumn()) throw new RuntimeException('Selecciona un trabajador activo de esta empresa.');
                if ($mode==='count') {
                    $count=(int)($_POST['installment_count']??0);if ($count<1||$count>120) throw new RuntimeException('El número de cuotas debe estar entre 1 y 120.');
                    $installment=(int)ceil($principal/$count);
                } elseif ($mode==='amount') {
                    $raw=(string)($_POST['installment_amount']??'');if (!preg_match('/^[1-9]\d{0,13}$/D',$raw)) throw new RuntimeException('El valor de cuota debe ser un número entero de pesos mayor que cero.');
                    $installment=(int)$raw;if ($installment>$principal) throw new RuntimeException('El valor de la cuota no puede superar el monto del préstamo.');
                    $count=(int)ceil($principal/$installment);if ($count>120) throw new RuntimeException('El plan supera las 120 cuotas. Aumenta el valor de la cuota.');
                } else throw new RuntimeException('Selecciona cómo quieres definir las cuotas.');
                $notes=trim((string)($_POST['notes']??''));if (mb_strlen($notes)>500) throw new RuntimeException('La observación admite hasta 500 caracteres.');
                $pdo->prepare("INSERT INTO company_loans(company_id,employee_id,principal,installment_amount,installment_count,start_period,status,notes,created_by) VALUES(?,?,?,?,?,?,'active',?,?)")->execute([$companyId,$employeeId,$principal,$installment,$count,$period,$notes?:null,(int)$user['id']]);
                $loanId=(int)$pdo->lastInsertId();$remaining=$principal;$date=DateTimeImmutable::createFromFormat('!Y-m-d',$period.'-01');$insert=$pdo->prepare("INSERT INTO company_loan_installments(loan_id,employee_id,installment_number,due_period,amount) VALUES(?,?,?,?,?)");
                for($n=1;$n<=$count;$n++){$amount=min($installment,$remaining);$due=$date->modify('+'.($n-1).' months')->format('Y-m');$insert->execute([$loanId,$employeeId,$n,$due,$amount]);$remaining-=$amount;}
                $payload=['loan_id'=>$loanId,'employee_id'=>$employeeId,'principal'=>$principal,'installment_amount'=>$installment,'installment_count'=>$count,'start_period'=>$period];
                $pdo->prepare('INSERT INTO audit_logs(company_id,user_id,action,entity_type,entity_id,payload_json) VALUES(?,?,?,?,?,?)')->execute([$companyId,(int)$user['id'],'company-loan-create','company_loan',$loanId,json_encode($payload,JSON_THROW_ON_ERROR)]);
            });
            $_SESSION['flash']=$page==='loan-cancel'?'Préstamo cancelado. Las cuotas ya liquidadas se conservaron.':'Préstamo registrado. Calcula el período inicial para aplicar automáticamente la primera cuota.';
            go('loans&period='.rawurlencode($period));
        } catch(Throwable $e) { periodError($e); }
    }

    if ($page==='loans') {
        $companyId=(int)$user['company_id'];
        $q=$pdo->prepare('SELECT l.*,e.rut,e.full_name,(SELECT COALESCE(SUM(i.amount),0) FROM company_loan_installments i WHERE i.loan_id=l.id AND i.status="paid") paid_total,(SELECT COALESCE(SUM(i.amount),0) FROM company_loan_installments i WHERE i.loan_id=l.id AND i.status="pending") pending_total,(SELECT COUNT(*) FROM company_loan_installments i WHERE i.loan_id=l.id AND i.status="paid") paid_count,(SELECT COUNT(*) FROM company_loan_installments i WHERE i.loan_id=l.id AND i.status="pending") pending_count FROM company_loans l JOIN employees e ON e.id=l.employee_id WHERE l.company_id=? ORDER BY FIELD(l.status,"active","completed","cancelled"),e.full_name,l.created_at DESC');$q->execute([$companyId]);$loans=$q->fetchAll();
        $installmentsByLoan=[];if($loans){$ids=array_map(static fn($l)=>(int)$l['id'],$loans);$marks=implode(',',array_fill(0,count($ids),'?'));$q=$pdo->prepare("SELECT loan_id,installment_number,due_period,amount,status FROM company_loan_installments WHERE loan_id IN ($marks) ORDER BY due_period,installment_number");$q->execute($ids);foreach($q->fetchAll() as $i)$installmentsByLoan[(int)$i['loan_id']][]=$i;}
        $employeesQuery=$pdo->prepare("SELECT id,rut,full_name FROM employees WHERE company_id=? AND status='Activo' AND (termination_date IS NULL OR termination_date>=?) ORDER BY full_name");$employeesQuery->execute([$companyId,$period.'-01']);$options='';foreach($employeesQuery->fetchAll() as $e)$options.='<option value="'.(int)$e['id'].'">'.h($e['full_name'].' · '.$e['rut']).'</option>';
        $rows='';foreach($loans as $loan){$remaining=max(0,(int)$loan['pending_total']);$state=['active'=>'Activo','completed'=>'Pagado','cancelled'=>'Cancelado'][$loan['status']]??$loan['status'];$action='';if($loan['status']==='active'&&periodAdministrator())$action.='<form class="inline-form" method="post" action="?page=loan-cancel" onsubmit="return confirm(\'¿Cancelar las cuotas pendientes de este préstamo?\')"><input type="hidden" name="csrf" value="'.h($_SESSION['loan_csrf']).'"><input type="hidden" name="period" value="'.h($period).'"><input type="hidden" name="loan_id" value="'.(int)$loan['id'].'"><button class="secondary">Cancelar</button></form>';$plan='';foreach($installmentsByLoan[(int)$loan['id']]??[] as $i){$installmentState=['pending'=>'Pendiente','paid'=>'Descontada','cancelled'=>'Cancelada'][$i['status']]??$i['status'];$plan.='<tr><td>'.(int)$i['installment_number'].'</td><td>'.h($i['due_period']).'</td><td>$'.number_format((int)$i['amount'],0,',','.').'</td><td>'.h($installmentState).'</td></tr>';}$details='<details><summary>Ver '.(int)$loan['installment_count'].' cuotas</summary><div class="table-scroll"><table><tr><th>N.º</th><th>Período</th><th>Monto</th><th>Estado</th></tr>'.$plan.'</table></div>'.(!empty($loan['notes'])?'<p>'.h($loan['notes']).'</p>':'').'</details>';$rows.='<tr><td>'.h($loan['full_name']).'<br><small>'.h($loan['rut']).'</small></td><td>$'.number_format((int)$loan['principal'],0,',','.').'</td><td>$'.number_format((int)$loan['installment_amount'],0,',','.').'</td><td>'.(int)$loan['installment_count'].'</td><td>'.h($loan['start_period']).'</td><td>'.(int)($loan['paid_count']??0).' / '.(int)$loan['installment_count'].'</td><td>$'.number_format($remaining,0,',','.').'</td><td>'.h($state).'</td><td>'.$details.$action.'</td></tr>';}
        $flash=isset($_SESSION['flash'])?'<p class="notice">'.h($_SESSION['flash']).'</p>':'';unset($_SESSION['flash']);$form='';
        if(periodAdministrator())$form='<section class="card"><h2>Registrar préstamo</h2><p class="muted">La primera cuota se agenda en el período seleccionado. Se descontará al calcular ese mes.</p><form method="post" action="?page=loan-create" class="grid"><input type="hidden" name="csrf" value="'.h($_SESSION['loan_csrf']).'"><input type="hidden" name="period" value="'.h($period).'"><label>Trabajador<select name="employee_id" required><option value="">Seleccionar trabajador</option>'.$options.'</select></label><label>Monto total ($)<input name="principal" type="number" min="1" step="1" required></label><label>Inicio del descuento<input value="'.h($period).'" disabled></label><label>Definir cuotas<select name="schedule_mode" id="loan-schedule-mode"><option value="count">Por número de cuotas</option><option value="amount">Por valor mensual</option></select></label><label class="loan-count-field">Cantidad de cuotas<input name="installment_count" type="number" min="1" max="120" step="1" value="1" required></label><label class="loan-amount-field" hidden>Valor de cada cuota ($)<input name="installment_amount" type="number" min="1" step="1"></label><label>Observación<input name="notes" maxlength="500" placeholder="Opcional"></label><button>Crear préstamo y cuotas</button></form></section><script>document.getElementById("loan-schedule-mode").addEventListener("change",function(){const count=this.value==="count",a=document.querySelector(".loan-count-field"),b=document.querySelector(".loan-amount-field");a.hidden=!count;b.hidden=count;a.querySelector("input").required=count;b.querySelector("input").required=!count;});</script>';
        $body='<div class="head"><div><h1>Préstamos empresa</h1><p class="muted">Cuotas automáticas por trabajador y período. Período seleccionado: <strong>'.h($period).'</strong>.</p></div><form method="get"><input type="hidden" name="page" value="loans"><label>Mes de inicio<input type="month" name="period" value="'.h($period).'" required></label><button class="secondary">Ver período</button></form></div>'.$flash.$form.'<section class="card"><h2>Préstamos y saldos</h2><div class="table-scroll"><table><thead><tr><th>Trabajador</th><th>Monto inicial</th><th>Cuota</th><th>Cuotas</th><th>Inicio</th><th>Pagadas</th><th>Saldo pendiente</th><th>Estado</th><th>Acción</th></tr></thead><tbody>'.($rows?:'<tr><td colspan="9">Todavía no hay préstamos registrados.</td></tr>').'</tbody></table></div></section><section class="card"><p>Las cuotas pendientes se incorporan al descuento “Préstamo empresa” al calcular sus períodos. Las cuotas ya liquidadas quedan respaldadas en el historial. Si el préstamo se registra después de calcular su primer mes, vuelve a Variables y cálculos y presiona “Calcular”.</p></section>';
        layout('Préstamos empresa',$body);exit;
    }
}
