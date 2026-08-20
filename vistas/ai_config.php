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
            <h1 class="box-title">
              <i class="bi bi-robot"></i> Integraciones IA (OpenAI + SMTP)
            </h1>
          </div>

          <div class="panel-body">

            <div class="callout callout-info" style="margin:12px;">
              <h4><i class="fa fa-info-circle"></i> ¿Que se configura aqui?</h4>
              <p>
                <strong>OpenAI</strong> se usa para generar los reportes automaticos (Cierre diario, Vencimientos, Stock critico) y para el AI Chat.
                <strong>SMTP</strong> es el correo desde el que se envian los reportes. Recomendado: Gmail con contrasena de aplicacion.
              </p>
            </div>

            <form id="aiConfigForm">

              <!-- OpenAI -->
              <div class="box box-solid" style="margin:10px;">
                <div class="box-header with-border" style="background:#f4f8fb;">
                  <h3 class="box-title"><i class="bi bi-cpu"></i> OpenAI</h3>
                </div>
                <div class="box-body">
                  <div class="row">
                    <div class="form-group col-md-8">
                      <label>API Key</label>
                      <input type="password" autocomplete="new-password" class="form-control" name="openai_api_key" id="openai_api_key" placeholder="sk-...">
                      <span class="help-block" id="openai_api_key_hint" style="font-size:11px;">
                        Deja vacio para conservar la actual. <a href="https://platform.openai.com/api-keys" target="_blank">Obtener key</a>
                      </span>
                    </div>
                    <div class="form-group col-md-4">
                      <label>Modelo</label>
                      <select class="form-control" name="openai_model" id="openai_model">
                        <option value="gpt-4o-mini">gpt-4o-mini (recomendado, barato)</option>
                        <option value="gpt-4o">gpt-4o (mejor calidad)</option>
                        <option value="gpt-4-turbo">gpt-4-turbo</option>
                      </select>
                    </div>
                    <div class="form-group col-md-6">
                      <label>Organizacion (opcional)</label>
                      <input type="text" class="form-control" name="openai_org" id="openai_org" placeholder="org-...">
                    </div>
                    <div class="form-group col-md-6" style="padding-top:24px;">
                      <button type="button" class="btn btn-info" id="btnProbarOpenAI">
                        <i class="fa fa-plug"></i> Probar conexion OpenAI
                      </button>
                      <span id="resultProbarOpenAI" style="margin-left:10px;"></span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- SMTP -->
              <div class="box box-solid" style="margin:10px;">
                <div class="box-header with-border" style="background:#f4f8fb;">
                  <h3 class="box-title"><i class="bi bi-envelope-at"></i> SMTP (envio de correos)</h3>
                </div>
                <div class="box-body">
                  <div class="row">
                    <div class="form-group col-md-6">
                      <label>Host SMTP</label>
                      <input type="text" class="form-control" name="smtp_host" id="smtp_host" placeholder="smtp.gmail.com">
                    </div>
                    <div class="form-group col-md-3">
                      <label>Puerto</label>
                      <input type="number" class="form-control" name="smtp_port" id="smtp_port" value="587">
                    </div>
                    <div class="form-group col-md-3">
                      <label>Cifrado</label>
                      <select class="form-control" name="smtp_secure" id="smtp_secure">
                        <option value="tls">TLS (587)</option>
                        <option value="ssl">SSL (465)</option>
                        <option value="none">Ninguno</option>
                      </select>
                    </div>

                    <div class="form-group col-md-6">
                      <label>Usuario SMTP</label>
                      <input type="text" class="form-control" name="smtp_user" id="smtp_user" placeholder="tucorreo@gmail.com" autocomplete="new-password">
                    </div>
                    <div class="form-group col-md-6">
                      <label>Contrasena SMTP</label>
                      <input type="password" class="form-control" name="smtp_pass" id="smtp_pass" placeholder="Deja vacio para conservar" autocomplete="new-password">
                      <span class="help-block" style="font-size:11px;">
                        En Gmail usa <a href="https://myaccount.google.com/apppasswords" target="_blank">contrasena de aplicacion</a>, no la del correo.
                      </span>
                    </div>

                    <div class="form-group col-md-6">
                      <label>Correo remitente (From)</label>
                      <input type="email" class="form-control" name="smtp_from" id="smtp_from" placeholder="reportes@tufarmacia.com">
                    </div>
                    <div class="form-group col-md-6">
                      <label>Nombre remitente</label>
                      <input type="text" class="form-control" name="smtp_from_name" id="smtp_from_name" placeholder="Farmacia XYZ">
                    </div>

                    <div class="form-group col-md-8">
                      <label>Enviar correo de prueba a:</label>
                      <input type="email" class="form-control" id="destino_prueba" placeholder="tucorreo@ejemplo.com">
                    </div>
                    <div class="form-group col-md-4" style="padding-top:24px;">
                      <button type="button" class="btn btn-info" id="btnProbarSMTP">
                        <i class="fa fa-paper-plane"></i> Enviar prueba
                      </button>
                    </div>
                    <div class="col-md-12">
                      <span id="resultProbarSMTP"></span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-group" style="padding:12px;">
                <button type="submit" class="btn btn-primary btn-lg">
                  <i class="fa fa-save"></i> Guardar configuracion
                </button>
                <a href="escritorio.php" class="btn btn-default btn-lg">Cancelar</a>
              </div>

            </form>

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
<script src="scripts/ai_config.js"></script>
<?php
}
ob_end_flush();
?>
