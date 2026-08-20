<?php
/**
 * Webhook de Meta WhatsApp Business Cloud API -> AIChat.
 *
 * Independiente del webhook de Green API (ajax/whatsapp_webhook.php).
 *
 * ---------------------------------------------------------------
 * VERIFICACION (una sola vez, al configurar el webhook en Meta):
 *   GET /ajax/meta_webhook.php?hub.mode=subscribe
 *                             &hub.verify_token=<META_VERIFY_TOKEN>
 *                             &hub.challenge=<numero>
 *   -> Debe responder el numero de hub.challenge en texto plano con HTTP 200.
 *
 * MENSAJES ENTRANTES (POST JSON):
 *   {
 *     "object": "whatsapp_business_account",
 *     "entry": [{
 *       "id": "...",
 *       "changes": [{
 *         "value": {
 *           "messaging_product": "whatsapp",
 *           "metadata": { "phone_number_id": "..." },
 *           "contacts": [{ "profile": {"name":"..."}, "wa_id": "51987654321" }],
 *           "messages": [{
 *             "from": "51987654321",
 *             "id": "wamid.xxx",
 *             "timestamp": "1723910400",
 *             "text": { "body": "hola" },
 *             "type": "text"
 *           }]
 *         },
 *         "field": "messages"
 *       }]
 *     }]
 *   }
 *
 * ENVIO DE RESPUESTA:
 *   POST https://graph.facebook.com/v20.0/{PHONE_NUMBER_ID}/messages
 *   Authorization: Bearer {META_ACCESS_TOKEN}
 *   { "messaging_product":"whatsapp", "to":"51987654321",
 *     "type":"text", "text": {"body":"..."} }
 *
 * CONSTANTES ESPERADAS en config/global.php:
 *   META_VERIFY_TOKEN     -> string que TU eliges (se pega en el panel de Meta)
 *   META_ACCESS_TOKEN     -> token permanente de System User con permisos WhatsApp
 *   META_PHONE_NUMBER_ID  -> id numerico del numero WhatsApp registrado en Meta
 *   META_GRAPH_VERSION    -> (opcional) por defecto 'v20.0'
 */

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);

require_once __DIR__ . "/../config/Conexion.php";
require_once __DIR__ . "/../modelos/AIChat.php";

// -------- Blindaje: Meta reintenta agresivamente si no ve 200.
set_exception_handler(function ($e) {
    error_log("[meta_webhook] Excepcion: " . $e->getMessage());
    http_response_code(200);
    echo 'ERR';
    exit;
});
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("[meta_webhook] Fatal: " . $e['message']);
        if (!headers_sent()) http_response_code(200);
        echo 'ERR';
    }
});

// ================================================================
// 1) VERIFICACION GET (handshake con Meta)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode      = $_GET['hub_mode']         ?? $_GET['hub.mode']         ?? '';
    $token     = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge']    ?? $_GET['hub.challenge']    ?? '';

    // Sin parametros: ping manual desde navegador
    if ($mode === '' && $token === '' && $challenge === '') {
        header('Content-Type: text/plain');
        echo "Webhook Meta WhatsApp activo. Configura este URL en Meta con tu VERIFY_TOKEN.";
        exit;
    }

    $verifyExpected = defined('META_VERIFY_TOKEN') ? META_VERIFY_TOKEN : '';
    if ($mode === 'subscribe' && $verifyExpected !== '' && hash_equals($verifyExpected, $token)) {
        http_response_code(200);
        header('Content-Type: text/plain');
        echo $challenge; // Meta espera el challenge EXACTO
        exit;
    }

    http_response_code(403);
    echo 'verify token invalido';
    exit;
}

// ================================================================
// 2) MENSAJE ENTRANTE POST
// ================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(200);
    exit('OK');
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || ($data['object'] ?? '') !== 'whatsapp_business_account') {
    http_response_code(200);
    exit('OK');
}

$mensaje = extraerMensajeMeta($data);
if (!$mensaje || $mensaje['from'] === '' || $mensaje['text'] === '') {
    // Puede ser un status update (delivered, read...) o tipo no soportado.
    http_response_code(200);
    exit('OK');
}

$from        = $mensaje['from'];        // "51987654321" (wa_id, sin +)
$body        = trim($mensaje['text']);
$phoneNumId  = $mensaje['phone_number_id']; // id del numero receptor (Meta)

// -------- Responder 200 YA, procesar despues
http_response_code(200);
if (function_exists('fastcgi_finish_request')) {
    echo 'OK';
    fastcgi_finish_request();
} else {
    ignore_user_abort(true);
    ob_start();
    echo 'OK';
    header('Content-Length: ' . ob_get_length());
    header('Connection: close');
    ob_end_flush();
    @ob_flush();
    flush();
}

// -------- Autorizacion del numero
$auth = buscarAutorizadoMeta($from);
if (!$auth) {
    error_log("[meta_webhook] Numero no autorizado: $from");
    enviarMensajeMeta($from,
        "Tu numero no esta autorizado para usar el asistente de la farmacia. " .
        "Pide al administrador que te de acceso.",
        $phoneNumId
    );
    exit;
}

$idusuario = (int)$auth['idusuario'];

// -------- Comandos rapidos
$cmd = strtolower($body);
if ($cmd === '/nuevo' || $cmd === 'nuevo' || $cmd === '/reset') {
    actualizarIdConvMeta($auth['id'], null);
    enviarMensajeMeta($from, "Listo, empezamos una conversacion nueva. Escribeme tu pregunta.", $phoneNumId);
    exit;
}
if ($cmd === '/ayuda' || $cmd === 'ayuda' || $cmd === 'help') {
    enviarMensajeMeta($from,
        "Soy el asistente IA de la farmacia. Preguntame en lenguaje natural, por ejemplo:\n\n" .
        "- Cuanto vendi hoy?\n" .
        "- Top 5 productos mas vendidos esta semana\n" .
        "- Que medicamentos vencen este mes?\n" .
        "- Stock de paracetamol\n\n" .
        "Comandos:\n" .
        "  /nuevo  - arrancar nueva conversacion\n" .
        "  /ayuda  - ver esta ayuda",
        $phoneNumId
    );
    exit;
}

// -------- Decidir idconv y llamar a AIChat
$idconv = decidirIdConvMeta($auth);

$chat = new AIChat();
$r = $chat->preguntar($body, $idusuario, $idconv);

if (!empty($r['idconv'])) {
    actualizarIdConvMeta($auth['id'], $r['idconv']);
}

if (!empty($r['ok'])) {
    $texto = $r['respuesta'];
    if (!empty($r['num_filas']) && $r['num_filas'] > 1) {
        $texto .= "\n\n(" . $r['num_filas'] . " registros consultados)";
    }
} else {
    $texto = "No pude resolver tu consulta: " . ($r['message'] ?? 'error desconocido') .
             "\n\nIntenta reformular o escribe /ayuda para ejemplos.";
}

// Meta limita texto a 4096 caracteres
if (strlen($texto) > 3500) {
    $texto = substr($texto, 0, 3490) . "\n[...]";
}

enviarMensajeMeta($from, $texto, $phoneNumId);
exit;


// ================================================================
// Helpers
// ================================================================

function extraerMensajeMeta(array $data)
{
    $entry   = $data['entry'][0]   ?? null;
    $change  = $entry['changes'][0] ?? null;
    $value   = $change['value']    ?? null;
    if (!$value) return null;

    $phoneNumId = $value['metadata']['phone_number_id'] ?? '';
    $messages   = $value['messages'] ?? [];
    if (empty($messages)) return null;

    $msg = $messages[0];
    $tipo = $msg['type'] ?? '';

    $texto = '';
    if ($tipo === 'text') {
        $texto = $msg['text']['body'] ?? '';
    } elseif ($tipo === 'button') {
        $texto = $msg['button']['text'] ?? '';
    } elseif ($tipo === 'interactive') {
        // reply de botones o listas
        $inter = $msg['interactive'] ?? [];
        if (($inter['type'] ?? '') === 'button_reply') {
            $texto = $inter['button_reply']['title'] ?? '';
        } elseif (($inter['type'] ?? '') === 'list_reply') {
            $texto = $inter['list_reply']['title'] ?? '';
        }
    } else {
        // audio, image, sticker, etc. no soportados
        return null;
    }

    return [
        'from'            => preg_replace('/\D+/', '', $msg['from'] ?? ''),
        'text'            => $texto,
        'phone_number_id' => $phoneNumId,
    ];
}

function buscarAutorizadoMeta($telefono)
{
    global $conexion;
    $t = $conexion->real_escape_string($telefono);
    $sql = "SELECT id, idusuario, alias, idconv_actual, ultima_actividad, activo
            FROM whatsapp_autorizado
            WHERE telefono='$t' AND activo=1
            LIMIT 1";
    $rs = $conexion->query($sql);
    if (!$rs || $rs->num_rows === 0) return null;
    return $rs->fetch_assoc();
}

function decidirIdConvMeta(array $auth)
{
    if (empty($auth['idconv_actual']) || empty($auth['ultima_actividad'])) return null;
    $minutos = defined('WA_INACTIVIDAD_MIN') ? (int)WA_INACTIVIDAD_MIN : 30;
    $ultima = strtotime($auth['ultima_actividad']);
    if ($ultima === false) return null;
    if ((time() - $ultima) > $minutos * 60) return null;
    return $auth['idconv_actual'];
}

function actualizarIdConvMeta($id, $idconv)
{
    global $conexion;
    $id = (int)$id;
    $ic = $idconv ? "'" . $conexion->real_escape_string($idconv) . "'" : "NULL";
    $conexion->query("UPDATE whatsapp_autorizado
                      SET idconv_actual=$ic, ultima_actividad=NOW()
                      WHERE id=$id");
}

function enviarMensajeMeta($to, $texto, $phoneNumberId = null)
{
    if (!defined('META_ACCESS_TOKEN') || META_ACCESS_TOKEN === '') {
        error_log("[meta_webhook] Falta configurar META_ACCESS_TOKEN");
        return false;
    }
    // Si Meta nos dio el phone_number_id en el webhook, usarlo. Si no, usar constante.
    if (!$phoneNumberId) {
        if (!defined('META_PHONE_NUMBER_ID') || META_PHONE_NUMBER_ID === '') {
            error_log("[meta_webhook] Falta configurar META_PHONE_NUMBER_ID");
            return false;
        }
        $phoneNumberId = META_PHONE_NUMBER_ID;
    }
    $version = defined('META_GRAPH_VERSION') && META_GRAPH_VERSION !== '' ? META_GRAPH_VERSION : 'v20.0';

    $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";

    $body = [
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'text',
        'text'              => ['body' => $texto, 'preview_url' => false],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . META_ACCESS_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($http < 200 || $http >= 300) {
        error_log("[meta_webhook] Error enviando a $to (HTTP $http): $resp | curl: $err");
        return false;
    }
    return true;
}
