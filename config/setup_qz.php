<?php
/**
 * Generador de certificado para QZ Tray.
 * Ejecutar UNA SOLA VEZ: http://localhost/farmacia/config/setup_qz.php
 * Borrar o proteger este archivo después.
 */
session_start();
if (!isset($_SESSION['idusuario'])) {
    http_response_code(403);
    exit('Acceso denegado. Inicia sesión primero.');
}

$privateKeyFile = __DIR__ . '\\qz_private.key';
$certFile       = __DIR__ . '\\..\\public\\qz_cert.pem';
$opensslBin     = 'C:\\xampp\\apache\\bin\\openssl.exe';
$opensslConf    = 'C:\\xampp\\apache\\conf\\openssl.cnf';

$yaExisten = file_exists($privateKeyFile) && file_exists($certFile);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar'])) {

    if (!file_exists($opensslBin)) {
        die('No se encontró openssl.exe en ' . $opensslBin);
    }

    // Limpiar archivos anteriores
    @unlink($privateKeyFile);
    @unlink($certFile);

    // Paso 1: generar llave privada RSA 2048
    $cmd1 = '"' . $opensslBin . '" genrsa -out "' . $privateKeyFile . '" 2048 2>&1';
    exec($cmd1, $out1, $ret1);

    if ($ret1 !== 0 || !file_exists($privateKeyFile)) {
        echo '<meta charset="utf-8"><h3 style="color:red">Error generando llave privada</h3><pre>';
        echo htmlspecialchars(implode("\n", $out1));
        echo '</pre>';
        exit;
    }

    // Paso 2: generar certificado autofirmado (10 años)
    $cmd2 = '"' . $opensslBin . '" req -new -x509'
          . ' -key "'    . $privateKeyFile . '"'
          . ' -out "'    . $certFile       . '"'
          . ' -days 3650'
          . ' -subj "/CN=FarmaciaPOS-localhost"'
          . ' -config "' . $opensslConf   . '"'
          . ' 2>&1';
    exec($cmd2, $out2, $ret2);

    if ($ret2 !== 0 || !file_exists($certFile)) {
        echo '<meta charset="utf-8"><h3 style="color:red">Error generando certificado</h3><pre>';
        echo htmlspecialchars(implode("\n", $out2));
        echo '</pre>';
        exit;
    }

    // Verificar que la llave carga correctamente
    $privKey = openssl_pkey_get_private(file_get_contents($privateKeyFile));
    if (!$privKey) {
        echo '<meta charset="utf-8"><h3 style="color:red">Certificado generado pero llave no carga: ' . openssl_error_string() . '</h3>';
        exit;
    }

    // Ruta de destino en QZ Tray
    $username  = getenv('USERNAME') ?: get_current_user();
    $authDir   = 'C:\\Users\\' . $username . '\\AppData\\Roaming\\qz\\auth\\';
    $authDirAlt = 'C:\\Users\\' . $username . '\\AppData\\Roaming\\qz-tray\\auth\\';
    $destDir   = is_dir($authDir) ? $authDir : (is_dir($authDirAlt) ? $authDirAlt : '');

    $copiado = false;
    if ($destDir) {
        $copiado = copy($certFile, $destDir . 'farmacia_pos.pem');
    }

    echo '<meta charset="utf-8">';
    echo '<h2 style="color:green">&#10003; Certificado generado correctamente</h2>';
    echo '<p><strong>Llave privada:</strong> ' . htmlspecialchars($privateKeyFile) . '</p>';
    echo '<p><strong>Certificado:</strong> ' . htmlspecialchars($certFile) . '</p>';

    if ($copiado) {
        echo '<div style="background:#e6f4ea;padding:12px;border-radius:4px;margin:12px 0">';
        echo '&#10003; El certificado fue copiado automáticamente a la carpeta auth de QZ Tray:<br>';
        echo '<code>' . htmlspecialchars($destDir . 'farmacia_pos.pem') . '</code>';
        echo '</div>';
        echo '<h3>Último paso</h3>';
        echo '<ol><li>Clic derecho en el ícono de QZ Tray → <strong>Exit</strong></li>';
        echo '<li>Vuelve a abrir QZ Tray</li>';
        echo '<li>Prueba imprimir un ticket — no debe salir el popup</li></ol>';
    } else {
        echo '<div style="background:#fff3cd;padding:12px;border-radius:4px;margin:12px 0">';
        echo '&#9888; Copia manual requerida:<br>';
        echo 'Copia <code>' . htmlspecialchars($certFile) . '</code><br>';
        echo 'a <code>' . htmlspecialchars($authDir) . '</code>';
        echo '</div>';
        echo '<h3>Pasos finales</h3>';
        echo '<ol>';
        echo '<li>Copia el archivo cert a la carpeta auth de QZ Tray (ver arriba)</li>';
        echo '<li>Clic derecho en QZ Tray → <strong>Exit</strong></li>';
        echo '<li>Vuelve a abrir QZ Tray</li>';
        echo '<li>Prueba imprimir un ticket</li>';
        echo '</ol>';
    }

    echo '<hr><a href="test_qz.php">Ejecutar diagnóstico</a>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Setup QZ Tray</title></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:40px auto;padding:20px">
<h2>Configuración de certificado QZ Tray</h2>
<?php if ($yaExisten): ?>
<div style="background:#fff3cd;border:1px solid #ffc107;padding:12px;border-radius:4px;margin-bottom:16px">
  &#9888; Ya existen archivos. Si regeneras deberás volver a copiar el cert a QZ Tray.
</div>
<?php endif; ?>
<p>Genera las llaves RSA para que QZ Tray confíe en este sitio sin popups.</p>
<form method="post">
  <button name="generar" value="1"
    style="padding:10px 24px;background:#1a6fa0;color:#fff;border:0;border-radius:4px;font-size:14px;cursor:pointer">
    <?php echo $yaExisten ? 'Regenerar certificado' : 'Generar certificado'; ?>
  </button>
</form>
</body>
</html>
