<?php
declare(strict_types=1);
final class LreExporter {
    public static function csv(array $rows): string {
        $h=['RUT','Nombre Completo','Fecha Ingreso','Tipo Contrato','Días Trab.','Días Lic.Méd.','Días Lic.S/G','Sueldo Base','SB Proporcional','HH.EE $','Bono Imponible','Comisiones','Gratificación','Otros Hab. Imp.','TOTAL IMPONIBLE','AFP Nombre','AFP $','Salud Nombre','Salud $','Adic. Isapre $','AFC Trab. $','TOTAL PREV.','Imp. Único $','Asig. Familiar','Movilización','Colación','Aguinaldo','Otros No Imp.','TOTAL HABERES','Anticipo','Prést. Empresa','Prést. CCAF','Otros Dtos.','TOTAL DESCTOS.','LÍQUIDO A PAGAR','Ap.SIS Emp.','Ap.Mutual $','AFC Emp. $','TOTAL AP. EMP.','COSTO TOTAL EMP.','Comuna','Región','Ap.SANNA $'];
        $out=fopen('php://temp','w+'); fputcsv($out,$h,';'); foreach($rows as $r) fputcsv($out,$r,';'); rewind($out); return stream_get_contents($out);
    }
}
