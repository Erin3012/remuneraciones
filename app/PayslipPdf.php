<?php
declare(strict_types=1);
final class PayslipPdf
{
    private array $content = [];
    private float $y = 806;

    public static function render(array $employee, array $calc, string $period, string $company): string
    {
        $pdf = new self();
        $pdf->draw($employee, $calc, $period, $company);
        return $pdf->build();
    }

    public static function download(array $employee, array $calc, string $period, string $company): never
    {
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$employee['full_name']) ?: 'liquidacion';
        $data = self::render($employee, $calc, $period, $company);
        while (ob_get_level() > 0) ob_end_clean();
        header_remove();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="liquidacion_'.$name.'_'.$period.'.pdf"');
        header('Content-Length: '.strlen($data));
        header('X-Content-Type-Options: nosniff');
        echo $data;
        exit;
    }

    private function draw(array $e, array $r, string $period, string $company): void
    {
        $this->text(36, 806, 'LIQUIDACION DE SUELDO', 16, true);
        $this->text(36, 787, $company, 10, true);
        $this->text(36, 772, 'Periodo liquidado: '.$period, 9);
        $this->text(420, 787, 'Fecha: '.date('d/m/Y'), 9, true);
        $this->text(420, 772, 'Alcance liquidado: '.$period, 9, true);
        $this->line(36, 758, 559, 758, 1.4);

        $this->rect(36, 694, 523, 50);
        $this->text(45, 729, 'RUT: '.$e['rut'], 8);
        $this->text(295, 729, 'Nombre: '.$e['full_name'], 8);
        $this->text(45, 714, 'Cargo: '.$e['position'], 8);
        $this->text(295, 714, 'Fecha ingreso: '.(!empty($e['hire_date']) ? date('d/m/Y', strtotime((string)$e['hire_date'])) : ''), 8);
        $this->text(45, 699, 'Contrato: '.$e['contract_type'], 8);
        $this->text(295, 699, 'AFP: '.$e['afp'].'   Salud: '.$e['health_institution'], 8);

        $this->section(36, 430, 255, 'Haberes');
        $this->section(304, 430, 255, 'Descuentos');
        $money = static fn($v): string => '$'.number_format((float)$v, 0, ',', '.');
        $left = [['Sueldo base proporcional',$r['sb']],['Horas extra 50%',$r['ot50']],['Horas extra 100%',$r['ot100']],['Bono imponible',$r['bonus']],['Comisiones',$r['comm']],['Gratificacion',$r['grat']],['Colacion',$r['meal']],['Movilizacion',$r['transport']],['Asignacion familiar',$r['family']],['Aguinaldo no imponible',$r['nonTax']]];
        $right = [['AFP',$r['afp']],['Salud',$r['health']],['Seguro cesantia',$r['scWorker']],['Impuesto unico',$r['iusc']]];
        $this->rows(36, 407, 255, $left, $money);
        $this->rows(304, 407, 255, $right, $money);
        $legal=(float)$r['afp']+(float)$r['health']+(float)$r['scWorker']+(float)$r['iusc'];
        $other=(float)($r['advance']??0)+(float)($r['company_loan']??0)+(float)($r['ccaf_loan']??0)+(float)($r['other_discounts']??0);
        $this->total(36, 224, 255, 'TOTAL IMPONIBLE', $money($r['taxable']));
        $this->total(36, 195, 255, 'TOTAL NO IMPONIBLE', $money((float)$r['haberes']-(float)$r['taxable']));
        $this->total(36, 166, 255, 'TOTAL HABERES', $money($r['haberes']));
        $this->total(304, 224, 255, 'DESCUENTOS LEGALES', $money($legal));
        $this->rows(304, 207, 255, [['Anticipo',$r['advance']??0],['Prestamo empresa',$r['company_loan']??0],['Prestamo CCAF',$r['ccaf_loan']??0],['Otros descuentos',$r['other_discounts']??0]], $money);
        $this->total(304, 122, 255, 'TOTAL OTROS DESCUENTOS', $money($other));
        $this->total(304, 93, 255, 'TOTAL DESCUENTOS', $money($r['discounts']));
        $this->total(36, 105, 523, 'LIQUIDO A PAGAR', $money($r['net']), 13);
        $this->text(36, 82, 'SON: '.moneyWords((float)$r['net']), 9, true);
        $this->text(36, 58, 'Recibi conforme el monto indicado en esta liquidacion.', 8);
        $this->line(55, 28, 240, 28, .8);$this->line(355, 28, 540, 28, .8);
        $this->text(91, 15, 'Firma del empleador', 8, true);$this->text(396, 15, 'Firma del trabajador', 8, true);
    }

    private function section(float $x,float $y,float $w,string $title): void {$this->rect($x,$y,$w,267);$this->text($x+9,$y+249,$title,10,true);$this->line($x+8,$y+243,$x+$w-8,$y+243,.7);}
    private function rows(float $x,float $y,float $w,array $rows,callable $money): void {foreach($rows as [$label,$value]){if(abs((float)$value)<.00001)continue;$this->text($x+9,$y,$label,8);$this->text($x+$w-78,$y,$money($value),8,true);$y-=16;}}
    private function total(float $x,float $y,float $w,string $label,string $value,float $size=9): void {$this->line($x+8,$y+10,$x+$w-8,$y+10,1);$this->text($x+9,$y,$label,$size,true);$this->text($x+$w-90,$y,$value,$size,true);}
    private function text(float $x,float $y,string $text,float $size=9,bool $bold=false): void {$font=$bold?'/F2':'/F1';$this->content[]="BT {$font} {$size} Tf 1 0 0 1 ".number_format($x,2,'.','').' '.number_format($y,2,'.','')." Tm (".$this->escape($text).") Tj ET";}
    private function line(float $x1,float $y1,float $x2,float $y2,float $width): void {$this->content[]=$width.' w '.number_format($x1,2,'.','').' '.number_format($y1,2,'.','').' m '.number_format($x2,2,'.','').' '.number_format($y2,2,'.','').' l S';}
    private function rect(float $x,float $y,float $w,float $h): void {$this->content[]='0.55 w '.number_format($x,2,'.','').' '.number_format($y,2,'.','').' '.number_format($w,2,'.','').' '.number_format($h,2,'.','').' re S';}
    private function escape(string $text): string {$text=function_exists('iconv')?(iconv('UTF-8','Windows-1252//TRANSLIT',$text)?:$text):$text;return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$text);}
    private function build(): string {$stream=implode("\n",$this->content);$objects=[];$objects[]="<< /Type /Catalog /Pages 2 0 R >>";$objects[]="<< /Type /Pages /Kids [3 0 R] /Count 1 >>";$objects[]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>";$objects[]="<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";$objects[]="<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";$objects[]="<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream";$pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];foreach($objects as $i=>$object){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$object."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";for($i=1;$i<count($offsets);$i++)$pdf.=str_pad((string)$offsets[$i],10,'0',STR_PAD_LEFT)." 00000 n \n";$pdf.="trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";return $pdf;}
}
