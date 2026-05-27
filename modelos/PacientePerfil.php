<?php
require_once __DIR__ . "/../config/Conexion.php";

class PacientePerfil {

    public function obtener($idpersona) {
        $idpersona = (int)$idpersona;
        return ejecutarConsultaSimpleFila(
            "SELECT pp.*, p.nombre FROM paciente_perfil pp
             INNER JOIN persona p ON pp.idpersona = p.idpersona
             WHERE pp.idpersona='$idpersona' LIMIT 1"
        );
    }

    public function guardar($idpersona, $alergias, $condiciones_cron, $medicamentos_cron, $observaciones) {
        $idpersona         = (int)$idpersona;
        $alergias          = limpiarCadena(trim((string)$alergias));
        $condiciones_cron  = limpiarCadena(trim((string)$condiciones_cron));
        $medicamentos_cron = limpiarCadena(trim((string)$medicamentos_cron));
        $observaciones     = limpiarCadena(trim((string)$observaciones));
        if ($idpersona <= 0) return false;
        return ejecutarConsulta(
            "INSERT INTO paciente_perfil (idpersona,alergias,condiciones_cron,medicamentos_cron,observaciones)
             VALUES('$idpersona','$alergias','$condiciones_cron','$medicamentos_cron','$observaciones')
             ON DUPLICATE KEY UPDATE
             alergias='$alergias', condiciones_cron='$condiciones_cron',
             medicamentos_cron='$medicamentos_cron', observaciones='$observaciones'"
        );
    }
}
?>
