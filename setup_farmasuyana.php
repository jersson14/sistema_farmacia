<?php
/**
 * Setup inicial – FarmaSuyana
 * Visita UNA VEZ: http://localhost/farmacia/setup_farmasuyana.php
 * Luego elimina este archivo o restringe el acceso.
 */
require_once "config/Conexion.php";

$ok  = [];
$err = [];

// 1. Actualizar configuracion_empresa
$sql = "UPDATE configuracion_empresa SET
    nombre_comercial = 'FarmaSuyana',
    razon_social     = 'Botica FarmaSuyana',
    direccion        = 'Urb. Patibamba Baja, Av. Sinchi Roca Lote 1 – al Costado de la Iglesia Cristiana',
    color_primario   = '#1D4ED8',
    color_secundario = '#DC2626',
    logo             = 'farmasuyana.png'
";
if ($conexion->query($sql)) {
    $ok[] = "✅ Empresa actualizada ({$conexion->affected_rows} fila/s).";
} else {
    // Si no existe, insertar
    $sql2 = "INSERT INTO configuracion_empresa
        (nombre_comercial,razon_social,direccion,color_primario,color_secundario,logo,moneda)
        VALUES
        ('FarmaSuyana','Botica FarmaSuyana',
         'Urb. Patibamba Baja, Av. Sinchi Roca Lote 1',
         '#1D4ED8','#DC2626','farmasuyana.png','PEN')";
    if ($conexion->query($sql2)) $ok[] = "✅ Empresa insertada.";
    else $err[] = "❌ Error empresa: " . $conexion->error;
}

// 2. Copiar logo si no existe
$src  = __DIR__ . '/files/famacia.png';
$dest = __DIR__ . '/files/empresa/farmasuyana.png';
if (!file_exists($dest) && file_exists($src)) {
    copy($src, $dest) ? $ok[] = "✅ Logo copiado." : $err[] = "❌ No se pudo copiar logo.";
} else {
    $ok[] = "✅ Logo ya existe en files/empresa/";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Setup FarmaSuyana</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',sans-serif;background:#EFF6FF;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{background:#fff;border-radius:20px;padding:40px 48px;box-shadow:0 12px 40px rgba(29,78,216,.15);max-width:520px;width:100%}
.box img{height:72px;margin:0 auto 24px;display:block}
h2{font-size:1.4rem;font-weight:800;color:#1E3A8A;margin:0 0 20px;text-align:center}
.msg{padding:12px 16px;border-radius:10px;margin-bottom:10px;font-size:.9rem;font-weight:600}
.ok{background:#EFF6FF;color:#1E3A8A;border:1px solid #BFDBFE}
.err{background:#FEF2F2;color:#B91C1C;border:1px solid #FECACA}
.btn{
  display:block;text-align:center;margin-top:28px;
  background:linear-gradient(135deg,#1E3A8A,#1D4ED8);
  color:#fff;padding:13px;border-radius:12px;font-weight:700;
  text-decoration:none;font-size:.95rem;
}
.warn{font-size:.78rem;color:#64748B;text-align:center;margin-top:16px}
</style>
</head>
<body>
<div class="box">
  <img src="files/famacia.png" alt="FarmaSuyana">
  <h2>Setup inicial completado</h2>
  <?php foreach ($ok  as $m) echo "<div class='msg ok'>$m</div>"; ?>
  <?php foreach ($err as $m) echo "<div class='msg err'>$m</div>"; ?>
  <a href="index.php" class="btn">Ir a la Landing Page</a>
  <a href="tienda/index.php" class="btn" style="margin-top:10px;background:linear-gradient(135deg,#DC2626,#B91C1C)">Ver Tienda Online</a>
  <p class="warn">⚠️ Elimina este archivo (<code>setup_farmasuyana.php</code>) después de usarlo.</p>
</div>
</body>
</html>
