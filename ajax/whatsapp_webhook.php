<?php
/**
 * Webhook de Green API (WhatsApp) -> AIChat.
 *
 * Green API envia POST con JSON. Nos interesan solo los eventos 'incomingMessageReceived'
 * con mensaje de texto. El resto (status, historial) los ignoramos con 200 OK.
 *
 * Estructura relevante del JSON entrante:
 *   {
 *     "typeWebhook": "incomingMessageReceived",
 *     "senderData": { "sender": "51987654321@c.us", "chatId": "51987654321@c.us" },
 *     "messageData": {
 *        "typeMessage": "textMessage",
 *        "textMessageData": { "textMessage": "hola" }
 *     }
 *   }
 *
 * Para responder llamamos al endpoint:
 *   POST https://api.green-api.com/waInstance{ID}/sendMessage/{TOKEN}
 *   body: { "chatId": "51987654321@c.us", "message": "..." }
 *
 * Configurar en Green API (panel -> Instance -> Settings):
 *   Webhook URL:  https://TU-DOMINIO/farmacia/ajax/whatsapp_webhook.php
 *   Activar:      "Incoming webhook" y "Outgoing message webhook" (al menos incoming)
 *   (Opcional) Webhook Token: pega el valor de GREEN_WEBHOOK_TOKEN
 */

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);

require_once __DIR__ . "/../config/Conexion.php";
require_once __DIR__ . "/../modelos/AIChat.php";

// -------- Blindaje: responder 200 siempre para que Green no reintente en bucle.
set_exception_handler(function ($e) {
    error_log("[wa_webhook] Excepcion: " . $e->getMessage());
    http_response_code(200);
    echo 'ERR';
    exit;
});
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("[wa_webhook] Fatal: " . $e['message']);
        if (!headers_sent()) http_response_code(200);
        echo 'ERR';
    }
});

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Ping simple para verificar que el endpoint responde
    header('Content-Type: text/plain');
    echo "Webhook WhatsApp activo. Envia POST desde Green API.";
    exit;
}

// -------- Validar token del webhook (opcional)
if (defined('GREEN_VALIDAR_TOKEN') && GREEN_VALIDAR_TOKEN) {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $esperado = 'Bearer ' . GREEN_WEBHOOK_TOKEN;
    if (!hash_equals($esperado, $auth)) {
        http_response_code(403);
        exit('Token invalido.');
    }
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(200);
    exit('OK');
}

// Solo procesamos mensajes entrantes de texto
$tipo = $data['typeWebhook'] ?? '';
if ($tipo !== 'incomingMessageReceived') {
    http_response_code(200);
    exit('OK');
}

$mensaje = extraerMensaje($data);
if (!$mensaje || $mensaje['from'] === '' || $mensaje['text'] === '') {
    http_response_code(200);
    exit('OK');
}

// Ignorar mensajes de grupos (chatId termina en @g.us)
if (str_ends_with($mensaje['chatId'], '@g.us')) {
    http_response_code(200);
    exit('OK');
}

$from   = $mensaje['from'];    // "51987654321"
$chatId = $mensaje['chatId'];  // "51987654321@c.us"
$body   = trim($mensaje['text']);

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
$auth = buscarAutorizado($from);
if (!$auth) {
    error_log("[wa_webhook] Numero no autorizado: $from");
    enviarMensaje($chatId,
        "Tu numero no esta autorizado para usar el asistente de la farmacia. " .
        "Pide al administrador que te de acceso."
    );
    exit;
}

$idusuario = (int)$auth['idusuario'];

// -------- Comandos rapidos
$cmd = strtolower($body);
if ($cmd === '/nuevo' || $cmd === 'nuevo' || $cmd === '/reset') {
    actualizarIdConv($auth['id'], null);
    enviarMensaje($chatId, "Listo, empezamos una conversacion nueva. Escribeme tu pregunta.");
    exit;
}
if ($cmd === '/ayuda' || $cmd === 'ayuda' || $cmd === 'help') {
    enviarMensaje($chatId,
        "Soy el asistente IA de la farmacia. Preguntame en lenguaje natural, por ejemplo:\n\n" .
        "- Cuanto vendi hoy?\n" .
        "- Top 5 productos mas vendidos esta semana\n" .
        "- Que medicamentos vencen este mes?\n" .
        "- Stock de paracetamol\n\n" .
        "Comandos:\n" .
        "  /nuevo  - arrancar nueva conversacion\n" .
        "  /ayuda  - ver esta ayuda"
    );
    exit;
}

// -------- Decidir idconv y llamar a AIChat
$idconv = decidirIdConv($auth);

$chat = new AIChat();
$r = $chat->preguntar($body, $idusuario, $idconv);

if (!empty($r['idconv'])) {
    actualizarIdConv($auth['id'], $r['idconv']);
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

if (strlen($texto) > 3500) {
    $texto = substr($texto, 0, 3490) . "\n[...]";
}

enviarMensaje($chatId, $texto);
exit;


// ================================================================
// Helpers
// ================================================================

function extraerMensaje(array $data)
{
    $sender  = $data['senderData']['sender']  ?? '';
    $chatId  = $data['senderData']['chatId']  ?? $sender;
    $tipoMsg = $data['messageData']['typeMessage'] ?? '';

    // Solo texto por ahora. Green API tiene otros tipos: extendedTextMessage, imageMessage, etc.
    $texto = '';
    if ($tipoMsg === 'textMessage') {
        $texto = $data['messageData']['textMessageData']['textMessage'] ?? '';
    } elseif ($tipoMsg === 'extendedTextMessage') {
        $texto = $data['messageData']['extendedTextMessageData']['text'] ?? '';
    } else {
        return null;
    }

    return [
        'from'   => preg_replace('/\D+/', '', $sender), // "51987654321"
        'chatId' => $chatId,                            // "51987654321@c.us"
        'text'   => $texto,
    ];
}

function buscarAutorizado($telefono)
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

function decidirIdConv(array $auth)
{
    if (empty($auth['idconv_actual']) || empty($auth['ultima_actividad'])) return null;
    $minutos = defined('WA_INACTIVIDAD_MIN') ? (int)WA_INACTIVIDAD_MIN : 30;
    $ultima = strtotime($auth['ultima_actividad']);
    if ($ultima === false) return null;
    if ((time() - $ultima) > $minutos * 60) return null;
    return $auth['idconv_actual'];
}

function actualizarIdConv($id, $idconv)
{
    global $conexion;
    $id = (int)$id;
    $ic = $idconv ? "'" . $conexion->real_escape_string($idconv) . "'" : "NULL";
    $conexion->query("UPDATE whatsapp_autorizado
                      SET idconv_actual=$ic, ultima_actividad=NOW()
                      WHERE id=$id");
}

function enviarMensaje($chatId, $texto)
{
    if (!defined('GREEN_ID_INSTANCE') || GREEN_ID_INSTANCE === '' ||
        !defined('GREEN_API_TOKEN')   || GREEN_API_TOKEN   === '') {
        error_log("[wa_webhook] Falta configurar GREEN_ID_INSTANCE o GREEN_API_TOKEN");
        return false;
    }

    $url  = "https://api.green-api.com/waInstance" . GREEN_ID_INSTANCE
          . "/sendMessage/" . GREEN_API_TOKEN;

    $body = [
        'chatId'  => $chatId,
        'message' => $texto,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($http < 200 || $http >= 300) {
        error_log("[wa_webhook] Error enviando a $chatId (HTTP $http): $resp | curl: $err");
        return false;
    }
    return true;
}
