<?php
ob_start();
error_reporting(0);
if (strlen(session_id()) < 1) session_start();
require_once "../../modelos/PedidoOnline.php";
require_once "../../modelos/ConfigTienda.php";
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$op = $_GET['op'] ?? '';

if (!isset($_SESSION['tienda_cliente'])) {
    echo json_encode(array('ok'=>false,'message'=>'Debes iniciar sesion para realizar un pedido.','login_required'=>true));
    exit;
}

try {

$mdl       = new PedidoOnline();
$cfgTienda = new ConfigTienda();
$idcliente = (int)$_SESSION['tienda_cliente']['idcliente_tienda'];

switch ($op) {

    case 'crear':
        if (empty($_SESSION['carrito_tienda'])) {
            echo json_encode(array('ok'=>false,'message'=>'El carrito esta vacio.')); break;
        }

        // Calcular costo de envío
        $subtotal = 0;
        $items    = array();
        foreach ($_SESSION['carrito_tienda'] as $id => $item) {
            $sub      = round($item['precio'] * $item['cantidad'], 2);
            $subtotal += $sub;
            $items[]  = array('idarticulo'=>$item['idarticulo'],'cantidad'=>$item['cantidad']);
        }
        $tipoEntrega   = in_array($_POST['tipo_entrega'] ?? '', array('ENVIO','RECOJO'), true) ? $_POST['tipo_entrega'] : 'ENVIO';
        $envioGratis   = (float)$cfgTienda->obtener('envio_gratis_desde', 80);
        $costoEnvio    = ($tipoEntrega === 'RECOJO') ? 0 : (($subtotal >= $envioGratis) ? 0 : (float)$cfgTienda->obtener('costo_envio_default', 5));
        $metodoPago    = limpiarCadena($_POST['metodo_pago'] ?? 'CONTRAENTREGA');

        // Subir comprobante Yape si existe
        $comprobante_pago = '';
        if ($metodoPago === 'YAPE' && !empty($_FILES['comprobante_yape']['name'])) {
            $ext  = strtolower(pathinfo($_FILES['comprobante_yape']['name'], PATHINFO_EXTENSION));
            $extsOk = array('jpg','jpeg','png','gif','webp');
            if (in_array($ext, $extsOk, true) && $_FILES['comprobante_yape']['size'] < 3145728) {
                $nombreArchivo = 'yape_' . time() . '_' . rand(100,999) . '.' . $ext;
                $ruta = realpath(__DIR__ . '/../../files/tienda/') . '/' . $nombreArchivo;
                if (move_uploaded_file($_FILES['comprobante_yape']['tmp_name'], $ruta)) {
                    $comprobante_pago = $nombreArchivo;
                }
            }
        }

        $datos = array(
            'idcliente_tienda' => $idcliente,
            'nombre_entrega'   => limpiarCadena(trim($_POST['nombre_entrega']   ?? '')),
            'telefono_entrega' => limpiarCadena(trim($_POST['telefono_entrega'] ?? '')),
            'direccion_entrega'=> limpiarCadena(trim($_POST['direccion_entrega']?? '')),
            'distrito_entrega' => limpiarCadena(trim($_POST['distrito_entrega'] ?? '')),
            'tipo_comprobante' => limpiarCadena(trim($_POST['tipo_comprobante'] ?? 'Boleta')),
            'ruc_factura'      => limpiarCadena(trim($_POST['ruc_factura']       ?? '')),
            'razon_social'     => limpiarCadena(trim($_POST['razon_social']      ?? '')),
            'metodo_pago'      => $metodoPago,
            'referencia_yape'  => limpiarCadena(trim($_POST['referencia_yape']   ?? '')),
            'comprobante_pago' => $comprobante_pago,
            'notas_cliente'    => limpiarCadena(trim($_POST['notas_cliente']     ?? '')),
            'tipo_entrega'     => $tipoEntrega,
            'costo_envio'      => $costoEnvio
        );

        if (!$datos['nombre_entrega'] || !$datos['telefono_entrega']) {
            echo json_encode(array('ok'=>false,'message'=>'Nombre y teléfono de contacto son obligatorios.')); break;
        }
        if ($tipoEntrega === 'ENVIO' && !$datos['direccion_entrega']) {
            echo json_encode(array('ok'=>false,'message'=>'Ingresa tu dirección de entrega.')); break;
        }

        $r = $mdl->crear($datos, $items);
        if (!$r || (is_array($r) && !$r['ok'])) {
            echo json_encode(is_array($r) ? $r : array('ok'=>false,'message'=>'No se pudo procesar el pedido. Intenta de nuevo.')); break;
        }

        // Vaciar carrito
        $_SESSION['carrito_tienda'] = array();

        echo json_encode(array(
            'ok'      => true,
            'idpedido'=> $r['idpedido'],
            'total'   => $r['total'],
            'message' => 'Pedido registrado correctamente.'
        ));
        break;

    case 'mis_pedidos':
        $rs   = $mdl->listarPorCliente($idcliente);
        $data = array();
        if ($rs) { while ($row = $rs->fetch_assoc()) $data[] = $row; }
        echo json_encode(array('ok'=>true,'pedidos'=>$data));
        break;

    case 'detalle':
        $idpedido = isset($_GET['idpedido']) ? (int)$_GET['idpedido'] : 0;
        $cab = $mdl->obtenerCabecera($idpedido);
        if (!$cab || (int)$cab['idcliente_tienda'] !== $idcliente) {
            echo json_encode(array('ok'=>false,'message'=>'Pedido no encontrado')); break;
        }
        $det   = $mdl->obtenerDetalle($idpedido);
        $items = array();
        if ($det) { while ($row = $det->fetch_assoc()) $items[] = $row; }
        echo json_encode(array('ok'=>true,'cabecera'=>$cab,'detalle'=>$items));
        break;

    default:
        echo json_encode(array('ok'=>false,'message'=>'Operacion no reconocida'));
}

} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (strpos($msg, "doesn't exist") !== false) {
        echo json_encode(array('ok'=>false,'message'=>'La tienda online no está configurada. Ejecuta migrations/20260525_tienda_online.sql en phpMyAdmin.'));
    } else {
        echo json_encode(array('ok'=>false,'message'=>'Error: ' . $msg));
    }
}
?>
