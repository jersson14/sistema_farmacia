<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
  header("Location: login.html");
}else{

require 'header.php';

if ($_SESSION['ventas']==1) {
 ?>
    <div class="content-wrapper" id="mainCW">

    <!-- ══════════════ RESUMEN DE PAGOS ══════════════ -->
    <div class="row" id="resumenPagosVenta" style="padding:10px 15px 0">
      <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box"><span class="info-box-icon bg-green"><i class="fa fa-money"></i></span>
          <div class="info-box-content"><span class="info-box-text">Efectivo</span>
            <span class="info-box-number" id="vResEfectivo">S/ 0.00</span></div></div></div>
      <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box"><span class="info-box-icon bg-purple"><i class="fa fa-mobile"></i></span>
          <div class="info-box-content"><span class="info-box-text">Yape / Plin</span>
            <span class="info-box-number" id="vResYape">S/ 0.00</span></div></div></div>
      <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box"><span class="info-box-icon bg-light-blue"><i class="fa fa-credit-card"></i></span>
          <div class="info-box-content"><span class="info-box-text">Tarjeta</span>
            <span class="info-box-number" id="vResTarjeta">S/ 0.00</span></div></div></div>
      <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box"><span class="info-box-icon bg-yellow"><i class="fa fa-bar-chart"></i></span>
          <div class="info-box-content"><span class="info-box-text">Total ventas</span>
            <span class="info-box-number" id="vResTotal">S/ 0.00</span></div></div></div>
    </div>

    <!-- ══════════════ HISTORIAL DE VENTAS ══════════════ -->
    <section class="content" id="listadoregistros">
      <div class="row">
        <div class="col-md-12">
          <div class="box">
            <div class="box-header with-border">
              <h1 class="box-title">
                Ventas
                <button class="btn btn-success" onclick="mostrarform(true, true)" id="btnagregar">
                  <i class="fa fa-plus-circle"></i> Nueva Venta
                </button>
              </h1>
            </div>
            <div class="box-body">
              <div class="row" style="margin-bottom:10px">
                <div class="col-md-3 col-sm-6 col-xs-12">
                  <label>Desde</label>
                  <input type="date" id="filtro_venta_inicio" class="form-control">
                </div>
                <div class="col-md-3 col-sm-6 col-xs-12">
                  <label>Hasta</label>
                  <input type="date" id="filtro_venta_fin" class="form-control">
                </div>
                <div class="col-md-6 col-sm-12 col-xs-12" style="padding-top:25px">
                  <button type="button" class="btn btn-primary" id="btnFiltrarVenta">
                    <i class="fa fa-filter"></i> Filtrar
                  </button>
                  <button type="button" class="btn btn-default" id="btnLimpiarFiltroVenta">
                    <i class="fa fa-eraser"></i> Limpiar
                  </button>
                </div>
              </div>
              <div class="table-responsive">
                <table id="tbllistado" class="table table-striped table-bordered table-condensed table-hover">
                  <thead>
                    <th>Opciones</th><th>Fecha</th><th>Cliente</th><th>Usuario</th>
                    <th>Documento</th><th>Número</th><th>Total</th><th>Método</th><th>Estado</th>
                  </thead>
                  <tbody></tbody>
                  <tfoot>
                    <th>Opciones</th><th>Fecha</th><th>Cliente</th><th>Usuario</th>
                    <th>Documento</th><th>Número</th><th>Total</th><th>Método</th><th>Estado</th>
                  </tfoot>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════════ POS — NUEVA VENTA ══════════════ -->
    <div class="pos-wrapper" id="formularioregistros" style="display:none">
      <form id="formulario" method="POST" action="">

        <!-- ── Campos ocultos del formulario ─────────────────── -->
        <input type="hidden" name="idventa"    id="idventa">
        <input type="hidden" name="total_venta" id="total_venta">
        <input type="hidden" name="monto_efectivo" id="monto_efectivo" value="0">
        <input type="hidden" name="monto_tarjeta"  id="monto_tarjeta"  value="0">
        <input type="hidden" name="monto_digital"  id="monto_digital"  value="0">
        <input type="hidden" name="seguro_nombre"  id="seguro_nombre"  value="">
        <input type="hidden" name="seguro_copago"  id="seguro_copago"  value="0">
        <input type="hidden" name="seguro_nro_autorizacion" id="seguro_nro_autorizacion" value="">
        <input type="hidden" name="tipo_comprobante" id="tipo_comprobante" value="Boleta">
        <input type="hidden" name="serie_comprobante" id="serie_comprobante">
        <input type="hidden" name="num_comprobante"  id="num_comprobante">
        <input type="hidden" name="impuesto" id="impuesto" value="0">
        <input type="hidden" name="metodo_pago" id="metodo_pago" value="EFECTIVO">
        <input type="hidden" name="fecha_hora" id="fecha_hora">

        <!-- ── Tabla de detalles oculta (datos del carrito para POST) ── -->
        <div style="display:none">
          <table id="detalles">
            <tbody></tbody>
            <tfoot>
              <tr><th colspan="7"></th>
                <th><span id="total">S/ 0.00</span></th>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- ── Barra superior del POS ───────────────────────── -->
        <div class="pos-topbar">
          <div class="pos-search-section">
            <div class="pos-search-box">
              <i class="fa fa-search pos-search-icon"></i>
              <input type="text" id="posSearchInput" class="pos-search-input"
                     placeholder="Buscar medicamento por nombre o código... (F2)">
            </div>
            <button type="button" class="pos-scan-btn" title="Escanear código de barras (Ctrl+B)"
                    onclick="$('#codigo_rapido').focus()">
              <i class="fa fa-barcode"></i>
            </button>
            <input type="text" id="codigo_rapido" class="pos-scan-hidden" placeholder="Escanear">
          </div>
          <div class="pos-topbar-actions">
            <button type="button" class="pos-historial-btn" onclick="cancelarform()">
              <i class="fa fa-list"></i> Historial
            </button>
          </div>
        </div>

        <!-- ── Barra de categorías ───────────────────────────── -->
        <div class="pos-cat-bar" id="posCatBar">
          <button type="button" class="pos-cat-pill active" data-cat="">
            <i class="fa fa-th-large"></i> Todos
          </button>
        </div>

        <!-- ── Cuerpo del POS (grid + carrito) ──────────────── -->
        <div class="pos-body">

          <!-- LEFT: Grid de productos -->
          <div class="pos-productos-panel">
            <div class="pos-grid" id="posProductGrid">
              <div class="pos-grid-loading">
                <i class="fa fa-spinner fa-spin fa-2x"></i>
                <p>Cargando productos...</p>
              </div>
            </div>
            <div class="pos-grid-footer" id="posGridFooter" style="display:none"></div>
          </div>

          <!-- RIGHT: Carrito -->
          <div class="pos-carrito-panel" id="posCarritoPanel">
            <!-- Handle móvil -->
            <div class="pos-mobile-handle" id="posMobileHandle">
              <div class="pos-mobile-drag-bar"></div>
              <button type="button" class="pos-mobile-close-btn" onclick="cerrarCarritoMobile()">
                <i class="fa fa-chevron-down"></i>
              </button>
            </div>

            <!-- Cliente + tipo comprobante -->
            <div class="pos-carrito-header">
              <div class="pos-cliente-row">
                <div class="pos-dni-wrap">
                  <input type="text" id="posDniBuscar" class="pos-dni-input"
                         placeholder="DNI" maxlength="8" autocomplete="off"
                         title="Buscar cliente por DNI y Enter">
                  <button type="button" class="pos-dni-btn" id="btnBuscarDni" title="Buscar por DNI">
                    <i class="fa fa-search"></i>
                  </button>
                </div>
                <select name="idcliente" id="idcliente"
                        class="selectpicker" data-live-search="true"
                        data-none-selected-text="Consumidor Final">
                </select>
                <button type="button" class="pos-icon-btn" title="Nuevo cliente"
                        data-toggle="modal" data-target="#modalClienteVenta">
                  <i class="fa fa-user-plus"></i>
                </button>
                <button type="button" class="pos-icon-btn pos-icon-btn-info"
                        title="Perfil farmacológico" onclick="verPerfilPaciente()">
                  <i class="fa fa-heartbeat"></i>
                </button>
              </div>
              <div class="pos-doc-toggle">
                <button type="button" class="pos-doc-btn active" data-tipo="Boleta">Boleta</button>
                <button type="button" class="pos-doc-btn" data-tipo="Factura">Factura</button>
                <button type="button" class="pos-doc-btn" data-tipo="Ticket">Ticket</button>
                <span class="pos-serie-badge" id="posSerieBadge">···</span>
              </div>
            </div>

            <!-- Items del carrito (renderizados por JS) -->
            <div class="pos-carrito-items" id="posCarritoItems">
              <div class="pos-carrito-empty" id="posCarritoEmpty">
                <i class="fa fa-shopping-basket fa-3x"></i>
                <p>Sin productos en el carrito</p>
                <small>Selecciona un medicamento o escanea su código</small>
              </div>
            </div>

            <!-- Totales -->
            <div class="pos-totales">
              <div class="pos-total-row">
                <span>Subtotal</span>
                <span id="posTotalSinIgv">S/ 0.00</span>
              </div>
              <div class="pos-total-row pos-igv-row" id="posIgvRow" style="display:none">
                <span id="posIgvLabel">IGV (18%)</span>
                <span id="posIgvMonto">S/ 0.00</span>
              </div>
              <div class="pos-total-row pos-grand-total">
                <span>TOTAL</span>
                <span id="posTotalFinal">S/ 0.00</span>
              </div>
            </div>

            <!-- Métodos de pago -->
            <div class="pos-pagos">
              <div class="pos-metodos">
                <button type="button" class="pos-metodo-btn active" data-metodo="EFECTIVO">
                  <i class="fa fa-money"></i><br><small>Efectivo</small>
                </button>
                <button type="button" class="pos-metodo-btn" data-metodo="YAPE">
                  <i class="fa fa-mobile"></i><br><small>Yape/Plin</small>
                </button>
                <button type="button" class="pos-metodo-btn" data-metodo="TARJETA">
                  <i class="fa fa-credit-card"></i><br><small>Tarjeta</small>
                </button>
                <button type="button" class="pos-metodo-btn" data-metodo="MIXTO">
                  <i class="fa fa-exchange"></i><br><small>Mixto</small>
                </button>
              </div>
              <!-- Montos para pago mixto -->
              <div id="seccionMixto" style="display:none" class="pos-mixto-inputs">
                <div class="pos-mixto-row">
                  <label>Efectivo</label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-addon">S/</span>
                    <input type="number" step="0.01" min="0" class="form-control vis-monto" id="vis_efectivo" value="0">
                  </div>
                </div>
                <div class="pos-mixto-row">
                  <label>Tarjeta</label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-addon">S/</span>
                    <input type="number" step="0.01" min="0" class="form-control vis-monto" id="vis_tarjeta" value="0">
                  </div>
                </div>
                <div class="pos-mixto-row">
                  <label>Yape/Plin</label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-addon">S/</span>
                    <input type="number" step="0.01" min="0" class="form-control vis-monto" id="vis_digital" value="0">
                  </div>
                </div>
              </div>
            </div>

            <!-- Botón COBRAR -->
            <div class="pos-cobrar-section">
              <button type="submit" class="btn-pos-cobrar" id="btnGuardar" style="display:none">
                <span class="btn-cobrar-label">
                  <i class="fa fa-check-circle"></i> COBRAR
                  <small style="font-weight:400;font-size:11px;opacity:.8">(F10)</small>
                </span>
                <span class="btn-cobrar-total" id="btnCobrarTotal">S/ 0.00</span>
              </button>
              <button type="button" class="btn-pos-cancelar" id="btnCancelar" onclick="cancelarform()">
                <i class="fa fa-arrow-left"></i> Cancelar venta
              </button>
            </div>

          </div><!-- /pos-carrito-panel -->
        </div><!-- /pos-body -->

      </form>
    </div><!-- /pos-wrapper -->

    <!-- Botón flotante carrito (solo móvil) -->
    <button type="button" id="posFloatCartBtn" class="pos-float-cart-btn" onclick="abrirCarritoMobile()">
      <span class="pos-float-badge" id="posFloatBadge">0</span>
      <i class="fa fa-shopping-basket"></i>
      <span class="pos-float-label">Ver carrito</span>
      <span class="pos-float-total" id="posFloatTotal">S/ 0.00</span>
    </button>
    <!-- Backdrop carrito móvil -->
    <div class="pos-carrito-backdrop" id="posCarritoBackdrop" onclick="cerrarCarritoMobile()"></div>

    </div><!-- /content-wrapper -->

  <!-- ══════════ MODALES (fuera del content-wrapper) ══════════ -->

  <!-- Modal Perfil Farmacológico -->
  <div class="modal fade" id="modalPerfilPaciente" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header" style="background:#1a7abf;color:#fff">
          <button type="button" class="close" data-dismiss="modal" style="color:#fff">&times;</button>
          <h4 class="modal-title"><i class="fa fa-heartbeat"></i> Perfil Farmacológico del Paciente</h4>
        </div>
        <div class="modal-body">
          <div id="perfilMsg" class="alert" style="display:none"></div>
          <p class="text-muted" id="perfilNombrePaciente"></p>
          <div class="form-group">
            <label><i class="fa fa-exclamation-triangle text-danger"></i> <strong>Alergias conocidas</strong></label>
            <textarea class="form-control" id="pp_alergias" rows="2" placeholder="Ej: Penicilina, AAS..."></textarea>
          </div>
          <div class="form-group">
            <label><i class="fa fa-stethoscope text-warning"></i> Condiciones crónicas</label>
            <textarea class="form-control" id="pp_condiciones" rows="2" placeholder="Ej: Diabetes, hipertensión..."></textarea>
          </div>
          <div class="form-group">
            <label><i class="fa fa-medkit text-info"></i> Medicamentos de uso permanente</label>
            <textarea class="form-control" id="pp_medicamentos" rows="2" placeholder="Ej: Metformina 850mg c/12h..."></textarea>
          </div>
          <div class="form-group">
            <label>Otras observaciones</label>
            <textarea class="form-control" id="pp_observaciones" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
          <button type="button" class="btn btn-primary" id="btnGuardarPerfil" onclick="guardarPerfilPaciente()">
            <i class="fa fa-save"></i> Guardar perfil
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Catálogo (fallback con F2) -->
  <div class="modal fade modal-catalog" id="myModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header catalog-modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title"><i class="fa fa-shopping-basket"></i> Catálogo de Artículos</h4>
          <p class="catalog-subtitle">Selecciona productos para agregar a la venta</p>
        </div>
        <div class="modal-body">
          <div class="catalog-info-row">
            <div class="catalog-tip"><i class="fa fa-lightbulb-o"></i> Si ya agregaste un producto, seleccionarlo incrementa la cantidad.</div>
            <div class="catalog-counter"><span id="ventasItemsSeleccionados">0</span> items en la venta</div>
          </div>
          <table id="tblarticulos" class="table table-striped table-bordered table-condensed table-hover" style="width:100%">
            <thead>
              <th>Agregar</th><th>Nombre</th><th>Categoría</th><th>Unidad</th>
              <th>Código</th><th>Stock</th><th>Precio</th><th>Imagen</th>
            </thead>
            <tbody></tbody>
            <tfoot>
              <th>Agregar</th><th>Nombre</th><th>Categoría</th><th>Unidad</th>
              <th>Código</th><th>Stock</th><th>Precio</th><th>Imagen</th>
            </tfoot>
          </table>
        </div>
        <div class="modal-footer">
          <button class="btn btn-default" type="button" data-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Receta Médica -->
  <div class="modal fade" id="modalReceta" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header" style="background:#ebf5fb;border-bottom:2px solid #2980b9;">
          <h4 class="modal-title">
            <i class="fa fa-file-text-o text-primary"></i> Receta médica requerida (Rx)
          </h4>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning" id="rxAlertaProductos" style="margin-bottom:14px;"></div>
          <div class="form-group">
            <label>Nombre del médico (*)</label>
            <input type="text" class="form-control" id="rx_nombre_medico" maxlength="150" placeholder="Nombre completo del médico prescriptor">
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>N° Colegiatura / CMP (*)</label>
                <input type="text" class="form-control" id="rx_colegiatura" maxlength="50" placeholder="Ej: CMP 12345">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Tipo de receta</label>
                <select class="form-control" id="rx_tipo_receta">
                  <option value="SIMPLE">Simple</option>
                  <option value="ESPECIAL">Especial (benzodiazepinas)</option>
                  <option value="RETENIDA">Retenida (narcóticos)</option>
                </select>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Establecimiento de salud</label>
            <input type="text" class="form-control" id="rx_establecimiento" maxlength="200" placeholder="Hospital, clínica o consultorio">
          </div>
          <div class="form-group">
            <label>Fecha de emisión</label>
            <input type="date" class="form-control" id="rx_fecha_emision">
          </div>
          <div class="form-group">
            <label>Observaciones</label>
            <textarea class="form-control" id="rx_observaciones" rows="2" maxlength="500"></textarea>
          </div>
          <div class="form-group" id="rx_bloque_diagnostico" style="display:none;">
            <label>Diagnóstico del paciente <small class="text-muted">(requerido para psicotrópicos/estupefacientes)</small></label>
            <input type="text" class="form-control" id="rx_diagnostico" maxlength="300" placeholder="Ej: Trastorno de ansiedad generalizada, insomnio crónico">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" id="btnCancelarReceta">
            <i class="fa fa-times"></i> Cancelar
          </button>
          <button type="button" class="btn btn-primary" id="btnConfirmarReceta">
            <i class="fa fa-check"></i> Confirmar venta con receta
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Nuevo Cliente -->
  <div class="modal fade" id="modalClienteVenta" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form id="formClienteRapido" autocomplete="off">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title"><i class="fa fa-user-plus"></i> Nuevo cliente</h4>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Nombre (*)</label>
              <input type="text" class="form-control" name="nombre" id="cli_nombre" maxlength="100" required>
            </div>
            <div class="form-group">
              <label>Tipo documento</label>
              <select class="form-control" name="tipo_documento" id="cli_tipo_documento">
                <option value="DNI">DNI</option>
                <option value="RUC">RUC</option>
                <option value="CEDULA">CEDULA</option>
              </select>
            </div>
            <div class="form-group">
              <label>Número documento</label>
              <input type="text" class="form-control" name="num_documento" id="cli_num_documento" maxlength="20">
            </div>
            <div class="form-group">
              <label>Dirección</label>
              <input type="text" class="form-control" name="direccion" id="cli_direccion" maxlength="70">
            </div>
            <div class="form-group">
              <label>Teléfono</label>
              <input type="text" class="form-control" name="telefono" id="cli_telefono" maxlength="20">
            </div>
            <div class="form-group">
              <label>Email</label>
              <input type="email" class="form-control" name="email" id="cli_email" maxlength="50">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            <button type="submit" class="btn btn-primary" id="btnGuardarClienteRapido">
              <i class="fa fa-save"></i> Guardar cliente
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

<?php
}else{
 require 'noacceso.php';
}
require 'footer.php';
 ?>
 <script src="../public/js/qz-tray.js"></script>
 <script src="scripts/venta.js?v=20260603a"></script>
 <?php
}
ob_end_flush();
  ?>
