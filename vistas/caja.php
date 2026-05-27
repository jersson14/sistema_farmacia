<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
  header("Location: login.html");
}else{
$moduloCajaHabilitado = true;
if (!$moduloCajaHabilitado) {
  header("Location: escritorio.php");
  exit;
}

require 'header.php';
if ($_SESSION['compras']==1 || $_SESSION['ventas']==1) {
?>
<div class="content-wrapper">
  <section class="content">

    <!-- ===== SECCIÓN: SIN CAJA ABIERTA ===== -->
    <div id="seccionCajaCerrada" style="display:none">
      <div class="row">
        <div class="col-md-6 col-md-offset-3">
          <div class="box box-warning">
            <div class="box-header with-border">
              <h3 class="box-title"><i class="fa fa-lock"></i> No tienes una caja abierta</h3>
            </div>
            <div class="box-body">
              <p class="text-muted">Ingresa el monto inicial para comenzar a atender.</p>
              <form id="formAbrirCaja">
                <div class="form-group">
                  <label>Monto inicial:</label>
                  <div class="input-group">
                    <span class="input-group-addon">S/</span>
                    <input type="number" step="0.01" min="0" class="form-control input-lg"
                           name="monto_apertura" id="monto_apertura" placeholder="0.00" required autofocus>
                  </div>
                </div>
                <div class="form-group">
                  <label>Observacion (opcional):</label>
                  <input type="text" class="form-control" name="observacion" id="obs_apertura"
                         placeholder="Ej: Turno mañana">
                </div>
                <button class="btn btn-success btn-lg btn-block" type="submit">
                  <i class="fa fa-unlock"></i>&nbsp; ABRIR CAJA
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-12">
          <div class="box box-default">
            <div class="box-header with-border">
              <h3 class="box-title"><i class="fa fa-history"></i> Historial de cajas</h3>
            </div>
            <div class="box-body table-responsive">
              <table id="tblhistcaja" class="table table-striped table-bordered table-condensed table-hover">
                <thead>
                  <th>ID</th><th>Apertura</th><th>Cierre</th><th>Cajero</th>
                  <th>Monto apertura</th><th>Ingresos</th><th>Egresos</th>
                  <th>Total sistema</th><th>Total real</th><th>Diferencia</th>
                  <th>Estado</th><th>PDF</th>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div><!-- /seccionCajaCerrada -->

    <!-- ===== SECCIÓN: CAJA ABIERTA ===== -->
    <div id="seccionCajaAbierta" style="display:none">

      <!-- Resumen de la caja -->
      <div class="row">
        <div class="col-md-12">
          <div class="box box-success">
            <div class="box-header with-border">
              <h3 class="box-title"><i class="fa fa-unlock"></i> <span id="lblEstadoCaja">CAJA ABIERTA</span></h3>
              <div class="box-tools pull-right">
                <span class="text-muted" id="lblFechaApertura"></span>
              </div>
            </div>
            <div class="box-body">
              <!-- Fila 1: Ventas por método -->
              <div class="row text-center" style="margin-bottom:12px">
                <div class="col-md-2 col-sm-4 col-xs-6">
                  <p class="text-muted" style="margin-bottom:2px;font-size:11px"><i class="fa fa-lock-open"></i> Apertura</p>
                  <h4 id="cajApertura">S/ 0.00</h4>
                </div>
                <div class="col-md-2 col-sm-4 col-xs-6">
                  <p class="text-muted" style="margin-bottom:2px;font-size:11px"><i class="fa fa-money"></i> Ventas Efectivo</p>
                  <h4 id="cajVentas" class="text-green">S/ 0.00</h4>
                </div>
                <div class="col-md-2 col-sm-4 col-xs-6">
                  <p class="text-muted" style="margin-bottom:2px;font-size:11px"><i class="fa fa-mobile"></i> Ventas Yape/Plin</p>
                  <h4 id="cajYape" class="text-purple">S/ 0.00</h4>
                </div>
                <div class="col-md-2 col-sm-4 col-xs-6">
                  <p class="text-muted" style="margin-bottom:2px;font-size:11px"><i class="fa fa-credit-card"></i> Ventas Tarjeta</p>
                  <h4 id="cajTarjeta" class="text-light-blue">S/ 0.00</h4>
                </div>
                <div class="col-md-2 col-sm-4 col-xs-6">
                  <p class="text-muted" style="margin-bottom:2px;font-size:11px"><i class="fa fa-plus"></i> Ingresos extra</p>
                  <h4 id="cajIngresos" class="text-aqua">S/ 0.00</h4>
                </div>
                <div class="col-md-2 col-sm-4 col-xs-6">
                  <p class="text-muted" style="margin-bottom:2px;font-size:11px"><i class="fa fa-minus"></i> Egresos</p>
                  <h4 id="cajEgresos" class="text-red">S/ 0.00</h4>
                </div>
              </div>
              <!-- Fila 2: Totales -->
              <div class="row text-center" style="border-top:1px solid #ddd;padding-top:10px">
                <div class="col-md-6 col-xs-12" style="border-right:2px solid #ddd">
                  <p class="text-muted" style="margin-bottom:2px;font-size:11px">EFECTIVO ESPERADO EN CAJA</p>
                  <h3 id="cajSistema" class="text-green"><strong>S/ 0.00</strong></h3>
                </div>
                <div class="col-md-6 col-xs-12">
                  <p class="text-muted" style="margin-bottom:2px;font-size:11px">TOTAL DIGITAL (Yape + Tarjeta)</p>
                  <h3 id="cajDigital" class="text-light-blue"><strong>S/ 0.00</strong></h3>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Acciones: movimiento rápido + cerrar caja -->
      <div class="row">
        <div class="col-md-5">
          <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title"><i class="fa fa-plus"></i> Agregar movimiento</h3>
            </div>
            <div class="box-body">
              <form id="formMovimientoCaja" class="form-horizontal">
                <div class="form-group">
                  <label class="col-sm-3 control-label">Tipo</label>
                  <div class="col-sm-9">
                    <select class="form-control" name="tipo" id="mov_tipo">
                      <option value="INGRESO">INGRESO</option>
                      <option value="EGRESO">EGRESO</option>
                    </select>
                  </div>
                </div>
                <div class="form-group">
                  <label class="col-sm-3 control-label">Concepto</label>
                  <div class="col-sm-9">
                    <input type="text" class="form-control" name="concepto" id="mov_concepto"
                           placeholder="Descripcion del movimiento" required>
                  </div>
                </div>
                <div class="form-group">
                  <label class="col-sm-3 control-label">Monto</label>
                  <div class="col-sm-9">
                    <div class="input-group">
                      <span class="input-group-addon">S/</span>
                      <input type="number" step="0.01" min="0.01" class="form-control"
                             name="monto" id="mov_monto" placeholder="0.00" required>
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <div class="col-sm-offset-3 col-sm-9">
                    <button class="btn btn-primary btn-block" type="submit">
                      <i class="fa fa-plus-circle"></i> Registrar movimiento
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="col-md-7">
          <div class="box box-danger">
            <div class="box-header with-border">
              <h3 class="box-title"><i class="fa fa-lock"></i> Cerrar caja</h3>
            </div>
            <div class="box-body">
              <p class="text-muted">Cuenta el dinero físico en caja e ingresa el total real.</p>
              <form id="formCerrarCaja" class="form-horizontal">
                <div class="form-group">
                  <label class="col-sm-4 control-label">Total contado (S/)</label>
                  <div class="col-sm-8">
                    <div class="input-group">
                      <span class="input-group-addon">S/</span>
                      <input type="number" step="0.01" min="0" class="form-control input-lg"
                             name="monto_cierre_real" id="monto_cierre_real"
                             placeholder="Monto real contado" required>
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label class="col-sm-4 control-label">Observacion</label>
                  <div class="col-sm-8">
                    <input type="text" class="form-control" name="observacion" id="obs_cierre"
                           placeholder="Obs. cierre">
                  </div>
                </div>
                <div class="form-group">
                  <div class="col-sm-offset-4 col-sm-8">
                    <button class="btn btn-danger btn-lg btn-block" type="submit"
                            onclick="return confirm('¿Confirmas el cierre de caja?')">
                      <i class="fa fa-lock"></i> CERRAR CAJA
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <!-- Movimientos de la caja abierta -->
      <div class="row">
        <div class="col-md-12">
          <div class="box box-default">
            <div class="box-header with-border">
              <h3 class="box-title"><i class="fa fa-list"></i> Movimientos de la caja actual</h3>
            </div>
            <div class="box-body table-responsive">
              <table id="tblmovcaja" class="table table-striped table-bordered table-condensed table-hover" style="width:100%">
                <thead><th>Fecha</th><th>Tipo</th><th>Concepto</th><th>Monto</th><th>Usuario</th></thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Historial (también visible cuando hay caja abierta) -->
      <div class="row">
        <div class="col-md-12">
          <div class="box box-default collapsed-box">
            <div class="box-header with-border">
              <h3 class="box-title"><i class="fa fa-history"></i> Historial de cajas anteriores</h3>
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse">
                  <i class="fa fa-plus"></i>
                </button>
              </div>
            </div>
            <div class="box-body table-responsive" style="display:none">
              <table id="tblhistcaja2" class="table table-striped table-bordered table-condensed table-hover">
                <thead>
                  <th>ID</th><th>Apertura</th><th>Cierre</th><th>Cajero</th>
                  <th>Monto apertura</th><th>Ingresos</th><th>Egresos</th>
                  <th>Total sistema</th><th>Total real</th><th>Diferencia</th>
                  <th>Estado</th><th>PDF</th>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /seccionCajaAbierta -->

  </section>
</div>
<?php
}else{
  require 'noacceso.php';
}
require 'footer.php';
?>
<script src="scripts/caja.js"></script>
<?php
}
ob_end_flush();
?>
