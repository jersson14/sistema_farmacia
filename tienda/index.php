<?php
$GLOBALS['pageTitulo'] = 'Catálogo';
require 'layout.php';
$bannerTxt = $cfgTienda->obtener('banner_texto', 'Tu salud, nuestra prioridad');
?>

<!-- Hero -->
<div class="hero">
  <div class="hero-bg-img"></div>
  <div class="hero-inner">
    <?php if ($logo): ?><img src="<?php echo htmlspecialchars($logo); ?>" alt="<?php echo htmlspecialchars($nombreEmp); ?>" class="hero-logo"><?php endif; ?>
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

<!-- Catálogo: panel de filtros + grid -->
<div class="section-wrap">
  <div class="catalogo-layout">

    <!-- ── Panel de filtros (multiselección) ── -->
    <aside class="filtros-panel" id="filtrosPanel">
      <div class="filtros-head">
        <h3><i class="bi bi-sliders"></i> Filtros</h3>
        <button type="button" class="filtros-limpiar" id="btnLimpiarFiltros" onclick="limpiarFiltros()">Limpiar filtros</button>
        <button type="button" class="filtros-cerrar" onclick="toggleFiltros(false)" title="Cerrar"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="filtros-body" id="filtrosBody">
        <div class="filtros-cargando">Cargando filtros…</div>
      </div>
      <div class="filtros-footer">
        <button type="button" class="btn-ver-resultados" onclick="toggleFiltros(false)">Ver resultados</button>
      </div>
    </aside>
    <div class="filtros-backdrop" id="filtrosBackdrop" onclick="toggleFiltros(false)"></div>

    <!-- ── Contenido principal ── -->
    <div class="catalogo-main">
      <div class="catalog-header">
        <div>
          <h2 class="catalog-title"><i class="bi bi-grid-1x2-fill"></i> Nuestros Productos</h2>
          <p class="catalog-sub" id="resultadosLabel">Cargando productos…</p>
        </div>
        <div class="catalog-tools">
          <button type="button" class="btn-filtros-movil" onclick="toggleFiltros(true)">
            <i class="bi bi-sliders"></i> Filtros <span class="btn-filtros-num" id="filtrosNum" style="display:none">0</span>
          </button>
          <label class="orden-wrap">
            <span>Ordenar por</span>
            <select id="ordenSelect" onchange="cambiarOrden(this.value)">
              <option value="">Destacados</option>
              <option value="nombre">Nombre (A-Z)</option>
              <option value="precio_asc">Menor precio</option>
              <option value="precio_desc">Mayor precio</option>
            </select>
          </label>
        </div>
      </div>

      <!-- Chips de filtros aplicados -->
      <div class="chips-aplicados" id="chipsAplicados" style="display:none"></div>

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

  </div>
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
    <?php if ($logo): ?><img src="<?php echo htmlspecialchars($logo); ?>" alt="<?php echo htmlspecialchars($nombreEmp); ?>" class="tf-logo"><?php endif; ?>
    <div class="tf-info">
      <div class="tf-name"><?php echo htmlspecialchars($nombreEmp); ?></div>
      <p class="tf-tag">Al cuidado de tu salud</p>
      <?php if ($direccionEmp): ?>
      <p class="tf-contact"><a href="https://maps.google.com/?q=<?php echo urlencode($direccionEmp); ?>" target="_blank" rel="noopener"><i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($direccionEmp); ?></a></p>
      <?php endif; ?>
      <?php if ($telefono): ?>
      <p class="tf-contact"><i class="bi bi-telephone-fill"></i> <?php echo htmlspecialchars($telefono); ?></p>
      <?php endif; ?>
      <?php if ($correoEmp): ?>
      <p class="tf-contact"><a href="mailto:<?php echo htmlspecialchars($correoEmp); ?>"><i class="bi bi-envelope-fill"></i> <?php echo htmlspecialchars($correoEmp); ?></a></p>
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
var busqueda     = '';
var ordenActual  = '';
var MAX_IMAGENES = 3;

// Estado de los filtros de multiselección del panel lateral
var filtros = { cats: [], labs: [], formas: [], tipos: [], oferta: false, stock: false };
// Opciones disponibles (llegan del servidor)
var opcionesFiltro = { categorias: [], laboratorios: [], formas: [], tipos: [], total_oferta: 0 };
// Grupos colapsados por el usuario
var gruposCerrados = {};

// Si vino del nav con ?q=...
(function(){
  var params = new URLSearchParams(window.location.search);
  if (params.get('q')) {
    busqueda = params.get('q');
    document.getElementById('buscarInput').value = busqueda;
  }
  if (params.get('oferta') === '1') filtros.oferta = true;
})();

// ── Query string común para el backend ─────────────────────
function queryFiltros(){
  var qs = 'q=' + encodeURIComponent(busqueda);
  if (filtros.cats.length)   qs += '&cats='   + encodeURIComponent(filtros.cats.join('|'));
  if (filtros.labs.length)   qs += '&labs='   + encodeURIComponent(filtros.labs.join('|'));
  if (filtros.formas.length) qs += '&formas=' + encodeURIComponent(filtros.formas.join('|'));
  if (filtros.tipos.length)  qs += '&tipos='  + encodeURIComponent(filtros.tipos.join('|'));
  if (filtros.oferta)        qs += '&oferta=1';
  if (filtros.stock)         qs += '&stock=1';
  if (ordenActual)           qs += '&orden='  + encodeURIComponent(ordenActual);
  return qs;
}

function totalFiltrosActivos(){
  return filtros.cats.length + filtros.labs.length + filtros.formas.length
       + filtros.tipos.length + (filtros.oferta ? 1 : 0) + (filtros.stock ? 1 : 0);
}

// ── Panel de filtros ───────────────────────────────────────
function cargarFiltros(){
  fetch('ajax/catalogo.php?op=filtros&q=' + encodeURIComponent(busqueda))
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (!d.ok) return;
      opcionesFiltro = d;
      renderFiltros();
    })
    .catch(function(){
      document.getElementById('filtrosBody').innerHTML =
        '<div class="filtros-cargando">No se pudieron cargar los filtros.</div>';
    });
}

function renderFiltros(){
  var html = '';

  // Grupo destacado: ofertas y disponibilidad
  html += '<div class="filtro-grupo">'
        +   '<div class="filtro-grupo-head" onclick="toggleGrupo(\'destacados\')">'
        +     '<span>Destacados</span>'
        +     '<i class="bi bi-chevron-' + (gruposCerrados['destacados'] ? 'right' : 'down') + '"></i>'
        +   '</div>'
        +   '<div class="filtro-opciones" ' + (gruposCerrados['destacados'] ? 'style="display:none"' : '') + '>'
        +     opcionHtml('oferta', '1', '🔥 Solo productos en oferta', opcionesFiltro.total_oferta, filtros.oferta)
        +     opcionHtml('stock', '1', 'Solo disponibles ahora', null, filtros.stock)
        +   '</div>'
        + '</div>';

  html += grupoHtml('cats',   'Categorías',   opcionesFiltro.categorias);
  html += grupoHtml('labs',   'Marca / Laboratorio', opcionesFiltro.laboratorios);
  html += grupoHtml('formas', 'Presentación', opcionesFiltro.formas);
  html += grupoHtml('tipos',  'Receta médica', opcionesFiltro.tipos);

  document.getElementById('filtrosBody').innerHTML = html;

  var num = totalFiltrosActivos();
  var badge = document.getElementById('filtrosNum');
  badge.textContent = num;
  badge.style.display = num > 0 ? 'inline-flex' : 'none';
}

function grupoHtml(clave, titulo, opciones){
  if (!opciones || opciones.length === 0) return '';
  var cerrado = !!gruposCerrados[clave];
  var lista = '';
  opciones.forEach(function(o){
    var marcado = filtros[clave].indexOf(String(o.valor)) !== -1;
    lista += opcionHtml(clave, o.valor, o.etiqueta, o.total, marcado);
  });
  return '<div class="filtro-grupo">'
       +   '<div class="filtro-grupo-head" onclick="toggleGrupo(\'' + clave + '\')">'
       +     '<span>' + esc(titulo) + ' (' + opciones.length + ')</span>'
       +     '<i class="bi bi-chevron-' + (cerrado ? 'right' : 'down') + '"></i>'
       +   '</div>'
       +   '<div class="filtro-opciones' + (opciones.length > 8 ? ' filtro-opciones-scroll' : '') + '"'
       +     (cerrado ? ' style="display:none"' : '') + '>' + lista + '</div>'
       + '</div>';
}

function opcionHtml(clave, valor, etiqueta, total, marcado){
  var v = String(valor).replace(/'/g, "\\'");
  return '<label class="filtro-opcion' + (marcado ? ' activa' : '') + '">'
       +   '<input type="checkbox" ' + (marcado ? 'checked' : '')
       +     ' onchange="alternarFiltro(\'' + clave + '\',\'' + esc(v) + '\')">'
       +   '<span class="filtro-check"><i class="bi bi-check-lg"></i></span>'
       +   '<span class="filtro-label">' + esc(etiqueta) + '</span>'
       +   (total !== null && total !== undefined ? '<span class="filtro-total">' + total + '</span>' : '')
       + '</label>';
}

function toggleGrupo(clave){
  gruposCerrados[clave] = !gruposCerrados[clave];
  renderFiltros();
}

function alternarFiltro(clave, valor){
  if (clave === 'oferta' || clave === 'stock'){
    filtros[clave] = !filtros[clave];
  } else {
    var i = filtros[clave].indexOf(String(valor));
    if (i === -1) filtros[clave].push(String(valor));
    else          filtros[clave].splice(i, 1);
  }
  paginaActual = 1;
  renderFiltros();
  renderChips();
  cargarProductos();
}

function limpiarFiltros(){
  filtros = { cats: [], labs: [], formas: [], tipos: [], oferta: false, stock: false };
  paginaActual = 1;
  renderFiltros();
  renderChips();
  cargarProductos();
}

// ── Chips de filtros aplicados ─────────────────────────────
function etiquetaDe(clave, valor){
  var fuente = { cats: 'categorias', labs: 'laboratorios', formas: 'formas', tipos: 'tipos' }[clave];
  var lista  = opcionesFiltro[fuente] || [];
  for (var i = 0; i < lista.length; i++){
    if (String(lista[i].valor) === String(valor)) return lista[i].etiqueta;
  }
  return valor;
}

function renderChips(){
  var cont = document.getElementById('chipsAplicados');
  var html = '';
  ['cats','labs','formas','tipos'].forEach(function(clave){
    filtros[clave].forEach(function(v){
      html += '<button type="button" class="chip-aplicado" onclick="alternarFiltro(\'' + clave + '\',\'' + esc(String(v).replace(/'/g,"\\'")) + '\')">'
            + esc(etiquetaDe(clave, v)) + ' <i class="bi bi-x"></i></button>';
    });
  });
  if (filtros.oferta){
    html += '<button type="button" class="chip-aplicado chip-oferta" onclick="alternarFiltro(\'oferta\',\'1\')">En oferta <i class="bi bi-x"></i></button>';
  }
  if (filtros.stock){
    html += '<button type="button" class="chip-aplicado" onclick="alternarFiltro(\'stock\',\'1\')">Disponibles <i class="bi bi-x"></i></button>';
  }
  if (html){
    html = '<span class="chips-titulo">Filtros aplicados:</span>' + html
         + '<button type="button" class="chip-limpiar" onclick="limpiarFiltros()">Limpiar todo</button>';
    cont.innerHTML = html;
    cont.style.display = 'flex';
  } else {
    cont.style.display = 'none';
    cont.innerHTML = '';
  }
}

// ── Panel lateral en móvil ─────────────────────────────────
function toggleFiltros(abrir){
  var panel = document.getElementById('filtrosPanel');
  var back  = document.getElementById('filtrosBackdrop');
  if (abrir){
    panel.classList.add('abierto');
    back.classList.add('visible');
    document.body.style.overflow = 'hidden';
  } else {
    panel.classList.remove('abierto');
    back.classList.remove('visible');
    document.body.style.overflow = '';
  }
}

// ── Búsqueda y orden ───────────────────────────────────────
function buscarProductos(){
  busqueda     = document.getElementById('buscarInput').value.trim();
  paginaActual = 1;
  cargarProductos();
  cargarFiltros();
}

function cambiarOrden(valor){
  ordenActual  = valor;
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

  var url = 'ajax/catalogo.php?op=listar&pagina=' + paginaActual + '&' + queryFiltros();

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
        grid.innerHTML = '<div class="empty-state"><div class="empty-icon">🔍</div><h3>Sin resultados</h3>'
          + '<p>No encontramos productos con ese criterio.</p>'
          + (totalFiltrosActivos() > 0 ? '<button class="btn-quitar-filtros" onclick="limpiarFiltros()">Quitar filtros</button>' : '')
          + '</div>';
        document.getElementById('paginacion').style.display = 'none';
        var lbl = document.getElementById('resultadosLabel');
        if (lbl) lbl.textContent = 'Sin resultados';
        return;
      }
      var html = '';
      d.productos.forEach(function(p){ html += tarjetaProducto(p); });
      grid.innerHTML = html;
      var lbl = document.getElementById('resultadosLabel');
      if (lbl) lbl.textContent = (d.total || d.productos.length) + ' productos disponibles';
      renderPaginacion(d.paginas, d.pagina);
    }).catch(function(e){
      grid.innerHTML = '<div class="empty-state"><div class="empty-icon">⚠️</div><h3>Error de red</h3><p>' + e.message + '</p></div>';
    });
}

// ── Galería de la tarjeta (1 a 3 imágenes) ─────────────────
function listaImagenes(p){
  var imgs = (p.imagenes && p.imagenes.length) ? p.imagenes.slice(0, MAX_IMAGENES)
           : (p.imagen ? [p.imagen] : []);
  return imgs;
}

function galeriaHtml(p, idGaleria){
  var imgs = listaImagenes(p);
  if (imgs.length === 0){
    return '<span class="sin-imagen">💊</span>';
  }
  var html = '';
  imgs.forEach(function(img, i){
    html += '<img class="pc-img' + (i === 0 ? ' activa' : '') + '"'
         +  ' src="../files/articulos/' + encodeURIComponent(img) + '"'
         +  ' alt="' + esc(p.nombre) + '"'
         +  ' onerror="this.style.display=\'none\'">';
  });
  if (imgs.length > 1){
    html += '<div class="pc-dots" onclick="event.stopPropagation()">';
    imgs.forEach(function(img, i){
      html += '<button type="button" class="pc-dot' + (i === 0 ? ' activa' : '') + '"'
           +  ' onmouseenter="verImagen(\'' + idGaleria + '\',' + i + ')"'
           +  ' onclick="event.stopPropagation(); verImagen(\'' + idGaleria + '\',' + i + ')"'
           +  ' title="Foto ' + (i+1) + '"></button>';
    });
    html += '</div>';
    html += '<span class="pc-nfotos"><i class="bi bi-images"></i> ' + imgs.length + '</span>';
  }
  return html;
}

// Cambia la imagen visible de una tarjeta o del modal
function verImagen(idGaleria, indice){
  var cont = document.getElementById(idGaleria);
  if (!cont) return;
  var imgs = cont.querySelectorAll('.pc-img');
  var dots = cont.querySelectorAll('.pc-dot');
  for (var i = 0; i < imgs.length; i++){
    imgs[i].classList.toggle('activa', i === indice);
    if (dots[i]) dots[i].classList.toggle('activa', i === indice);
  }
}

// ── Condición de venta: con receta o sin receta ─────────────
// El cliente debe saber de un vistazo si necesita receta médica.
var CONDICION_VENTA = {
  'OTC': {
    clase:  'venta-libre',
    icono:  'bi-check-circle-fill',
    corto:  'Sin receta',
    titulo: 'Venta libre — no necesitas receta',
    detalle:'Puedes comprar este producto sin presentar receta médica.'
  },
  'RX': {
    clase:  'venta-receta',
    icono:  'bi-file-earmark-medical-fill',
    corto:  'Con receta',
    titulo: 'Requiere receta médica',
    detalle:'Necesitas la receta de tu médico. Preséntala al recibir tu pedido; sin ella no podemos entregarte el medicamento.'
  },
  'CONTROL_ESPECIAL': {
    clase:  'venta-control',
    icono:  'bi-shield-fill-exclamation',
    corto:  'Receta retenida',
    titulo: 'Medicamento de control especial',
    detalle:'Requiere receta médica especial, que la farmacia debe retener y registrar por norma de DIGEMID.'
  }
};

function condicionVenta(tipo){
  return CONDICION_VENTA[tipo] || CONDICION_VENTA['OTC'];
}

// Distintivo que se dibuja sobre la foto del producto
function badgeReceta(tipo){
  var c = condicionVenta(tipo);
  return '<span class="badge-venta ' + c.clase + '" title="' + esc(c.titulo) + '">'
       +   '<i class="bi ' + c.icono + '"></i> ' + esc(c.corto)
       + '</span>';
}

// ── Sello de oferta: cinta diagonal + medallón de descuento ─
// Se dibuja encima de la foto del producto para que la oferta salte a la vista.
function cintaOferta(pct){
  var n = parseFloat(pct) || 0;
  return '<span class="pc-cinta"><span>OFERTA</span></span>'
       + (n > 0 ? '<span class="pc-sello">-' + n.toFixed(0) + '%<small>DCTO</small></span>' : '');
}

// ── Tarjeta de producto ────────────────────────────────────
function tarjetaProducto(p){
  var sinStock = p.stock <= 0;
  var dci = (p.principio_activo)
    ? '<div class="pdci">' + esc(p.principio_activo) + (p.concentracion ? ' ' + esc(p.concentracion) : '') + '</div>'
    : '';
  var lab = p.laboratorio ? '<div class="plab">' + esc(p.laboratorio) + '</div>' : '';
  var precio    = parseFloat(p.precio_venta) || 0;
  var enOferta  = !!p.en_oferta && parseFloat(p.descuento_porcentaje) > 0;
  var precioHtml = precio > 0 ? 'S/ ' + precio.toFixed(2) : '<small>Sin precio</small>';
  var ahorro = 0;
  if (enOferta && precio > 0) {
    var original = parseFloat(p.precio_original || 0);
    ahorro = Math.max(0, original - precio);
    precioHtml = '<span class="precio-tachado">S/ ' + original.toFixed(2) + '</span>S/ ' + precio.toFixed(2);
  }
  var idGaleria = 'gal' + p.idarticulo;

  return '<div class="product-card' + (enOferta ? ' pc-oferta' : '') + '" onclick="verProducto(' + p.idarticulo + ')">'
    + '<div class="img-wrap" id="' + idGaleria + '">'
    +   galeriaHtml(p, idGaleria)
    +   badgeReceta(p.tipo_venta)
    +   (enOferta ? cintaOferta(p.descuento_porcentaje) : '')
    + '</div>'
    + '<div class="card-body">'
    +   '<div class="pcat">' + esc(p.categoria) + '</div>'
    +   '<div class="pname">' + esc(p.nombre) + '</div>'
    +   dci + lab
    +   (enOferta && ahorro > 0 ? '<span class="pc-ahorro"><i class="bi bi-piggy-bank-fill"></i> Ahorras S/ ' + ahorro.toFixed(2) + '</span>' : '')
    +   '<div class="pprice">' + precioHtml + '</div>'
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
      var enOferta = !!p.en_oferta && parseFloat(p.descuento_porcentaje) > 0;
      var sinStock = (parseInt(p.stock) || 0) <= 0;
      var ahorroModal = enOferta ? Math.max(0, (parseFloat(p.precio_original) || 0) - precio) : 0;

      // Galería del modal: imagen grande + miniaturas
      var imgs = listaImagenes(p);
      var imgSec = '<div class="pm-img' + (enOferta ? ' pm-img-oferta' : '') + '" id="pmGaleria">';
      if (imgs.length === 0){
        imgSec += '<span style="font-size:80px">💊</span>';
      } else {
        imgs.forEach(function(img, i){
          imgSec += '<img class="pc-img' + (i === 0 ? ' activa' : '') + '"'
                 +  ' src="../files/articulos/' + encodeURIComponent(img) + '"'
                 +  ' alt="' + esc(p.nombre) + '">';
        });
      }
      if (enOferta) imgSec += cintaOferta(p.descuento_porcentaje);
      imgSec += '</div>';
      if (imgs.length > 1){
        imgSec += '<div class="pm-thumbs">';
        imgs.forEach(function(img, i){
          imgSec += '<button type="button" class="pm-thumb' + (i === 0 ? ' activa' : '') + '"'
                 +  ' onclick="verImagenModal(' + i + ')">'
                 +  '<img src="../files/articulos/' + encodeURIComponent(img) + '" alt="Foto ' + (i+1) + '">'
                 +  '</button>';
        });
        imgSec += '</div>';
      }

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

      // Aviso de condición de venta (con receta / sin receta)
      var cv = condicionVenta(p.tipo_venta);
      var recetaSec = '<div class="pm-receta ' + cv.clase + '">'
        + '<i class="bi ' + cv.icono + '"></i>'
        + '<div><strong>' + esc(cv.titulo) + '</strong>'
        + '<span>' + esc(cv.detalle) + '</span></div>'
        + '</div>';

      // Stock y botón
      var stockTxt = sinStock
        ? '<div class="pm-nostock">⚠️ Sin stock disponible actualmente</div>'
        : '<div class="pm-stock-ok">✅ En stock (' + p.stock + ' disponibles)</div>';

      var btnSec = sinStock
        ? '<button class="btn-primary-lg" disabled style="opacity:.5;cursor:not-allowed"><i class="bi bi-x-circle"></i> Sin stock</button>'
        : '<button class="btn-primary-lg" id="btnModalAgregar" onclick="agregarDesdeModal(' + p.idarticulo + ',\'' + esc(p.nombre).replace(/\'/g,"\\'") + '\')"><i class="bi bi-cart-plus-fill"></i> Agregar al carrito</button>';

      var precioModalHtml = precio > 0
        ? (enOferta ? '<span class="precio-tachado">S/ ' + parseFloat(p.precio_original || 0).toFixed(2) + '</span>' : '')
          + 'S/ ' + precio.toFixed(2) + ' <small>/ unidad</small>'
        : '<small style="font-size:16px">Consultar precio</small>';

      contenido.innerHTML = imgSec
        + '<div class="pm-body">'
        + '<div class="pm-cat">' + esc(p.categoria) + '</div>'
        + '<div class="pm-name">' + esc(p.nombre) + '</div>'
        + (meta ? '<div class="pm-meta">' + meta + '</div>' : '')
        + '<div class="pm-price">' + precioModalHtml + '</div>'
        + (enOferta && ahorroModal > 0 ? '<div class="pc-ahorro" style="margin:-6px 0 14px"><i class="bi bi-piggy-bank-fill"></i> Ahorras S/ ' + ahorroModal.toFixed(2) + '</div>' : '')
        + recetaSec
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

// Cambia la foto grande del modal desde las miniaturas
function verImagenModal(indice){
  verImagen('pmGaleria', indice);
  var thumbs = document.querySelectorAll('.pm-thumb');
  for (var i = 0; i < thumbs.length; i++){
    thumbs[i].classList.toggle('activa', i === indice);
  }
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

// Cerrar con Escape (modal y panel de filtros)
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape'){ cerrarModal(null); toggleFiltros(false); }
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
      cargarFiltros();
    }, 400);
  });
  inp.addEventListener('keypress', function(e){
    if (e.key === 'Enter'){ clearTimeout(timer); buscarProductos(); }
  });
})();

cargarFiltros();
renderChips();
cargarProductos();
</script>
</body>
</html>
