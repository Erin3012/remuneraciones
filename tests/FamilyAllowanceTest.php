<?php
declare(strict_types=1);
require_once __DIR__.'/../app/FamilyAllowance.php';
require_once __DIR__.'/../app/PayrollCalculator.php';
function familyCheck(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$p=['period'=>'2026-09','family_brackets'=>[[649039,22601],[947990,13870],[1478539,4382]]];
$e=['family_loads'=>1,'contract_type'=>'Indefinido'];
familyCheck(FamilyAllowance::cycle('2026-01')===2025,'January must use the preceding January–June');
familyCheck(FamilyAllowance::cycle('2026-07')===2026,'July must start a new cycle');
familyCheck(FamilyAllowance::months('2026-09')===['2026-01','2026-02','2026-03','2026-04','2026-05','2026-06'],'Not a rolling semester');
$h=[];foreach(FamilyAllowance::months('2026-09') as $m)$h[$m]=['total'=>1000000,'days'=>30,'complete'=>true];
$a=FamilyAllowance::assess($e,'2026-09',$p,[],$h);familyCheck($a['band']==='C'&&$a['rate']===4382&&$a['average']==1000000,'Historical average');
$a=FamilyAllowance::assess($e,'2026-09',$p,['accredited_band'=>'B']);familyCheck($a['rate']===13870&&$a['source']==='accredited','Accreditation has priority even without history');
$a=FamilyAllowance::assess($e,'2026-09',$p,['accredited_band'=>'D'], $h);familyCheck($a['ready']&&$a['rate']===0,'D is valid, not missing');
unset($h['2026-02']);familyCheck(!FamilyAllowance::assess($e,'2026-09',$p,[],$h)['ready'],'Missing month is not zero');
$h['2026-02']=['total'=>0,'days'=>30,'complete'=>true];familyCheck(!FamilyAllowance::assess($e,'2026-09',$p,[],$h)['ready'],'Zero placeholder cannot count as 30 days of income');
$h['2026-02']=['total'=>0,'days'=>0,'complete'=>true];$a=FamilyAllowance::assess($e,'2026-09',$p,[],$h);familyCheck($a['average']==1000000&&$a['income_months']===5,'Divide only by months with income');
$h['2026-02']['complete']=false;familyCheck(!FamilyAllowance::assess($e,'2026-09',$p,[],$h)['ready'],'Subsidy missing');
foreach([649039=>'A',649040=>'B',947990=>'B',947991=>'C',1478539=>'C',1478540=>'D'] as $income=>$expected){foreach(FamilyAllowance::months('2026-09') as $m)$h[$m]=['total'=>$income,'days'=>30,'complete'=>true];familyCheck(FamilyAllowance::assess($e,'2026-09',$p,[],$h)['band']===$expected,'Bracket edge '.$income);}
$fixed=$e;$fixed['contract_type']='Plazo Fijo';familyCheck(!FamilyAllowance::assess($fixed,'2026-09',$p,[],$h)['ready'],'Unknown fixed term duration requires confirmation');
foreach(FamilyAllowance::months('2026-09','year') as $m)$h[$m]=['total'=>800000,'days'=>30,'complete'=>true];
familyCheck(count(FamilyAllowance::assess($fixed,'2026-09',$p,['income_basis'=>'year'],$h)['months'])===12,'Short contracts need 12 months');
require __DIR__.'/PayrollCalculatorTest.php';
$p['period']='2026-09';$p['family_brackets']=[[649039,22601],[947990,13870],[1478539,4382]];
$v['medical_leave_days']=30;$v['family_assessment']=FamilyAllowance::assess($e,'2026-09',$p,['accredited_band'=>'C']);
$r=(new PayrollCalculator())->calculate($e,$v,$p);familyCheck($r['days']===0&&$r['family']===4382,'Full medical leave must not change the accredited band');
$e['family_loads']=2;$r=(new PayrollCalculator())->calculate($e,$v,$p);familyCheck($r['family']===8764,'Rate times recognized loads');
unset($v['family_assessment']);$r=(new PayrollCalculator())->calculate($e,$v,$p);familyCheck(!$r['familyAssessment']['ready']&&$r['warning']!==''&&$r['family']===0,'No current-month fallback');
echo "Family allowance precedence, history, boundaries and medical leave tests passed\n";
