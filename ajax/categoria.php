<?php 
require_once "../modelos/Categoria.php";

$categoria=new Categoria();

$idcategoria=isset($_POST["idcategoria"])? limpiarCadena($_POST["idcategoria"]):"";
$nombre=isset($_POST["nombre"])? limpiarCadena($_POST["nombre"]):"";
$descripcion=isset($_POST["descripcion"])? limpiarCadena($_POST["descripcion"]):"";

switch ($_GET["op"]) {
	case 'guardaryeditar':
	if (empty($idcategoria)) {
		$rspta=$categoria->insertar($nombre,$descripcion);
		echo $rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos";
	}else{
         $rspta=$categoria->editar($idcategoria,$nombre,$descripcion);
		echo $rspta ? "Datos actualizados correctamente" : "No se pudo actualizar los datos";
	}
		break;
	

	case 'eliminar':
		if (session_status() === PHP_SESSION_NONE) session_start();
		if (empty($_SESSION['idusuario']) || empty($_SESSION['almacen'])) {
			echo json_encode(array("ok"=>false, "message"=>"No tienes permiso para eliminar en este módulo"));
			break;
		}
		echo json_encode($categoria->eliminar($idcategoria));
		break;
	case 'activar':
		$rspta=$categoria->activar($idcategoria);
		echo $rspta ? "Datos activados correctamente" : "No se pudo activar los datos";
		break;
	
	case 'mostrar':
		$rspta=$categoria->mostrar($idcategoria);
		echo json_encode($rspta);
		break;

    case 'listar':
		$rspta=$categoria->listar();
		$data=Array();

		while ($reg=$rspta->fetch_object()) {
			$data[]=array(
            "0"=>'<button class="btn btn-warning btn-xs" title="Editar" onclick="mostrar('.$reg->idcategoria.')"><i class="fa fa-pencil"></i></button>'.' <button class="btn btn-danger btn-xs" title="Eliminar" onclick="eliminar('.$reg->idcategoria.')"><i class="fa fa-trash"></i></button>'.($reg->condicion ? '' : ' <button class="btn btn-primary btn-xs" title="Activar" onclick="activar('.$reg->idcategoria.')"><i class="fa fa-check"></i></button>'),
            "1"=>$reg->nombre,
            "2"=>$reg->descripcion,
            "3"=>($reg->condicion)?'<span class="label bg-green">Activo</span>':'<span class="label bg-red">Inactivo</span>'
              );
		}
		$results=array(
             "sEcho"=>1,//info para datatables
             "iTotalRecords"=>count($data),//enviamos el total de registros al datatable
             "iTotalDisplayRecords"=>count($data),//enviamos el total de registros a visualizar
             "aaData"=>$data); 
		echo json_encode($results);
		break;
}
 ?>