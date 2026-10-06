<?php
declare(strict_types=1);
require_once __DIR__.'/FamilyAllowance.php';
// Keep the entry point inside Workers instead of adding another main module.
ob_start(static function(string $html): string {
    global $user;
    if(!$user)return $html;
    return str_replace('<button>Guardar trabajadores</button>','<a class="button secondary" href="?page=family-allowances">Asignación familiar: tramos e ingresos</a> <button>Guardar trabajadores</button>',$html);
});
if(!$user||$page!=='family-allowances')return;
try {
    $cid=(int)$user['company_id'];
    $period=(string)($_POST['period']??$_GET['period']??$pdo->query('SELECT period FROM global_parameter_versions ORDER BY period DESC LIMIT 1')->fetchColumn());
    $periodService->assertReady();$params=$periodService->parameters($period);$closed=$periodService->isClosed($period);
    if(!$periodService->tableExists('family_allowance_settings')||!$periodService->tableExists('family_income_history'))throw new RuntimeException('Ejecuta php tools/migrate_family_allowance.php antes de registrar los tramos.');
    $_SESSION['family_csrf']??=bin2hex(random_bytes(32));
    $id=(int)($_POST['employee_id']??$_GET['employee_id']??0);
    $q=$pdo->prepare('SELECT * FROM employees WHERE company_id=? ORDER BY full_name');$q->execute([$cid]);$employees=$q->fetchAll();$employee=null;
    foreach($employees as $e)if((int)$e['id']===$id)$employee=$e;
    if($_SERVER['REQUEST_METHOD']==='POST') {
        if(!$employee)throw new RuntimeException('El trabajador no pertenece a la empresa seleccionada.');
        if(!hash_equals($_SESSION['family_csrf'],(string)($_POST['csrf']??'')))throw new RuntimeException('El formulario venció. Recarga la página.');
        $periodService->editable($period,function()use($pdo,$id,$period,$cid,$user){
            $band=(string)($_POST['band']??'');$basis=(string)($_POST['basis']??'auto');$reference=trim((string)($_POST['reference']??''));
            if(!in_array($band,['','A','B','C','D'],true)||!in_array($basis,['auto','semester','year'],true))throw new RuntimeException('Tramo o período de ingresos inválido.');
            if(strlen($reference)>255||($band!==''&&$reference===''))throw new RuntimeException('Indica la entidad y referencia del certificado del tramo (máximo 255 caracteres).');
            $cycle=FamilyAllowance::cycle($period);$months=FamilyAllowance::months($period,'year');
            $q=$pdo->prepare('SELECT * FROM family_allowance_settings WHERE employee_id=? AND cycle_year=?');$q->execute([$id,$cycle]);$oldSetting=$q->fetch();
            $q=$pdo->prepare('SELECT * FROM family_income_history WHERE employee_id=? AND period BETWEEN ? AND ?');$q->execute([$id,$months[0],end($months)]);$oldHistory=$q->fetchAll();
            $pdo->prepare('INSERT INTO family_allowance_settings(employee_id,cycle_year,accredited_band,income_basis,reference) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE accredited_band=VALUES(accredited_band),income_basis=VALUES(income_basis),reference=VALUES(reference)')->execute([$id,$cycle,$band===''?null:$band,$basis,$reference]);
            $save=$pdo->prepare('INSERT INTO family_income_history(employee_id,period,total_income,income_days,reference) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE total_income=VALUES(total_income),income_days=VALUES(income_days),reference=VALUES(reference)');
            $delete=$pdo->prepare('DELETE FROM family_income_history WHERE employee_id=? AND period=?');
            foreach($months as $month) {
                $amount=$_POST['income'][$month]??'';$days=$_POST['income_days'][$month]??'';$ref=trim((string)($_POST['income_reference'][$month]??''));
                if($amount===''&&$days===''&&$ref===''){$delete->execute([$id,$month]);continue;}
                if(!is_scalar($amount)||!preg_match('/^\d{1,14}$/D',(string)$amount)||!is_scalar($days)||!preg_match('/^\d{1,2}$/D',(string)$days)||(int)$days>31||((int)$amount>0&&(int)$days===0)||($ref==='')||strlen($ref)>255)throw new RuntimeException('En '.$month.' ingresa el total bruto entero, días con ingresos (0–31) y la fuente. Un mes sin ingresos debe declararse con 0 y 0.');
                $save->execute([$id,$month,(int)$amount,(int)$days,$ref]);
            }
            $pdo->prepare('INSERT INTO audit_logs(company_id,user_id,action,entity_type,payload_json) VALUES(?,?,?,?,?)')->execute([$cid,$user['id'],'family-allowance-inputs','employee',json_encode(['employee_id'=>$id,'period'=>$period,'cycle'=>$cycle,'old_setting'=>$oldSetting,'old_history'=>$oldHistory,'new_band'=>$band,'new_basis'=>$basis],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
        });
        $_SESSION['flash']='Antecedentes guardados. Recalcula el período para actualizar las liquidaciones.';go('family-allowances&period='.rawurlencode($period).'&employee_id='.$id);
    }
    $context=FamilyAllowance::context($pdo,$cid,$period);$options='<option value="">Selecciona trabajador</option>';$summary='';
    foreach($employees as $e) {
        $options.='<option value="'.(int)$e['id'].'"'.((int)$e['id']===$id?' selected':'').'>'.h($e['full_name'].' — '.$e['rut']).'</option>';
        if((int)$e['family_loads']===0)continue;
        $a=FamilyAllowance::assess($e,$period,$params,$context['settings'][(int)$e['id']]??[],$context['history'][(int)$e['id']]??[]);
        $origin=match($a['source']){'accredited'=>'Certificado acreditado','accounting_validated'=>'Liquidación contable validada','history'=>'Promedio histórico','first_month'=>'Primer mes de ingresos',default=>$a['warning']};
        $summary.='<tr><td><a href="?page=family-allowances&period='.h($period).'&employee_id='.(int)$e['id'].'">'.h($e['full_name']).'</a></td><td>'.h($e['rut']).'</td><td>'.(int)$e['family_loads'].'</td><td>'.h($a['band']??'Pendiente').'</td><td>'.($a['ready']?'$'.number_format($a['rate'],0,',','.').' por carga':'Pendiente de antecedentes').'</td><td>'.h($origin).'</td></tr>';
    }
    $flash=isset($_SESSION['flash'])?'<p class="notice">'.h($_SESSION['flash']).'</p>':'';unset($_SESSION['flash']);
    $body='<div class="head"><div><h1>Asignación familiar</h1><p class="muted">Empresa seleccionada · Período '.h($period).'. Se usa el tramo de referencia registrado; si falta, se evalúan los ingresos históricos.</p></div><a class="button secondary" href="?page=employees">Volver a trabajadores</a></div>'.$flash;
    $body.='<section class="card"><form method="get"><input type="hidden" name="page" value="family-allowances"><div class="grid"><label>Período<input type="month" name="period" value="'.h($period).'" required></label><label>Trabajador<select name="employee_id">'.$options.'</select></label></div><button>Consultar</button></form></section>';
    if($employee) {
        $setting=$context['settings'][$id]??[];$a=FamilyAllowance::assess($employee,$period,$params,$setting,$context['history'][$id]??[]);$cycle=FamilyAllowance::cycle($period);
        $bands='<option value="">Sin tramo acreditado: calcular promedio</option>';foreach(['A','B','C','D'] as $i=>$b)$bands.='<option value="'.$b.'"'.(($setting['accredited_band']??'')===$b?' selected':'').'>Tramo '.$b.' — '.($b==='D'?'Sin pago':'$'.number_format((float)($params['family_brackets'][$i][1]??0),0,',','.').' por carga').'</option>';
        $bases='';foreach(['auto'=>'Automático según contrato','semester'=>'Enero–junio: regla general / plazo fijo mayor a 6 meses','year'=>'Julio–junio: obra/faena o plazo fijo hasta 6 meses'] as $key=>$label)$bases.='<option value="'.$key.'"'.(($setting['income_basis']??'auto')===$key?' selected':'').'>'.h($label).'</option>';
        $q=$pdo->prepare('SELECT * FROM family_income_history WHERE employee_id=?');$q->execute([$id]);$manual=[];foreach($q as $r)$manual[$r['period']]=$r;
        $rows='';foreach(FamilyAllowance::months($period,'year') as $month){$m=$manual[$month]??[];$r=$context['history'][$id][$month]??null;$rows.='<tr><td>'.h($month).'</td><td>'.($r&&$r['complete']?'$'.number_format((float)$r['total'],0,',','.').' · '.(int)$r['days'].' días · '.h($r['source']==='declared'?'Declarado':'Liquidación guardada'):'Sin información completa').'</td><td><input aria-label="Ingreso bruto '.$month.'" type="number" min="0" step="1" name="income['.$month.']" value="'.h($m['total_income']??'').'" placeholder="Usar liquidación"></td><td><input aria-label="Días con ingresos '.$month.'" type="number" min="0" max="31" step="1" name="income_days['.$month.']" value="'.h($m['income_days']??'').'"></td><td><input aria-label="Fuente '.$month.'" name="income_reference['.$month.']" maxlength="255" value="'.h($m['reference']??'').'" placeholder="Certificado, subsidio, declaración"></td></tr>';}
        $assessmentLabel=match($a['source']){'accredited'=>'Tramo '.$a['band'].' · Certificado acreditado','accounting_validated'=>'Tramo '.$a['band'].' · Liquidación contable validada', 'first_month'=>'Tramo '.$a['band'].' · Primer mes de asignación: $'.number_format((float)$a['average'],0,',','.'),'history'=>'Tramo '.$a['band'].' · Promedio $'.number_format((float)$a['average'],0,',','.'),default=>''};
        $body.='<section class="card"><h2>'.h($employee['full_name']).'</h2><p class="'.($a['ready']?'notice':'error').'">'.h($a['ready']?$assessmentLabel:$a['warning']).'</p><form method="post"><input type="hidden" name="page" value="family-allowances"><input type="hidden" name="period" value="'.h($period).'"><input type="hidden" name="employee_id" value="'.$id.'"><input type="hidden" name="csrf" value="'.h($_SESSION['family_csrf']).'"><fieldset'.($closed?' disabled':'').'><legend>Tramo vigente de julio '.$cycle.' a junio '.($cycle+1).'</legend><div class="grid"><label>Tramo aplicado<select name="band">'.$bands.'</select></label><label>Documento de respaldo<input name="reference" maxlength="255" value="'.h($setting['reference']??'').'" placeholder="Certificado o liquidación contable validada"></label><label>Período legal de ingresos<select name="basis">'.$bases.'</select></label></div><details><summary>Revisar o completar ingresos históricos</summary><p>Se usan liquidaciones guardadas, sin recalcularlas con el sueldo actual. Revisa que incluyan todos los ingresos. Si hubo licencia, completa remuneraciones más subsidios y otros ingresos brutos. Los montos declarados reemplazan el total del mes: no se suman dos veces. Deja los tres campos vacíos para volver a usar la liquidación. Declara 0 y 0 solo si verificaste que no hubo ingresos.</p><div class="table-scroll"><table><thead><tr><th>Mes</th><th>Antecedente disponible</th><th>Total bruto del mes ($)</th><th>Días con ingresos</th><th>Fuente / respaldo</th></tr></thead><tbody>'.$rows.'</tbody></table></div></details><button>Guardar antecedentes</button></fieldset></form>'.($closed?'<p class="notice">Período cerrado: solo consulta. La fotografía histórica no se modifica.</p>':'').'</section>';
    }
    $body.='<section class="card"><h2>Trabajadores con cargas</h2><div class="table-scroll"><table><thead><tr><th>Trabajador</th><th>RUT</th><th>Cargas</th><th>Tramo</th><th>Valor</th><th>Origen / pendiente</th></tr></thead><tbody>'.($summary?:'<tr><td colspan="6">No hay trabajadores con cargas registradas.</td></tr>').'</tbody></table></div></section>';
    layout('Asignación familiar',$body);exit;
}catch(Throwable $e){periodError($e);}
