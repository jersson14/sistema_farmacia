<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
  header("Location: login.html");
}else{

require 'header.php';
if ($_SESSION['acceso']==1) {
?>
<style>
.lw-top { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.lw-top h1 { margin:0; font-size:20px; font-weight:700; }
.lw-top p { margin:2px 0 0; color:#64748b; font-size:13px; }
.lw-layout { display:grid; grid-template-columns:240px minmax(0,1fr); gap:16px; align-items:start; }
.lw-nav { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:6px; position:sticky; top:10px; }
.lw-nav a { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:8px; color:#334155; font-weight:600; font-size:13px; }
.lw-nav a:hover { background:#f1f5f9; color:#1D4ED8; }
.lw-nav a.active { background:#1D4ED8; color:#fff; }
.lw-nav a i { font-size:15px; width:18px; text-align:center; }
.lw-nav .lw-dot { margin-left:auto; width:8px; height:8px; border-radius:50%; background:#f59e0b; display:none; }
.lw-nav a.sucio .lw-dot { display:inline-block; }
.lw-nav a .lw-off { margin-left:auto; font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; }
.lw-nav a.active .lw-off { color:#cbd5e1; }
.lw-panel { background:#fff; border:1px solid #e2e8f0; border-radius:12px; }
.lw-panel-hd { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; padding:14px 18px; border-bottom:1px solid #e2e8f0; }
.lw-panel-hd h2 { margin:0; font-size:17px; font-weight:700; }
.lw-panel-hd .lw-ayuda { margin:3px 0 0; font-size:12px; color:#64748b; }
.lw-panel-bd { padding:16px 18px 6px; }
.lw-panel-ft { display:flex; justify-content:flex-end; gap:8px; padding:12px 18px; border-top:1px solid #e2e8f0; background:#f8fafc; border-radius:0 0 12px 12px; position:sticky; bottom:0; }
.lw-campo { margin-bottom:14px; }
.lw-campo > label { display:block; font-weight:600; font-size:13px; margin-bottom:4px; color:#1f2937; }
.lw-campo .help-block { margin:4px 0 0; font-size:11.5px; }
.lw-grid2 { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0 16px; }
.lw-check { display:flex; align-items:center; gap:8px; font-weight:600; font-size:13px; cursor:pointer; margin:0; }
.lw-check input { width:18px; height:18px; margin:0; }
.lw-color { display:flex; align-items:center; gap:8px; }
.lw-color input[type=color] { width:46px; height:34px; padding:2px; border:1px solid #cbd5e1; border-radius:8px; }
.lw-img { display:flex; gap:12px; align-items:flex-start; }
.lw-img-prev { width:120px; height:78px; border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc center/cover no-repeat; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:22px; }
.lw-img-ctrl { flex:1; min-width:0; display:flex; flex-direction:column; gap:6px; }
.lw-icon { display:flex; gap:6px; align-items:center; position:relative; }
.lw-icon-prev { width:34px; height:34px; border-radius:8px; background:#1D4ED8; color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
.lw-icon-pick { position:absolute; z-index:30; top:38px; left:0; width:292px; background:#fff; border:1px solid #cbd5e1; border-radius:10px; box-shadow:0 10px 30px rgba(15,23,42,.18); padding:8px; display:grid; grid-template-columns:repeat(8,1fr); gap:4px; }
.lw-icon-pick button { height:32px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; font-size:15px; color:#334155; }
.lw-icon-pick button:hover { background:#1D4ED8; color:#fff; border-color:#1D4ED8; }
.lw-list-item { border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px 2px; margin-bottom:10px; background:#fbfdff; }
.lw-list-hd { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
.lw-list-hd strong { font-size:12px; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
.lw-list-hd .btn { padding:2px 7px; }
.lw-prod-sel { list-style:none; margin:8px 0 0; padding:0; }
.lw-prod-sel li { display:flex; align-items:center; gap:8px; padding:6px 8px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:6px; background:#fff; font-size:13px; }
.lw-prod-sel li img { width:32px; height:32px; object-fit:cover; border-radius:6px; background:#f1f5f9; }
.lw-prod-sel li span { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.lw-prod-res { position:absolute; z-index:30; left:0; right:0; top:36px; background:#fff; border:1px solid #cbd5e1; border-radius:8px; box-shadow:0 10px 30px rgba(15,23,42,.18); max-height:260px; overflow:auto; }
.lw-prod-res a { display:flex; align-items:center; gap:8px; padding:7px 10px; color:#1f2937; font-size:13px; }
.lw-prod-res a:hover { background:#eff6ff; }
@media (max-width: 991px) {
  .lw-layout { grid-template-columns:minmax(0,1fr); }
  .lw-nav { position:static; display:flex; overflow-x:auto; gap:4px; }
  .lw-nav a { white-space:nowrap; }
  .lw-nav a .lw-off { display:none; }
}
@media (max-width: 600px) {
  .lw-grid2 { grid-template-columns:1fr; }
  .lw-img { flex-direction:column; }
  .lw-img-prev { width:100%; height:120px; }
}
</style>
<div class="content-wrapper">
  <section class="content">
    <div class="box">
      <div class="box-header with-border">
        <div class="lw-top">
          <div>
            <h1><i class="bi bi-globe2"></i> Página web</h1>
            <p>Edita los textos, imágenes y secciones de tu página pública. Los cambios se ven al instante al guardar.</p>
          </div>
          <a href="../index.php" target="_blank" class="btn btn-default"><i class="fa fa-external-link"></i> Ver página</a>
        </div>
      </div>
      <div class="box-body">
        <div class="alert alert-warning" id="lwMigracion" style="display:none">
          <i class="fa fa-database"></i> Para poder guardar cambios falta crear la tabla de la página web.
          Ejecuta <strong>migrations/20260925_landing_config.sql</strong> en la base de datos. Mientras tanto la página muestra los textos originales.
        </div>
        <div class="lw-layout">
          <nav class="lw-nav" id="lwNav"></nav>
          <div class="lw-panel">
            <div class="lw-panel-hd">
              <div>
                <h2 id="lwTitulo">Cargando…</h2>
                <p class="lw-ayuda" id="lwAyuda"></p>
              </div>
            </div>
            <form id="lwForm" autocomplete="off" onsubmit="return false">
              <div class="lw-panel-bd" id="lwCampos">
                <p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Cargando contenido…</p>
              </div>
              <div class="lw-panel-ft">
                <button type="button" class="btn btn-default" id="lwRestaurar"><i class="fa fa-undo"></i> Restaurar textos originales</button>
                <button type="button" class="btn btn-primary" id="lwGuardar"><i class="fa fa-save"></i> Guardar y publicar</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
<input type="file" id="lwFile" accept="image/jpeg,image/png,image/gif" style="display:none">
<?php
}else{
  require 'noacceso.php';
}
require 'footer.php';
?>
<script src="scripts/landing.js?v=20260925a"></script>
<?php
}
ob_end_flush();
?>
