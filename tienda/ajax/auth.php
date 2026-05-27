<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

if (strlen(session_id()) < 1) session_start();
header('Content-Type: application/json; charset=utf-8');

function authJson($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

try {
    require_once "../../modelos/ClienteTienda.php";
} catch (Throwable $e) {
    authJson(array('ok'=>false,'message'=>'Error al cargar modelo: ' . $e->getMessage()));
}

$op = $_GET['op'] ?? '';

try {

switch ($op) {

    // ── REGISTRO ──────────────────────────────────────────
    case 'registro':
        $nombre    = trim($_POST['nombre']    ?? '');
        $email     = trim($_POST['email']     ?? '');
        $password  = trim($_POST['password']  ?? '');
        $password2 = trim($_POST['password2'] ?? '');
        $telefono  = trim($_POST['telefono']  ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $distrito  = trim($_POST['distrito']  ?? '');

        if (!$nombre || !$email || !$password)
            authJson(array('ok'=>false,'message'=>'Nombre, email y contraseña son obligatorios.'));
        if ($password !== $password2)
            authJson(array('ok'=>false,'message'=>'Las contraseñas no coinciden.'));
        if (strlen($password) < 6)
            authJson(array('ok'=>false,'message'=>'La contraseña debe tener al menos 6 caracteres.'));

        $mdl = new ClienteTienda();
        $r   = $mdl->registrar($nombre, $email, $password, $telefono, $direccion, $distrito);

        if ($r === false)
            authJson(array('ok'=>false,'message'=>'No se pudo crear la cuenta. Asegurate de haber ejecutado migrations/20260525_tienda_online.sql en phpMyAdmin.'));
        if (is_array($r) && !$r['ok'])
            authJson($r);

        $_SESSION['tienda_cliente'] = array(
            'idcliente_tienda' => $r['idcliente_tienda'],
            'nombre'           => $r['nombre'],
            'email'            => $r['email'],
            'telefono'         => $r['telefono']  ?? '',
            'direccion'        => $r['direccion'] ?? '',
            'distrito'         => $r['distrito']  ?? ''
        );
        authJson(array('ok'=>true,'message'=>'¡Cuenta creada! Bienvenido/a, ' . $r['nombre'] . '.'));

    // ── LOGIN ─────────────────────────────────────────────
    case 'login':
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$email || !$password)
            authJson(array('ok'=>false,'message'=>'Ingresa tu email y contraseña.'));

        $mdl = new ClienteTienda();
        $r   = $mdl->login($email, $password);

        if (!$r)
            authJson(array('ok'=>false,'message'=>'Email o contraseña incorrectos.'));
        if (is_array($r) && isset($r['ok']) && !$r['ok'])
            authJson($r);

        $_SESSION['tienda_cliente'] = array(
            'idcliente_tienda' => $r['idcliente_tienda'],
            'nombre'           => $r['nombre'],
            'email'            => $r['email'],
            'telefono'         => $r['telefono']  ?? '',
            'direccion'        => $r['direccion'] ?? '',
            'distrito'         => $r['distrito']  ?? ''
        );
        authJson(array('ok'=>true,'message'=>'Sesión iniciada. ¡Bienvenido/a, ' . $r['nombre'] . '!'));

    // ── LOGOUT ────────────────────────────────────────────
    case 'logout':
        unset($_SESSION['tienda_cliente']);
        authJson(array('ok'=>true));

    // ── ESTADO ───────────────────────────────────────────
    case 'estado':
        if (!empty($_SESSION['tienda_cliente'])) {
            authJson(array('ok'=>true,'logado'=>true,'nombre'=>$_SESSION['tienda_cliente']['nombre']));
        }
        authJson(array('ok'=>true,'logado'=>false));

    default:
        authJson(array('ok'=>false,'message'=>'Operacion no reconocida: ' . htmlspecialchars($op)));
}

} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (strpos($msg, "doesn't exist") !== false || strpos($msg, 'cliente_tienda') !== false) {
        authJson(array('ok'=>false,'message'=>'Tabla cliente_tienda no existe. Ejecuta migrations/20260525_tienda_online.sql en phpMyAdmin.'));
    }
    authJson(array('ok'=>false,'message'=>'Error: ' . $msg));
}
?>
