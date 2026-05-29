<?php 
require_once "../modelos/Ingreso.php";
if (strlen(session_id())<1) 
	session_start();

$ingreso=new Ingreso();

$idingreso=isset($_POST["idingreso"])? limpiarCadena($_POST["idingreso"]):"";
$idproveedor=isset($_POST["idproveedor"])? limpiarCadena($_POST["idproveedor"]):"";
$idusuario=$_SESSION["idusuario"];
$tipo_comprobante=isset($_POST["tipo_comprobante"])? limpiarCadena($_POST["tipo_comprobante"]):"";
$serie_comprobante=isset($_POST["serie_comprobante"])? limpiarCadena($_POST["serie_comprobante"]):"";
$num_comprobante=isset($_POST["num_comprobante"])? limpiarCadena($_POST["num_comprobante"]):"";
$fecha_hora=isset($_POST["fecha_hora"])? limpiarCadena($_POST["fecha_hora"]):"";
$impuesto=isset($_POST["impuesto"])? limpiarCadena($_POST["impuesto"]):"";
$total_compra=isset($_POST["total_compra"])? limpiarCadena($_POST["total_compra"]):"";

if (!function_exists('fechaFiltroSeguroIngreso')) {
	function fechaFiltroSeguroIngreso($valor) {
		$valor = trim((string)$valor);
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
			return $valor;
		}
		return '';
	}
}


switch ($_GET["op"]) {
	case 'resumenPagos':
		// Verificar si la columna metodo_pago existe
		$colCheck = ejecutarConsultaSimpleFila("SHOW COLUMNS FROM ingreso LIKE 'metodo_pago'");
		if (!$colCheck) {
			// Columna aún no existe, retornar solo total general
			$fecha_inicioRP = fechaFiltroSeguroIngreso(isset($_GET["fecha_inicio"]) ? $_GET["fecha_inicio"] : '');
			$fecha_finRP    = fechaFiltroSeguroIngreso(isset($_GET["fecha_fin"])    ? $_GET["fecha_fin"]    : '');
			$whereRP = array("estado='Aceptado'");
			if ($fecha_inicioRP !== '') { $whereRP[] = "DATE(fecha_hora)>='$fecha_inicioRP'"; }
			if ($fecha_finRP    !== '') { $whereRP[] = "DATE(fecha_hora)<='$fecha_finRP'"; }
			$filtroRP = implode(" AND ", $whereRP);
			$rowRP = ejecutarConsultaSimpleFila("SELECT IFNULL(SUM(total_compra),0) AS total_general, COUNT(*) AS cantidad FROM ingreso WHERE $filtroRP");
			echo json_encode(array('ok'=>true,'data'=>array(
				'total_efectivo'=>0,'total_yape'=>0,'total_tarjeta'=>0,
				'total_general'=>$rowRP ? (float)$rowRP['total_general'] : 0,
				'cantidad'=>$rowRP ? (int)$rowRP['cantidad'] : 0
			)));
			break;
		}
		$fecha_inicioRP = fechaFiltroSeguroIngreso(isset($_GET["fecha_inicio"]) ? $_GET["fecha_inicio"] : '');
		$fecha_finRP    = fechaFiltroSeguroIngreso(isset($_GET["fecha_fin"])    ? $_GET["fecha_fin"]    : '');
		$whereRP = array("estado='Aceptado'");
		if ($fecha_inicioRP !== '') { $whereRP[] = "DATE(fecha_hora)>='$fecha_inicioRP'"; }
		if ($fecha_finRP    !== '') { $whereRP[] = "DATE(fecha_hora)<='$fecha_finRP'"; }
		$filtroRP = implode(" AND ", $whereRP);
		$sqlRP = "SELECT
			IFNULL(SUM(CASE WHEN metodo_pago='EFECTIVO' THEN total_compra ELSE 0 END),0) AS total_efectivo,
			IFNULL(SUM(CASE WHEN metodo_pago IN ('YAPE','PLIN') THEN total_compra ELSE 0 END),0) AS total_yape,
			IFNULL(SUM(CASE WHEN metodo_pago='TARJETA' THEN total_compra ELSE 0 END),0) AS total_tarjeta,
			IFNULL(SUM(total_compra),0) AS total_general,
			COUNT(*) AS cantidad
			FROM ingreso WHERE $filtroRP";
		$rowRP = ejecutarConsultaSimpleFila($sqlRP);
		echo json_encode(array('ok'=>true,'data'=>array(
			'total_efectivo' => $rowRP ? (float)$rowRP['total_efectivo'] : 0,
			'total_yape'     => $rowRP ? (float)$rowRP['total_yape']     : 0,
			'total_tarjeta'  => $rowRP ? (float)$rowRP['total_tarjeta']  : 0,
			'total_general'  => $rowRP ? (float)$rowRP['total_general']  : 0,
			'cantidad'       => $rowRP ? (int)$rowRP['cantidad']          : 0,
		)));
		break;

	case 'guardaryeditar':
	if (empty($idingreso)) {
		$numero_lote_arr       = isset($_POST["numero_lote"])       ? $_POST["numero_lote"]       : array();
		$fecha_vencimiento_arr = isset($_POST["fecha_vencimiento"]) ? $_POST["fecha_vencimiento"] : array();
		$fecha_fabricacion_arr = isset($_POST["fecha_fabricacion"]) ? $_POST["fecha_fabricacion"] : array();
		$temperatura_recepcion = (isset($_POST["temperatura_recepcion"]) && $_POST["temperatura_recepcion"] !== '') ? (float)$_POST["temperatura_recepcion"] : null;
		$temp_observacion      = isset($_POST["temp_observacion"]) ? limpiarCadena($_POST["temp_observacion"]) : '';
		$metodo_pago           = limpiarCadena(isset($_POST["metodo_pago"]) ? $_POST["metodo_pago"] : 'EFECTIVO');
		$rspta=$ingreso->insertar($idproveedor,$idusuario,$tipo_comprobante,$serie_comprobante,$num_comprobante,$fecha_hora,$impuesto,$total_compra,$_POST["idarticulo"],$_POST["cantidad"],$_POST["precio_compra"],$_POST["precio_venta"],$numero_lote_arr,$fecha_vencimiento_arr,$fecha_fabricacion_arr,$temperatura_recepcion,$temp_observacion,$metodo_pago);
		if (is_array($rspta)) {
			if (!empty($rspta["ok"])) {
				echo json_encode(array(
					"ok"=>true,
					"message"=>"Datos registrados correctamente",
					"serie_comprobante"=>isset($rspta["serie_comprobante"])?$rspta["serie_comprobante"]:"",
					"num_comprobante"=>isset($rspta["num_comprobante"])?$rspta["num_comprobante"]:""
				));
			}else{
				echo json_encode(array(
					"ok"=>false,
					"message"=>isset($rspta["message"])?$rspta["message"]:"No se pudo registrar los datos"
				));
			}
		}else{
			echo json_encode(array(
				"ok"=>(bool)$rspta,
				"message"=>$rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos"
			));
		}
	}else{
		echo json_encode(array(
			"ok"=>false,
			"message"=>"Edicion de ingreso no disponible desde este formulario"
		));
	}
		break;

	case 'agregarDetalle':
		$idingreso_amp = isset($_POST["idingreso"]) ? (int)$_POST["idingreso"] : 0;
		if ($idingreso_amp <= 0) {
			echo json_encode(array("ok"=>false, "message"=>"ID de ingreso inválido"));
			break;
		}
		$idarticulo_amp      = isset($_POST["idarticulo"])       ? $_POST["idarticulo"]       : array();
		$cantidad_amp        = isset($_POST["cantidad"])         ? $_POST["cantidad"]         : array();
		$precio_compra_amp   = isset($_POST["precio_compra"])    ? $_POST["precio_compra"]    : array();
		$precio_venta_amp    = isset($_POST["precio_venta"])     ? $_POST["precio_venta"]     : array();
		$numero_lote_amp     = isset($_POST["numero_lote"])      ? $_POST["numero_lote"]      : array();
		$fvenc_amp           = isset($_POST["fecha_vencimiento"])? $_POST["fecha_vencimiento"]: array();
		$ffab_amp            = isset($_POST["fecha_fabricacion"]) ? $_POST["fecha_fabricacion"]: array();
		$rspta_amp = $ingreso->agregarDetalle($idingreso_amp, $idusuario,
			$idarticulo_amp, $cantidad_amp, $precio_compra_amp, $precio_venta_amp,
			$numero_lote_amp, $fvenc_amp, $ffab_amp
		);
		echo json_encode($rspta_amp);
		break;

	case 'siguienteCorrelativo':
		$tipo = isset($_GET["tipo_comprobante"]) ? limpiarCadena($_GET["tipo_comprobante"]) : "Boleta";
		$serie = isset($_GET["serie_comprobante"]) ? limpiarCadena($_GET["serie_comprobante"]) : "";
		$rspta = $ingreso->obtenerSiguienteCorrelativo($tipo, $serie);
		echo json_encode($rspta);
		break;
	

	case 'anular':
		$rspta=$ingreso->anular($idingreso);
		echo $rspta ? "Ingreso anulado correctamente" : "No se pudo anular el ingreso";
		break;
	
	case 'mostrar':
		$rspta=$ingreso->mostrar($idingreso);
		echo json_encode($rspta);
		break;

	case 'listarDetalle':
		$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
		$rspta = $ingreso->listarDetalle($id);
		$total = 0;
		echo '<thead style="background-color:#A9D0F5">
        <th></th>
        <th>Articulo</th>
        <th>Unidad</th>
        <th>Cantidad</th>
        <th>Precio Compra</th>
        <th>Precio Venta</th>
        <th>N&deg; Lote</th>
        <th>Vencimiento</th>
        <th>Subtotal</th>
        <th>Guardar</th>
       </thead>';
		while ($reg = $rspta->fetch_object()) {
			$subtotal  = (float)$reg->precio_compra * (float)$reg->cantidad;
			$total    += $subtotal;
			$iddet     = (int)$reg->iddetalle_ingreso;
			$loteVal   = htmlspecialchars($reg->numero_lote ?? '');
			$vencVal   = htmlspecialchars($reg->fecha_vencimiento ?? '');
			$cantVal   = number_format((float)$reg->cantidad, 0, '.', '');
			$pcomVal   = number_format((float)$reg->precio_compra, 2, '.', '');
			$pvenVal   = number_format((float)$reg->precio_venta,  2, '.', '');
			echo '<tr class="filas" data-iddetalle="'.$iddet.'">
			<td></td>
			<td>'.htmlspecialchars($reg->nombre).'</td>
			<td>'.htmlspecialchars($reg->unidad).'</td>
			<td><input type="number" step="1" min="1" name="det_cantidad" value="'.$cantVal.'" style="width:70px" oninput="recalcFilaDetalle(this)" class="form-control input-sm"></td>
			<td><input type="number" step="0.01" min="0" name="det_precio_compra" value="'.$pcomVal.'" style="width:90px" oninput="recalcFilaDetalle(this)" class="form-control input-sm"></td>
			<td><input type="number" step="0.01" min="0" name="det_precio_venta" value="'.$pvenVal.'" style="width:90px" class="form-control input-sm"></td>
			<td><input type="text" name="det_numero_lote" value="'.$loteVal.'" maxlength="50" style="width:90px" placeholder="N° Lote" class="form-control input-sm"></td>
			<td><input type="date" name="det_fecha_vencimiento" value="'.$vencVal.'" style="width:130px" class="form-control input-sm"></td>
			<td><span class="det-subtotal">'.number_format($subtotal, 2).'</span></td>
			<td><button type="button" class="btn btn-success btn-xs" onclick="guardarFilaDetalle('.$iddet.')"><i class="fa fa-save"></i> Guardar</button></td>
			</tr>';
		}
		echo '<tfoot>
         <th>TOTAL</th>
         <th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
         <th><h4 id="det-total-view">'.formatearMoneda($total).'</h4></th>
       </tfoot>';
		break;

	case 'actualizarDetalle':
		$iddetalle_upd      = isset($_POST['iddetalle'])        ? (int)$_POST['iddetalle']                : 0;
		$cantidad_upd       = isset($_POST['cantidad'])         ? $_POST['cantidad']                      : 0;
		$precio_compra_upd  = isset($_POST['precio_compra'])    ? (float)$_POST['precio_compra']          : 0;
		$precio_venta_upd   = isset($_POST['precio_venta'])     ? (float)$_POST['precio_venta']           : 0;
		$numero_lote_upd    = isset($_POST['numero_lote'])      ? limpiarCadena($_POST['numero_lote'])    : '';
		$fecha_venc_upd     = isset($_POST['fecha_vencimiento'])? limpiarCadena($_POST['fecha_vencimiento']): '';
		if ($fecha_venc_upd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_venc_upd)) {
			echo json_encode(array("ok"=>false, "message"=>"La fecha de vencimiento es obligatoria y debe tener formato válido."));
			break;
		}
		$fechaObj = DateTime::createFromFormat('Y-m-d', $fecha_venc_upd);
		if (!$fechaObj || $fechaObj < new DateTime('today')) {
			echo json_encode(array("ok"=>false, "message"=>"La fecha de vencimiento no puede ser una fecha pasada."));
			break;
		}
		$rspta_upd = $ingreso->actualizarDetalle($iddetalle_upd, $cantidad_upd, $precio_compra_upd, $precio_venta_upd, $numero_lote_upd, $fecha_venc_upd);
		echo json_encode($rspta_upd);
		break;

    case 'listar':
		$fecha_inicio = fechaFiltroSeguroIngreso(isset($_GET["fecha_inicio"]) ? $_GET["fecha_inicio"] : '');
		$fecha_fin = fechaFiltroSeguroIngreso(isset($_GET["fecha_fin"]) ? $_GET["fecha_fin"] : '');
		$rspta=$ingreso->listarPorFecha($fecha_inicio, $fecha_fin);
		$data=Array();

		while ($reg=$rspta->fetch_object()) {
			$url='../reportes/exIngreso.php?id=';
			$data[]=array(
            "0"=>(($reg->estado=='Aceptado')
				?'<button class="btn btn-warning btn-xs" onclick="mostrar('.$reg->idingreso.')" title="Ver detalle"><i class="fa fa-eye"></i></button> '
				 .'<button class="btn btn-success btn-xs" onclick="abrirAmpliarIngreso('.$reg->idingreso.')" title="Agregar artículos a esta compra"><i class="fa fa-plus"></i></button> '
				 .'<button class="btn btn-danger btn-xs" onclick="anular('.$reg->idingreso.')"><i class="fa fa-close"></i></button>'
				:'<button class="btn btn-warning btn-xs" onclick="mostrar('.$reg->idingreso.')" title="Ver detalle"><i class="fa fa-eye"></i></button>')
				.'<a target="_blank" href="'.$url.$reg->idingreso.'"> <button class="btn btn-info btn-xs"><i class="fa fa-file"></i></button></a>',
            "1"=>$reg->fecha,
            "2"=>$reg->proveedor,
            "3"=>$reg->usuario,
            "4"=>$reg->tipo_comprobante,
            "5"=>$reg->serie_comprobante. '-' .$reg->num_comprobante,
            "6"=>formatearMoneda((float)$reg->total_compra),
            "7"=>($reg->estado=='Aceptado')?'<span class="label bg-green">Aceptado</span>':'<span class="label bg-red">Anulado</span>'
              );
		}
		$results=array(
             "sEcho"=>1,//info para datatables
             "iTotalRecords"=>count($data),//enviamos el total de registros al datatable
             "iTotalDisplayRecords"=>count($data),//enviamos el total de registros a visualizar
             "aaData"=>$data); 
		echo json_encode($results);
		break;

		case 'selectProveedor':
			require_once "../modelos/Persona.php";
			$persona = new Persona();

			$rspta = $persona->listarp();

			while ($reg = $rspta->fetch_object()) {
				echo '<option value='.$reg->idpersona.'>'.$reg->nombre.'</option>';
			}
			break;

		case 'crearProveedorRapido':
			require_once "../modelos/Persona.php";
			$persona = new Persona();

			$nombreProveedor = isset($_POST['nombre']) ? trim(limpiarCadena($_POST['nombre'])) : '';
			$tipoDocumento = isset($_POST['tipo_documento']) ? trim(limpiarCadena($_POST['tipo_documento'])) : 'DNI';
			$numDocumento = isset($_POST['num_documento']) ? trim(limpiarCadena($_POST['num_documento'])) : '';
			$direccionProveedor = isset($_POST['direccion']) ? trim(limpiarCadena($_POST['direccion'])) : '';
			$telefonoProveedor = isset($_POST['telefono']) ? trim(limpiarCadena($_POST['telefono'])) : '';
			$emailProveedor = isset($_POST['email']) ? trim(limpiarCadena($_POST['email'])) : '';

			if ($nombreProveedor === '') {
				echo json_encode(array("ok"=>false, "message"=>"El nombre del proveedor es obligatorio"));
				break;
			}

			$tiposDocumentoPermitidos = array("DNI", "RUC", "CEDULA");
			if (!in_array($tipoDocumento, $tiposDocumentoPermitidos, true)) {
				$tipoDocumento = "DNI";
			}

			$idProveedorNuevo = $persona->insertarRetornarId("Proveedor", $nombreProveedor, $tipoDocumento, $numDocumento, $direccionProveedor, $telefonoProveedor, $emailProveedor);
			if (!$idProveedorNuevo) {
				echo json_encode(array("ok"=>false, "message"=>"No se pudo registrar el proveedor"));
				break;
			}

			echo json_encode(array(
				"ok"=>true,
				"message"=>"Proveedor registrado correctamente",
				"idproveedor"=>(int)$idProveedorNuevo,
				"nombre"=>$nombreProveedor
			));
			break;

			case 'listarArticulos':
			require_once "../modelos/Articulo.php";
			$articulo=new Articulo();

				$rspta=$articulo->listarActivos();
		$data=Array();

		while ($reg=$rspta->fetch_object()) {
			$nombrejs = addslashes($reg->nombre);
			$unidadjs = addslashes($reg->abreviatura);
			$stock = (float)$reg->stock;
			$stockFmt = number_format($stock,0);
			$precioCompraRef = is_null($reg->precio_compra_ref) ? 0 : (float)$reg->precio_compra_ref;
			$btnAgregar = '<button class="btn btn-add-item" type="button" onclick="agregarDetalle('.$reg->idarticulo.',\''.$nombrejs.'\',\''.$unidadjs.'\','.$precioCompraRef.')"><i class="fa fa-plus-circle"></i> Agregar</button>';

			if ($stock<=0) {
				$stockHtml='<span class="stock-pill stock-empty">'.$stockFmt.'</span>';
			} elseif ($stock<=5) {
				$stockHtml='<span class="stock-pill stock-low">'.$stockFmt.'</span>';
			} else {
				$stockHtml='<span class="stock-pill stock-ok">'.$stockFmt.'</span>';
			}

			$imagen = !empty($reg->imagen) ? $reg->imagen : 'default-50x50.gif';
			$imgHtml = "<img class='catalog-thumb' src='../files/articulos/".$imagen."' onerror=\"this.src='../public/img/default-50x50.gif';\" alt='articulo'>";
			$data[]=array(
            "0"=>$btnAgregar,
            "1"=>$reg->nombre,
            "2"=>$reg->categoria,
            "3"=>$reg->abreviatura,
            "4"=>$reg->codigo,
            "5"=>$stockHtml,
            "6"=>formatearMoneda((float)$precioCompraRef),
            "7"=>$imgHtml
          
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
