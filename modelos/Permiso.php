<?php 
//incluir la conexion de base de datos
require_once __DIR__ . "/../config/Conexion.php";
class Permiso{ 


	//implementamos nuestro constructor
public function __construct(){

}



//listar registros
public function listar(){
	$sql="SELECT * FROM permiso";
	return ejecutarConsulta($sql);
}
}

 ?>
