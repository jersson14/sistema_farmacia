<?php 
if (strlen(session_id())<1) 
  session_start();

$brandNombre = "FARMASUYANA";
$brandSub = "AL CUIDADO DE TU SALUD";
$brandLogo = "../files/famacia.png";
$brandPrimary = "#1D4ED8";
$brandPrimaryDark = "#1E3A8A";
$brandSecondary = "#DC2626";

try {
  require_once "../config/Conexion.php";
if (!function_exists('darkenHexColor')) {
    function darkenHexColor($hex, $factor = 0.22) {
      $hex = ltrim((string)$hex, '#');
      if (strlen($hex) !== 6) {
        return '#0b4f4a';
      }
      $r = hexdec(substr($hex, 0, 2));
      $g = hexdec(substr($hex, 2, 2));
      $b = hexdec(substr($hex, 4, 2));
      $r = max(0, (int)round($r * (1 - $factor)));
      $g = max(0, (int)round($g * (1 - $factor)));
      $b = max(0, (int)round($b * (1 - $factor)));
      return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
  }
  if (!function_exists('decodeBrandText')) {
    function decodeBrandText($text) {
      $decoded = html_entity_decode((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      return trim($decoded);
    }
  }
  $tablaCfg = ejecutarConsulta("SHOW TABLES LIKE 'configuracion_empresa'");
  if ($tablaCfg && $tablaCfg->num_rows > 0) {
    $cfg = ejecutarConsultaSimpleFila("SELECT * FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1");
    if ($cfg) {
      if (!empty($cfg['nombre_comercial'])) {
        $brandNombre = strtoupper(decodeBrandText($cfg['nombre_comercial']));
      }
      if (!empty($cfg['razon_social'])) {
        $brandSub = strtoupper(decodeBrandText($cfg['razon_social']));
      }
      if ($brandSub === $brandNombre) {
        $brandSub = "";
      } elseif ($brandSub !== "" && stripos($brandSub, $brandNombre) !== false) {
        $resto = trim(str_ireplace($brandNombre, "", $brandSub));
        $resto = trim($resto, " -|\"'");
        if ($resto !== "") {
          $brandSub = $resto;
        }
      }
      if (!empty($cfg['logo'])) {
        $logoEmpresaFS = realpath(__DIR__ . "/../files/empresa/" . $cfg['logo']);
        if ($logoEmpresaFS && file_exists($logoEmpresaFS)) {
          $brandLogo = "../files/empresa/" . $cfg['logo'];
        } elseif (file_exists(__DIR__ . "/" . $cfg['logo'])) {
          $brandLogo = $cfg['logo'];
        } else {
          $brandLogo = "logo1.jpeg";
        }
      }
      if (!empty($cfg['color_primario'])) {
        $brandPrimary = $cfg['color_primario'];
        $brandPrimaryDark = darkenHexColor($brandPrimary, 0.25);
      }
      if (!empty($cfg['color_secundario'])) {
        $brandSecondary = $cfg['color_secundario'];
      }
    }
  }
} catch (Exception $e) {
}

  ?>
 <!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>FarmaSuyana | Sistema</title>
  <!-- Tell the browser to be responsive to screen width -->
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
  <!-- Bootstrap 3.3.7 -->
  <link rel="stylesheet" href="../public/css/bootstrap.min.css">
  <!-- Font Awesome -->

  <link rel="stylesheet" href="../public/css/font-awesome.min.css">

  <link rel="stylesheet" href="../public/css/AdminLTE.min.css">
  <link rel="stylesheet" href="../public/css/_all-skins.min.css">
  <link rel="stylesheet" href="../public/css/custom-theme.css?v=20260527d">
  <link rel="stylesheet" href="../public/css/pos.css?v=20260528b">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="icon" href="<?php echo htmlspecialchars($brandLogo); ?>" type="image/png">
<!-- DATATABLES-->

<link rel="stylesheet" href="../public/datatables/jquery.dataTables.min.css">
<link rel="stylesheet" href="../public/datatables/buttons.dataTables.min.css">
<link rel="stylesheet" href="../public/datatables/responsive.dataTables.min.css">
<link rel="stylesheet" href="../public/css/bootstrap-select.min.css">
<style>
:root{
  --brand-primary: <?php echo htmlspecialchars($brandPrimary); ?>;
  --brand-primary-dark: <?php echo htmlspecialchars($brandPrimaryDark); ?>;
  --brand-accent: <?php echo htmlspecialchars($brandSecondary); ?>;
}

/* ── Bell de notificaciones ─────────────────────────────── */
.notif-bell-li { position: relative; }
.notif-bell-btn {
  position: relative;
  padding: 18px 14px !important;
  color: rgba(255,255,255,0.85) !important;
}
.notif-bell-icon { font-size: 18px; }
.notif-bell-badge {
  position: absolute;
  top: 10px;
  right: 6px;
  background: #dc2626;
  color: #fff;
  border-radius: 50%;
  width: 18px;
  height: 18px;
  font-size: 10px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  animation: notif-pulse 1.8s infinite;
}
@keyframes notif-pulse {
  0%, 100% { transform: scale(1); }
  50%       { transform: scale(1.2); }
}
.notif-bell-badge.sin-pedidos { animation: none; background: #64748b; }

.notif-dropdown {
  width: 300px;
  padding: 0;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 8px 30px rgba(0,0,0,0.18);
  border: 1px solid #e2e8f0;
}
.notif-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: var(--brand-primary);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
}
.notif-header a.notif-ver-todos {
  color: rgba(255,255,255,0.8);
  font-size: 11px;
  font-weight: 600;
  text-decoration: none;
}
.notif-header a.notif-ver-todos:hover { color: #fff; }
.notif-body { max-height: 300px; overflow-y: auto; }
.notif-empty {
  padding: 20px;
  text-align: center;
  color: #94a3b8;
  font-size: 13px;
}
.notif-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 14px;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  text-decoration: none;
  color: #1f2937;
}
.notif-item:hover { background: #f8fafc; color: #1f2937; }
.notif-item-icon {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: #dbeafe;
  color: var(--brand-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  flex-shrink: 0;
}
.notif-item-body { flex: 1; min-width: 0; }
.notif-item-nombre {
  font-size: 13px;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.notif-item-meta {
  font-size: 11px;
  color: #64748b;
  margin-top: 1px;
}
.notif-item-total {
  font-size: 13px;
  font-weight: 800;
  color: #16a34a;
  flex-shrink: 0;
}

/* ── Bell de stock bajo ─────────────────────────────────── */
.stock-bell-btn {
  position: relative;
  padding: 18px 14px !important;
  color: rgba(255,255,255,0.85) !important;
}
.stock-bell-icon { font-size: 18px; }
.stock-bell-badge {
  position: absolute;
  top: 10px;
  right: 6px;
  background: #d97706;
  color: #fff;
  border-radius: 50%;
  width: 18px;
  height: 18px;
  font-size: 10px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  animation: stock-pulse 1.8s infinite;
}
@keyframes stock-pulse {
  0%, 100% { transform: scale(1);   box-shadow: 0 0 0 0 rgba(217,119,6,0.6); }
  50%       { transform: scale(1.2); box-shadow: 0 0 0 5px rgba(217,119,6,0); }
}
.stock-dropdown {
  width: 300px;
  padding: 0;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 8px 30px rgba(0,0,0,0.18);
  border: 1px solid #fde68a;
}
.stock-notif-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: #b45309;
  color: #fff;
  font-size: 13px;
  font-weight: 700;
}
.stock-notif-header a {
  color: rgba(255,255,255,0.8);
  font-size: 11px;
  font-weight: 600;
  text-decoration: none;
}
.stock-notif-header a:hover { color: #fff; }
.stock-notif-body { max-height: 300px; overflow-y: auto; }
.stock-notif-empty {
  padding: 20px;
  text-align: center;
  color: #94a3b8;
  font-size: 13px;
}
.stock-notif-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 14px;
  border-bottom: 1px solid #fef3c7;
  color: #1f2937;
}
.stock-notif-item:last-child { border-bottom: none; }
.stock-notif-icon {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: #fef3c7;
  color: #b45309;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  flex-shrink: 0;
}
.stock-notif-nombre {
  font-size: 13px;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
  min-width: 0;
}
.stock-notif-qty {
  font-size: 13px;
  font-weight: 800;
  flex-shrink: 0;
  padding: 2px 8px;
  border-radius: 50px;
}
.stock-qty-agotado { background: #fee2e2; color: #991b1b; }
.stock-qty-critico { background: #fef3c7; color: #92400e; }

/* ── Bell de vencimientos ───────────────────────────────── */
.venc-bell-btn {
  position: relative;
  padding: 18px 14px !important;
  color: rgba(255,255,255,0.85) !important;
}
.venc-bell-icon { font-size: 18px; }
.venc-bell-badge {
  position: absolute;
  top: 10px;
  right: 6px;
  background: #9333ea;
  color: #fff;
  border-radius: 50%;
  width: 18px;
  height: 18px;
  font-size: 10px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  animation: venc-pulse 1.8s infinite;
}
@keyframes venc-pulse {
  0%, 100% { transform: scale(1);   box-shadow: 0 0 0 0 rgba(147,51,234,0.6); }
  50%       { transform: scale(1.2); box-shadow: 0 0 0 5px rgba(147,51,234,0); }
}
.venc-dropdown {
  width: 310px;
  padding: 0;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 8px 30px rgba(0,0,0,0.18);
  border: 1px solid #e9d5ff;
}
.venc-notif-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: #7e22ce;
  color: #fff;
  font-size: 13px;
  font-weight: 700;
}
.venc-notif-header a {
  color: rgba(255,255,255,0.8);
  font-size: 11px;
  font-weight: 600;
  text-decoration: none;
}
.venc-notif-header a:hover { color: #fff; }
.venc-notif-body { max-height: 300px; overflow-y: auto; }
.venc-notif-empty {
  padding: 20px;
  text-align: center;
  color: #94a3b8;
  font-size: 13px;
}
.venc-notif-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 14px;
  border-bottom: 1px solid #f3e8ff;
  color: #1f2937;
}
.venc-notif-item:last-child { border-bottom: none; }
.venc-notif-icon {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  flex-shrink: 0;
}
.venc-icon-vencido  { background: #fee2e2; color: #dc2626; }
.venc-icon-proximo  { background: #fef3c7; color: #b45309; }
.venc-notif-body-text { flex: 1; min-width: 0; }
.venc-notif-nombre {
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.venc-notif-sub {
  font-size: 11px;
  color: #64748b;
  margin-top: 1px;
}
.venc-notif-tag {
  font-size: 10px;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 50px;
  flex-shrink: 0;
  white-space: nowrap;
}
.venc-tag-vencido { background: #fee2e2; color: #991b1b; }
.venc-tag-proximo { background: #fef3c7; color: #92400e; }
</style>

</head>
<body class="hold-transition skin-blue sidebar-mini">
<div class="wrapper">

  <header class="main-header">
    <!-- Logo -->
    <a href="escritorio.php" class="logo app-brand-logo">
      <!-- mini logo for sidebar mini 50x50 pixels -->
      <span class="logo-mini">
        <img src="<?php echo htmlspecialchars($brandLogo); ?>" alt="Logo Empresa" class="brand-logo-mini-img" onerror="this.src='logo1.jpeg'">
      </span>
      <!-- logo for regular state and mobile devices -->
      
      <span class="logo-lg">
        <span class="brand-logo-lg-wrap">
          <img src="<?php echo htmlspecialchars($brandLogo); ?>" alt="Logo Empresa" class="brand-logo-img" onerror="this.src='logo1.jpeg'">
        </span>
      </span>
    </a>
    <!-- Header Navbar: style can be found in header.less -->
    <nav class="navbar navbar-static-top">
      <!-- Sidebar toggle button-->
      <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
        <span class="sr-only">NAVEGACIÓM</span>
      </a>

      <div class="navbar-custom-menu">
        <ul class="nav navbar-nav">

          <?php
          // Badge de estado de caja en el header
          $cajaBadgeAbierta = false;
          if (isset($_SESSION['idusuario'])) {
            try {
              $cajaBadgeRow = ejecutarConsultaSimpleFila(
                "SELECT idcaja FROM caja_diaria WHERE idusuario='" . (int)$_SESSION['idusuario'] . "' AND estado='ABIERTA' ORDER BY idcaja DESC LIMIT 1"
              );
              if ($cajaBadgeRow && !empty($cajaBadgeRow['idcaja'])) {
                $cajaBadgeAbierta = true;
              }
            } catch (Exception $e) {}
          }
          ?>
          <li>
            <a href="caja.php" title="Estado de caja" style="padding-top:18px;padding-bottom:18px;">
              <?php if ($cajaBadgeAbierta): ?>
                <span class="caja-badge caja-badge-open">
                  <span class="caja-dot"></span>
                  <i class="bi bi-unlock-fill" style="font-size:12px;"></i>
                  CAJA ABIERTA
                </span>
              <?php else: ?>
                <span class="caja-badge caja-badge-closed">
                  <span class="caja-dot"></span>
                  <i class="bi bi-lock-fill" style="font-size:12px;"></i>
                  CAJA CERRADA
                </span>
              <?php endif; ?>
            </a>
          </li>

          <!-- Bell de pedidos online -->
          <?php if (!empty($_SESSION['ventas']) && $_SESSION['ventas']==1): ?>
          <li class="dropdown notif-bell-li" id="notifBellLi">
            <a href="#" class="dropdown-toggle notif-bell-btn" data-toggle="dropdown" title="Pedidos online pendientes">
              <i class="bi bi-bell-fill notif-bell-icon"></i>
              <span class="notif-bell-badge" id="notifBellBadge" style="display:none">0</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-right notif-dropdown" id="notifDropdown">
              <li class="notif-header">
                <span><i class="bi bi-bag-check"></i> Pedidos pendientes</span>
                <a href="pedidos_online.php" class="notif-ver-todos">Ver todos</a>
              </li>
              <li class="notif-body" id="notifBody">
                <div class="notif-empty">Sin pedidos nuevos</div>
              </li>
            </ul>
          </li>
          <?php endif; ?>

          <!-- Bell de vencimientos -->
          <?php if (!empty($_SESSION['almacen']) && $_SESSION['almacen']==1): ?>
          <li class="dropdown" id="vencBellLi" style="display:none">
            <a href="#" class="dropdown-toggle venc-bell-btn" data-toggle="dropdown" title="Alertas de vencimiento">
              <i class="bi bi-calendar-x-fill venc-bell-icon" style="color:#c084fc"></i>
              <span class="venc-bell-badge" id="vencBellBadge">0</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-right venc-dropdown" id="vencDropdown">
              <li class="venc-notif-header">
                <span><i class="bi bi-calendar-x-fill"></i> Vencimientos</span>
                <a href="vencimientos.php">Ver todo &rarr;</a>
              </li>
              <li class="venc-notif-body" id="vencNotifBody">
                <div class="venc-notif-empty">Cargando...</div>
              </li>
            </ul>
          </li>
          <?php endif; ?>

          <!-- Bell de stock bajo -->
          <?php if (!empty($_SESSION['almacen']) && $_SESSION['almacen']==1 || !empty($_SESSION['ventas']) && $_SESSION['ventas']==1): ?>
          <li class="dropdown" id="stockBellLi" style="display:none">
            <a href="#" class="dropdown-toggle stock-bell-btn" data-toggle="dropdown" title="Productos con stock bajo">
              <i class="bi bi-exclamation-triangle-fill stock-bell-icon" style="color:#fbbf24"></i>
              <span class="stock-bell-badge" id="stockBellBadge">0</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-right stock-dropdown" id="stockDropdown">
              <li class="stock-notif-header">
                <span><i class="bi bi-exclamation-triangle-fill"></i> Stock bajo</span>
                <a href="articulo.php">Ver artículos</a>
              </li>
              <li class="stock-notif-body" id="stockNotifBody">
                <div class="stock-notif-empty">Cargando...</div>
              </li>
            </ul>
          </li>
          <?php endif; ?>

          <li class="dropdown user user-menu">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              <img src="../files/usuarios/<?php echo $_SESSION['imagen']; ?>" class="user-image" alt="">
              <span class="hidden-xs"><?php echo $_SESSION['nombre']; ?></span>
            </a>
            <ul class="dropdown-menu">
              <!-- User image -->
              <li class="user-header">
                <img src="../files/usuarios/<?php echo $_SESSION['imagen']; ?>" class="img-circle" alt="">

                <p>
                <span class="hidden-xs"><?php echo $_SESSION['nombre']; ?></span>
                  <small>2021</small>
                </p>
              </li>
              <!-- Menu Footer-->
              <li class="user-footer">
                <div class="pull-left">
                  <a href="usuario.php?perfil=1&idusuario=<?php echo (int)$_SESSION['idusuario']; ?>" class="btn btn-default btn-flat">Mi Perfil</a>
                </div>
                <div class="pull-right">
                  <a href="../ajax/usuario.php?op=salir" class="btn btn-default btn-flat">Salir</a>
                </div>
              </li>
            </ul>
          </li>
          <!-- Control Sidebar Toggle Button -->

        </ul>
      </div>
    </nav>
  </header>
  <!-- Left side column. contains the logo and sidebar -->
  <aside class="main-sidebar">
    <!-- sidebar: style can be found in sidebar.less -->
    <section class="sidebar">
      <!-- Sidebar user panel -->
     
      <!-- /.search form -->
      <!-- sidebar menu: : style can be found in sidebar.less -->
      <ul class="sidebar-menu" data-widget="tree">

<br>
       <?php
if ($_SESSION['escritorio']==1) {
  echo '<li><a href="escritorio.php">
          <i class="bi bi-speedometer2"></i> <span>Escritorio</span>
        </a></li>';
}
        ?>
        <?php
if ($_SESSION['almacen']==1) {
  echo '<li class="treeview">
          <a href="#">
            <i class="bi bi-boxes"></i> <span>Almacén</span>
            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
          </a>
          <ul class="treeview-menu">
            <li><a href="articulo.php"><i class="bi bi-capsule-pill"></i> Artículos</a></li>
            <li><a href="categoria.php"><i class="bi bi-tags"></i> Categorías</a></li>
            <li><a href="unidad.php"><i class="bi bi-rulers"></i> Unidades</a></li>
            <li><a href="vencimientos.php"><i class="bi bi-calendar-x"></i> Vencimientos</a></li>
          </ul>
        </li>';
}
        ?>
        <?php
if ($_SESSION['compras']==1) {
  echo '<li class="treeview">
          <a href="#">
            <i class="bi bi-bag-check"></i> <span>Compras</span>
            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
          </a>
          <ul class="treeview-menu">
            <li><a href="ingreso.php"><i class="bi bi-box-arrow-in-down"></i> Ingresos</a></li>
            <li><a href="proveedor.php"><i class="bi bi-truck"></i> Proveedores</a></li>
          </ul>
        </li>';
}
        ?>
        <?php
if ($_SESSION['ventas']==1) {
  echo '<li class="treeview">
          <a href="#">
            <i class="bi bi-cart3"></i> <span>Ventas</span>
            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
          </a>
          <ul class="treeview-menu">
            <li><a href="venta.php"><i class="bi bi-receipt"></i> Ventas</a></li>
            <li><a href="cliente.php"><i class="bi bi-people"></i> Clientes</a></li>
            <li><a href="caja.php"><i class="bi bi-cash-stack"></i> Caja Diaria</a></li>
            <li><a href="control_especial.php"><i class="bi bi-shield-exclamation"></i> Control Especial</a></li>
            <li><a href="pedidos_online.php"><i class="bi bi-phone"></i> Pedidos Online</a></li>
          </ul>
        </li>';
}
        ?>
        <?php
if ($_SESSION['acceso']==1) {
  echo '<li class="treeview">
          <a href="#">
            <i class="bi bi-person-lock"></i> <span>Acceso</span>
            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
          </a>
          <ul class="treeview-menu">
            <li><a href="usuario.php"><i class="bi bi-person-gear"></i> Usuarios</a></li>
            <li><a href="permiso.php"><i class="bi bi-key"></i> Permisos</a></li>
          </ul>
        </li>';
}
        ?>
        <?php
if ($_SESSION['consultac']==1 || $_SESSION['consultav']==1) {
  echo '<li class="treeview">
          <a href="#">
            <i class="bi bi-bar-chart-line"></i> <span>Reportes</span>
            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
          </a>
          <ul class="treeview-menu">';
  if ($_SESSION['consultac']==1) {
    echo '<li><a href="comprasfecha.php"><i class="bi bi-file-earmark-bar-graph"></i> Compras por fechas</a></li>';
  }
  if ($_SESSION['consultav']==1) {
    echo '<li><a href="ventasfechacliente.php"><i class="bi bi-person-lines-fill"></i> Ventas por cliente</a></li>';
  }
  echo '<li><a href="reportes.php"><i class="bi bi-collection"></i> Centro de reportes</a></li>';
  if ($_SESSION['consultac']==1 || $_SESSION['consultav']==1 || (!empty($_SESSION['acceso']) && $_SESSION['acceso']==1)) {
    echo '<li><a href="resultados.php"><i class="bi bi-graph-up-arrow"></i> Estado de Resultados</a></li>';
  }
  echo '</ul>
        </li>';
}
        ?>
        <?php
$_sEmpresa  = ($_SESSION['acceso']==1);
$_sKardex   = ($_SESSION['acceso']==1 || $_SESSION['almacen']==1 || (!empty($_SESSION['kardex'])  && $_SESSION['kardex']==1));
$_sCuentas  = ($_SESSION['acceso']==1 || (!empty($_SESSION['cuentas']) && $_SESSION['cuentas']==1));
$_sBackup   = ($_SESSION['acceso']==1 || (!empty($_SESSION['backup'])  && $_SESSION['backup']==1));

if ($_sEmpresa || $_sKardex || $_sCuentas || $_sBackup) {
  echo '<li class="treeview">
          <a href="#">
            <i class="bi bi-rocket-takeoff"></i> <span>Gestión Pro</span>
            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
          </a>
          <ul class="treeview-menu">';
  if ($_sEmpresa) echo '<li><a href="empresa.php"><i class="bi bi-building"></i> Empresa</a></li>';
  if ($_sKardex)  echo '<li><a href="procenter.php"><i class="bi bi-graph-up-arrow"></i> Kardex y Alertas</a></li>';
  if ($_sCuentas) echo '<li><a href="cuentas.php"><i class="bi bi-credit-card-2-front"></i> CxC y CxP</a></li>';
  if ($_sBackup)  echo '<li><a href="backup.php"><i class="bi bi-cloud-arrow-up"></i> Backup</a></li>';
  echo '</ul></li>';
}
        ?>
             
        
        
      </ul>
    </section>
    <!-- /.sidebar -->
  </aside>
