<?php 
//incluir la conexion de base de datos
require_once __DIR__ . "/../config/Conexion.php";
class Articulo{


	//implementamos nuestro constructor
public function __construct(){

}

//metodo insertar registro
public function insertar($idcategoria,$idunidad,$codigo,$nombre,$stock,$stock_minimo,$precio_venta,$descripcion,$imagen,
	$principio_activo='',$concentracion='',$forma_farmaceutica='',$via_administracion='',
	$laboratorio='',$registro_sanitario='',$requiere_frio=0,$tipo_venta='OTC'){
	$tiposVentaPermitidos = array('OTC','RX','CONTROL_ESPECIAL');
	$tipo_venta = in_array($tipo_venta, $tiposVentaPermitidos, true) ? $tipo_venta : 'OTC';
	$requiere_frio = $requiere_frio ? 1 : 0;
	$precio_venta = max(0, (float)$precio_venta);
	$sql="INSERT INTO articulo
		(idcategoria,idunidad,codigo,nombre,stock,stock_minimo,precio_venta,descripcion,imagen,condicion,
		 principio_activo,concentracion,forma_farmaceutica,via_administracion,
		 laboratorio,registro_sanitario,requiere_frio,tipo_venta)
	 VALUES ('$idcategoria','$idunidad','$codigo','$nombre','$stock','$stock_minimo','$precio_venta','$descripcion','$imagen','1',
	 		 '$principio_activo','$concentracion','$forma_farmaceutica','$via_administracion',
	 		 '$laboratorio','$registro_sanitario','$requiere_frio','$tipo_venta')";
	return ejecutarConsulta($sql);
}

public function editar($idarticulo,$idcategoria,$idunidad,$codigo,$nombre,$stock,$stock_minimo,$precio_venta,$descripcion,$imagen,
	$principio_activo='',$concentracion='',$forma_farmaceutica='',$via_administracion='',
	$laboratorio='',$registro_sanitario='',$requiere_frio=0,$tipo_venta='OTC'){
	$tiposVentaPermitidos = array('OTC','RX','CONTROL_ESPECIAL');
	$tipo_venta = in_array($tipo_venta, $tiposVentaPermitidos, true) ? $tipo_venta : 'OTC';
	$requiere_frio = $requiere_frio ? 1 : 0;
	$precio_venta = max(0, (float)$precio_venta);
	$sql="UPDATE articulo SET
		idcategoria='$idcategoria',idunidad='$idunidad',codigo='$codigo',nombre='$nombre',
		stock='$stock',stock_minimo='$stock_minimo',precio_venta='$precio_venta',
		descripcion='$descripcion',imagen='$imagen',
		principio_activo='$principio_activo',concentracion='$concentracion',
		forma_farmaceutica='$forma_farmaceutica',via_administracion='$via_administracion',
		laboratorio='$laboratorio',registro_sanitario='$registro_sanitario',
		requiere_frio='$requiere_frio',tipo_venta='$tipo_venta'
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
	$sql="SELECT a.idarticulo,a.idcategoria,a.idunidad,c.nombre as categoria,u.nombre as unidad,u.abreviatura,
		a.codigo,a.nombre,a.stock,a.stock_minimo,a.precio_venta,a.descripcion,a.imagen,a.condicion
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
	$sql="SELECT a.idarticulo,a.idcategoria,a.idunidad,c.nombre as categoria,u.nombre as unidad,u.abreviatura,
		a.codigo,a.nombre,a.stock,a.stock_minimo,
		IFNULL(a.principio_activo,'') as principio_activo,
		IFNULL(a.concentracion,'') as concentracion,
		IFNULL(a.tipo_venta,'OTC') as tipo_venta,
		COALESCE(
			NULLIF(a.precio_venta, 0),
			(SELECT di.precio_venta FROM detalle_ingreso di WHERE di.idarticulo=a.idarticulo ORDER BY di.iddetalle_ingreso DESC LIMIT 1),
			0
		) AS precio_venta,
		a.descripcion,a.imagen,a.condicion
	FROM articulo a
	INNER JOIN categoria c ON a.idcategoria=c.idcategoria
	LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
	WHERE a.condicion='1'";
	return ejecutarConsulta($sql);
}

public function buscarActivoPorCodigo($codigo){
	$sql="SELECT a.idarticulo,a.codigo,a.nombre,a.stock,a.tipo_venta,
		IFNULL(a.principio_activo,'') as principio_activo,
		IFNULL(u.abreviatura,'und') as abreviatura,
		COALESCE(
			NULLIF(a.precio_venta, 0),
			(SELECT di.precio_venta FROM detalle_ingreso di WHERE di.idarticulo=a.idarticulo ORDER BY di.iddetalle_ingreso DESC LIMIT 1),
			0
		) AS precio_venta
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
	$sql="SELECT a.idarticulo,a.codigo,a.nombre,a.stock,a.tipo_venta,
		IFNULL(a.principio_activo,'') as principio_activo,
		IFNULL(a.concentracion,'') as concentracion,
		IFNULL(u.abreviatura,'und') as abreviatura,
		COALESCE(
			NULLIF(a.precio_venta, 0),
			(SELECT di.precio_venta FROM detalle_ingreso di WHERE di.idarticulo=a.idarticulo ORDER BY di.iddetalle_ingreso DESC LIMIT 1),
			0
		) AS precio_venta
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
