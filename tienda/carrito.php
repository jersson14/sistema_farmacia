<?php
$GLOBALS['pageTitulo'] = 'Mi carrito';
require 'layout.php';
?>

<div class="carrito-page">
  <h2 class="page-title">🛒 Mi carrito</h2>

  <div id="carritoContenido"></div>

  <div id="carritoFooter" style="display:none">
    <div class="cart-summary">
      <div class="summary-row">
        <span>Subtotal</span><span id="cartTotal">S/ 0.00</span>
      </div>
      <div class="summary-row">
        <span>Envío</span><span style="color:#10b981;font-weight:600">Se calcula en el siguiente paso</span>
      </div>
      <div class="summary-total">
        <span>Total</span><span id="cartTotalBottom">S/ 0.00</span>
      </div>
    </div>

    <div style="margin-top:16px;display:flex;flex-direction:column;gap:10px">
      <a href="checkout.php" class="btn-primary-lg" style="text-decoration:none">
        Continuar al pago →
      </a>
      <button class="btn-outline" onclick="vaciarCarrito()">
        🗑 Vaciar carrito
      </button>
    </div>
  </div>
</div>

<div class="tienda-footer">
  <div class="f-logo">🏪 <?php echo htmlspecialchars($nombreEmp); ?></div>
  <p><a href="index.php">← Seguir comprando</a></p>
</div>

<script>
function cargarCarrito(){
  fetch('ajax/carrito.php?op=obtener')
    .then(function(r){ return r.json(); })
    .then(function(d){
      var cont = document.getElementById('carritoContenido');
      var foot = document.getElementById('carritoFooter');
      document.getElementById('navCartCount').textContent = d.total_items || 0;

      if (!d.ok || d.items.length === 0) {
        foot.style.display = 'none';
        cont.innerHTML = '<div class="empty-state">'
          + '<div class="empty-icon">🛒</div>'
          + '<h3>Tu carrito está vacío</h3>'
          + '<p>Agrega productos desde el catálogo para comenzar.</p><br>'
          + '<a href="index.php" class="btn-primary-lg" style="max-width:240px;margin:0 auto;text-decoration:none">Ver productos →</a>'
          + '</div>';
        return;
      }

      foot.style.display = 'block';
      var total = d.total;
      document.getElementById('cartTotal').textContent        = 'S/ ' + total.toFixed(2);
      document.getElementById('cartTotalBottom').textContent  = 'S/ ' + total.toFixed(2);

      cont.innerHTML = '';
      d.items.forEach(function(item){
        var img = item.imagen ? '../files/articulos/' + item.imagen : '../public/img/default-50x50.gif';
        var row = document.createElement('div');
        row.className = 'cart-item';
        var clave = String(item.clave || item.idarticulo);
        var claveJs = "'" + clave.replace(/[^0-9:]/g, '') + "'";
        row.id = 'ci-' + clave.replace(':', '-');
        row.innerHTML =
          '<div class="ci-img">'
          +   '<img src="' + img + '" onerror="this.src=\'../public/img/default-50x50.gif\'" alt="">'
          + '</div>'
          + '<div>'
          +   '<div class="ci-name">' + esc(item.nombre) + '</div>'
          +   (item.mostrar_presentacion ? '<div class="ci-pres"><i class="bi bi-boxes"></i> ' + esc(item.presentacion) + '</div>' : '')
          +   '<div class="ci-price">S/ ' + item.precio.toFixed(2) + ' c/u &nbsp;·&nbsp; <strong>S/ ' + item.subtotal.toFixed(2) + '</strong></div>'
          +   (item.con_dni
              ? '<div class="ci-dni"><i class="bi bi-person-vcard-fill"></i> Precio con DNI aplicado <s>S/ ' + parseFloat(item.precio_regular).toFixed(2) + '</s></div>'
              : (item.precio_dni > 0 ? '<div class="ci-dni"><i class="bi bi-person-vcard-fill"></i> Con DNI: S/ ' + parseFloat(item.precio_dni).toFixed(2) + ' c/u · <a href="login.php">inicia sesión</a></div>' : ''))
          + '</div>'
          + '<div class="qty-control">'
          +   '<button onclick="cambiarQty(' + claveJs + ',' + (item.cantidad-1) + ')">−</button>'
          +   '<input type="number" value="' + item.cantidad + '" min="0" max="' + item.stock_max + '" onchange="cambiarQty(' + claveJs + ',this.value)">'
          +   '<button onclick="cambiarQty(' + claveJs + ',' + (item.cantidad+1) + ')">+</button>'
          + '</div>'
          + '<button class="btn-delete" onclick="quitarItem(' + claveJs + ')" title="Quitar">✕</button>';
        cont.appendChild(row);
      });
    });
}

function esc(s){ var d=document.createElement('span'); d.textContent=String(s||''); return d.innerHTML; }

// "clave" = idarticulo:idpresentacion (Tableta y Caja del mismo producto son líneas distintas)
function cambiarQty(clave, qty){
  qty = parseInt(qty);
  var fd = new FormData(); fd.append('clave', clave); fd.append('cantidad', qty);
  fetch('ajax/carrito.php?op=actualizar', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d){ if (d.ok) cargarCarrito(); });
}

function quitarItem(clave){
  var fd = new FormData(); fd.append('clave', clave);
  fetch('ajax/carrito.php?op=quitar', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d){ if (d.ok) cargarCarrito(); });
}

function vaciarCarrito(){
  if (!confirm('¿Vaciar todo el carrito?')) return;
  fetch('ajax/carrito.php?op=vaciar', {method:'POST'})
    .then(function(r){ return r.json(); })
    .then(function(d){ if (d.ok) cargarCarrito(); });
}

cargarCarrito();
</script>
</body>
</html>
