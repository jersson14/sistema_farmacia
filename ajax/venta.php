<?php 
require_once "../modelos/Venta.php";
if (strlen(session_id())<1) 
	session_start();

$venta = new Venta();

$idventa=isset($_POST["idventa"])? limpiarCadena($_POST["idventa"]):"";
$idcliente=isset($_POST["idcliente"])? limpiarCadena($_POST["idcliente"]):"";
$idusuario=$_SESSION["idusuario"];
$tipo_comprobante=isset($_POST["tipo_comprobante"])? limpiarCadena($_POST["tipo_comprobante"]):"";
$serie_comprobante=isset($_POST["serie_comprobante"])? limpiarCadena($_POST["serie_comprobante"]):"";
$num_comprobante=isset($_POST["num_comprobante"])? limpiarCadena($_POST["num_comprobante"]):"";
$fecha_hora=isset($_POST["fecha_hora"])? limpiarCadena($_POST["fecha_hora"]):"";
$impuesto=isset($_POST["impuesto"])? limpiarCadena($_POST["impuesto"]):"";
$total_venta=isset($_POST["total_venta"])? limpiarCadena($_POST["total_venta"]):"";

if (!function_exists('fechaFiltroSeguro')) {
	function fechaFiltroSeguro($valor) {
		$valor = trim((string)$valor);
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
			return $valor;
		}
		return '';
	}
}





switch ($_GET["op"]) {
	case 'resumenPagos':
		$fecha_inicio = fechaFiltroSeguro(isset($_GET["fecha_inicio"]) ? $_GET["fecha_inicio"] : '');
		$fecha_fin    = fechaFiltroSeguro(isset($_GET["fecha_fin"])    ? $_GET["fecha_fin"]    : '');
		$whereResumen = array("estado='Aceptado'");
		if ($fecha_inicio !== '') {
			$whereResumen[] = "DATE(fecha_hora)>='$fecha_inicio'";
		}
		if ($fecha_fin !== '') {
			$whereResumen[] = "DATE(fecha_hora)<='$fecha_fin'";
		}
		$filtroResumen = implode(" AND ", $whereResumen);
		$sqlResumen = "SELECT
			IFNULL(SUM(IFNULL(monto_efectivo,0)),0) AS total_efectivo,
			IFNULL(SUM(IFNULL(monto_digital,0)),0)  AS total_yape,
			IFNULL(SUM(IFNULL(monto_tarjeta,0)),0)  AS total_tarjeta,
			IFNULL(SUM(total_venta),0)              AS total_general,
			COUNT(*)                                 AS cantidad
			FROM venta WHERE $filtroResumen";
		$rowResumen = ejecutarConsultaSimpleFila($sqlResumen);
		echo json_encode(array(
			'ok'   => true,
			'data' => array(
				'total_efectivo' => $rowResumen ? (float)$rowResumen['total_efectivo'] : 0,
				'total_yape'     => $rowResumen ? (float)$rowResumen['total_yape']     : 0,
				'total_tarjeta'  => $rowResumen ? (float)$rowResumen['total_tarjeta']  : 0,
				'total_general'  => $rowResumen ? (float)$rowResumen['total_general']  : 0,
				'cantidad'       => $rowResumen ? (int)$rowResumen['cantidad']          : 0,
			)
		));
		break;

	case 'guardaryeditar':
	if (empty($idventa)) {
		// Verificar que hay caja abierta antes de registrar la venta
		require_once "../modelos/Caja.php";
		$cajaMdl = new Caja();
		if (!$cajaMdl->cajaAbiertaUsuario($idusuario)) {
			echo json_encode(array('ok'=>false,'message'=>'Debes abrir la caja antes de registrar ventas. Ve al modulo de Caja.'));
			exit;
		}
		$metodo_pago   = isset($_POST['metodo_pago'])   ? limpiarCadena($_POST['metodo_pago'])   : 'EFECTIVO';
		$monto_efectivo = isset($_POST['monto_efectivo']) ? (float)$_POST['monto_efectivo']       : 0;
		$monto_tarjeta  = isset($_POST['monto_tarjeta'])  ? (float)$_POST['monto_tarjeta']        : 0;
		$monto_digital  = isset($_POST['monto_digital'])  ? (float)$_POST['monto_digital']        : 0;
		$seguro_nombre           = isset($_POST['seguro_nombre'])           ? limpiarCadena($_POST['seguro_nombre'])           : '';
		$seguro_copago           = isset($_POST['seguro_copago'])           ? (float)$_POST['seguro_copago']                   : 0;
		$seguro_nro_autorizacion = isset($_POST['seguro_nro_autorizacion']) ? limpiarCadena($_POST['seguro_nro_autorizacion']) : '';
		$rspta=$venta->insertar($idcliente,$idusuario,$tipo_comprobante,$serie_comprobante,$num_comprobante,$fecha_hora,$impuesto,$total_venta,$_POST["idarticulo"],$_POST["cantidad"],$_POST["precio_venta"],$_POST["descuento"],$metodo_pago,$monto_efectivo,$monto_tarjeta,$monto_digital,$seguro_nombre,$seguro_copago,$seguro_nro_autorizacion);
		if (is_array($rspta)) {
			if (!empty($rspta["ok"])) {
				echo json_encode(array(
					"ok"=>true,
					"message"=>"Datos registrados correctamente",
					"idventa"=>isset($rspta["idventa"])?(int)$rspta["idventa"]:0,
					"serie_comprobante"=>isset($rspta["serie_comprobante"])?$rspta["serie_comprobante"]:"",
					"num_comprobante"=>isset($rspta["num_comprobante"])?$rspta["num_comprobante"]:"",
					"alertas"=>isset($rspta["alertas"])?$rspta["alertas"]:array()
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
				"message"=>$rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos",
				"alertas"=>array()
			));
		}
	}else{
        
	}
		break;

	case 'siguienteCorrelativo':
		$tipo = isset($_GET["tipo_comprobante"]) ? limpiarCadena($_GET["tipo_comprobante"]) : "Boleta";
		$serie = isset($_GET["serie_comprobante"]) ? limpiarCadena($_GET["serie_comprobante"]) : "";
		$rspta = $venta->obtenerSiguienteCorrelativo($tipo, $serie);
		echo json_encode($rspta);
		break;
	

	case 'anular':
		if (!isset($_SESSION['acceso']) || $_SESSION['acceso'] != 1) {
			echo "Sin permiso: solo administradores pueden anular ventas";
			break;
		}
		$rspta=$venta->anular($idventa, $idusuario);
		echo $rspta ? "Venta anulada correctamente" : "No se pudo anular la venta";
		break;
	
	case 'mostrar':
		$rspta=$venta->mostrar($idventa);
		echo json_encode($rspta);
		break;

	case 'listarDetalleJSON':
		$id = (int)($_GET['id'] ?? 0);
		$rs = $venta->listarDetalleJSON($id);
		$items = [];
		while ($r = $rs->fetch_assoc()) {
			$items[] = [
				'idarticulo'  => (int)$r['idarticulo'],
				'nombre'      => $r['nombre'],
				'unidad'      => $r['unidad'],
				'cantidad'    => (float)$r['cantidad'],
				'precio_venta'=> (float)$r['precio_venta'],
				'descuento'   => (float)$r['descuento'],
				'subtotal'    => (float)$r['subtotal'],
				'stock'       => (float)$r['stock'],
			];
		}
		echo json_encode($items);
		break;

	case 'listarDetalle':
		//recibimos el idventa
		$id=$_GET['id'];

		$rspta=$venta->listarDetalle($id);
		$total=0;
		echo ' <thead style="background-color:#A9D0F5">
        <th>Opciones</th>
        <th>Articulo</th>
        <th>Unidad</th>
        <th>Cantidad</th>
        <th>Precio Venta</th>
        <th>Descuento</th>
        <th>Subtotal</th>
        <th>Actualizar</th>
       </thead>';
		while ($reg=$rspta->fetch_object()) {
			echo '<tr class="filas">
			<td></td>
			<td>'.$reg->nombre.'</td>
			<td>'.$reg->unidad.'</td>
			<td>'.number_format((float)$reg->cantidad,0).'</td>
			<td>'.number_format((float)$reg->precio_venta,2).'</td>
			<td>'.number_format((float)$reg->descuento,2).'</td>
			<td>'.number_format((float)$reg->subtotal,2).'</td>
			<td></td></tr>';
			$total=$total+($reg->precio_venta*$reg->cantidad-$reg->descuento);
		}
		echo '<tfoot>
         <th>TOTAL</th>
         <th></th>
         <th></th>
         <th></th>
         <th></th>
         <th></th>
         <th></th>
         <th><h4 id="total">'.formatearMoneda($total).'</h4><input type="hidden" name="total_venta" id="total_venta"></th>
       </tfoot>';
		break;

    case 'listar':
		$fecha_inicio = fechaFiltroSeguro(isset($_GET["fecha_inicio"]) ? $_GET["fecha_inicio"] : '');
		$fecha_fin = fechaFiltroSeguro(isset($_GET["fecha_fin"]) ? $_GET["fecha_fin"] : '');
		$rspta=$venta->listarPorFecha($fecha_inicio, $fecha_fin);
		$data=Array();

		while ($reg=$rspta->fetch_object()) {
                 // Boleta y Ticket → formato térmico 80mm; Factura → A4
                 if ($reg->tipo_comprobante === 'Factura') {
                 	$url='../reportes/exFactura.php?id=';
                 } else {
                    $url='../reportes/exTicket.php?id=';
                 }

            $metodo = strtoupper($reg->metodo_pago ?? 'EFECTIVO');
            $metodoBadgeColors = array(
                'EFECTIVO'      => 'bg-green',
                'YAPE'          => 'bg-purple',
                'PLIN'          => 'bg-purple',
                'TARJETA'       => 'bg-light-blue',
                'TRANSFERENCIA' => 'bg-aqua',
                'MIXTO'         => 'bg-orange',
            );
            $metodoBadge = '<span class="label '.($metodoBadgeColors[$metodo] ?? 'bg-gray').'">'.$metodo.'</span>';
			$data[]=array(
            "0"=>(($reg->estado=='Aceptado')?'<button class="btn btn-warning btn-xs" onclick="mostrar('.$reg->idventa.')"><i class="fa fa-eye"></i></button>'.' '.'<button class="btn btn-danger btn-xs" onclick="anular('.$reg->idventa.')"><i class="fa fa-close"></i></button>':'<button class="btn btn-warning btn-xs" onclick="mostrar('.$reg->idventa.')"><i class="fa fa-eye"></i></button>').
            '<a target="_blank" href="'.$url.$reg->idventa.'"> <button class="btn btn-info btn-xs"><i class="fa fa-file"></i></button></a>',
            "1"=>$reg->fecha,
            "2"=>$reg->cliente,
            "3"=>$reg->usuario,
            "4"=>$reg->tipo_comprobante,
            "5"=>$reg->serie_comprobante. '-' .$reg->num_comprobante,
            "6"=>formatearMoneda((float)$reg->total_venta),
            "7"=>$metodoBadge,
            "8"=>($reg->estado=='Aceptado')?'<span class="label bg-green">Aceptado</span>':'<span class="label bg-red">Anulado</span>'
              );
		}
		$results=array(
             "sEcho"=>1,//info para datatables
             "iTotalRecords"=>count($data),//enviamos el total de registros al datatable
             "iTotalDisplayRecords"=>count($data),//enviamos el total de registros a visualizar
             "aaData"=>$data); 
		echo json_encode($results);
		break;

		case 'selectCliente':
			require_once "../modelos/Persona.php";
			$persona = new Persona();
			echo '<option value="0">Consumidor Final</option>';
			$rsptaCli = ejecutarConsulta("SELECT idpersona, nombre FROM persona WHERE tipo_persona='Cliente' AND nombre != 'CONSUMIDOR FINAL' ORDER BY nombre ASC");
			if ($rsptaCli) {
				while ($reg = $rsptaCli->fetch_object()) {
					echo '<option value=' . (int)$reg->idpersona . '>' . htmlspecialchars($reg->nombre) . '</option>';
				}
			}
			break;

		case 'buscarClienteDni':
			require_once "../modelos/Persona.php";
			$persona = new Persona();
			$dni = isset($_GET['dni']) ? limpiarCadena(trim($_GET['dni'])) : '';
			if (strlen($dni) < 3) {
				echo json_encode(['ok'=>false, 'message'=>'Ingresa al menos 3 dígitos']);
				break;
			}
			$row = $persona->buscarPorDocumento($dni);
			if ($row) {
				echo json_encode(['ok'=>true, 'idpersona'=>(int)$row['idpersona'], 'nombre'=>$row['nombre']]);
			} else {
				echo json_encode(['ok'=>false, 'message'=>'No encontrado']);
			}
			break;

		case 'crearClienteRapido':
			require_once "../modelos/Persona.php";
			$persona = new Persona();

			$nombreCliente = isset($_POST['nombre']) ? trim(limpiarCadena($_POST['nombre'])) : '';
			$tipoDocumento = isset($_POST['tipo_documento']) ? trim(limpiarCadena($_POST['tipo_documento'])) : 'DNI';
			$numDocumento = isset($_POST['num_documento']) ? trim(limpiarCadena($_POST['num_documento'])) : '';
			$direccionCliente = isset($_POST['direccion']) ? trim(limpiarCadena($_POST['direccion'])) : '';
			$telefonoCliente = isset($_POST['telefono']) ? trim(limpiarCadena($_POST['telefono'])) : '';
			$emailCliente = isset($_POST['email']) ? trim(limpiarCadena($_POST['email'])) : '';

			if ($nombreCliente === '') {
				echo json_encode(array("ok"=>false, "message"=>"El nombre del cliente es obligatorio"));
				break;
			}

			$tiposDocumentoPermitidos = array("DNI", "RUC", "CEDULA");
			if (!in_array($tipoDocumento, $tiposDocumentoPermitidos, true)) {
				$tipoDocumento = "DNI";
			}

			$idClienteNuevo = $persona->insertarRetornarId("Cliente", $nombreCliente, $tipoDocumento, $numDocumento, $direccionCliente, $telefonoCliente, $emailCliente);
			if (!$idClienteNuevo) {
				echo json_encode(array("ok"=>false, "message"=>"No se pudo registrar el cliente"));
				break;
			}

			echo json_encode(array(
				"ok"=>true,
				"message"=>"Cliente registrado correctamente",
				"idcliente"=>(int)$idClienteNuevo,
				"nombre"=>$nombreCliente
			));
			break;

			case 'listarArticulos':
			require_once "../modelos/Articulo.php";
			$articulo=new Articulo();

				$rspta=$articulo->listarActivosVenta();
		$data=Array();

		while ($reg=$rspta->fetch_object()) {
			$nombrejs = addslashes($reg->nombre);
			$unidadjs = addslashes($reg->abreviatura);
			$precio = is_null($reg->precio_venta) ? 0 : (float)$reg->precio_venta;
			$stock = (float)$reg->stock;
			$stockVisible = $stock > 0 ? (int)round($stock) : 0;
			$stockFmt = number_format($stockVisible,0);
			$stockMinimo = isset($reg->stock_minimo) ? (int)round((float)$reg->stock_minimo) : 0;
			$umbralBajo = max($stockMinimo, 5);
			// Tipo de venta definido antes del botón para usarlo en el onclick
			$tipoVenta = !empty($reg->tipo_venta) ? $reg->tipo_venta : 'OTC';

			if ($stockVisible<=0) {
				$btnAgregar='<button class="btn btn-add-item btn-add-disabled" type="button" disabled title="Sin stock"><i class="fa fa-ban"></i> Sin stock</button>';
				$stockHtml='<span class="stock-pill stock-empty">'.$stockFmt.'</span>';
			} elseif ($stockVisible<=$umbralBajo) {
				$btnAgregar='<button class="btn btn-add-item" type="button" onclick="agregarDetalle('.$reg->idarticulo.',\''.$nombrejs.'\','.$precio.',\''.$unidadjs.'\','.$stockVisible.',\''.$tipoVenta.'\')"><i class="fa fa-plus-circle"></i> Agregar</button>';
				$stockHtml='<span class="stock-pill stock-low">'.$stockFmt.'</span>';
			} else {
				$btnAgregar='<button class="btn btn-add-item" type="button" onclick="agregarDetalle('.$reg->idarticulo.',\''.$nombrejs.'\','.$precio.',\''.$unidadjs.'\','.$stockVisible.',\''.$tipoVenta.'\')"><i class="fa fa-plus-circle"></i> Agregar</button>';
				$stockHtml='<span class="stock-pill stock-ok">'.$stockFmt.'</span>';
			}

			$imagen = !empty($reg->imagen) ? $reg->imagen : 'default-50x50.gif';
			$imgHtml = "<img class='catalog-thumb' src='../files/articulos/".$imagen."' onerror=\"this.src='../public/img/default-50x50.gif';\" alt='articulo'>";
			$badgeColor = $tipoVenta === 'OTC' ? 'bg-green' : ($tipoVenta === 'RX' ? 'bg-orange' : 'bg-red');
			$badgeLabel = $tipoVenta === 'CONTROL_ESPECIAL' ? 'CTRL' : $tipoVenta;
			$nombreConBadge = '<span class="label '.$badgeColor.'" style="font-size:10px">'.$badgeLabel.'</span> '.$reg->nombre;
			if (!empty($reg->principio_activo)) {
				$nombreConBadge .= '<br><small class="text-muted">'.$reg->principio_activo.(!empty($reg->concentracion) ? ' '.$reg->concentracion : '').'</small>';
			}
			$data[]=array(
            "0"=>$btnAgregar,
            "1"=>$nombreConBadge,
            "2"=>$reg->categoria,
            "3"=>$reg->abreviatura,
            "4"=>$reg->codigo,
            "5"=>$stockHtml,
            "6"=>formatearMoneda($precio),
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

		case 'listarArticulosGrid':
			require_once "../modelos/Articulo.php";
			$art = new Articulo();
			$resultado = $art->listarActivosVenta();
			$items = array();
			while ($reg = $resultado->fetch_object()) {
				$precio = is_null($reg->precio_venta) ? 0 : (float)$reg->precio_venta;
				$stock  = (int)round((float)$reg->stock);
				$items[] = array(
					'idarticulo'     => (int)$reg->idarticulo,
					'idcategoria'    => (int)$reg->idcategoria,
					'nombre'         => $reg->nombre,
					'principio_activo' => $reg->principio_activo,
					'concentracion'  => $reg->concentracion,
					'categoria'      => $reg->categoria,
					'abreviatura'    => $reg->abreviatura,
					'codigo'         => $reg->codigo,
					'stock'          => $stock,
					'stock_minimo'   => (int)round((float)($reg->stock_minimo ?? 0)),
					'precio_venta'   => $precio,
					'tipo_venta'     => !empty($reg->tipo_venta) ? $reg->tipo_venta : 'OTC',
					'imagen'         => !empty($reg->imagen) ? $reg->imagen : ''
				);
			}
			echo json_encode(array('ok' => true, 'data' => $items));
			break;

		case 'listarCategoriasVenta':
			require_once "../modelos/Categoria.php";
			$cat = new Categoria();
			$resultado = $cat->select();
			$cats = array();
			while ($reg = $resultado->fetch_object()) {
				$cats[] = array('idcategoria' => (int)$reg->idcategoria, 'nombre' => $reg->nombre);
			}
			echo json_encode(array('ok' => true, 'data' => $cats));
			break;

		case 'buscarArticuloCodigo':
			$codigo = isset($_POST['codigo']) ? limpiarCadena($_POST['codigo']) : '';
			require_once "../modelos/Articulo.php";
			$articulo=new Articulo();
			$reg = $articulo->buscarActivoPorCodigo($codigo);
			if (!$reg) {
				echo json_encode(array("ok"=>false,"message"=>"No se encontro un articulo con ese codigo"));
				break;
			}
			if ((float)$reg['stock'] <= 0) {
				echo json_encode(array("ok"=>false,"message"=>"El articulo no tiene stock disponible"));
				break;
			}
			echo json_encode(array(
				"ok"=>true,
				"idarticulo"=>$reg['idarticulo'],
				"nombre"=>$reg['nombre'],
				"precio_venta"=>(float)$reg['precio_venta'],
				"unidad"=>$reg['abreviatura'],
				"stock"=>(int)round((float)$reg['stock']),
				"tipo_venta"=>(!empty($reg['tipo_venta']) ? $reg['tipo_venta'] : 'OTC')
			));
			break;
}
 ?>
