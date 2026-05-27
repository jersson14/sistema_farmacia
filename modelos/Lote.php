<?php
require_once __DIR__ . "/../config/Conexion.php";

class Lote
{
    public function insertar($idarticulo, $numero_lote, $fecha_vencimiento, $fecha_fabricacion, $cantidad, $idingreso)
    {
        $idarticulo        = (int)$idarticulo;
        $numero_lote       = limpiarCadena((string)$numero_lote);
        $fecha_vencimiento = limpiarCadena((string)$fecha_vencimiento);
        $fecha_fabricacion = $fecha_fabricacion ? limpiarCadena((string)$fecha_fabricacion) : null;
        $cantidad          = max(0, (int)$cantidad);
        $idingreso         = $idingreso ? (int)$idingreso : null;

        $fabricacionSQL = $fecha_fabricacion ? "'$fecha_fabricacion'" : 'NULL';
        $ingresoSQL     = $idingreso         ? "'$idingreso'"         : 'NULL';

        $sql = "INSERT INTO lote_articulo
                    (idarticulo, numero_lote, fecha_vencimiento, fecha_fabricacion, cantidad_inicial, cantidad_actual, idingreso, condicion)
                VALUES
                    ('$idarticulo','$numero_lote','$fecha_vencimiento',$fabricacionSQL,'$cantidad','$cantidad',$ingresoSQL,1)";
        return ejecutarConsulta_retornarID($sql);
    }

    // Devuelve el lote que debe salir primero según FEFO
    public function loteFEFO($idarticulo)
    {
        $idarticulo = (int)$idarticulo;
        $sql = "SELECT idlote, numero_lote, fecha_vencimiento, cantidad_actual
                FROM lote_articulo
                WHERE idarticulo='$idarticulo'
                  AND condicion=1
                  AND cantidad_actual > 0
                  AND fecha_vencimiento >= CURDATE()
                ORDER BY fecha_vencimiento ASC
                LIMIT 1";
        return ejecutarConsultaSimpleFila($sql);
    }

    public function descontarStock($idlote, $cantidad)
    {
        $idlote   = (int)$idlote;
        $cantidad = max(0, (int)$cantidad);
        $sql = "UPDATE lote_articulo
                SET cantidad_actual = cantidad_actual - '$cantidad'
                WHERE idlote='$idlote' AND cantidad_actual >= '$cantidad'";
        return ejecutarConsulta($sql);
    }

    public function listarPorArticulo($idarticulo)
    {
        $idarticulo = (int)$idarticulo;
        $sql = "SELECT idlote, numero_lote, fecha_vencimiento, fecha_fabricacion,
                       cantidad_inicial, cantidad_actual, condicion,
                       DATEDIFF(fecha_vencimiento, CURDATE()) AS dias_restantes
                FROM lote_articulo
                WHERE idarticulo='$idarticulo'
                ORDER BY fecha_vencimiento ASC";
        return ejecutarConsulta($sql);
    }

    // Lotes que vencen dentro de $dias días y tienen stock
    public function listarProximosVencer($dias = 30)
    {
        $dias = max(1, (int)$dias);
        $sql = "SELECT l.idlote, l.numero_lote, l.fecha_vencimiento, l.cantidad_actual,
                       a.idarticulo, a.nombre AS articulo, a.codigo,
                       DATEDIFF(l.fecha_vencimiento, CURDATE()) AS dias_restantes
                FROM lote_articulo l
                INNER JOIN articulo a ON a.idarticulo = l.idarticulo
                WHERE l.condicion=1
                  AND l.cantidad_actual > 0
                  AND l.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL '$dias' DAY)
                ORDER BY l.fecha_vencimiento ASC";
        return ejecutarConsulta($sql);
    }

    public function contarProximosVencer($dias = 30)
    {
        $dias = max(1, (int)$dias);
        $sql = "SELECT COUNT(*) AS total
                FROM lote_articulo
                WHERE condicion=1
                  AND cantidad_actual > 0
                  AND fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL '$dias' DAY)";
        $row = ejecutarConsultaSimpleFila($sql);
        return $row ? (int)$row['total'] : 0;
    }

    public function listarVencidos()
    {
        $sql = "SELECT l.idlote, l.numero_lote, l.fecha_vencimiento, l.cantidad_actual,
                       a.idarticulo, a.nombre AS articulo, a.codigo,
                       DATEDIFF(CURDATE(), l.fecha_vencimiento) AS dias_vencido
                FROM lote_articulo l
                INNER JOIN articulo a ON a.idarticulo = l.idarticulo
                WHERE l.condicion=1
                  AND l.cantidad_actual > 0
                  AND l.fecha_vencimiento < CURDATE()
                ORDER BY l.fecha_vencimiento ASC";
        return ejecutarConsulta($sql);
    }

    public function contarVencidos()
    {
        $sql = "SELECT COUNT(*) AS total
                FROM lote_articulo
                WHERE condicion=1
                  AND cantidad_actual > 0
                  AND fecha_vencimiento < CURDATE()";
        $row = ejecutarConsultaSimpleFila($sql);
        return $row ? (int)$row['total'] : 0;
    }
}
?>
