<?php
require_once __DIR__ . "/../config/Conexion.php";

class AIConfig
{
    public function obtener()
    {
        $sql = "SELECT * FROM ai_config WHERE idai_config = 1";
        $row = ejecutarConsultaSimpleFila($sql);
        if ($row) return $row;

        ejecutarConsulta("INSERT INTO ai_config (idai_config, openai_model, smtp_port, smtp_secure) VALUES (1, 'gpt-4o-mini', 587, 'tls')");
        return ejecutarConsultaSimpleFila($sql);
    }

    public function guardar($data)
    {
        $openai_api_key = limpiarCadena($data['openai_api_key'] ?? '');
        $openai_model   = limpiarCadena($data['openai_model']   ?? 'gpt-4o-mini');
        $openai_org     = limpiarCadena($data['openai_org']     ?? '');
        $smtp_host      = limpiarCadena($data['smtp_host']      ?? '');
        $smtp_port      = (int)($data['smtp_port'] ?? 587);
        $smtp_secure    = limpiarCadena($data['smtp_secure']    ?? 'tls');
        $smtp_user      = limpiarCadena($data['smtp_user']      ?? '');
        $smtp_pass      = limpiarCadena($data['smtp_pass']      ?? '');
        $smtp_from      = limpiarCadena($data['smtp_from']      ?? '');
        $smtp_from_name = limpiarCadena($data['smtp_from_name'] ?? '');

        $this->obtener();

        $sql = "UPDATE ai_config SET
            openai_api_key = '$openai_api_key',
            openai_model   = '$openai_model',
            openai_org     = '$openai_org',
            smtp_host      = '$smtp_host',
            smtp_port      = $smtp_port,
            smtp_secure    = '$smtp_secure',
            smtp_user      = '$smtp_user',
            smtp_pass      = '$smtp_pass',
            smtp_from      = '$smtp_from',
            smtp_from_name = '$smtp_from_name'
            WHERE idai_config = 1";

        return ejecutarConsulta($sql);
    }
}
