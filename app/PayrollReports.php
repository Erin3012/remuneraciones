<?php
declare(strict_types=1);

function payrollBookHeaders(bool $excel=false): array {
    if ($excel) return ['RUT (sin puntos)','Nombre completo','Fecha ingreso','Tipo contrato','Días trabajados','Días licencia médica','Días licencia s/goce','Sueldo base','Sueldo base proporcional','Ajuste ley sueldo base','Horas extra $','Bono imponible','Comisiones','Gratificación','Aguinaldo Fiestas Patrias','Otros haberes imponibles','TOTAL IMPONIBLE','AFP nombre','AFP $','Salud nombre','Salud $','Adicional Isapre $','AFC trabajador $','TOTAL PREVISIONAL','Impuesto único $','Asignación familiar','Movilización','Colación','Aguinaldo','Viático','Otros no imponibles','TOTAL HABERES','Anticipo','Descto. aguinaldo','Préstamo empresa','Préstamo CCAF','Otros descuentos','TOTAL DESCUENTOS','LÍQUIDO A PAGAR','Aporte SIS empleador','Aporte Mutual $','AFC empleador $','TOTAL APORTES EMPLEADOR','COSTO TOTAL EMPRESA','Comuna','Región','Aporte SANNA $'];
    return ['RUT','Nombre completo','Fecha ingreso','Tipo contrato','Días trab.','Días lic. méd.','Días lic. s/goce','Sueldo base','SB proporcional','Ajuste ley sueldo base','HH.EE. $','Bono imponible','Comisiones','Gratificación','Aguinaldo Fiestas Patrias','Otros hab. imp.','TOTAL IMPONIBLE','AFP','AFP $','Salud','Salud $','Adic. Isapre $','AFC trab. $','TOTAL PREV.','Imp. único $','Asig. familiar','Movilización','Colación','Aguinaldo','Viático','Otros no imp.','TOTAL HABERES','Anticipo','Descto. aguinaldo','Prést. empresa','Prést. CCAF','Otros descuentos','TOTAL DESCTOS.','LÍQUIDO A PAGAR','Ap. SIS','Ap. Mutual $','AFC emp. $','TOTAL AP. EMP.','COSTO TOTAL EMP.','Comuna','Región','Ap. SANNA $'];
}

function payrollBookRows(array $data,bool $lre=false): array {
    $rows=[];
    foreach ($data['workers'] as $w) {
        $e=$w['employee'];$v=$w['variables'];$r=$w['calculation'];
        $date=$e['hire_date']??'';if ($date&&!$lre) $date=date('d/m/Y',strtotime($date));
        $contract=$e['contract_type']==='Indefinido'?'1':($e['contract_type']==='Plazo Fijo'?'2':'3');
        $prev=$r['afp']+$r['health']+$r['scWorker']+($lre?0:$r['additional']);
        $rows[]=[str_replace('.','',$e['rut']),$e['full_name'],$date,$contract,$r['days'],$v['medical_leave_days']??0,$v['unpaid_leave_days']??0,$e['base_salary'],$r['sb'],$r['minimumWageAdjustment']??0,$r['ot50']+$r['ot100'],$r['bonus'],$r['comm'],$r['grat'],$r['patrioticBonus']??0,0,$r['taxable'],$e['afp'],$r['afp'],$e['health_institution'],$r['health'],$r['additional'],$r['scWorker'],$prev,$r['iusc'],$r['family'],$r['transport'],$r['meal'],$r['nonTaxableBonus']??0,$r['viatico']??0,($r['attendanceLunch']??0)+($r['attendanceSnack']??0),$r['haberes'],$v['advance']??0,$r['patrioticBonusDiscount']??0,$v['company_loan']??0,$v['ccaf_loan']??0,$v['other_discounts']??0,$r['discounts'],$r['net'],$r['sis'],$r['mutual'],$r['scEmployer'],$r['employer_total'],$r['haberes']+$r['employer_total'],$e['commune']??'',$e['region']??'',$r['sanna']];
    }
    return $rows;
}

if ($user && $page==='variables') go('variables-grid&period='.rawurlencode((string)($_GET['period']??'')));
if ($user && in_array($page,['calculate','payslips','payslip','payslip-pdf','payslips-all','payslips-all-pdf','book','book-excel','summary','lre'],true)) {
    try {
        $period=(string)($_GET['period']??'');$closed=$periodService->isClosed($period);
        if ($page==='calculate'&&$closed) throw new RuntimeException('El mes está cerrado. Reábrelo desde Parámetros Previred antes de recalcular.');
        if (!$closed) $periodService->recalculate($companyId,$period);
        $data=$periodService->data($companyId,$period);$companyName=(string)$data['company']['name'];
        $money=fn($v)=>'$'.number_format((float)$v,0,',','.');
        if (in_array($page,['payslip','payslip-pdf'],true)) {
            $selected=null;foreach ($data['workers'] as $w) if ((int)$w['employee']['id']===(int)($_GET['employee']??0)) $selected=$w;
            if (!$selected) throw new RuntimeException('No existe una liquidación de este trabajador en el período.');
            $html=payslipHtml($selected['employee'],$selected['calculation'],$period,$companyName);
            if ($page==='payslip-pdf') PayslipPdf::download($html,$period.'_'.$selected['employee']['full_name'].'.pdf',(string)file_get_contents(__DIR__.'/../public/style.css'));
            layout('Liquidación',$html.'<div class="payslip-actions"><a class="button" href="?page=payslip-pdf&period='.h($period).'&employee='.(int)$selected['employee']['id'].'">Descargar PDF</a></div>');exit;
        }
        if (in_array($page,['payslips-all','payslips-all-pdf'],true)) {
            $html='';foreach ($data['workers'] as $w) $html.='<div class="batch-payslip">'.payslipHtml($w['employee'],$w['calculation'],$period,$companyName).'</div>';
            PayslipPdf::download($html,$period.'_liquidaciones.pdf',(string)file_get_contents(__DIR__.'/../public/style.css'));
        }
        if (in_array($page,['payslips','calculate'],true)) {
            $rows='';foreach ($data['workers'] as $w) {$e=$w['employee'];$r=$w['calculation'];$rows.='<tr><td>'.h($e['rut']).'</td><td>'.h($e['full_name']).'</td><td>'.h($e['position']).'</td><td>'.$money($r['haberes']).'</td><td>'.$money($r['discounts']).'</td><td>'.$money($r['net']).'</td><td><a class="button secondary" href="?page=payslip&period='.h($period).'&employee='.(int)$e['id'].'">Abrir liquidación</a></td></tr>';}
            layout('Liquidaciones','<div class="head"><h1>04 · Liquidaciones '.h($period).'</h1><a class="button" href="?page=payslips-all-pdf&period='.h($period).'">Exportar todas en PDF</a></div><section class="card table-scroll"><p>'.($closed?'Información histórica conservada al cerrar el período.':'Liquidaciones calculadas con los datos actuales del período.').'</p><table><tr><th>RUT</th><th>Trabajador</th><th>Cargo</th><th>Haberes</th><th>Descuentos</th><th>Líquido</th><th>Documento</th></tr>'.$rows.'</table></section>');exit;
        }
        if ($page==='summary') {
            $tot=array_fill_keys(['haberes','discounts','net','employer_total','afp','health','additional','scWorker','iusc','sis','mutual','scEmployer','sanna'],0);
            foreach ($data['workers'] as $w) foreach ($tot as $key=>$unused) $tot[$key]+=$w['calculation'][$key]??0;
            $cards='';foreach (['haberes'=>'Total haberes','discounts'=>'Total descuentos','net'=>'Líquido a pagar','employer_total'=>'Aportes empleador'] as $key=>$label) $cards.='<div class="metric"><span>'.$label.'</span><strong>'.$money($tot[$key]).'</strong></div>';
            $detail='';foreach ([['AFP',$tot['afp'],$tot['sis']],['Salud / adicional Isapre',$tot['health']+$tot['additional'],0],['Seguro cesantía',$tot['scWorker'],$tot['scEmployer']],['Impuesto único',$tot['iusc'],0],['Mutual / SANNA',0,$tot['mutual']+$tot['sanna']]] as $row) $detail.='<tr><td>'.$row[0].'</td><td>'.$money($row[1]).'</td><td>'.$money($row[2]).'</td></tr>';
            layout('Resumen','<h1>05 · Resumen de cotizaciones '.h($period).'</h1><div class="cards">'.$cards.'</div><section class="card"><table><tr><th>Concepto</th><th>Trabajador</th><th>Empleador</th></tr>'.$detail.'</table></section>');exit;
        }
        if ($page==='lre') {
            header('Content-Type:text/csv; charset=utf-8');header('Content-Disposition:attachment; filename="'.$companyId.'_'.$period.'.csv"');echo LreExporter::csv(payrollBookRows($data,true));exit;
        }
        $headers=payrollBookHeaders($page==='book-excel');$rows=payrollBookRows($data);$numeric=[4,5,6,7,8,9,10,11,12,13,14,16,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,43];$tot=array_fill(0,count($headers),0);
        foreach ($rows as $row) foreach ($numeric as $i) $tot[$i]+=(float)$row[$i];
        if ($page==='book-excel') {
            header('Content-Type:application/vnd.ms-excel; charset=utf-8');header('Content-Disposition:attachment; filename="libro_remuneraciones_'.$period.'.xls"');echo BookExporter::xml('Libro de remuneraciones · '.$period,$headers,$rows,$numeric,$tot);exit;
        }
        $thead='';foreach ($headers as $label) $thead.='<th>'.h($label).'</th>';
        $format=fn($i,$v)=>in_array($i,[4,5,6],true)?(string)(int)$v:(in_array($i,$numeric,true)?$money($v):h($v));$html='';
        foreach ($rows as $row) {$html.='<tr>';foreach ($row as $i=>$v) $html.='<td class="'.(in_array($i,[14,29,34,35,39,40],true)?'excel-result':'excel-calculated').'">'.$format($i,$v).'</td>';$html.='</tr>';}
        $html.='<tr>';foreach ($headers as $i=>$unused) $html.='<td class="excel-result">'.($i===0?'TOTALES':(in_array($i,$numeric,true)?$format($i,$tot[$i]):'')).'</td>';$html.='</tr>';
        layout('Libro de remuneraciones','<div class="head"><h1>06 · Libro de remuneraciones '.h($period).'</h1><div><a class="button secondary" href="?page=book-excel&period='.h($period).'">Descargar Excel</a> <a class="button secondary" href="?page=lre&period='.h($period).'">Descargar LRE</a> <button onclick="window.print()">Imprimir / Guardar PDF</button></div></div><section class="card excel-wrap book-web-wrap"><table class="excel-grid"><thead><tr>'.$thead.'</tr></thead><tbody>'.$html.'</tbody></table></section>');exit;
    } catch (Throwable $e) {periodError($e);}
}
