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

// Elimina un registro. Si la BD lo impide porque tiene historial (llave foranea, errno 1451)
// y la tabla tiene campo condicion, se marca condicion=2 (eliminado): desaparece de todo el
// sistema pero ventas/compras/reportes antiguos siguen cuadrando.
// $opc['previos']   DELETEs de tablas hijas desechables, en la misma transaccion.
// $opc['liberar']   campos UNIQUE (nombre, login) que se renombran al archivar para poder reutilizarlos.
// $opc['archivable'] false si la tabla no tiene campo condicion.
function eliminarRegistro($tabla, $campoId, $id, $etiqueta = 'El registro', $opc = array()){
	global $conexion;
	$previos    = isset($opc['previos']) ? $opc['previos'] : array();
	$liberar    = isset($opc['liberar']) ? $opc['liberar'] : array();
	$archivable = isset($opc['archivable']) ? (bool)$opc['archivable'] : true;
	$id = (int)$id;
	if ($id <= 0) {
		return array("ok"=>false, "message"=>"No se encontró el registro a eliminar");
	}
	$existe = ejecutarConsultaSimpleFila("SELECT `$campoId` FROM `$tabla` WHERE `$campoId`='$id' LIMIT 1");
	if (!$existe) {
		return array("ok"=>false, "message"=>"$etiqueta ya no existe");
	}

	$conexion->begin_transaction();
	$errno = 0;
	try {
		foreach ($previos as $sqlPrevio) {
			if (!$conexion->query($sqlPrevio)) { $errno = $conexion->errno; break; }
		}
		if ($errno === 0 && !$conexion->query("DELETE FROM `$tabla` WHERE `$campoId`='$id'")) {
			$errno = $conexion->errno;
		}
	} catch (mysqli_sql_exception $e) {
		$errno = (int)$e->getCode();
	}

	if ($errno === 0) {
		$conexion->commit();
		return array("ok"=>true, "message"=>"$etiqueta se eliminó correctamente", "modo"=>"fisico");
	}
	$conexion->rollback();

	if ($errno == 1451 && $archivable) {
		$sets = array("condicion='2'");
		foreach ($liberar as $campo) {
			$col = ejecutarConsultaSimpleFila("SELECT CHARACTER_MAXIMUM_LENGTH AS len FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tabla' AND COLUMN_NAME='$campo'");
			$len = ($col && (int)$col['len'] > 0) ? (int)$col['len'] : 20;
			$suf = " [elim #$id]";
			$sets[] = "`$campo`=CONCAT(LEFT(`$campo`, ".max(1, $len - strlen($suf))."), '$suf')";
		}
		if (ejecutarConsulta("UPDATE `$tabla` SET ".implode(',', $sets)." WHERE `$campoId`='$id'")) {
			return array("ok"=>true, "message"=>"$etiqueta se eliminó. Como tiene movimientos registrados, su historial se conserva en los reportes.", "modo"=>"archivado");
		}
	}
	if ($errno == 1451) {
		return array("ok"=>false, "message"=>"$etiqueta no se puede eliminar porque tiene movimientos registrados");
	}
	return array("ok"=>false, "message"=>"No se pudo eliminar. Inténtalo nuevamente.");
}

// Filtro SQL para ocultar personas eliminadas. Devuelve "" si la migracion
// 20260925_persona_condicion.sql aun no se aplico (la columna no existe).
function sqlPersonaVisible($alias = ''){
	global $conexion;
	static $tieneCondicion = null;
	if ($tieneCondicion === null) {
		$rs = $conexion->query("SHOW COLUMNS FROM persona LIKE 'condicion'");
		$tieneCondicion = $rs && $rs->num_rows > 0;
	}
	if (!$tieneCondicion) return "";
	$pref = $alias !== '' ? $alias.'.' : '';
	return " AND {$pref}condicion<>2";
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
