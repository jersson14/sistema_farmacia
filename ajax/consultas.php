<?php
if (strlen(session_id()) < 1) session_start();
if (!isset($_SESSION['idusuario'])) {
    echo json_encode(['ok' => false, 'message' => 'Sesión no válida']);
    exit;
}
require_once "../modelos/Consultas.php";

$consulta = new Consultas();

if (!function_exists('fechaSeguro')) {
    function fechaSeguro($valor, $fallback) {
        $valor = trim((string)$valor);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return $valor;
        }
        return $fallback;
    }
}

switch ($_GET["op"]) {
	

    case 'comprasfecha':
    if (!isset($_SESSION['consultac']) || $_SESSION['consultac'] != 1) {
        echo json_encode(['sEcho'=>1,'iTotalRecords'=>0,'iTotalDisplayRecords'=>0,'aaData'=>[]]);
        break;
    }
    $fecha_inicio=$_REQUEST["fecha_inicio"];
    $fecha_fin=$_REQUEST["fecha_fin"];

		$rspta=$consulta->comprasfecha($fecha_inicio,$fecha_fin);
		$data=Array();

		while ($reg=$rspta->fetch_object()) {
			$data[]=array(
            "0"=>$reg->fecha,
            "1"=>$reg->usuario,
            "2"=>$reg->proveedor,
            "3"=>$reg->tipo_comprobante,
            "4"=>$reg->serie_comprobante.' '.$reg->num_comprobante,
            "5"=>$reg->total_compra,
            "6"=>$reg->impuesto,
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

    case 'ventasfechacliente':
    if (!isset($_SESSION['consultav']) || $_SESSION['consultav'] != 1) {
        echo json_encode(['sEcho'=>1,'iTotalRecords'=>0,'iTotalDisplayRecords'=>0,'aaData'=>[]]);
        break;
    }
    $fecha_inicio = fechaSeguro(isset($_REQUEST["fecha_inicio"]) ? $_REQUEST["fecha_inicio"] : '', date("Y-m-01"));
    $fecha_fin    = fechaSeguro(isset($_REQUEST["fecha_fin"])    ? $_REQUEST["fecha_fin"]    : '', date("Y-m-d"));
    $idcliente    = (int)(isset($_REQUEST["idcliente"]) ? $_REQUEST["idcliente"] : 0);

        $rspta=$consulta->ventasfechacliente($fecha_inicio,$fecha_fin,$idcliente);
        $data=Array();

        while ($reg=$rspta->fetch_object()) {
            $data[]=array(
            "0"=>$reg->fecha,
            "1"=>$reg->usuario,
            "2"=>$reg->cliente,
            "3"=>$reg->tipo_comprobante,
            "4"=>$reg->serie_comprobante.' '.$reg->num_comprobante,
            "5"=>$reg->total_venta,
            "6"=>$reg->impuesto,
            "7"=>($reg->estado=='Aceptado')?'<span class="label bg-green">Aceptado</span>':'<span class="label bg-red">Anulado</span>'
              );
        }
        $results=array(
             "sEcho"=>1,
             "iTotalRecords"=>count($data),
             "iTotalDisplayRecords"=>count($data),
             "aaData"=>$data);
        echo json_encode($results);
        break;

    case 'clientesselect':
    if (!isset($_SESSION['consultav']) || $_SESSION['consultav'] != 1) {
        echo json_encode(['ok'=>false,'clientes'=>[]]);
        break;
    }
        $rspta = $consulta->listarClientesSelect();
        $clientes = array();
        if ($rspta) {
            while ($reg = $rspta->fetch_object()) {
                $clientes[] = array('idpersona'=>(int)$reg->idpersona, 'nombre'=>$reg->nombre);
            }
        }
        echo json_encode(['ok'=>true,'clientes'=>$clientes]);
        break;

    case 'utilidadperiodo':
    if ((!isset($_SESSION['consultav']) || $_SESSION['consultav'] != 1) && (!isset($_SESSION['acceso']) || $_SESSION['acceso'] != 1)) {
        echo json_encode(['sEcho'=>1,'iTotalRecords'=>0,'iTotalDisplayRecords'=>0,'aaData'=>[]]);
        break;
    }
        $fecha_inicio = fechaSeguro(isset($_REQUEST["fecha_inicio"]) ? $_REQUEST["fecha_inicio"] : '', date("Y-m-01"));
        $fecha_fin = fechaSeguro(isset($_REQUEST["fecha_fin"]) ? $_REQUEST["fecha_fin"] : '', date("Y-m-d"));

        $rspta = $consulta->utilidadPorPeriodo($fecha_inicio, $fecha_fin);
        $data = array();

        while ($reg = $rspta->fetch_object()) {
            $utilidad = (float)$reg->venta_total - (float)$reg->costo_estimado;
            $margen = ((float)$reg->venta_total > 0) ? ($utilidad / (float)$reg->venta_total) * 100 : 0;
            $data[] = array(
                "0" => $reg->codigo,
                "1" => $reg->articulo,
                "2" => $reg->categoria,
                "3" => number_format((float)$reg->cantidad_vendida, 0),
                "4" => number_format((float)$reg->venta_total, 2),
                "5" => number_format((float)$reg->costo_estimado, 2),
                "6" => number_format($utilidad, 2),
                "7" => number_format($margen, 2) . " %"
            );
        }

        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        );
        echo json_encode($results);
        break;

    case 'topproductos':
    if ((!isset($_SESSION['consultav']) || $_SESSION['consultav'] != 1) && (!isset($_SESSION['acceso']) || $_SESSION['acceso'] != 1)) {
        echo json_encode(['sEcho'=>1,'iTotalRecords'=>0,'iTotalDisplayRecords'=>0,'aaData'=>[]]);
        break;
    }
        $fecha_inicio = fechaSeguro(isset($_REQUEST["fecha_inicio"]) ? $_REQUEST["fecha_inicio"] : '', date("Y-m-01"));
        $fecha_fin = fechaSeguro(isset($_REQUEST["fecha_fin"]) ? $_REQUEST["fecha_fin"] : '', date("Y-m-d"));
        $limite = isset($_REQUEST["limite"]) ? (int)$_REQUEST["limite"] : 20;
        $modo = isset($_REQUEST["modo"]) ? strtoupper(trim($_REQUEST["modo"])) : 'MAS';
        if (!in_array($modo, array('MAS', 'MENOS'), true)) {
            $modo = 'MAS';
        }
        if ($limite <= 0) $limite = 20;

        $rspta = $consulta->topProductosPeriodo($fecha_inicio, $fecha_fin, $limite, $modo);
        $data = array();
        $rank = 1;

        while ($reg = $rspta->fetch_object()) {
            $data[] = array(
                "0" => $rank++,
                "1" => $reg->codigo,
                "2" => $reg->articulo,
                "3" => $reg->categoria,
                "4" => number_format((float)$reg->cantidad, 0) . " " . $reg->unidad,
                "5" => number_format((float)$reg->total, 2)
            );
        }

        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        );
        echo json_encode($results);
        break;

    case 'stockcritico':
    if ((!isset($_SESSION['kardex']) || $_SESSION['kardex'] != 1) && (!isset($_SESSION['almacen']) || $_SESSION['almacen'] != 1) && (!isset($_SESSION['acceso']) || $_SESSION['acceso'] != 1)) {
        echo json_encode(['sEcho'=>1,'iTotalRecords'=>0,'iTotalDisplayRecords'=>0,'aaData'=>[]]);
        break;
    }
        $rspta = $consulta->stockCritico();
        $data = array();

        while ($reg = $rspta->fetch_object()) {
            $badge = '<span class="label bg-green">OK</span>';
            if ($reg->alerta === 'AGOTADO') {
                $badge = '<span class="label bg-red">AGOTADO</span>';
            } elseif ($reg->alerta === 'BAJO MINIMO') {
                $badge = '<span class="label bg-yellow">BAJO MINIMO</span>';
            } elseif ($reg->alerta === 'PROXIMO A AGOTARSE') {
                $badge = '<span class="label bg-orange">PROXIMO A AGOTARSE</span>';
            } elseif ($reg->alerta === 'SIN MOVIMIENTO') {
                $badge = '<span class="label bg-aqua">SIN MOVIMIENTO</span>';
            }

            $data[] = array(
                "0" => $reg->codigo,
                "1" => $reg->articulo,
                "2" => $reg->categoria,
                "3" => number_format((float)$reg->stock, 0) . " " . $reg->unidad,
                "4" => number_format((float)$reg->stock_minimo, 0) . " " . $reg->unidad,
                "5" => $badge,
                "6" => $reg->ultimo_mov,
                "7" => (int)$reg->dias_sin_mov
            );
        }

        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        );
        echo json_encode($results);
        break;

    case 'kardexvalorizado':
    if ((!isset($_SESSION['kardex']) || $_SESSION['kardex'] != 1) && (!isset($_SESSION['acceso']) || $_SESSION['acceso'] != 1)) {
        echo json_encode(['sEcho'=>1,'iTotalRecords'=>0,'iTotalDisplayRecords'=>0,'aaData'=>[]]);
        break;
    }
        $fecha_inicio = fechaSeguro(isset($_REQUEST["fecha_inicio"]) ? $_REQUEST["fecha_inicio"] : '', date("Y-m-01"));
        $fecha_fin = fechaSeguro(isset($_REQUEST["fecha_fin"]) ? $_REQUEST["fecha_fin"] : '', date("Y-m-d"));

        $rspta = $consulta->kardexValorizado($fecha_inicio, $fecha_fin);
        $data = array();

        while ($reg = $rspta->fetch_object()) {
            $data[] = array(
                "0" => $reg->codigo,
                "1" => $reg->articulo,
                "2" => number_format((float)$reg->entrada, 0) . " " . $reg->unidad,
                "3" => number_format((float)$reg->salida, 0) . " " . $reg->unidad,
                "4" => number_format((float)$reg->saldo, 0) . " " . $reg->unidad,
                "5" => number_format((float)$reg->costo_promedio, 2),
                "6" => number_format((float)$reg->valor_stock, 2)
            );
        }

        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        );
        echo json_encode($results);
        break;

    case 'clientesproveedores':
    if ((!isset($_SESSION['consultav']) || $_SESSION['consultav'] != 1) && (!isset($_SESSION['acceso']) || $_SESSION['acceso'] != 1)) {
        echo json_encode(['sEcho'=>1,'iTotalRecords'=>0,'iTotalDisplayRecords'=>0,'aaData'=>[]]);
        break;
    }
        $fecha_inicio = fechaSeguro(isset($_REQUEST["fecha_inicio"]) ? $_REQUEST["fecha_inicio"] : '', date("Y-m-01"));
        $fecha_fin = fechaSeguro(isset($_REQUEST["fecha_fin"]) ? $_REQUEST["fecha_fin"] : '', date("Y-m-d"));
        $tipo = isset($_REQUEST["tipo"]) ? strtoupper(trim($_REQUEST["tipo"])) : 'TODOS';
        if (!in_array($tipo, array('TODOS', 'CLIENTE', 'PROVEEDOR'), true)) {
            $tipo = 'TODOS';
        }

        $rspta = $consulta->clientesProveedoresPeriodo($fecha_inicio, $fecha_fin, $tipo);
        $data = array();

        while ($reg = $rspta->fetch_object()) {
            $tipoBadge = ($reg->tipo === 'CLIENTE')
                ? '<span class="label bg-green">CLIENTE</span>'
                : '<span class="label bg-aqua">PROVEEDOR</span>';

            $data[] = array(
                "0" => $tipoBadge,
                "1" => $reg->persona,
                "2" => $reg->documento,
                "3" => $reg->telefono,
                "4" => (int)$reg->operaciones,
                "5" => number_format((float)$reg->total, 2),
                "6" => $reg->ultimo_mov
            );
        }

        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        );
        echo json_encode($results);
        break;
}
 ?>
