<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
    header("Location: login.html");
} else {

require 'header.php';

if ($_SESSION['ventas'] == 1 || $_SESSION['acceso'] == 1) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="box box-danger">
      <div class="box-header with-border">
        <h1 class="box-title"><i class="fa fa-shield text-danger"></i> Libro de Control de Psicotrópicos y Estupefacientes</h1>
        <div class="box-tools pull-right">
          <a id="btnExportPdf" href="#" target="_blank" class="btn btn-danger btn-sm">
            <i class="fa fa-file-pdf-o"></i> Exportar PDF
          </a>
        </div>
      </div>
      <div class="box-body">

        <!-- Filtro de fechas -->
        <div class="row" style="margin-bottom:12px;">
          <div class="col-xs-12 col-sm-6 col-md-4">
            <div class="input-group">
              <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
              <input type="date" id="ce_fecha_inicio" class="form-control" placeholder="Desde">
              <span class="input-group-addon">—</span>
              <input type="date" id="ce_fecha_fin" class="form-control" placeholder="Hasta">
            </div>
          </div>
          <div class="col-xs-12 col-sm-auto" style="margin-top:4px;">
            <button id="btnFiltrarCE" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filtrar</button>
            <button id="btnLimpiarCE" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Limpiar</button>
          </div>
        </div>

        <!-- Tabla libro de control -->
        <div class="table-responsive">
          <table id="tblControlEspecial" class="table table-bordered table-hover table-striped" style="width:100%">
            <thead style="background-color:#d9534f;color:#fff;">
              <tr>
                <th>Fecha/Hora</th>
                <th>Medicamento</th>
                <th>Paciente</th>
                <th>Cant.</th>
                <th>N° Lote</th>
                <th>Venc.</th>
                <th>Médico</th>
                <th>Colegiatura</th>
                <th>QF Dispensador</th>
                <th>Venta</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

      </div><!-- /.box-body -->
    </div><!-- /.box -->
  </section>
</div><!-- /.content-wrapper -->

<?php require 'footer.php'; ?>
<script src="scripts/control_especial.js"></script>

<?php
} else {
    echo '<div class="content-wrapper"><section class="content"><div class="callout callout-danger"><h4>Sin permiso</h4><p>No tienes acceso a este modulo.</p></div></section></div>';
    require 'footer.php';
}
}
ob_end_flush();
?>
