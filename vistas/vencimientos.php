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
        <h1 class="box-title"><i class="fa fa-calendar-times-o text-danger"></i> Control de Vencimientos</h1>
      </div>
      <div class="box-body">

        <!-- Contadores -->
        <div class="row" id="contadoresVenc" style="margin-bottom:16px;">
          <div class="col-xs-12 text-center text-muted"><i class="fa fa-spin fa-spinner"></i> Cargando...</div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" role="tablist" style="margin-bottom:16px;">
          <li role="presentation" class="active">
            <a href="#tabVencidos" data-toggle="tab" role="tab">
              <i class="fa fa-exclamation-triangle text-danger"></i> Vencidos
            </a>
          </li>
          <li role="presentation">
            <a href="#tabProximos" data-toggle="tab" role="tab">
              <i class="fa fa-clock-o text-warning"></i> Próximos a vencer
            </a>
          </li>
        </ul>

        <div class="tab-content">

          <!-- Tab vencidos -->
          <div class="tab-pane active" id="tabVencidos" role="tabpanel">
            <table id="tblVencidos" class="table table-striped table-bordered table-condensed table-hover" style="width:100%">
              <thead>
                <th>Código</th>
                <th>Artículo</th>
                <th>N° Lote</th>
                <th>F. Vencimiento</th>
                <th>Días vencido</th>
                <th>Stock lote</th>
                <th>Estado</th>
              </thead>
              <tbody></tbody>
              <tfoot>
                <th>Código</th>
                <th>Artículo</th>
                <th>N° Lote</th>
                <th>F. Vencimiento</th>
                <th>Días vencido</th>
                <th>Stock lote</th>
                <th>Estado</th>
              </tfoot>
            </table>
          </div>

          <!-- Tab próximos -->
          <div class="tab-pane" id="tabProximos" role="tabpanel">
            <div style="margin-bottom:12px;">
              <label>Mostrar lotes que vencen en los próximos</label>
              <select id="selDiasVenc" class="form-control" style="display:inline-block;width:auto;margin:0 8px;">
                <option value="30">30 días</option>
                <option value="60">60 días</option>
                <option value="90">90 días</option>
              </select>
            </div>
            <table id="tblProximos" class="table table-striped table-bordered table-condensed table-hover" style="width:100%">
              <thead>
                <th>Código</th>
                <th>Artículo</th>
                <th>N° Lote</th>
                <th>F. Vencimiento</th>
                <th>Días restantes</th>
                <th>Stock lote</th>
                <th>Urgencia</th>
              </thead>
              <tbody></tbody>
              <tfoot>
                <th>Código</th>
                <th>Artículo</th>
                <th>N° Lote</th>
                <th>F. Vencimiento</th>
                <th>Días restantes</th>
                <th>Stock lote</th>
                <th>Urgencia</th>
              </tfoot>
            </table>
          </div>

        </div>
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
<script src="scripts/vencimientos.js"></script>
<?php
}

ob_end_flush();
?>
