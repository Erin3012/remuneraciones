<?php
declare(strict_types=1);

final class BookExporter {
    private static function esc(mixed $value): string { return htmlspecialchars((string)$value, ENT_XML1|ENT_QUOTES, 'UTF-8'); }
    public static function xml(string $title, array $headers, array $rows, array $numericIndexes, array $totals): string {
        $xml='<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?>';
        $xml.='<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40"><DocumentProperties xmlns="urn:schemas-microsoft-com:office:office"><Author>Remuneraciones</Author></DocumentProperties><ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel"><ProtectStructure>False</ProtectStructure><ProtectWindows>False</ProtectWindows></ExcelWorkbook><Styles>';
        $xml.='<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="10"/></Style>';
        $xml.='<Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#17324D" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#102A43"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#FFFFFF"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#FFFFFF"/></Borders><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/></Style>';
        $xml.='<Style ss:ID="Text"><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2EA"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2EA"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2EA"/></Borders></Style>';
        $xml.='<Style ss:ID="Money"><NumberFormat ss:Format="#,##0;[Red](#,##0);-"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2EA"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2EA"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2EA"/></Borders><Alignment ss:Horizontal="Right"/></Style>';
        $xml.='<Style ss:ID="Total"><Font ss:Bold="1"/><Interior ss:Color="#FFF3C4" ss:Pattern="Solid"/><NumberFormat ss:Format="#,##0;[Red](#,##0);-"/><Borders><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#A88725"/><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#A88725"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#A88725"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#A88725"/></Borders></Style></Styles>';
        $xml.='<Worksheet ss:Name="Libro remuneraciones"><Table>';
        foreach($headers as $header)$xml.='<Column ss:Width="'.(mb_strlen((string)$header)>18?125:95).'"/>';
        $xml.='<Row ss:Height="34"><Cell ss:MergeAcross="'.(count($headers)-1).'" ss:StyleID="Header"><Data ss:Type="String">'.self::esc($title).'</Data></Cell></Row><Row ss:Height="42">';
        foreach($headers as $header)$xml.='<Cell ss:StyleID="Header"><Data ss:Type="String">'.self::esc($header).'</Data></Cell>';
        $xml.='</Row>';
        foreach($rows as $row){$xml.='<Row>';foreach($row as $i=>$value){$numeric=in_array($i,$numericIndexes,true);$empty=$value===null||$value==='';$style=$numeric?'Money':'Text';$xml.=$empty?'<Cell ss:StyleID="'.$style.'"/>':'<Cell ss:StyleID="'.$style.'"><Data ss:Type="'.($numeric?'Number':'String').'">'.self::esc($value).'</Data></Cell>';}$xml.='</Row>';}
        $xml.='<Row>';foreach($headers as $i=>$unused){$value=$totals[$i]??null;$numeric=in_array($i,$numericIndexes,true);$value=$i===0?'TOTALES':$value;$style=$numeric?'Total':'Text';$xml.=($value===null||$value==='')?'<Cell ss:StyleID="'.$style.'"/>':'<Cell ss:StyleID="'.$style.'"><Data ss:Type="'.($numeric?'Number':'String').'">'.self::esc($value).'</Data></Cell>';}$xml.='</Row></Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>2</SplitHorizontal><TopRowBottomPane>2</TopRowBottomPane><ActivePane>2</ActivePane><ProtectObjects>False</ProtectObjects><ProtectScenarios>False</ProtectScenarios></WorksheetOptions></Worksheet></Workbook>';
        return $xml;
    }
}
