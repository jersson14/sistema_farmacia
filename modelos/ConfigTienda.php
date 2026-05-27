<?php
require_once __DIR__ . "/../config/Conexion.php";

class ConfigTienda
{
    private $cache = null;

    public function obtener($clave, $defecto = '')
    {
        if ($this->cache === null) $this->cargarTodo();
        return isset($this->cache[$clave]) ? $this->cache[$clave] : $defecto;
    }

    public function cargarTodo()
    {
        $this->cache = array();
        try {
            $rs = ejecutarConsulta("SELECT clave, valor FROM config_tienda");
            if ($rs) {
                while ($row = $rs->fetch_assoc()) {
                    $this->cache[$row['clave']] = $row['valor'];
                }
            }
        } catch (Throwable $e) {
            // Tabla no existe aún — usar valores por defecto hasta que se ejecute la migración
        }
        return $this->cache;
    }

    public function guardar($clave, $valor)
    {
        $clave = limpiarCadena($clave);
        $valor = limpiarCadena($valor);
        return ejecutarConsulta(
            "INSERT INTO config_tienda (clave, valor, descripcion) VALUES('$clave','$valor','')
             ON DUPLICATE KEY UPDATE valor='$valor'"
        );
    }

    public function guardarMultiple(array $pares)
    {
        $ok = true;
        foreach ($pares as $clave => $valor) {
            $this->guardar($clave, $valor) or $ok = false;
        }
        return $ok;
    }

    public function estaActiva()
    {
        return (string)$this->obtener('tienda_activa', '1') === '1';
    }
}
?>
