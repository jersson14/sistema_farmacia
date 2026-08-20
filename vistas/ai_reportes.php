<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
    header("Location: login.html");
} else {
    require 'header.php';
    $esAdmin = !empty($_SESSION['acceso']) && $_SESSION['acceso'] == 1;
    $tieneIA = !empty($_SESSION['ia']) && $_SESSION['ia'] == 1;
    if ($esAdmin || $tieneIA) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="box box-primary">
          <div class="box-header with-border">
            <h1 class="box-title"><i class="bi bi-envelope-paper"></i> Reportes IA por correo</h1>
            <div class="box-tools pull-right">
              <a href="ai_config.php" class="btn btn-default btn-sm">
                <i class="bi bi-gear"></i> Configurar integraciones
              </a>
            </div>
          </div>
          <div class="panel-body">

            <div class="callout callout-info" style="margin:12px;">
              <p style="margin:0;">
                Cada reporte recolecta datos reales de tu farmacia, los analiza con IA y envia un correo HTML a los destinatarios que configures.
                Antes de enviar puedes hacer <strong>Vista previa</strong> para revisar el contenido.
              </p>
            </div>

            <div class="table-responsive" style="margin:12px;">
              <table class="table table-bordered table-striped" id="tblReportes">
                <thead style="background:#0f766e;color:#fff;">
                  <tr>
                    <th>Reporte</th>
                    <th>Descripcion</th>
                    <th>Destinatarios</th>
                    <th style="width:80px;">Frecuencia</th>
                    <th style="width:260px;">Acciones</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>

            <hr>
            <div style="margin:12px;">
              <h4><i class="bi bi-clock-history"></i> Historial reciente</h4>
              <div class="table-responsive">
                <table class="table table-condensed table-hover" id="tblHistorial">
                  <thead>
                    <tr>
                      <th>Fecha</th>
                      <th>Reporte</th>
                      <th>Destinatarios</th>
                      <th>Asunto</th>
                      <th>Estado</th>
                      <th>Tokens</th>
                      <th>Ver</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Modal: Configurar destinatarios -->
<div class="modal fade" id="modalDestinatarios" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Destinatarios del reporte</h4>
      </div>
      <div class="modal-body">
        <form id="formDestinatarios">
          <input type="hidden" id="dest_codigo">
          <div class="form-group">
            <label>Correos (separados por coma)</label>
            <textarea class="form-control" id="dest_lista" rows="3" placeholder="dueno@farmacia.com, admin@farmacia.com"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" id="btnGuardarDestinatarios">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Enviar (con fecha para cierre diario) -->
<div class="modal fade" id="modalEnviar" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Enviar reporte</h4>
      </div>
      <div class="modal-body">
        <input type="hidden" id="env_codigo">
        <p><strong>Reporte:</strong> <span id="env_nombre"></span></p>
        <div class="form-group" id="wrap_fecha">
          <label>Fecha del reporte</label>
          <input type="date" class="form-control" id="env_fecha">
        </div>
        <div class="form-group" id="wrap_dias" style="display:none;">
          <label>Ventana de dias (para alerta de vencimientos)</label>
          <input type="number" class="form-control" id="env_dias" value="60" min="7" max="365">
          <span class="help-block" style="font-size:11px;">Analiza lotes que venceran dentro de esta cantidad de dias.</span>
        </div>
        <div class="form-group">
          <label>Enviar a (opcional, sobreescribe la configuracion)</label>
          <input type="text" class="form-control" id="env_override" placeholder="Deja vacio para usar los destinatarios guardados">
        </div>
        <div id="env_status"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-info" id="btnPreview"><i class="fa fa-eye"></i> Vista previa</button>
        <button class="btn btn-success" id="btnEnviarAhora"><i class="fa fa-paper-plane"></i> Enviar ahora</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Preview HTML -->
<div class="modal fade" id="modalPreview" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Vista previa del correo</h4>
      </div>
      <div class="modal-body" style="padding:0;">
        <iframe id="previewFrame" style="width:100%;height:70vh;border:0;"></iframe>
      </div>
    </div>
  </div>
</div>

<?php
    } else {
        require 'noacceso.php';
    }
    require 'footer.php';
?>
<script src="scripts/ai_reportes.js"></script>
<?php
}
ob_end_flush();
?>
