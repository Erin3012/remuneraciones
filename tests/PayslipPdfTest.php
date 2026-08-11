<?php
declare(strict_types=1);
require_once __DIR__.'/../app/PayslipPdf.php';

$html='<article class="payslip-reference"><h1>LIQUIDACIÓN DE SUELDO</h1><p>Prueba Dompdf</p></article>';
$css=(string)file_get_contents(__DIR__.'/../public/style.css');
$pdf=PayslipPdf::renderHtml($html,$css);
if(!str_starts_with($pdf,"%PDF-"))throw new RuntimeException('PDF header inválido');
if(!str_contains($pdf,"xref\n")||!str_contains($pdf,"startxref\n"))throw new RuntimeException('Estructura PDF incompleta');
$marker=strrpos($pdf,"startxref\n");
$xref=(int)substr($pdf,$marker+10);
if(substr($pdf,$xref,4)!=='xref')throw new RuntimeException('Índice xref inválido');
echo "Payslip PDF test passed\n";
