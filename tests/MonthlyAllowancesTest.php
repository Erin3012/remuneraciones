<?php
require_once __DIR__.'/../app/PayrollPeriods.php';
require __DIR__.'/PayrollCalculatorTest.php';
function allowanceCheck(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$calculator=new PayrollCalculator();$v['medical_leave_days']=10;
$baseline=$calculator->calculate($e,$v,$p);
$inherit=$calculator->calculate($e,$v+['meal_allowance_override'=>null,'transport_allowance_override'=>null],$p);
allowanceCheck($baseline===$inherit,'Null must preserve every legacy value');
$over=$calculator->calculate($e,$v+['meal_allowance_override'=>80000,'transport_allowance_override'=>0],$p);
allowanceCheck($over['meal']===80000 && $over['transport']===0,'Override must not be prorated and zero must be honored');
foreach(['days','sb','taxable','afp','health','iusc','discounts'] as $key)allowanceCheck($over[$key]===$baseline[$key],'Unrelated calculation changed: '.$key);
allowanceCheck(abs(($over['haberes']-$baseline['haberes'])-(80000-$baseline['meal']-$baseline['transport']))<0.01,'Totals do not include override exactly once');
allowanceCheck($calculator->calculate($e,$v,$p)===$baseline,'Override leaked into another calculation');
allowanceCheck($calculator->calculate($e,$v+['absent_hours'=>10.5],$p)===$baseline,'Recording absent hours must not change payroll until the discount method is defined');
allowanceCheck(PayrollPeriods::absentHours('10.5')===10.5,'Fractional absent hours must be retained');
allowanceCheck(PayrollPeriods::absentHours('0')===0.0,'Zero absent hours must be accepted');
foreach(['-0.01','744.01','abc',[],INF] as $value){$blocked=false;try{PayrollPeriods::absentHours($value);}catch(RuntimeException){$blocked=true;}allowanceCheck($blocked,'Invalid absent hours accepted');}
foreach([null,''] as $value)allowanceCheck(PayrollPeriods::allowanceOverride($value)===null,'Blank must inherit');
allowanceCheck(PayrollPeriods::allowanceOverride('0')===0,'Zero must remain zero');
foreach(['-1','1.5','abc',[],100000000000000] as $value){$blocked=false;try{PayrollPeriods::allowanceOverride($value);}catch(RuntimeException){$blocked=true;}allowanceCheck($blocked,'Invalid override accepted');}
echo "Monthly allowance calculation and validation tests passed\n";
