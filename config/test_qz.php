<?php
session_start();
if (!isset($_SESSION['idusuario'])) {
    exit('Inicia sesión primero: <a href="../index.php">Login</a>');
}

$privateKeyFile = __DIR__ . '/qz_private.key';
$certFile       = __DIR__ . '/../public/qz_cert.pem';

echo '<meta charset="utf-8">';
echo '<style>body{font-family:monospace;padding:20px} .ok{color:green} .err{color:red} .warn{color:orange}</style>';
echo '<h2>Diagnóstico QZ Tray</h2><pre>';

// 1. Verificar archivos
echo "\n[1] Archivos:\n";
echo "  qz_private.key : " . (file_exists($privateKeyFile) ? '<span class="ok">✔ existe</span>' : '<span class="err">✘ NO existe — ejecuta setup_qz.php</span>') . "\n";
echo "  qz_cert.pem    : " . (file_exists($certFile) ? '<span class="ok">✔ existe</span>' : '<span class="err">✘ NO existe</span>') . "\n";

if (!file_exists($privateKeyFile) || !file_exists($certFile)) {
    echo "</pre><p class='err'>Falta el certificado. <a href='setup_qz.php'>Ir a setup_qz.php</a></p>";
    exit;
}

// 2. Verificar que la llave privada carga
echo "\n[2] Cargar llave privada:\n";
$keyContent = file_get_contents($privateKeyFile);
$privKey = openssl_pkey_get_private($keyContent);
$usandoFallback = false;
if (!$privKey) {
    echo "  <span class='warn'>⚠ PHP nativo: " . openssl_error_string() . "</span>\n";
    echo "  Probando fallback con openssl.exe...\n";
    $usandoFallback = true;
} else {
    echo "  <span class='ok'>✔ Llave cargada con PHP nativo</span>\n";
}

// 3. Probar firma
echo "\n[3] Prueba de firma:\n";
$testData = 'test-challenge-12345';
$signature = null;
$opensslBin = 'C:\\xampp\\apache\\bin\\openssl.exe';

if (!$usandoFallback) {
    openssl_sign($testData, $signature, $privKey, OPENSSL_ALGO_SHA512);
}

if (empty($signature)) {
    // Fallback: openssl.exe
    if (!file_exists($opensslBin)) {
        echo "  <span class='err'>✘ openssl.exe no encontrado en $opensslBin</span>\n";
        exit;
    }
    $tmpIn  = tempnam(sys_get_temp_dir(), 'qzin_');
    $tmpOut = tempnam(sys_get_temp_dir(), 'qzout_');
    file_put_contents($tmpIn, $testData);
    $cmd = '"' . $opensslBin . '" dgst -sha512 -sign "' . $privateKeyFile . '" -out "' . $tmpOut . '" "' . $tmpIn . '" 2>&1';
    exec($cmd, $cmdOut, $ret);
    if ($ret === 0 && file_exists($tmpOut)) {
        $signature = file_get_contents($tmpOut);
    }
    @unlink($tmpIn); @unlink($tmpOut);
    if (empty($signature)) {
        echo "  <span class='err'>✘ Firma falló con openssl.exe: " . htmlspecialchars(implode(' ', $cmdOut)) . "</span>\n";
        exit;
    }
    echo "  <span class='ok'>✔ Firma generada con openssl.exe (fallback)</span>\n";
} else {
    echo "  <span class='ok'>✔ Firma generada con PHP nativo</span>\n";
}
$signatureB64 = base64_encode($signature);
echo "  Longitud base64: " . strlen($signatureB64) . " caracteres\n";

// 4. Verificar la firma con la clave pública del cert
echo "\n[4] Verificar firma con el certificado:\n";
$certContent = file_get_contents($certFile);
$pubKey = openssl_get_publickey($certContent);
$verify = openssl_verify($testData, $signature, $pubKey, OPENSSL_ALGO_SHA512);
if ($verify === 1) {
    echo "  <span class='ok'>✔ La firma es válida — el par llave/certificado coincide</span>\n";
} else {
    echo "  <span class='err'>✘ La firma NO verifica — el certificado y la llave no coinciden</span>\n";
}

// 5. URL pública del cert
echo "\n[5] URL del certificado (debe ser accesible desde el navegador):\n";
$url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$url .= str_replace('/config/test_qz.php', '/public/qz_cert.pem', $_SERVER['REQUEST_URI']);
echo "  $url\n";

// 6. Verificar ruta del sign endpoint
echo "\n[6] Endpoint de firma:\n";
$signPath = __DIR__ . '/../ajax/qz_sign.php';
echo "  ajax/qz_sign.php : " . (file_exists($signPath) ? '<span class="ok">✔ existe</span>' : '<span class="err">✘ NO existe</span>') . "\n";

echo "\n</pre><p><strong>Si todo tiene ✔</strong>, el problema es solo que QZ Tray necesita reconocer el certificado.<br>";
echo "Copia <code>" . htmlspecialchars($certFile) . "</code><br>a la carpeta auth de QZ Tray y reinicia QZ Tray completamente (no solo Reload).</p>";
?>
