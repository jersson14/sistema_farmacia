<?php
require_once __DIR__ . "/../config/Conexion.php";

class ControlEspecial
{
    /**
     * Registra en el libro de control todos los artículos CONTROL_ESPECIAL
     * de una venta. Se llama después de guardar venta + receta.
     */
    public function guardarDesdeVenta($idventa, $idreceta, $idusuario_qf, $diagnostico = '')
    {
        $idventa      = (int)$idventa;
        $idreceta     = $idreceta ? (int)$idreceta : null;
        $idusuario_qf = $idusuario_qf ? (int)$idusuario_qf : null;
        $diagnosticoE = limpiarCadena((string)$diagnostico);

        if ($idventa <= 0) return false;

        // Obtener artículos de control especial en esta venta con info de lote
        $sql = "SELECT dv.idarticulo, dv.cantidad, dv.idlote,
                       a.nombre AS nombre_articulo,
                       IFNULL(la.numero_lote,'') AS numero_lote,
                       la.fecha_vencimiento,
                       v.idcliente
                FROM detalle_venta dv
                INNER JOIN articulo a ON dv.idarticulo = a.idarticulo
                INNER JOIN venta v ON dv.idventa = v.idventa
                LEFT JOIN lote_articulo la ON dv.idlote = la.idlote
                WHERE dv.idventa = '$idventa'
                  AND a.tipo_venta = 'CONTROL_ESPECIAL'";
        $rs = ejecutarConsulta($sql);
        if (!$rs || $rs->num_rows === 0) return true; // Sin items de control

        // Obtener datos del prescriptor desde la receta
        $nombre_medico = '';
        $colegiatura   = '';
        if ($idreceta) {
            $receta = ejecutarConsultaSimpleFila(
                "SELECT nombre_medico, colegiatura FROM receta_medica WHERE idreceta='$idreceta' LIMIT 1"
            );
            if ($receta) {
                $nombre_medico = (string)$receta['nombre_medico'];
                $colegiatura   = (string)$receta['colegiatura'];
            }
        }

        // Obtener datos del QF que dispensa
        $nombre_qf       = '';
        $colegiatura_qf  = '';
        if ($idusuario_qf) {
            $qf = ejecutarConsultaSimpleFila(
                "SELECT nombre, IF(colegiatura_qf IS NULL,'',colegiatura_qf) AS colegiatura_qf
                 FROM usuario WHERE idusuario='$idusuario_qf' LIMIT 1"
            );
            if ($qf) {
                $nombre_qf      = (string)$qf['nombre'];
                $colegiatura_qf = (string)$qf['colegiatura_qf'];
            }
        }

        $idrecetaSql = $idreceta ? "'$idreceta'" : 'NULL';
        $idusuarioQfSql = $idusuario_qf ? "'$idusuario_qf'" : 'NULL';
        $nombre_medicoE = limpiarCadena($nombre_medico);
        $colegiaturaE   = limpiarCadena($colegiatura);
        $nombre_qfE     = limpiarCadena($nombre_qf);
        $colegiatura_qfE = limpiarCadena($colegiatura_qf);

        $sw = true;
        while ($row = $rs->fetch_assoc()) {
            $idarticulo   = (int)$row['idarticulo'];
            $idcliente    = $row['idcliente'] ? "'".(int)$row['idcliente']."'" : 'NULL';
            $idlote       = $row['idlote']    ? "'".(int)$row['idlote']."'"    : 'NULL';
            $cantidad     = (float)$row['cantidad'];
            $numero_lote  = limpiarCadena((string)$row['numero_lote']);
            $fVenc        = ($row['fecha_vencimiento'] && preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['fecha_vencimiento']))
                            ? "'".$row['fecha_vencimiento']."'" : 'NULL';

            $sqlIns = "INSERT INTO control_especial
                        (idventa, idarticulo, idcliente, idreceta,
                         nombre_medico, colegiatura, cantidad,
                         idlote, numero_lote, fecha_vencimiento,
                         idusuario_qf, nombre_qf, colegiatura_qf, diagnostico)
                       VALUES
                        ('$idventa','$idarticulo',$idcliente,$idrecetaSql,
                         '$nombre_medicoE','$colegiaturaE','$cantidad',
                         $idlote,'$numero_lote',$fVenc,
                         $idusuarioQfSql,'$nombre_qfE','$colegiatura_qfE','$diagnosticoE')";
            ejecutarConsulta($sqlIns) or $sw = false;
        }
        return $sw;
    }

    public function listar($fechaInicio = '', $fechaFin = '')
    {
        $where = array();
        if ($fechaInicio !== '') $where[] = "DATE(ce.fecha_registro) >= '$fechaInicio'";
        if ($fechaFin   !== '') $where[] = "DATE(ce.fecha_registro) <= '$fechaFin'";
        $filtro = count($where) ? ' WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT ce.idcontrol, ce.idventa,
                       DATE_FORMAT(ce.fecha_registro,'%d/%m/%Y %H:%i') AS fecha_registro,
                       a.nombre AS medicamento, a.codigo,
                       IFNULL(p.nombre,'---') AS paciente,
                       ce.cantidad,
                       (a.stock + IFNULL((SELECT SUM(ce2.cantidad) FROM control_especial ce2
                        WHERE ce2.idarticulo = ce.idarticulo AND ce2.idcontrol > ce.idcontrol), 0)) AS saldo,
                       ce.numero_lote,
                       IF(ce.fecha_vencimiento IS NULL,'---',DATE_FORMAT(ce.fecha_vencimiento,'%d/%m/%Y')) AS fecha_vencimiento,
                       ce.nombre_medico, ce.colegiatura,
                       ce.nombre_qf, ce.colegiatura_qf,
                       ce.diagnostico
                FROM control_especial ce
                INNER JOIN articulo a ON ce.idarticulo = a.idarticulo
                LEFT JOIN persona p   ON ce.idcliente  = p.idpersona
                $filtro
                ORDER BY ce.idcontrol DESC";
        return ejecutarConsulta($sql);
    }

    public function contarHoy()
    {
        $row = ejecutarConsultaSimpleFila(
            "SELECT COUNT(*) AS total FROM control_especial WHERE DATE(fecha_registro) = CURDATE()"
        );
        return $row ? (int)$row['total'] : 0;
    }

    public function listarParaPdf($fechaInicio = '', $fechaFin = '')
    {
        $where = array();
        if ($fechaInicio !== '') $where[] = "DATE(ce.fecha_registro) >= '$fechaInicio'";
        if ($fechaFin   !== '') $where[] = "DATE(ce.fecha_registro) <= '$fechaFin'";
        $filtro = count($where) ? ' WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT ce.idcontrol, ce.idventa,
                       DATE_FORMAT(ce.fecha_registro,'%d/%m/%Y %H:%i') AS fecha_registro,
                       a.nombre AS medicamento, a.codigo, a.principio_activo, a.concentracion,
                       IFNULL(p.nombre,'Sin nombre') AS paciente,
                       IFNULL(p.num_documento,'') AS num_documento,
                       ce.cantidad,
                       (a.stock + IFNULL((SELECT SUM(ce2.cantidad) FROM control_especial ce2
                        WHERE ce2.idarticulo = ce.idarticulo AND ce2.idcontrol > ce.idcontrol), 0)) AS saldo,
                       ce.numero_lote,
                       IF(ce.fecha_vencimiento IS NULL,'',DATE_FORMAT(ce.fecha_vencimiento,'%d/%m/%Y')) AS fecha_vencimiento,
                       ce.nombre_medico, ce.colegiatura,
                       ce.nombre_qf, ce.colegiatura_qf,
                       ce.diagnostico
                FROM control_especial ce
                INNER JOIN articulo a ON ce.idarticulo = a.idarticulo
                LEFT JOIN persona p   ON ce.idcliente  = p.idpersona
                $filtro
                ORDER BY ce.idcontrol ASC";
        return ejecutarConsulta($sql);
    }
}
?>
