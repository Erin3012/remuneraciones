<?php
declare(strict_types=1);
require_once __DIR__.'/FamilyAllowance.php';

final class PayrollCalculator {
    private static function n(mixed $v): float { return (float)($v ?? 0); }
    private static function r(float $v): int { return (int)round($v, 0, PHP_ROUND_HALF_UP); }

    public function calculate(array $e, array $v, array $p): array {
        $calendarDays = max(0, min(30, (int)($v['calendar_days'] ?? 30)));
        $days = max(0, $calendarDays-(int)($v['medical_leave_days'] ?? 0)-(int)($v['unpaid_leave_days'] ?? 0));
        $base = self::n($e['base_salary']); $sb = self::r($base/30*$days);
        // Garantiza el ingreso mínimo mensual proporcional a los días remunerados.
        $minimumWage = self::n($p['minimum_wage'] ?? 0);
        $minimumWageTarget = self::r($minimumWage*$days/30);
        $minimumWageAdjustment = max(0, $minimumWageTarget-$sb);
        $lic = self::r($base/30*(int)($v['medical_leave_days'] ?? 0));
        // Para la jornada mensual de 180 horas, la hora extra se calcula sobre
        // el sueldo convenido o, si es menor, sobre el ingreso mínimo legal.
        $overtimeBase = max($base, $minimumWage);
        $overtimeHoursDivisor = 180;
        $ot50 = self::r($overtimeBase/$overtimeHoursDivisor*1.5*self::n($v['overtime_50'] ?? 0));
        $ot100 = self::r($overtimeBase/$overtimeHoursDivisor*2*self::n($v['overtime_100'] ?? 0));
        $agreedOt = self::r(self::n($v['agreed_overtime_hours'] ?? 0)*self::n($v['agreed_overtime_value'] ?? 0));
        $bonus = self::n($v['taxable_bonus'] ?? 0); $comm = self::n($v['commissions'] ?? 0); $patrioticBonus = self::n($v['patriotic_bonus'] ?? 0);
        $attendanceLunch = (int)round(self::n($v['attendance_lunch'] ?? 0)); $attendanceSnack = (int)round(self::n($v['attendance_snack'] ?? 0));
        $grat = ($e['gratification_type'] ?? 'Art.50') === 'Garantizada' ? self::n($v['guaranteed_gratification'] ?? 0) : min(self::r(($sb+$minimumWageAdjustment+$ot50+$ot100+$bonus+$comm)*.25), self::r($minimumWage*4.75/12));
        $taxable = $sb+$minimumWageAdjustment+$ot50+$ot100+$agreedOt+$bonus+$comm+$patrioticBonus+$grat;
        $afpCap = self::r(self::n($p['afp_cap_uf'])*self::n($p['uf'])); $scCap = self::r(self::n($p['unemployment_cap_uf'])*self::n($p['uf']));
        $afpBase = min($taxable,$afpCap); $scBase = min($taxable,$scCap);
        $afpRate = self::n($p['afp_rates'][$e['afp']] ?? 0); $afp = ($e['contributes_afp'] ?? 1) ? self::r($afpBase*$afpRate) : 0;
        $healthBase = self::r($afpBase*.07); $plan = self::r(self::n($e['isapre_plan_uf'])*self::n($p['uf']));
        $health = ($e['health_institution'] ?? 'Fonasa') === 'Fonasa' ? $healthBase : max($healthBase,$plan); $additional = ($e['health_institution'] ?? 'Fonasa') === 'Isapre' ? max(0,$plan-$healthBase) : 0;
        $scWorker = ($e['contract_type'] ?? '') === 'Indefinido' ? self::r($scBase*self::n($p['sc_worker'])) : 0;
        $iuscBase = max(0,$taxable-$afp-min($health,$selfHealthCap=$p['health_cap'] ?? 0)-$scWorker);
        $iusc = $this->tax($iuscBase,$p);
        // Overrides are final amounts for this month: null inherits the employee
        // allowance, while zero explicitly means no allowance. Do not prorate twice.
        $meal = isset($v['meal_allowance_override']) ? self::r(self::n($v['meal_allowance_override'])) : self::r(self::n($e['meal_allowance'])*$days/30);
        $transport = isset($v['transport_allowance_override']) ? self::r(self::n($v['transport_allowance_override'])) : self::r(self::n($e['transport_allowance'])*$days/30);
        $familyAssessment=$v['family_assessment']??FamilyAllowance::assess($e,(string)($p['period']??date('Y-m')),$p);
        $family = (int)($e['family_loads'] ?? 0) * (int)$familyAssessment['rate'];
        $nonTaxableBonus=self::n($v['non_taxable_bonus'] ?? 0); $viatico=self::n($v['viatico'] ?? 0); $nonTax = $nonTaxableBonus+$viatico+$attendanceLunch+$attendanceSnack;
        $patrioticBonusDiscount = self::n($v['patriotic_bonus_discount'] ?? 0);
        $haberes = $taxable+$meal+$transport+$family+$nonTax; $discounts = $afp+$health+$scWorker+$iusc+self::n($v['advance'])+self::n($v['company_loan'])+self::n($v['ccaf_loan'])+$patrioticBonusDiscount+self::n($v['other_discounts']);
        $sis=self::r($afpBase*self::n($p['sis'])); $mutual=self::r($afpBase*self::n($e['mutual_rate'] ?: $p['mutual'])); $scEmployer=self::r($scBase*(($e['contract_type'] ?? '')==='Indefinido'?$p['sc_employer_indefinite']:$p['sc_employer_fixed'])); $sanna=self::r($afpBase*self::n($p['sanna']));
        $reformAfp=self::r($afpBase*self::n($p['reform_afp'])); $reformSsp=self::r($afpBase*self::n($p['reform_ssp']));
         return compact('days','sb','minimumWageTarget','minimumWageAdjustment','overtimeBase','overtimeHoursDivisor','lic','ot50','ot100','agreedOt','bonus','comm','patrioticBonus','grat','taxable','afpCap','scCap','afpBase','scBase','afp','health','additional','scWorker','iusc','meal','transport','family','familyAssessment','patrioticBonusDiscount','nonTaxableBonus','viatico','attendanceLunch','attendanceSnack','nonTax','haberes','discounts','sis','mutual','scEmployer','sanna','reformAfp','reformSsp') + ['advance'=>self::n($v['advance']??0),'company_loan'=>self::n($v['company_loan']??0),'ccaf_loan'=>self::n($v['ccaf_loan']??0),'other_discounts'=>self::n($v['other_discounts']??0),'net'=>max(0,$haberes-$discounts),'employer_total'=>$sis+$mutual+$scEmployer+$sanna+$reformAfp+$reformSsp,'warning'=>trim(($minimumWageAdjustment>0?'Ajuste ingreso mínimo aplicado. ':'').$familyAssessment['warning'])];
    }
    private function tax(float $base, array $p): int { foreach (($p['tax_brackets'] ?? []) as $b) if ($base >= $b[0] && $base <= $b[1]) return max(0,self::r($base*$b[2]-$b[3])); return 0; }
}
