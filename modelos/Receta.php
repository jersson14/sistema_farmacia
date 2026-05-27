<?php
require_once __DIR__ . "/../config/Conexion.php";

class Receta
{
    public function insertar($idventa, $idcliente, $nombre_medico, $colegiatura, $establecimiento, $fecha_emision, $tipo_receta, $observaciones)
    {
        $idventa         = (int)$idventa;
        $idcliente       = $idcliente ? (int)$idcliente : null;
        $nombre_medico   = limpiarCadena((string)$nombre_medico);
        $colegiatura     = limpiarCadena((string)$colegiatura);
        $establecimiento = limpiarCadena((string)$establecimiento);
        $fecha_emision   = trim((string)$fecha_emision);
        $tiposPermitidos = array('SIMPLE', 'ESPECIAL', 'RETENIDA');
        $tipo_receta     = in_array($tipo_receta, $tiposPermitidos, true) ? $tipo_receta : 'SIMPLE';
        $observaciones   = limpiarCadena((string)$observaciones);

        $clienteSql = $idcliente ? "'$idcliente'" : 'NULL';
        $fechaSql   = ($fecha_emision && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_emision)) ? "'$fecha_emision'" : 'NULL';

        $sql = "INSERT INTO receta_medica (idventa, idcliente, nombre_medico, colegiatura, establecimiento, fecha_emision, tipo_receta, observaciones)
                VALUES('$idventa', $clienteSql, '$nombre_medico', '$colegiatura', '$establecimiento', $fechaSql, '$tipo_receta', '$observaciones')";
        return ejecutarConsulta_retornarID($sql);
    }

    public function listarPorVenta($idventa)
    {
        $idventa = (int)$idventa;
        $sql = "SELECT r.*, p.nombre AS cliente
                FROM receta_medica r
                LEFT JOIN persona p ON r.idcliente = p.idpersona
                WHERE r.idventa = '$idventa'
                LIMIT 1";
        return ejecutarConsultaSimpleFila($sql);
    }

    public function listar($fechaInicio = '', $fechaFin = '')
    {
        $where = array();
        if ($fechaInicio !== '') $where[] = "DATE(r.fecha_registro) >= '$fechaInicio'";
        if ($fechaFin   !== '') $where[] = "DATE(r.fecha_registro) <= '$fechaFin'";
        $filtro = count($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT r.idreceta, r.idventa, r.nombre_medico, r.colegiatura, r.tipo_receta,
                       DATE_FORMAT(r.fecha_emision,'%d/%m/%Y') AS fecha_emision,
                       DATE_FORMAT(r.fecha_registro,'%d/%m/%Y %H:%i') AS fecha_registro,
                       p.nombre AS cliente
                FROM receta_medica r
                LEFT JOIN persona p ON r.idcliente = p.idpersona
                $filtro
                ORDER BY r.idreceta DESC";
        return ejecutarConsulta($sql);
    }
}
?>
