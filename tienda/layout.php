<?php
// Helper de layout — include en cada página de la tienda
if (strlen(session_id()) < 1) session_start();

require_once "../config/Conexion.php";
require_once "../modelos/ConfigTienda.php";
require_once "../modelos/Empresa.php";

$cfgTienda = new ConfigTienda();

// Si la tienda está en mantenimiento solo admin puede verla
if (!$cfgTienda->estaActiva() && !isset($_SESSION['nombre'])) {
    ?><!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Tienda en mantenimiento</title>
    <link rel="stylesheet" href="css/tienda.css"></head>
    <body style="display:flex;align-items:center;justify-content:center;min-height:100vh;flex-direction:column;gap:16px;text-align:center;padding:40px">
    <div style="font-size:56px">🔧</div>
    <h2 style="font-size:24px;font-weight:800">Tienda temporalmente cerrada</h2>
    <p style="color:#6b7280">Volvemos pronto. Gracias por tu paciencia.</p>
    </body></html><?php exit;
}

$empMdl   = new Empresa();
$emp      = $empMdl->datosReporte();
$telefono = !empty($emp['telefono']) ? $emp['telefono'] : '';
$waNum    = $cfgTienda->obtener('whatsapp_numero','');

// Nombre: usar BD solo si no es un dato viejo/genérico
$_viejosNombres = ['PERNO CENTRO', 'pernocentro', 'mi tienda', 'itiendas', 'farmacia', ''];
$_empNombreBD   = strtolower(trim($emp['nombre'] ?? ''));
$nombreEmp = (!empty($emp['nombre']) && !in_array($_empNombreBD, $_viejosNombres)
              && stripos($emp['nombre'], 'PERNO') === false)
             ? $emp['nombre']
             : 'Botica FarmaSuyana';

// Logo: prioridad → BD → farmasuyana.png → famacia.png
$logo = '';
if (!empty($emp['logo'])) {
    $logoFS = realpath(__DIR__ . '/../files/empresa/' . $emp['logo']);
    if ($logoFS && file_exists($logoFS)) $logo = '../files/empresa/' . $emp['logo'];
}
if (empty($logo)) {
    if (file_exists(__DIR__ . '/../files/empresa/farmasuyana.png'))
        $logo = '../files/empresa/farmasuyana.png';
    elseif (file_exists(__DIR__ . '/../files/famacia.png'))
        $logo = '../files/famacia.png';
}

$clienteLogado = !empty($_SESSION['tienda_cliente']);
$nombreCliente = $clienteLogado ? $_SESSION['tienda_cliente']['nombre'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars(($GLOBALS['pageTitulo'] ?? 'Tienda') . ' — ' . $nombreEmp); ?></title>
  <link rel="stylesheet" href="css/tienda.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <?php if (!empty($GLOBALS['pageHead'])) echo $GLOBALS['pageHead']; ?>
</head>
<body>

<!-- Toast container -->
<div class="toast-container" id="toastContainer"></div>

<!-- FAB: Carrito flotante -->
<a href="carrito.php" class="fab-cart" id="fabCart" title="Ver mi carrito">
  <i class="bi bi-cart-fill"></i>
  <span class="fab-cart-badge" id="fabCartBadge">0</span>
</a>

<!-- Navbar -->
<nav class="tienda-nav">
  <a href="index.php" class="brand">
    <?php if ($logo): ?><img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo"><?php endif; ?>
    <span><?php echo htmlspecialchars($nombreEmp); ?></span>
    <span class="brand-dot"></span>
  </a>

  <div class="nav-search" id="navSearchWrap">
    <span class="search-icon"><i class="bi bi-search"></i></span>
    <input type="text" id="navSearchInput" placeholder="Buscar productos..." autocomplete="off"
           onkeypress="if(event.key==='Enter'){ window.location.href='index.php?q='+encodeURIComponent(this.value); }">
  </div>

  <div class="nav-actions">
    <?php if ($waNum): ?>
    <a href="https://wa.me/<?php echo preg_replace('/\D/','',$waNum); ?>" target="_blank" class="nav-btn nav-btn-wa">
      <i class="bi bi-whatsapp"></i><span class="nav-btn-label"> WhatsApp</span>
    </a>
    <?php endif; ?>

    <a href="carrito.php" class="nav-btn nav-btn-cart" title="Carrito">
      <i class="bi bi-cart3"></i>
      <span class="nav-cart-num" id="navCartCount">0</span>
    </a>

    <?php if ($clienteLogado): ?>
    <a href="mis_pedidos.php" class="nav-btn"><i class="bi bi-clock-history"></i><span class="nav-btn-label"> Mis pedidos</span></a>
    <a href="logout.php" class="nav-btn"><i class="bi bi-box-arrow-right"></i><span class="nav-btn-label"> Salir</span></a>
    <?php else: ?>
    <a href="login.php" class="nav-btn"><i class="bi bi-person-fill"></i><span class="nav-btn-label"> Ingresar</span></a>
    <?php endif; ?>
  </div>
</nav>

<script>
// ── Cart badge (init + update) ────────────────────────────
function _syncBadge(n){
  var navEl  = document.getElementById('navCartCount');
  var fabBdg = document.getElementById('fabCartBadge');
  var fabBtn = document.getElementById('fabCart');
  if (navEl)  navEl.textContent  = n;
  if (fabBdg) {
    fabBdg.textContent = n;
    fabBdg.className   = 'fab-cart-badge' + (n === 0 ? ' zero' : '');
  }
  if (fabBtn && n > 0) {
    fabBtn.classList.remove('bump');
    void fabBtn.offsetWidth;
    fabBtn.classList.add('bump');
  }
}

(function(){
  fetch('ajax/carrito.php?op=count')
    .then(function(r){ return r.json(); })
    .then(function(d){ _syncBadge(d.total_items || 0); })
    .catch(function(){});
})();

function actualizarCartBadge(){
  fetch('ajax/carrito.php?op=count')
    .then(function(r){ return r.json(); })
    .then(function(d){ _syncBadge(d.total_items || 0); });
}

// ── Toast notifications ───────────────────────────────────
function tiendaNotify(tipo, msg){
  var icons = { ok:'✅', err:'❌', inf:'ℹ️', warn:'⚠️' };
  var container = document.getElementById('toastContainer');
  var toast = document.createElement('div');
  toast.className = 'toast ' + (tipo || 'inf');
  toast.innerHTML = '<span class="toast-icon">' + (icons[tipo] || icons.inf) + '</span><span>' + msg + '</span>';
  container.appendChild(toast);
  setTimeout(function(){
    toast.style.animation = 'toastOut .3s forwards';
    setTimeout(function(){ toast.remove(); }, 300);
  }, 3200);
}
</script>
