<?php
require_once __DIR__ . "/../config/Conexion.php";

/**
 * Introspector de esquema: genera una descripcion compacta de la BD
 * para pasarla como contexto a la IA.
 *
 * Uso:
 *   $schema = (new DBSchema())->describir();
 *   // string listo para incluir en un system prompt
 */
class DBSchema
{
    private $tablasPermitidas;

    public function __construct(array $tablasPermitidas = null)
    {
        $this->tablasPermitidas = $tablasPermitidas ?? $this->tablasNegocioPorDefecto();
    }

    public function tablas()
    {
        global $conexion;
        $rs = $conexion->query("SHOW TABLES");
        $out = [];
        while ($row = $rs->fetch_array()) {
            $t = $row[0];
            if (in_array($t, $this->tablasPermitidas, true)) $out[] = $t;
        }
        return $out;
    }

    public function columnas($tabla)
    {
        global $conexion;
        $tabla = preg_replace('/[^a-zA-Z0-9_]/', '', $tabla);
        $rs = $conexion->query("SHOW COLUMNS FROM `$tabla`");
        if (!$rs) return [];
        $cols = [];
        while ($row = $rs->fetch_assoc()) {
            $cols[] = [
                'nombre' => $row['Field'],
                'tipo'   => $row['Type'],
                'null'   => $row['Null'],
                'key'    => $row['Key'],
            ];
        }
        return $cols;
    }

    /**
     * Descripcion textual del esquema (compacta) para pasar a la IA.
     */
    public function describir()
    {
        $out = "ESQUEMA DE LA BASE DE DATOS (farmacia):\n\n";
        foreach ($this->tablas() as $t) {
            $out .= "-- $t\n";
            foreach ($this->columnas($t) as $c) {
                $marker = $c['key'] === 'PRI' ? ' [PK]' : '';
                $out .= "  {$c['nombre']} : {$c['tipo']}$marker\n";
            }
            $out .= "\n";
        }
        $out .= $this->glosario();
        return $out;
    }

    private function glosario()
    {
        return "GLOSARIO DEL DOMINIO:\n"
             . "- articulo: catalogo de medicamentos/productos. Filtrar por condicion=1 para activos.\n"
             . "- venta + detalle_venta: ventas realizadas. detalle_venta.idarticulo -> articulo.\n"
             . "- ingreso + detalle_ingreso: compras a proveedores. Aumenta stock por trigger.\n"
             . "- persona: clientes y proveedores (distinguir por tipo_persona).\n"
             . "- caja_diaria + caja_movimiento: control de caja diaria por usuario.\n"
             . "- lote_articulo: trazabilidad de lotes con fecha_vencimiento, cantidad_actual, condicion=1 para activos.\n"
             . "- Ventas anuladas: venta.estado='Anulada'. Ignorar en totales salvo que se pida explicito.\n"
             . "- Moneda por defecto: PEN (soles peruanos).\n\n"
             . "REGLAS DE COLUMNAS (IMPORTANTE, no inventar):\n"
             . "- 'fecha_vencimiento' NO existe en articulo. Vive SOLO en lote_articulo.fecha_vencimiento "
             . "y en detalle_ingreso.fecha_vencimiento. Para consultas de vencimientos SIEMPRE hacer "
             . "JOIN: FROM lote_articulo la JOIN articulo a ON a.idarticulo=la.idarticulo "
             . "WHERE la.condicion=1 AND la.cantidad_actual>0.\n"
             . "- 'Productos vencidos' = lote_articulo.fecha_vencimiento < CURDATE() AND cantidad_actual>0.\n"
             . "- 'Por vencer este mes' = lote_articulo.fecha_vencimiento BETWEEN CURDATE() AND LAST_DAY(CURDATE()).\n"
             . "- 'stock' vive en articulo.stock (total agregado). El stock por lote esta en lote_articulo.cantidad_actual.\n"
             . "- Antes de referenciar cualquier columna, verifica que aparece en el esquema listado arriba. "
             . "Si no aparece, NO la uses aunque suene logica.\n";
    }

    /**
     * Tablas de negocio consultables. Excluye tablas sensibles (usuario, ai_*, etc.)
     */
    private function tablasNegocioPorDefecto()
    {
        return [
            'articulo', 'categoria', 'unidad_medida',
            'persona',
            'ingreso', 'detalle_ingreso',
            'venta', 'detalle_venta',
            'caja_diaria', 'caja_movimiento',
            'cuenta_cobrar', 'pago_cuenta_cobrar',
            'cuenta_pagar', 'pago_cuenta_pagar',
            'lote_articulo',
            'receta_medica',
            'configuracion_empresa',
        ];
    }
}
