<?php
if (strlen(session_id()) < 1) session_start();
if (!isset($_SESSION['nombre'])) {
    echo json_encode(array('ok'=>false,'message'=>'Sesion expirada'));
    exit;
}
require_once "../modelos/Receta.php";

$receta = new Receta();
$op     = $_GET['op'] ?? '';

switch ($op) {
    case 'guardar':
        $idventa         = isset($_POST['idventa'])         ? (int)$_POST['idventa']                    : 0;
        $idcliente       = isset($_POST['idcliente'])       ? (int)$_POST['idcliente']                   : 0;
        $nombre_medico   = isset($_POST['nombre_medico'])   ? limpiarCadena($_POST['nombre_medico'])     : '';
        $colegiatura     = isset($_POST['colegiatura'])     ? limpiarCadena($_POST['colegiatura'])       : '';
        $establecimiento = isset($_POST['establecimiento']) ? limpiarCadena($_POST['establecimiento'])   : '';
        $fecha_emision   = isset($_POST['fecha_emision'])   ? trim($_POST['fecha_emision'])              : '';
        $tipo_receta     = isset($_POST['tipo_receta'])     ? limpiarCadena($_POST['tipo_receta'])       : 'SIMPLE';
        $observaciones   = isset($_POST['observaciones'])   ? limpiarCadena($_POST['observaciones'])     : '';

        if ($idventa <= 0) {
            echo json_encode(array('ok'=>false,'message'=>'ID de venta invalido'));
            break;
        }
        if ($nombre_medico === '') {
            echo json_encode(array('ok'=>false,'message'=>'El nombre del medico es obligatorio'));
            break;
        }

        $idnew = $receta->insertar($idventa, $idcliente, $nombre_medico, $colegiatura, $establecimiento, $fecha_emision, $tipo_receta, $observaciones);
        if ($idnew) {
            echo json_encode(array('ok'=>true, 'idreceta'=>(int)$idnew, 'message'=>'Receta registrada'));
        } else {
            echo json_encode(array('ok'=>false,'message'=>'No se pudo registrar la receta'));
        }
        break;

    case 'listarPorVenta':
        $idventa = isset($_GET['idventa']) ? (int)$_GET['idventa'] : 0;
        $r = $receta->listarPorVenta($idventa);
        echo json_encode($r ? array('ok'=>true,'data'=>$r) : array('ok'=>false,'data'=>null));
        break;

    default:
        echo json_encode(array('ok'=>false,'message'=>'Operacion no reconocida'));
}
?>
