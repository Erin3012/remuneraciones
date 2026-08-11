<?php
declare(strict_types=1);
require_once __DIR__.'/../app/PayslipPdf.php';
function moneyWords(float $value): string { return 'cien pesos'; }
$employee=['full_name'=>'Prueba','rut'=>'1-9','position'=>'Cargo','contract_type'=>'Indefinido','hire_date'=>'2026-01-01','afp'=>'Modelo','health_institution'=>'Fonasa'];
$calc=['sb'=>1000,'ot50'=>0,'ot100'=>0,'bonus'=>0,'comm'=>0,'grat'=>0,'meal'=>0,'transport'=>0,'family'=>0,'nonTax'=>0,'taxable'=>1000,'afp'=>100,'health'=>0,'scWorker'=>0,'iusc'=>0,'advance'=>0,'company_loan'=>0,'ccaf_loan'=>0,'other_discounts'=>0,'haberes'=>1000,'discounts'=>100,'net'=>900];
$pdf=PayslipPdf::render($employee,$calc,'2026-07','Empresa');
if(!str_starts_with($pdf,"%PDF-1.4\n"))throw new RuntimeException('PDF header inválido');
if(!str_contains($pdf,"xref\n")||!str_contains($pdf,"startxref\n"))throw new RuntimeException('Estructura PDF incompleta');
$marker=strrpos($pdf,"startxref\n");$xref=(int)substr($pdf,$marker+10);if(substr($pdf,$xref,4)!=='xref')throw new RuntimeException('Índice xref inválido');
echo "Payslip PDF test passed\n";
