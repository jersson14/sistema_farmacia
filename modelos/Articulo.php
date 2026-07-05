<?php
//incluir la conexion de base de datos
require_once __DIR__ . "/../config/Conexion.php";
class Articulo{


	//implementamos nuestro constructor
public function __construct(){

}

// arma el fragmento SET/VALUES de los campos de oferta ya normalizados
private function normalizarOferta($en_oferta, $descuento_porcentaje, $oferta_fecha_inicio, $oferta_fecha_fin){
	$en_oferta = $en_oferta ? 1 : 0;
	$descuento_porcentaje = max(0, min(100, (float)$descuento_porcentaje));
	$inicio = trim((string)$oferta_fecha_inicio);
	$fin    = trim((string)$oferta_fecha_fin);
	return array(
		'en_oferta'            => $en_oferta,
		'descuento_porcentaje' => $descuento_porcentaje,
		'oferta_fecha_inicio'  => $inicio !== '' ? "'$inicio'" : 'NULL',
		'oferta_fecha_fin'     => $fin !== ''    ? "'$fin'"    : 'NULL',
	);
}

//metodo insertar registro
public function insertar($idcategoria,$idunidad,$codigo,$nombre,$stock,$stock_minimo,$precio_venta,$descripcion,$imagen,
	$principio_activo='',$concentracion='',$forma_farmaceutica='',$via_administracion='',
	$laboratorio='',$registro_sanitario='',$requiere_frio=0,$tipo_venta='OTC',
	$en_oferta=0,$descuento_porcentaje=0,$oferta_fecha_inicio='',$oferta_fecha_fin=''){
	$tiposVentaPermitidos = array('OTC','RX','CONTROL_ESPECIAL');
	$tipo_venta = in_array($tipo_venta, $tiposVentaPermitidos, true) ? $tipo_venta : 'OTC';
	$requiere_frio = $requiere_frio ? 1 : 0;
	$precio_venta = max(0, (float)$precio_venta);
	$o = $this->normalizarOferta($en_oferta, $descuento_porcentaje, $oferta_fecha_inicio, $oferta_fecha_fin);
	$sql="INSERT INTO articulo
		(idcategoria,idunidad,codigo,nombre,stock,stock_minimo,precio_venta,descripcion,imagen,condicion,
		 principio_activo,concentracion,forma_farmaceutica,via_administracion,
		 laboratorio,registro_sanitario,requiere_frio,tipo_venta,
		 en_oferta,descuento_porcentaje,oferta_fecha_inicio,oferta_fecha_fin)
	 VALUES ('$idcategoria','$idunidad','$codigo','$nombre','$stock','$stock_minimo','$precio_venta','$descripcion','$imagen','1',
	 		 '$principio_activo','$concentracion','$forma_farmaceutica','$via_administracion',
	 		 '$laboratorio','$registro_sanitario','$requiere_frio','$tipo_venta',
	 		 '{$o['en_oferta']}','{$o['descuento_porcentaje']}',{$o['oferta_fecha_inicio']},{$o['oferta_fecha_fin']})";
	return ejecutarConsulta($sql);
}

public function editar($idarticulo,$idcategoria,$idunidad,$codigo,$nombre,$stock,$stock_minimo,$precio_venta,$descripcion,$imagen,
	$principio_activo='',$concentracion='',$forma_farmaceutica='',$via_administracion='',
	$laboratorio='',$registro_sanitario='',$requiere_frio=0,$tipo_venta='OTC',
	$en_oferta=0,$descuento_porcentaje=0,$oferta_fecha_inicio='',$oferta_fecha_fin=''){
	$tiposVentaPermitidos = array('OTC','RX','CONTROL_ESPECIAL');
	$tipo_venta = in_array($tipo_venta, $tiposVentaPermitidos, true) ? $tipo_venta : 'OTC';
	$requiere_frio = $requiere_frio ? 1 : 0;
	$precio_venta = max(0, (float)$precio_venta);
	$o = $this->normalizarOferta($en_oferta, $descuento_porcentaje, $oferta_fecha_inicio, $oferta_fecha_fin);
	$sql="UPDATE articulo SET
		idcategoria='$idcategoria',idunidad='$idunidad',codigo='$codigo',nombre='$nombre',
		stock='$stock',stock_minimo='$stock_minimo',precio_venta='$precio_venta',
		descripcion='$descripcion',imagen='$imagen',
		principio_activo='$principio_activo',concentracion='$concentracion',
		forma_farmaceutica='$forma_farmaceutica',via_administracion='$via_administracion',
		laboratorio='$laboratorio',registro_sanitario='$registro_sanitario',
		requiere_frio='$requiere_frio',tipo_venta='$tipo_venta',
		en_oferta='{$o['en_oferta']}',descuento_porcentaje='{$o['descuento_porcentaje']}',
		oferta_fecha_inicio={$o['oferta_fecha_inicio']},oferta_fecha_fin={$o['oferta_fecha_fin']}
	WHERE idarticulo='$idarticulo'";
	return ejecutarConsulta($sql);
}
public function desactivar($idarticulo){
	$sql="UPDATE articulo SET condicion='0' WHERE idarticulo='$idarticulo'";
	return ejecutarConsulta($sql);
}
public function activar($idarticulo){
	$sql="UPDATE articulo SET condicion='1' WHERE idarticulo='$idarticulo'";
	return ejecutarConsulta($sql);
}

//metodo para mostrar registros
public function mostrar($idarticulo){
	$sql="SELECT * FROM articulo WHERE idarticulo='$idarticulo'";
	return ejecutarConsultaSimpleFila($sql);
}

//listar registros
public function listar(){
	$vigente = sqlOfertaVigenteExpr('a');
	$sql="SELECT a.idarticulo,a.idcategoria,a.idunidad,c.nombre as categoria,u.nombre as unidad,u.abreviatura,
		a.codigo,a.nombre,a.stock,a.stock_minimo,a.precio_venta,a.descripcion,a.imagen,a.condicion,
		a.en_oferta,a.descuento_porcentaje,a.oferta_fecha_inicio,a.oferta_fecha_fin,
		$vigente AS oferta_vigente,
		(SELECT DATE_FORMAT(la.fecha_vencimiento,'%d/%m/%Y')
		 FROM lote_articulo la
		 WHERE la.idarticulo=a.idarticulo AND la.condicion=1 AND la.cantidad_actual>0
		 ORDER BY la.fecha_vencimiento ASC LIMIT 1) AS prox_vencimiento,
		(SELECT DATEDIFF(la2.fecha_vencimiento, CURDATE())
		 FROM lote_articulo la2
		 WHERE la2.idarticulo=a.idarticulo AND la2.condicion=1 AND la2.cantidad_actual>0
		 ORDER BY la2.fecha_vencimiento ASC LIMIT 1) AS dias_para_vencer
	FROM articulo a
	INNER JOIN categoria c ON a.idcategoria=c.idcategoria
	LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad";
	return ejecutarConsulta($sql);
}

//listar registros activos
public function listarActivos(){
	$sql="SELECT a.idarticulo,a.idcategoria,a.idunidad,c.nombre as categoria,u.nombre as unidad,u.abreviatura,a.codigo,a.nombre,a.stock,a.stock_minimo,
	(SELECT di.precio_compra FROM detalle_ingreso di WHERE di.idarticulo=a.idarticulo ORDER BY di.iddetalle_ingreso DESC LIMIT 0,1) AS precio_compra_ref,
	(SELECT di.precio_venta FROM detalle_ingreso di WHERE di.idarticulo=a.idarticulo ORDER BY di.iddetalle_ingreso DESC LIMIT 0,1) AS precio_venta_ref,
	a.descripcion,a.imagen,a.condicion
	FROM articulo a
	INNER JOIN categoria c ON a.idcategoria=c.idcategoria
	LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
	WHERE a.condicion='1'";
	return ejecutarConsulta($sql);
}

public function listarActivosVenta(){
	$precioFinal = sqlPrecioFinalExpr('a');
	$precioBase  = sqlPrecioBaseExpr('a');
	$vigente     = sqlOfertaVigenteExpr('a');
	$sql="SELECT a.idarticulo,a.idcategoria,a.idunidad,c.nombre as categoria,u.nombre as unidad,u.abreviatura,
		a.codigo,a.nombre,a.stock,a.stock_minimo,
		IFNULL(a.principio_activo,'') as principio_activo,
		IFNULL(a.concentracion,'') as concentracion,
		IFNULL(a.tipo_venta,'OTC') as tipo_venta,
		$precioFinal AS precio_venta,
		$precioBase AS precio_venta_original,
		$vigente AS en_oferta,
		a.descuento_porcentaje,
		a.descripcion,a.imagen,a.condicion
	FROM articulo a
	INNER JOIN categoria c ON a.idcategoria=c.idcategoria
	LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
	WHERE a.condicion='1'";
	return ejecutarConsulta($sql);
}

public function buscarActivoPorCodigo($codigo){
	$precioFinal = sqlPrecioFinalExpr('a');
	$precioBase  = sqlPrecioBaseExpr('a');
	$vigente     = sqlOfertaVigenteExpr('a');
	$sql="SELECT a.idarticulo,a.codigo,a.nombre,a.stock,a.tipo_venta,
		IFNULL(a.principio_activo,'') as principio_activo,
		IFNULL(u.abreviatura,'und') as abreviatura,
		$precioFinal AS precio_venta,
		$precioBase AS precio_venta_original,
		$vigente AS en_oferta,
		a.descuento_porcentaje
	FROM articulo a
	LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
	WHERE a.condicion='1'
	AND (a.codigo='$codigo' OR a.codigo LIKE '$codigo%' OR a.nombre LIKE '%$codigo%'
	     OR a.principio_activo LIKE '%$codigo%')
	ORDER BY (a.codigo='$codigo') DESC, a.idarticulo ASC
	LIMIT 1";
	return ejecutarConsultaSimpleFila($sql);
}

// Búsqueda extendida para venta: por nombre, principio activo, laboratorio, código
public function buscarExtendido($termino){
	$t = limpiarCadena($termino);
	$precioFinal = sqlPrecioFinalExpr('a');
	$precioBase  = sqlPrecioBaseExpr('a');
	$vigente     = sqlOfertaVigenteExpr('a');
	$sql="SELECT a.idarticulo,a.codigo,a.nombre,a.stock,a.tipo_venta,
		IFNULL(a.principio_activo,'') as principio_activo,
		IFNULL(a.concentracion,'') as concentracion,
		IFNULL(u.abreviatura,'und') as abreviatura,
		$precioFinal AS precio_venta,
		$precioBase AS precio_venta_original,
		$vigente AS en_oferta,
		a.descuento_porcentaje
	FROM articulo a
	LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
	WHERE a.condicion='1'
	AND (a.nombre LIKE '%$t%' OR a.principio_activo LIKE '%$t%'
	     OR a.laboratorio LIKE '%$t%' OR a.codigo LIKE '%$t%'
	     OR a.codigo='$t')
	ORDER BY (a.codigo='$t') DESC, a.nombre ASC
	LIMIT 20";
	return ejecutarConsulta($sql);
}
}
 ?>
