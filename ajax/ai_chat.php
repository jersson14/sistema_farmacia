<?php
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
header('Content-Type: application/json; charset=utf-8');

// Blindaje: cualquier fatal / exception no capturada devuelve JSON legible en vez de HTTP 500 vacio.
set_exception_handler(function ($e) {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Excepcion: ' . $e->getMessage()]);
    exit;
});
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Fatal: ' . $e['message']]);
    }
});

session_start();
if (!isset($_SESSION['idusuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Sesion expirada.']);
    exit;
}
$esAdmin = !empty($_SESSION['acceso']) && $_SESSION['acceso'] == 1;
$tieneIA = !empty($_SESSION['ia']) && $_SESSION['ia'] == 1;
if (!$esAdmin && !$tieneIA) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Sin permiso.']);
    exit;
}

require_once __DIR__ . "/../modelos/AIChat.php";
$chat = new AIChat();

$op = $_GET['op'] ?? ($_POST['op'] ?? '');
$idusuario = (int)$_SESSION['idusuario'];

switch ($op) {

    case 'preguntar':
        $pregunta = trim($_POST['pregunta'] ?? '');
        $idconv   = trim($_POST['idconv']   ?? '');
        if ($pregunta === '') {
            echo json_encode(['ok' => false, 'message' => 'Pregunta vacia.']);
            exit;
        }
        $r = $chat->preguntar($pregunta, $idusuario, $idconv);
        echo json_encode($r);
        break;

    case 'nueva':
        // Solo devuelve un nuevo idconv para que el frontend lo use
        echo json_encode(['ok' => true, 'idconv' => $chat->nuevoIdConv()]);
        break;

    case 'listar_conversaciones':
        // Admin ve todo, resto solo lo suyo
        $idu = $esAdmin ? null : $idusuario;
        $rs = $chat->listarConversaciones($idu, 50);
        $out = [];
        while ($r = $rs->fetch_assoc()) $out[] = $r;
        echo json_encode(['ok' => true, 'data' => $out]);
        break;

    case 'cargar_conversacion':
        $idconv = trim($_GET['idconv'] ?? '');
        if ($idconv === '') { echo json_encode(['ok' => false, 'message' => 'Falta idconv.']); exit; }
        $idu = $esAdmin ? null : $idusuario;
        $rs = $chat->cargarConversacion($idconv, $idu);
        $out = [];
        while ($r = $rs->fetch_assoc()) $out[] = $r;
        echo json_encode(['ok' => true, 'idconv' => $idconv, 'data' => $out]);
        break;

    case 'eliminar_conversacion':
        $idconv = trim($_POST['idconv'] ?? '');
        if ($idconv === '') { echo json_encode(['ok' => false, 'message' => 'Falta idconv.']); exit; }
        $idu = $esAdmin ? null : $idusuario;
        $ok = $chat->eliminarConversacion($idconv, $idu);
        echo json_encode(['ok' => (bool)$ok, 'message' => $ok ? 'Conversacion eliminada.' : 'Error.']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Operacion no valida.']);
}
