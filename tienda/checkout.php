<?php
$GLOBALS['pageTitulo'] = 'Finalizar pedido';
require 'layout.php';

if (empty($_SESSION['tienda_cliente'])) {
    header('Location: login.php?next=checkout'); exit;
}

$cliente = $_SESSION['tienda_cliente'];

// Refrescar datos de entrega desde BD (por si el perfil fue actualizado después del login)
try {
    require_once __DIR__ . '/../modelos/ClienteTienda.php';
    $mdlCli = new ClienteTienda();
    $dbCli  = $mdlCli->obtener((int)$cliente['idcliente_tienda']);
    if ($dbCli) {
        $cliente['nombre']    = $dbCli['nombre']    ?: $cliente['nombre'];
        $cliente['telefono']  = $dbCli['telefono']  ?: '';
        $cliente['direccion'] = $dbCli['direccion'] ?: '';
        $cliente['distrito']  = $dbCli['distrito']  ?: '';
        // Actualizar sesión con datos frescos
        $_SESSION['tienda_cliente']['telefono']  = $cliente['telefono'];
        $_SESSION['tienda_cliente']['direccion'] = $cliente['direccion'];
        $_SESSION['tienda_cliente']['distrito']  = $cliente['distrito'];
    }
} catch (Throwable $e) { /* si el modelo falla, usamos lo que hay en sesión */ }

$yapeNum     = $cfgTienda->obtener('yape_numero', '');
$yapeNom     = $cfgTienda->obtener('yape_nombre', '');
$yapeQR      = $cfgTienda->obtener('yape_qr_imagen', '');
$envioGratis = (float)$cfgTienda->obtener('envio_gratis_desde', 80);
$costoEnvio  = (float)$cfgTienda->obtener('costo_envio_default', 5);

$yapeQRSrc = '';
if ($yapeQR && file_exists(__DIR__ . '/../files/tienda/' . $yapeQR)) {
    $yapeQRSrc = '../files/tienda/' . $yapeQR;
}
?>

<div class="checkout-page">
  <h2 class="page-title">📦 Finalizar pedido</h2>

  <div id="msgCheckout" style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:10px;font-size:14px;border-left:4px solid #ef4444;background:#fee2e2;color:#7f1d1d"></div>

  <!-- 1 — Resumen del carrito -->
  <div class="checkout-card">
    <div class="cc-title">
      <span class="cc-num">1</span> Resumen de tu pedido
    </div>
    <div id="resumenItems"><div style="color:#6b7280;font-size:14px">Cargando...</div></div>
  </div>

  <!-- 2 — Datos de entrega -->
  <div class="checkout-card">
    <div class="cc-title">
      <span class="cc-num">2</span> Datos de entrega
    </div>
    <div class="fgroup">
      <label>Nombre completo *</label>
      <input type="text" id="nombre_entrega" value="<?php echo htmlspecialchars($cliente['nombre'] ?? ''); ?>">
    </div>
    <div class="fgroup">
      <label>Teléfono de contacto *</label>
      <input type="text" id="telefono_entrega" placeholder="9XXXXXXXX"
             value="<?php echo htmlspecialchars($cliente['telefono'] ?? ''); ?>">
    </div>
    <div id="seccionDireccion">
      <div class="fgroup">
        <label>Dirección completa *</label>
        <input type="text" id="direccion_entrega" placeholder="Av. Principal 123, Ref. ..."
               value="<?php echo htmlspecialchars($cliente['direccion'] ?? ''); ?>">
      </div>
      <div class="fgroup">
        <label>Distrito *</label>
        <input type="text" id="distrito_entrega" placeholder="Tu distrito"
               value="<?php echo htmlspecialchars($cliente['distrito'] ?? ''); ?>">
      </div>
    </div>
    <div class="fgroup">
      <label>Notas para el repartidor <span style="font-weight:400;color:#6b7280">(opcional)</span></label>
      <textarea id="notas_cliente" placeholder="Indicaciones adicionales..."></textarea>
    </div>
  </div>

  <!-- 3 — Tipo de entrega -->
  <div class="checkout-card">
    <div class="cc-title">
      <span class="cc-num">3</span> Tipo de entrega
    </div>
    <div class="metodo-card selected" id="cardEnvio" onclick="selEntrega('ENVIO')">
      <input type="radio" name="tipo_entrega" value="ENVIO" checked>
      <div class="mc-info">
        <div class="mc-title">Envío a domicilio</div>
        <div class="mc-desc">Te lo llevamos — S/ <?php echo number_format($costoEnvio,2); ?> (gratis desde S/ <?php echo number_format($envioGratis,2); ?>)</div>
      </div>
      <span class="mc-icon">🛵</span>
    </div>
    <div class="metodo-card" id="cardRecojo" onclick="selEntrega('RECOJO')">
      <input type="radio" name="tipo_entrega" value="RECOJO">
      <div class="mc-info">
        <div class="mc-title">Recojo en farmacia</div>
        <div class="mc-desc">Sin costo de envío — GRATIS</div>
      </div>
      <span class="mc-icon">🏪</span>
    </div>
  </div>

  <!-- 4 — Comprobante -->
  <div class="checkout-card">
    <div class="cc-title">
      <span class="cc-num">4</span> Tipo de comprobante
    </div>
    <div class="metodo-card selected" id="cardBoleta" onclick="selComp('Boleta')">
      <input type="radio" name="tipo_comp" value="Boleta" checked>
      <div class="mc-info">
        <div class="mc-title">Boleta de venta</div>
        <div class="mc-desc">Para personas naturales</div>
      </div>
      <span class="mc-icon">🧾</span>
    </div>
    <div class="metodo-card" id="cardFactura" onclick="selComp('Factura')">
      <input type="radio" name="tipo_comp" value="Factura">
      <div class="mc-info">
        <div class="mc-title">Factura</div>
        <div class="mc-desc">Para empresas con RUC</div>
      </div>
      <span class="mc-icon">🏢</span>
    </div>
    <div id="datosFactura" style="display:none;margin-top:12px">
      <div class="fgroup">
        <label>RUC de la empresa</label>
        <input type="text" id="ruc_factura" placeholder="20XXXXXXXXX">
      </div>
      <div class="fgroup">
        <label>Razón social</label>
        <input type="text" id="razon_social" placeholder="Empresa S.A.C.">
      </div>
    </div>
  </div>

  <!-- 5 — Método de pago -->
  <div class="checkout-card">
    <div class="cc-title">
      <span class="cc-num">5</span> Método de pago
    </div>

    <div class="metodo-card selected" id="cardContra" onclick="selPago('CONTRAENTREGA')">
      <input type="radio" name="metodo_pago" value="CONTRAENTREGA" checked>
      <div class="mc-info">
        <div class="mc-title">Pago contra entrega</div>
        <div class="mc-desc">Paga en efectivo al recibir tu pedido</div>
      </div>
      <span class="mc-icon">💵</span>
    </div>

    <div class="metodo-card" id="cardYape" onclick="selPago('YAPE')">
      <input type="radio" name="metodo_pago" value="YAPE">
      <div class="mc-info">
        <div class="mc-title">Pagar con Yape</div>
        <div class="mc-desc">Escanea el QR y adjunta tu comprobante</div>
      </div>
      <span class="mc-icon">📱</span>
    </div>

    <!-- Datos Yape -->
    <div id="datosYape" style="display:none">
      <?php if ($yapeQRSrc || $yapeNom || $yapeNum): ?>
      <div class="yape-panel">
        <p>Escanea el QR con tu app Yape</p>
        <?php if ($yapeQRSrc): ?>
        <img src="<?php echo htmlspecialchars($yapeQRSrc); ?>" alt="QR Yape">
        <?php endif; ?>
        <?php if ($yapeNom): ?><div class="yape-name"><?php echo htmlspecialchars($yapeNom); ?></div><?php endif; ?>
        <?php if ($yapeNum): ?><div class="yape-num"><?php echo htmlspecialchars($yapeNum); ?></div><?php endif; ?>
      </div>
      <?php else: ?>
      <div class="yape-panel">
        <p>Consulta el número de Yape en la farmacia o escríbenos por WhatsApp.</p>
      </div>
      <?php endif; ?>
      <div class="fgroup" style="margin-top:12px">
        <label>N° de operación Yape *</label>
        <input type="text" id="referencia_yape" placeholder="Ej: 1234567">
      </div>
      <div class="fgroup">
        <label>Captura del comprobante de pago</label>
        <input type="file" id="comprobante_yape" accept="image/*">
      </div>
    </div>
  </div>

  <!-- Total panel -->
  <div class="total-panel">
    <div class="total-row">
      <span>Subtotal productos</span>
      <span id="showSubtotal">S/ 0.00</span>
    </div>
    <div class="total-row">
      <span>Costo de envío</span>
      <span id="showEnvio">S/ <?php echo number_format($costoEnvio,2); ?></span>
    </div>
    <div class="total-row main">
      <span>Total a pagar</span>
      <span id="showTotal">S/ 0.00</span>
    </div>
    <p style="font-size:12px;opacity:.7;margin-top:8px">
      🎉 Envío gratis en pedidos mayores a S/ <?php echo number_format($envioGratis,2); ?>
    </p>
  </div>

  <button class="btn-primary-lg" id="btnConfirmar" onclick="confirmarPedido()">
    ✔ Confirmar pedido
  </button>
  <a href="carrito.php" style="display:block;text-align:center;margin-top:12px;font-size:14px;color:#6b7280">
    ← Editar carrito
  </a>
</div>

<div class="tienda-footer">
  <div class="f-logo">🏪 <?php echo htmlspecialchars($nombreEmp); ?></div>
  <p>&copy; <?php echo date('Y'); ?></p>
</div>

<script>
var ENVIO_GRATIS   = <?php echo $envioGratis; ?>;
var COSTO_ENVIO    = <?php echo $costoEnvio; ?>;
var subtotalCart   = 0;
var metodoPagoSel  = 'CONTRAENTREGA';
var tipoCompSel    = 'Boleta';
var tipoEntregaSel = 'ENVIO';

function selEntrega(t){
  tipoEntregaSel = t;
  document.getElementById('cardEnvio').classList.toggle('selected',  t === 'ENVIO');
  document.getElementById('cardRecojo').classList.toggle('selected', t === 'RECOJO');
  document.getElementById('seccionDireccion').style.display = (t === 'RECOJO') ? 'none' : 'block';
  actualizarTotales();
}

function selPago(m){
  metodoPagoSel = m;
  document.getElementById('cardYape').classList.toggle('selected',   m === 'YAPE');
  document.getElementById('cardContra').classList.toggle('selected', m === 'CONTRAENTREGA');
  document.getElementById('datosYape').style.display = (m === 'YAPE') ? 'block' : 'none';
}

function selComp(t){
  tipoCompSel = t;
  document.getElementById('cardBoleta').classList.toggle('selected',  t === 'Boleta');
  document.getElementById('cardFactura').classList.toggle('selected', t === 'Factura');
  document.getElementById('datosFactura').style.display = (t === 'Factura') ? 'block' : 'none';
}

function cargarResumen(){
  fetch('ajax/carrito.php?op=obtener')
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (!d.ok || d.items.length === 0) {
        document.getElementById('resumenItems').innerHTML =
          '<p style="color:#ef4444;font-size:14px">Tu carrito está vacío. <a href="index.php" style="color:#0f766e">Volver al catálogo →</a></p>';
        document.getElementById('btnConfirmar').disabled = true;
        return;
      }
      subtotalCart = d.total;
      var html = '';
      d.items.forEach(function(i){
        html += '<div class="order-summary-item">'
              + '<span class="osi-name">' + esc(i.nombre) + ' × ' + i.cantidad + '</span>'
              + '<span class="osi-price">S/ ' + i.subtotal.toFixed(2) + '</span></div>';
      });
      document.getElementById('resumenItems').innerHTML = html;
      actualizarTotales();
    });
}

function actualizarTotales(){
  var envio;
  if (tipoEntregaSel === 'RECOJO') {
    envio = 0;
  } else {
    envio = subtotalCart >= ENVIO_GRATIS ? 0 : COSTO_ENVIO;
  }
  document.getElementById('showSubtotal').textContent = 'S/ ' + subtotalCart.toFixed(2);
  document.getElementById('showEnvio').textContent    = envio > 0 ? 'S/ ' + envio.toFixed(2) : 'GRATIS 🎉';
  document.getElementById('showTotal').textContent    = 'S/ ' + (subtotalCart + envio).toFixed(2);
}

function confirmarPedido(){
  var btn = document.getElementById('btnConfirmar');
  btn.disabled = true;
  btn.textContent = 'Procesando...';
  btn.classList.add('loading');

  var fd = new FormData();
  fd.append('nombre_entrega',    document.getElementById('nombre_entrega').value.trim());
  fd.append('telefono_entrega',  document.getElementById('telefono_entrega').value.trim());
  fd.append('direccion_entrega', document.getElementById('direccion_entrega').value.trim());
  fd.append('distrito_entrega',  document.getElementById('distrito_entrega').value.trim());
  fd.append('notas_cliente',     document.getElementById('notas_cliente').value.trim());
  fd.append('tipo_comprobante',  tipoCompSel);
  fd.append('ruc_factura',       (document.getElementById('ruc_factura') || {value:''}).value);
  fd.append('razon_social',      (document.getElementById('razon_social') || {value:''}).value);
  fd.append('tipo_entrega',      tipoEntregaSel);
  fd.append('metodo_pago',       metodoPagoSel);
  fd.append('referencia_yape',   (document.getElementById('referencia_yape') || {value:''}).value);

  var fileInput = document.getElementById('comprobante_yape');
  if (fileInput && fileInput.files[0]) fd.append('comprobante_yape', fileInput.files[0]);

  fetch('ajax/pedido.php?op=crear', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok) {
        window.location.href = 'pedido_ok.php?id=' + d.idpedido;
      } else {
        var msg = document.getElementById('msgCheckout');
        msg.style.display = 'block';
        msg.textContent   = d.message || 'No se pudo procesar el pedido.';
        btn.disabled = false;
        btn.textContent = '✔ Confirmar pedido';
        btn.classList.remove('loading');
        if (d.login_required) window.location.href = 'login.php?next=checkout';
      }
    }).catch(function(){
      btn.disabled = false;
      btn.textContent = '✔ Confirmar pedido';
      btn.classList.remove('loading');
    });
}

function esc(s){ var d=document.createElement('span'); d.textContent=String(s||''); return d.innerHTML; }

cargarResumen();
</script>
</body>
</html>
