<?php
if (strlen(session_id()) < 1) session_start();
if (!isset($_SESSION['idusuario'])) {
    echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
    exit;
}

require_once "../config/Conexion.php";
require_once "../modelos/Empresa.php";

$cfg         = (new Empresa())->obtener();
$impresora   = !empty($cfg['nombre_impresora']) ? trim($cfg['nombre_impresora']) : '';

if ($impresora === '') {
    echo json_encode(['ok' => false, 'msg' => 'Impresora no configurada. Ve a Gestión Pro → Empresa y completa el campo "Nombre de impresora".']);
    exit;
}

// ESC/POS: ESC p pin t1 t2  →  abre gaveta conectada al puerto kick
$cmd = "\x1B\x70\x00\x19\xFA";

$ok  = false;
$err = '';

// Método 1: escribir directamente al recurso compartido de Windows
$recurso = "\\\\" . "localhost" . "\\" . $impresora;
$bytes = @file_put_contents($recurso, $cmd);
if ($bytes !== false) {
    $ok = true;
} else {
    // Método 2: crear archivo temporal y copiarlo al recurso con cmd.exe
    $tmp = tempnam(sys_get_temp_dir(), 'gvt');
    if ($tmp !== false) {
        file_put_contents($tmp, $cmd);
        $dest = "\\\\" . "localhost" . "\\" . $impresora;
        $salida = shell_exec("cmd /c copy /b \"$tmp\" \"$dest\" 2>&1");
        @unlink($tmp);
        if ($salida !== null && (strpos($salida, '1 file') !== false || strpos($salida, '1 archivo') !== false)) {
            $ok = true;
        } else {
            $err = trim((string)$salida);
        }
    }
}

echo json_encode(['ok' => $ok, 'msg' => $ok ? 'Gaveta abierta' : 'No se pudo abrir la gaveta: ' . $err]);
?>
