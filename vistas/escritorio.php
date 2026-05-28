<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
  header("Location: login.html");
} else {

require 'header.php';

if ($_SESSION['escritorio'] == 1) {
  require_once "../modelos/Consultas.php";
  $consulta = new Consultas();

  // Alertas de vencimiento (silencia errores si la migración aún no fue ejecutada)
  $cntVencidos = 0;
  $cntVence30  = 0;
  $cntVence60  = 0;
  try {
    require_once "../modelos/Lote.php";
    $loteMdl    = new Lote();
    $cntVencidos = $loteMdl->contarVencidos();
    $cntVence30  = $loteMdl->contarProximosVencer(30);
    $cntVence60  = $loteMdl->contarProximosVencer(60);
  } catch (Throwable $exLote) {}

  $hoy = date("Y-m-d");
  $inicioDefault = date("Y-m-01");

  $fechaInicio = isset($_GET["fecha_inicio"]) ? trim($_GET["fecha_inicio"]) : $inicioDefault;
  $fechaFin = isset($_GET["fecha_fin"]) ? trim($_GET["fecha_fin"]) : $hoy;

  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)) {
    $fechaInicio = $inicioDefault;
  }
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) {
    $fechaFin = $hoy;
  }
  if (strtotime($fechaInicio) > strtotime($fechaFin)) {
    $tmpFecha = $fechaInicio;
    $fechaInicio = $fechaFin;
    $fechaFin = $tmpFecha;
  }

  $rangoTexto = date("d/m/Y", strtotime($fechaInicio)) . " - " . date("d/m/Y", strtotime($fechaFin));

  $rsptac = $consulta->totalcomprarango($fechaInicio, $fechaFin);
  $regc = $rsptac->fetch_object();
  $totalc = $regc ? (float)$regc->total_compra : 0;

  $rsptav = $consulta->totalventarango($fechaInicio, $fechaFin);
  $regv = $rsptav->fetch_object();
  $totalv = $regv ? (float)$regv->total_venta : 0;
  $totalcm = $totalc;
  $totalvm = $totalv;
  $codigoMoneda = function_exists('obtenerMonedaEmpresaCodigo') ? obtenerMonedaEmpresaCodigo() : 'PEN';

  $rskpi = $consulta->kpisgenerales();
  $kpi = $rskpi->fetch_object();
  $articulosActivos = $kpi ? (int)$kpi->articulos_activos : 0;
  $categoriasActivas = $kpi ? (int)$kpi->categorias_activas : 0;
  $clientes = $kpi ? (int)$kpi->clientes : 0;
  $proveedores = $kpi ? (int)$kpi->proveedores : 0;
  $stockTotal = $kpi ? (float)$kpi->stock_total : 0;

  $diasPeriodo = (int)floor((strtotime($fechaFin) - strtotime($fechaInicio)) / 86400) + 1;
  if ($diasPeriodo <= 0) {
    $diasPeriodo = 1;
  }
  $promedioVentas = $totalv / $diasPeriodo;
  $promedioCompras = $totalc / $diasPeriodo;

  $labelsCompras10 = array();
  $dataCompras10 = array();
  $compras10 = $consulta->comprasdiariasrango($fechaInicio, $fechaFin);
  while ($reg = $compras10->fetch_object()) {
    $labelsCompras10[] = date("d/m", strtotime($reg->fecha));
    $dataCompras10[] = round((float)$reg->total, 2);
  }

  $labelsVentas12 = array();
  $dataVentas12 = array();
  $ventas12 = $consulta->ventasmensualesrango($fechaInicio, $fechaFin);
  while ($reg = $ventas12->fetch_object()) {
    $labelsVentas12[] = $reg->fecha;
    $dataVentas12[] = round((float)$reg->total, 2);
  }

  $labelsComparativo = array();
  $compras6Map = array();
  $ventas6Map = array();

  $compras6 = $consulta->comprasmensualesrango($fechaInicio, $fechaFin);
  while ($reg = $compras6->fetch_object()) {
    $periodo = isset($reg->periodo) ? $reg->periodo : '';
    if ($periodo !== '') {
      $compras6Map[$periodo] = round((float)$reg->total, 2);
    }
  }

  $ventas6 = $consulta->ventasmensualesrango($fechaInicio, $fechaFin);
  while ($reg = $ventas6->fetch_object()) {
    $periodo = isset($reg->periodo) ? $reg->periodo : '';
    if ($periodo !== '') {
      $ventas6Map[$periodo] = round((float)$reg->total, 2);
    }
  }

  $periodosComparativo = array();
  $cursorMes = strtotime(date("Y-m-01", strtotime($fechaInicio)));
  $finMes = strtotime(date("Y-m-01", strtotime($fechaFin)));
  while ($cursorMes <= $finMes) {
    $periodosComparativo[] = date("Y-m", $cursorMes);
    $cursorMes = strtotime("+1 month", $cursorMes);
  }

  if (count($periodosComparativo) === 0) {
    $periodosComparativo[] = date("Y-m", strtotime($fechaInicio));
  }

  $dataCompras6 = array();
  $dataVentas6 = array();
  foreach ($periodosComparativo as $periodo) {
    $labelsComparativo[] = date("M Y", strtotime($periodo . "-01"));
    $dataCompras6[] = isset($compras6Map[$periodo]) ? $compras6Map[$periodo] : 0;
    $dataVentas6[] = isset($ventas6Map[$periodo]) ? $ventas6Map[$periodo] : 0;
  }

  $labelsTop = array();
  $dataTop = array();
  $topProductos = $consulta->topproductosvendidosrango($fechaInicio, $fechaFin, 7);
  while ($reg = $topProductos->fetch_object()) {
    $labelsTop[] = $reg->producto;
    $dataTop[] = round((float)$reg->total, 2);
  }

  $labelsCategoria = array();
  $dataCategoria = array();
  $ventasCategoria = $consulta->ventasporcategoriarango($fechaInicio, $fechaFin, 8);
  while ($reg = $ventasCategoria->fetch_object()) {
    $labelsCategoria[] = $reg->categoria;
    $dataCategoria[] = round((float)$reg->total, 2);
  }

  $movimientos = array();
  $rsmov = $consulta->ultimomovimientosrango($fechaInicio, $fechaFin, 10);
  while ($reg = $rsmov->fetch_object()) {
    $movimientos[] = array(
      "tipo" => $reg->tipo,
      "fecha" => date("d/m/Y H:i", strtotime($reg->fecha)),
      "documento" => $reg->documento,
      "persona" => $reg->persona,
      "total" => (float)$reg->total
    );
  }

  // Stock por categoría
  $stockCategorias = array();
  $rsCat = $consulta->stockporcategoria();
  if ($rsCat) {
    while ($reg = $rsCat->fetch_object()) {
      $stockCategorias[] = array(
        "categoria"        => $reg->categoria,
        "total_productos"  => (int)$reg->total_productos,
        "total_stock"      => (int)$reg->total_stock,
        "agotados"         => (int)$reg->agotados,
        "criticos"         => (int)$reg->criticos,
      );
    }
  }
  $labelsCat  = array_column($stockCategorias, 'categoria');
  $dataCatStock = array_column($stockCategorias, 'total_stock');

  // Productos con stock crítico/agotado (≤ stock_minimo)
  $stockProductos = array();
  $rsstock = $consulta->stockporproducto(15);
  if ($rsstock) {
    while ($reg = $rsstock->fetch_object()) {
      $stockProductos[] = array(
        "nombre"       => $reg->nombre,
        "categoria"    => $reg->categoria,
        "stock"        => (int)$reg->stock,
        "stock_minimo" => (int)$reg->stock_minimo,
      );
    }
  }
?>
<div class="content-wrapper">
  <section class="content dashboard-wrap">
    <div class="dashboard-head">
      <h1>Panel Ejecutivo</h1>
      <p>Resumen interactivo de compras, ventas e inventario.</p>
    </div>

    <div class="box dashboard-box dashboard-filter-box">
      <div class="box-body">
        <form method="get" action="escritorio.php" class="row dashboard-filter-form">
          <div class="col-md-3 col-sm-6 col-xs-12 fecha-col">
            <label>Desde</label>
            <input type="date" class="form-control" name="fecha_inicio" value="<?php echo htmlspecialchars($fechaInicio); ?>" max="<?php echo htmlspecialchars($fechaFin); ?>">
          </div>
          <div class="col-md-3 col-sm-6 col-xs-12 fecha-col">
            <label>Hasta</label>
            <input type="date" class="form-control" name="fecha_fin" value="<?php echo htmlspecialchars($fechaFin); ?>" min="<?php echo htmlspecialchars($fechaInicio); ?>">
          </div>
          <div class="col-md-6 col-sm-12 col-xs-12 dashboard-filter-actions">
            <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Aplicar filtros</button>
            <a href="escritorio.php" class="btn btn-default"><i class="fa fa-refresh"></i> Limpiar</a>
            <span class="text-muted" style="margin-left:12px;">Rango activo: <strong><?php echo htmlspecialchars($rangoTexto); ?></strong></span>
          </div>
        </form>
      </div>
    </div>

    <?php if ($cntVencidos > 0 || $cntVence30 > 0): ?>
    <div class="row" style="margin-bottom:10px;">
      <?php if ($cntVencidos > 0): ?>
      <div class="col-md-4 col-sm-6 col-xs-12">
        <a href="vencimientos.php" class="alert alert-danger" style="display:block;padding:10px 16px;text-decoration:none;">
          <strong><i class="fa fa-exclamation-triangle"></i> <?php echo $cntVencidos; ?> lote(s) VENCIDO(S)</strong>
          <span style="float:right;">Revisar &rarr;</span>
        </a>
      </div>
      <?php endif; ?>
      <?php if ($cntVence30 > 0): ?>
      <div class="col-md-4 col-sm-6 col-xs-12">
        <a href="vencimientos.php" class="alert alert-warning" style="display:block;padding:10px 16px;text-decoration:none;">
          <strong><i class="fa fa-clock-o"></i> <?php echo $cntVence30; ?> lote(s) vencen en 30 días</strong>
          <span style="float:right;">Revisar &rarr;</span>
        </a>
      </div>
      <?php endif; ?>
      <?php if ($cntVence60 > $cntVence30): ?>
      <div class="col-md-4 col-sm-6 col-xs-12">
        <a href="vencimientos.php" class="alert alert-info" style="display:block;padding:10px 16px;text-decoration:none;">
          <strong><i class="fa fa-info-circle"></i> <?php echo ($cntVence60 - $cntVence30); ?> lote(s) vencen en 31-60 días</strong>
          <span style="float:right;">Revisar &rarr;</span>
        </a>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="row dashboard-kpis">
      <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
        <a href="venta.php" class="kpi-card kpi-sales">
          <div class="kpi-icon"><i class="fa fa-line-chart"></i></div>
          <div class="kpi-meta">
            <span>Ventas del Periodo</span>
            <strong><?php echo formatearMoneda($totalv, $codigoMoneda); ?></strong>
          </div>
        </a>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
        <a href="ingreso.php" class="kpi-card kpi-buy">
          <div class="kpi-icon"><i class="fa fa-shopping-basket"></i></div>
          <div class="kpi-meta">
            <span>Compras del Periodo</span>
            <strong><?php echo formatearMoneda($totalc, $codigoMoneda); ?></strong>
          </div>
        </a>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
        <a href="#stock-productos" class="kpi-card kpi-stock" style="scroll-behavior:smooth;" onclick="document.getElementById('stock-productos').scrollIntoView({behavior:'smooth'});return false;">
          <div class="kpi-icon"><i class="bi bi-boxes"></i></div>
          <div class="kpi-meta">
            <span>Unidades en Stock</span>
            <strong><?php echo number_format($stockTotal, 0); ?></strong>
          </div>
        </a>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
        <div class="kpi-card kpi-month">
          <div class="kpi-icon"><i class="fa fa-calendar"></i></div>
          <div class="kpi-meta">
            <span>Balance del Periodo</span>
            <strong><?php echo formatearMoneda($totalvm - $totalcm, $codigoMoneda); ?></strong>
          </div>
        </div>
      </div>
    </div>

    <div class="row dashboard-kpis secondary">
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="mini-kpi">
          <label>Promedio Ventas / Dia</label>
          <strong><?php echo formatearMoneda($promedioVentas, $codigoMoneda); ?></strong>
        </div>
      </div>
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="mini-kpi">
          <label>Promedio Compras / Dia</label>
          <strong><?php echo formatearMoneda($promedioCompras, $codigoMoneda); ?></strong>
        </div>
      </div>
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="mini-kpi">
          <label>Clientes</label>
          <strong><?php echo $clientes; ?></strong>
        </div>
      </div>
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="mini-kpi">
          <label>Proveedores / Categorias / Articulos</label>
          <strong><?php echo $proveedores; ?> / <?php echo $categoriasActivas; ?> / <?php echo $articulosActivos; ?></strong>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-8 col-md-8 col-sm-12 col-xs-12">
        <div class="box dashboard-box">
          <div class="box-header with-border">
            <h3 class="box-title">Comparativo Compras vs Ventas (Rango Seleccionado)</h3>
          </div>
          <div class="box-body">
            <canvas id="chartComparativo" height="120"></canvas>
          </div>
        </div>
      </div>
      <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
        <div class="box dashboard-box">
          <div class="box-header with-border">
            <h3 class="box-title">Ventas por Categoria (Rango Seleccionado)</h3>
          </div>
          <div class="box-body">
            <canvas id="chartCategoria" height="170"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
        <div class="box dashboard-box">
          <div class="box-header with-border">
            <h3 class="box-title">Compras por Dia (Rango Seleccionado)</h3>
          </div>
          <div class="box-body">
            <canvas id="chartCompras10" height="150"></canvas>
          </div>
        </div>
      </div>
      <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
        <div class="box dashboard-box">
          <div class="box-header with-border">
            <h3 class="box-title">Ventas por Mes (Rango Seleccionado)</h3>
          </div>
          <div class="box-body">
            <canvas id="chartVentas12" height="150"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
        <div class="box dashboard-box">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="bi bi-trophy" style="color:#d97706;margin-right:6px;"></i>Top Productos Vendidos</h3>
          </div>
          <div class="box-body">
            <canvas id="chartTopProductos" height="180"></canvas>
          </div>
        </div>
      </div>
      <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
        <div class="box dashboard-box">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="bi bi-clock-history" style="color:#0284c7;margin-right:6px;"></i>Últimos Movimientos</h3>
          </div>
          <div class="box-body table-responsive">
            <table class="table table-striped table-condensed">
              <thead>
                <tr>
                  <th>Tipo</th><th>Fecha</th><th>Documento</th><th>Persona</th><th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($movimientos) === 0) { ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:20px;">Sin movimientos en el período.</td></tr>
                <?php } else { foreach ($movimientos as $mov) { ?>
                <tr>
                  <td>
                    <span class="mov-badge <?php echo $mov["tipo"] === "Venta" ? "mov-venta" : "mov-compra"; ?>">
                      <?php echo $mov["tipo"]; ?>
                    </span>
                  </td>
                  <td><?php echo $mov["fecha"]; ?></td>
                  <td><?php echo $mov["documento"]; ?></td>
                  <td><?php echo $mov["persona"]; ?></td>
                  <td><?php echo formatearMoneda($mov["total"], $codigoMoneda); ?></td>
                </tr>
                <?php } } ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ── STOCK: CATEGORÍAS + CRÍTICOS ─────────────── -->
    <div class="row" id="stock-productos">

      <!-- Stock por Categoría — gráfico de barras -->
      <div class="col-lg-7 col-md-7 col-sm-12 col-xs-12">
        <div class="box dashboard-box">
          <div class="box-header with-border">
            <h3 class="box-title">
              <i class="bi bi-bar-chart-steps" style="margin-right:6px;"></i>
              Stock por Categoría
            </h3>
          </div>
          <div class="box-body">
            <?php if (empty($stockCategorias)): ?>
              <p class="text-muted text-center" style="padding:40px 0;">Sin datos de inventario.</p>
            <?php else: ?>
            <canvas id="chartStockCat" height="200"></canvas>
            <!-- Tarjetas resumen de categorías -->
            <div class="cat-stock-grid">
              <?php
              $colores = ['#1D4ED8','#7c3aed','#0284c7','#16a34a','#f59e0b','#dc2626','#0d9488','#db2777'];
              foreach ($stockCategorias as $idx => $cat):
                $color = $colores[$idx % count($colores)];
                $pct   = $stockTotal > 0 ? round(($cat['total_stock'] / $stockTotal) * 100) : 0;
                $alerta = ($cat['agotados'] + $cat['criticos']) > 0;
              ?>
              <div class="cat-stock-card">
                <div class="cat-stock-dot" style="background:<?php echo $color; ?>;"></div>
                <div class="cat-stock-info">
                  <div class="cat-stock-name"><?php echo htmlspecialchars($cat['categoria']); ?></div>
                  <div class="cat-stock-nums">
                    <strong><?php echo number_format($cat['total_stock']); ?></strong>
                    <span>uds · <?php echo $cat['total_productos']; ?> prod. · <?php echo $pct; ?>%</span>
                    <?php if ($alerta): ?>
                    <span class="cat-alerta"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo $cat['agotados'] + $cat['criticos']; ?> alerta(s)</span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Productos con stock crítico -->
      <div class="col-lg-5 col-md-5 col-sm-12 col-xs-12">
        <div class="box dashboard-box">
          <div class="box-header with-border" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;">
            <h3 class="box-title" style="margin:0;">
              <i class="bi bi-exclamation-triangle" style="color:#f59e0b;margin-right:6px;"></i>
              Stock Crítico
            </h3>
            <a href="articulo.php" class="btn btn-xs btn-primary">
              <i class="bi bi-arrow-right-circle"></i> Ver todo
            </a>
          </div>
          <div class="box-body" style="padding:0;min-height:auto;">
            <?php
            $criticos = array_filter($stockProductos, function($p){ return $p['stock'] <= max(1, $p['stock_minimo']); });
            if (empty($criticos)):
            ?>
              <div style="padding:32px 20px;text-align:center;">
                <i class="bi bi-check-circle" style="font-size:32px;color:#16a34a;display:block;margin-bottom:8px;"></i>
                <p style="color:#16a34a;font-weight:600;margin:0;">Todo el inventario está en niveles normales</p>
              </div>
            <?php else: ?>
            <div class="criticos-list">
              <?php foreach (array_values($criticos) as $sp):
                $s  = $sp['stock'];
                $sm = $sp['stock_minimo'];
                if ($s <= 0)       { $cls = 'crit-agotado'; $lbl = 'Agotado'; }
                elseif ($s <= $sm) { $cls = 'crit-critico'; $lbl = 'Crítico'; }
                else               { $cls = 'crit-bajo';    $lbl = 'Bajo'; }
              ?>
              <div class="critico-item">
                <div class="critico-info">
                  <div class="critico-nombre"><?php echo htmlspecialchars($sp['nombre']); ?></div>
                  <div class="critico-cat"><?php echo htmlspecialchars($sp['categoria']); ?></div>
                </div>
                <div class="critico-right">
                  <div class="critico-stock"><?php echo $s; ?></div>
                  <span class="stock-status <?php echo $cls; ?>"><?php echo $lbl; ?></span>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div><!-- /row stock -->

  </section>
</div>
<?php
} else {
 require 'noacceso.php';
}

require 'footer.php';
?>
<script src="../public/js/Chart.bundle.min.js"></script>
<script>
(function () {
  var labelsCompras10 = <?php echo json_encode($labelsCompras10); ?>;
  var dataCompras10 = <?php echo json_encode($dataCompras10); ?>;
  var labelsVentas12 = <?php echo json_encode($labelsVentas12); ?>;
  var dataVentas12 = <?php echo json_encode($dataVentas12); ?>;
  var labelsComparativo = <?php echo json_encode($labelsComparativo); ?>;
  var dataCompras6 = <?php echo json_encode($dataCompras6); ?>;
  var dataVentas6 = <?php echo json_encode($dataVentas6); ?>;
  var labelsTop = <?php echo json_encode($labelsTop); ?>;
  var dataTop = <?php echo json_encode($dataTop); ?>;
  var labelsCategoria = <?php echo json_encode($labelsCategoria); ?>;
  var dataCategoria   = <?php echo json_encode($dataCategoria); ?>;
  var labelsCat       = <?php echo json_encode($labelsCat); ?>;
  var dataCatStock    = <?php echo json_encode($dataCatStock); ?>;

  Chart.defaults.global.animation.duration = 350;
  Chart.defaults.global.defaultFontFamily = '"Trebuchet MS", "Verdana", "Segoe UI", sans-serif';
  Chart.defaults.global.defaultFontColor = '#334155';

  var currencySymbol = window.appCurrencySymbol || <?php echo json_encode(obtenerSimboloMoneda($codigoMoneda)); ?>;
  var moneyTick = function(value){
    return currencySymbol + ' ' + Number(value).toLocaleString('es-PE', {minimumFractionDigits: 0, maximumFractionDigits: 0});
  };

  new Chart(document.getElementById('chartComparativo').getContext('2d'), {
    type: 'line',
    data: {
      labels: labelsComparativo,
      datasets: [{
        label: 'Compras',
        data: dataCompras6,
        borderColor: '#0284c7',
        backgroundColor: 'rgba(2,132,199,0.15)',
        fill: true,
        borderWidth: 2,
        pointRadius: 3
      },{
        label: 'Ventas',
        data: dataVentas6,
        borderColor: '#16a34a',
        backgroundColor: 'rgba(22,163,74,0.15)',
        fill: true,
        borderWidth: 2,
        pointRadius: 3
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      legend: { position: 'top' },
      scales: {
        yAxes: [{ ticks: { beginAtZero: true, callback: moneyTick } }]
      }
    }
  });

  new Chart(document.getElementById('chartCategoria').getContext('2d'), {
    type: 'doughnut',
    data: {
      labels: labelsCategoria.length ? labelsCategoria : ['Sin datos'],
      datasets: [{
        data: dataCategoria.length ? dataCategoria : [1],
        backgroundColor: ['#0ea5e9','#14b8a6','#f59e0b','#16a34a','#8b5cf6','#f97316','#06b6d4','#e11d48']
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      legend: { position: 'bottom' }
    }
  });

  new Chart(document.getElementById('chartCompras10').getContext('2d'), {
    type: 'bar',
    data: {
      labels: labelsCompras10,
      datasets: [{
        label: 'Compras (' + currencySymbol + ')',
        data: dataCompras10,
        backgroundColor: 'rgba(2,132,199,0.22)',
        borderColor: '#0284c7',
        borderWidth: 1.5
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      legend: { display: false },
      scales: {
        yAxes: [{ ticks: { beginAtZero: true, callback: moneyTick } }]
      }
    }
  });

  new Chart(document.getElementById('chartVentas12').getContext('2d'), {
    type: 'bar',
    data: {
      labels: labelsVentas12,
      datasets: [{
        label: 'Ventas (' + currencySymbol + ')',
        data: dataVentas12,
        backgroundColor: 'rgba(22,163,74,0.22)',
        borderColor: '#16a34a',
        borderWidth: 1.5
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      legend: { display: false },
      scales: {
        yAxes: [{ ticks: { beginAtZero: true, callback: moneyTick } }]
      }
    }
  });

  new Chart(document.getElementById('chartTopProductos').getContext('2d'), {
    type: 'horizontalBar',
    data: {
      labels: labelsTop.length ? labelsTop : ['Sin datos'],
      datasets: [{
        label: 'Monto vendido (' + currencySymbol + ')',
        data: dataTop.length ? dataTop : [0],
        backgroundColor: 'rgba(245,158,11,0.26)',
        borderColor: '#d97706',
        borderWidth: 1.5
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      legend: { display: false },
      scales: {
        xAxes: [{ ticks: { beginAtZero: true, callback: moneyTick } }]
      }
    }
  });
  // Gráfico stock por categoría
  var ctxCat = document.getElementById('chartStockCat');
  if (ctxCat && labelsCat.length) {
    var catColors = ['#1D4ED8','#7c3aed','#0284c7','#16a34a','#f59e0b','#dc2626','#0d9488','#db2777'];
    var catBg = labelsCat.map(function(_,i){ return catColors[i % catColors.length] + '30'; });
    var catBorder = labelsCat.map(function(_,i){ return catColors[i % catColors.length]; });
    new Chart(ctxCat.getContext('2d'), {
      type: 'bar',
      data: {
        labels: labelsCat,
        datasets: [{
          label: 'Unidades en stock',
          data: dataCatStock,
          backgroundColor: catBg,
          borderColor: catBorder,
          borderWidth: 2,
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        legend: { display: false },
        scales: {
          yAxes: [{ ticks: { beginAtZero: true } }],
          xAxes: [{ ticks: { maxRotation: 30, minRotation: 0, fontSize: 11 } }]
        }
      }
    });
  }

})();
</script>
<?php
}

ob_end_flush();
?>
