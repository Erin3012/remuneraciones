<?php
declare(strict_types=1);

/** Global period publication, locking and immutable company payroll snapshots. */
final class PayrollPeriods {
    public const VARIABLE_FIELDS = ['medical_leave_days','unpaid_leave_days','overtime_50','overtime_100','agreed_overtime_hours','agreed_overtime_value','taxable_bonus','commissions','guaranteed_gratification','patriotic_bonus','non_taxable_bonus','viatico','advance','company_loan','ccaf_loan','patriotic_bonus_discount','other_discounts'];
    private string $mutex;
    public const ALLOWANCE_OVERRIDE_FIELDS = ['meal_allowance_override','transport_allowance_override'];

    public static function allowanceOverride(mixed $value): ?int {
        if ($value===null || $value==='') return null;
        if (!is_scalar($value) || !preg_match('/^\d{1,14}$/D',(string)$value)) throw new RuntimeException('Colación y movilización del mes deben ser pesos enteros no negativos; deja vacío para usar la ficha.');
        return (int)$value;
    }

    public function __construct(private PDO $pdo) {
        $this->mutex = 'payroll-periods-'.substr(hash('sha256', (string)$pdo->query('SELECT DATABASE()')->fetchColumn()),0,32);
    }

    public static function validatePeriod(string $period): void {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$period)) throw new RuntimeException('Selecciona un período válido (AAAA-MM).');
    }

    public function tableExists(string $table): bool {
        $q=$this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $q->execute([$table]); return (bool)$q->fetchColumn();
    }

    public function ready(): bool {
        if (!$this->tableExists('global_period_closures')||!$this->tableExists('payroll_period_snapshots')) return false;
        $q=$this->pdo->query('SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name="payroll_periods" AND column_name="status"');
        return !$q->fetchColumn();
    }

    public function assertReady(): void {
        if (!$this->ready()) throw new RuntimeException('Falta actualizar los períodos globales. Ejecuta php tools/migrate_global_periods.php desde la carpeta de la aplicación.');
    }

    public function acquireMutex(): void {
        $q=$this->pdo->prepare('SELECT GET_LOCK(?,30)');$q->execute([$this->mutex]);
        if ((int)$q->fetchColumn()!==1) throw new RuntimeException('Hay otro cambio de nómina en curso. Intenta nuevamente.');
    }

    public function releaseMutex(): void {
        $this->pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$this->mutex]);
    }

    private function atomic(callable $operation): mixed {
        $this->acquireMutex();
        try {
            $this->pdo->beginTransaction();
            $result=$operation();$this->pdo->commit();return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();throw $e;
        } finally {$this->releaseMutex();}
    }

    public function parameters(string $period): array {
        self::validatePeriod($period);
        $q=$this->pdo->prepare('SELECT values_json FROM global_parameter_versions WHERE period=?');$q->execute([$period]);
        $json=$q->fetchColumn();if ($json===false) throw new RuntimeException('El administrador todavía no ha guardado los parámetros Previred de este período.');
        return json_decode((string)$json,true,512,JSON_THROW_ON_ERROR);
    }

    public function isClosed(string $period): bool {
        $this->assertReady();self::validatePeriod($period);
        $q=$this->pdo->prepare('SELECT closed_at FROM global_period_closures WHERE period=?');$q->execute([$period]);
        return (bool)$q->fetchColumn();
    }

    public function editable(string $period, callable $operation): mixed {
        $this->assertReady();self::validatePeriod($period);
        return $this->atomic(function() use ($period,$operation) {
            $q=$this->pdo->prepare('SELECT closed_at FROM global_period_closures WHERE period=? FOR UPDATE');$q->execute([$period]);$row=$q->fetch();
            if (!$row) throw new RuntimeException('El período aún no está disponible. Guarda sus parámetros Previred primero.');
            if ($row['closed_at']) throw new RuntimeException('El período '.$period.' está cerrado para todas las empresas. Solo el administrador puede reabrirlo desde Parámetros Previred.');
            return $operation();
        });
    }

    public function publish(string $period,array $values,int $userId): void {
        $this->assertReady();self::validatePeriod($period);
        if (!$values) throw new RuntimeException('Los parámetros del período están vacíos.');
        $this->atomic(function() use ($period,$values,$userId) {
            $this->pdo->prepare('INSERT INTO global_period_closures(period) VALUES(?) ON DUPLICATE KEY UPDATE period=VALUES(period)')->execute([$period]);
            if ($this->isClosed($period)) throw new RuntimeException('Reabre el período antes de modificar sus parámetros Previred.');
            $json=json_encode($values,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            $this->pdo->prepare('INSERT INTO global_parameter_versions(period,values_json,created_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE values_json=VALUES(values_json),created_by=VALUES(created_by)')->execute([$period,$json,$userId]);
            $this->pdo->prepare('INSERT INTO payroll_periods(company_id,period) SELECT id,? FROM companies ON DUPLICATE KEY UPDATE period=VALUES(period)')->execute([$period]);
            $this->pdo->prepare('INSERT INTO parameter_versions(company_id,period,values_json,created_by) SELECT id,?,?,? FROM companies ON DUPLICATE KEY UPDATE values_json=VALUES(values_json),created_by=VALUES(created_by)')->execute([$period,$json,$userId]);
        });
    }

    /** Called inside the company-creation transaction, while holding the mutex. */
    public function provisionCompany(int $companyId): void {
        $this->assertReady();
        $this->pdo->prepare('INSERT INTO payroll_periods(company_id,period) SELECT ?,period FROM global_parameter_versions ON DUPLICATE KEY UPDATE period=VALUES(period)')->execute([$companyId]);
        $this->pdo->prepare('INSERT INTO parameter_versions(company_id,period,values_json,created_by) SELECT ?,period,values_json,created_by FROM global_parameter_versions ON DUPLICATE KEY UPDATE values_json=VALUES(values_json),created_by=VALUES(created_by)')->execute([$companyId]);
        $q=$this->pdo->prepare('SELECT pp.id,pp.period FROM payroll_periods pp JOIN global_period_closures g ON g.period=pp.period WHERE pp.company_id=? AND g.closed_at IS NOT NULL');$q->execute([$companyId]);
        foreach ($q->fetchAll() as $row) $this->snapshotCompany((int)$row['id'],$companyId,$row['period'],false);
    }

    public function companyPeriod(int $companyId,string $period): array {
        $this->parameters($period);
        $q=$this->pdo->prepare('SELECT id,company_id,period FROM payroll_periods WHERE company_id=? AND period=?');$q->execute([$companyId,$period]);
        $row=$q->fetch();if (!$row) throw new RuntimeException('Falta sincronizar este período. Ejecuta php tools/migrate_global_periods.php.');return $row;
    }

    public static function calendarDays(array $employee,string $period): int {
        $start=1;$end=30;
        if (!empty($employee['hire_date']) && substr($employee['hire_date'],0,7)===$period) $start=min(30,(int)substr($employee['hire_date'],8,2));
        if (!empty($employee['termination_date']) && substr($employee['termination_date'],0,7)===$period) $end=min(30,(int)substr($employee['termination_date'],8,2));
        return max(0,min(30,$end-$start+1));
    }

    private function liveData(int $companyId,string $period): array {
        $pp=$this->companyPeriod($companyId,$period);$params=$this->parameters($period);$params['period']=$period;
        $q=$this->pdo->prepare('SELECT * FROM employees WHERE company_id=? ORDER BY full_name');$q->execute([$companyId]);$employees=$q->fetchAll();
        $q=$this->pdo->prepare('SELECT * FROM payroll_variables WHERE period_id=?');$q->execute([$pp['id']]);$variables=[];
        foreach ($q as $v) $variables[(int)$v['employee_id']]=$v;
        $attendance=[];$days=[];
        foreach (['attendance_records'=>&$attendance,'attendance_days'=>&$days] as $table=>&$records) {
            if ($this->tableExists($table)) {$q=$this->pdo->prepare("SELECT * FROM $table WHERE company_id=? AND work_date BETWEEN ? AND LAST_DAY(?)");$q->execute([$companyId,$period.'-01',$period.'-01']);$records=$q->fetchAll();}
        }unset($records);
        $allowances=[];foreach ($attendance as $r) if (($r['status']??'present')==='present') {
            $id=(int)$r['employee_id'];$allowances[$id]['attendance_lunch']=($allowances[$id]['attendance_lunch']??0)+(int)($r['lunch_amount']??0);
            $allowances[$id]['attendance_snack']=($allowances[$id]['attendance_snack']??0)+(int)($r['snack_amount']??0);
        }
        $workers=[];$calculator=new PayrollCalculator();$first=$period.'-01';$last=date('Y-m-t',strtotime($first));
        foreach ($employees as $e) {
            if (($e['status']!=='Activo' && !$e['termination_date']) || ($e['hire_date'] && $e['hire_date']>$last) || ($e['termination_date'] && $e['termination_date']<$first)) continue;
            $v=array_replace(array_fill_keys(self::VARIABLE_FIELDS,0),array_fill_keys(self::ALLOWANCE_OVERRIDE_FIELDS,null),$variables[(int)$e['id']]??[],$allowances[(int)$e['id']]??[]);
            $v['calendar_days']=self::calendarDays($e,$period);
            $workers[]=['employee'=>$e,'variables'=>$v,'calculation'=>$calculator->calculate($e,$v,$params)];
        }
        $q=$this->pdo->prepare('SELECT * FROM companies WHERE id=?');$q->execute([$companyId]);
        return ['company'=>$q->fetch(),'parameters'=>$params,'all_employees'=>$employees,'workers'=>$workers,'attendance_records'=>$attendance,'attendance_days'=>$days];
    }

    public function data(int $companyId,string $period): array {
        $pp=$this->companyPeriod($companyId,$period);
        if (!$this->isClosed($period)) return $this->liveData($companyId,$period);
        $q=$this->pdo->prepare('SELECT snapshot_json FROM payroll_period_snapshots WHERE period_id=?');$q->execute([$pp['id']]);$json=$q->fetchColumn();
        if ($json===false) throw new RuntimeException('Falta la fotografía histórica de este período. Ejecuta php tools/migrate_global_periods.php.');
        return json_decode((string)$json,true,512,JSON_THROW_ON_ERROR);
    }

    public function rows(int $companyId,string $period): array {
        $out=[];foreach ($this->data($companyId,$period)['workers'] as $w) $out[]=array_replace($w['employee'],$w['variables'],['id'=>$w['employee']['id'],'calculation_json'=>json_encode($w['calculation'],JSON_THROW_ON_ERROR)]);
        return $out;
    }

    private function storeCalculations(int $periodId,array $workers): void {
        $q=$this->pdo->prepare('INSERT INTO payslips(period_id,employee_id,calculation_json) VALUES(?,?,?) ON DUPLICATE KEY UPDATE calculation_json=VALUES(calculation_json)');
        foreach ($workers as $w) $q->execute([$periodId,$w['employee']['id'],json_encode($w['calculation'],JSON_THROW_ON_ERROR)]);
        // Remove only stale calculations from open months, preserving IDs of valid payslips.
        $ids=array_column(array_column($workers,'employee'),'id');
        $sql='DELETE FROM payslips WHERE period_id=?';$args=[$periodId];
        if ($ids) {$sql.=' AND employee_id NOT IN ('.implode(',',array_fill(0,count($ids),'?')).')';$args=array_merge($args,$ids);}
        $this->pdo->prepare($sql)->execute($args);
    }

    public function recalculate(int $companyId,string $period): void {
        $this->editable($period,function() use ($companyId,$period) {
            $pp=$this->companyPeriod($companyId,$period);$this->storeCalculations((int)$pp['id'],$this->liveData($companyId,$period)['workers']);
        });
    }

    private function snapshotCompany(int $periodId,int $companyId,string $period,bool $preserve): void {
        $data=$this->liveData($companyId,$period);
        if ($preserve) {
            // Legacy calculations are authoritative; their old employee inputs were not versioned.
            $q=$this->pdo->prepare('SELECT e.*,p.calculation_json FROM payslips p JOIN employees e ON e.id=p.employee_id WHERE p.period_id=? AND e.company_id=? ORDER BY e.full_name');$q->execute([$periodId,$companyId]);
            $old=$q->fetchAll();$positions=[];foreach ($data['workers'] as $i=>$w) $positions[(int)$w['employee']['id']]=$i;
            foreach ($old as $e) {
                $r=json_decode($e['calculation_json'],true,512,JSON_THROW_ON_ERROR);unset($e['calculation_json']);$id=(int)$e['id'];
                if (isset($positions[$id])) $data['workers'][$positions[$id]]['calculation']=$r;
                else {
                    $q=$this->pdo->prepare('SELECT * FROM payroll_variables WHERE period_id=? AND employee_id=?');$q->execute([$periodId,$id]);
                    $data['workers'][]=['employee'=>$e,'variables'=>array_replace(array_fill_keys(self::VARIABLE_FIELDS,0),$q->fetch()?:[]),'calculation'=>$r];
                }
            }
            $data['legacy_snapshot']=true;
        } else $this->storeCalculations($periodId,$data['workers']);
        $this->pdo->prepare('INSERT INTO payroll_period_snapshots(period_id,snapshot_json) VALUES(?,?) ON DUPLICATE KEY UPDATE snapshot_json=VALUES(snapshot_json),captured_at=CURRENT_TIMESTAMP')->execute([$periodId,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    }

    public function setClosed(string $period,int $userId,bool $closed): void {
        $this->assertReady();$this->parameters($period);
        $this->atomic(function() use ($period,$userId,$closed) {
            $q=$this->pdo->prepare('SELECT closed_at FROM global_period_closures WHERE period=? FOR UPDATE');$q->execute([$period]);$row=$q->fetch();
            if (!$row) throw new RuntimeException('El período no está sincronizado.');
            if ((bool)$row['closed_at']===$closed) return;
            $q=$this->pdo->prepare('SELECT id,company_id FROM payroll_periods WHERE period=? ORDER BY company_id');$q->execute([$period]);$companies=$q->fetchAll();
            if ($closed) foreach ($companies as $pp) $this->snapshotCompany((int)$pp['id'],(int)$pp['company_id'],$period,false);
            $this->pdo->prepare('UPDATE global_period_closures SET closed_at=?,closed_by=?,snapshot_pending=0 WHERE period=?')->execute([$closed?date('Y-m-d H:i:s'):null,$closed?$userId:null,$period]);
            $audit=$this->pdo->prepare('INSERT INTO audit_logs(company_id,user_id,action,entity_type,payload_json) VALUES(?,?,?,?,?)');
            foreach ($companies as $pp) $audit->execute([$pp['company_id'],$userId,$closed?'period-global-close':'period-global-reopen','global_period',json_encode(['period'=>$period,'global'=>true],JSON_THROW_ON_ERROR)]);
        });
    }

    public function backfillSnapshots(): void {
        $this->atomic(function() {
            $q=$this->pdo->query('SELECT pp.id,pp.company_id,pp.period FROM payroll_periods pp JOIN global_period_closures g ON g.period=pp.period LEFT JOIN payroll_period_snapshots s ON s.period_id=pp.id WHERE g.closed_at IS NOT NULL AND s.period_id IS NULL');
            foreach ($q->fetchAll() as $pp) $this->snapshotCompany((int)$pp['id'],(int)$pp['company_id'],$pp['period'],true);
            $this->pdo->exec('UPDATE global_period_closures SET snapshot_pending=0');
        });
    }
}
