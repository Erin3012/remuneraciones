<?php
declare(strict_types=1);
require __DIR__.'/PayrollCalculatorTest.php';
require_once __DIR__.'/../app/PayslipPdf.php';
$user=null;require_once __DIR__.'/../app/PayrollReports.php';
function absenceCheck(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$p['minimum_wage']=553553;$p['afp_rates']=['Habitat'=>.1127];$p['tax_brackets']=[[0,999999999,0,0]];
$e=array_replace($e,['base_salary'=>535101,'afp'=>'Habitat','family_loads'=>0,'rut'=>'18.405.834-0','full_name'=>'VINET CORREA EDGAR EDUARDO','position'=>'Trabajador','hire_date'=>'2026-01-01']);
$v=array_replace($v,['medical_leave_days'=>1,'overtime_50'=>3,'patriotic_bonus'=>50000,'viatico'=>166456,'advance'=>400000,'company_loan'=>50000,'patriotic_bonus_discount'=>50000,'meal_allowance_override'=>58000,'transport_allowance_override'=>38667]);
$calculator=new PayrollCalculator();$before=$calculator->calculate($e,$v,$p);
$r=$calculator->calculate($e,$v+['absent_hours'=>10.5],$p);
absenceCheck($before['net']===360382.0,'Edgar baseline changed unexpectedly');
absenceCheck($r['absentHoursDiscount']===32291,'10.5 hours at full monthly minimum must deduct 32291 pesos');
absenceCheck($r['days']===29&&$r['sb']===$before['sb']&&$r['minimumWageAdjustment']===$before['minimumWageAdjustment'],'Hours must not be converted into whole days or restore the minimum wage after the discount');
absenceCheck($r['salaryAfterAbsences']===502810&&$r['grat']===129162,'Art.50 must use the earned salary after absent hours');
absenceCheck((int)$r['taxable']===695811&&$r['afp']===78418&&$r['health']===48707&&$r['scWorker']===4175,'Contributions must use the reduced taxable earnings');
absenceCheck($r['net']===327634.0,'Discount was applied incorrectly or more than once');
absenceCheck($r['meal']===$before['meal']&&$r['transport']===$before['transport']&&$r['viatico']===$before['viatico'],'Absent hours changed non-taxable allowances');
absenceCheck($calculator->calculate($e,$v+['absent_hours'=>0],$p)===$before,'Zero hours must preserve all amounts');
$higher=$calculator->calculate(array_replace($e,['base_salary'=>900000]),$v+['absent_hours'=>1.5],$p);
absenceCheck($higher['absentHoursDiscount']===7500,'Higher salaries must use their monthly salary rather than the minimum');
$guaranteed=$calculator->calculate(array_replace($e,['gratification_type'=>'Garantizada']),array_replace($v,['absent_hours'=>10.5,'guaranteed_gratification'=>100000]),$p);
absenceCheck((int)$guaranteed['grat']===100000,'Agreed guaranteed gratification must remain the declared amount');
foreach([-1,INF,744] as $hours){$blocked=false;try{$calculator->calculate($e,$v+['absent_hours'=>$hours],$p);}catch(RuntimeException){$blocked=true;}absenceCheck($blocked,'Invalid or excessive absence accepted');}
$data=['workers'=>[['employee'=>$e,'variables'=>$v+['absent_hours'=>10.5],'calculation'=>$r]]];
$rows=payrollBookRows($data);absenceCheck(count($rows[0])===count(payrollBookHeaders()),'Book column mismatch');
absenceCheck(array_slice($rows[0],-2)===[10.5,32291],'Book must expose hours and discount');
absenceCheck(count(payrollBookRows($data,true)[0])===count($rows[0])-2,'Extra book columns leaked into LRE');
function h(mixed $value): string {return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
$source=(string)file_get_contents(__DIR__.'/../public/index.php');$offset=strpos($source,'function numberToWordsEs(');
if($offset===false)throw new RuntimeException('Payslip functions not found');eval(substr($source,$offset));
$html=payslipHtml($e,$r,'2026-09','Empresa de prueba');
absenceCheck(str_contains($html,'Dcto. horas faltadas (10,5 h)')&&str_contains($html,'-$32.291'),'Payslip must show fractional hours and the negative earning');
absenceCheck(companyLoanLabel([['installment_number'=>4,'installment_count'=>12,'amount'=>50000]],50000)==='Préstamo empresa (4/12)','Scheduled loan should show the current installment number');
absenceCheck(companyLoanLabel([],50000)==='Préstamo empresa','Manual company-loan discounts must not be mislabeled as total installments');
absenceCheck(!str_contains(payslipHtml($e,$before,'2026-09','Empresa de prueba'),'Dcto. horas faltadas'),'Zero-hour payslip gained an empty row');
foreach([$html,'<div class="batch-payslip">'.$html.'</div><div class="batch-payslip">'.$html.'</div>'] as $document){$pdf=PayslipPdf::renderHtml($document,(string)file_get_contents(__DIR__.'/../public/style.css'));absenceCheck(str_starts_with($pdf,'%PDF-'),'Individual/batch PDF generation failed');}
echo "Absent-hours calculation, book, payslip and PDF tests passed\n";
