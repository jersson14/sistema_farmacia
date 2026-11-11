<?php
// ============================================================
// Autorización de acciones sensibles (anular / eliminar / editar)
// ============================================================
// Un usuario puede hacer la acción si:
//   a) tiene el permiso en su sesión (o es administrador: permiso Acceso), o
//   b) un administrador la autorizó con su usuario y clave para ESE registro.
//
// Las autorizaciones viven en $_SESSION['autorizaciones'][recurso:id] y valen
// solo para ese registro. Las acciones finales (anular, eliminar) las consumen;
// la edición (compras, proveedores) se renueva con cada uso mientras dure.
// Requiere config/Conexion.php cargado y la sesión iniciada.

require_once __DIR__ . "/Conexion.php";

if (!defined('AUT_MINUTOS')) {
	define('AUT_MINUTOS', 15);        // vigencia de una autorización sin uso
	define('AUT_MAX_INTENTOS', 5);    // claves erradas antes del bloqueo
	define('AUT_BLOQUEO_SEG', 120);   // bloqueo tras demasiados intentos
}

// recurso => [clave de sesión, idpermiso, etiqueta para mensajes]
function autRecursos() {
	return array(
		'venta'     => array('permiso' => 'ventas_anular',  'id' => 14, 'nombre' => 'Anular y eliminar ventas'),
		'ingreso'   => array('permiso' => 'compras_editar', 'id' => 15, 'nombre' => 'Editar y anular compras'),
		'proveedor' => array('permiso' => 'compras_editar', 'id' => 15, 'nombre' => 'Editar y anular compras'),
	);
}

// ¿El usuario en sesión tiene el permiso propio? (el administrador siempre)
function autTienePermiso($clavePermiso) {
	return !empty($_SESSION[$clavePermiso]) || !empty($_SESSION['acceso']);
}

function autLlave($recurso, $id) {
	return $recurso . ':' . (int)$id;
}

// ¿Puede hacer la acción sobre este registro? (permiso propio o autorización vigente)
function autPuede($recurso, $id, $renovar = true) {
	$rec = autRecursos();
	if (!isset($rec[$recurso])) return false;
	if (autTienePermiso($rec[$recurso]['permiso'])) return true;
	$k = autLlave($recurso, $id);
	if (empty($_SESSION['autorizaciones'][$k])) return false;
	$a = $_SESSION['autorizaciones'][$k];
	if ($a['expira'] < time()) {
		unset($_SESSION['autorizaciones'][$k]);
		return false;
	}
	// uso activo: renueva la vigencia
	if ($renovar) $_SESSION['autorizaciones'][$k]['expira'] = time() + AUT_MINUTOS * 60;
	return true;
}

// Las acciones finales (anular, eliminar) gastan la autorización
function autConsumir($recurso, $id) {
	unset($_SESSION['autorizaciones'][autLlave($recurso, $id)]);
}

// Respuesta estándar cuando falta autorización: el JS la detecta y abre el modal
function autRespuestaRequerida($mensaje = '') {
	return array(
		'ok' => false,
		'requiere_autorizacion' => true,
		'message' => $mensaje !== '' ? $mensaje : 'Esta acción necesita la autorización de un administrador.'
	);
}

// Valida usuario + clave de quien autoriza. Devuelve [ok, message, usuario]
function autValidarAutorizador($login, $clave, $recurso) {
	$rec = autRecursos();
	$idPermiso = (int)$rec[$recurso]['id'];
	$login = limpiarCadena($login);
	$hash  = hash('SHA256', (string)$clave);
	$u = ejecutarConsultaSimpleFila("SELECT idusuario, nombre FROM usuario
		WHERE login='$login' AND clave='$hash' AND condicion='1' LIMIT 1");
	if (!$u) {
		return array(false, 'Usuario o clave incorrectos.', null);
	}
	$idu = (int)$u['idusuario'];
	$p = ejecutarConsultaSimpleFila("SELECT COUNT(*) AS n FROM usuario_permiso
		WHERE idusuario='$idu' AND idpermiso IN (5, $idPermiso)");
	if (!$p || (int)$p['n'] === 0) {
		return array(false, $u['nombre'] . ' no tiene permiso para autorizar esta acción. Pide ayuda a un administrador.', null);
	}
	return array(true, '', $u);
}

// Registra la autorización (si la tabla aún no existe, no rompe el flujo)
function autRegistrarLog($recurso, $id, $accion, $idSolicita, $idAutoriza) {
	$rec = autRecursos();
	$permiso = limpiarCadena($rec[$recurso]['permiso']);
	$recurso = limpiarCadena($recurso);
	$accion  = limpiarCadena(mb_substr((string)$accion, 0, 150));
	$id = (int)$id; $idSolicita = (int)$idSolicita; $idAutoriza = (int)$idAutoriza;
	ejecutarConsulta("INSERT INTO autorizacion_log (permiso, recurso, id_registro, accion, idusuario_solicita, idusuario_autoriza)
		VALUES ('$permiso', '$recurso', '$id', '$accion', '$idSolicita', '$idAutoriza')");
}

// Permisos de acción que el JS necesita para pintar candados y saltar el modal
function autPermisosParaJs() {
	return array(
		'ventas_anular'  => autTienePermiso('ventas_anular'),
		'compras_editar' => autTienePermiso('compras_editar'),
	);
}

// Ícono de candado para botones que pedirán autorización
function autCandado($recurso, $id) {
	return autPuede($recurso, $id, false) ? '' : ' <i class="fa fa-lock aut-lock" title="Requiere autorización del administrador"></i>';
}
