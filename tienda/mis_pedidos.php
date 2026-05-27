<?php
$GLOBALS['pageTitulo'] = 'Mis pedidos';
require 'layout.php';
if (empty($_SESSION['tienda_cliente'])) { header('Location: login.php'); exit; }
?>

<div style="max-width:760px;margin:0 auto;padding:32px 20px 60px;">
  <h2 class="page-title">📋 Mis pedidos</h2>
  <div id="listaPedidos">
    <!-- skeleton -->
    <div style="background:#fff;border-radius:16px;border:1px solid #e5e7eb;padding:20px;margin-bottom:14px">
      <div class="skeleton skeleton-line" style="width:40%;margin-bottom:10px"></div>
      <div class="skeleton skeleton-line" style="width:70%"></div>
    </div>
    <div style="background:#fff;border-radius:16px;border:1px solid #e5e7eb;padding:20px;margin-bottom:14px">
      <div class="skeleton skeleton-line" style="width:40%;margin-bottom:10px"></div>
      <div class="skeleton skeleton-line" style="width:70%"></div>
    </div>
  </div>
</div>

<div class="tienda-footer">
  <div class="f-logo">🏪 <?php echo htmlspecialchars($nombreEmp); ?></div>
  <p><a href="index.php">← Seguir comprando</a></p>
</div>

<script>
fetch('ajax/pedido.php?op=mis_pedidos')
  .then(function(r){ return r.json(); })
  .then(function(d){
    var el = document.getElementById('listaPedidos');
    if (!d.ok || d.pedidos.length === 0) {
      el.innerHTML = '<div class="empty-state"><div class="empty-icon">📦</div><h3>Sin pedidos aún</h3><p>Cuando realices tu primera compra aparecerá aquí.</p><br><a href="index.php" class="btn-primary-lg" style="max-width:260px;margin:0 auto;display:block;text-decoration:none">Ver productos →</a></div>';
      return;
    }
    var html = '';
    d.pedidos.forEach(function(p){
      var cls = 'estado-' + p.estado.replace(' ','_');
      html += '<div class="pedido-card">'
        + '<div>'
        + '<div class="pd-id">Pedido #' + p.idpedido + '</div>'
        + '<div class="pd-date">' + esc(p.fecha) + ' &nbsp;·&nbsp; ' + esc(p.metodo_pago) + ' &nbsp;·&nbsp; ' + esc(p.tipo_comprobante) + '</div>'
        + '</div>'
        + '<div style="display:flex;align-items:center;gap:14px">'
        + '<div class="pd-total">S/ ' + parseFloat(p.total).toFixed(2) + '</div>'
        + '<span class="estado-badge ' + cls + '">' + esc(p.estado) + '</span>'
        + '</div>'
        + '</div>';
    });
    el.innerHTML = html;
  });

function esc(s){ var d=document.createElement('span'); d.textContent=String(s||''); return d.innerHTML; }
</script>
</body>
</html>
