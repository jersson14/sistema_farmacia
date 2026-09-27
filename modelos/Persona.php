<?php 
//incluir la conexion de base de datos
require_once __DIR__ . "/../config/Conexion.php";
class Persona{


	//implementamos nuestro constructor
public function __construct(){

}

//metodo insertar regiustro
public function insertar($tipo_persona,$nombre,$tipo_documento,$num_documento,$direccion,$telefono,$email){
	$sql="INSERT INTO persona (tipo_persona,nombre,tipo_documento,num_documento,direccion,telefono,email) VALUES ('$tipo_persona','$nombre','$tipo_documento','$num_documento','$direccion','$telefono','$email')";
	return ejecutarConsulta($sql);
}

public function insertarRetornarId($tipo_persona,$nombre,$tipo_documento,$num_documento,$direccion,$telefono,$email){
	$sql="INSERT INTO persona (tipo_persona,nombre,tipo_documento,num_documento,direccion,telefono,email) VALUES ('$tipo_persona','$nombre','$tipo_documento','$num_documento','$direccion','$telefono','$email')";
	return ejecutarConsulta_retornarID($sql);
}



public function editar($idpersona,$tipo_persona,$nombre,$tipo_documento,$num_documento,$direccion,$telefono,$email){
	$sql="UPDATE persona SET tipo_persona='$tipo_persona', nombre='$nombre',tipo_documento='$tipo_documento',num_documento='$num_documento',direccion='$direccion',telefono='$telefono',email='$email' 
	WHERE idpersona='$idpersona'";
	return ejecutarConsulta($sql);
}
//funcion para eliminar datos
public function eliminar($idpersona){
	$idpersona = (int)$idpersona;
	$cf = ejecutarConsultaSimpleFila("SELECT nombre, tipo_persona FROM persona WHERE idpersona='$idpersona' LIMIT 1");
	if ($cf && strtoupper(trim($cf['nombre'])) === 'CONSUMIDOR FINAL') {
		return array("ok"=>false, "message"=>"\"Consumidor Final\" lo usa el sistema para las boletas sin cliente y no se puede eliminar");
	}
	$etiqueta = ($cf && strtoupper(trim($cf['tipo_persona'])) === 'PROVEEDOR') ? 'El proveedor' : 'El cliente';
	return eliminarRegistro('persona', 'idpersona', $idpersona, $etiqueta, array(
		'previos'    => array("DELETE FROM paciente_perfil WHERE idpersona='$idpersona'"),
		'archivable' => sqlPersonaVisible() !== ''
	));
}

//metodo para mostrar registros
public function mostrar($idpersona){
	$sql="SELECT * FROM persona WHERE idpersona='$idpersona'";
	return ejecutarConsultaSimpleFila($sql);
}

//listar registros
public function listarp(){
	$sql="SELECT * FROM persona WHERE tipo_persona='Proveedor'".sqlPersonaVisible()." ORDER BY nombre ASC";
	return ejecutarConsulta($sql);
}
public function listarc(){
	$sql="SELECT * FROM persona WHERE tipo_persona='Cliente'".sqlPersonaVisible()." ORDER BY nombre ASC";
	return ejecutarConsulta($sql);
}

public function buscarPorDocumento($num){
	$sql="SELECT idpersona, nombre, num_documento FROM persona WHERE tipo_persona='Cliente' AND num_documento='$num'".sqlPersonaVisible()." LIMIT 1";
	$r = ejecutarConsulta($sql);
	if ($r && $r->num_rows > 0) return $r->fetch_assoc();
	return null;
}
}

 ?>
