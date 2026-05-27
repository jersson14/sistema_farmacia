<?php
if (strlen(session_id()) < 1) session_start();
if (!isset($_SESSION['idusuario'])) {
    echo json_encode(['ok' => false, 'message' => 'Sesión no válida']);
    exit;
}
require_once "../modelos/Articulo.php";

$articulo=new Articulo();

$idarticulo       = isset($_POST["idarticulo"])        ? limpiarCadena($_POST["idarticulo"])        : "";
$idcategoria      = isset($_POST["idcategoria"])       ? limpiarCadena($_POST["idcategoria"])       : "";
$idunidad         = isset($_POST["idunidad"])          ? limpiarCadena($_POST["idunidad"])          : "";
$codigo           = isset($_POST["codigo"])            ? limpiarCadena($_POST["codigo"])            : "";
$nombre           = isset($_POST["nombre"])            ? limpiarCadena($_POST["nombre"])            : "";
$stock            = isset($_POST["stock"])             ? (int)round((float)limpiarCadena($_POST["stock"])): 0;
$stock_minimo     = isset($_POST["stock_minimo"])      ? (int)round((float)limpiarCadena($_POST["stock_minimo"])): 1;
$precio_venta     = isset($_POST["precio_venta"])      ? max(0, (float)limpiarCadena($_POST["precio_venta"]))  : 0;
$descripcion      = isset($_POST["descripcion"])       ? limpiarCadena($_POST["descripcion"])       : "";
$imagen           = isset($_POST["imagen"])            ? limpiarCadena($_POST["imagen"])            : "";
// Campos farmacéuticos
$principio_activo   = isset($_POST["principio_activo"])   ? limpiarCadena($_POST["principio_activo"])   : "";
$concentracion      = isset($_POST["concentracion"])      ? limpiarCadena($_POST["concentracion"])      : "";
$forma_farmaceutica = isset($_POST["forma_farmaceutica"]) ? limpiarCadena($_POST["forma_farmaceutica"]) : "";
$via_administracion = isset($_POST["via_administracion"]) ? limpiarCadena($_POST["via_administracion"]) : "";
$laboratorio        = isset($_POST["laboratorio"])        ? limpiarCadena($_POST["laboratorio"])        : "";
$registro_sanitario = isset($_POST["registro_sanitario"]) ? limpiarCadena($_POST["registro_sanitario"]) : "";
$requiere_frio      = isset($_POST["requiere_frio"])      ? 1 : 0;
$tipo_venta         = isset($_POST["tipo_venta"])         ? limpiarCadena($_POST["tipo_venta"])         : "OTC";

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
	if (!file_exists($_FILES['imagen']['tmp_name'])|| !is_uploaded_file($_FILES['imagen']['tmp_name'])) {
		$imagen=$_POST["imagenactual"];
	}else{
		$ext=explode(".", $_FILES["imagen"]["name"]);
		if ($_FILES['imagen']['type']=="image/jpg" || $_FILES['imagen']['type']=="image/jpeg" || $_FILES['imagen']['type']=="image/png") {
			$imagen=round(microtime(true)).'.'. end($ext);
			move_uploaded_file($_FILES["imagen"]["tmp_name"], "../files/articulos/".$imagen);
		}
	}
	if (empty($idarticulo)) {
		$rspta=$articulo->insertar($idcategoria,$idunidad,$codigo,$nombre,$stock,$stock_minimo,$precio_venta,$descripcion,$imagen,
			$principio_activo,$concentracion,$forma_farmaceutica,$via_administracion,
			$laboratorio,$registro_sanitario,$requiere_frio,$tipo_venta);
		echo $rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos";
	}else{
		$rspta=$articulo->editar($idarticulo,$idcategoria,$idunidad,$codigo,$nombre,$stock,$stock_minimo,$precio_venta,$descripcion,$imagen,
			$principio_activo,$concentracion,$forma_farmaceutica,$via_administracion,
			$laboratorio,$registro_sanitario,$requiere_frio,$tipo_venta);
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
			$data[]=array(
            "0"=>($reg->condicion)?'<button class="btn btn-warning btn-xs" onclick="mostrar('.$reg->idarticulo.')"><i class="fa fa-pencil"></i></button>'.' '.'<button class="btn btn-danger btn-xs" onclick="desactivar('.$reg->idarticulo.')"><i class="fa fa-close"></i></button>':'<button class="btn btn-warning btn-xs" onclick="mostrar('.$reg->idarticulo.')"><i class="fa fa-pencil"></i></button>'.' '.'<button class="btn btn-primary btn-xs" onclick="activar('.$reg->idarticulo.')"><i class="fa fa-check"></i></button>',
            "1"=>$reg->nombre,
            "2"=>$reg->categoria,
            "3"=>$reg->abreviatura,
            "4"=>$reg->codigo,
            "5"=>(int)round((float)$reg->stock),
            "6"=>(int)round((float)$reg->stock_minimo),
            "7"=>formatearMoneda((float)$reg->precio_venta),
            "8"=>"<img src='../files/articulos/".$reg->imagen."' height='50px' width='50px'>",
            "9"=>$reg->descripcion,
            "10"=>($reg->condicion)?'<span class="label bg-green">Activado</span>':'<span class="label bg-red">Desactivado</span>'
              );
		}
		$results=array(
             "sEcho"=>1,//info para datatables
             "iTotalRecords"=>count($data),//enviamos el total de registros al datatable
             "iTotalDisplayRecords"=>count($data),//enviamos el total de registros a visualizar
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
