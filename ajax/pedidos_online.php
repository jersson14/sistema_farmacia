<?php
error_reporting(0);
ini_set('display_errors', '0');
ob_start();
if (strlen(session_id()) < 1) session_start();
if (!isset($_SESSION['nombre'])) {
    echo json_encode(array('ok'=>false,'message'=>'Sesion expirada'));
    exit;
}
require_once "../modelos/PedidoOnline.php";
require_once "../modelos/ConfigTienda.php";

if (!function_exists('fechaFiltroSeguro')) {
    function fechaFiltroSeguro($v) {
        $v = trim((string)$v);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
    }
}

$op  = $_GET['op'] ?? '';
$mdl = new PedidoOnline();

switch ($op) {

    case 'listar':
        $estado = isset($_GET['estado']) ? limpiarCadena($_GET['estado']) : '';
        $fi     = fechaFiltroSeguro($_GET['fecha_inicio'] ?? '');
        $ff     = fechaFiltroSeguro($_GET['fecha_fin']    ?? '');
        $rs     = $mdl->listarAdmin($estado, $fi, $ff);
        $data   = array();
        $colores = array(
            'PENDIENTE'=>'bg-yellow','CONFIRMADO'=>'bg-blue','EN_PREPARACION'=>'bg-purple',
            'DESPACHADO'=>'bg-aqua','ENTREGADO'=>'bg-green','CANCELADO'=>'bg-red'
        );
        while ($reg = $rs->fetch_object()) {
            $badgeClass = isset($colores[$reg->estado]) ? $colores[$reg->estado] : 'bg-gray';
            $comp = $reg->comprobante_pago
                ? '<a href="../files/tienda/'.htmlspecialchars($reg->comprobante_pago).'" target="_blank" class="btn btn-xs btn-default"><i class="fa fa-image"></i></a>'
                : '';
            $data[] = array(
                "0" => $reg->idpedido,
                "1" => $reg->fecha,
                "2" => htmlspecialchars($reg->cliente) . '<br><small class="text-muted">' . htmlspecialchars($reg->email) . '</small>',
                "3" => htmlspecialchars($reg->nombre_entrega) . '<br><small>' . htmlspecialchars($reg->distrito_entrega) . '</small>',
                "4" => htmlspecialchars($reg->metodo_pago) . ($reg->referencia_yape ? '<br><small>Ref: '.htmlspecialchars($reg->referencia_yape).'</small>' : '') . ' ' . $comp,
                "5" => 'S/ ' . number_format((float)$reg->total, 2),
                "6" => '<span class="label ' . $badgeClass . '">' . htmlspecialchars($reg->estado) . '</span>',
                "7" => '<button class="btn btn-warning btn-xs" onclick="verPedido('.(int)$reg->idpedido.')"><i class="fa fa-eye"></i></button>'
                     . ' <button class="btn btn-success btn-xs" onclick="cambiarEstado('.(int)$reg->idpedido.', \'CONFIRMADO\')"><i class="fa fa-check"></i></button>'
                     . ' <button class="btn btn-danger btn-xs" onclick="cambiarEstado('.(int)$reg->idpedido.', \'CANCELADO\')"><i class="fa fa-times"></i></button>'
            );
        }
        echo json_encode(array('sEcho'=>1,'iTotalRecords'=>count($data),'iTotalDisplayRecords'=>count($data),'aaData'=>$data));
        break;

    case 'cambiarEstado':
        $idpedido   = isset($_POST['idpedido'])    ? (int)$_POST['idpedido']           : 0;
        $estado     = isset($_POST['estado'])       ? limpiarCadena($_POST['estado'])   : '';
        $notas      = isset($_POST['notas_admin'])  ? limpiarCadena($_POST['notas_admin']) : '';
        if ($idpedido <= 0) { echo json_encode(array('ok'=>false,'message'=>'ID invalido')); break; }
        $ok = $mdl->cambiarEstado($idpedido, $estado, $notas);
        // Si se marca como ENTREGADO con pago CONTRAENTREGA, registrar ingreso en caja
        if ($ok && $estado === 'ENTREGADO') {
            $cab = $mdl->obtenerCabecera($idpedido);
            if ($cab && $cab['metodo_pago'] === 'CONTRAENTREGA') {
                require_once "../modelos/Caja.php";
                $cajaMdl   = new Caja();
                $idusuario = (int)$_SESSION['idusuario'];
                $cajaAb    = $cajaMdl->cajaAbiertaUsuario($idusuario);
                if ($cajaAb) {
                    $concepto = limpiarCadena('Pedido Online #' . $idpedido . ' (contraentrega)');
                    $cajaMdl->agregarMovimiento($cajaAb['idcaja'], $idusuario, 'INGRESO', $concepto, (float)$cab['total']);
                }
            }
        }
        echo json_encode(array('ok'=>(bool)$ok));
        break;

    case 'detalle':
        $idpedido = isset($_GET['idpedido']) ? (int)$_GET['idpedido'] : 0;
        $cab = $mdl->obtenerCabecera($idpedido);
        $det = $mdl->obtenerDetalle($idpedido);
        $items = array();
        while ($row = $det->fetch_assoc()) $items[] = $row;
        echo json_encode(array('ok'=> (bool)$cab, 'cabecera'=>$cab, 'detalle'=>$items));
        break;

    case 'contadores':
        $c = $mdl->contarPorEstado();
        echo json_encode(array('ok'=>true,'data'=>$c));
        break;

    case 'notifPedidos':
        // Devuelve cantidad de pedidos PENDIENTES + últimos 5 para el bell del panel
        $pendientes = ejecutarConsultaSimpleFila(
            "SELECT COUNT(*) as total FROM pedido_online WHERE estado='PENDIENTE'"
        );
        $ultimos = ejecutarConsulta(
            "SELECT po.idpedido, po.nombre_entrega, po.total, po.fecha_pedido,
                    ct.email
             FROM pedido_online po
             LEFT JOIN cliente_tienda ct ON po.idcliente_tienda = ct.idcliente_tienda
             WHERE po.estado='PENDIENTE'
             ORDER BY po.idpedido DESC LIMIT 5"
        );
        $lista = array();
        if ($ultimos) {
            while ($r = $ultimos->fetch_assoc()) {
                $lista[] = array(
                    'idpedido'       => (int)$r['idpedido'],
                    'nombre_entrega' => $r['nombre_entrega'],
                    'total'          => (float)$r['total'],
                    'fecha'          => $r['fecha_pedido'],
                    'email'          => $r['email'] ?? ''
                );
            }
        }
        echo json_encode(array(
            'ok'        => true,
            'pendientes'=> (int)($pendientes['total'] ?? 0),
            'pedidos'   => $lista
        ));
        break;

    case 'guardarConfig':
        if ($_SESSION['acceso'] != 1) { echo json_encode(array('ok'=>false,'message'=>'Sin permiso')); break; }
        $cfg = new ConfigTienda();
        $pares = array(
            'yape_numero'         => limpiarCadena(trim($_POST['yape_numero']   ?? '')),
            'yape_nombre'         => limpiarCadena(trim($_POST['yape_nombre']   ?? '')),
            'costo_envio_default' => limpiarCadena(trim($_POST['costo_envio']   ?? '5')),
            'envio_gratis_desde'  => limpiarCadena(trim($_POST['envio_gratis']  ?? '80')),
            'whatsapp_numero'     => limpiarCadena(trim($_POST['whatsapp']      ?? '')),
            'tienda_activa'       => (isset($_POST['tienda_activa']) && $_POST['tienda_activa'] == '1') ? '1' : '0',
            'banner_texto'        => limpiarCadena(trim($_POST['banner_texto']  ?? ''))
        );
        // Subir QR Yape si viene archivo
        if (!empty($_FILES['yape_qr_imagen']['name'])) {
            $ext   = strtolower(pathinfo($_FILES['yape_qr_imagen']['name'], PATHINFO_EXTENSION));
            $extsOk = array('jpg','jpeg','png','gif','webp');
            if (in_array($ext, $extsOk, true) && $_FILES['yape_qr_imagen']['size'] < 2097152) {
                $nombre = 'yape_qr_' . time() . '.' . $ext;
                $ruta   = realpath(__DIR__ . '/../files/tienda/') . '/' . $nombre;
                if (move_uploaded_file($_FILES['yape_qr_imagen']['tmp_name'], $ruta)) {
                    $pares['yape_qr_imagen'] = $nombre;
                }
            }
        }
        $ok = $cfg->guardarMultiple($pares);
        echo json_encode(array('ok'=>(bool)$ok,'message'=>$ok ? 'Configuracion guardada' : 'Error al guardar'));
        break;

    case 'convertirAVenta':
        $respConv = array('ok'=>false,'message'=>'Error desconocido');
        try {
            if (!isset($_SESSION['ventas']) || $_SESSION['ventas'] != 1) {
                $respConv = array('ok'=>false,'message'=>'Sin permiso para registrar ventas');
            } else {
                $idpedido = isset($_POST['idpedido']) ? (int)$_POST['idpedido'] : 0;
                if ($idpedido <= 0) throw new Exception('ID de pedido inválido');

                // Verificar caja abierta antes de continuar
                require_once "../modelos/Caja.php";
                $cajaMdl = new Caja();
                $cajaAb  = $cajaMdl->cajaAbiertaUsuario((int)$_SESSION['idusuario']);
                if (!$cajaAb) throw new Exception('Debes abrir la caja antes de convertir un pedido en venta.');

                $cab = $mdl->obtenerCabecera($idpedido);
                if (!$cab) throw new Exception('Pedido no encontrado');
                if (in_array($cab['estado'], array('ENTREGADO','CANCELADO'), true))
                    throw new Exception('El pedido ya está ' . $cab['estado']);

                $detRs = $mdl->obtenerDetalle($idpedido);
                $items = array();
                while ($row = $detRs->fetch_assoc()) $items[] = $row;
                if (empty($items)) throw new Exception('El pedido no tiene productos');

                // Buscar persona por email → por nombre → crear → fallback Consumidor Final
                $emailCli  = trim((string)$cab['email']);
                $idpersona = 0;
                if ($emailCli !== '') {
                    $pEmail = ejecutarConsultaSimpleFila(
                        "SELECT idpersona FROM persona WHERE email='".limpiarCadena($emailCli)."' AND tipo_persona='Cliente' LIMIT 1"
                    );
                    if ($pEmail) $idpersona = (int)$pEmail['idpersona'];
                }
                if (!$idpersona) {
                    $nombreBusca = limpiarCadena(trim((string)$cab['nombre_cliente']));
                    $pNom = ejecutarConsultaSimpleFila(
                        "SELECT idpersona FROM persona WHERE nombre='$nombreBusca' AND tipo_persona='Cliente' LIMIT 1"
                    );
                    if ($pNom) $idpersona = (int)$pNom['idpersona'];
                }
                if (!$idpersona) {
                    $nombreNuevo = limpiarCadena(trim((string)$cab['nombre_cliente']));
                    $emailNuevo  = limpiarCadena($emailCli);
                    $telNuevo    = limpiarCadena(trim((string)($cab['telefono_entrega'] ?? '')));
                    $sqlIns = "INSERT INTO persona (tipo_persona, nombre, tipo_documento, num_documento, email, telefono)
                               VALUES ('Cliente','$nombreNuevo','DNI','00000000','$emailNuevo','$telNuevo')";
                    $newId = ejecutarConsulta_retornarID($sqlIns);
                    if ($newId) $idpersona = (int)$newId;
                }
                if (!$idpersona) {
                    $pCF = ejecutarConsultaSimpleFila(
                        "SELECT idpersona FROM persona WHERE nombre='Consumidor Final' AND tipo_persona='Cliente' LIMIT 1"
                    );
                    if ($pCF) $idpersona = (int)$pCF['idpersona'];
                }
                if (!$idpersona) throw new Exception('No se pudo identificar el cliente');

                // Arrays para Venta::insertar()
                $arrArt = array(); $arrCant = array(); $arrPrecio = array(); $arrDesc = array();
                foreach ($items as $it) {
                    $arrArt[]    = (int)$it['idarticulo'];
                    $arrCant[]   = (float)$it['cantidad'];
                    $arrPrecio[] = (float)$it['precio_unitario'];
                    $arrDesc[]   = 0.0;
                }

                // Método de pago: pasar monto_efectivo=0 para que Venta::insertar
                // no intente registrar en caja internamente; lo hacemos manualmente
                $mpedido  = strtoupper(trim((string)$cab['metodo_pago']));
                $mPago    = ($mpedido === 'YAPE') ? 'YAPE' : 'EFECTIVO';
                $mTotal   = (float)$cab['total'];

                $cfgImp   = ejecutarConsultaSimpleFila("SELECT IFNULL(impuesto_default,0) AS impuesto FROM configuracion_empresa LIMIT 1");
                $impuesto = $cfgImp ? (float)$cfgImp['impuesto'] : 0;

                $tipoComp = in_array($cab['tipo_comprobante'], array('Boleta','Factura','Ticket'), true)
                            ? $cab['tipo_comprobante'] : 'Boleta';

                require_once "../modelos/Venta.php";
                $ventaMdl = new Venta();
                // Pasamos monto_efectivo=0 intencionalmente para evitar el require Caja.php interno
                $result = $ventaMdl->insertar(
                    $idpersona, (int)$_SESSION['idusuario'],
                    $tipoComp, '', '', '',
                    $impuesto, $mTotal,
                    $arrArt, $arrCant, $arrPrecio, $arrDesc,
                    $mPago, 0, 0, 0
                );

                if (!$result['ok']) throw new Exception($result['message']);

                // Registrar ingreso en caja (caja ya verificada arriba)
                if ($mTotal > 0) {
                    $concCaja = limpiarCadena('Venta '.$result['tipo_comprobante'].' '.$result['serie_comprobante'].'-'.$result['num_comprobante'].' (pedido #'.$idpedido.')');
                    $cajaMdl->agregarMovimiento($cajaAb['idcaja'], (int)$_SESSION['idusuario'], 'INGRESO', $concCaja, $mTotal);
                }

                $notaConv = limpiarCadena('Venta '.$result['tipo_comprobante'].' '.$result['serie_comprobante'].'-'.$result['num_comprobante']);
                $mdl->cambiarEstado($idpedido, 'ENTREGADO', $notaConv);

                $respConv = array(
                    'ok'                => true,
                    'idventa'           => (int)$result['idventa'],
                    'tipo_comprobante'  => $result['tipo_comprobante'],
                    'serie_comprobante' => $result['serie_comprobante'],
                    'num_comprobante'   => $result['num_comprobante'],
                    'message'           => 'Venta registrada exitosamente'
                );
            }
        } catch (Throwable $e) {
            $respConv = array('ok'=>false,'message'=>$e->getMessage());
        }
        ob_end_clean();
        echo json_encode($respConv);
        break;

    default:
        echo json_encode(array('ok'=>false,'message'=>'Operacion no reconocida'));
}
?>
