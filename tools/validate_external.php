<?php
declare(strict_types=1);

require_once __DIR__.'/../app/Database.php';
require_once __DIR__.'/../app/PayrollCalculator.php';

function pdfStrings(string $file): array {
    $bytes=file_get_contents($file); if($bytes===false) throw new RuntimeException("No se pudo leer $file");
    $pages=[]; $pos=0;
    while(($filter=strpos($bytes,'/Filter/FlateDecode',$pos))!==false){
        $stream=strpos($bytes,"stream\n",$filter); if($stream===false)break;
        $start=$stream+7; $end=strpos($bytes,'endstream',$start); if($end===false)break; $pos=$end+9;
        $raw=substr($bytes,$start,$end-$start); if(str_starts_with($raw,"\r"))$raw=substr($raw,1);
        $decoded=@gzuncompress($raw); if($decoded===false)$decoded=@gzinflate(substr($raw,2)); if($decoded===false)continue;
        preg_match_all('/\(((?:\\.|[^)])*)\)\s*Tj/s',$decoded,$matches);
        $page=[]; foreach($matches[1] as $value){$value=str_replace(['\\(', '\\)', '\\\\', "\\n", "\\r"],['(',')','\\',' ',' '],$value);$page[]=trim($value);}
        if($page)$pages[]=$page;
    }
    return $pages;
}
function money(string $value): ?int { $value=trim($value); if($value===''||!preg_match('/^-?[0-9][0-9.]*$/',$value))return null; return (int)str_replace('.','',$value); }
function rutKey(string $rut): string {return strtoupper(preg_replace('/[^0-9Kk]/','',$rut));}
function nextValue(array $tokens,string $label,int $from=0): ?int {foreach($tokens as $i=>$token){if($i<$from||stripos($token,$label)===false)continue;for($j=$i+1;$j<count($tokens)&&$j<=$i+4;$j++){if(($n=money($tokens[$j]))!==null)return $n;}}return null;}
function liquidationRows(string $file): array {
    $rows=[]; foreach(pdfStrings($file) as $tokens){$idx=null;$rut='';foreach($tokens as $i=>$token){if(preg_match('/\d{1,3}(?:\.\d{3})+-[0-9Kk]/',$token,$m)&&rutKey($m[0])!=='762712776'){$rut=$m[0];$idx=$i;break;}}if(!$rut)continue;$row=['rut'=>$rut,'name'=>$tokens[$idx+1]??''];foreach(['Sueldo base'=>'sueldo_base','HORAS EXTRA 50%'=>'horas_extra_50','HORAS EXTRA 100%'=>'horas_extra_100','GRATIFICACION LEGAL'=>'gratificacion','TOTAL IMPONIBLE'=>'total_imponible','VIATICOS'=>'viaticos','MOVILIZACION'=>'movilizacion','COLACION'=>'colacion','ASIGNACION FAMILIAR'=>'asignacion_familiar','TOTAL NO IMPONIBLE'=>'total_no_imponible','PREVISION'=>'prevision','SALUD'=>'salud','IMPUESTO UNICO'=>'impuesto_unico','SEGURO CESANTIA'=>'seguro_cesantia','TOTAL DESC. LEGALES'=>'total_descuentos_legales','ANTICIPO DE LIQUIDACION'=>'anticipo','TOTAL OTROS DESC.'=>'total_otros_descuentos','TOTAL HABERES:'=>'total_haberes','TOTAL DESCUENTOS:'=>'total_descuentos','ALCANCE LIQUIDO:'=>'liquido'] as $label=>$key)$row[$key]=nextValue($tokens,$label,$idx);$rows[]=$row;}
    return $rows;
}
function bookTotals(string $file): array {
    $totals=[];foreach(pdfStrings($file) as $tokens){$idx=array_search('TOTAL GENERAL',$tokens,true);if($idx===false)continue;$nums=[];for($i=$idx+1;$i<count($tokens)&&count($nums)<12;$i++)if(($n=money($tokens[$i]))!==null)$nums[]=$n;if(count($nums)>=10)$totals[]=$nums;}return $totals;
}

$liquidations=$argv[1]??'';$book=$argv[2]??'';$period='2026-07';$noDb=in_array('--no-db',$argv,true);
if(!$liquidations||!is_file($liquidations)||!$book||!is_file($book)){fwrite(STDERR,"Uso: php tools/validate_external.php <LIQUIDACIONES JULIO.pdf> <libro de rem. junio 2026.pdf>\n");exit(2);}
$external=liquidationRows($liquidations);$bookTotals=bookTotals($book);$company=null;$actual=[];$companyRut='76271277-6';
if(!$noDb){$pdo=Database::connection();$s=$pdo->prepare('SELECT * FROM companies WHERE REPLACE(REPLACE(UPPER(rut),".",""),"-","")=?');$s->execute([rutKey($companyRut)]);$company=$s->fetch();}
if($company){$s=$pdo->prepare('SELECT e.rut,e.full_name,p.calculation_json FROM employees e LEFT JOIN payroll_periods pp ON pp.company_id=e.company_id AND pp.period=? LEFT JOIN payslips p ON p.employee_id=e.id AND p.period_id=pp.id WHERE e.company_id=?');$s->execute([$period,$company['id']]);foreach($s as $row)$actual[rutKey($row['rut'])]=['calc'=>$row['calculation_json']?json_decode($row['calculation_json'],true):null];}
$csv=[["Periodo","RUT","Trabajador","Concepto","Externo","Aplicacion","Diferencia","Diagnostico"]];$compared=0;$matches=0;$differences=0;$missing=0;
foreach($external as $row){$item=$actual[rutKey($row['rut'])]??null;if(!$item){$missing++;$csv[]=[$period,$row['rut'],$row['name'],'TRABAJADOR','','','','No existe en la base local'];continue;}$calc=$item['calc']??[];$map=['sueldo_base'=>'sb','gratificacion'=>'grat','total_imponible'=>'taxable','total_no_imponible'=>'nonTax','total_haberes'=>'haberes','total_descuentos'=>'discounts','liquido'=>'net'];foreach($map as $label=>$key){if($row[$label]===null)continue;$ext=(int)$row[$label];$app=array_key_exists($key,$calc)?(int)$calc[$key]:null;$diff=$app===null?null:$app-$ext;$ok=$diff!==null&&abs($diff)<=1;$compared++;if($ok)$matches++;else$differences++;$diagnosis=$app===null?'Sin cálculo':($ok?'Coincide':'Revisar datos, variables, parámetros o fórmula');$csv[]=[$period,$row['rut'],$row['name'],$label,$ext,$app,$diff,$diagnosis];}}
$out=__DIR__.'/external_validation_'.date('Ymd_His').'.csv';$fp=fopen($out,'wb');foreach($csv as $line)fputcsv($fp,$line,';');fclose($fp);echo "Validación externa\nEmpresa: ".($company['name']??'No encontrada')." ($companyRut)\nLiquidaciones: ".count($external)." trabajadores externos\nComparaciones: $compared | Coinciden: $matches | Diferencias: $differences | No encontrados: $missing\nTotales generales libro junio detectados: ".count($bookTotals)."\nModo: ".($noDb?'extracción sin base de datos':'comparación con base de datos')."\nInforme: $out\n";
