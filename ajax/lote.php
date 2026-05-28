<?php
if (strlen(session_id()) < 1) {
    session_start();
}
if (!isset($_SESSION['nombre'])) {
    echo json_encode(array('ok'=>false,'message'=>'Sesion expirada'));
    exit;
}
require_once "../modelos/Lote.php";

$lote = new Lote();
$op   = $_GET['op'] ?? '';

switch ($op) {
    case 'proximosVencer':
        $dias  = isset($_GET['dias']) ? max(1, (int)$_GET['dias']) : 30;
        $rspta = $lote->listarProximosVencer($dias);
        $data  = array();
        while ($reg = $rspta->fetch_object()) {
            $urgencia = (int)$reg->dias_restantes <= 15 ? 'danger' : ((int)$reg->dias_restantes <= 30 ? 'warning' : 'info');
            $data[] = array(
                '0' => htmlspecialchars($reg->codigo),
                '1' => htmlspecialchars($reg->articulo),
                '2' => htmlspecialchars($reg->numero_lote),
                '3' => date('d/m/Y', strtotime($reg->fecha_vencimiento)),
                '4' => (int)$reg->dias_restantes,
                '5' => (int)$reg->cantidad_actual,
                '6' => '<span class="label label-'.$urgencia.'">'.(int)$reg->dias_restantes.' días</span>'
            );
        }
        echo json_encode(array(
            'ok'                  => true,
            'sEcho'               => 1,
            'iTotalRecords'       => count($data),
            'iTotalDisplayRecords'=> count($data),
            'aaData'              => $data
        ));
        break;

    case 'vencidos':
        $rspta = $lote->listarVencidos();
        $data  = array();
        while ($reg = $rspta->fetch_object()) {
            $data[] = array(
                '0' => htmlspecialchars($reg->codigo),
                '1' => htmlspecialchars($reg->articulo),
                '2' => htmlspecialchars($reg->numero_lote),
                '3' => date('d/m/Y', strtotime($reg->fecha_vencimiento)),
                '4' => (int)$reg->dias_vencido,
                '5' => (int)$reg->cantidad_actual,
                '6' => '<span class="label label-danger">VENCIDO hace '.(int)$reg->dias_vencido.' días</span>'
            );
        }
        echo json_encode(array(
            'ok'                  => true,
            'sEcho'               => 1,
            'iTotalRecords'       => count($data),
            'iTotalDisplayRecords'=> count($data),
            'aaData'              => $data
        ));
        break;

    case 'contadores':
        echo json_encode(array(
            'ok'            => true,
            'vencidos'      => $lote->contarVencidos(),
            'vence_30'      => $lote->contarProximosVencer(30),
            'vence_60'      => $lote->contarProximosVencer(60),
            'vence_90'      => $lote->contarProximosVencer(90)
        ));
        break;

    case 'porArticulo':
        $idarticulo = isset($_GET['idarticulo']) ? (int)$_GET['idarticulo'] : 0;
        if ($idarticulo <= 0) {
            echo json_encode(array('ok'=>false,'message'=>'ID de articulo invalido'));
            break;
        }
        $rspta = $lote->listarPorArticulo($idarticulo);
        $lotes = array();
        while ($reg = $rspta->fetch_object()) {
            $lotes[] = array(
                'idlote'            => (int)$reg->idlote,
                'numero_lote'       => $reg->numero_lote,
                'fecha_vencimiento' => $reg->fecha_vencimiento,
                'cantidad_actual'   => (int)$reg->cantidad_actual,
                'dias_restantes'    => (int)$reg->dias_restantes
            );
        }
        echo json_encode(array('ok'=>true,'lotes'=>$lotes));
        break;

    case 'notifVencimiento':
        header('Content-Type: application/json');
        $vencidos = $lote->contarVencidos();
        $proximos = $lote->contarProximosVencer(30);
        $sql = "SELECT a.nombre AS articulo, l.numero_lote, l.fecha_vencimiento,
                       l.cantidad_actual,
                       DATEDIFF(CURDATE(), l.fecha_vencimiento) AS dias_vencido,
                       DATEDIFF(l.fecha_vencimiento, CURDATE()) AS dias_restantes,
                       IF(l.fecha_vencimiento < CURDATE(), 'VENCIDO', 'PROXIMO') AS tipo
                FROM lote_articulo l
                INNER JOIN articulo a ON a.idarticulo = l.idarticulo
                WHERE l.condicion=1
                  AND l.cantidad_actual > 0
                  AND (l.fecha_vencimiento < CURDATE()
                       OR l.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
                ORDER BY l.fecha_vencimiento ASC
                LIMIT 8";
        $rs    = ejecutarConsulta($sql);
        $lotes = [];
        while ($reg = $rs->fetch_assoc()) {
            $lotes[] = [
                'articulo'         => $reg['articulo'],
                'numero_lote'      => $reg['numero_lote'],
                'fecha_vencimiento'=> $reg['fecha_vencimiento'],
                'cantidad_actual'  => (int)$reg['cantidad_actual'],
                'dias_vencido'     => (int)$reg['dias_vencido'],
                'dias_restantes'   => (int)$reg['dias_restantes'],
                'tipo'             => $reg['tipo'],
            ];
        }
        echo json_encode([
            'ok'      => true,
            'vencidos'=> $vencidos,
            'proximos'=> $proximos,
            'lotes'   => $lotes,
        ]);
        break;

    case 'listarTodos':
        $draw = isset($_GET['draw']) ? (int)$_GET['draw'] : (isset($_GET['sEcho']) ? (int)$_GET['sEcho'] : 1);
        $sql = "SELECT l.idlote, a.codigo, a.nombre AS articulo,
                       l.numero_lote,
                       l.fecha_fabricacion,
                       l.fecha_vencimiento,
                       l.cantidad_inicial,
                       l.cantidad_actual,
                       DATEDIFF(l.fecha_vencimiento, CURDATE()) AS dias_restantes
                FROM lote_articulo l
                INNER JOIN articulo a ON a.idarticulo = l.idarticulo
                WHERE l.condicion = 1
                ORDER BY a.nombre ASC, l.fecha_vencimiento ASC";
        $rs   = ejecutarConsulta($sql);
        $data = array();
        while ($reg = $rs->fetch_object()) {
            $dias = (int)$reg->dias_restantes;
            $cant = (int)$reg->cantidad_actual;
            if ($cant <= 0) {
                $badge = '<span class="label label-default">AGOTADO</span>';
            } elseif ($dias < 0) {
                $badge = '<span class="label label-danger">VENCIDO hace '.abs($dias).' d</span>';
            } elseif ($dias <= 30) {
                $badge = '<span class="label label-warning">VENCE en '.$dias.' d</span>';
            } else {
                $badge = '<span class="label label-success">VIGENTE</span>';
            }
            $fVenc = $reg->fecha_vencimiento ? date('d/m/Y', strtotime($reg->fecha_vencimiento)) : '-';
            $fFab  = $reg->fecha_fabricacion  ? date('d/m/Y', strtotime($reg->fecha_fabricacion))  : '-';
            $data[] = array(
                '0' => htmlspecialchars($reg->codigo),
                '1' => htmlspecialchars($reg->articulo),
                '2' => htmlspecialchars($reg->numero_lote),
                '3' => $fFab,
                '4' => $fVenc,
                '5' => (int)$reg->cantidad_inicial,
                '6' => $cant,
                '7' => $badge,
                '8' => (int)$reg->idlote
            );
        }
        echo json_encode(array(
            'draw'                => $draw,
            'sEcho'               => $draw,
            'iTotalRecords'       => count($data),
            'iTotalDisplayRecords'=> count($data),
            'aaData'              => $data
        ));
        break;

    default:
        echo json_encode(array('ok'=>false,'message'=>'Operacion no reconocida'));
}
?>
