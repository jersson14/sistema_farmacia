<?php
// Autorización del administrador para acciones sensibles.
// POST op=solicitar: recurso ("venta:12" | "ingreso:5" | "proveedor:7"), accion, login, clave
// Responde { ok, message, autorizador, minutos }
if (strlen(session_id()) < 1) session_start();
require_once "../config/Autorizacion.php";

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['idusuario'])) {
	echo json_encode(array('ok' => false, 'message' => 'Tu sesión venció. Vuelve a iniciar sesión.'));
	exit;
}

$op = $_GET['op'] ?? '';

switch ($op) {
	case 'solicitar':
		$recursoTxt = isset($_POST['recurso']) ? trim($_POST['recurso']) : '';
		if (!preg_match('/^(venta|ingreso|proveedor):(\d+)$/', $recursoTxt, $m)) {
			echo json_encode(array('ok' => false, 'message' => 'No se pudo identificar el registro. Recarga la página e inténtalo de nuevo.'));
			break;
		}
		$recurso = $m[1];
		$id = (int)$m[2];

		// Bloqueo temporal por demasiados intentos fallidos
		$bloq = $_SESSION['aut_bloqueo_hasta'] ?? 0;
		if ($bloq > time()) {
			$seg = $bloq - time();
			echo json_encode(array('ok' => false, 'message' => "Demasiados intentos fallidos. Espera $seg segundos e inténtalo de nuevo."));
			break;
		}

		$login = isset($_POST['login']) ? trim($_POST['login']) : '';
		$clave = isset($_POST['clave']) ? (string)$_POST['clave'] : '';
		if ($login === '' || $clave === '') {
			echo json_encode(array('ok' => false, 'message' => 'Escribe el usuario y la clave del administrador.'));
			break;
		}

		list($ok, $msg, $aut) = autValidarAutorizador($login, $clave, $recurso);
		if (!$ok) {
			$_SESSION['aut_intentos'] = ($_SESSION['aut_intentos'] ?? 0) + 1;
			if ($_SESSION['aut_intentos'] >= AUT_MAX_INTENTOS) {
				$_SESSION['aut_intentos'] = 0;
				$_SESSION['aut_bloqueo_hasta'] = time() + AUT_BLOQUEO_SEG;
				$msg .= ' Por seguridad, espera 2 minutos antes de volver a intentar.';
			}
			echo json_encode(array('ok' => false, 'message' => $msg));
			break;
		}

		$_SESSION['aut_intentos'] = 0;
		$_SESSION['autorizaciones'][autLlave($recurso, $id)] = array(
			'por'    => (int)$aut['idusuario'],
			'nombre' => $aut['nombre'],
			'expira' => time() + AUT_MINUTOS * 60,
		);
		$accion = isset($_POST['accion']) ? $_POST['accion'] : '';
		autRegistrarLog($recurso, $id, $accion, $_SESSION['idusuario'], $aut['idusuario']);

		echo json_encode(array(
			'ok' => true,
			'message' => 'Autorizado por ' . $aut['nombre'],
			'autorizador' => $aut['nombre'],
			'minutos' => AUT_MINUTOS,
		));
		break;

	default:
		echo json_encode(array('ok' => false, 'message' => 'Operación no válida'));
}
