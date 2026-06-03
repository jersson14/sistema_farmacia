<?php
ob_start();
if (strlen(session_id()) < 1) session_start();

if (!isset($_SESSION['nombre'])) {
    echo "Debe ingresar al sistema correctamente para visualizar el reporte";
    ob_end_flush();
    exit;
}

if ($_SESSION['ventas'] != 1) {
    echo "No tiene permiso para visualizar el reporte";
    ob_end_flush();
    exit;
}

require_once "../modelos/Venta.php";
require_once "../modelos/Empresa.php";

$idventa = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idventa <= 0) {
    echo "ID de venta no válido.";
    ob_end_flush();
    exit;
}

$ventaMdl = new Venta();
$rspta    = $ventaMdl->ventacabecera($idventa);
$reg      = $rspta->fetch_object();

if (!$reg) {
    echo "No se encontró la venta solicitada.";
    ob_end_flush();
    exit;
}

$empMdl       = new Empresa();
$cfgEmpresa   = $empMdl->datosReporte();
$codigoMoneda = !empty($cfgEmpresa['moneda']) ? strtoupper($cfgEmpresa['moneda']) : 'PEN';
$simbolo      = obtenerSimboloMoneda($codigoMoneda);

$empresa  = !empty($cfgEmpresa['nombre'])          ? $cfgEmpresa['nombre']          : 'FARMACIA';
$tagline  = !empty($cfgEmpresa['nombre_comercial']) ? $cfgEmpresa['nombre_comercial'] : 'Al cuidado de tu salud';
// Si nombre comercial == razón social, mostrar tagline genérico
if ($tagline === $empresa) $tagline = 'Al cuidado de tu salud';
$ruc      = !empty($cfgEmpresa['ruc'])             ? $cfgEmpresa['ruc']             : '';
$dir1     = !empty($cfgEmpresa['direccion_linea1']) ? $cfgEmpresa['direccion_linea1']: '';
$dir2     = !empty($cfgEmpresa['direccion_linea2']) ? $cfgEmpresa['direccion_linea2']: '';
$telefono = !empty($cfgEmpresa['telefono'])         ? $cfgEmpresa['telefono']        : '';
$email    = !empty($cfgEmpresa['email'])            ? $cfgEmpresa['email']           : '';
$web      = !empty($cfgEmpresa['web'])              ? $cfgEmpresa['web']             : '';

// Logo: buscar en archivos de empresa
$logo = '';
if (!empty($cfgEmpresa['logo'])) {
    $logoFS = realpath(__DIR__ . '/../files/empresa/' . $cfgEmpresa['logo']);
    if ($logoFS && file_exists($logoFS)) {
        $logo = '../files/empresa/' . $cfgEmpresa['logo'];
    }
}
// Fallback: buscar logo genérico de la farmacia
if (!$logo) {
    $candidatos = [
        __DIR__ . '/../files/famacia.png'  => '../files/famacia.png',
        __DIR__ . '/logo.png'              => 'logo.png',
        __DIR__ . '/logo1.jpeg'            => 'logo1.jpeg',
    ];
    foreach ($candidatos as $path => $url) {
        if (file_exists($path)) { $logo = $url; break; }
    }
}

$total    = (float)$reg->total_venta;
$impuesto = (float)$reg->impuesto;
$base     = $impuesto > 0 ? round($total / (1 + ($impuesto / 100)), 2) : $total;
$igv      = round($total - $base, 2);

$metodoPago    = property_exists($reg, 'metodo_pago')    ? (string)$reg->metodo_pago    : '';
$montoEfectivo = property_exists($reg, 'monto_efectivo') ? (float)$reg->monto_efectivo  : 0;
$montoTarjeta  = property_exists($reg, 'monto_tarjeta')  ? (float)$reg->monto_tarjeta   : 0;
$montoDigital  = property_exists($reg, 'monto_digital')  ? (float)$reg->monto_digital   : 0;
$vuelto        = ($metodoPago === 'EFECTIVO' && $montoEfectivo > $total) ? round($montoEfectivo - $total, 2) : 0;

$seguroNombre = property_exists($reg,'seguro_nombre')           ? trim((string)$reg->seguro_nombre)           : '';
$seguroCopago = property_exists($reg,'seguro_copago')           ? (float)$reg->seguro_copago                  : 0;
$seguroAut    = property_exists($reg,'seguro_nro_autorizacion') ? trim((string)$reg->seguro_nro_autorizacion) : '';

// Fecha y hora
$fechaRaw  = (string)$reg->fecha;
$fechaFmt  = '';
$horaFmt   = '';
if (strlen($fechaRaw) >= 10) {
    $ts       = strtotime($fechaRaw);
    $fechaFmt = $ts ? date('d/m/Y', $ts) : substr($fechaRaw, 0, 10);
    $horaFmt  = ($ts && strlen($fechaRaw) > 10) ? date('H:i', $ts) : (strlen($fechaRaw) > 10 ? substr($fechaRaw, 11, 5) : '');
}

function esc($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function mon($v, $sym){ return $sym . ' ' . number_format((float)$v, 2); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo esc($reg->tipo_comprobante . ' ' . $reg->serie_comprobante . '-' . $reg->num_comprobante); ?></title>
  <link rel="stylesheet" href="../public/css/ticket.css?v=20260603a">
</head>
<body>

<div class="ticket-shell">

  <!-- ════════ CABECERA ════════ -->
  <div class="ticket-head">

    <?php if ($logo): ?>
    <div class="brand-logo-wrap">
      <img class="brand-logo" src="<?php echo esc($logo); ?>" alt="<?php echo esc($empresa); ?>">
    </div>
    <?php else: ?>
    <div class="brand-icon-fallback">+</div>
    <?php endif; ?>

    <?php if ($ruc): ?>
    <div class="brand-ruc">RUC <?php echo esc($ruc); ?></div>
    <?php endif; ?>
    <?php if ($dir1): ?><div class="brand-addr"><?php echo esc($dir1); ?></div><?php endif; ?>
    <?php if ($dir2): ?><div class="brand-addr"><?php echo esc($dir2); ?></div><?php endif; ?>
    <?php if ($telefono || $email): ?>
    <div class="brand-addr">
      <?php if ($telefono): ?>&#128222; <?php echo esc($telefono); ?><?php endif; ?>
      <?php if ($telefono && $email): ?> &nbsp;|&nbsp; <?php endif; ?>
      <?php if ($email): ?>&#9993; <?php echo esc($email); ?><?php endif; ?>
    </div>
    <?php endif; ?>

  </div><!-- /ticket-head -->

  <!-- ════════ NÚMERO DE COMPROBANTE ════════ -->
  <div class="doc-badge">
    <span class="doc-tipo"><?php echo esc($reg->tipo_comprobante); ?></span>
    <span class="doc-numero"><?php echo esc($reg->serie_comprobante . ' - ' . $reg->num_comprobante); ?></span>
  </div>

  <!-- ════════ CUERPO ════════ -->
  <div class="ticket-body">

    <!-- Fecha / Cajero -->
    <div class="info-grid">
      <div class="info-col">
        <span class="info-label">Fecha</span>
        <span class="info-value"><?php echo esc($fechaFmt ?: $fechaRaw); ?></span>
      </div>
      <?php if ($horaFmt): ?>
      <div class="info-col" style="text-align:center">
        <span class="info-label">Hora</span>
        <span class="info-value"><?php echo esc($horaFmt); ?></span>
      </div>
      <?php endif; ?>
      <div class="info-col" style="text-align:right">
        <span class="info-label">Atendido por</span>
        <span class="info-value"><?php echo esc($reg->usuario); ?></span>
      </div>
    </div>

    <hr class="sep-dash">

    <!-- Cliente -->
    <div class="client-block">
      <div class="client-header">
        <span class="client-icon">&#128100;</span>
        <span class="client-title">Cliente</span>
      </div>
      <div class="client-name"><?php echo esc($reg->cliente); ?></div>
      <?php if (!empty($reg->tipo_documento) && !empty($reg->num_documento) && $reg->num_documento !== '00000000'): ?>
      <div class="client-doc"><?php echo esc($reg->tipo_documento . ': ' . $reg->num_documento); ?></div>
      <?php endif; ?>
    </div>

    <!-- Detalle de ítems -->
    <div class="items-section-title">&#9679; Detalle de productos</div>
    <table class="items-table">
      <thead>
        <tr>
          <th class="qty">Cant</th>
          <th class="desc">Descripción</th>
          <th class="price">P/U</th>
          <th class="amount">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $detalle   = $ventaMdl->ventadetalles($idventa);
        $cantTotal = 0;
        $itemCount = 0;
        while ($d = $detalle->fetch_object()):
            $cantTotal += (float)$d->cantidad;
            $itemCount++;
            $precio   = (float)$d->precio_venta;
            $descuento = isset($d->descuento) ? (float)$d->descuento : 0;
            $subtotal = (float)$d->subtotal;
            $cant     = (float)$d->cantidad;
            $cantFmt  = ($cant == floor($cant)) ? number_format($cant, 0) : number_format($cant, 2);
        ?>
        <tr>
          <td class="qty"><?php echo $cantFmt; ?></td>
          <td class="desc">
            <?php echo esc($d->articulo); ?>
            <?php if (!empty($d->unidad) && $d->unidad !== 'und' && $d->unidad !== 'UND'): ?>
            <span style="font-size:12px;color:#777"> (<?php echo esc($d->unidad); ?>)</span>
            <?php endif; ?>
            <?php if (!empty($d->principio_activo)): ?>
            <span class="item-dci"><?php echo esc($d->principio_activo); ?></span>
            <?php endif; ?>
            <?php
            $lotePartes = [];
            if (!empty($d->numero_lote))       $lotePartes[] = 'Lote: ' . $d->numero_lote;
            if (!empty($d->fecha_vencimiento)) $lotePartes[] = 'Vto: ' . date('m/Y', strtotime($d->fecha_vencimiento));
            if ($lotePartes):
            ?>
            <span class="item-lote"><?php echo esc(implode(' | ', $lotePartes)); ?></span>
            <?php endif; ?>
          </td>
          <td class="price"><?php echo number_format($precio, 2); ?></td>
          <td class="amount"><?php echo number_format($subtotal, 2); ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

    <!-- Totales secundarios -->
    <div class="totals-block">
      <div class="totals-row">
        <span><?php echo $itemCount; ?> art&iacute;culo<?php echo $itemCount !== 1 ? 's' : ''; ?></span>
        <span style="color:#888"><?php echo number_format($cantTotal, 0); ?> unidades</span>
      </div>
      <hr class="sep-dash" style="margin:4px 0">
      <?php if ($impuesto > 0): ?>
      <div class="totals-row">
        <span>Op. Gravada</span>
        <span><?php echo mon($base, $simbolo); ?></span>
      </div>
      <div class="totals-row">
        <span>IGV (<?php echo number_format($impuesto, 0); ?>%)</span>
        <span><?php echo mon($igv, $simbolo); ?></span>
      </div>
      <?php else: ?>
      <div class="totals-row">
        <span>Op. Inafecta</span>
        <span><?php echo mon($total, $simbolo); ?></span>
      </div>
      <?php endif; ?>
    </div>

    <!-- TOTAL PRINCIPAL -->
    <div class="totals-main-wrap">
      <span class="totals-main-label">&#10003; Total a pagar</span>
      <span class="totals-main-amount"><?php echo mon($total, $simbolo); ?></span>
    </div>

    <!-- Método de pago / vuelto -->
    <?php if ($metodoPago || $montoEfectivo > 0 || $montoTarjeta > 0 || $montoDigital > 0): ?>
    <div class="pago-block">
      <?php if ($metodoPago): ?>
      <div class="pago-row">
        <span>Forma de pago</span>
        <strong><?php echo esc($metodoPago); ?></strong>
      </div>
      <?php endif; ?>
      <?php if ($montoEfectivo > 0): ?>
      <div class="pago-row">
        <span>Efectivo</span>
        <span><?php echo mon($montoEfectivo, $simbolo); ?></span>
      </div>
      <?php endif; ?>
      <?php if ($montoTarjeta > 0): ?>
      <div class="pago-row">
        <span>Tarjeta</span>
        <span><?php echo mon($montoTarjeta, $simbolo); ?></span>
      </div>
      <?php endif; ?>
      <?php if ($montoDigital > 0): ?>
      <div class="pago-row">
        <span>Yape / Plin</span>
        <span><?php echo mon($montoDigital, $simbolo); ?></span>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Vuelto / cambio -->
    <?php if ($vuelto > 0): ?>
    <div class="cambio-block">
      <span class="cambio-label">&#8594; Vuelto / cambio</span>
      <span class="cambio-amount"><?php echo mon($vuelto, $simbolo); ?></span>
    </div>
    <?php endif; ?>

    <!-- Seguro / EPS -->
    <?php if ($seguroNombre): ?>
    <div class="seguro-block">
      <span class="seguro-title">&#9888; Seguro / EPS</span>
      <div><?php echo esc($seguroNombre); ?></div>
      <?php if ($seguroAut): ?>
      <div style="color:#666">Aut: <?php echo esc($seguroAut); ?></div>
      <?php endif; ?>
      <?php if ($seguroCopago > 0): ?>
      <div>Copago: <?php echo mon($seguroCopago, $simbolo); ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Mensaje legal -->
    <hr class="sep-dash" style="margin-top:8px">
    <div style="font-size:12px;color:#888;line-height:1.5;text-align:center;margin-top:3px">
      <?php if ($reg->tipo_comprobante === 'Boleta'): ?>
      Este documento no acredita cr&eacute;dito fiscal.
      <?php elseif ($reg->tipo_comprobante === 'Factura'): ?>
      Representaci&oacute;n impresa de Comprobante Electr&oacute;nico.
      <?php endif; ?>
    </div>

    <!-- PIE -->
    <div class="ticket-foot">
      <span class="gracias-icon">&#128151;</span>
      <div class="gracias">¡Gracias por su preferencia!</div>
      <div class="gracias-sub">Al cuidado de su salud</div>
      <?php if ($web): ?>
      <div class="foot-contact">&#127760; <?php echo esc($web); ?></div>
      <?php elseif ($email): ?>
      <div class="foot-contact">&#9993; <?php echo esc($email); ?></div>
      <?php endif; ?>
      <div class="foot-legal">
        Conserve su comprobante de pago.<br>
        <?php echo esc($empresa); ?> &mdash; <?php echo date('Y'); ?>
      </div>
    </div>

  </div><!-- /ticket-body -->

  <!-- Barra decorativa tricolor al pie -->
  <div class="foot-bar"></div>

</div><!-- /ticket-shell -->

<!-- Botón reimprimir — visible en pantalla, oculto al imprimir -->
<div style="margin-top:20px;text-align:center;" id="print-tip">
  <button onclick="window.print()" class="btn-print">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
    Reimprimir
  </button>
</div>

<script>
window.addEventListener('load', function() {
  setTimeout(function() { window.print(); }, 400);
});
</script>
</body>
</html>
<?php
ob_end_flush();
?>
