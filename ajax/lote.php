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

if (!function_exists('proveedorLoteHtml')) {
    function proveedorLoteHtml($reg) {
        if (empty($reg->proveedor)) {
            return '<span class="text-muted">Sin compra vinculada</span>';
        }
        $fecha = !empty($reg->fecha_compra) ? date('d/m/Y', strtotime($reg->fecha_compra)) : '';
        return '<strong>'.htmlspecialchars($reg->proveedor).'</strong>'
             . '<br><small class="text-muted">'.htmlspecialchars(trim($reg->documento)).($fecha ? ' · '.$fecha : '').'</small>';
    }
}

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
                '3' => proveedorLoteHtml($reg),
                '4' => date('d/m/Y', strtotime($reg->fecha_vencimiento)),
                '5' => (int)$reg->dias_restantes,
                '6' => (int)$reg->cantidad_actual,
                '7' => '<span class="label label-'.$urgencia.'">'.(int)$reg->dias_restantes.' días</span>'
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
                '3' => proveedorLoteHtml($reg),
                '4' => date('d/m/Y', strtotime($reg->fecha_vencimiento)),
                '5' => (int)$reg->dias_vencido,
                '6' => (int)$reg->cantidad_actual,
                '7' => '<span class="label label-danger">VENCIDO hace '.(int)$reg->dias_vencido.' días</span>'
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
                       DATEDIFF(l.fecha_vencimiento, CURDATE()) AS dias_restantes,
                       i.idingreso, i.fecha_hora AS fecha_compra, i.tipo_comprobante, i.serie_comprobante, i.num_comprobante,
                       i.estado AS estado_compra, p.nombre AS proveedor
                FROM lote_articulo l
                INNER JOIN articulo a ON a.idarticulo = l.idarticulo
                LEFT JOIN ingreso i ON i.idingreso = l.idingreso
                LEFT JOIN persona p ON p.idpersona = i.idproveedor
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
            // Proveedor y compra de origen del lote
            if (!empty($reg->idingreso)) {
                $provHtml   = '<strong>'.htmlspecialchars($reg->proveedor ?: '-').'</strong>';
                $docCompra  = trim($reg->tipo_comprobante.' '.$reg->serie_comprobante.'-'.$reg->num_comprobante);
                $compraHtml = ($reg->fecha_compra ? date('d/m/Y', strtotime($reg->fecha_compra)) : '-')
                            . '<br><small class="text-muted">'.htmlspecialchars($docCompra).'</small>'
                            . ($reg->estado_compra === 'Borrador' ? ' <span class="label label-warning">Borrador</span>' : '')
                            . ($reg->estado_compra === 'Anulado'  ? ' <span class="label label-danger">Anulada</span>'  : '');
            } else {
                $provHtml   = '<span class="text-muted">Sin compra vinculada</span>';
                $compraHtml = '<span class="text-muted">-</span>';
            }
            $data[] = array(
                '0'  => htmlspecialchars($reg->codigo),
                '1'  => htmlspecialchars($reg->articulo),
                '2'  => htmlspecialchars($reg->numero_lote),
                '3'  => $provHtml,
                '4'  => $compraHtml,
                '5'  => $fFab,
                '6'  => $fVenc,
                '7'  => (int)$reg->cantidad_inicial,
                '8'  => $cant,
                '9'  => $badge,
                '10' => (int)$reg->idlote
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
