<?php
ob_start();
if (strlen(session_id()) < 1) session_start();

if (!isset($_SESSION['nombre'])) {
  echo "Debe iniciar sesión para ver este reporte.";
  ob_end_flush();
  exit;
}

$puedeVer = (!empty($_SESSION['consultav']) && $_SESSION['consultav']==1)
         || (!empty($_SESSION['consultac']) && $_SESSION['consultac']==1)
         || (!empty($_SESSION['acceso'])    && $_SESSION['acceso']==1);

if (!$puedeVer) {
  echo "No tiene permiso para ver este reporte.";
  ob_end_flush();
  exit;
}

$fi = isset($_GET['fi']) ? trim($_GET['fi']) : date('Y-m-01');
$ff = isset($_GET['ff']) ? trim($_GET['ff']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fi)) $fi = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ff)) $ff = date('Y-m-d');

require_once "../config/Conexion.php";
require_once "../fpdf181/fpdf.php";
require_once "../modelos/Empresa.php";
require_once "../modelos/Consultas.php";

$empresaModel = new Empresa();
$cfg          = $empresaModel->datosReporte();
$nombreEmpresa = !empty($cfg['nombre'])   ? $cfg['nombre']   : 'FARMACIA';
$rucEmpresa    = !empty($cfg['ruc'])      ? $cfg['ruc']      : '';
$dirEmpresa    = trim((string)$cfg['direccion_linea1'] . ' ' . (string)$cfg['direccion_linea2']);
$monedaSimbolo = !empty($cfg['moneda'])   ? obtenerSimboloMoneda(strtoupper($cfg['moneda'])) : 'S/';

$consultas     = new Consultas();
$resumen       = $consultas->resumenResultados($fi, $ff);

$totalVentas   = $resumen['total_ventas'];
$totalCompras  = $resumen['total_compras'];
$utilidadBruta = $resumen['utilidad_bruta'];
$ingresosCaja  = $resumen['ingresos_caja'];
$egresosCaja   = $resumen['egresos_caja'];
$resultado     = $resumen['resultado'];
$margenBruto   = $totalVentas > 0 ? round(($utilidadBruta / $totalVentas) * 100, 1) : 0;

// =====================================================================
class PDF_Resultados extends FPDF
{
  var $empresa   = '';
  var $ruc       = '';
  var $direccion = '';
  var $logo      = '';

  function Header() {
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
    $this->Cell(0, 8, 'ESTADO DE RESULTADOS', 0, 1, 'C', true);
    $this->SetTextColor(0, 0, 0);
    $this->Ln(3);
  }

  function Footer() {
    $this->SetY(-12);
    $this->SetFont('Arial', 'I', 7);
    $this->Cell(0, 5, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
  }

  function fila($label, $valor, $negrita = false, $bgRgb = null, $txtColor = null) {
    if ($bgRgb) $this->SetFillColor($bgRgb[0], $bgRgb[1], $bgRgb[2]);
    if ($txtColor) $this->SetTextColor($txtColor[0], $txtColor[1], $txtColor[2]);
    $this->SetFont('Arial', $negrita ? 'B' : '', 10);
    $this->Cell(110, 7, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $label), 1, 0, 'L', (bool)$bgRgb);
    $this->Cell(60, 7, $valor, 1, 1, 'R', (bool)$bgRgb);
    $this->SetFillColor(255, 255, 255);
    $this->SetTextColor(0, 0, 0);
  }

  function separador($texto) {
    $this->SetFillColor(220, 220, 220);
    $this->SetFont('Arial', 'B', 9);
    $this->Cell(170, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto), 1, 1, 'L', true);
    $this->SetFillColor(255, 255, 255);
  }

  function fmt($val, $sym) {
    return $sym . ' ' . number_format((float)$val, 2);
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

$pdf = new PDF_Resultados('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->empresa   = $nombreEmpresa;
$pdf->ruc       = $rucEmpresa;
$pdf->direccion = $dirEmpresa;
$pdf->logo      = $_logoPath;
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

$sym = $monedaSimbolo;

// Período
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'Periodo: ' . date('d/m/Y', strtotime($fi)) . ' al ' . date('d/m/Y', strtotime($ff)), 0, 1, 'C');
$pdf->Cell(0, 5, 'Generado el: ' . date('d/m/Y H:i'), 0, 1, 'C');
$pdf->Ln(4);

// Encabezado de tabla
$pdf->SetFillColor(60, 60, 60);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(110, 7, 'Concepto', 1, 0, 'L', true);
$pdf->Cell(60, 7, 'Monto', 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);

// Ingresos
$pdf->separador('INGRESOS');
$pdf->fila('Ventas del periodo', $pdf->fmt($totalVentas, $sym), false, [240, 255, 240], [0, 120, 0]);

// Costo de ventas
$pdf->separador('COSTO DE VENTAS');
$pdf->fila('Compras / mercaderia', $pdf->fmt($totalCompras, $sym), false, [255, 240, 240], [180, 0, 0]);

// Utilidad bruta
$colorUB = $utilidadBruta >= 0 ? [210, 240, 255] : [255, 220, 220];
$txtUB   = $utilidadBruta >= 0 ? [0, 60, 120]    : [150, 0, 0];
$pdf->SetFont('Arial', 'B', 11);
$pdf->fila('UTILIDAD BRUTA  (Margen: ' . $margenBruto . '%)', $pdf->fmt($utilidadBruta, $sym), true, $colorUB, $txtUB);
$pdf->Ln(2);

// Movimientos de caja
if ($ingresosCaja > 0 || $egresosCaja > 0) {
  $pdf->separador('MOVIMIENTOS DE CAJA');
  if ($ingresosCaja > 0) $pdf->fila('Ingresos adicionales de caja', '+ ' . $pdf->fmt($ingresosCaja, $sym), false, [240, 255, 240], [0, 120, 0]);
  if ($egresosCaja  > 0) $pdf->fila('Egresos / Gastos de caja',      '- ' . $pdf->fmt($egresosCaja,  $sym), false, [255, 240, 240], [180, 0, 0]);
  $pdf->Ln(2);
}

// Resultado final
$colorRes = $resultado >= 0 ? [200, 240, 200] : [255, 200, 200];
$txtRes   = $resultado >= 0 ? [0, 100, 0]     : [150, 0, 0];
$pdf->SetFont('Arial', 'B', 12);
$pdf->fila('RESULTADO DEL PERIODO (' . ($resultado >= 0 ? 'GANANCIA' : 'PERDIDA') . ')', $pdf->fmt($resultado, $sym), true, $colorRes, $txtRes);

$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 5, 'Documento generado por el sistema. No requiere firma.', 0, 1, 'C');

ob_end_clean();
$pdf->Output('I', 'EstadoResultados_' . $fi . '_' . $ff . '.pdf');
?>
