<?php 
require_once "global.php";

$conexion=new mysqli(DB_HOST,DB_USERNAME,DB_PASSWORD,DB_NAME);

mysqli_query($conexion, 'SET NAMES "'.DB_ENCODE.'"');

//muestra posible error en la conexion
if (mysqli_connect_errno()) {
	printf("Falló en la conexion con la base de datos: %s\n",mysqli_connect_error());
	exit();
}

// Zona horaria Lima, Perú (UTC-5) — PHP y MySQL sincronizados
date_default_timezone_set('America/Lima');
mysqli_query($conexion, "SET time_zone = '-05:00'"  );

if (!function_exists('ejecutarConsulta')) {
	function ejecutarConsulta($sql){
		global $conexion;
		try {
			$query = $conexion->query($sql);
			return $query;
		} catch (mysqli_sql_exception $e) {
			return false;
		}
	}

	function ejecutarConsultaSimpleFila($sql){
global $conexion;
$query=$conexion->query($sql);
if (!$query) return null;
$row=$query->fetch_assoc();
return $row;
	}
function ejecutarConsulta_retornarID($sql){
global $conexion;
$query=$conexion->query($sql);
return $conexion->insert_id;
}

function limpiarCadena($str){
global $conexion;
$str=mysqli_real_escape_string($conexion,trim($str));
return htmlspecialchars($str);
}

function obtenerMonedaEmpresaCodigo(){
global $conexion;
	static $cachedMoneda = null;
	if ($cachedMoneda !== null) {
		return $cachedMoneda;
	}

	$cachedMoneda = 'PEN';
	$tabla = $conexion->query("SHOW TABLES LIKE 'configuracion_empresa'");
	if ($tabla && $tabla->num_rows > 0) {
		$rs = $conexion->query("SELECT moneda FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1");
		if ($rs && ($row = $rs->fetch_assoc())) {
			$moneda = strtoupper(trim((string)$row['moneda']));
			if ($moneda !== '') {
				$cachedMoneda = $moneda;
			}
		}
	}

	return $cachedMoneda;
}

function obtenerSimboloMoneda($codigo = null){
	if ($codigo === null || trim((string)$codigo) === '') {
		$codigo = obtenerMonedaEmpresaCodigo();
	}
	$codigo = strtoupper(trim((string)$codigo));

	$map = array(
		'PEN' => 'S/',
		'USD' => '$',
		'EUR' => 'EUR',
		'MXN' => 'MX$',
		'COP' => 'COP$',
		'CLP' => 'CLP$',
		'ARS' => 'AR$',
		'BOB' => 'Bs'
	);

	return isset($map[$codigo]) ? $map[$codigo] : $codigo;
}

function obtenerNombreMonedaLetras($codigo = null){
	if ($codigo === null || trim((string)$codigo) === '') {
		$codigo = obtenerMonedaEmpresaCodigo();
	}
	$codigo = strtoupper(trim((string)$codigo));

	$map = array(
		'PEN' => 'SOLES',
		'USD' => 'DOLARES',
		'EUR' => 'EUROS',
		'MXN' => 'PESOS MEXICANOS',
		'COP' => 'PESOS COLOMBIANOS',
		'CLP' => 'PESOS CHILENOS',
		'ARS' => 'PESOS ARGENTINOS',
		'BOB' => 'BOLIVIANOS'
	);

	return isset($map[$codigo]) ? $map[$codigo] : 'MONEDA';
}

function formatearMoneda($monto, $codigo = null, $decimales = 2){
	$simbolo = obtenerSimboloMoneda($codigo);
	return $simbolo . ' ' . number_format((float)$monto, (int)$decimales, '.', ',');
}

// Precio base de un articulo: precio_venta propio, o el ultimo precio_venta de compra si no tiene
function sqlPrecioBaseExpr($alias = 'a'){
	return "COALESCE(NULLIF($alias.precio_venta,0),(SELECT di.precio_venta FROM detalle_ingreso di WHERE di.idarticulo=$alias.idarticulo ORDER BY di.iddetalle_ingreso DESC LIMIT 1),0)";
}

// Condicion booleana SQL: la oferta del articulo esta vigente ahora mismo
function sqlOfertaVigenteExpr($alias = 'a'){
	return "($alias.en_oferta=1 AND $alias.descuento_porcentaje>0 AND ($alias.oferta_fecha_inicio IS NULL OR NOW()>=$alias.oferta_fecha_inicio) AND ($alias.oferta_fecha_fin IS NULL OR NOW()<=$alias.oferta_fecha_fin))";
}

// Precio final a cobrar: con descuento si la oferta esta vigente, si no el precio base
function sqlPrecioFinalExpr($alias = 'a'){
	$base    = sqlPrecioBaseExpr($alias);
	$vigente = sqlOfertaVigenteExpr($alias);
	return "IF($vigente, ROUND($base * (1 - $alias.descuento_porcentaje/100), 2), $base)";
}

}

 ?>
