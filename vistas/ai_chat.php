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
<style>
.chat-shell { display:flex; height:calc(100vh - 130px); background:#f4f6f9; }
.chat-side  { width:280px; background:#fff; border-right:1px solid #e2e8f0; display:flex; flex-direction:column; }
.chat-side .top { padding:10px; border-bottom:1px solid #eee; }
.chat-side .top button { width:100%; }
.chat-side h5 { padding:12px 15px 6px; margin:0; color:#0f766e; font-weight:600; font-size:13px; text-transform:uppercase; letter-spacing:.5px; }
.chat-side .list { flex:1; overflow-y:auto; }
.chat-side .item { padding:10px 12px; border-bottom:1px solid #f2f2f2; cursor:pointer; font-size:12px; position:relative; }
.chat-side .item:hover { background:#f0f9f7; }
.chat-side .item.active { background:#e6fffb; border-left:3px solid #0f766e; padding-left:9px; }
.chat-side .item .q { color:#111; font-weight:500; margin-bottom:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; padding-right:20px; }
.chat-side .item .t { color:#888; font-size:10px; }
.chat-side .item .del { position:absolute; right:6px; top:8px; color:#c00; opacity:0; font-size:14px; padding:2px 5px; }
.chat-side .item:hover .del { opacity:.7; }
.chat-side .item .del:hover { opacity:1; }

.chat-main { flex:1; display:flex; flex-direction:column; }
.chat-log  { flex:1; overflow-y:auto; padding:20px; }
.chat-msg  { max-width:80%; margin-bottom:14px; padding:12px 16px; border-radius:12px; line-height:1.45; font-size:14px; }
.chat-msg.user { background:#0f766e; color:#fff; margin-left:auto; border-bottom-right-radius:2px; }
.chat-msg.bot  { background:#fff; border:1px solid #e2e8f0; margin-right:auto; border-bottom-left-radius:2px; }
.chat-msg.err  { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; margin-right:auto; }
.chat-msg .sql-toggle { font-size:11px; color:#666; cursor:pointer; margin-top:8px; user-select:none; }
.chat-msg .sql-box { display:none; margin-top:6px; background:#f3f4f6; padding:8px; border-radius:6px; font-family:monospace; font-size:11px; white-space:pre-wrap; color:#1f2937; }
.chat-msg .tabla-box { margin-top:10px; overflow-x:auto; }
.chat-msg .tabla-box table { font-size:12px; width:100%; }
.chat-msg .tabla-box th { background:#f8fafc; }

.chat-input-wrap { border-top:1px solid #e2e8f0; background:#fff; padding:12px; }
.chat-suggestions { padding:0 12px 8px; }
.chat-suggestions .chip { display:inline-block; background:#eef2f7; color:#334155; padding:5px 10px; margin:3px; border-radius:20px; font-size:12px; cursor:pointer; }
.chat-suggestions .chip:hover { background:#0f766e; color:#fff; }
.chat-input { display:flex; gap:8px; }
.chat-input textarea { flex:1; resize:none; border-radius:8px; padding:10px; }
.chat-input button { min-width:100px; }
</style>

<div class="content-wrapper">
  <section class="content" style="padding:0;">
    <div class="chat-shell">

      <div class="chat-side">
        <div class="top">
          <button class="btn btn-primary btn-sm" id="btnNuevoChat">
            <i class="bi bi-plus-circle"></i> Nueva conversacion
          </button>
        </div>
        <h5><i class="bi bi-chat-square-text"></i> Conversaciones</h5>
        <div class="list" id="convList"></div>
      </div>

      <div class="chat-main">

        <div class="chat-log" id="chatLog">
          <div class="chat-msg bot">
            <strong>Hola <?= htmlspecialchars($_SESSION['nombre'] ?? '') ?></strong> <i class="bi bi-robot"></i><br>
            Pregunta lo que quieras sobre tu farmacia en lenguaje natural. Yo consulto la base de datos por ti.
          </div>
        </div>

        <div class="chat-suggestions">
          <span class="chip" onclick="ask(this)">¿Cuanto vendi hoy?</span>
          <span class="chip" onclick="ask(this)">Top 5 productos mas vendidos este mes</span>
          <span class="chip" onclick="ask(this)">¿Cuantos productos estan bajo el stock minimo?</span>
          <span class="chip" onclick="ask(this)">Ventas por hora del dia de hoy</span>
          <span class="chip" onclick="ask(this)">Clientes con mas compras acumuladas</span>
          <span class="chip" onclick="ask(this)">¿Que lotes vencen este mes?</span>
        </div>

        <div class="chat-input-wrap">
          <div class="chat-input">
            <textarea id="txtPregunta" rows="2" placeholder="Escribe tu pregunta y presiona Enter (Shift+Enter = nueva linea)..."></textarea>
            <button class="btn btn-primary" id="btnPreguntar"><i class="fa fa-paper-plane"></i> Enviar</button>
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
<script src="scripts/ai_chat.js"></script>
<?php
}
ob_end_flush();
?>
