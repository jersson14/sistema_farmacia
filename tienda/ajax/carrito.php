<?php
if (strlen(session_id()) < 1) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once "../../config/Conexion.php";

$op = $_GET['op'] ?? '';

// Carrito almacenado en sesión como array: idarticulo => [datos + cantidad]
if (!isset($_SESSION['carrito_tienda'])) {
    $_SESSION['carrito_tienda'] = array();
}

// Verificar si la columna precio_venta existe en articulo
function _tiendaColumnExists($col) {
    global $conexion;
    $r = $conexion->query("SHOW COLUMNS FROM `articulo` LIKE '$col'");
    return $r && $r->num_rows > 0;
}
$_tienePrecioVenta = _tiendaColumnExists('precio_venta');

try {
switch ($op) {

    case 'agregar':
        $id  = isset($_POST['idarticulo']) ? (int)$_POST['idarticulo'] : 0;
        $qty = max(1, (int)($_POST['cantidad'] ?? 1));
        if ($id <= 0) { echo json_encode(array('ok'=>false,'message'=>'Producto invalido')); break; }

        $precioExpr = $_tienePrecioVenta
            ? "COALESCE(NULLIF(a.precio_venta,0),(SELECT di.precio_venta FROM detalle_ingreso di WHERE di.idarticulo=a.idarticulo ORDER BY di.iddetalle_ingreso DESC LIMIT 1),0)"
            : "IFNULL((SELECT di.precio_venta FROM detalle_ingreso di WHERE di.idarticulo=a.idarticulo ORDER BY di.iddetalle_ingreso DESC LIMIT 1),0)";
        $art = ejecutarConsultaSimpleFila(
            "SELECT idarticulo, nombre, stock, condicion, IFNULL(imagen,'') AS imagen,
                    $precioExpr AS precio_venta
             FROM articulo a WHERE idarticulo='$id' AND condicion=1 LIMIT 1"
        );
        if (!$art) { echo json_encode(array('ok'=>false,'message'=>'Producto no disponible en la tienda')); break; }

        $stockMax = (int)round((float)$art['stock']);
        $cantActual = isset($_SESSION['carrito_tienda'][$id]) ? (int)$_SESSION['carrito_tienda'][$id]['cantidad'] : 0;
        $cantNueva  = min($cantActual + $qty, $stockMax);

        if ($cantNueva <= 0) { echo json_encode(array('ok'=>false,'message'=>'Sin stock disponible')); break; }

        $_SESSION['carrito_tienda'][$id] = array(
            'idarticulo'   => (int)$art['idarticulo'],
            'nombre'       => $art['nombre'],
            'precio'       => (float)$art['precio_venta'],
            'cantidad'     => $cantNueva,
            'stock_max'    => $stockMax,
            'imagen'       => $art['imagen']
        );
        echo json_encode(array('ok'=>true,'message'=>'Agregado al carrito','total_items'=>count($_SESSION['carrito_tienda'])));
        break;

    case 'actualizar':
        $id  = isset($_POST['idarticulo']) ? (int)$_POST['idarticulo'] : 0;
        $qty = (int)($_POST['cantidad'] ?? 1);
        if ($id <= 0 || !isset($_SESSION['carrito_tienda'][$id])) {
            echo json_encode(array('ok'=>false,'message'=>'Producto no esta en el carrito')); break;
        }
        $stockMax = (int)$_SESSION['carrito_tienda'][$id]['stock_max'];
        if ($qty <= 0) {
            unset($_SESSION['carrito_tienda'][$id]);
        } else {
            $_SESSION['carrito_tienda'][$id]['cantidad'] = min($qty, $stockMax);
        }
        echo json_encode(array('ok'=>true,'total_items'=>count($_SESSION['carrito_tienda'])));
        break;

    case 'quitar':
        $id = isset($_POST['idarticulo']) ? (int)$_POST['idarticulo'] : 0;
        unset($_SESSION['carrito_tienda'][$id]);
        echo json_encode(array('ok'=>true,'total_items'=>count($_SESSION['carrito_tienda'])));
        break;

    case 'obtener':
        $items  = array();
        $total  = 0;
        if (!empty($_SESSION['carrito_tienda'])) {
            // Refrescar precios y stock desde BD para corregir cualquier valor desactualizado
            $ids = implode(',', array_map('intval', array_keys($_SESSION['carrito_tienda'])));
            $precioExprObt = $_tienePrecioVenta
                ? "COALESCE(NULLIF(a.precio_venta,0),(SELECT di.precio_venta FROM detalle_ingreso di WHERE di.idarticulo=a.idarticulo ORDER BY di.iddetalle_ingreso DESC LIMIT 1),0)"
                : "IFNULL((SELECT di.precio_venta FROM detalle_ingreso di WHERE di.idarticulo=a.idarticulo ORDER BY di.iddetalle_ingreso DESC LIMIT 1),0)";
            $rsP = ejecutarConsulta(
                "SELECT a.idarticulo, $precioExprObt AS precio_venta, IFNULL(a.stock,0) AS stock
                 FROM articulo a WHERE a.idarticulo IN ($ids) AND a.condicion=1"
            );
            $preciosDB = array();
            if ($rsP) {
                while ($rp = $rsP->fetch_assoc()) {
                    $preciosDB[(int)$rp['idarticulo']] = array(
                        'precio' => (float)$rp['precio_venta'],
                        'stock'  => (int)round((float)$rp['stock'])
                    );
                }
            }
            foreach ($_SESSION['carrito_tienda'] as $id => $item) {
                $idInt = (int)$id;
                if (isset($preciosDB[$idInt])) {
                    $_SESSION['carrito_tienda'][$id]['precio']    = $preciosDB[$idInt]['precio'];
                    $_SESSION['carrito_tienda'][$id]['stock_max'] = $preciosDB[$idInt]['stock'];
                }
                $precio = $_SESSION['carrito_tienda'][$id]['precio'];
                $sub    = round($precio * $item['cantidad'], 2);
                $total += $sub;
                $items[] = array(
                    'idarticulo'=> $item['idarticulo'],
                    'nombre'    => $item['nombre'],
                    'precio'    => $precio,
                    'cantidad'  => $item['cantidad'],
                    'stock_max' => $_SESSION['carrito_tienda'][$id]['stock_max'],
                    'subtotal'  => $sub,
                    'imagen'    => $item['imagen']
                );
            }
        }
        echo json_encode(array(
            'ok'         => true,
            'items'      => $items,
            'total'      => round($total, 2),
            'total_items'=> count($items)
        ));
        break;

    case 'vaciar':
        $_SESSION['carrito_tienda'] = array();
        echo json_encode(array('ok'=>true));
        break;

    case 'count':
        echo json_encode(array('ok'=>true,'total_items'=>count($_SESSION['carrito_tienda'])));
        break;

    default:
        echo json_encode(array('ok'=>false,'message'=>'Operacion no reconocida'));
}
} catch (Throwable $e) {
    echo json_encode(array('ok'=>false,'message'=>'Error interno: ' . $e->getMessage()));
}
?>
