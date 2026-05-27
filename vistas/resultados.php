<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
  header("Location: login.html");
} else {

require 'header.php';

$puedeVer = (!empty($_SESSION['consultav']) && $_SESSION['consultav']==1)
         || (!empty($_SESSION['consultac']) && $_SESSION['consultac']==1)
         || (!empty($_SESSION['acceso'])    && $_SESSION['acceso']==1);

if ($puedeVer):

require_once "../modelos/Consultas.php";
require_once "../config/Conexion.php";

$fi = isset($_GET['fi']) ? trim($_GET['fi']) : date('Y-m-01');
$ff = isset($_GET['ff']) ? trim($_GET['ff']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fi)) $fi = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ff)) $ff = date('Y-m-d');

$consultas   = new Consultas();
$resumen     = $consultas->resumenResultados($fi, $ff);
$moneda      = obtenerMonedaEmpresaCodigo();

$totalVentas   = $resumen['total_ventas'];
$totalCompras  = $resumen['total_compras'];
$utilidadBruta = $resumen['utilidad_bruta'];
$ingresosCaja  = $resumen['ingresos_caja'];
$egresosCaja   = $resumen['egresos_caja'];
$resultado     = $resumen['resultado'];
$margenBruto   = $totalVentas > 0 ? round(($utilidadBruta / $totalVentas) * 100, 1) : 0;

// Datos para el gráfico
$rsVentas  = $consultas->ventasdiariasrango($fi, $ff);
$rsCompras = $consultas->comprasdiariasrango($fi, $ff);

$mapaV = []; $mapaC = [];
while ($r = $rsVentas->fetch_assoc())  $mapaV[$r['fecha']] = (float)$r['total'];
while ($r = $rsCompras->fetch_assoc()) $mapaC[$r['fecha']] = (float)$r['total'];

$todasFechas = array_unique(array_merge(array_keys($mapaV), array_keys($mapaC)));
sort($todasFechas);

$labelsChart  = [];
$dataVentas   = [];
$dataCompras  = [];
foreach ($todasFechas as $fecha) {
  $labelsChart[] = date('d/m', strtotime($fecha));
  $dataVentas[]  = $mapaV[$fecha]  ?? 0;
  $dataCompras[] = $mapaC[$fecha]  ?? 0;
}

$labelsJson  = json_encode($labelsChart);
$ventasJson  = json_encode($dataVentas);
$comprasJson = json_encode($dataCompras);

?>
<div class="content-wrapper">
  <section class="content">

    <!-- Filtro de fechas -->
    <div class="row" style="margin-bottom:0;">
      <div class="col-md-12">
        <div class="box box-default">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="bi bi-graph-up-arrow"></i> Estado de Resultados</h3>
          </div>
          <div class="box-body">
            <form method="GET" action="resultados.php" class="form-inline">
              <div class="form-group" style="margin-right:10px;">
                <label style="margin-right:5px;">Desde</label>
                <input type="date" name="fi" class="form-control input-sm" value="<?php echo $fi; ?>">
              </div>
              <div class="form-group" style="margin-right:10px;">
                <label style="margin-right:5px;">Hasta</label>
                <input type="date" name="ff" class="form-control input-sm" value="<?php echo $ff; ?>">
              </div>
              <button type="submit" class="btn btn-primary btn-sm">
                <i class="fa fa-search"></i> Consultar
              </button>
              <a href="../reportes/rptResultados.php?fi=<?php echo urlencode($fi); ?>&ff=<?php echo urlencode($ff); ?>"
                 target="_blank" class="btn btn-danger btn-sm" style="margin-left:8px;">
                <i class="fa fa-file-pdf-o"></i> Descargar PDF
              </a>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Tarjetas KPI -->
    <div class="row">
      <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
        <div class="info-box" style="border-left:4px solid #00a65a;">
          <span class="info-box-icon" style="background:#00a65a;"><i class="fa fa-line-chart"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Ventas</span>
            <span class="info-box-number"><?php echo formatearMoneda($totalVentas, $moneda); ?></span>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
        <div class="info-box" style="border-left:4px solid #dd4b39;">
          <span class="info-box-icon" style="background:#dd4b39;"><i class="fa fa-shopping-basket"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Costo de compras</span>
            <span class="info-box-number"><?php echo formatearMoneda($totalCompras, $moneda); ?></span>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
        <div class="info-box" style="border-left:4px solid #0073b7;">
          <span class="info-box-icon" style="background:#0073b7;"><i class="fa fa-balance-scale"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Utilidad bruta <small>(<?php echo $margenBruto; ?>%)</small></span>
            <span class="info-box-number" style="color:<?php echo $utilidadBruta >= 0 ? '#00a65a' : '#dd4b39'; ?>">
              <?php echo formatearMoneda($utilidadBruta, $moneda); ?>
            </span>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
        <div class="info-box" style="border-left:4px solid <?php echo $resultado >= 0 ? '#00a65a' : '#dd4b39'; ?>;">
          <span class="info-box-icon" style="background:<?php echo $resultado >= 0 ? '#00a65a' : '#dd4b39'; ?>;">
            <i class="fa fa-<?php echo $resultado >= 0 ? 'thumbs-up' : 'thumbs-down'; ?>"></i>
          </span>
          <div class="info-box-content">
            <span class="info-box-text">Resultado del período</span>
            <span class="info-box-number" style="color:<?php echo $resultado >= 0 ? '#00a65a' : '#dd4b39'; ?>">
              <?php echo formatearMoneda($resultado, $moneda); ?>
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabla + Gráfico -->
    <div class="row">
      <!-- Estado de Resultados detallado -->
      <div class="col-md-5">
        <div class="box box-primary">
          <div class="box-header with-border">
            <h3 class="box-title">Desglose del período</h3>
            <small class="text-muted" style="margin-left:8px;">
              <?php echo date('d/m/Y', strtotime($fi)); ?> — <?php echo date('d/m/Y', strtotime($ff)); ?>
            </small>
          </div>
          <div class="box-body no-padding">
            <table class="table table-condensed" style="margin-bottom:0;">
              <tbody>
                <tr style="background:#f9f9f9;">
                  <td colspan="2"><strong><i class="fa fa-plus-circle text-green"></i> INGRESOS</strong></td>
                </tr>
                <tr>
                  <td style="padding-left:20px;">Ventas del período</td>
                  <td class="text-right text-green"><strong><?php echo formatearMoneda($totalVentas, $moneda); ?></strong></td>
                </tr>

                <tr style="background:#f9f9f9;">
                  <td colspan="2"><strong><i class="fa fa-minus-circle text-red"></i> COSTO DE VENTAS</strong></td>
                </tr>
                <tr>
                  <td style="padding-left:20px;">Compras / mercadería</td>
                  <td class="text-right text-red"><strong><?php echo formatearMoneda($totalCompras, $moneda); ?></strong></td>
                </tr>

                <tr style="background:#e8f4fd; border-top:2px solid #0073b7;">
                  <td><strong>UTILIDAD BRUTA</strong></td>
                  <td class="text-right">
                    <strong style="color:<?php echo $utilidadBruta >= 0 ? '#00a65a' : '#dd4b39'; ?>; font-size:15px;">
                      <?php echo formatearMoneda($utilidadBruta, $moneda); ?>
                    </strong>
                    <br><small class="text-muted">Margen: <?php echo $margenBruto; ?>%</small>
                  </td>
                </tr>

                <?php if ($ingresosCaja > 0 || $egresosCaja > 0): ?>
                <tr style="background:#f9f9f9;">
                  <td colspan="2"><strong><i class="fa fa-exchange text-blue"></i> MOVIMIENTOS DE CAJA</strong></td>
                </tr>
                <?php if ($ingresosCaja > 0): ?>
                <tr>
                  <td style="padding-left:20px;">Ingresos adicionales</td>
                  <td class="text-right text-green">+ <?php echo formatearMoneda($ingresosCaja, $moneda); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($egresosCaja > 0): ?>
                <tr>
                  <td style="padding-left:20px;">Egresos / Gastos</td>
                  <td class="text-right text-red">- <?php echo formatearMoneda($egresosCaja, $moneda); ?></td>
                </tr>
                <?php endif; ?>
                <?php endif; ?>

                <tr style="background:<?php echo $resultado >= 0 ? '#d4edda' : '#f8d7da'; ?>; border-top:2px solid <?php echo $resultado >= 0 ? '#00a65a' : '#dd4b39'; ?>;">
                  <td><strong style="font-size:14px;">RESULTADO DEL PERÍODO</strong></td>
                  <td class="text-right">
                    <strong style="color:<?php echo $resultado >= 0 ? '#00a65a' : '#dd4b39'; ?>; font-size:16px;">
                      <?php echo formatearMoneda($resultado, $moneda); ?>
                    </strong>
                    <br>
                    <span class="label label-<?php echo $resultado >= 0 ? 'success' : 'danger'; ?>">
                      <?php echo $resultado >= 0 ? 'GANANCIA' : 'PÉRDIDA'; ?>
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Gráfico -->
      <div class="col-md-7">
        <div class="box box-default">
          <div class="box-header with-border">
            <h3 class="box-title">Ventas vs Compras por día</h3>
          </div>
          <div class="box-body">
            <?php if (empty($todasFechas)): ?>
              <div class="text-center text-muted" style="padding:40px 0;">
                <i class="fa fa-bar-chart fa-3x" style="opacity:0.3;"></i>
                <p style="margin-top:10px;">Sin movimientos en el período seleccionado.</p>
              </div>
            <?php else: ?>
              <canvas id="chartResultados" height="200"></canvas>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  </section>
</div>

<?php if (!empty($todasFechas)): ?>
<script src="../public/js/Chart.bundle.min.js"></script>
<script>
(function() {
  var ctx = document.getElementById('chartResultados').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?php echo $labelsJson; ?>,
      datasets: [
        {
          label: 'Ventas',
          data: <?php echo $ventasJson; ?>,
          backgroundColor: 'rgba(0,166,90,0.7)',
          borderColor: '#00a65a',
          borderWidth: 1
        },
        {
          label: 'Compras',
          data: <?php echo $comprasJson; ?>,
          backgroundColor: 'rgba(221,75,57,0.7)',
          borderColor: '#dd4b39',
          borderWidth: 1
        }
      ]
    },
    options: {
      responsive: true,
      scales: {
        yAxes: [{ ticks: { beginAtZero: true } }],
        xAxes: [{ ticks: { maxRotation: 60, minRotation: 30 } }]
      },
      legend: { position: 'top' }
    }
  });
})();
</script>
<?php endif; ?>

<?php
require 'footer.php';
else: ?>
<div class="content-wrapper">
  <section class="content">
    <div class="callout callout-danger">
      <h4>Acceso denegado</h4>
      <p>No tienes permiso para ver esta sección.</p>
    </div>
  </section>
</div>
<?php
require 'footer.php';
endif;
}
ob_end_flush();
?>
