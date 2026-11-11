<?php
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
header('Content-Type: application/json; charset=utf-8');

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

require_once __DIR__ . "/../modelos/AIReporte.php";
// Demo: no se cambian destinatarios ni se envía a correos escritos a mano
require_once __DIR__ . "/../config/demo.php";
if (DEMO_MODE && (($_GET['op'] ?? ($_POST['op'] ?? '')) === 'guardar_destinatarios' || trim($_POST['destinatarios_override'] ?? '') !== '')) demoBloquearAjax();
$rep = new AIReporte();

$op = $_GET['op'] ?? ($_POST['op'] ?? '');

switch ($op) {

    case 'listar':
        $rs = $rep->listarDefiniciones();
        $out = [];
        while ($r = $rs->fetch_assoc()) $out[] = $r;
        echo json_encode(['ok' => true, 'data' => $out]);
        break;

    case 'guardar_destinatarios':
        $codigo = $_POST['codigo'] ?? '';
        $destinatarios = $_POST['destinatarios'] ?? '';
        // Validacion rapida: solo correos separados por coma
        $lista = array_filter(array_map('trim', explode(',', $destinatarios)));
        foreach ($lista as $c) {
            if (!filter_var($c, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['ok' => false, 'message' => "Correo invalido: $c"]);
                exit;
            }
        }
        $ok = $rep->guardarDestinatarios($codigo, implode(',', $lista));
        echo json_encode(['ok' => (bool)$ok, 'message' => $ok ? 'Destinatarios guardados.' : 'Error al guardar.']);
        break;

    case 'enviar':
        $codigo = $_POST['codigo'] ?? '';
        $fecha  = $_POST['fecha']  ?? null;
        $dias   = $_POST['dias']   ?? null;
        $destinatariosOverride = trim($_POST['destinatarios_override'] ?? '');

        $opts = ['idusuario' => $_SESSION['idusuario']];
        if ($fecha) $opts['fecha'] = $fecha;
        if ($dias)  $opts['dias']  = (int)$dias;
        if ($destinatariosOverride !== '') $opts['destinatarios'] = $destinatariosOverride;

        $r = $rep->ejecutar($codigo, $opts);
        echo json_encode($r);
        break;

    case 'historial':
        $codigo = $_GET['codigo'] ?? null;
        $rs = $rep->listarLog($codigo, 30);
        $out = [];
        while ($r = $rs->fetch_assoc()) $out[] = $r;
        echo json_encode(['ok' => true, 'data' => $out]);
        break;

    case 'ver_log':
        $idlog = (int)($_GET['idlog'] ?? 0);
        $log = $rep->obtenerLog($idlog);
        if (!$log) { echo json_encode(['ok' => false, 'message' => 'No encontrado.']); exit; }
        // Devolver como HTML directo si se pide con vista=html
        if (($_GET['vista'] ?? '') === 'html') {
            header('Content-Type: text/html; charset=utf-8');
            echo $log['html_resultado'] ?: '<p>Sin contenido.</p>';
            exit;
        }
        echo json_encode(['ok' => true, 'data' => $log]);
        break;

    case 'preview':
        // Genera el HTML pero NO envia. Sirve para revisar antes de enviar.
        $codigo = $_POST['codigo'] ?? '';
        $fecha  = $_POST['fecha']  ?? null;
        $dias   = (int)($_POST['dias'] ?? 60);
        try {
            if ($codigo === 'cierre_diario') {
                $datos = $rep->datosCierreDiario($fecha ?: date('Y-m-d'));
                $g = $rep->generarHTMLCierreDiario($datos);
            } elseif ($codigo === 'vencimientos') {
                $datos = $rep->datosVencimientos($dias);
                $g = $rep->generarHTMLVencimientos($datos);
            } elseif ($codigo === 'stock_critico') {
                $datos = $rep->datosStockCritico();
                $g = $rep->generarHTMLStockCritico($datos);
            } else {
                echo json_encode(['ok' => false, 'message' => "Preview para '$codigo' no implementado aun."]);
                exit;
            }
            echo json_encode(['ok' => true, 'html' => $g['html'], 'tokens' => $g['tokens_prompt'] + $g['tokens_respuesta']]);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Operacion no valida.']);
}
