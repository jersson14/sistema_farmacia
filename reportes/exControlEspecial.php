<?php
ob_start();
if (strlen(session_id()) < 1) session_start();

if (!isset($_SESSION['nombre'])) {
    echo "Debe ingresar al sistema";
    ob_end_flush();
    exit;
}
if ($_SESSION['ventas'] != 1 && $_SESSION['acceso'] != 1) {
    echo "Sin permiso para este reporte";
    ob_end_flush();
    exit;
}

require_once "../fpdf181/fpdf.php";
require_once "../modelos/ControlEspecial.php";
require_once "../modelos/Empresa.php";

if (!function_exists('fechaFiltroSeguro')) {
    function fechaFiltroSeguro($v) {
        $v = trim((string)$v);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
    }
}

$fi = fechaFiltroSeguro(isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '');
$ff = fechaFiltroSeguro(isset($_GET['fecha_fin'])    ? $_GET['fecha_fin']    : '');

$ceMdl = new ControlEspecial();
$rs     = $ceMdl->listarParaPdf($fi, $ff);
$empMdl = new Empresa();
$emp    = $empMdl->datosReporte();

$nombreEmp  = !empty($emp['nombre'])    ? $emp['nombre']    : 'Farmacia';
$rucEmp     = !empty($emp['ruc'])       ? $emp['ruc']       : '';
$dirEmp     = trim((string)($emp['direccion_linea1'] ?? '') . ' ' . (string)($emp['direccion_linea2'] ?? ''));
$telEmp     = !empty($emp['telefono'])  ? $emp['telefono']  : '';

$periodoTxt = '';
if ($fi && $ff) {
    $periodoTxt = date('d/m/Y', strtotime($fi)) . ' al ' . date('d/m/Y', strtotime($ff));
} elseif ($fi) {
    $periodoTxt = 'Desde ' . date('d/m/Y', strtotime($fi));
} elseif ($ff) {
    $periodoTxt = 'Hasta ' . date('d/m/Y', strtotime($ff));
} else {
    $periodoTxt = 'Todo el período';
}

function u8($t) { return utf8_decode((string)$t); }

class PDFLibroControl extends FPDF
{
    public $empresa   = '';
    public $ruc       = '';
    public $direccion = '';
    public $telefono  = '';
    public $periodo   = '';

    public function Header()
    {
        $this->SetFillColor(180, 30, 30);
        $this->Rect(0, 0, 297, 18, 'F');
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 13);
        $this->SetXY(8, 3);
        $this->Cell(0, 6, u8('LIBRO DE CONTROL DE PSICOTRÓPICOS Y ESTUPEFACIENTES'), 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->SetXY(8, 10);
        $this->Cell(0, 5, u8($this->empresa . ($this->ruc ? ' — RUC ' . $this->ruc : '') . '    |    Período: ' . $this->periodo), 0, 1, 'C');

        // Cabecera de columnas
        $this->SetFillColor(60, 60, 60);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetXY(8, 20);
        $cols  = array('Fecha/Hora','Medicamento','Paciente','Cant','Saldo','Lote','Venc.','Médico / Colegiatura','QF Dispensador','Diagnóstico');
        $widths = array(26, 44, 30, 10, 12, 15, 14, 46, 36, 48);
        for ($i = 0; $i < count($cols); $i++) {
            $this->Cell($widths[$i], 6, u8($cols[$i]), 1, 0, 'C', true);
        }
        $this->Ln();
    }

    public function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 8, u8('Página ') . $this->PageNo() . u8(' — Generado: ') . date('d/m/Y H:i'), 0, 0, 'C');
    }
}

$pdf = new PDFLibroControl('L', 'mm', 'A4');
$pdf->empresa   = $nombreEmp;
$pdf->ruc       = $rucEmp;
$pdf->direccion = $dirEmp;
$pdf->telefono  = $telEmp;
$pdf->periodo   = $periodoTxt;
$pdf->SetMargins(8, 30, 8);
$pdf->SetAutoPageBreak(true, 16);
$pdf->AddPage();

$widths = array(26, 44, 30, 10, 12, 15, 14, 46, 36, 48);

$pdf->SetFont('Arial', '', 8);
$fila = 0;
while ($reg = $rs->fetch_object()) {
    $fila++;
    $fill = ($fila % 2 === 0);
    if ($fill) {
        $pdf->SetFillColor(245, 240, 240);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    $pdf->SetTextColor(30, 30, 30);

    $medicamento = $reg->medicamento;
    if (!empty($reg->principio_activo)) {
        $medicamento .= "\n" . $reg->principio_activo . (!empty($reg->concentracion) ? ' ' . $reg->concentracion : '');
    }
    $medicoCol = $reg->nombre_medico . ($reg->colegiatura ? ' / ' . $reg->colegiatura : '');

    $pdf->Cell($widths[0], 5, u8($reg->fecha_registro),     1, 0, 'L', $fill);
    $pdf->Cell($widths[1], 5, u8(mb_strimwidth($medicamento, 0, 38, '...')), 1, 0, 'L', $fill);
    $pdf->Cell($widths[2], 5, u8(mb_strimwidth($reg->paciente, 0, 25, '...')), 1, 0, 'L', $fill);
    $pdf->Cell($widths[3], 5, u8(number_format((float)$reg->cantidad, 0)), 1, 0, 'C', $fill);
    $pdf->Cell($widths[4], 5, u8(number_format((float)$reg->saldo, 0)),    1, 0, 'C', $fill);
    $pdf->Cell($widths[5], 5, u8($reg->numero_lote),         1, 0, 'C', $fill);
    $pdf->Cell($widths[6], 5, u8($reg->fecha_vencimiento),   1, 0, 'C', $fill);
    $pdf->Cell($widths[7], 5, u8(mb_strimwidth($medicoCol, 0, 42, '...')), 1, 0, 'L', $fill);
    $pdf->Cell($widths[8], 5, u8(mb_strimwidth($reg->nombre_qf . ($reg->colegiatura_qf ? ' / '.$reg->colegiatura_qf : ''), 0, 33, '...')), 1, 0, 'L', $fill);
    $pdf->Cell($widths[9], 5, u8(mb_strimwidth((string)$reg->diagnostico, 0, 42, '...')), 1, 1, 'L', $fill);
}

if ($fila === 0) {
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(array_sum($widths), 8, u8('No hay registros para el período seleccionado.'), 1, 1, 'C');
}

// Firma QF al pie del último contenido
$pdf->Ln(6);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(40, 40, 40);
$pdf->Cell(100, 5, '', 0, 0);
$pdf->Cell(90, 5, u8('_________________________________'), 0, 1, 'C');
$pdf->Cell(100, 5, '', 0, 0);
$pdf->Cell(90, 5, u8('Químico Farmacéutico Responsable'), 0, 1, 'C');
$pdf->Cell(100, 5, '', 0, 0);
$pdf->Cell(90, 5, u8('CQP N° ____________________'), 0, 1, 'C');

ob_end_clean();
$pdf->Output('I', 'libro_control_especial.pdf');
?>
