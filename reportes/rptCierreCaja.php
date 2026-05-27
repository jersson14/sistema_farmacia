<?php
ob_start();
if (strlen(session_id()) < 1) {
  session_start();
}

if (!isset($_SESSION['nombre'])) {
  echo "Debe iniciar sesion para ver este reporte.";
  ob_end_flush();
  exit;
}

$idcaja = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idcaja <= 0) {
  echo "ID de caja no valido.";
  ob_end_flush();
  exit;
}

require_once "../config/Conexion.php";
require_once "../fpdf181/fpdf.php";
require_once "../modelos/Empresa.php";

// --- Datos de la empresa ---
$empresaModel = new Empresa();
$cfg = $empresaModel->datosReporte();
$nombreEmpresa = !empty($cfg['nombre'])  ? $cfg['nombre']  : 'FARMACIA';
$rucEmpresa    = !empty($cfg['ruc'])     ? $cfg['ruc']     : '';
$dirEmpresa    = trim((string)$cfg['direccion_linea1'] . ' ' . (string)$cfg['direccion_linea2']);
$telEmpresa    = !empty($cfg['telefono']) ? $cfg['telefono'] : '';
$monedaSimbolo = !empty($cfg['moneda'])  ? obtenerSimboloMoneda(strtoupper($cfg['moneda'])) : 'S/';

// --- Datos de la caja ---
$sqlCaja = "SELECT c.idcaja, c.fecha_apertura, c.fecha_cierre, c.monto_apertura,
              c.monto_cierre_sistema, c.monto_cierre_real, c.diferencia, c.estado, c.observacion,
              u.nombre AS cajero
            FROM caja_diaria c
            INNER JOIN usuario u ON u.idusuario = c.idusuario
            WHERE c.idcaja = '$idcaja' LIMIT 1";
$caja = ejecutarConsultaSimpleFila($sqlCaja);

if (!$caja) {
  echo "Caja no encontrada.";
  ob_end_flush();
  exit;
}

// Verificar que el usuario tiene permiso para ver esta caja
$esAdmin = isset($_SESSION['acceso']) && $_SESSION['acceso'] == 1;
$esPropietario = isset($_SESSION['idusuario']) && ejecutarConsultaSimpleFila(
  "SELECT idcaja FROM caja_diaria WHERE idcaja='$idcaja' AND idusuario='" . (int)$_SESSION['idusuario'] . "' LIMIT 1"
);
if (!$esAdmin && !$esPropietario) {
  echo "No tiene permiso para ver esta caja.";
  ob_end_flush();
  exit;
}

// --- Resumen de movimientos ---
$sqlResumen = "SELECT
    IFNULL(SUM(CASE WHEN tipo='INGRESO' AND concepto LIKE 'Venta %' THEN monto ELSE 0 END),0) AS ventas_efectivo,
    IFNULL(SUM(CASE WHEN tipo='INGRESO' AND concepto NOT LIKE 'Venta %' THEN monto ELSE 0 END),0) AS ingresos_manuales,
    IFNULL(SUM(CASE WHEN tipo='EGRESO' THEN monto ELSE 0 END),0) AS egresos
  FROM caja_movimiento WHERE idcaja='$idcaja'";
$resumen = ejecutarConsultaSimpleFila($sqlResumen);

$apertura        = (float)$caja['monto_apertura'];
$ventasEfectivo  = (float)$resumen['ventas_efectivo'];
$ingresosManuales= (float)$resumen['ingresos_manuales'];
$egresos         = (float)$resumen['egresos'];
$totalSistema    = $apertura + $ventasEfectivo + $ingresosManuales - $egresos;
$totalReal       = (float)$caja['monto_cierre_real'];
$diferencia      = (float)$caja['diferencia'];

// Si la caja está abierta, totalReal y diferencia se calculan en tiempo real
if ($caja['estado'] === 'ABIERTA') {
  $totalReal  = 0;
  $diferencia = 0;
}

// --- Movimientos ---
$sqlMov = "SELECT m.fecha_hora, m.tipo, m.concepto, m.monto, u.nombre AS usuario
           FROM caja_movimiento m
           INNER JOIN usuario u ON u.idusuario = m.idusuario
           WHERE m.idcaja='$idcaja'
           ORDER BY m.idmovimiento ASC";
$movimientos = ejecutarConsulta($sqlMov);

// =====================================================================
// PDF con FPDF
// =====================================================================
class PDF_CierreCaja extends FPDF
{
  var $empresa    = '';
  var $ruc        = '';
  var $direccion  = '';
  var $logo       = '';

  function Header()
  {
    if (!empty($this->logo) && file_exists($this->logo)) {
      $this->Image($this->logo, 10, 5, 28, 0);
    }
    $this->SetFont('Arial', 'B', 13);
    $this->SetXY(40, 6);
    $this->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $this->empresa), 0, 1, 'L');
    if ($this->ruc) {
      $this->SetFont('Arial', '', 9);
      $this->SetX(40);
      $this->Cell(0, 5, 'RUC: ' . $this->ruc, 0, 1, 'L');
    }
    if ($this->direccion) {
      $this->SetFont('Arial', '', 8);
      $this->SetX(40);
      $this->Cell(0, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $this->direccion), 0, 1, 'L');
    }
    $this->SetFont('Arial', 'B', 13);
    $this->SetFillColor(40, 40, 40);
    $this->SetTextColor(255, 255, 255);
    $this->Cell(0, 8, 'REPORTE DE CIERRE DE CAJA', 0, 1, 'C', true);
    $this->SetTextColor(0, 0, 0);
    $this->Ln(3);
  }

  function Footer()
  {
    $this->SetY(-12);
    $this->SetFont('Arial', 'I', 7);
    $this->Cell(0, 5, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
  }

  function fmtNum($val, $simbolo = 'S/') {
    return $simbolo . ' ' . number_format((float)$val, 2);
  }

  function filaResumen($label, $valor, $negrita = false, $color = null) {
    if ($color) {
      $this->SetFillColor($color[0], $color[1], $color[2]);
    }
    $style = $negrita ? 'B' : '';
    $this->SetFont('Arial', $style, 10);
    $this->Cell(110, 7, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $label), 1, 0, 'L', (bool)$color);
    $this->Cell(60, 7, $valor, 1, 1, 'R', (bool)$color);
    if ($color) {
      $this->SetFillColor(255, 255, 255);
    }
  }
}

$_logoPath = '';
if (!empty($cfg['logo'])) {
  $l = realpath(__DIR__ . '/../files/empresa/' . $cfg['logo']);
  if ($l && file_exists($l)) $_logoPath = $l;
}
if (!$_logoPath) {
  foreach ([__DIR__.'/logo1.jpeg', __DIR__.'/logo.png'] as $_p) {
    if (file_exists($_p)) { $_logoPath = $_p; break; }
  }
}

$pdf = new PDF_CierreCaja('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->empresa   = $nombreEmpresa;
$pdf->ruc       = $rucEmpresa;
$pdf->direccion = $dirEmpresa;
$pdf->logo      = $_logoPath;
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

// --- Datos de la caja ---
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, 'DATOS DE LA CAJA', 0, 1, 'L');
$pdf->SetFont('Arial', '', 9);

$estadoLabel = $caja['estado'] === 'ABIERTA' ? 'ABIERTA (aun no cerrada)' : 'CERRADA';
$fechaApertura = $caja['fecha_apertura'] ? date('d/m/Y H:i', strtotime($caja['fecha_apertura'])) : '-';
$fechaCierre   = !empty($caja['fecha_cierre']) ? date('d/m/Y H:i', strtotime($caja['fecha_cierre'])) : '-';

$datosInfo = array(
  array('Caja N°:', '#' . $caja['idcaja']),
  array('Cajero:', $caja['cajero']),
  array('Estado:', $estadoLabel),
  array('Apertura:', $fechaApertura),
  array('Cierre:', $fechaCierre),
);
foreach ($datosInfo as $fila) {
  $pdf->SetFont('Arial', 'B', 9);
  $pdf->Cell(40, 6, $fila[0], 0, 0);
  $pdf->SetFont('Arial', '', 9);
  $pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $fila[1]), 0, 1);
}
$pdf->Ln(3);

// --- Resumen financiero ---
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, 'RESUMEN FINANCIERO', 0, 1, 'L');
$pdf->Ln(1);

// Cabecera de tabla
$pdf->SetFillColor(60, 60, 60);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(110, 7, 'Concepto', 1, 0, 'L', true);
$pdf->Cell(60, 7, 'Monto', 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);

$sym = $monedaSimbolo;
$pdf->filaResumen('Monto de apertura', $pdf->fmtNum($apertura, $sym));
$pdf->filaResumen('Ventas cobradas en efectivo', $pdf->fmtNum($ventasEfectivo, $sym), false, array(220, 255, 220));
$pdf->filaResumen('Ingresos manuales', $pdf->fmtNum($ingresosManuales, $sym));
$pdf->filaResumen('Egresos', $pdf->fmtNum($egresos, $sym), false, array(255, 220, 220));

// Total sistema
$pdf->SetFillColor(200, 230, 200);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(110, 8, 'TOTAL ESPERADO EN CAJA', 1, 0, 'L', true);
$pdf->Cell(60, 8, $pdf->fmtNum($totalSistema, $sym), 1, 1, 'R', true);

if ($caja['estado'] === 'CERRADA') {
  $pdf->SetFillColor(255, 255, 255);
  $pdf->filaResumen('Total real contado', $pdf->fmtNum($totalReal, $sym), true);

  // Diferencia con color según sobrante/faltante
  $colorDif = $diferencia >= 0 ? array(220, 255, 220) : array(255, 220, 220);
  $label = 'Diferencia (' . ($diferencia >= 0 ? 'sobrante' : 'faltante') . ')';
  $pdf->filaResumen($label, $pdf->fmtNum($diferencia, $sym), true, $colorDif);
}
$pdf->Ln(5);

// --- Movimientos ---
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, 'DETALLE DE MOVIMIENTOS', 0, 1, 'L');
$pdf->Ln(1);

$pdf->SetFillColor(60, 60, 60);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(32, 6, 'Fecha/Hora', 1, 0, 'C', true);
$pdf->Cell(20, 6, 'Tipo', 1, 0, 'C', true);
$pdf->Cell(88, 6, 'Concepto', 1, 0, 'C', true);
$pdf->Cell(30, 6, 'Monto', 1, 1, 'C', true);
$pdf->SetTextColor(0, 0, 0);

$totalIngM = 0;
$totalEgM  = 0;
$fila = 0;
while ($mov = $movimientos->fetch_object()) {
  $fila++;
  $fill = ($fila % 2 === 0);
  $pdf->SetFillColor(245, 245, 245);
  $pdf->SetFont('Arial', '', 8);
  $tipoLabel = $mov->tipo === 'INGRESO' ? 'INGRESO' : 'EGRESO';
  $fechaMov  = date('d/m/Y H:i', strtotime($mov->fecha_hora));
  $pdf->Cell(32, 6, $fechaMov, 1, 0, 'C', $fill);
  $pdf->Cell(20, 6, $tipoLabel, 1, 0, 'C', $fill);
  $pdf->Cell(88, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', substr($mov->concepto, 0, 55)), 1, 0, 'L', $fill);
  $pdf->Cell(30, 6, $pdf->fmtNum($mov->monto, $sym), 1, 1, 'R', $fill);
  if ($mov->tipo === 'INGRESO') $totalIngM += (float)$mov->monto;
  else                          $totalEgM  += (float)$mov->monto;
}

if ($fila === 0) {
  $pdf->SetFont('Arial', 'I', 9);
  $pdf->Cell(0, 7, 'Sin movimientos registrados.', 1, 1, 'C');
}
$pdf->Ln(5);

// --- Firma ---
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'Observaciones: ' . iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$caja['observacion']), 0, 1);
$pdf->Ln(10);

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(90, 6, '', 'B', 0, 'C');
$pdf->Cell(10, 6, '', 0, 0);
$pdf->Cell(90, 6, '', 'B', 1, 'C');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(90, 5, 'Firma del cajero', 0, 0, 'C');
$pdf->Cell(10, 5, '', 0, 0);
$pdf->Cell(90, 5, 'V°B° Supervisor', 0, 1, 'C');
$pdf->Ln(3);
$pdf->SetFont('Arial', 'I', 7);
$pdf->Cell(0, 5, 'Generado el: ' . date('d/m/Y H:i:s'), 0, 1, 'R');

ob_end_clean();
$pdf->Output('I', 'CierreCaja_' . $idcaja . '.pdf');
?>
