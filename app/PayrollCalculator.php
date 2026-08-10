<?php
declare(strict_types=1);

final class PayrollCalculator {
    private static function n(mixed $v): float { return round((float)($v ?? 0)); }
    private static function r(float $v): int { return (int)round($v, 0, PHP_ROUND_HALF_UP); }

    public function calculate(array $e, array $v, array $p): array {
        $calendarDays = max(0, min(30, (int)($v['calendar_days'] ?? 30)));
        $days = max(0, $calendarDays-(int)($v['medical_leave_days'] ?? 0)-(int)($v['unpaid_leave_days'] ?? 0));
        $base = self::n($e['base_salary']); $sb = self::r($base/30*$days);
        $lic = self::r($base/30*(int)($v['medical_leave_days'] ?? 0));
        $ot50 = self::r($base/182*1.5*self::n($v['overtime_50'] ?? 0));
        $ot100 = self::r($base/182*2*self::n($v['overtime_100'] ?? 0));
        $bonus = self::n($v['taxable_bonus'] ?? 0); $comm = self::n($v['commissions'] ?? 0);
        $grat = ($e['gratification_type'] ?? 'Art.50') === 'Garantizada' ? self::n($v['guaranteed_gratification'] ?? 0) : min(self::r(($sb+$ot50+$ot100+$bonus+$comm)*.25), self::r(self::n($p['minimum_wage'])*4.75/12));
        $taxable = $sb+$ot50+$ot100+$bonus+$comm+$grat;
        $afpCap = self::r(self::n($p['afp_cap_uf'])*self::n($p['uf'])); $scCap = self::r(self::n($p['unemployment_cap_uf'])*self::n($p['uf']));
        $afpBase = min($taxable,$afpCap); $scBase = min($taxable,$scCap);
        $afpRate = self::n($p['afp_rates'][$e['afp']] ?? 0); $afp = ($e['contributes_afp'] ?? 1) ? self::r($afpBase*$afpRate) : 0;
        $healthBase = self::r($afpBase*.07); $plan = self::r(self::n($e['isapre_plan_uf'])*self::n($p['uf']));
        $health = ($e['health_institution'] ?? 'Fonasa') === 'Fonasa' ? $healthBase : max($healthBase,$plan); $additional = ($e['health_institution'] ?? 'Fonasa') === 'Isapre' ? max(0,$plan-$healthBase) : 0;
        $scWorker = ($e['contract_type'] ?? '') === 'Indefinido' ? self::r($scBase*self::n($p['sc_worker'])) : 0;
        $iuscBase = max(0,$taxable-$lic-$afp-min($health,$selfHealthCap=$p['health_cap'] ?? 0)-$scWorker);
        $iusc = $this->tax($iuscBase,$p);
        $meal = self::r(self::n($e['meal_allowance'])*$days/30); $transport = self::r(self::n($e['transport_allowance'])*$days/30);
        $familyRate=self::n($p['family_allowance']); foreach(($p['family_brackets']??[]) as $bracket){if($taxable<=(float)$bracket[0]){$familyRate=(float)$bracket[1];break;}} $family = (int)($e['family_loads'] ?? 0) * self::r($familyRate); $nonTax = self::n($v['non_taxable_bonus'] ?? 0);
        $haberes = $taxable-$lic+$meal+$transport+$family+$nonTax; $discounts = $afp+$health+$scWorker+$iusc+self::n($v['advance'])+self::n($v['company_loan'])+self::n($v['ccaf_loan'])+self::n($v['other_discounts']);
        $sis=self::r($afpBase*self::n($p['sis'])); $mutual=self::r($afpBase*self::n($e['mutual_rate'] ?: $p['mutual'])); $scEmployer=self::r($scBase*(($e['contract_type'] ?? '')==='Indefinido'?$p['sc_employer_indefinite']:$p['sc_employer_fixed'])); $sanna=self::r($afpBase*self::n($p['sanna']));
        $reformAfp=self::r($afpBase*self::n($p['reform_afp'])); $reformSsp=self::r($afpBase*self::n($p['reform_ssp']));
        return compact('days','sb','lic','ot50','ot100','bonus','comm','grat','taxable','afpCap','scCap','afpBase','scBase','afp','health','additional','scWorker','iusc','meal','transport','family','nonTax','haberes','discounts','sis','mutual','scEmployer','sanna','reformAfp','reformSsp') + ['advance'=>self::n($v['advance']??0),'company_loan'=>self::n($v['company_loan']??0),'ccaf_loan'=>self::n($v['ccaf_loan']??0),'other_discounts'=>self::n($v['other_discounts']??0),'net'=>max(0,$haberes-$discounts),'employer_total'=>$sis+$mutual+$scEmployer+$sanna+$reformAfp+$reformSsp,'warning'=>$base<self::n($p['minimum_wage'])?'Bajo ingreso mínimo':''];
    }
    private function tax(float $base, array $p): int { foreach (($p['tax_brackets'] ?? []) as $b) if ($base >= $b[0] && $base <= $b[1]) return max(0,self::r($base*$b[2]-$b[3])); return 0; }
}
