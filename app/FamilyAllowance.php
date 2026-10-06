<?php
declare(strict_types=1);

/** Eligibility inputs are historical, never the taxable income of the current month. */
final class FamilyAllowance {
    public static function cycle(string $period): int {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$period)) throw new RuntimeException('Período inválido.');
        return (int)substr($period,0,4)-((int)substr($period,5,2)<7?1:0);
    }

    public static function months(string $period,string $basis='semester'): array {
        $year=self::cycle($period);$months=[];
        if($basis==='year') for($m=7;$m<=12;$m++)$months[]=($year-1).'-'.sprintf('%02d',$m);
        for($m=1;$m<=6;$m++)$months[]=$year.'-'.sprintf('%02d',$m);
        return $months;
    }

    public static function pending(string $message): array {
        return ['ready'=>false,'band'=>null,'rate'=>0,'source'=>'pending','average'=>null,'warning'=>$message];
    }

    public static function assess(array $employee,string $period,array $parameters,array $setting=[],array $history=[]): array {
        if((int)($employee['family_loads']??0)===0)return ['ready'=>true,'band'=>null,'rate'=>0,'source'=>'no_loads','average'=>null,'warning'=>''];
        $brackets=$parameters['family_brackets']??[];
        if(count($brackets)!==3)return self::pending('Faltan los tres tramos de asignación familiar en Parámetros Previred.');
        $band=$setting['accredited_band']??null;
        if($band!==null&&$band!=='') {
            if(!in_array($band,['A','B','C','D'],true))return self::pending('Tramo acreditado inválido.');
            $index=array_search($band,['A','B','C','D'],true);
            $reference=(string)($setting['reference']??'');
            $source=str_starts_with($reference,'Liquidación contable validada:')?'accounting_validated':'accredited';
            return ['ready'=>true,'band'=>$band,'rate'=>$index===3?0:(int)$brackets[$index][1],'source'=>$source,'average'=>null,'warning'=>'','reference'=>$reference];
        }
        $basis=$setting['income_basis']??'auto';
        if($basis==='auto') {
            if(($employee['contract_type']??'')==='Obra o Faena')$basis='year';
            elseif(($employee['contract_type']??'')==='Plazo Fijo')return self::pending('Confirma si el contrato a plazo fijo es de hasta seis meses para elegir el período de ingresos.');
            else $basis='semester';
        }
        $sum=0;$count=0;$days=0;$missing=[];$months=self::months($period,$basis);
        foreach($months as $month) {
            $record=$history[$month]??null;
            if(!$record||!($record['complete']??false)){$missing[]=$month;continue;}
            $amount=(float)$record['total'];$incomeDays=(int)$record['days'];
            if($amount<0||$incomeDays<0||$incomeDays>31||($amount>0&&$incomeDays===0)||($amount===0.0&&$incomeDays>0)){$missing[]=$month;continue;}
            if($incomeDays>0){$sum+=$amount;$count++;$days+=$incomeDays;}
        }
        if($days<30||$count===0) {
            // Legal fallback: when the reference semester has fewer than 30 income
            // days, use the first month in which family allowance accrued.
            $referenceEnd=end($months);$hireMonth=substr((string)($employee['hire_date']??''),0,7);
            $first=$history[$hireMonth]??null;
            if($hireMonth<=$period&&$hireMonth>$referenceEnd&&$first&&($first['complete']??false)&&(int)$first['days']>0&&(float)$first['total']>0) {
                $average=(float)$first['total'];$source='first_month';$incomeMonths=1;$incomeDays=(int)$first['days'];
            } else return self::pending($missing?'Asignación familiar pendiente: faltan ingresos completos de '.implode(', ',$missing).'. No se consideran como cero.':'Menos de 30 días con ingresos en el período de referencia: requiere revisión o tramo acreditado.');
        } else {
            if($missing)return self::pending('Asignación familiar pendiente: faltan ingresos completos de '.implode(', ',$missing).'. No se consideran como cero.');
            $average=$sum/$count;$source='history';$incomeMonths=$count;$incomeDays=$days;
        }
        $band='D';$rate=0;
        foreach($brackets as $i=>$bracket)if($average<=(float)$bracket[0]){$band=['A','B','C'][$i];$rate=(int)$bracket[1];break;}
        return ['ready'=>true,'band'=>$band,'rate'=>$rate,'source'=>$source,'average'=>$average,'warning'=>'','months'=>$months,'income_months'=>$incomeMonths,'income_days'=>$incomeDays];
    }

    /** Read saved payroll, not a recalculation using today's employee salary. */
    public static function context(PDO $pdo,int $companyId,string $period): array {
        $settings=[];$history=[];
        $exists=$pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('family_allowance_settings','family_income_history')")->fetchAll(PDO::FETCH_COLUMN);
        if(count($exists)!==2)return ['settings'=>[],'history'=>[]];
        $q=$pdo->prepare('SELECT s.* FROM family_allowance_settings s JOIN employees e ON e.id=s.employee_id WHERE e.company_id=? AND s.cycle_year=?');$q->execute([$companyId,self::cycle($period)]);
        foreach($q as $r)$settings[(int)$r['employee_id']]=$r;
        $months=self::months($period,'year');$historyEnd=max(end($months),$period);
        $q=$pdo->prepare('SELECT ps.employee_id,pp.period,ps.calculation_json,pv.medical_leave_days FROM payslips ps JOIN payroll_periods pp ON pp.id=ps.period_id LEFT JOIN payroll_variables pv ON pv.period_id=pp.id AND pv.employee_id=ps.employee_id WHERE pp.company_id=? AND pp.period BETWEEN ? AND ?');$q->execute([$companyId,$months[0],$historyEnd]);
        foreach($q as $r) {
            $calc=json_decode($r['calculation_json'],true,512,JSON_THROW_ON_ERROR);
            // A payslip alone cannot tell us the subsidy or other employer's earnings.
            $complete=array_key_exists('haberes',$calc)&&array_key_exists('family',$calc)&&array_key_exists('days',$calc)&&(int)($r['medical_leave_days']??0)===0&&(float)($calc['lic']??0)===0.0&&(float)$calc['haberes']>(float)$calc['family'];
            $history[(int)$r['employee_id']][$r['period']]=['total'=>max(0,(float)($calc['haberes']??0)-(float)($calc['family']??0)),'days'=>(int)($calc['days']??0),'complete'=>$complete,'source'=>'saved_payroll'];
        }
        $q=$pdo->prepare('SELECT h.* FROM family_income_history h JOIN employees e ON e.id=h.employee_id WHERE e.company_id=? AND h.period BETWEEN ? AND ?');$q->execute([$companyId,$months[0],$months[count($months)-1]]);
        foreach($q as $r)$history[(int)$r['employee_id']][$r['period']]=['total'=>$r['total_income'],'days'=>$r['income_days'],'complete'=>true,'source'=>'declared','reference'=>$r['reference']];
        return compact('settings','history');
    }
}
