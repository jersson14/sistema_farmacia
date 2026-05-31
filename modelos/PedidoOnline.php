<?php
require_once __DIR__ . "/../config/Conexion.php";

class PedidoOnline
{
    public function crear($datos, $items)
    {
        global $conexion;

        $idcliente      = (int)$datos['idcliente_tienda'];
        $nombre_e       = limpiarCadena($datos['nombre_entrega']);
        $telefono_e     = limpiarCadena($datos['telefono_entrega']);
        $direccion_e    = limpiarCadena($datos['direccion_entrega']);
        $distrito_e     = limpiarCadena($datos['distrito_entrega']);
        $tipo_comp      = in_array($datos['tipo_comprobante'], array('Boleta','Factura'), true) ? $datos['tipo_comprobante'] : 'Boleta';
        $ruc_f          = limpiarCadena($datos['ruc_factura'] ?? '');
        $razon_s        = limpiarCadena($datos['razon_social'] ?? '');
        $metodo_pago    = in_array($datos['metodo_pago'], array('YAPE','CONTRAENTREGA'), true) ? $datos['metodo_pago'] : 'CONTRAENTREGA';
        $ref_yape       = limpiarCadena($datos['referencia_yape'] ?? '');
        $comp_pago      = limpiarCadena($datos['comprobante_pago'] ?? '');
        $notas          = limpiarCadena($datos['notas_cliente'] ?? '');
        $tipo_entrega   = in_array($datos['tipo_entrega'] ?? '', array('ENVIO','RECOJO'), true) ? $datos['tipo_entrega'] : 'ENVIO';
        $costo_envio    = max(0, (float)($datos['costo_envio'] ?? 0));

        if ($idcliente <= 0 || empty($items)) return false;

        $conexion->autocommit(false);
        try {
            // Calcular subtotal desde items validados
            $subtotal = 0;
            $detalles = array();
            foreach ($items as $item) {
                $idarticulo = (int)$item['idarticulo'];
                $cantidad   = max(1, (int)$item['cantidad']);
                // Validar que el artículo existe, está activo y tiene stock
                $art = ejecutarConsultaSimpleFila(
                    "SELECT a.idarticulo, a.nombre, a.stock,
                            COALESCE(NULLIF(a.precio_venta,0),
                                (SELECT di.precio_venta FROM detalle_ingreso di WHERE di.idarticulo=a.idarticulo ORDER BY di.iddetalle_ingreso DESC LIMIT 1),
                            0) AS precio_venta
                     FROM articulo a WHERE a.idarticulo='$idarticulo' AND a.condicion=1 LIMIT 1"
                );
                if (!$art) {
                    $conexion->rollback(); $conexion->autocommit(true); return false;
                }
                if ((float)$art['stock'] < $cantidad) {
                    $conexion->rollback(); $conexion->autocommit(true);
                    return array('ok'=>false,'message'=> 'Stock insuficiente para ' . $art['nombre']);
                }
                $precio   = (float)$art['precio_venta'];
                $sub      = round($precio * $cantidad, 2);
                $subtotal += $sub;
                $detalles[] = array(
                    'idarticulo'     => $idarticulo,
                    'nombre_producto'=> limpiarCadena($art['nombre']),
                    'precio_unitario'=> $precio,
                    'cantidad'       => $cantidad,
                    'subtotal'       => $sub
                );
            }
            $total = round($subtotal + $costo_envio, 2);

            // Verificar si la columna tipo_entrega existe (requiere migration 20260526_add_tipo_entrega_pedido.sql)
            $tieneColEntrega = $conexion->query("SHOW COLUMNS FROM `pedido_online` LIKE 'tipo_entrega'");
            $conEntrega      = $tieneColEntrega && $tieneColEntrega->num_rows > 0;

            if ($conEntrega) {
                $sql = "INSERT INTO pedido_online
                        (idcliente_tienda, nombre_entrega, telefono_entrega, direccion_entrega, distrito_entrega,
                         tipo_entrega, tipo_comprobante, ruc_factura, razon_social, subtotal, costo_envio, total,
                         metodo_pago, referencia_yape, comprobante_pago, notas_cliente)
                        VALUES
                        ('$idcliente','$nombre_e','$telefono_e','$direccion_e','$distrito_e',
                         '$tipo_entrega','$tipo_comp','$ruc_f','$razon_s','$subtotal','$costo_envio','$total',
                         '$metodo_pago','$ref_yape','$comp_pago','$notas')";
            } else {
                $sql = "INSERT INTO pedido_online
                        (idcliente_tienda, nombre_entrega, telefono_entrega, direccion_entrega, distrito_entrega,
                         tipo_comprobante, ruc_factura, razon_social, subtotal, costo_envio, total,
                         metodo_pago, referencia_yape, comprobante_pago, notas_cliente)
                        VALUES
                        ('$idcliente','$nombre_e','$telefono_e','$direccion_e','$distrito_e',
                         '$tipo_comp','$ruc_f','$razon_s','$subtotal','$costo_envio','$total',
                         '$metodo_pago','$ref_yape','$comp_pago','$notas')";
            }
            $idpedido = ejecutarConsulta_retornarID($sql);
            if (!$idpedido) {
                $conexion->rollback(); $conexion->autocommit(true); return false;
            }

            foreach ($detalles as $d) {
                $sqlD = "INSERT INTO detalle_pedido_online
                         (idpedido, idarticulo, nombre_producto, precio_unitario, cantidad, subtotal)
                         VALUES('$idpedido','{$d['idarticulo']}','{$d['nombre_producto']}',
                                '{$d['precio_unitario']}','{$d['cantidad']}','{$d['subtotal']}')";
                ejecutarConsulta($sqlD);
            }

            $conexion->commit();
            $conexion->autocommit(true);
            return array('ok'=>true, 'idpedido'=>(int)$idpedido, 'total'=>$total);

        } catch (Throwable $e) {
            $conexion->rollback(); $conexion->autocommit(true); return false;
        }
    }

    public function listarPorCliente($idcliente)
    {
        $idcliente = (int)$idcliente;
        $sql = "SELECT p.idpedido, DATE_FORMAT(p.fecha_pedido,'%d/%m/%Y %H:%i') AS fecha,
                       p.total, p.estado, p.metodo_pago, p.tipo_comprobante
                FROM pedido_online p
                WHERE p.idcliente_tienda = '$idcliente'
                ORDER BY p.idpedido DESC";
        return ejecutarConsulta($sql);
    }

    public function obtenerDetalle($idpedido)
    {
        $idpedido = (int)$idpedido;
        return ejecutarConsulta(
            "SELECT d.*, a.codigo FROM detalle_pedido_online d
             INNER JOIN articulo a ON d.idarticulo = a.idarticulo
             WHERE d.idpedido = '$idpedido'"
        );
    }

    public function obtenerCabecera($idpedido)
    {
        $idpedido = (int)$idpedido;
        return ejecutarConsultaSimpleFila(
            "SELECT p.*, c.nombre AS nombre_cliente, c.email,
                    DATE_FORMAT(p.fecha_pedido,'%d/%m/%Y %H:%i') AS fecha_formato
             FROM pedido_online p
             INNER JOIN cliente_tienda c ON p.idcliente_tienda = c.idcliente_tienda
             WHERE p.idpedido = '$idpedido' LIMIT 1"
        );
    }

    public function cambiarEstado($idpedido, $estado, $notas_admin = '')
    {
        $idpedido    = (int)$idpedido;
        $estadosOk   = array('PENDIENTE','CONFIRMADO','EN_PREPARACION','DESPACHADO','ENTREGADO','CANCELADO');
        $estado      = in_array($estado, $estadosOk, true) ? $estado : 'PENDIENTE';
        $notas_admin = limpiarCadena($notas_admin);
        return ejecutarConsulta(
            "UPDATE pedido_online SET estado='$estado', notas_admin='$notas_admin'
             WHERE idpedido='$idpedido'"
        );
    }

    public function listarAdmin($estado = '', $fechaInicio = '', $fechaFin = '')
    {
        $where = array();
        if ($estado !== '' && $estado !== 'TODOS') $where[] = "p.estado='".limpiarCadena($estado)."'";
        if ($fechaInicio !== '') $where[] = "DATE(p.fecha_pedido)>='$fechaInicio'";
        if ($fechaFin    !== '') $where[] = "DATE(p.fecha_pedido)<='$fechaFin'";
        $filtro = count($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        return ejecutarConsulta(
            "SELECT p.idpedido, DATE_FORMAT(p.fecha_pedido,'%d/%m/%Y %H:%i') AS fecha,
                    c.nombre AS cliente, c.email, c.telefono AS tel_cliente,
                    p.nombre_entrega, p.telefono_entrega, p.distrito_entrega,
                    p.total, p.metodo_pago, p.estado, p.comprobante_pago, p.referencia_yape
             FROM pedido_online p
             INNER JOIN cliente_tienda c ON p.idcliente_tienda = c.idcliente_tienda
             $filtro
             ORDER BY p.idpedido DESC"
        );
    }

    public function contarPorEstado()
    {
        $sql = "SELECT estado, COUNT(*) AS total FROM pedido_online GROUP BY estado";
        $rs  = ejecutarConsulta($sql);
        $r   = array();
        while ($row = $rs->fetch_assoc()) $r[$row['estado']] = (int)$row['total'];
        return $r;
    }
}
?>
