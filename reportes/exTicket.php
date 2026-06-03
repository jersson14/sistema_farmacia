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
  <link rel="stylesheet" href="../public/css/ticket.css?v=20260529e">
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

<!-- ── Panel de impresión QZ Tray ─────────────────────────── -->
<div id="qz-panel" style="margin-top:16px;font-family:Arial,sans-serif;max-width:320px;">

  <!-- Estado de conexión -->
  <div id="qz-status" style="font-size:12px;padding:6px 10px;border-radius:5px;margin-bottom:8px;background:#e8f4fd;color:#1a6fa0;border:1px solid #b8d9f0;">
    &#9679; Conectando con QZ Tray...
  </div>

  <!-- Selector de impresora (visible al conectar) -->
  <div id="qz-printer-wrap" style="display:none;margin-bottom:8px;">
    <label style="font-size:12px;font-weight:700;color:#333;display:block;margin-bottom:3px;">Impresora:</label>
    <select id="qz-printer-select" style="width:100%;padding:5px 8px;font-size:12px;border:1px solid #ccc;border-radius:4px;"></select>
    <label style="font-size:12px;color:#555;margin-top:6px;display:flex;align-items:center;gap:5px;cursor:pointer;">
      <input type="checkbox" id="chk-cajon" checked style="width:14px;height:14px;cursor:pointer;">
      Abrir cajón de dinero al imprimir
    </label>
  </div>

  <!-- Botón imprimir con QZ -->
  <button id="btn-qz-print" style="display:none;width:100%;padding:10px;background:#1a6fa0;color:#fff;border:0;border-radius:6px;font-size:13px;font-weight:700;cursor:pointer;">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:5px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
    Imprimir en ticketera
  </button>

  <!-- Fallback navegador -->
  <button id="btn-browser-print" class="btn-print" onclick="window.print()" style="display:none;width:100%;margin-top:6px;">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
    Imprimir desde navegador
  </button>

  <div id="qz-msg" style="font-size:11px;color:#888;margin-top:5px;min-height:16px;"></div>
</div>

<script src="../public/js/qz-tray.js"></script>
<script>
(function(){
  var QZ_PRINTER_KEY  = 'qz_preferred_printer_farmacia';
  var QZ_CAJON_KEY    = 'qz_abrir_cajon_farmacia';
  var connected = false;

  function setStatus(msg, tipo) {
    var el = document.getElementById('qz-status');
    if (!el) return;
    var colores = {
      ok:      { bg:'#e6f4ea', color:'#1e7e34', border:'#b2dfbb' },
      error:   { bg:'#fdecea', color:'#b71c1c', border:'#f5c6c6' },
      warning: { bg:'#fff8e1', color:'#7a5c00', border:'#ffe082' },
      info:    { bg:'#e8f4fd', color:'#1a6fa0', border:'#b8d9f0' }
    };
    var c = colores[tipo] || colores.info;
    el.style.background = c.bg;
    el.style.color = c.color;
    el.style.border = '1px solid ' + c.border;
    el.textContent = msg;
  }

  function setMsg(msg) {
    var el = document.getElementById('qz-msg');
    if (el) el.textContent = msg;
  }

  function buildTicketHtml() {
    var shell = document.querySelector('.ticket-shell');
    if (!shell) return null;
    var css = <?php
      $cssPath = __DIR__ . '/../public/css/ticket.css';
      echo json_encode(file_exists($cssPath) ? file_get_contents($cssPath) : '');
    ?>;
    return '<!DOCTYPE html><html><head><meta charset="utf-8">' +
           '<style>' + css + '</style>' +
           '</head><body>' + shell.outerHTML + '</body></html>';
  }

  function cargarImpresoras() {
    var virtuales = ['fax','pdf','xps','onenote','microsoft','send to','imagen','image writer','novapdf','cutepdf','deskpdf','primopdf','snagit','papercut'];

    function esVirtual(nombre) {
      var n = nombre.toLowerCase();
      return virtuales.some(function(v){ return n.indexOf(v) !== -1; });
    }

    function seleccionarEn(sel, nombre) {
      for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === nombre) { sel.selectedIndex = i; return true; }
      }
      return false;
    }

    var listaPromise   = qz.printers.find('');
    var defaultPromise = qz.printers.getDefault().catch(function(){ return ''; });

    return Promise.all([listaPromise, defaultPromise]).then(function(res) {
      var lista     = Array.isArray(res[0]) ? res[0] : (res[0] ? [res[0]] : []);
      var winDefault = (typeof res[1] === 'string') ? res[1] : '';

      // Excluir impresoras virtuales del listado; si el resultado queda vacío, mostrar todas
      var listaFiltrada = lista.filter(function(p){ return !esVirtual(p); });
      if (listaFiltrada.length > 0) lista = listaFiltrada;

      var sel = document.getElementById('qz-printer-select');
      sel.innerHTML = '';
      lista.forEach(function(p) {
        var op = document.createElement('option');
        op.value = p; op.textContent = p;
        sel.appendChild(op);
      });

      // Prioridad 1: preferencia guardada por el usuario en este navegador
      var preferida = '';
      try { preferida = localStorage.getItem(QZ_PRINTER_KEY) || ''; } catch(e) {}
      var encontrada = preferida ? seleccionarEn(sel, preferida) : false;

      // Prioridad 2: impresora predeterminada de Windows
      if (!encontrada && winDefault && !esVirtual(winDefault)) {
        encontrada = seleccionarEn(sel, winDefault);
      }

      // Prioridad 3: primera impresora con nombre de ticketera
      if (!encontrada) {
        var palabrasCaja = ['caja','ticket','thermal','termica','térmica','pos','receipt','80mm','58mm','tmt','epson tm','star','xprinter','bixolon','citizen','sewoo','hoin','rongta','gprinter'];
        outer1: for (var j = 0; j < sel.options.length; j++) {
          var n = sel.options[j].value.toLowerCase();
          for (var k = 0; k < palabrasCaja.length; k++) {
            if (n.indexOf(palabrasCaja[k]) !== -1) { sel.selectedIndex = j; encontrada = true; break outer1; }
          }
        }
      }

      // Prioridad 4: primera impresora disponible (ya filtradas las virtuales)
      if (!encontrada && sel.options.length > 0) { sel.selectedIndex = 0; }

      // Restaurar preferencia de cajón — default: ACTIVADO
      var chk = document.getElementById('chk-cajon');
      if (chk) {
        try {
          var guardado = localStorage.getItem(QZ_CAJON_KEY);
          chk.checked = (guardado !== '0');
        } catch(e) {}
        chk.addEventListener('change', function() {
          try { localStorage.setItem(QZ_CAJON_KEY, this.checked ? '1' : '0'); } catch(e) {}
        });
      }
      document.getElementById('qz-printer-wrap').style.display = 'block';
      document.getElementById('btn-qz-print').style.display = 'block';
      document.getElementById('btn-browser-print').style.display = 'block';
      return lista;
    });
  }

  function abrirCajon(printer) {
    // Comando ESC/POS estándar para abrir cajón (pin 2)
    // ESC p m t1 t2  →  0x1B 0x70 0x00 0x3C 0x78
    var rawConfig = qz.configs.create(printer);
    return qz.print(rawConfig, [{
      type: 'raw', format: 'plain', flavor: 'plain',
      data: '\x1B\x70\x00\x3C\x78'
    }]).catch(function() {
      // Si pin 2 falla, intentar pin 5
      return qz.print(rawConfig, [{
        type: 'raw', format: 'plain', flavor: 'plain',
        data: '\x1B\x70\x01\x3C\x78'
      }]);
    });
  }

  function imprimirQZ() {
    var btn = document.getElementById('btn-qz-print');
    var sel = document.getElementById('qz-printer-select');
    var printer = sel ? sel.value : '';
    if (!printer) { setMsg('Selecciona una impresora.'); return; }

    // Guardar impresora preferida
    try { localStorage.setItem(QZ_PRINTER_KEY, printer); } catch(e) {}

    btn.disabled = true;
    btn.textContent = 'Imprimiendo...';
    setMsg('');

    var html = buildTicketHtml();
    if (!html) { setMsg('Error: no se encontró el contenido del ticket.'); btn.disabled = false; return; }

    var config = qz.configs.create(printer, {
      colorType: 'blackwhite',
      copies: 1,
      size: { width: 80, height: null },
      units: 'mm',
      rasterize: false
    });

    var abrirCajonActivado = false;
    var chk = document.getElementById('chk-cajon');
    if (chk) abrirCajonActivado = chk.checked;

    var iconoSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:5px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>';

    qz.print(config, [{ type: 'pixel', format: 'html', flavor: 'plain', data: html }])
      .then(function() {
        if (abrirCajonActivado) {
          return abrirCajon(printer).catch(function() {});
        }
      })
      .then(function() {
        setMsg(abrirCajonActivado ? '✔ Impreso y cajón abierto.' : '✔ Impreso correctamente.');
        btn.innerHTML = iconoSvg + ' Imprimir en ticketera';
        btn.disabled = false;
      })
      .catch(function(err) {
        setMsg('Error al imprimir: ' + (err.message || err));
        btn.innerHTML = iconoSvg + ' Imprimir en ticketera';
        btn.disabled = false;
      });
  }

  // Modo anónimo — funciona en cualquier equipo con QZ Tray instalado
  // (requiere que "Block anonymous requests" esté desmarcado en QZ Tray)
  function setupSeguridad() {
    qz.security.setCertificatePromise(function(resolve) { resolve(); });
    qz.security.setSignatureAlgorithm('SHA512');
    qz.security.setSignaturePromise(function() {
      return function(resolve) { resolve(); };
    });
  }

  function intentarConexion(segura) {
    return qz.websocket.connect({
      host: 'localhost',
      port: { secure: [8181], insecure: [8182] },
      usingSecure: segura,
      keepAlive: 60
    });
  }

  function alConectar(lista) {
    var sel = document.getElementById('qz-printer-select');
    if (sel && sel.options.length > 0 && sel.value) {
      setTimeout(function() { imprimirQZ(); }, 400);
    } else {
      setMsg('No se encontró ninguna impresora física. Conecta tu ticketera e intenta de nuevo.');
    }
  }

  function conectarQZ() {
    if (!window.qz) { sinQZ(); return; }

    setupSeguridad();

    // 1er intento: WSS:8181 (funciona desde HTTP y HTTPS — localhost es origen seguro)
    intentarConexion(true)
      .then(function() {
        connected = true;
        setStatus('&#9679; QZ Tray conectado', 'ok');
        return cargarImpresoras();
      })
      .then(alConectar)
      .catch(function() {
        // 2do intento: WS:8182
        intentarConexion(false)
          .then(function() {
            connected = true;
            setStatus('&#9679; QZ Tray conectado', 'ok');
            return cargarImpresoras();
          })
          .then(alConectar)
          .catch(function(err) {
            console.warn('QZ Tray no disponible:', err);
            sinQZ();
          });
      });
  }

  function sinQZ() {
    setStatus('&#9679; QZ Tray no disponible — usando navegador', 'warning');
    document.getElementById('btn-browser-print').style.display = 'block';
    setMsg('Asegúrate de que QZ Tray esté ejecutándose en la barra de tareas.');
    setTimeout(function() { window.print(); }, 300);
  }

  document.getElementById('btn-qz-print').addEventListener('click', imprimirQZ);

  window.addEventListener('load', function() {
    setTimeout(conectarQZ, 300);
  });
})();
</script>
</body>
</html>
<?php
ob_end_flush();
?>
