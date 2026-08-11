<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

final class PayslipPdf
{
    public static function renderHtml(string $payslipHtml, string $css = ''): string
    {
        self::loadDompdf();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $document = '<!doctype html><html lang="es"><head><meta charset="UTF-8">'
            .'<style>'.$css.self::pdfCss().'</style></head><body>'
            .$payslipHtml.'</body></html>';
        $dompdf->loadHtml($document, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public static function download(string $payslipHtml, string $filename = 'liquidacion.pdf', string $css = ''): never
    {
        $data = self::renderHtml($payslipHtml, $css);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header_remove();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.self::safeFilename($filename).'"');
        header('Content-Length: '.strlen($data));
        header('X-Content-Type-Options: nosniff');
        echo $data;
        exit;
    }

    private static function loadDompdf(): void
    {
        $autoload = __DIR__.'/../vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new RuntimeException('Falta la dependencia Dompdf. Ejecuta composer install en el proyecto.');
        }
        require_once $autoload;
    }

    private static function safeFilename(string $filename): string
    {
        $filename = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $filename) ?: 'liquidacion.pdf';
        return str_ends_with(strtolower($filename), '.pdf') ? $filename : $filename.'.pdf';
    }

    private static function pdfCss(): string
    {
        return <<<'CSS'
@page { size: A4 portrait; margin: 10mm; }
html, body { margin: 0; padding: 0; background: #fff; color: #172033; font-family: DejaVu Sans, sans-serif; }
.payslip-reference { box-sizing: border-box; width: 190mm; max-width: 190mm; margin: 0 auto; padding: 0; border: 0; background: #fff; }
.payslip-company { display: table; width: 100%; border-bottom: 2px solid #102a43; padding-bottom: 10px; margin-bottom: 12px; }
.payslip-company > div { display: table-cell; vertical-align: top; width: 50%; }
.payslip-company h1 { font-size: 1.35rem; margin: 0 0 4px; }
.payslip-company p { margin: 3px 0; }
.payslip-date { text-align: right; font-size: .85rem; line-height: 1.7; }
.employee-data { display: block !important; width: 100%; box-sizing: border-box; border: 1px solid #cfd8e3; background: #f7f9fb; padding: 10px; margin-bottom: 12px; font-size: .85rem; }
.employee-data > div { display: inline-block !important; box-sizing: border-box; width: 49% !important; margin: 0; padding: 5px 0; vertical-align: middle; line-height: 1.4; }
.employee-data b { vertical-align: middle; }
.payslip-summary-grid { display: table !important; width: 100% !important; table-layout: fixed; border-spacing: 12px 0; margin-left: -12px; }
.payslip-summary-grid > section { display: table-cell !important; width: 50% !important; border: 1px solid #aebdca; padding: 10px; vertical-align: top; }
.payslip-summary-grid h2 { font-size: 1rem; margin: 0 0 7px; padding-bottom: 5px; border-bottom: 1px solid #aebdca; }
.payslip-summary-grid p { margin: 5px 0; font-size: .82rem; }
.payslip-summary-grid p strong { float: right; }
.payslip-summary-grid .total-line { clear: both; border-top: 2px solid #102a43; padding-top: 7px; font-weight: 700; margin-top: 12px; }
.payslip-net { clear: both; margin: 12px 0 6px; padding: 10px; border: 2px solid #102a43; text-align: right; font-size: 1.2rem; font-weight: 700; }
.amount-words { border-bottom: 1px solid #cfd8e3; padding-bottom: 10px; margin: 0 0 15px; }
.receipt { font-size: .78rem; text-align: center; margin: 15px 0 34px; }
.signature-grid { display: table; width: 100%; table-layout: fixed; text-align: center; font-size: .78rem; }
.signature-grid > div { display: table-cell; width: 50%; padding: 0 35px; }
.signature-grid span { display: block; border-top: 1px solid #172033; margin-bottom: 6px; }
.signature-grid small { display: block; margin-top: 3px; }
.batch-payslip { page-break-after: always; break-after: page; }
.batch-payslip:last-child { page-break-after: auto; break-after: auto; }
CSS;
    }
}
