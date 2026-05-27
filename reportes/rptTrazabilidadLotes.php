<?php
ob_start();
if (strlen(session_id()) < 1) session_start();

if (!isset($_SESSION['nombre'])) {
    echo "Debe iniciar sesión para ver este reporte.";
    ob_end_flush(); exit;
}

require_once "../config/Conexion.php";
require_once "../fpdf181/fpdf.php";
require_once "../modelos/Empresa.php";

$idlote = isset($_GET['idlote']) ? (int)$_GET['idlote'] : 0;
if ($idlote <= 0) {
    echo "ID de lote no válido.";
    ob_end_flush(); exit;
}

// Datos del lote
try {
    $lote = ejecutarConsultaSimpleFila(
        "SELECT la.*, a.nombre AS producto, a.codigo, IFNULL(a.principio_activo,'') AS principio_activo
         FROM lote_articulo la
         INNER JOIN articulo a ON la.idarticulo = a.idarticulo
         WHERE la.idlote='$idlote' LIMIT 1"
    );
} catch (Throwable $e) {
    echo "La tabla lote_articulo no existe. Ejecuta la migración.";
    ob_end_flush(); exit;
}

if (!$lote) {
    echo "Lote #$idlote no encontrado.";
    ob_end_flush(); exit;
}

// Ingreso que originó el lote
$ingreso = null;
if (!empty($lote['idingreso'])) {
    $ingreso = ejecutarConsultaSimpleFila(
        "SELECT i.idingreso, DATE_FORMAT(i.fecha_hora,'%d/%m/%Y %H:%i') AS fecha_ingreso,
                i.serie_comprobante, i.num_comprobante, p.nombre AS proveedor
         FROM ingreso i
         INNER JOIN persona p ON i.idproveedor = p.idpersona
         WHERE i.idingreso='{$lote['idingreso']}' LIMIT 1"
    );
}

// Ventas que usaron este lote
$ventas = ejecutarConsulta(
    "SELECT DATE_FORMAT(v.fecha_hora,'%d/%m/%Y %H:%i') AS fecha_venta,
            v.tipo_comprobante, v.serie_comprobante, v.num_comprobante,
            p.nombre AS cliente, dv.cantidad, dv.precio_venta
     FROM detalle_venta dv
     INNER JOIN venta v ON dv.idventa = v.idventa
     INNER JOIN persona p ON v.idcliente = p.idpersona
     WHERE dv.idlote='$idlote' AND v.estado='Aceptado'
     ORDER BY v.fecha_hora ASC"
);

// Empresa
$emp  = (new Empresa())->datosReporte();
$sym  = obtenerSimboloMoneda(!empty($emp['moneda']) ? $emp['moneda'] : 'PEN');

// PDF
class PDF_Trazabilidad extends FPDF {
    var $empresa = ''; var $logo = '';
    function Header(){
        if (!empty($this->logo) && file_exists($this->logo)) {
            $this->Image($this->logo, 10, 5, 28, 0);
        }
        $this->SetFont('Arial','B',13);
        $this->SetXY(40, 6);
        $this->Cell(0,7, iconv('UTF-8','ISO-8859-1//TRANSLIT',$this->empresa),0,1,'L');
        $this->SetFont('Arial','B',12);
        $this->SetFillColor(40,40,40); $this->SetTextColor(255,255,255);
        $this->Cell(0,7,'TRAZABILIDAD DE LOTE',0,1,'C',true);
        $this->SetTextColor(0,0,0); $this->Ln(3);
    }
    function Footer(){
        $this->SetY(-12); $this->SetFont('Arial','I',7);
        $this->Cell(0,5,'Pagina '.$this->PageNo().'/{nb}  |  Generado: '.date('d/m/Y H:i'),0,0,'C');
    }
    function seccion($titulo){
        $this->SetFont('Arial','B',10);
        $this->SetFillColor(220,230,255);
        $this->Cell(0,6, iconv('UTF-8','ISO-8859-1//TRANSLIT',$titulo),0,1,'L',true);
        $this->Ln(1);
    }
    function fila($label, $valor){
        $this->SetFont('Arial','B',9); $this->Cell(55,6, iconv('UTF-8','ISO-8859-1//TRANSLIT',$label),0,0);
        $this->SetFont('Arial','',9);  $this->Cell(0,6, iconv('UTF-8','ISO-8859-1//TRANSLIT',(string)$valor),0,1);
    }
}

$_logoPath = '';
if (!empty($emp['logo'])) {
    $l = realpath(__DIR__ . '/../files/empresa/' . $emp['logo']);
    if ($l && file_exists($l)) $_logoPath = $l;
}
if (!$_logoPath) {
    foreach ([__DIR__.'/logo1.jpeg', __DIR__.'/logo.png'] as $_p) {
        if (file_exists($_p)) { $_logoPath = $_p; break; }
    }
}

$pdf = new PDF_Trazabilidad('P','mm','A4');
$pdf->AliasNbPages();
$pdf->empresa = !empty($emp['nombre']) ? $emp['nombre'] : 'FARMACIA';
$pdf->logo    = $_logoPath;
$pdf->AddPage();
$pdf->SetAutoPageBreak(true,18);

// Sección 1: Datos del lote
$pdf->seccion('1. Datos del lote');
$pdf->fila('N° de lote:', $lote['numero_lote']);
$pdf->fila('Producto:', $lote['producto']);
if (!empty($lote['principio_activo'])) $pdf->fila('DCI / Principio activo:', $lote['principio_activo']);
$pdf->fila('Código artículo:', $lote['codigo']);
$pdf->fila('F. vencimiento:', !empty($lote['fecha_vencimiento']) ? date('d/m/Y', strtotime($lote['fecha_vencimiento'])) : '-');
$pdf->fila('F. fabricación:', !empty($lote['fecha_fabricacion']) ? date('d/m/Y', strtotime($lote['fecha_fabricacion'])) : '-');
$pdf->fila('Cantidad inicial:', number_format((float)$lote['cantidad_inicial'],2));
$pdf->fila('Cantidad actual:', number_format((float)$lote['cantidad_actual'],2));
$pdf->fila('Estado:', (int)$lote['condicion'] ? 'Activo' : 'Inactivo');
$pdf->Ln(4);

// Sección 2: Origen
$pdf->seccion('2. Ingreso de compra que generó este lote');
if ($ingreso) {
    $pdf->fila('N° ingreso:', '#' . $ingreso['idingreso']);
    $pdf->fila('Fecha:', $ingreso['fecha_ingreso']);
    $pdf->fila('Comprobante:', $ingreso['serie_comprobante'] . '-' . $ingreso['num_comprobante']);
    $pdf->fila('Proveedor:', $ingreso['proveedor']);
} else {
    $pdf->SetFont('Arial','I',9);
    $pdf->Cell(0,6,'No se encontró el ingreso de compra asociado.',0,1);
}
$pdf->Ln(4);

// Sección 3: Ventas
$pdf->seccion('3. Ventas en las que se despachó este lote');
$pdf->SetFillColor(60,60,60); $pdf->SetTextColor(255,255,255);
$pdf->SetFont('Arial','B',8);
$pdf->Cell(35,6,'Fecha venta',1,0,'C',true);
$pdf->Cell(28,6,'Comprobante',1,0,'C',true);
$pdf->Cell(70,6,'Cliente',1,0,'C',true);
$pdf->Cell(22,6,'Cantidad',1,0,'C',true);
$pdf->Cell(28,6,'P. Unit.',1,1,'C',true);
$pdf->SetTextColor(0,0,0);

$fila = 0; $totalCant = 0;
while ($v = $ventas->fetch_assoc()) {
    $fila++;
    $fill = ($fila % 2 === 0);
    $pdf->SetFillColor(245,245,245);
    $pdf->SetFont('Arial','',8);
    $pdf->Cell(35,6,$v['fecha_venta'],1,0,'C',$fill);
    $pdf->Cell(28,6,$v['tipo_comprobante'].' '.$v['serie_comprobante'].'-'.$v['num_comprobante'],1,0,'C',$fill);
    $pdf->Cell(70,6,iconv('UTF-8','ISO-8859-1//TRANSLIT',substr($v['cliente'],0,42)),1,0,'L',$fill);
    $pdf->Cell(22,6,number_format((float)$v['cantidad'],2),1,0,'R',$fill);
    $pdf->Cell(28,6,$sym.' '.number_format((float)$v['precio_venta'],2),1,1,'R',$fill);
    $totalCant += (float)$v['cantidad'];
}
if ($fila === 0) {
    $pdf->SetFont('Arial','I',9);
    $pdf->Cell(0,7,'Este lote no ha sido despachado en ninguna venta.',1,1,'C');
} else {
    $pdf->SetFont('Arial','B',9); $pdf->SetFillColor(200,230,200);
    $pdf->Cell(133,6,'Total despachado:',1,0,'R',true);
    $pdf->Cell(22,6,number_format($totalCant,2),1,0,'R',true);
    $pdf->Cell(28,6,'',1,1,'',true);
}

ob_end_clean();
$pdf->Output('I', 'Trazabilidad_Lote_' . $idlote . '.pdf');
?>
