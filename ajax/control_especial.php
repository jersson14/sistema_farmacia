<?php
if (strlen(session_id()) < 1) session_start();
if (!isset($_SESSION['nombre'])) {
    echo json_encode(array('ok'=>false,'message'=>'Sesion expirada'));
    exit;
}

require_once "../modelos/ControlEspecial.php";

if (!function_exists('fechaFiltroSeguro')) {
    function fechaFiltroSeguro($valor) {
        $valor = trim((string)$valor);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? $valor : '';
    }
}

$op = $_GET['op'] ?? '';
$ce = new ControlEspecial();

switch ($op) {

    case 'guardarDesdeVenta':
        $idventa      = isset($_POST['idventa'])      ? (int)$_POST['idventa']      : 0;
        $idreceta     = isset($_POST['idreceta'])      ? (int)$_POST['idreceta']     : 0;
        $idusuario_qf = isset($_SESSION['idusuario']) ? (int)$_SESSION['idusuario'] : 0;
        $diagnostico  = isset($_POST['diagnostico'])  ? trim((string)$_POST['diagnostico']) : '';

        if ($idventa <= 0) {
            echo json_encode(array('ok'=>false,'message'=>'ID de venta invalido'));
            break;
        }
        $ok = $ce->guardarDesdeVenta($idventa, $idreceta ?: null, $idusuario_qf ?: null, $diagnostico);
        echo json_encode(array('ok'=>(bool)$ok));
        break;

    case 'listar':
        $fi = fechaFiltroSeguro(isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '');
        $ff = fechaFiltroSeguro(isset($_GET['fecha_fin'])    ? $_GET['fecha_fin']    : '');
        $rs = $ce->listar($fi, $ff);
        $data = array();
        while ($reg = $rs->fetch_object()) {
            $data[] = array(
                "0"  => $reg->fecha_registro,
                "1"  => htmlspecialchars($reg->medicamento),
                "2"  => htmlspecialchars($reg->paciente),
                "3"  => number_format((float)$reg->cantidad, 0),
                "4"  => number_format((float)$reg->saldo, 0),
                "5"  => htmlspecialchars($reg->numero_lote),
                "6"  => $reg->fecha_vencimiento,
                "7"  => htmlspecialchars($reg->nombre_medico),
                "8"  => htmlspecialchars($reg->colegiatura),
                "9"  => htmlspecialchars($reg->nombre_qf),
                "10" => htmlspecialchars($reg->diagnostico),
                "11" => '<a target="_blank" href="../reportes/exTicket.php?id='.(int)$reg->idventa.'" class="btn btn-xs btn-info"><i class="fa fa-file"></i> Venta #'.(int)$reg->idventa.'</a>'
            );
        }
        echo json_encode(array(
            "sEcho"              => 1,
            "iTotalRecords"      => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData"             => $data
        ));
        break;

    default:
        echo json_encode(array('ok'=>false,'message'=>'Operacion no reconocida'));
}
?>
