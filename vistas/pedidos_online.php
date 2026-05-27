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

    <!-- KPI pedidos -->
    <div class="row" id="kpiPedidos" style="margin-bottom:12px;">
      <div class="col-xs-12 text-center text-muted"><i class="fa fa-spin fa-spinner"></i> Cargando...</div>
    </div>

    <!-- Panel de configuración de tienda -->
    <?php if ($_SESSION['acceso'] == 1): ?>
    <div class="box box-default collapsed-box">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-cog"></i> Configuración de la Tienda Online</h3>
        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i></button>
        </div>
      </div>
      <div class="box-body" style="display:none">
        <form id="formConfigTienda" enctype="multipart/form-data">
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>N° celular Yape</label>
                <input type="text" name="yape_numero" id="cfg_yape_num" class="form-control" placeholder="9XXXXXXXX">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Nombre titular Yape</label>
                <input type="text" name="yape_nombre" id="cfg_yape_nom" class="form-control" placeholder="Nombre">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Imagen QR Yape (PNG/JPG)</label>
                <input type="file" name="yape_qr_imagen" class="form-control" accept="image/*">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Costo envío (S/)</label>
                <input type="number" step="0.5" name="costo_envio" id="cfg_envio" class="form-control" value="5">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Envío gratis desde (S/)</label>
                <input type="number" step="1" name="envio_gratis" id="cfg_envio_gratis" class="form-control" value="80">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>WhatsApp (con código país)</label>
                <input type="text" name="whatsapp" id="cfg_wa" class="form-control" placeholder="51XXXXXXXXX">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Tienda activa</label>
                <select name="tienda_activa" id="cfg_activa" class="form-control">
                  <option value="1">Sí — abierta al público</option>
                  <option value="0">No — en mantenimiento</option>
                </select>
              </div>
            </div>
          </div>
          <button type="button" class="btn btn-success" onclick="guardarConfig()"><i class="fa fa-save"></i> Guardar configuración</button>
          <a href="../tienda/index.php" target="_blank" class="btn btn-default"><i class="fa fa-external-link"></i> Ver tienda</a>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Lista de pedidos -->
    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-shopping-bag"></i> Pedidos Online</h3>
        <div class="box-tools pull-right" style="display:flex;gap:6px;align-items:center">
          <input type="date" id="po_fi" class="form-control input-sm" style="width:130px">
          <input type="date" id="po_ff" class="form-control input-sm" style="width:130px">
          <select id="po_estado" class="form-control input-sm" style="width:140px">
            <option value="TODOS">Todos</option>
            <option value="PENDIENTE">Pendiente</option>
            <option value="CONFIRMADO">Confirmado</option>
            <option value="EN_PREPARACION">En preparación</option>
            <option value="DESPACHADO">Despachado</option>
            <option value="ENTREGADO">Entregado</option>
            <option value="CANCELADO">Cancelado</option>
          </select>
          <button class="btn btn-primary btn-sm" onclick="recargarPedidos()"><i class="fa fa-filter"></i></button>
        </div>
      </div>
      <div class="box-body">
        <table id="tblPedidosOnline" class="table table-bordered table-hover table-striped">
          <thead style="background:#3c8dbc;color:#fff">
            <tr>
              <th>#</th><th>Fecha</th><th>Cliente</th><th>Entrega</th>
              <th>Pago</th><th>Total</th><th>Estado</th><th>Acciones</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

  </section>
</div>

<!-- Modal detalle pedido -->
<div class="modal fade" id="modalDetallePedido" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Detalle del pedido</h4></div>
      <div class="modal-body" id="modalDetalleCuerpo">Cargando...</div>
      <div class="modal-footer">
        <div class="row">
          <div class="col-sm-6">
            <select id="nuevoEstado" class="form-control">
              <option value="PENDIENTE">PENDIENTE</option>
              <option value="CONFIRMADO">CONFIRMADO</option>
              <option value="EN_PREPARACION">EN_PREPARACION</option>
              <option value="DESPACHADO">DESPACHADO</option>
              <option value="ENTREGADO">ENTREGADO</option>
              <option value="CANCELADO">CANCELADO</option>
            </select>
          </div>
          <div class="col-sm-6" style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end">
            <button class="btn btn-success" id="btnCambiarEstadoModal" onclick="aplicarCambioEstado()">
              <i class="fa fa-save"></i> Guardar estado
            </button>
            <button class="btn btn-primary" id="btnConvertirVenta" style="display:none" onclick="convertirAVenta()">
              <i class="fa fa-shopping-cart"></i> Registrar Venta
            </button>
            <button class="btn btn-default" data-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require 'footer.php'; ?>
<script src="scripts/pedidos_online.js"></script>

<?php
} else {
    echo '<div class="content-wrapper"><section class="content"><div class="callout callout-danger"><h4>Sin permiso</h4></div></section></div>';
    require 'footer.php';
}
}
ob_end_flush();
?>
