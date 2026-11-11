<?php
if (strlen(session_id()) < 1) {
    session_start();
}
require_once "../modelos/Landing.php";

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['idusuario']) || empty($_SESSION['acceso']) || $_SESSION['acceso'] != 1) {
    echo json_encode(array('ok' => false, 'message' => 'Solo un administrador puede editar la página web'));
    exit;
}

$landing = new Landing();
$op = isset($_GET['op']) ? $_GET['op'] : '';
$seccion = isset($_POST['seccion']) ? preg_replace('/[^a-z_]/', '', $_POST['seccion']) : '';

switch ($op) {
    case 'cargar':
        echo json_encode(array(
            'ok'   => true,
            'data' => array(
                'esquema'    => Landing::esquema(),
                'valores'    => $landing->obtener(),
                'migracion'  => $landing->tablaDisponible(),
            ),
        ), JSON_UNESCAPED_UNICODE);
        break;

    case 'guardar':
        $datos = json_decode(isset($_POST['datos']) ? $_POST['datos'] : '', true);
        if (!is_array($datos)) {
            echo json_encode(array('ok' => false, 'message' => 'No se recibieron los datos del formulario'));
            break;
        }
        echo json_encode($landing->guardar($seccion, $datos), JSON_UNESCAPED_UNICODE);
        break;

    case 'restaurar':
        echo json_encode($landing->restaurar($seccion), JSON_UNESCAPED_UNICODE);
        break;

    case 'subirImagen':
        echo json_encode($landing->subirImagen(isset($_FILES['imagen']) ? $_FILES['imagen'] : null));
        break;

    case 'buscarArticulos':
        $ids = isset($_POST['ids']) ? array_filter(array_map('intval', explode(',', $_POST['ids']))) : array();
        $texto = isset($_POST['q']) ? $_POST['q'] : '';
        echo json_encode(array('ok' => true, 'data' => $landing->buscarArticulos($texto, $ids)), JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(array('ok' => false, 'message' => 'Operación no válida'));
}
