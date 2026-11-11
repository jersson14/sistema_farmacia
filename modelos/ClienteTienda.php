<?php
require_once __DIR__ . "/../config/Conexion.php";

class ClienteTienda
{
    /**
     * Registro de cliente para la tienda online.
     * Identificador principal: DNI. Email es opcional.
     */
    public function registrar($nombre, $dni, $password, $telefono, $direccion, $distrito, $email = '')
    {
        $nombre    = limpiarCadena(trim((string)$nombre));
        $dni       = limpiarCadena(trim((string)$dni));
        $email     = limpiarCadena(strtolower(trim((string)$email)));
        $telefono  = limpiarCadena(trim((string)$telefono));
        $direccion = limpiarCadena(trim((string)$direccion));
        $distrito  = limpiarCadena(trim((string)$distrito));

        if ($nombre === '' || $dni === '' || $password === '') return false;

        // DNI peruano = 8 digitos. Carnet de extranjeria admite 9-12 alfanumericos.
        // Aceptamos 8-12 caracteres, solo digitos y letras (mayus).
        if (!preg_match('/^[0-9A-Z]{8,12}$/', strtoupper($dni))) {
            return array('ok'=>false,'message'=>'DNI invalido. Debe tener entre 8 y 12 caracteres.');
        }
        $dni = strtoupper($dni);

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return array('ok'=>false,'message'=>'El correo electronico no es valido.');
        }

        // DNI unico
        $existe = ejecutarConsultaSimpleFila("SELECT idcliente_tienda FROM cliente_tienda WHERE dni='$dni' LIMIT 1");
        if ($existe) return array('ok'=>false,'message'=>'Ya existe una cuenta registrada con ese DNI.');

        $hash  = password_hash((string)$password, PASSWORD_DEFAULT);
        $hashE = limpiarCadena($hash);

        $emailSql = $email === '' ? 'NULL' : "'$email'";

        $sql = "INSERT INTO cliente_tienda (nombre, dni, email, password_hash, telefono, direccion, distrito)
                VALUES('$nombre','$dni',$emailSql,'$hashE','$telefono','$direccion','$distrito')";
        $id = ejecutarConsulta_retornarID($sql);
        return $id ? array(
            'ok'              => true,
            'idcliente_tienda'=> (int)$id,
            'nombre'          => $nombre,
            'dni'             => $dni,
            'email'           => $email,
            'telefono'        => $telefono,
            'direccion'       => $direccion,
            'distrito'        => $distrito
        ) : false;
    }

    /**
     * Login por DNI.
     */
    public function login($dni, $password)
    {
        $dni = limpiarCadena(strtoupper(trim((string)$dni)));
        if ($dni === '') return false;

        $row = ejecutarConsultaSimpleFila(
            "SELECT idcliente_tienda, nombre, dni, email, password_hash, activo,
                    telefono, direccion, distrito
             FROM cliente_tienda WHERE dni='$dni' LIMIT 1"
        );
        if (!$row) return false;
        if (!(int)$row['activo']) return array('ok'=>false,'message'=>'Tu cuenta esta desactivada.');
        if (!password_verify((string)$password, (string)$row['password_hash'])) return false;
        return array(
            'ok'              => true,
            'idcliente_tienda'=> (int)$row['idcliente_tienda'],
            'nombre'          => $row['nombre'],
            'dni'             => $row['dni'],
            'email'           => $row['email'] ?? '',
            'telefono'        => $row['telefono'] ?? '',
            'direccion'       => $row['direccion'] ?? '',
            'distrito'        => $row['distrito']  ?? ''
        );
    }

    public function obtener($id)
    {
        $id = (int)$id;
        return ejecutarConsultaSimpleFila(
            "SELECT idcliente_tienda, nombre, dni, email, telefono, direccion, distrito, fecha_registro
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
