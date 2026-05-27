<?php
$GLOBALS['pageTitulo'] = 'Ingresar / Registrarse';
require 'layout.php';

if (!empty($_SESSION['tienda_cliente'])) {
    header('Location: index.php'); exit;
}
?>

<div class="auth-page">
  <div class="auth-card">

    <div class="auth-header">
      <h2>Bienvenido 👋</h2>
      <p>Ingresa o crea una cuenta para continuar</p>
    </div>

    <div class="auth-tabs">
      <button class="auth-tab active" id="tabLogin" onclick="showTab('login')">Ingresar</button>
      <button class="auth-tab" id="tabRegistro" onclick="showTab('registro')">Crear cuenta</button>
    </div>

    <!-- Panel Login -->
    <div class="auth-body" id="panelLogin">
      <div id="msgLogin" style="display:none;margin-bottom:14px;padding:10px 14px;border-radius:8px;font-size:14px"></div>
      <div class="fgroup">
        <label>Correo electrónico</label>
        <input type="email" id="l_email" placeholder="tu@correo.com" autocomplete="email">
      </div>
      <div class="fgroup">
        <label>Contraseña</label>
        <input type="password" id="l_pass" placeholder="••••••" autocomplete="current-password">
      </div>
      <button class="btn-primary-lg" id="btnLogin" onclick="hacerLogin()">Ingresar a mi cuenta</button>
      <div style="text-align:center;margin-top:16px">
        <a href="index.php" style="font-size:13px;color:#6b7280">← Volver al catálogo</a>
      </div>
    </div>

    <!-- Panel Registro -->
    <div class="auth-body" id="panelRegistro" style="display:none">
      <div id="msgRegistro" style="display:none;margin-bottom:14px;padding:10px 14px;border-radius:8px;font-size:14px"></div>
      <div class="fgroup">
        <label>Nombre completo *</label>
        <input type="text" id="r_nombre" placeholder="Juan Pérez">
      </div>
      <div class="fgroup">
        <label>Correo electrónico *</label>
        <input type="email" id="r_email" placeholder="tu@correo.com">
      </div>
      <div class="fgroup">
        <label>Contraseña * <span style="font-weight:400;color:#6b7280">(mín. 6 caracteres)</span></label>
        <input type="password" id="r_pass" placeholder="••••••">
      </div>
      <div class="fgroup">
        <label>Repetir contraseña *</label>
        <input type="password" id="r_pass2" placeholder="••••••">
      </div>
      <div class="fgroup">
        <label>Teléfono / WhatsApp</label>
        <input type="text" id="r_tel" placeholder="9XXXXXXXX">
      </div>
      <div class="fgroup">
        <label>Dirección de entrega</label>
        <input type="text" id="r_dir" placeholder="Av. Principal 123">
      </div>
      <div class="fgroup">
        <label>Distrito</label>
        <input type="text" id="r_dist" placeholder="Tu distrito">
      </div>
      <button class="btn-primary-lg" id="btnRegistro" onclick="hacerRegistro()">Crear mi cuenta</button>
      <div style="text-align:center;margin-top:16px">
        <a href="index.php" style="font-size:13px;color:#6b7280">← Volver al catálogo</a>
      </div>
    </div>

  </div>
</div>

<script>
function showTab(tab){
  document.getElementById('panelLogin').style.display    = tab === 'login'    ? 'block' : 'none';
  document.getElementById('panelRegistro').style.display = tab === 'registro' ? 'block' : 'none';
  document.getElementById('tabLogin').classList.toggle('active',    tab === 'login');
  document.getElementById('tabRegistro').classList.toggle('active', tab === 'registro');
}

function mostrarMsg(id, tipo, texto){
  var el = document.getElementById(id);
  el.style.display = 'block';
  el.style.background   = tipo === 'ok' ? '#dcfce7' : '#fee2e2';
  el.style.color        = tipo === 'ok' ? '#166534' : '#7f1d1d';
  el.style.borderLeft   = '4px solid ' + (tipo === 'ok' ? '#16a34a' : '#ef4444');
  el.textContent = texto;
}

function fetchAuth(url, fd, onOk, onErr){
  fetch(url, {method:'POST', body:fd})
    .then(function(r){ return r.text(); })
    .then(function(txt){
      var d;
      try { d = JSON.parse(txt); }
      catch(e){ onErr('Respuesta inesperada: ' + txt.substring(0,200)); return; }
      if (d.ok) onOk(d); else onErr(d.message || 'Error desconocido');
    })
    .catch(function(e){ onErr('Error de red: ' + e.message); });
}

function hacerLogin(){
  var btn = document.getElementById('btnLogin');
  btn.disabled = true; btn.textContent = 'Verificando...';
  var fd = new FormData();
  fd.append('email',    document.getElementById('l_email').value);
  fd.append('password', document.getElementById('l_pass').value);
  fetchAuth('ajax/auth.php?op=login', fd,
    function(d){
      mostrarMsg('msgLogin','ok','✔ ' + d.message);
      setTimeout(function(){ window.location.href = 'index.php'; }, 700);
    },
    function(msg){
      mostrarMsg('msgLogin','err', msg);
      btn.disabled = false; btn.textContent = 'Ingresar a mi cuenta';
    }
  );
}

function hacerRegistro(){
  var btn = document.getElementById('btnRegistro');
  btn.disabled = true; btn.textContent = 'Creando cuenta...';
  var fd = new FormData();
  fd.append('nombre',    document.getElementById('r_nombre').value);
  fd.append('email',     document.getElementById('r_email').value);
  fd.append('password',  document.getElementById('r_pass').value);
  fd.append('password2', document.getElementById('r_pass2').value);
  fd.append('telefono',  document.getElementById('r_tel').value);
  fd.append('direccion', document.getElementById('r_dir').value);
  fd.append('distrito',  document.getElementById('r_dist').value);
  fetchAuth('ajax/auth.php?op=registro', fd,
    function(d){
      mostrarMsg('msgRegistro','ok','✔ ' + d.message);
      setTimeout(function(){ window.location.href = 'index.php'; }, 700);
    },
    function(msg){
      mostrarMsg('msgRegistro','err', msg);
      btn.disabled = false; btn.textContent = 'Crear mi cuenta';
    }
  );
}

document.getElementById('l_pass').addEventListener('keypress', function(e){ if(e.key==='Enter') hacerLogin(); });
document.getElementById('r_pass2').addEventListener('keypress', function(e){ if(e.key==='Enter') hacerRegistro(); });
</script>
</body>
</html>
