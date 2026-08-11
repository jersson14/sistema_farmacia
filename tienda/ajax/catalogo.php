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
$tieneColumnasOferta = _catColExists('en_oferta');
$tieneGaleria        = _catColExists('imagen2');

// Máximo de imágenes por producto
define('CAT_MAX_IMAGENES', 3);

/**
 * Convierte un parámetro multiselección ("a|b|c") en un array de valores limpios.
 */
function _catLista($param) {
    if (!isset($_GET[$param]) || trim($_GET[$param]) === '') return array();
    $partes = explode('|', $_GET[$param]);
    $out = array();
    foreach ($partes as $p) {
        $p = limpiarCadena(trim($p));
        if ($p !== '') $out[] = $p;
    }
    return array_slice(array_unique($out), 0, 50);
}

/**
 * Arma la lista de imágenes de un producto (máx. 3, sin huecos).
 */
function _catImagenes($fila) {
    $imgs = array();
    foreach (array('imagen', 'imagen2', 'imagen3') as $campo) {
        if (!empty($fila[$campo]) && trim($fila[$campo]) !== '') {
            $imgs[] = trim($fila[$campo]);
        }
    }
    return array_slice($imgs, 0, CAT_MAX_IMAGENES);
}

/**
 * Construye el WHERE compartido por 'listar' y 'filtros' a partir de los
 * parámetros de búsqueda y de los multiselect del panel lateral.
 * $incluirMulti=false devuelve solo el filtro de texto (para contar opciones).
 */
function _catWhere($incluirMulti = true) {
    global $tieneColumnasRx, $tieneColumnasOferta;

    $where = array("a.condicion=1");

    $busqueda = isset($_GET['q']) ? limpiarCadena(trim($_GET['q'])) : '';
    if ($busqueda !== '') {
        $busqFiltro = "(a.nombre LIKE '%$busqueda%' OR a.codigo LIKE '%$busqueda%'";
        if ($tieneColumnasRx) {
            $busqFiltro .= " OR a.principio_activo LIKE '%$busqueda%' OR a.laboratorio LIKE '%$busqueda%'";
        }
        $busqFiltro .= ')';
        $where[] = $busqFiltro;
    }

    if (!$incluirMulti) return $where;

    // Categorías (multiselección por id)
    $cats = array();
    foreach (_catLista('cats') as $c) {
        $c = (int)$c;
        if ($c > 0) $cats[] = $c;
    }
    // Compatibilidad con el filtro simple anterior (?idcat=)
    $idcat = isset($_GET['idcat']) ? (int)$_GET['idcat'] : 0;
    if ($idcat > 0 && !in_array($idcat, $cats, true)) $cats[] = $idcat;
    if (count($cats) > 0) {
        $where[] = "a.idcategoria IN (" . implode(',', $cats) . ")";
    }

    if ($tieneColumnasRx) {
        // Laboratorios (multiselección por nombre)
        $labs = _catLista('labs');
        if (count($labs) > 0) {
            $where[] = "a.laboratorio IN ('" . implode("','", $labs) . "')";
        }
        // Forma farmacéutica / presentación (multiselección por nombre)
        $formas = _catLista('formas');
        if (count($formas) > 0) {
            $where[] = "a.forma_farmaceutica IN ('" . implode("','", $formas) . "')";
        }
        // Tipo de venta (OTC / RX / control especial)
        $tipos = array();
        foreach (_catLista('tipos') as $t) {
            if (in_array($t, array('OTC', 'RX', 'CONTROL_ESPECIAL'), true)) $tipos[] = $t;
        }
        if (count($tipos) > 0) {
            $where[] = "a.tipo_venta IN ('" . implode("','", $tipos) . "')";
        }
    }

    // Solo productos en oferta vigente
    if (!empty($_GET['oferta']) && $tieneColumnasOferta) {
        $where[] = sqlOfertaVigenteExpr('a');
    }
    // Solo productos disponibles
    if (!empty($_GET['stock'])) {
        $where[] = "IFNULL(a.stock,0) > 0";
    }

    return $where;
}

ob_end_clean(); // descartar cualquier salida accidental hasta aquí
header('Content-Type: application/json; charset=utf-8');

$op = $_GET['op'] ?? 'listar';

try {

switch ($op) {

    // ── LISTAR ───────────────────────────────────────────────
    case 'listar':
        $pagina    = max(1, (int)($_GET['pagina'] ?? 1));
        $porPagina = 15;
        $offset    = ($pagina - 1) * $porPagina;

        // Solo artículos activos. Mostrar TODOS (incluso sin stock y sin importar
        // tipo_venta) para que el catálogo muestre el inventario completo.
        $filtro = ' WHERE ' . implode(' AND ', _catWhere());

        $total    = 0;
        $countRow = ejecutarConsultaSimpleFila("SELECT COUNT(*) AS total FROM articulo a $filtro");
        if ($countRow) $total = (int)$countRow['total'];

        $extraSelect = $tieneColumnasRx
            ? ", IFNULL(a.principio_activo,'') AS principio_activo, IFNULL(a.concentracion,'') AS concentracion, IFNULL(a.laboratorio,'') AS laboratorio, IFNULL(a.forma_farmaceutica,'') AS forma_farmaceutica"
            : '';
        // Si el medicamento se vende con o sin receta (se muestra al cliente en la tarjeta)
        $extraSelect .= $tieneColumnasTienda
            ? ", IFNULL(a.tipo_venta,'OTC') AS tipo_venta"
            : ", 'OTC' AS tipo_venta";
        $galeriaSelect = $tieneGaleria
            ? ", IFNULL(a.imagen2,'') AS imagen2, IFNULL(a.imagen3,'') AS imagen3"
            : ", '' AS imagen2, '' AS imagen3";

        $precioBase = sqlPrecioBaseExpr('a');
        if ($tieneColumnasOferta) {
            $ofertaSelect = ", " . sqlPrecioFinalExpr('a') . " AS precio_final, $precioBase AS precio_original, " . sqlOfertaVigenteExpr('a') . " AS en_oferta, a.descuento_porcentaje";
            // Las ofertas vigentes se muestran primero (se pintan en grande en el catálogo)
            $orden = "(" . sqlOfertaVigenteExpr('a') . ") DESC, a.nombre ASC";
        } else {
            $ofertaSelect = ", $precioBase AS precio_final, $precioBase AS precio_original, 0 AS en_oferta, 0 AS descuento_porcentaje";
            $orden = "a.nombre ASC";
        }

        // Ordenamiento elegido por el cliente
        switch ($_GET['orden'] ?? '') {
            case 'precio_asc':  $orden = "precio_final ASC, a.nombre ASC"; break;
            case 'precio_desc': $orden = "precio_final DESC, a.nombre ASC"; break;
            case 'nombre':      $orden = "a.nombre ASC"; break;
        }

        $sql = "SELECT a.idarticulo, a.nombre, a.codigo,
                       IFNULL(a.stock, 0) AS stock,
                       IFNULL(a.imagen,'') AS imagen,
                       IFNULL(c.nombre,'General') AS categoria
                       $galeriaSelect
                       $extraSelect
                       $ofertaSelect
                FROM articulo a
                LEFT JOIN categoria c ON a.idcategoria = c.idcategoria
                $filtro
                ORDER BY $orden
                LIMIT $porPagina OFFSET $offset";

        $rs = ejecutarConsulta($sql);
        if (!$rs) {
            echo json_encode(array('ok'=>false,'message'=>'Error al consultar productos. Verifica la base de datos.'));
            break;
        }

        $productos = array();
        while ($r = $rs->fetch_assoc()) {
            $imagenes = _catImagenes($r);
            $productos[] = array(
                'idarticulo'       => (int)$r['idarticulo'],
                'nombre'           => $r['nombre'],
                'codigo'           => $r['codigo'],
                'precio_venta'     => (float)$r['precio_final'],
                'precio_original'  => (float)$r['precio_original'],
                'en_oferta'        => !empty($r['en_oferta']) ? 1 : 0,
                'descuento_porcentaje' => (float)$r['descuento_porcentaje'],
                'stock'            => (int)round((float)$r['stock']),
                'principio_activo' => isset($r['principio_activo']) ? $r['principio_activo'] : '',
                'concentracion'    => isset($r['concentracion'])    ? $r['concentracion']    : '',
                'laboratorio'      => isset($r['laboratorio'])      ? $r['laboratorio']      : '',
                'forma_farmaceutica' => isset($r['forma_farmaceutica']) ? $r['forma_farmaceutica'] : '',
                'tipo_venta'       => isset($r['tipo_venta'])       ? $r['tipo_venta']       : 'OTC',
                'imagen'           => $r['imagen'],
                'imagenes'         => $imagenes,
                'total_imagenes'   => count($imagenes),
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

    // ── OPCIONES DE FILTRO (panel multiselección) ────────────
    // Los conteos respetan solo el término de búsqueda, no las casillas marcadas,
    // para que las opciones no desaparezcan mientras el usuario filtra.
    case 'filtros':
        $filtroBase = ' WHERE ' . implode(' AND ', _catWhere(false));

        // Categorías
        $categorias = array();
        $rs = ejecutarConsulta(
            "SELECT c.idcategoria AS valor, c.nombre AS etiqueta, COUNT(*) AS total
             FROM articulo a
             INNER JOIN categoria c ON a.idcategoria = c.idcategoria
             $filtroBase
             GROUP BY c.idcategoria, c.nombre
             ORDER BY c.nombre ASC"
        );
        if ($rs) {
            while ($r = $rs->fetch_assoc()) {
                $categorias[] = array('valor'=>(string)$r['valor'], 'etiqueta'=>$r['etiqueta'], 'total'=>(int)$r['total']);
            }
        }

        $laboratorios = array();
        $formas       = array();
        $tipos        = array();
        if ($tieneColumnasRx) {
            $rs = ejecutarConsulta(
                "SELECT a.laboratorio AS valor, COUNT(*) AS total
                 FROM articulo a
                 $filtroBase AND a.laboratorio IS NOT NULL AND TRIM(a.laboratorio) <> ''
                 GROUP BY a.laboratorio
                 ORDER BY total DESC, a.laboratorio ASC
                 LIMIT 40"
            );
            if ($rs) {
                while ($r = $rs->fetch_assoc()) {
                    $laboratorios[] = array('valor'=>$r['valor'], 'etiqueta'=>$r['valor'], 'total'=>(int)$r['total']);
                }
            }

            $rs = ejecutarConsulta(
                "SELECT a.forma_farmaceutica AS valor, COUNT(*) AS total
                 FROM articulo a
                 $filtroBase AND a.forma_farmaceutica IS NOT NULL AND TRIM(a.forma_farmaceutica) <> ''
                 GROUP BY a.forma_farmaceutica
                 ORDER BY total DESC, a.forma_farmaceutica ASC
                 LIMIT 30"
            );
            if ($rs) {
                while ($r = $rs->fetch_assoc()) {
                    $formas[] = array('valor'=>$r['valor'], 'etiqueta'=>$r['valor'], 'total'=>(int)$r['total']);
                }
            }

            $etiquetasTipo = array(
                'OTC'              => 'Sin receta (venta libre)',
                'RX'               => 'Con receta médica',
                'CONTROL_ESPECIAL' => 'Receta retenida (control especial)'
            );
            $rs = ejecutarConsulta(
                "SELECT IFNULL(a.tipo_venta,'OTC') AS valor, COUNT(*) AS total
                 FROM articulo a
                 $filtroBase
                 GROUP BY IFNULL(a.tipo_venta,'OTC')
                 ORDER BY total DESC"
            );
            if ($rs) {
                while ($r = $rs->fetch_assoc()) {
                    $tipos[] = array(
                        'valor'    => $r['valor'],
                        'etiqueta' => isset($etiquetasTipo[$r['valor']]) ? $etiquetasTipo[$r['valor']] : $r['valor'],
                        'total'    => (int)$r['total']
                    );
                }
            }
        }

        // Cuántos productos están hoy en oferta
        $totalOferta = 0;
        if ($tieneColumnasOferta) {
            $row = ejecutarConsultaSimpleFila(
                "SELECT COUNT(*) AS total FROM articulo a $filtroBase AND " . sqlOfertaVigenteExpr('a')
            );
            if ($row) $totalOferta = (int)$row['total'];
        }

        echo json_encode(array(
            'ok'           => true,
            'categorias'   => $categorias,
            'laboratorios' => $laboratorios,
            'formas'       => $formas,
            'tipos'        => $tipos,
            'total_oferta' => $totalOferta
        ));
        break;

    // ── PRODUCTO INDIVIDUAL ───────────────────────────────────
    case 'producto':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        $extraSelect = $tieneColumnasRx
            ? ", IFNULL(a.principio_activo,'') AS principio_activo, IFNULL(a.concentracion,'') AS concentracion, IFNULL(a.forma_farmaceutica,'') AS forma_farmaceutica, IFNULL(a.via_administracion,'') AS via_administracion, IFNULL(a.laboratorio,'') AS laboratorio"
            : '';
        $extraSelect .= $tieneColumnasTienda
            ? ", IFNULL(a.tipo_venta,'OTC') AS tipo_venta"
            : ", 'OTC' AS tipo_venta";
        $galeriaSelectP = $tieneGaleria
            ? ", IFNULL(a.imagen2,'') AS imagen2, IFNULL(a.imagen3,'') AS imagen3"
            : ", '' AS imagen2, '' AS imagen3";
        $filtroTienda = '';

        $precioBaseP = sqlPrecioBaseExpr('a');
        if ($tieneColumnasOferta) {
            $ofertaSelectP = ", " . sqlPrecioFinalExpr('a') . " AS precio_final, $precioBaseP AS precio_original, " . sqlOfertaVigenteExpr('a') . " AS en_oferta, a.descuento_porcentaje";
        } else {
            $ofertaSelectP = ", $precioBaseP AS precio_final, $precioBaseP AS precio_original, 0 AS en_oferta, 0 AS descuento_porcentaje";
        }

        $r = ejecutarConsultaSimpleFila(
            "SELECT a.idarticulo, a.nombre, a.codigo,
                    IFNULL(a.stock,0) AS stock,
                    IFNULL(a.descripcion,'') AS descripcion,
                    IFNULL(a.imagen,'') AS imagen,
                    IFNULL(c.nombre,'General') AS categoria
                    $galeriaSelectP
                    $extraSelect
                    $ofertaSelectP
             FROM articulo a
             LEFT JOIN categoria c ON a.idcategoria = c.idcategoria
             WHERE a.idarticulo='$id' AND a.condicion=1 $filtroTienda LIMIT 1"
        );
        if (!$r) {
            echo json_encode(array('ok'=>false,'message'=>'Producto no encontrado'));
            break;
        }
        $r['precio_venta']    = (float)$r['precio_final'];
        $r['precio_original'] = (float)$r['precio_original'];
        $r['en_oferta']       = !empty($r['en_oferta']) ? 1 : 0;
        $r['descuento_porcentaje'] = (float)$r['descuento_porcentaje'];
        $r['stock']        = (int)round((float)$r['stock']);
        $r['imagenes']     = _catImagenes($r);
        $r['total_imagenes'] = count($r['imagenes']);
        echo json_encode(array('ok' => true, 'producto' => $r));
        break;

    default:
        echo json_encode(array('ok'=>false,'message'=>'Operacion no reconocida'));
}

} catch (Throwable $e) {
    echo json_encode(array('ok'=>false,'message'=>'Error: ' . $e->getMessage()));
}
?>
