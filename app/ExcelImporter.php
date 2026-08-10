<?php
declare(strict_types=1);

final class ExcelImporter {
    private ZipArchive $zip;
    public function __construct(string $path) {
        if (!class_exists('ZipArchive')) throw new RuntimeException('La extensión ZIP de PHP no está disponible.');
        $this->zip = new ZipArchive();
        if ($this->zip->open($path) !== true) throw new RuntimeException('No se pudo abrir el archivo Excel.');
    }
    public function close(): void { $this->zip->close(); }
    private function xml(string $path): SimpleXMLElement {
        $raw=$this->zip->getFromName($path); if($raw===false)throw new RuntimeException('Hoja Excel no encontrada: '.$path);
        return new SimpleXMLElement($raw);
    }
    private function value(SimpleXMLElement $cell): string {
        $ns=$cell->getName()==='c' ? $cell->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main') : $cell;
        if(isset($ns->is)) return trim((string)($ns->is->t ?? ''));
        return trim((string)($ns->v ?? ''));
    }
    private function rows(string $path): array {
        $sheet=$this->xml($path);$uri='http://schemas.openxmlformats.org/spreadsheetml/2006/main';$sheet->registerXPathNamespace('m',$uri);$out=[];
        foreach($sheet->xpath('//m:sheetData/m:row') as $row){$r=(int)$row->attributes()['r'];$cells=[];foreach($row->children($uri)->c as $cell){$attrs=$cell->attributes();$cells[(string)$attrs['r']]=$this->value($cell);}$out[$r]=$cells;}return $out;
    }
    private function date(string $value): ?string {
        if($value==='')return null;if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$value))return $value;if(is_numeric($value)){ $dt=new DateTime('1899-12-30');$dt->modify('+'.(int)$value.' days');return $dt->format('Y-m-d'); }return null;
    }
    private function money(string $value): float { return (float)str_replace([',','.',' '],['.','',''],$value); }
    public function employees(): array {
        $rows=$this->rows('xl/worksheets/sheet3.xml');$out=[];
        for($i=4;$i<=203;$i++){ $r=$rows[$i]??[];if(empty($r['A'.$i])||empty($r['B'.$i]))continue;$out[]=['rut'=>$r['A'.$i],'full_name'=>$r['B'.$i],'hire_date'=>$this->date($r['C'.$i]??''),'position'=>$r['D'.$i]??'','base_salary'=>$this->money($r['E'.$i]??'0'),'contract_type'=>$r['F'.$i]??'Indefinido','gratification_type'=>$r['G'.$i]??'Art.50','health_institution'=>$r['H'.$i]??'Fonasa','isapre_plan_uf'=>(float)str_replace(',','.',$r['I'.$i]??'0'),'afp'=>$r['J'.$i]??'','meal_allowance'=>$this->money($r['K'.$i]??'0'),'transport_allowance'=>$this->money($r['L'.$i]??'0'),'family_loads'=>(int)($r['M'.$i]??0),'status'=>$r['N'.$i]??'Activo','ccaf'=>$r['O'.$i]??'','commune'=>$r['P'.$i]??'','region'=>$r['Q'.$i]??'','mutual_rate'=>(float)str_replace(',','.',$r['R'.$i]??'0'),'contributes_afp'=>($r['S'.$i]??'Sí')==='Sí'];}return $out;
    }
    public function parameters(): array {
        $rows=$this->rows('xl/worksheets/sheet2.xml');$v=fn(string $cell,string $default='')=>$rows[(int)preg_replace('/\D/','',$cell)][$cell]??$default;
        $p=['uf'=>$this->money($v('B5')),'utm'=>$this->money($v('B6')),'minimum_wage'=>$this->money($v('B7')),'afp_cap_uf'=>(float)str_replace(',','.',$v('B8')),'unemployment_cap_uf'=>(float)str_replace(',','.',$v('B9')),'mutual'=>(float)str_replace(',','.',$v('E6')),'sis'=>(float)str_replace(',','.',$v('E5')),'sc_worker'=>(float)str_replace(',','.',$v('E7')),'sc_employer_indefinite'=>(float)str_replace(',','.',$v('E8')),'sc_employer_fixed'=>(float)str_replace(',','.',$v('E9')),'sanna'=>(float)str_replace(',','.',$v('E10')),'reform_afp'=>(float)str_replace(',','.',$v('H5')),'reform_ssp'=>(float)str_replace(',','.',$v('H6')),'health_cap'=>$this->money($v('H10')),'family_allowance'=>0,'family_brackets'=>[],'afp_rates'=>[],'tax_brackets'=>[]];
        for($i=14;$i<=20;$i++){ $name=$v('A'.$i);if($name!=='')$p['afp_rates'][$name]=(float)str_replace(',','.',$v('B'.$i)); }
        for($i=14;$i<=17;$i++){ $limit=$this->money($v('E'.$i));$amount=$this->money($v('F'.$i));if($limit>0)$p['family_brackets'][]=[$limit,$amount]; }
        for($i=25;$i<=32;$i++){ $row=$rows[$i]??[];if(isset($row['A'.$i]))$p['tax_brackets'][]=[(float)$row['A'.$i],(float)($row['B'.$i]??999999999),(float)($row['C'.$i]??0),(float)($row['D'.$i]??0)]; }
        return $p;
    }
}
