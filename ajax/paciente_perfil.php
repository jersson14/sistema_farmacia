<?php
if (strlen(session_id()) < 1) session_start();
if (!isset($_SESSION['nombre'])) {
    echo json_encode(array('ok'=>false,'message'=>'Sin sesion'));
    exit;
}
require_once "../modelos/PacientePerfil.php";

$op  = $_GET['op'] ?? '';
$mdl = new PacientePerfil();

switch ($op) {
    case 'obtener':
        $idpersona = (int)($_GET['idpersona'] ?? 0);
        try {
            $row = $mdl->obtener($idpersona);
            echo json_encode(array('ok'=>(bool)$row, 'data'=> $row ?: array()));
        } catch (Throwable $e) {
            $msg = strpos($e->getMessage(), "doesn't exist") !== false
                ? 'Ejecuta migrations/20260525_paciente_perfil.sql primero.'
                : 'Error: ' . $e->getMessage();
            echo json_encode(array('ok'=>false,'message'=>$msg,'data'=>array()));
        }
        break;

    case 'guardar':
        $idpersona         = (int)($_POST['idpersona'] ?? 0);
        $alergias          = $_POST['alergias']          ?? '';
        $condiciones_cron  = $_POST['condiciones_cron']  ?? '';
        $medicamentos_cron = $_POST['medicamentos_cron'] ?? '';
        $observaciones     = $_POST['observaciones']     ?? '';
        try {
            $ok = $mdl->guardar($idpersona, $alergias, $condiciones_cron, $medicamentos_cron, $observaciones);
            echo json_encode(array('ok'=>(bool)$ok, 'message'=> $ok ? 'Perfil guardado' : 'Error al guardar'));
        } catch (Throwable $e) {
            $msg = strpos($e->getMessage(), "doesn't exist") !== false
                ? 'Ejecuta migrations/20260525_paciente_perfil.sql primero.'
                : 'Error: ' . $e->getMessage();
            echo json_encode(array('ok'=>false,'message'=>$msg));
        }
        break;

    default:
        echo json_encode(array('ok'=>false,'message'=>'Operacion no reconocida'));
}
?>
