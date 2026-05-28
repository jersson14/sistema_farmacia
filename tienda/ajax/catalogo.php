<?php
ob_start();
error_reporting(0);
if (strlen(session_id()) < 1) session_start();
require_once "../../config/Conexion.php";

// Detectar columnas farmacéuticas (pueden no existir si la migración no se ejecutó)
function _catColExists($col) {
    global $conexion;
    $r = $conexion->query("SHOW COLUMNS FROM `articulo` LIKE '$col'");
    return $r && $r->num_rows > 0;
}
$tieneColumnasTienda = _catColExists('tipo_venta');
$tieneColumnasRx     = _catColExists('principio_activo');

ob_end_clean(); // descartar cualquier salida accidental hasta aquí
header('Content-Type: application/json; charset=utf-8');

$op = $_GET['op'] ?? 'listar';

try {

switch ($op) {

    // ── LISTAR ───────────────────────────────────────────────
    case 'listar':
        $busqueda  = isset($_GET['q'])     ? limpiarCadena(trim($_GET['q'])) : '';
        $idcat     = isset($_GET['idcat']) ? (int)$_GET['idcat']             : 0;
        $pagina    = max(1, (int)($_GET['pagina'] ?? 1));
        $porPagina = 15;
        $offset    = ($pagina - 1) * $porPagina;

        // Solo artículos activos. Mostrar TODOS (incluso sin stock y sin importar
        // tipo_venta) para que el catálogo muestre el inventario completo.
        $where = array("a.condicion=1");

        if ($busqueda !== '') {
            $busqFiltro = "(a.nombre LIKE '%$busqueda%' OR a.codigo LIKE '%$busqueda%'";
            if ($tieneColumnasRx) {
                $busqFiltro .= " OR a.principio_activo LIKE '%$busqueda%' OR a.laboratorio LIKE '%$busqueda%'";
            }
            $busqFiltro .= ')';
            $where[] = $busqFiltro;
        }
        if ($idcat > 0) $where[] = "a.idcategoria='$idcat'";

        $filtro = ' WHERE ' . implode(' AND ', $where);

        $total    = 0;
        $countRow = ejecutarConsultaSimpleFila("SELECT COUNT(*) AS total FROM articulo a $filtro");
        if ($countRow) $total = (int)$countRow['total'];

        $extraSelect = $tieneColumnasRx
            ? ", IFNULL(a.principio_activo,'') AS principio_activo, IFNULL(a.concentracion,'') AS concentracion, IFNULL(a.laboratorio,'') AS laboratorio"
            : '';

        $sql = "SELECT a.idarticulo, a.nombre, a.codigo,
                       COALESCE(NULLIF(a.precio_venta,0),
                           (SELECT di.precio_venta FROM detalle_ingreso di WHERE di.idarticulo=a.idarticulo ORDER BY di.iddetalle_ingreso DESC LIMIT 1),
                       0) AS precio_venta,
                       IFNULL(a.stock, 0) AS stock,
                       IFNULL(a.imagen,'') AS imagen,
                       IFNULL(c.nombre,'General') AS categoria
                       $extraSelect
                FROM articulo a
                LEFT JOIN categoria c ON a.idcategoria = c.idcategoria
                $filtro
                ORDER BY a.nombre ASC
                LIMIT $porPagina OFFSET $offset";

        $rs = ejecutarConsulta($sql);
        if (!$rs) {
            echo json_encode(array('ok'=>false,'message'=>'Error al consultar productos. Verifica la base de datos.'));
            break;
        }

        $productos = array();
        while ($r = $rs->fetch_assoc()) {
            $productos[] = array(
                'idarticulo'       => (int)$r['idarticulo'],
                'nombre'           => $r['nombre'],
                'codigo'           => $r['codigo'],
                'precio_venta'     => (float)$r['precio_venta'],
                'stock'            => (int)round((float)$r['stock']),
                'principio_activo' => isset($r['principio_activo']) ? $r['principio_activo'] : '',
                'concentracion'    => isset($r['concentracion'])    ? $r['concentracion']    : '',
                'laboratorio'      => isset($r['laboratorio'])      ? $r['laboratorio']      : '',
                'imagen'           => $r['imagen'],
                'categoria'        => $r['categoria']
            );
        }

        echo json_encode(array(
            'ok'       => true,
            'total'    => $total,
            'pagina'   => $pagina,
            'paginas'  => max(1, (int)ceil($total / $porPagina)),
            'productos' => $productos
        ));
        break;

    // ── CATEGORÍAS ───────────────────────────────────────────
    case 'categorias':
        $joinFiltro = "a.condicion = 1";

        $rs = ejecutarConsulta(
            "SELECT c.idcategoria, c.nombre, COUNT(a.idarticulo) AS total
             FROM categoria c
             INNER JOIN articulo a ON a.idcategoria = c.idcategoria AND $joinFiltro
             GROUP BY c.idcategoria, c.nombre
             HAVING total > 0
             ORDER BY c.nombre ASC"
        );

        $cats = array();
        if ($rs) {
            while ($r = $rs->fetch_assoc()) {
                $cats[] = array(
                    'idcategoria' => (int)$r['idcategoria'],
                    'nombre'      => $r['nombre'],
                    'total'       => (int)$r['total']
                );
            }
        }
        echo json_encode(array('ok' => true, 'categorias' => $cats));
        break;

    // ── PRODUCTO INDIVIDUAL ───────────────────────────────────
    case 'producto':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        $extraSelect = $tieneColumnasRx
            ? ", IFNULL(a.principio_activo,'') AS principio_activo, IFNULL(a.concentracion,'') AS concentracion, IFNULL(a.forma_farmaceutica,'') AS forma_farmaceutica, IFNULL(a.via_administracion,'') AS via_administracion, IFNULL(a.laboratorio,'') AS laboratorio"
            : '';
        $filtroTienda = '';

        $r = ejecutarConsultaSimpleFila(
            "SELECT a.idarticulo, a.nombre, a.codigo,
                    COALESCE(NULLIF(a.precio_venta,0),
                        (SELECT di.precio_venta FROM detalle_ingreso di WHERE di.idarticulo=a.idarticulo ORDER BY di.iddetalle_ingreso DESC LIMIT 1),
                    0) AS precio_venta,
                    IFNULL(a.stock,0) AS stock,
                    IFNULL(a.descripcion,'') AS descripcion,
                    IFNULL(a.imagen,'') AS imagen,
                    IFNULL(c.nombre,'General') AS categoria
                    $extraSelect
             FROM articulo a
             LEFT JOIN categoria c ON a.idcategoria = c.idcategoria
             WHERE a.idarticulo='$id' AND a.condicion=1 $filtroTienda LIMIT 1"
        );
        if (!$r) {
            echo json_encode(array('ok'=>false,'message'=>'Producto no encontrado'));
            break;
        }
        $r['precio_venta'] = (float)$r['precio_venta'];
        $r['stock']        = (int)round((float)$r['stock']);
        echo json_encode(array('ok' => true, 'producto' => $r));
        break;

    default:
        echo json_encode(array('ok'=>false,'message'=>'Operacion no reconocida'));
}

} catch (Throwable $e) {
    echo json_encode(array('ok'=>false,'message'=>'Error: ' . $e->getMessage()));
}
?>
