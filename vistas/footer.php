<?php
$appCurrencyCode = function_exists('obtenerMonedaEmpresaCodigo') ? obtenerMonedaEmpresaCodigo() : 'PEN';
$appCurrencySymbol = function_exists('obtenerSimboloMoneda') ? obtenerSimboloMoneda($appCurrencyCode) : 'S/';
?>
  <footer class="main-footer">
    <div class="pull-right hidden-xs">
      <b>Version</b> 1.0.0
    </div>
    <strong>Copyright 2026<a target="_blank" href="https://www.facebook.com/jerzhitho.cm/"> Desarrollado por JCM</a>.</strong> Todo los derechos
    reservados.
  </footer>

<!-- jQuery 3 -->

<script src="../public/js/jquery.min.js"></script>
<!-- jQuery UI 1.11.4 -->
<!-- Bootstrap 3.3.7 -->
<script src="../public/js/bootstrap.min.js"></script>
<!-- Morris.js charts -->
<!-- AdminLTE App -->
<script src="../public/js/adminlte.min.js"></script>
<script src="../public/datatables/jquery.dataTables.min.js"></script>
<script src="../public/datatables/datatables.min.js"></script>
<script src="../public/datatables/jszip.min.js"></script>
<script src="../public/datatables/pdfmake.min.js"></script>
<script src="../public/datatables/vfs_fonts.js"></script>
<script src="../public/datatables/dataTables.buttons.min.js"></script>
<script src="../public/datatables/buttons.html5.min.js"></script>
<script src="../public/datatables/buttons.colVis.min.js"></script>
<script src="../public/js/app-datatable.js?v=20260321b"></script>
<script src="../public/js/bootbox.min.js"></script>
<script src="../public/js/bootstrap-select.min.js"></script>
<script src="../public/js/app-notify.js?v=20260321b"></script>
<script>
window.appCurrencyCode = <?php echo json_encode($appCurrencyCode); ?>;
window.appCurrencySymbol = <?php echo json_encode($appCurrencySymbol); ?>;
window.appMoney = function(value, decimals) {
  var num = Number(value);
  if (!isFinite(num)) {
    num = 0;
  }
  var dec = (typeof decimals === "number") ? decimals : 2;
  return window.appCurrencySymbol + " " + num.toLocaleString("es-PE", {
    minimumFractionDigits: dec,
    maximumFractionDigits: dec
  });
};
</script>
<style>
#alertasBarra { position: sticky; top: 0; z-index: 900; }
.alerta-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 700;
  color: #fff;
  letter-spacing: 0.01em;
}
.alerta-banner a { color: #fff; text-decoration: underline; margin-left: 14px; white-space: nowrap; }
.alerta-pedidos { background: #16a34a; }
.alerta-stock   { background: #dc2626; }
</style>
<script>
var _alertaPedidos = 0;
var _alertaStock   = 0;
$(function(){
  $('<div id="alertasBarra"></div>').prependTo('.content-wrapper');
});
function _actualizarAlertasBarra(){
  var $b = $("#alertasBarra");
  if (!$b.length) return;
  var html = "";
  if (_alertaPedidos > 0) {
    html += '<div class="alerta-banner alerta-pedidos">'
          + '<span><i class="bi bi-bag-check-fill"></i> &nbsp;'
          + _alertaPedidos + ' pedido(s) online pendientes de atención</span>'
          + '<a href="pedidos_online.php">Ver pedidos &rarr;</a>'
          + '</div>';
  }
  if (_alertaStock > 0) {
    html += '<div class="alerta-banner alerta-stock">'
          + '<span><i class="bi bi-exclamation-triangle-fill"></i> &nbsp;'
          + _alertaStock + ' producto(s) con stock bajo o agotado</span>'
          + '<a href="articulo.php">Ver inventario &rarr;</a>'
          + '</div>';
  }
  $b.html(html);
}
</script>

<?php if (!empty($_SESSION['ventas']) && $_SESSION['ventas']==1): ?>
<script>
/* ── Polling de pedidos online pendientes ─────────────────── */
var _notifUltimoCount = -1;
var _notifSonidoHabilitado = false;

function _notifPoll(){
  $.getJSON("../ajax/pedidos_online.php?op=notifPedidos", function(r){
    if (!r || !r.ok) return;
    var n = r.pendientes || 0;

    /* Badge */
    var $badge = $("#notifBellBadge");
    if (n > 0) {
      $badge.text(n > 99 ? "99+" : n).show().removeClass("sin-pedidos");
    } else {
      $badge.text(0).show().addClass("sin-pedidos").hide();
    }

    /* Sonido + notificación del browser solo cuando suben */
    if (_notifUltimoCount >= 0 && n > _notifUltimoCount) {
      _notifSonidoHabilitado && _notifSonido();
      if (typeof Notification !== "undefined" && Notification.permission === "granted") {
        new Notification("Nuevo pedido online", {
          body: "Tienes " + n + " pedido(s) pendientes.",
          icon: "../files/famacia.png"
        });
      }
      if (typeof appNotify === "function") {
        appNotify("info", "Nuevo pedido online — " + n + " pendiente(s)");
      }
    }
    _notifUltimoCount = n;
    _alertaPedidos = n;
    _actualizarAlertasBarra();

    /* Dropdown items */
    var $body = $("#notifBody");
    if (!r.pedidos || r.pedidos.length === 0) {
      $body.html('<div class="notif-empty">Sin pedidos nuevos</div>');
      return;
    }
    var html = "";
    r.pedidos.forEach(function(p){
      var fecha = p.fecha ? p.fecha.substring(0, 16).replace("T"," ") : "";
      html += '<a class="notif-item" href="pedidos_online.php">'
            + '<div class="notif-item-icon"><i class="bi bi-bag-check-fill"></i></div>'
            + '<div class="notif-item-body">'
            + '<div class="notif-item-nombre">#' + p.idpedido + ' — ' + _notifEsc(p.nombre_entrega) + '</div>'
            + '<div class="notif-item-meta">' + fecha + '</div>'
            + '</div>'
            + '<div class="notif-item-total">S/ ' + parseFloat(p.total).toFixed(2) + '</div>'
            + '</a>';
    });
    $body.html(html);
  });
}

function _notifEsc(s){
  return $("<span>").text(String(s||"")).html();
}

function _notifSonido(){
  try {
    var ctx = new (window.AudioContext || window.webkitAudioContext)();
    var osc = ctx.createOscillator();
    var gain = ctx.createGain();
    osc.connect(gain); gain.connect(ctx.destination);
    osc.frequency.setValueAtTime(880, ctx.currentTime);
    gain.gain.setValueAtTime(0.3, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
    osc.start(ctx.currentTime);
    osc.stop(ctx.currentTime + 0.4);
  } catch(e){}
}

/* Habilitar sonido al primer clic del usuario */
$(document).one("click", function(){ _notifSonidoHabilitado = true; });

/* Pedir permiso de notificación del browser */
if (typeof Notification !== "undefined" && Notification.permission === "default") {
  Notification.requestPermission();
}

/* Primera llamada y luego cada 30 segundos */
setTimeout(function(){
  _notifPoll();
  setInterval(_notifPoll, 30000);
}, 2000);
</script>
<?php endif; ?>

<?php if (!empty($_SESSION['idusuario'])): ?>
<script>
/* ── Polling de stock bajo ────────────────────────────────── */
var _stockUltimoCount = -1;
var _stockSonidoHabilitado = false;

function _stockPoll(){
  $.getJSON("../ajax/articulo.php?op=notifStock", function(r){
    if (!r || !r.ok) return;
    var n = r.criticos || 0;

    var $li    = $("#stockBellLi");
    var $badge = $("#stockBellBadge");

    if (n > 0) {
      $li.show();
      $badge.text(n > 99 ? "99+" : n);
    } else {
      $li.hide();
    }

    /* Alerta solo cuando aumenta la cantidad de críticos */
    if (_stockUltimoCount >= 0 && n > _stockUltimoCount) {
      if (_stockSonidoHabilitado) _stockSonido();
      if (typeof Notification !== "undefined" && Notification.permission === "granted") {
        new Notification("⚠ Stock bajo", {
          body: n + " producto(s) con stock crítico. Revisa el inventario.",
          icon: "../files/famacia.png"
        });
      }
      if (typeof appNotify === "function") {
        appNotify("warning", "⚠ " + n + " producto(s) con stock bajo — revisa el inventario");
      }
    }
    _stockUltimoCount = n;
    _alertaStock = n;
    _actualizarAlertasBarra();

    /* Actualizar dropdown */
    var $body = $("#stockNotifBody");
    if (!r.productos || r.productos.length === 0) {
      $body.html('<div class="stock-notif-empty">Sin productos críticos</div>');
      return;
    }
    var html = "";
    r.productos.forEach(function(p){
      var qty   = parseFloat(p.stock);
      var cls   = qty <= 0 ? "stock-qty-agotado" : "stock-qty-critico";
      var label = qty <= 0 ? "AGOTADO" : qty + " uds";
      html += '<div class="stock-notif-item">'
            + '<div class="stock-notif-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>'
            + '<div class="stock-notif-nombre">' + _stockEsc(p.nombre) + '</div>'
            + '<span class="stock-notif-qty ' + cls + '">' + label + '</span>'
            + '</div>';
    });
    $body.html(html);
  });
}

function _stockEsc(s){
  return $("<span>").text(String(s||"")).html();
}

function _stockSonido(){
  try {
    var ctx  = new (window.AudioContext || window.webkitAudioContext)();
    var osc  = ctx.createOscillator();
    var gain = ctx.createGain();
    osc.connect(gain); gain.connect(ctx.destination);
    osc.type = "triangle";
    osc.frequency.setValueAtTime(660, ctx.currentTime);
    osc.frequency.setValueAtTime(440, ctx.currentTime + 0.18);
    gain.gain.setValueAtTime(0.3, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
    osc.start(ctx.currentTime);
    osc.stop(ctx.currentTime + 0.5);
  } catch(e){}
}

$(document).one("click", function(){ _stockSonidoHabilitado = true; });

/* Primera llamada a los 5 segundos, luego cada 5 minutos */
setTimeout(function(){
  _stockPoll();
  setInterval(_stockPoll, 300000);
}, 5000);
</script>
<?php endif; ?>

<?php if (!empty($_SESSION['almacen']) && $_SESSION['almacen']==1): ?>
<script>
/* ── Polling de vencimientos ──────────────────────────────── */
var _vencUltimoTotal = -1;
var _vencSonidoHabilitado = false;

function _vencPoll(){
  $.getJSON("../ajax/lote.php?op=notifVencimiento", function(r){
    if (!r || !r.ok) return;
    var vencidos = r.vencidos || 0;
    var proximos = r.proximos || 0;
    var total    = vencidos + proximos;

    var $li    = $("#vencBellLi");
    var $badge = $("#vencBellBadge");

    if (total > 0) {
      $li.show();
      $badge.text(total > 99 ? "99+" : total);
    } else {
      $li.hide();
    }

    /* Alerta solo cuando aumenta el total */
    if (_vencUltimoTotal >= 0 && total > _vencUltimoTotal) {
      if (_vencSonidoHabilitado) _vencSonido();
      var msg = "";
      if (vencidos > 0) msg += vencidos + " lote(s) VENCIDO(S). ";
      if (proximos > 0) msg += proximos + " lote(s) próximo(s) a vencer (≤30 días).";
      if (typeof Notification !== "undefined" && Notification.permission === "granted") {
        new Notification("📅 Alerta de vencimientos", {
          body: msg.trim(),
          icon: "../files/famacia.png"
        });
      }
      if (typeof appNotify === "function") {
        if (vencidos > 0) appNotify("error",   "🗓 " + vencidos + " lote(s) VENCIDO(S) con stock — retira del inventario");
        if (proximos > 0) appNotify("warning", "🗓 " + proximos + " lote(s) vencen en menos de 30 días");
      }
    }
    _vencUltimoTotal = total;

    /* Dropdown */
    var $body = $("#vencNotifBody");
    if (!r.lotes || r.lotes.length === 0) {
      $body.html('<div class="venc-notif-empty">Sin alertas de vencimiento</div>');
      return;
    }
    var html = "";
    r.lotes.forEach(function(l){
      var esVencido = l.tipo === 'VENCIDO';
      var iconCls   = esVencido ? 'venc-icon-vencido' : 'venc-icon-proximo';
      var tagCls    = esVencido ? 'venc-tag-vencido'  : 'venc-tag-proximo';
      var tagTxt    = esVencido
                    ? 'Vencido hace ' + l.dias_vencido + 'd'
                    : 'Vence en ' + l.dias_restantes + 'd';
      var fVenc = l.fecha_vencimiento ? l.fecha_vencimiento.substring(0,10).split('-').reverse().join('/') : '';
      html += '<div class="venc-notif-item">'
            + '<div class="venc-notif-icon ' + iconCls + '"><i class="bi bi-calendar-x-fill"></i></div>'
            + '<div class="venc-notif-body-text">'
            + '<div class="venc-notif-nombre">' + _vencEsc(l.articulo) + '</div>'
            + '<div class="venc-notif-sub">Lote ' + _vencEsc(l.numero_lote) + ' · Venc. ' + fVenc + '</div>'
            + '</div>'
            + '<span class="venc-notif-tag ' + tagCls + '">' + tagTxt + '</span>'
            + '</div>';
    });
    $body.html(html);
  });
}

function _vencEsc(s){
  return $("<span>").text(String(s||"")).html();
}

function _vencSonido(){
  try {
    var ctx  = new (window.AudioContext || window.webkitAudioContext)();
    var osc  = ctx.createOscillator();
    var gain = ctx.createGain();
    osc.connect(gain); gain.connect(ctx.destination);
    osc.type = "sine";
    osc.frequency.setValueAtTime(520, ctx.currentTime);
    osc.frequency.setValueAtTime(380, ctx.currentTime + 0.15);
    osc.frequency.setValueAtTime(520, ctx.currentTime + 0.30);
    gain.gain.setValueAtTime(0.28, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.55);
    osc.start(ctx.currentTime);
    osc.stop(ctx.currentTime + 0.55);
  } catch(e){}
}

$(document).one("click", function(){ _vencSonidoHabilitado = true; });

/* Primera llamada a los 8 segundos, luego cada 10 minutos */
setTimeout(function(){
  _vencPoll();
  setInterval(_vencPoll, 600000);
}, 8000);
</script>
<?php endif; ?>
</body>
</html>
