<?php
require_once __DIR__ . "/../config/Conexion.php";

class ClienteTienda
{
    public function registrar($nombre, $email, $password, $telefono, $direccion, $distrito)
    {
        $nombre    = limpiarCadena(trim((string)$nombre));
        $email     = limpiarCadena(strtolower(trim((string)$email)));
        $telefono  = limpiarCadena(trim((string)$telefono));
        $direccion = limpiarCadena(trim((string)$direccion));
        $distrito  = limpiarCadena(trim((string)$distrito));

        if ($nombre === '' || $email === '' || $password === '') return false;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

        // Verificar email único
        $existe = ejecutarConsultaSimpleFila("SELECT idcliente_tienda FROM cliente_tienda WHERE email='$email' LIMIT 1");
        if ($existe) return array('ok'=>false,'message'=>'Ya existe una cuenta con ese correo electronico.');

        $hash = password_hash((string)$password, PASSWORD_DEFAULT);
        $hashE = limpiarCadena($hash);

        $sql = "INSERT INTO cliente_tienda (nombre, email, password_hash, telefono, direccion, distrito)
                VALUES('$nombre','$email','$hashE','$telefono','$direccion','$distrito')";
        $id = ejecutarConsulta_retornarID($sql);
        return $id ? array(
            'ok'              => true,
            'idcliente_tienda'=> (int)$id,
            'nombre'          => $nombre,
            'email'           => $email,
            'telefono'        => $telefono,
            'direccion'       => $direccion,
            'distrito'        => $distrito
        ) : false;
    }

    public function login($email, $password)
    {
        $email = limpiarCadena(strtolower(trim((string)$email)));
        $row = ejecutarConsultaSimpleFila(
            "SELECT idcliente_tienda, nombre, email, password_hash, activo,
                    telefono, direccion, distrito
             FROM cliente_tienda WHERE email='$email' LIMIT 1"
        );
        if (!$row) return false;
        if (!(int)$row['activo']) return array('ok'=>false,'message'=>'Tu cuenta esta desactivada.');
        if (!password_verify((string)$password, (string)$row['password_hash'])) return false;
        return array(
            'ok'              => true,
            'idcliente_tienda'=> (int)$row['idcliente_tienda'],
            'nombre'          => $row['nombre'],
            'email'           => $row['email'],
            'telefono'        => $row['telefono'] ?? '',
            'direccion'       => $row['direccion'] ?? '',
            'distrito'        => $row['distrito']  ?? ''
        );
    }

    public function obtener($id)
    {
        $id = (int)$id;
        return ejecutarConsultaSimpleFila(
            "SELECT idcliente_tienda, nombre, email, telefono, direccion, distrito, fecha_registro
             FROM cliente_tienda WHERE idcliente_tienda='$id' LIMIT 1"
        );
    }

    public function actualizar($id, $nombre, $telefono, $direccion, $distrito)
    {
        $id        = (int)$id;
        $nombre    = limpiarCadena(trim((string)$nombre));
        $telefono  = limpiarCadena(trim((string)$telefono));
        $direccion = limpiarCadena(trim((string)$direccion));
        $distrito  = limpiarCadena(trim((string)$distrito));
        return ejecutarConsulta(
            "UPDATE cliente_tienda SET nombre='$nombre', telefono='$telefono',
             direccion='$direccion', distrito='$distrito' WHERE idcliente_tienda='$id'"
        );
    }
}
?>
