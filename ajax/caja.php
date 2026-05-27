<?php
if (strlen(session_id()) < 1) {
    session_start();
}
require_once "../modelos/Caja.php";

$caja = new Caja();
$idusuario = $_SESSION['idusuario'];

switch ($_GET['op']) {
    case 'estado':
        $abierta = $caja->cajaAbiertaUsuario($idusuario);
        if (!$abierta) {
            echo json_encode(array('abierta' => false));
            break;
        }
        $res = $caja->resumenCaja($abierta['idcaja']);
        $apertura = (float)$abierta['monto_apertura'];
        $ventas   = (float)$res['ventas_efectivo'];
        $ingresos = (float)$res['ingresos_manuales'];
        $egresos  = (float)$res['total_egresos'];
        $sistema  = $apertura + $ventas + $ingresos - $egresos;

        // Consulta directa a venta para obtener totales Yape/Tarjeta del período de esta caja
        $ventasYape    = 0;
        $ventasTarjeta = 0;
        try {
            $fechaAp = limpiarCadena($abierta['fecha_apertura']);
            $sqlPagos = "SELECT
                IFNULL(SUM(IFNULL(monto_digital,0)),0) AS ventas_yape,
                IFNULL(SUM(IFNULL(monto_tarjeta,0)),0) AS ventas_tarjeta
                FROM venta
                WHERE idusuario='$idusuario'
                AND fecha_hora >= '$fechaAp'
                AND estado='Aceptado'";
            $pagos = ejecutarConsultaSimpleFila($sqlPagos);
            if ($pagos) {
                $ventasYape    = (float)$pagos['ventas_yape'];
                $ventasTarjeta = (float)$pagos['ventas_tarjeta'];
            }
        } catch (Throwable $e) {
            // Si falla la consulta de pagos, se retornan 0 sin romper la respuesta
        }

        echo json_encode(array(
            'abierta'        => true,
            'idcaja'         => $abierta['idcaja'],
            'fecha_apertura' => $abierta['fecha_apertura'],
            'monto_apertura' => $apertura,
            'ventas_efectivo'=> $ventas,
            'ventas_yape'    => $ventasYape,
            'ventas_tarjeta' => $ventasTarjeta,
            'ingresos'       => $ingresos,
            'egresos'        => $egresos,
            'sistema'        => $sistema
        ));
        break;

    case 'abrir':
        $monto_apertura = limpiarCadena($_POST['monto_apertura']);
        $observacion = limpiarCadena($_POST['observacion']);
        $rspta = $caja->abrirCaja($idusuario, $monto_apertura, $observacion);
        echo $rspta ? 'Caja abierta correctamente' : 'Ya tienes una caja abierta';
        break;

    case 'movimiento':
        $abierta = $caja->cajaAbiertaUsuario($idusuario);
        if (!$abierta) {
            echo 'No hay caja abierta';
            break;
        }
        $tipo = limpiarCadena($_POST['tipo']);
        $concepto = limpiarCadena($_POST['concepto']);
        $monto = limpiarCadena($_POST['monto']);
        $rspta = $caja->agregarMovimiento($abierta['idcaja'], $idusuario, $tipo, $concepto, $monto);
        echo $rspta ? 'Movimiento registrado' : 'No se pudo registrar el movimiento';
        break;

    case 'cerrar':
        $abierta = $caja->cajaAbiertaUsuario($idusuario);
        if (!$abierta) {
            echo 'No hay caja abierta';
            break;
        }
        $monto_cierre_real = limpiarCadena($_POST['monto_cierre_real']);
        $observacion = limpiarCadena($_POST['observacion']);
        $rspta = $caja->cerrarCaja($abierta['idcaja'], $monto_cierre_real, $observacion);
        echo $rspta ? 'Caja cerrada correctamente idcaja:'.$abierta['idcaja'] : 'No se pudo cerrar la caja';
        break;

    case 'listarMovimientos':
        $abierta = $caja->cajaAbiertaUsuario($idusuario);
        $data = array();
        if ($abierta) {
            $rspta = $caja->movimientosCaja($abierta['idcaja']);
            while ($reg = $rspta->fetch_object()) {
                $data[] = array(
                    '0' => date('d/m/Y H:i', strtotime($reg->fecha_hora)),
                    '1' => $reg->tipo,
                    '2' => $reg->concepto,
                    '3' => formatearMoneda((float)$reg->monto),
                    '4' => $reg->usuario
                );
            }
        }

        echo json_encode(array(
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data
        ));
        break;

    case 'historial':
        $esAdmin = isset($_SESSION['acceso']) && $_SESSION['acceso'] == 1;
        $rspta = $caja->historialCajas($idusuario, $esAdmin);
        $data = array();
        while ($reg = $rspta->fetch_object()) {
            $estado = $reg->estado === 'ABIERTA' ? '<span class="label bg-green">ABIERTA</span>' : '<span class="label bg-aqua">CERRADA</span>';
            $btnPdf = '<a href="../reportes/rptCierreCaja.php?id='.$reg->idcaja.'" target="_blank" class="btn btn-xs btn-default" title="PDF cierre"><i class="fa fa-file-pdf-o"></i></a>';
            $data[] = array(
                '0' => $reg->idcaja,
                '1' => date('d/m/Y H:i', strtotime($reg->fecha_apertura)),
                '2' => empty($reg->fecha_cierre) ? '-' : date('d/m/Y H:i', strtotime($reg->fecha_cierre)),
                '3' => htmlspecialchars($reg->cajero),
                '4' => formatearMoneda((float)$reg->monto_apertura),
                '5' => formatearMoneda((float)$reg->ingresos),
                '6' => formatearMoneda((float)$reg->egresos),
                '7' => formatearMoneda((float)$reg->monto_cierre_sistema),
                '8' => formatearMoneda((float)$reg->monto_cierre_real),
                '9' => formatearMoneda((float)$reg->diferencia),
                '10' => $estado,
                '11' => $btnPdf
            );
        }

        echo json_encode(array(
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data
        ));
        break;
}
?>
