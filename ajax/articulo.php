<?php
if (strlen(session_id()) < 1) session_start();
if (!isset($_SESSION['idusuario'])) {
    echo json_encode(['ok' => false, 'message' => 'Sesión no válida']);
    exit;
}
require_once "../modelos/Articulo.php";

$articulo=new Articulo();

// Máximo de imágenes que admite un artículo (imagen, imagen2, imagen3)
define('ART_MAX_IMAGENES', 3);

/**
 * Sube (si corresponde) la imagen del slot indicado y devuelve el nombre de archivo
 * que debe quedar guardado. Reglas:
 *  - Sin archivo nuevo → conserva la imagen actual del slot.
 *  - Marcada para quitar → cadena vacía.
 *  - Archivo nuevo válido → nombre generado; si falla la subida, conserva la actual.
 */
function resolverImagenArticulo($campo){
	$actual = isset($_POST[$campo . "actual"]) ? trim($_POST[$campo . "actual"]) : "";
	if (!empty($_POST["quitar_" . $campo])) {
		$actual = "";
	}
	if (!isset($_FILES[$campo]['tmp_name']) || !is_uploaded_file($_FILES[$campo]['tmp_name'])) {
		return $actual;
	}
	$allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
	$allowedExts  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
	$ext  = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
	$mime = strtolower($_FILES[$campo]['type']);
	if (!in_array($mime, $allowedMimes, true) || !in_array($ext, $allowedExts, true)) {
		return $actual;
	}
	// Nombre único: microtiempo + slot + aleatorio (evita colisiones al subir 3 a la vez)
	$nuevoNombre = round(microtime(true)) . '_' . $campo . mt_rand(100, 999) . '.' . $ext;
	$destino = "../files/articulos/" . $nuevoNombre;
	if (move_uploaded_file($_FILES[$campo]['tmp_name'], $destino)) {
		return $nuevoNombre;
	}
	return $actual;
}

$idarticulo       = isset($_POST["idarticulo"])        ? limpiarCadena($_POST["idarticulo"])        : "";
$idcategoria      = isset($_POST["idcategoria"])       ? limpiarCadena($_POST["idcategoria"])       : "";
$idunidad         = isset($_POST["idunidad"])          ? limpiarCadena($_POST["idunidad"])          : "";
$codigo           = isset($_POST["codigo"])            ? limpiarCadena($_POST["codigo"])            : "";
$nombre           = isset($_POST["nombre"])            ? limpiarCadena($_POST["nombre"])            : "";
$stock            = isset($_POST["stock"])             ? (int)round((float)limpiarCadena($_POST["stock"])): 0;
$stock_minimo     = isset($_POST["stock_minimo"])      ? (int)round((float)limpiarCadena($_POST["stock_minimo"])): 1;
$precio_venta     = isset($_POST["precio_venta"])      ? max(0, (float)limpiarCadena($_POST["precio_venta"]))  : 0;
$descripcion      = isset($_POST["descripcion"])       ? limpiarCadena($_POST["descripcion"])       : "";
$imagen           = ""; // se resuelve en guardaryeditar con el archivo subido o imagenactual
// Campos farmacéuticos
$principio_activo   = isset($_POST["principio_activo"])   ? limpiarCadena($_POST["principio_activo"])   : "";
$concentracion      = isset($_POST["concentracion"])      ? limpiarCadena($_POST["concentracion"])      : "";
$forma_farmaceutica = isset($_POST["forma_farmaceutica"]) ? limpiarCadena($_POST["forma_farmaceutica"]) : "";
$via_administracion = isset($_POST["via_administracion"]) ? limpiarCadena($_POST["via_administracion"]) : "";
$laboratorio        = isset($_POST["laboratorio"])        ? limpiarCadena($_POST["laboratorio"])        : "";
$registro_sanitario = isset($_POST["registro_sanitario"]) ? limpiarCadena($_POST["registro_sanitario"]) : "";
$requiere_frio      = isset($_POST["requiere_frio"])      ? 1 : 0;
$tipo_venta         = isset($_POST["tipo_venta"])         ? limpiarCadena($_POST["tipo_venta"])         : "OTC";
// Oferta por tiempo limitado
$en_oferta            = isset($_POST["en_oferta"])            ? 1 : 0;
$descuento_porcentaje = isset($_POST["descuento_porcentaje"]) ? max(0, min(100, (float)limpiarCadena($_POST["descuento_porcentaje"]))) : 0;
$oferta_fecha_inicio  = isset($_POST["oferta_fecha_inicio"])  ? str_replace('T', ' ', limpiarCadena($_POST["oferta_fecha_inicio"])) : "";
$oferta_fecha_fin     = isset($_POST["oferta_fecha_fin"])     ? str_replace('T', ' ', limpiarCadena($_POST["oferta_fecha_fin"])) : "";

if ($stock < 0) {
	$stock = 0;
}
if ($stock_minimo < 0) {
	$stock_minimo = 0;
}

switch ($_GET["op"]) {
	case 'guardaryeditar':
	if (!isset($_SESSION['almacen']) || $_SESSION['almacen'] != 1) {
		echo "Sin permiso para modificar artículos";
		break;
	}
	// Resolver los 3 slots de imagen y compactarlos: la galería nunca queda con huecos,
	// así la primera imagen siempre es la principal (la que usan venta, reportes y tickets).
	$imagenes = array();
	foreach (array('imagen', 'imagen2', 'imagen3') as $campoImg) {
		$nombreImg = resolverImagenArticulo($campoImg);
		if ($nombreImg !== "") {
			$imagenes[] = $nombreImg;
		}
	}
	$imagenes = array_slice($imagenes, 0, ART_MAX_IMAGENES);
	$imagen  = isset($imagenes[0]) ? $imagenes[0] : "";
	$imagen2 = isset($imagenes[1]) ? $imagenes[1] : "";
	$imagen3 = isset($imagenes[2]) ? $imagenes[2] : "";

	if (empty($idarticulo)) {
		$rspta=$articulo->insertar($idcategoria,$idunidad,$codigo,$nombre,$stock,$stock_minimo,$precio_venta,$descripcion,$imagen,
			$principio_activo,$concentracion,$forma_farmaceutica,$via_administracion,
			$laboratorio,$registro_sanitario,$requiere_frio,$tipo_venta,
			$en_oferta,$descuento_porcentaje,$oferta_fecha_inicio,$oferta_fecha_fin,
			$imagen2,$imagen3);
		echo $rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos";
	}else{
		$rspta=$articulo->editar($idarticulo,$idcategoria,$idunidad,$codigo,$nombre,$stock,$stock_minimo,$precio_venta,$descripcion,$imagen,
			$principio_activo,$concentracion,$forma_farmaceutica,$via_administracion,
			$laboratorio,$registro_sanitario,$requiere_frio,$tipo_venta,
			$en_oferta,$descuento_porcentaje,$oferta_fecha_inicio,$oferta_fecha_fin,
			$imagen2,$imagen3);
		echo $rspta ? "Datos actualizados correctamente" : "No se pudo actualizar los datos";
	}
		break;
	

	case 'desactivar':
		if (!isset($_SESSION['almacen']) || $_SESSION['almacen'] != 1) { echo "Sin permiso"; break; }
		$rspta=$articulo->desactivar($idarticulo);
		echo $rspta ? "Datos desactivados correctamente" : "No se pudo desactivar los datos";
		break;
	case 'activar':
		if (!isset($_SESSION['almacen']) || $_SESSION['almacen'] != 1) { echo "Sin permiso"; break; }
		$rspta=$articulo->activar($idarticulo);
		echo $rspta ? "Datos activados correctamente" : "No se pudo activar los datos";
		break;
	
	case 'mostrar':
		$rspta=$articulo->mostrar($idarticulo);
		if (is_array($rspta)) {
			$rspta["stock"] = (int)round((float)$rspta["stock"]);
			$rspta["stock_minimo"] = (int)round((float)$rspta["stock_minimo"]);
		}
		echo json_encode($rspta);
		break;

    case 'listar':
		$rspta=$articulo->listar();
		$data=Array();

		while ($reg=$rspta->fetch_object()) {
			$dias = ($reg->dias_para_vencer !== null) ? (int)$reg->dias_para_vencer : null;
			if ($reg->prox_vencimiento === null) {
				$vencCol = '<span class="text-muted">Sin lotes</span>';
			} elseif ($dias < 0) {
				$vencCol = '<span class="label bg-red"><i class="fa fa-times-circle"></i> Vencido ' . $reg->prox_vencimiento . '</span>';
			} elseif ($dias <= 30) {
				$vencCol = '<span class="label bg-orange"><i class="fa fa-warning"></i> ' . $reg->prox_vencimiento . ' (' . $dias . ' d)</span>';
			} else {
				$vencCol = '<span class="label bg-green">' . $reg->prox_vencimiento . '</span>';
			}
			if (!$reg->en_oferta || (float)$reg->descuento_porcentaje <= 0) {
				$ofertaCol = '<span class="text-muted">Sin oferta</span>';
			} elseif ($reg->oferta_vigente) {
				$ofertaCol = '<span class="label bg-red"><i class="fa fa-tag"></i> Vigente -' . rtrim(rtrim(number_format((float)$reg->descuento_porcentaje,2),'0'),'.') . '%</span>';
			} elseif ($reg->oferta_fecha_inicio !== null && strtotime($reg->oferta_fecha_inicio) > time()) {
				$ofertaCol = '<span class="label bg-orange"><i class="fa fa-clock-o"></i> Programada ' . date('d/m/Y H:i', strtotime($reg->oferta_fecha_inicio)) . '</span>';
			} else {
				$ofertaCol = '<span class="label bg-default">Vencida</span>';
			}
			$galeria = array_values(array_filter(array($reg->imagen, $reg->imagen2, $reg->imagen3), function($f){
				return $f !== null && trim($f) !== '';
			}));
			if (count($galeria) === 0) {
				$imgCol = '<span class="text-muted"><i class="fa fa-image"></i> Sin imagen</span>';
			} else {
				$imgCol = "<img src='../files/articulos/" . htmlspecialchars($galeria[0]) . "' height='50px' width='50px'>";
				if (count($galeria) > 1) {
					$imgCol .= ' <span class="label bg-blue" title="Imágenes en la galería">+' . (count($galeria) - 1) . '</span>';
				}
			}
			$data[]=array(
            "0"=>($reg->condicion)?'<button class="btn btn-warning btn-xs" onclick="mostrar('.$reg->idarticulo.')"><i class="fa fa-pencil"></i></button>'.' '.'<button class="btn btn-danger btn-xs" onclick="desactivar('.$reg->idarticulo.')"><i class="fa fa-close"></i></button>':'<button class="btn btn-warning btn-xs" onclick="mostrar('.$reg->idarticulo.')"><i class="fa fa-pencil"></i></button>'.' '.'<button class="btn btn-primary btn-xs" onclick="activar('.$reg->idarticulo.')"><i class="fa fa-check"></i></button>',
            "1"=>$reg->nombre,
            "2"=>$reg->categoria,
            "3"=>$reg->abreviatura,
            "4"=>$reg->codigo,
            "5"=>(int)round((float)$reg->stock),
            "6"=>(int)round((float)$reg->stock_minimo),
            "7"=>formatearMoneda((float)$reg->precio_venta),
            "8"=>$imgCol,
            "9"=>$reg->descripcion,
            "10"=>($reg->condicion)?'<span class="label bg-green">Activado</span>':'<span class="label bg-red">Desactivado</span>',
            "11"=>$vencCol,
            "12"=>$ofertaCol
              );
		}
		$draw = isset($_GET['draw']) ? (int)$_GET['draw'] : (isset($_GET['sEcho']) ? (int)$_GET['sEcho'] : 1);
		$results=array(
             "draw"=>$draw,
             "sEcho"=>$draw,
             "iTotalRecords"=>count($data),
             "iTotalDisplayRecords"=>count($data),
             "aaData"=>$data);
		echo json_encode($results);
		break;

		case 'selectUnidad':
			require_once "../modelos/Unidad.php";
			$unidad=new Unidad();
			$rspta=$unidad->select();

			while ($reg=$rspta->fetch_object()) {
				echo '<option value=' . $reg->idunidad.'>'.$reg->nombre.' ('.$reg->abreviatura.')</option>';
			}
			break;

		case 'selectCategoria':
			require_once "../modelos/Categoria.php";
			$categoria=new Categoria();

			$rspta=$categoria->select();

			while ($reg=$rspta->fetch_object()) {
				echo '<option value=' . $reg->idcategoria.'>'.$reg->nombre.'</option>';
			}
			break;

		case 'notifStock':
			header('Content-Type: application/json');
			$sql = "SELECT idarticulo, codigo, nombre,
			               CAST(stock AS DECIMAL(14,2)) AS stock,
			               CAST(IFNULL(stock_minimo,0) AS DECIMAL(14,2)) AS stock_minimo
			        FROM articulo
			        WHERE condicion = 1
			          AND stock <= GREATEST(IFNULL(stock_minimo, 0), 1)
			        ORDER BY stock ASC
			        LIMIT 10";
			$res = ejecutarConsulta($sql);
			$productos = [];
			while ($reg = $res->fetch_assoc()) {
				$productos[] = [
					'idarticulo'  => (int)$reg['idarticulo'],
					'codigo'      => $reg['codigo'],
					'nombre'      => $reg['nombre'],
					'stock'       => (float)$reg['stock'],
					'stock_minimo'=> (float)$reg['stock_minimo'],
				];
			}
			$sqlTotal = "SELECT COUNT(*) AS n FROM articulo
			             WHERE condicion=1 AND stock <= GREATEST(IFNULL(stock_minimo,0), 1)";
			$resTotal = ejecutarConsultaSimpleFila($sqlTotal);
			echo json_encode([
				'ok'       => true,
				'criticos' => (int)($resTotal['n'] ?? 0),
				'productos'=> $productos,
			]);
			break;
}
 ?>
