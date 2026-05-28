<?php
$GLOBALS['pageTitulo'] = 'Catálogo';
require 'layout.php';
$bannerTxt = $cfgTienda->obtener('banner_texto', 'Tu salud, nuestra prioridad');
?>

<!-- Hero -->
<div class="hero">
  <div class="hero-bg-img"></div>
  <div class="hero-inner">
    <img src="../files/famacia.png" alt="<?php echo htmlspecialchars($nombreEmp); ?>" class="hero-logo">
    <h1><?php echo htmlspecialchars($nombreEmp); ?></h1>
    <p class="hero-sub">Al cuidado de tu salud &nbsp;·&nbsp; <?php echo htmlspecialchars($bannerTxt); ?></p>
    <div class="hero-search">
      <i class="bi bi-search hero-search-icon"></i>
      <input type="text" id="buscarInput" placeholder="Buscar medicamento, vitamina, crema..." autocomplete="off">
      <button onclick="buscarProductos()"><i class="bi bi-search"></i> Buscar</button>
    </div>
    <div class="hero-trust">
      <span><i class="bi bi-patch-check-fill"></i> Calidad DIGEMID</span>
      <span><i class="bi bi-shield-check-fill"></i> 100% Garantizado</span>
      <span><i class="bi bi-bag-check-fill"></i> Compra Segura</span>
    </div>
  </div>
</div>

<!-- Categorías -->
<div class="cats-section">
  <div class="cats-scroll" id="catsBar">
    <button class="cat-chip active" data-id="0" onclick="filtrarCat(this, 0)">Todos</button>
  </div>
</div>

<!-- Grid de productos -->
<div class="section-wrap">
  <div class="catalog-header">
    <div>
      <h2 class="catalog-title"><i class="bi bi-grid-1x2-fill"></i> Nuestros Productos</h2>
      <p class="catalog-sub" id="resultadosLabel">Cargando productos…</p>
    </div>
  </div>
  <div class="products-grid" id="productGrid">
    <?php for($i=0;$i<15;$i++): ?>
    <div class="product-card-skeleton">
      <div class="skeleton skeleton-img"></div>
      <div class="skeleton-body">
        <div class="skeleton skeleton-line" style="width:40%"></div>
        <div class="skeleton skeleton-line" style="width:90%;margin-top:4px"></div>
        <div class="skeleton skeleton-line" style="width:35%;margin-top:8px;height:18px"></div>
        <div class="skeleton skeleton-line" style="width:100%;margin-top:10px;height:36px;border-radius:8px"></div>
      </div>
    </div>
    <?php endfor; ?>
  </div>
  <div class="pagination-wrap" id="paginacion" style="display:none"></div>
</div>

<!-- Modal detalle de producto -->
<div class="pm-overlay" id="pmOverlay" onclick="cerrarModal(event)">
  <div class="pm-box" id="pmBox">
    <button class="pm-close" onclick="cerrarModal(null)" title="Cerrar">✕</button>
    <div id="pmContenido">
      <!-- Se llena dinámicamente -->
    </div>
  </div>
</div>

<!-- Footer -->
<div class="tienda-footer">
  <div class="tf-top">
    <img src="../files/famacia.png" alt="<?php echo htmlspecialchars($nombreEmp); ?>" class="tf-logo">
    <div class="tf-info">
      <div class="tf-name"><?php echo htmlspecialchars($nombreEmp); ?></div>
      <p class="tf-tag">Al cuidado de tu salud</p>
      <?php if ($telefono): ?>
      <p class="tf-contact"><i class="bi bi-telephone-fill"></i> <?php echo htmlspecialchars($telefono); ?></p>
      <?php endif; ?>
    </div>
  </div>
  <div class="tf-links">
    <a href="index.php"><i class="bi bi-grid-fill"></i> Catálogo</a>
    <a href="carrito.php"><i class="bi bi-bag-fill"></i> Mi Carrito</a>
    <a href="mis_pedidos.php"><i class="bi bi-clock-history"></i> Mis Pedidos</a>
    <?php if ($waNum): ?>
    <a href="https://wa.me/<?php echo preg_replace('/\D/','',$waNum); ?>" target="_blank">
      <i class="bi bi-whatsapp"></i> WhatsApp
    </a>
    <?php endif; ?>
  </div>
  <div class="tf-bottom">
    <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($nombreEmp); ?> — Todos los derechos reservados</p>
  </div>
</div>

<script>
var paginaActual = 1;
var catActual    = 0;
var busqueda     = '';

// Si vino del nav con ?q=...
(function(){
  var params = new URLSearchParams(window.location.search);
  if (params.get('q')) {
    busqueda = params.get('q');
    document.getElementById('buscarInput').value = busqueda;
  }
})();

// ── Categorías ────────────────────────────────────────────
function cargarCategorias(){
  fetch('ajax/catalogo.php?op=categorias')
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (!d.ok) return;
      var bar = document.getElementById('catsBar');
      d.categorias.forEach(function(c){
        var chip = document.createElement('button');
        chip.className  = 'cat-chip';
        chip.dataset.id = c.idcategoria;
        chip.textContent = c.nombre + ' (' + c.total + ')';
        chip.onclick = function(){ filtrarCat(chip, c.idcategoria); };
        bar.appendChild(chip);
      });
    });
}

function filtrarCat(el, id){
  document.querySelectorAll('.cat-chip').forEach(function(c){ c.classList.remove('active'); });
  el.classList.add('active');
  catActual    = id;
  paginaActual = 1;
  cargarProductos();
}

// ── Búsqueda ──────────────────────────────────────────────
function buscarProductos(){
  busqueda     = document.getElementById('buscarInput').value.trim();
  paginaActual = 1;
  cargarProductos();
}

// ── Grid de productos ──────────────────────────────────────
function cargarProductos(){
  var grid = document.getElementById('productGrid');
  var skels = '';
  for (var k = 0; k < 15; k++){
    skels += '<div class="product-card-skeleton">'
      + '<div class="skeleton skeleton-img"></div>'
      + '<div class="skeleton-body">'
      + '<div class="skeleton skeleton-line" style="width:40%"></div>'
      + '<div class="skeleton skeleton-line" style="width:90%;margin-top:4px"></div>'
      + '<div class="skeleton skeleton-line" style="width:35%;margin-top:8px;height:18px"></div>'
      + '<div class="skeleton skeleton-line" style="width:100%;margin-top:10px;height:36px;border-radius:8px"></div>'
      + '</div></div>';
  }
  grid.innerHTML = skels;

  var url = 'ajax/catalogo.php?op=listar&pagina=' + paginaActual
          + '&idcat=' + catActual
          + '&q=' + encodeURIComponent(busqueda);

  fetch(url)
    .then(function(r){ return r.text(); })
    .then(function(txt){
      var d;
      try { d = JSON.parse(txt); }
      catch(e){
        grid.innerHTML = '<div class="empty-state"><div class="empty-icon">⚠️</div><h3>Error del servidor</h3><p style="font-size:12px;word-break:break-all;max-width:400px">' + txt.substring(0,300) + '</p></div>';
        return;
      }
      grid.innerHTML = '';
      if (!d.ok) {
        grid.innerHTML = '<div class="empty-state"><div class="empty-icon">⚠️</div><h3>Error</h3><p>' + esc(d.message || 'Error desconocido') + '</p></div>';
        document.getElementById('paginacion').style.display = 'none';
        return;
      }
      if (!d.productos || d.productos.length === 0) {
        grid.innerHTML = '<div class="empty-state"><div class="empty-icon">🔍</div><h3>Sin resultados</h3><p>No encontramos productos con ese criterio.</p></div>';
        document.getElementById('paginacion').style.display = 'none';
        var lbl = document.getElementById('resultadosLabel');
        if (lbl) lbl.textContent = 'Sin resultados';
        return;
      }
      d.productos.forEach(function(p){ grid.innerHTML += tarjetaProducto(p); });
      var lbl = document.getElementById('resultadosLabel');
      if (lbl) lbl.textContent = (d.total || d.productos.length) + ' productos disponibles';
      renderPaginacion(d.paginas, d.pagina);
    }).catch(function(e){
      grid.innerHTML = '<div class="empty-state"><div class="empty-icon">⚠️</div><h3>Error de red</h3><p>' + e.message + '</p></div>';
    });
}

// ── Tarjeta de producto ────────────────────────────────────
function imgHtml(imagen, nombre){
  if (imagen) {
    return '<img src="../files/articulos/' + encodeURIComponent(imagen) + '" alt="' + esc(nombre) + '" '
      + 'onerror="this.parentNode.innerHTML=\'<span style=&quot;font-size:52px&quot;>💊</span>\'">';
  }
  return '<span style="font-size:52px">💊</span>';
}

function tarjetaProducto(p){
  var sinStock = p.stock <= 0;
  var dci = (p.principio_activo)
    ? '<div class="pdci">' + esc(p.principio_activo) + (p.concentracion ? ' ' + esc(p.concentracion) : '') + '</div>'
    : '';
  var lab = p.laboratorio ? '<div class="plab">' + esc(p.laboratorio) + '</div>' : '';
  var precio = parseFloat(p.precio_venta) || 0;

  return '<div class="product-card" onclick="verProducto(' + p.idarticulo + ')">'
    + '<div class="img-wrap">'
    +   imgHtml(p.imagen, p.nombre)
    +   '<span class="badge-otc">OTC</span>'
    + '</div>'
    + '<div class="card-body">'
    +   '<div class="pcat">' + esc(p.categoria) + '</div>'
    +   '<div class="pname">' + esc(p.nombre) + '</div>'
    +   dci + lab
    +   '<div class="pprice">' + (precio > 0 ? 'S/ ' + precio.toFixed(2) : '<small>Sin precio</small>') + '</div>'
    +   (sinStock
        ? '<button class="btn-agregar" disabled onclick="event.stopPropagation()"><i class="bi bi-x-circle"></i> Sin stock</button>'
        : '<button class="btn-agregar" onclick="event.stopPropagation(); agregarAlCarrito(' + p.idarticulo + ',\'' + esc(p.nombre).replace(/\'/g,"\\'") + '\', this)"><i class="bi bi-cart-plus"></i> Agregar</button>')
    + '</div></div>';
}

// ── Agregar al carrito ─────────────────────────────────────
function agregarAlCarrito(id, nombre, btn){
  var orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Agregando…';
  var fd = new FormData();
  fd.append('idarticulo', id);
  fd.append('cantidad', 1);
  fetch('ajax/carrito.php?op=agregar', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok){
        btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Agregado';
        btn.classList.add('adding');
        tiendaNotify('ok', nombre + ' agregado al carrito');
        actualizarCartBadge();
        setTimeout(function(){ btn.innerHTML = orig; btn.classList.remove('adding'); btn.disabled = false; }, 1800);
      } else {
        tiendaNotify('err', d.message || 'No se pudo agregar');
        btn.innerHTML = orig;
        btn.disabled = false;
      }
    });
}

// ── Modal detalle de producto ──────────────────────────────
function verProducto(id){
  var overlay = document.getElementById('pmOverlay');
  var contenido = document.getElementById('pmContenido');

  // Mostrar modal con skeleton
  contenido.innerHTML =
    '<div class="pm-img"><span style="font-size:80px">⏳</span></div>'
    + '<div class="pm-body">'
    + '<div class="skeleton skeleton-line" style="width:30%;margin-bottom:10px"></div>'
    + '<div class="skeleton skeleton-line" style="width:85%;height:22px;margin-bottom:8px"></div>'
    + '<div class="skeleton skeleton-line" style="width:50%;margin-bottom:20px"></div>'
    + '<div class="skeleton skeleton-line" style="width:40%;height:30px;margin-bottom:16px"></div>'
    + '<div class="skeleton skeleton-line" style="width:100%;margin-bottom:6px"></div>'
    + '<div class="skeleton skeleton-line" style="width:90%;margin-bottom:6px"></div>'
    + '<div class="skeleton skeleton-line" style="width:70%"></div>'
    + '</div>';

  overlay.classList.add('open');
  document.body.style.overflow = 'hidden';

  fetch('ajax/catalogo.php?op=producto&id=' + id)
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (!d.ok){ contenido.innerHTML = '<div class="pm-body"><p style="color:#ef4444">Producto no encontrado.</p></div>'; return; }
      var p = d.producto;
      var precio = parseFloat(p.precio_venta) || 0;
      var sinStock = (parseInt(p.stock) || 0) <= 0;

      var imgSec = '<div class="pm-img">';
      if (p.imagen) {
        imgSec += '<img src="../files/articulos/' + encodeURIComponent(p.imagen) + '" alt="' + esc(p.nombre) + '" onerror="this.parentNode.innerHTML=\'<span style=font-size:80px>💊</span>\'">';
      } else {
        imgSec += '<span style="font-size:80px">💊</span>';
      }
      imgSec += '</div>';

      // Etiquetas de meta
      var meta = '';
      if (p.principio_activo) meta += '<span class="pm-tag">💊 ' + esc(p.principio_activo) + (p.concentracion ? ' ' + esc(p.concentracion) : '') + '</span>';
      if (p.laboratorio)      meta += '<span class="pm-tag">🏭 ' + esc(p.laboratorio) + '</span>';
      if (p.forma_farmaceutica) meta += '<span class="pm-tag">' + esc(p.forma_farmaceutica) + '</span>';
      if (p.via_administracion) meta += '<span class="pm-tag">📌 ' + esc(p.via_administracion) + '</span>';

      // Descripción
      var descSec = '';
      if (p.descripcion && p.descripcion.trim() !== '') {
        descSec = '<hr class="pm-divider"><div class="pm-desc-title">Descripción</div><div class="pm-desc">' + esc(p.descripcion).replace(/\n/g,'<br>') + '</div>';
      }

      // Stock y botón
      var stockTxt = sinStock
        ? '<div class="pm-nostock">⚠️ Sin stock disponible actualmente</div>'
        : '<div class="pm-stock-ok">✅ En stock (' + p.stock + ' disponibles)</div>';

      var btnSec = sinStock
        ? '<button class="btn-primary-lg" disabled style="opacity:.5;cursor:not-allowed"><i class="bi bi-x-circle"></i> Sin stock</button>'
        : '<button class="btn-primary-lg" id="btnModalAgregar" onclick="agregarDesdeModal(' + p.idarticulo + ',\'' + esc(p.nombre).replace(/\'/g,"\\'") + '\')"><i class="bi bi-cart-plus-fill"></i> Agregar al carrito</button>';

      contenido.innerHTML = imgSec
        + '<div class="pm-body">'
        + '<div class="pm-cat">' + esc(p.categoria) + '</div>'
        + '<div class="pm-name">' + esc(p.nombre) + '</div>'
        + (meta ? '<div class="pm-meta">' + meta + '</div>' : '')
        + '<div class="pm-price">' + (precio > 0 ? 'S/ ' + precio.toFixed(2) + ' <small>/ unidad</small>' : '<small style="font-size:16px">Consultar precio</small>') + '</div>'
        + descSec
        + '<hr class="pm-divider">'
        + stockTxt
        + btnSec
        + '</div>';
    })
    .catch(function(){
      contenido.innerHTML = '<div class="pm-body"><p style="color:#ef4444">Error al cargar el producto.</p></div>';
    });
}

function agregarDesdeModal(id, nombre){
  var btn = document.getElementById('btnModalAgregar');
  if (!btn) return;
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Agregando…';
  var fd = new FormData();
  fd.append('idarticulo', id);
  fd.append('cantidad', 1);
  fetch('ajax/carrito.php?op=agregar', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok){
        btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Agregado al carrito';
        btn.classList.add('adding');
        tiendaNotify('ok', nombre + ' agregado al carrito');
        actualizarCartBadge();
      } else {
        tiendaNotify('err', d.message || 'No se pudo agregar');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cart-plus-fill"></i> Agregar al carrito';
      }
    });
}

function cerrarModal(e){
  if (e && e.target !== document.getElementById('pmOverlay')) return;
  document.getElementById('pmOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

// Cerrar con Escape
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') cerrarModal(null);
});

// ── Paginación ─────────────────────────────────────────────
function renderPaginacion(paginas, actual){
  var el = document.getElementById('paginacion');
  if (paginas <= 1){ el.style.display = 'none'; return; }
  el.style.display = 'flex';
  el.innerHTML = '';
  for (var i = 1; i <= paginas; i++){
    var btn = document.createElement('button');
    btn.textContent = i;
    btn.className   = 'page-btn' + (i === actual ? ' active' : '');
    btn.onclick = (function(p){ return function(){
      paginaActual = p;
      cargarProductos();
      window.scrollTo({top: 0, behavior: 'smooth'});
    }; })(i);
    el.appendChild(btn);
  }
}

function esc(s){ var d = document.createElement('span'); d.textContent = String(s || ''); return d.innerHTML; }

// ── Inicio ─────────────────────────────────────────────────
(function(){
  var inp = document.getElementById('buscarInput');
  var timer;
  inp.addEventListener('input', function(){
    clearTimeout(timer);
    timer = setTimeout(function(){
      busqueda     = inp.value.trim();
      paginaActual = 1;
      cargarProductos();
    }, 400);
  });
  inp.addEventListener('keypress', function(e){
    if (e.key === 'Enter'){ clearTimeout(timer); buscarProductos(); }
  });
})();

cargarCategorias();
cargarProductos();
</script>
</body>
</html>
