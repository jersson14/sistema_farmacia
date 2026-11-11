<?php 
require_once "../modelos/Persona.php";
require_once "../config/Autorizacion.php";
if (session_status() === PHP_SESSION_NONE) session_start();

// Proveedores: registrar es libre; editar o eliminar requiere el permiso
// "Editar y anular compras" o autorización del administrador.
if (!function_exists('esProveedorBloqueado')) {
	function esProveedorBloqueado($idpersona) {
		$idpersona = (int)$idpersona;
		if ($idpersona <= 0) return false;
		$row = ejecutarConsultaSimpleFila("SELECT tipo_persona FROM persona WHERE idpersona='$idpersona' LIMIT 1");
		if (!$row || strtoupper(trim($row['tipo_persona'])) !== 'PROVEEDOR') return false;
		return !autPuede('proveedor', $idpersona);
	}
}

$persona=new Persona();

$idpersona=isset($_POST["idpersona"])? limpiarCadena($_POST["idpersona"]):"";
$tipo_persona=isset($_POST["tipo_persona"])? limpiarCadena($_POST["tipo_persona"]):"";
$nombre=isset($_POST["nombre"])? limpiarCadena($_POST["nombre"]):"";
$tipo_documento=isset($_POST["tipo_documento"])? limpiarCadena($_POST["tipo_documento"]):"";
$num_documento=isset($_POST["num_documento"])? limpiarCadena($_POST["num_documento"]):"";
$direccion=isset($_POST["direccion"])? limpiarCadena($_POST["direccion"]):"";
$telefono=isset($_POST["telefono"])? limpiarCadena($_POST["telefono"]):"";
$email=isset($_POST["email"])? limpiarCadena($_POST["email"]):"";

switch ($_GET["op"]) {
	case 'guardaryeditar':
	if (empty($idpersona)) {
		$rspta=$persona->insertar($tipo_persona,$nombre,$tipo_documento,$num_documento,$direccion,$telefono,$email);
		echo $rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos";
	}else{
		if (esProveedorBloqueado($idpersona)) {
			echo "Para modificar un proveedor necesitas la autorización de un administrador.";
			break;
		}
         $rspta=$persona->editar($idpersona,$tipo_persona,$nombre,$tipo_documento,$num_documento,$direccion,$telefono,$email);
		echo $rspta ? "Datos actualizados correctamente" : "No se pudo actualizar los datos";
	}
		break;
	

	case 'eliminar':
		if (session_status() === PHP_SESSION_NONE) session_start();
		if (empty($_SESSION['idusuario'])) {
			echo json_encode(array("ok"=>false, "message"=>"Tu sesión venció. Vuelve a iniciar sesión."));
			break;
		}
		if (esProveedorBloqueado($idpersona)) {
			echo json_encode(autRespuestaRequerida('Para eliminar un proveedor necesitas la autorización de un administrador.'));
			break;
		}
		$rsptaEl = $persona->eliminar($idpersona);
		if (!empty($rsptaEl['ok'])) autConsumir('proveedor', $idpersona);
		echo json_encode($rsptaEl);
		break;
	
	case 'mostrar':
		$rspta=$persona->mostrar($idpersona);
		echo json_encode($rspta);
		break;

    case 'listarp':
		$rspta=$persona->listarp();
		$data=Array();

		while ($reg=$rspta->fetch_object()) {
			$data[]=array(
            "0"=>'<button class="btn btn-warning btn-xs" title="Editar proveedor" onclick="mostrar('.$reg->idpersona.')"><i class="fa fa-pencil"></i>'.autCandado('proveedor', $reg->idpersona).'</button>'.' '.'<button class="btn btn-danger btn-xs" title="Eliminar proveedor" onclick="eliminar('.$reg->idpersona.')"><i class="fa fa-trash"></i>'.autCandado('proveedor', $reg->idpersona).'</button>',
            "1"=>$reg->nombre,
            "2"=>$reg->tipo_documento.": ".$reg->num_documento,
            "3"=>(!empty($reg->direccion) ? $reg->direccion : "-"),
            "4"=>$reg->telefono,
            "5"=>$reg->email
              );
		}
		$results=array(
             "sEcho"=>1,//info para datatables
             "iTotalRecords"=>count($data),//enviamos el total de registros al datatable
             "iTotalDisplayRecords"=>count($data),//enviamos el total de registros a visualizar
             "aaData"=>$data);
		echo json_encode($results);
		break;

		  case 'listarc':
		$rspta=$persona->listarc();
		$data=Array();

		while ($reg=$rspta->fetch_object()) {
			$data[]=array(
            "0"=>'<button class="btn btn-warning btn-xs" onclick="mostrar('.$reg->idpersona.')"><i class="fa fa-pencil"></i></button>'.' '.'<button class="btn btn-danger btn-xs" onclick="eliminar('.$reg->idpersona.')"><i class="fa fa-trash"></i></button>',
            "1"=>$reg->nombre,
            "2"=>$reg->tipo_documento,
            "3"=>$reg->num_documento,
            "4"=>$reg->telefono,
            "5"=>$reg->email
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