<?php
// Endpoint devuelve SIEMPRE JSON limpio. Sin warnings, sin HTML de errores.
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

// Solo admin (acceso=1) o quien tenga permiso IA
$esAdmin = !empty($_SESSION['acceso']) && $_SESSION['acceso'] == 1;
$tieneIA = !empty($_SESSION['ia']) && $_SESSION['ia'] == 1;
if (!$esAdmin && !$tieneIA) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'No tienes permiso para acceder a Integraciones IA.']);
    exit;
}

require_once __DIR__ . "/../modelos/AIConfig.php";
require_once __DIR__ . "/../modelos/AIService.php";

$op = $_GET['op'] ?? ($_POST['op'] ?? '');
$config = new AIConfig();

switch ($op) {

    case 'obtener':
        $data = $config->obtener();
        // Enmascarar valores sensibles para el frontend (no queremos re-enviar la key completa en cada carga)
        if ($data) {
            $data['openai_api_key_mask'] = $data['openai_api_key']
                ? substr($data['openai_api_key'], 0, 6) . '****' . substr($data['openai_api_key'], -4)
                : '';
            $data['smtp_pass_mask'] = $data['smtp_pass'] ? '******' : '';
            unset($data['openai_api_key'], $data['smtp_pass']);
        }
        echo json_encode(['ok' => true, 'data' => $data]);
        break;

    case 'guardar':
        // Si el campo llega vacio, conservar el valor actual (para no borrar credenciales al guardar solo el resto)
        $actual = $config->obtener();
        $payload = $_POST;
        if (trim($payload['openai_api_key'] ?? '') === '') {
            $payload['openai_api_key'] = $actual['openai_api_key'] ?? '';
        }
        if (trim($payload['smtp_pass'] ?? '') === '') {
            $payload['smtp_pass'] = $actual['smtp_pass'] ?? '';
        }
        $ok = $config->guardar($payload);
        echo json_encode(['ok' => (bool)$ok, 'message' => $ok ? 'Configuracion guardada.' : 'Error al guardar.']);
        break;

    case 'probar_openai':
        try {
            $svc = new AIService();
            $r = $svc->chat([
                ['role' => 'system', 'content' => 'Responde con una sola palabra: OK'],
                ['role' => 'user',   'content' => 'ping'],
            ], ['max_tokens' => 5]);
            echo json_encode([
                'ok' => true,
                'message' => 'OpenAI respondio: "' . trim($r['contenido']) . '"',
                'tokens' => $r['tokens_prompt'] + $r['tokens_respuesta'],
            ]);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'message' => 'OpenAI: ' . $e->getMessage()]);
        }
        break;

    case 'probar_smtp':
        try {
            $svc = new AIService();
            $destino = trim($_POST['destino'] ?? '');
            if ($destino === '') throw new RuntimeException('Ingresa un correo destino para la prueba.');
            $svc->enviarCorreo(
                $destino,
                'Prueba SMTP - Farmacia IA',
                '<h2>Prueba exitosa</h2><p>Si recibes este correo, tu configuracion SMTP funciona correctamente.</p>'
                . '<p style="color:#888;font-size:12px;">Enviado desde el modulo de Integraciones IA.</p>'
            );
            echo json_encode(['ok' => true, 'message' => 'Correo enviado a ' . $destino]);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'message' => 'SMTP: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Operacion no valida.']);
}
