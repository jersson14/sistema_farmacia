<?php
ob_start();
if (strlen(session_id()) < 1) session_start();

if (!isset($_SESSION['nombre'])) {
    echo "Debe iniciar sesión para ver este reporte.";
    ob_end_flush(); exit;
}
if (!isset($_SESSION['consultav']) || $_SESSION['consultav'] != 1) {
    echo "No tiene permiso para este reporte.";
    ob_end_flush(); exit;
}

require_once "../config/Conexion.php";
require_once "../fpdf181/fpdf.php";
require_once "../modelos/Empresa.php";

// Fechas
function validarFechaRot($v){
    $v = trim((string)$v);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
}
$fi = validarFechaRot($_GET['fecha_inicio'] ?? '');
$ff = validarFechaRot($_GET['fecha_fin']    ?? '');
if (!$fi) $fi = date('Y-m-01');
if (!$ff) $ff = date('Y-m-d');

// Empresa
$emp   = (new Empresa())->datosReporte();
$sym   = obtenerSimboloMoneda(!empty($emp['moneda']) ? $emp['moneda'] : 'PEN');

// Datos
$sql = "SELECT a.codigo, a.nombre,
               IFNULL(a.principio_activo,'') AS principio_activo,
               SUM(dv.cantidad) AS total_vendido,
               SUM(dv.cantidad * dv.precio_venta - dv.descuento) AS total_importe,
               COUNT(DISTINCT dv.idventa) AS num_ventas
        FROM detalle_venta dv
        INNER JOIN articulo a ON dv.idarticulo = a.idarticulo
        INNER JOIN venta v ON dv.idventa = v.idventa
        WHERE v.estado='Aceptado'
          AND DATE(v.fecha_hora) BETWEEN '$fi' AND '$ff'
        GROUP BY a.idarticulo, a.codigo, a.nombre, a.principio_activo
        ORDER BY total_vendido DESC
        LIMIT 50";
$rs = ejecutarConsulta($sql);

// PDF
class PDF_Rotacion extends FPDF {
    var $empresa = ''; var $periodo = '';
    function Header(){
        $this->SetFont('Arial','B',13);
        $this->Cell(0,7, iconv('UTF-8','ISO-8859-1//TRANSLIT',$this->empresa),0,1,'C');
        $this->SetFont('Arial','',9);
        $this->Cell(0,5, iconv('UTF-8','ISO-8859-1//TRANSLIT','Período: '.$this->periodo),0,1,'C');
        $this->SetFont('Arial','B',12);
        $this->SetFillColor(40,40,40); $this->SetTextColor(255,255,255);
        $this->Cell(0,7,'ROTACION DE INVENTARIO - TOP 50 PRODUCTOS',0,1,'C',true);
        $this->SetTextColor(0,0,0);
        $this->Ln(2);
    }
    function Footer(){
        $this->SetY(-12); $this->SetFont('Arial','I',7);
        $this->Cell(0,5,'Pagina '.$this->PageNo().'/{nb}  |  Generado: '.date('d/m/Y H:i'),0,0,'C');
    }
}

$pdf = new PDF_Rotacion('L','mm','A4');
$pdf->AliasNbPages();
$pdf->empresa = !empty($emp['nombre']) ? $emp['nombre'] : 'FARMACIA';
$pdf->periodo = date('d/m/Y', strtotime($fi)) . ' al ' . date('d/m/Y', strtotime($ff));
$pdf->AddPage();
$pdf->SetAutoPageBreak(true,18);

// Cabecera tabla
$pdf->SetFillColor(60,60,60); $pdf->SetTextColor(255,255,255);
$pdf->SetFont('Arial','B',8);
$pdf->Cell(8,7,'N°',1,0,'C',true);
$pdf->Cell(20,7,'Código',1,0,'C',true);
$pdf->Cell(95,7,'Producto / DCI',1,0,'C',true);
$pdf->Cell(32,7,'Unid. vendidas',1,0,'C',true);
$pdf->Cell(40,7,'Importe total',1,0,'C',true);
$pdf->Cell(25,7,'N° ventas',1,1,'C',true);
$pdf->SetTextColor(0,0,0);

$i = 0;
while ($row = $rs->fetch_assoc()) {
    $i++;
    $fill = ($i % 2 === 0);
    $pdf->SetFillColor(245,245,245);
    $pdf->SetFont('Arial','',$fill ? 8 : 8);
    $producto = iconv('UTF-8','ISO-8859-1//TRANSLIT', $row['nombre']);
    if (!empty($row['principio_activo'])) {
        $producto .= ' / ' . iconv('UTF-8','ISO-8859-1//TRANSLIT', $row['principio_activo']);
    }
    $pdf->Cell(8,6,$i,1,0,'C',$fill);
    $pdf->Cell(20,6,iconv('UTF-8','ISO-8859-1//TRANSLIT',$row['codigo']),1,0,'L',$fill);
    $pdf->Cell(95,6,substr($producto,0,60),1,0,'L',$fill);
    $pdf->Cell(32,6,number_format((float)$row['total_vendido'],2),1,0,'R',$fill);
    $pdf->Cell(40,6,$sym.' '.number_format((float)$row['total_importe'],2),1,0,'R',$fill);
    $pdf->Cell(25,6,(int)$row['num_ventas'],1,1,'C',$fill);
}
if ($i === 0) {
    $pdf->SetFont('Arial','I',9);
    $pdf->Cell(0,8,'Sin ventas registradas en el período seleccionado.',1,1,'C');
}

ob_end_clean();
$pdf->Output('I', 'Rotacion_' . $fi . '_' . $ff . '.pdf');
?>
