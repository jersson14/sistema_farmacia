<?php
if (strlen(session_id()) < 1) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once "../../config/Conexion.php";
require_once "../../modelos/Presentacion.php";

$op = $_GET['op'] ?? '';

// Carrito en sesión: "idarticulo:idpresentacion" => [datos + cantidad]
// Una misma medicina en Tableta y en Caja son dos líneas distintas.
if (!isset($_SESSION['carrito_tienda'])) {
    $_SESSION['carrito_tienda'] = array();
}

$presMdl = new Presentacion();
// Cliente con sesión iniciada = identificado con DNI → paga el precio con DNI
$conDni = !empty($_SESSION['tienda_cliente']);

function _tiendaClave($idarticulo, $idpresentacion) {
    return (int)$idarticulo . ':' . (int)$idpresentacion;
}

// Clave de la línea a modificar: "clave" (nuevo) o "idarticulo" (carritos antiguos / unidad base)
function _tiendaClavePost() {
    if (!empty($_POST['clave']) && preg_match('/^\d+:\d+$/', $_POST['clave'])) return $_POST['clave'];
    $id = isset($_POST['idarticulo']) ? (int)$_POST['idarticulo'] : 0;
    if (isset($_SESSION['carrito_tienda'][$id])) return $id; // carrito guardado antes de las presentaciones
    foreach ($_SESSION['carrito_tienda'] as $k => $it) {
        if ((int)$it['idarticulo'] === $id) return $k;
    }
    return '';
}

try {
switch ($op) {

    case 'agregar':
        $id     = isset($_POST['idarticulo']) ? (int)$_POST['idarticulo'] : 0;
        $idpres = isset($_POST['idpresentacion']) ? (int)$_POST['idpresentacion'] : 0;
        $qty    = max(1, (int)($_POST['cantidad'] ?? 1));
        if ($id <= 0) { echo json_encode(array('ok'=>false,'message'=>'Producto invalido')); break; }

        $art = ejecutarConsultaSimpleFila(
            "SELECT idarticulo, nombre, stock, IFNULL(imagen,'') AS imagen
             FROM articulo WHERE idarticulo='$id' AND condicion=1 LIMIT 1"
        );
        if (!$art) { echo json_encode(array('ok'=>false,'message'=>'Producto no disponible en la tienda')); break; }

        $mapa = $presMdl->mapaPorArticulos(array($id));
        $pres = Presentacion::elegirDeLista(isset($mapa[$id]) ? $mapa[$id] : array(), $idpres);
        if (!$pres) { echo json_encode(array('ok'=>false,'message'=>'Esa presentación ya no está disponible. Elige otra.')); break; }

        $factor   = max(1, (float)$pres['factor']);
        $stockMax = (int)floor((float)$art['stock'] / $factor);
        $clave    = _tiendaClave($id, $pres['id']);
        $cantActual = isset($_SESSION['carrito_tienda'][$clave]) ? (int)$_SESSION['carrito_tienda'][$clave]['cantidad'] : 0;
        $cantNueva  = min($cantActual + $qty, $stockMax);

        if ($cantNueva <= 0) {
            echo json_encode(array('ok'=>false,'message'=> $factor > 1
                ? 'No hay stock suficiente para vender por '.Presentacion::etiqueta($pres).'. Prueba con una presentación más pequeña.'
                : 'Sin stock disponible'));
            break;
        }

        $_SESSION['carrito_tienda'][$clave] = array(
            'clave'          => $clave,
            'idarticulo'     => (int)$art['idarticulo'],
            'idpresentacion' => (int)$pres['id'],
            'presentacion'   => Presentacion::etiqueta($pres),
            'nombre'         => $art['nombre'],
            'precio'         => Presentacion::precioCliente($pres, $conDni),
            'cantidad'       => $cantNueva,
            'stock_max'      => $stockMax,
            'imagen'         => $art['imagen']
        );
        echo json_encode(array('ok'=>true,'message'=>'Agregado al carrito','total_items'=>count($_SESSION['carrito_tienda'])));
        break;

    case 'actualizar':
        $clave = _tiendaClavePost();
        $qty   = (int)($_POST['cantidad'] ?? 1);
        if ($clave === '' || !isset($_SESSION['carrito_tienda'][$clave])) {
            echo json_encode(array('ok'=>false,'message'=>'Producto no esta en el carrito')); break;
        }
        $stockMax = (int)$_SESSION['carrito_tienda'][$clave]['stock_max'];
        if ($qty <= 0) {
            unset($_SESSION['carrito_tienda'][$clave]);
        } else {
            $_SESSION['carrito_tienda'][$clave]['cantidad'] = min($qty, $stockMax);
        }
        echo json_encode(array('ok'=>true,'total_items'=>count($_SESSION['carrito_tienda'])));
        break;

    case 'quitar':
        $clave = _tiendaClavePost();
        if ($clave !== '') unset($_SESSION['carrito_tienda'][$clave]);
        echo json_encode(array('ok'=>true,'total_items'=>count($_SESSION['carrito_tienda'])));
        break;

    case 'obtener':
        $items  = array();
        $total  = 0;
        if (!empty($_SESSION['carrito_tienda'])) {
            // Refrescar precios y stock desde BD (por presentación)
            $ids = array();
            foreach ($_SESSION['carrito_tienda'] as $it) $ids[] = (int)$it['idarticulo'];
            $mapa = $presMdl->mapaPorArticulos($ids);
            $stocks = array();
            $rsS = ejecutarConsulta("SELECT idarticulo, IFNULL(stock,0) AS stock FROM articulo
                WHERE idarticulo IN (".implode(',', array_unique($ids)).") AND condicion=1");
            while ($rsS && $rs = $rsS->fetch_assoc()) $stocks[(int)$rs['idarticulo']] = (float)$rs['stock'];

            $nuevo = array();
            foreach ($_SESSION['carrito_tienda'] as $k => $item) {
                $idA = (int)$item['idarticulo'];
                $pres = Presentacion::elegirDeLista(isset($mapa[$idA]) ? $mapa[$idA] : array(), isset($item['idpresentacion']) ? $item['idpresentacion'] : 0);
                if (!$pres || !isset($stocks[$idA])) continue; // producto o presentación ya no disponible
                $clave = _tiendaClave($idA, $pres['id']);
                $item['clave']          = $clave;
                $item['idpresentacion'] = (int)$pres['id'];
                $item['presentacion']   = Presentacion::etiqueta($pres);
                $item['precio']         = Presentacion::precioCliente($pres, $conDni);
                $item['stock_max']      = (int)floor($stocks[$idA] / max(1, (float)$pres['factor']));
                $nuevo[$clave] = $item;
                $sub    = round($item['precio'] * $item['cantidad'], 2);
                $total += $sub;
                $items[] = array(
                    'clave'          => $clave,
                    'idarticulo'     => $idA,
                    'idpresentacion' => $item['idpresentacion'],
                    'presentacion'   => $item['presentacion'],
                    'mostrar_presentacion' => isset($mapa[$idA]) && count($mapa[$idA]) > 1,
                    'nombre'         => $item['nombre'],
                    'precio'         => $item['precio'],
                    'precio_regular' => (float)$pres['precio'],
                    'precio_dni'     => (float)$pres['precio_dni'],
                    'con_dni'        => $conDni && $pres['precio_dni'] > 0,
                    'cantidad'       => $item['cantidad'],
                    'stock_max'      => $item['stock_max'],
                    'subtotal'       => $sub,
                    'imagen'         => $item['imagen']
                );
            }
            $_SESSION['carrito_tienda'] = $nuevo;
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
