<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
    header("Location: login.html");
} else {

require 'header.php';

if ($_SESSION['almacen'] == 1) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="box">
      <div class="box-header with-border">
        <h1 class="box-title"><i class="bi bi-layers text-primary"></i> Lotes por Producto</h1>
        <div class="box-tools pull-right">
          <a href="vencimientos.php" class="btn btn-warning btn-sm">
            <i class="fa fa-calendar-times-o"></i> Ver Vencimientos
          </a>
        </div>
      </div>
      <div class="box-body">

        <!-- Filtro rápido de estado -->
        <div style="margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
          <label style="margin:0;font-weight:600;font-size:13px;">Filtrar:</label>
          <button class="btn btn-xs btn-default btn-filtro active" data-filtro="">Todos</button>
          <button class="btn btn-xs btn-success btn-filtro" data-filtro="VIGENTE">Vigentes</button>
          <button class="btn btn-xs btn-warning btn-filtro" data-filtro="VENCE">Por vencer</button>
          <button class="btn btn-xs btn-danger btn-filtro" data-filtro="VENCIDO">Vencidos</button>
          <button class="btn btn-xs btn-default btn-filtro" data-filtro="AGOTADO">Agotados</button>
        </div>

        <table id="tblLotes" class="table table-striped table-bordered table-condensed table-hover" style="width:100%">
          <thead>
            <tr>
              <th>Código</th>
              <th>Producto</th>
              <th>N° Lote</th>
              <th>F. Fabricación</th>
              <th>F. Vencimiento</th>
              <th>Stock Inicial</th>
              <th>Stock Actual</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody></tbody>
          <tfoot>
            <tr>
              <th>Código</th>
              <th>Producto</th>
              <th>N° Lote</th>
              <th>F. Fabricación</th>
              <th>F. Vencimiento</th>
              <th>Stock Inicial</th>
              <th>Stock Actual</th>
              <th>Estado</th>
            </tr>
          </tfoot>
        </table>

      </div>
    </div>
  </section>
</div>
<?php
} else {
    require 'noacceso.php';
}

require 'footer.php';
?>
<script src="scripts/lotes.js"></script>
<?php
}
ob_end_flush();
?>
