<?php
require_once __DIR__ . "/../config/Conexion.php";
require_once __DIR__ . "/AIService.php";
require_once __DIR__ . "/Empresa.php";

/**
 * Orquestador de reportes IA.
 *
 * Flujo generico por reporte:
 *   1) Ejecutar queries fijas (SQL escrito por nosotros, no por la IA)
 *   2) Empaquetar datos en JSON
 *   3) Pedir a OpenAI un correo HTML con analisis + insights
 *   4) Enviar por SMTP + log en ai_report_log
 */
class AIReporte
{
    // ----- Definiciones y destinatarios -----

    public function listarDefiniciones()
    {
        return ejecutarConsulta("SELECT * FROM ai_report_definicion WHERE activo=1 ORDER BY idreport ASC");
    }

    public function obtenerDefinicion($codigo)
    {
        $codigo = limpiarCadena($codigo);
        return ejecutarConsultaSimpleFila("SELECT * FROM ai_report_definicion WHERE codigo='$codigo' LIMIT 1");
    }

    public function guardarDestinatarios($codigo, $destinatarios)
    {
        $codigo = limpiarCadena($codigo);
        $destinatarios = limpiarCadena($destinatarios);
        return ejecutarConsulta("UPDATE ai_report_definicion SET destinatarios='$destinatarios' WHERE codigo='$codigo'");
    }

    public function listarLog($codigo = null, $limit = 20)
    {
        $limit = (int)$limit;
        $where = '';
        if ($codigo) {
            $codigo = limpiarCadena($codigo);
            $where = "WHERE codigo='$codigo'";
        }
        return ejecutarConsulta("SELECT idlog, codigo, destinatarios, asunto, tokens_prompt, tokens_respuesta, status, error, creado_en FROM ai_report_log $where ORDER BY creado_en DESC LIMIT $limit");
    }

    public function obtenerLog($idlog)
    {
        $idlog = (int)$idlog;
        return ejecutarConsultaSimpleFila("SELECT * FROM ai_report_log WHERE idlog=$idlog");
    }

    // ----- Datos: cierre diario -----

    /**
     * Recolecta los datos del cierre diario para una fecha (YYYY-MM-DD).
     * Retorna un array asociativo listo para pasar a la IA.
     */
    public function datosCierreDiario($fecha = null)
    {
        $fecha = $fecha ?: date('Y-m-d');
        $fecha = limpiarCadena($fecha);
        $ayer  = date('Y-m-d', strtotime("$fecha -1 day"));

        // Totales del dia (excluye anuladas)
        $tot = ejecutarConsultaSimpleFila("
            SELECT
              COUNT(*)                        AS num_ventas,
              IFNULL(SUM(total_venta),0)      AS total_ventas,
              IFNULL(SUM(impuesto),0)         AS total_impuesto,
              IFNULL(AVG(total_venta),0)      AS ticket_promedio,
              IFNULL(MIN(total_venta),0)      AS ticket_min,
              IFNULL(MAX(total_venta),0)      AS ticket_max
            FROM venta
            WHERE DATE(fecha_hora)='$fecha' AND estado <> 'Anulada'
        ");

        // Comparativa vs. ayer
        $totAyer = ejecutarConsultaSimpleFila("
            SELECT COUNT(*) AS num_ventas, IFNULL(SUM(total_venta),0) AS total_ventas
            FROM venta WHERE DATE(fecha_hora)='$ayer' AND estado <> 'Anulada'
        ");

        // Anuladas del dia
        $anu = ejecutarConsultaSimpleFila("
            SELECT COUNT(*) AS anuladas, IFNULL(SUM(total_venta),0) AS monto_anulado
            FROM venta WHERE DATE(fecha_hora)='$fecha' AND estado='Anulada'
        ");

        // Top 10 productos vendidos
        $topProductos = [];
        $rs = ejecutarConsulta("
            SELECT a.nombre, SUM(dv.cantidad) AS unidades, SUM(dv.cantidad*dv.precio_venta) AS monto
            FROM detalle_venta dv
            INNER JOIN venta v      ON v.idventa = dv.idventa
            INNER JOIN articulo a   ON a.idarticulo = dv.idarticulo
            WHERE DATE(v.fecha_hora)='$fecha' AND v.estado <> 'Anulada'
            GROUP BY a.idarticulo, a.nombre
            ORDER BY monto DESC
            LIMIT 10
        ");
        if ($rs) while ($r = $rs->fetch_assoc()) $topProductos[] = $r;

        // Ventas por tipo de comprobante
        $porComprobante = [];
        $rs = ejecutarConsulta("
            SELECT tipo_comprobante, COUNT(*) AS cantidad, IFNULL(SUM(total_venta),0) AS monto
            FROM venta
            WHERE DATE(fecha_hora)='$fecha' AND estado <> 'Anulada'
            GROUP BY tipo_comprobante
        ");
        if ($rs) while ($r = $rs->fetch_assoc()) $porComprobante[] = $r;

        // Ventas por metodo de pago (si la columna existe)
        $porMetodoPago = [];
        $tieneCol = ejecutarConsulta("SHOW COLUMNS FROM venta LIKE 'metodo_pago'");
        if ($tieneCol && $tieneCol->num_rows > 0) {
            $rs = ejecutarConsulta("
                SELECT IFNULL(metodo_pago,'No especificado') AS metodo_pago,
                       COUNT(*) AS cantidad, IFNULL(SUM(total_venta),0) AS monto
                FROM venta
                WHERE DATE(fecha_hora)='$fecha' AND estado <> 'Anulada'
                GROUP BY metodo_pago
            ");
            if ($rs) while ($r = $rs->fetch_assoc()) $porMetodoPago[] = $r;
        }

        // Ventas por hora
        $porHora = [];
        $rs = ejecutarConsulta("
            SELECT HOUR(fecha_hora) AS hora, COUNT(*) AS cantidad, IFNULL(SUM(total_venta),0) AS monto
            FROM venta
            WHERE DATE(fecha_hora)='$fecha' AND estado <> 'Anulada'
            GROUP BY HOUR(fecha_hora)
            ORDER BY hora
        ");
        if ($rs) while ($r = $rs->fetch_assoc()) $porHora[] = $r;

        return [
            'fecha'            => $fecha,
            'fecha_anterior'   => $ayer,
            'totales'          => $tot,
            'totales_ayer'     => $totAyer,
            'anuladas'         => $anu,
            'top_productos'    => $topProductos,
            'por_comprobante'  => $porComprobante,
            'por_metodo_pago'  => $porMetodoPago,
            'por_hora'         => $porHora,
        ];
    }

    // ----- Datos: vencimientos -----

    public function datosVencimientos($dias = 60)
    {
        $dias = max(1, (int)$dias);

        // Vencidos con stock (urgencia maxima)
        $vencidos = [];
        $rs = ejecutarConsulta("
            SELECT l.numero_lote, l.fecha_vencimiento, l.cantidad_actual,
                   a.nombre AS articulo, a.codigo,
                   DATEDIFF(CURDATE(), l.fecha_vencimiento) AS dias_vencido
            FROM lote_articulo l
            INNER JOIN articulo a ON a.idarticulo = l.idarticulo
            WHERE l.condicion=1 AND l.cantidad_actual > 0
              AND l.fecha_vencimiento < CURDATE()
            ORDER BY l.fecha_vencimiento ASC
        ");
        if ($rs) while ($r = $rs->fetch_assoc()) $vencidos[] = $r;

        // Proximos a vencer (dentro de $dias)
        $proximos = [];
        $rs = ejecutarConsulta("
            SELECT l.numero_lote, l.fecha_vencimiento, l.cantidad_actual,
                   a.nombre AS articulo, a.codigo,
                   DATEDIFF(l.fecha_vencimiento, CURDATE()) AS dias_restantes
            FROM lote_articulo l
            INNER JOIN articulo a ON a.idarticulo = l.idarticulo
            WHERE l.condicion=1 AND l.cantidad_actual > 0
              AND l.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $dias DAY)
            ORDER BY l.fecha_vencimiento ASC
        ");
        if ($rs) while ($r = $rs->fetch_assoc()) $proximos[] = $r;

        // Valor economico en riesgo (stock * ultimo precio de compra)
        $riesgo = ejecutarConsultaSimpleFila("
            SELECT IFNULL(SUM(l.cantidad_actual *
                COALESCE((SELECT di.precio_compra FROM detalle_ingreso di
                          WHERE di.idarticulo=l.idarticulo
                          ORDER BY di.iddetalle_ingreso DESC LIMIT 1),0)
            ),0) AS valor
            FROM lote_articulo l
            WHERE l.condicion=1 AND l.cantidad_actual > 0
              AND l.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $dias DAY)
        ");

        return [
            'fecha_reporte'   => date('Y-m-d'),
            'ventana_dias'    => $dias,
            'vencidos'        => $vencidos,
            'total_vencidos'  => count($vencidos),
            'proximos'        => $proximos,
            'total_proximos'  => count($proximos),
            'valor_en_riesgo' => (float)($riesgo['valor'] ?? 0),
        ];
    }

    // ----- Datos: stock critico -----

    public function datosStockCritico()
    {
        // Productos por debajo del stock minimo (con minimo > 0)
        $criticos = [];
        $rs = ejecutarConsulta("
            SELECT a.idarticulo, a.codigo, a.nombre, a.stock, a.stock_minimo,
                   IFNULL(c.nombre,'Sin categoria') AS categoria,
                   (a.stock_minimo - a.stock) AS deficit
            FROM articulo a
            LEFT JOIN categoria c ON c.idcategoria = a.idcategoria
            WHERE a.condicion=1
              AND a.stock_minimo > 0
              AND a.stock <= a.stock_minimo
            ORDER BY deficit DESC, a.nombre ASC
            LIMIT 100
        ");
        if ($rs) while ($r = $rs->fetch_assoc()) $criticos[] = $r;

        // Agotados (stock <= 0) sin importar minimo
        $agotados = [];
        $rs = ejecutarConsulta("
            SELECT a.idarticulo, a.codigo, a.nombre,
                   IFNULL(c.nombre,'Sin categoria') AS categoria
            FROM articulo a
            LEFT JOIN categoria c ON c.idcategoria = a.idcategoria
            WHERE a.condicion=1 AND a.stock <= 0
            ORDER BY a.nombre ASC
            LIMIT 50
        ");
        if ($rs) while ($r = $rs->fetch_assoc()) $agotados[] = $r;

        // Rotacion de los ultimos 30 dias para sugerir cuanto comprar
        $rotacion = [];
        $rs = ejecutarConsulta("
            SELECT dv.idarticulo,
                   SUM(dv.cantidad) AS unidades_30d,
                   ROUND(SUM(dv.cantidad)/30, 2) AS promedio_diario
            FROM detalle_venta dv
            INNER JOIN venta v ON v.idventa = dv.idventa
            WHERE v.fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              AND v.estado <> 'Anulada'
            GROUP BY dv.idarticulo
        ");
        $rotacionMap = [];
        if ($rs) while ($r = $rs->fetch_assoc()) $rotacionMap[$r['idarticulo']] = $r;

        // Enriquecer criticos con rotacion + sugerencia
        foreach ($criticos as &$c) {
            $rot = $rotacionMap[$c['idarticulo']] ?? null;
            $c['unidades_ultimos_30d'] = $rot ? (int)$rot['unidades_30d']    : 0;
            $c['promedio_diario']      = $rot ? (float)$rot['promedio_diario'] : 0;
            // Sugerencia = cobertura 30 dias basada en rotacion, o al menos llenar el minimo * 2
            $sug = max(($c['promedio_diario'] * 30) - $c['stock'], ($c['stock_minimo'] * 2) - $c['stock']);
            $c['sugerencia_compra'] = max(1, (int)ceil($sug));
        }
        unset($c);

        return [
            'fecha_reporte'   => date('Y-m-d'),
            'total_criticos'  => count($criticos),
            'total_agotados'  => count($agotados),
            'criticos'        => $criticos,
            'agotados'        => $agotados,
        ];
    }

    // ----- Generar HTML via OpenAI -----

    public function generarHTMLCierreDiario($datos)
    {
        $empresa = (new Empresa())->obtener();
        $nomEmpresa = $empresa['nombre_comercial'] ?? 'Farmacia';
        $simbolo = obtenerSimboloMoneda();

        $system = "Eres un analista de negocios de una farmacia. Redactas correos ejecutivos, breves y accionables. "
                . "Escribes en espanol, en tono profesional pero calido. "
                . "Devuelves SOLO HTML valido (sin markdown, sin ```), listo para enviar por correo. "
                . "Usa tablas HTML simples (border, padding), colores sobrios (grises, azul #0f766e), "
                . "no uses estilos externos ni Javascript. Incluye siempre: "
                . "1) Titulo grande con la fecha, "
                . "2) Resumen ejecutivo en 2-3 frases, "
                . "3) Tabla de totales del dia, "
                . "4) Comparativa vs. dia anterior con % de variacion, "
                . "5) Top 5 productos vendidos, "
                . "6) 3 insights o recomendaciones al final. "
                . "Moneda a mostrar: $simbolo. Empresa: $nomEmpresa.";

        $user = "Genera el correo de cierre diario para la fecha {$datos['fecha']}. "
              . "Datos crudos (JSON):\n\n"
              . json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $svc = new AIService();
        $r = $svc->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ], ['temperature' => 0.4, 'max_tokens' => 2500]);

        return [
            'html'             => $this->limpiarHTML($r['contenido']),
            'tokens_prompt'    => $r['tokens_prompt'],
            'tokens_respuesta' => $r['tokens_respuesta'],
        ];
    }

    public function generarHTMLVencimientos($datos)
    {
        $empresa = (new Empresa())->obtener();
        $nomEmpresa = $empresa['nombre_comercial'] ?? 'Farmacia';
        $simbolo = obtenerSimboloMoneda();

        $system = "Eres un quimico farmaceutico regente. Redactas un correo urgente y accionable sobre lotes proximos a vencer y ya vencidos. "
                . "Escribes en espanol, tono profesional y directo. Devuelves SOLO HTML valido (sin markdown, sin ```). "
                . "Usa tablas HTML simples con border/padding, colores sobrios (rojo #b91c1c para vencidos, amarillo #d97706 para proximos, azul #0f766e para titulos). "
                . "Estructura obligatoria: "
                . "1) Titulo con fecha del reporte, "
                . "2) Alerta destacada si hay lotes VENCIDOS con stock (deben retirarse ya de la venta), "
                . "3) Tabla de lotes vencidos (si los hay), "
                . "4) Tabla de lotes por vencer en los proximos {$datos['ventana_dias']} dias, "
                . "5) Recomendaciones concretas: descuento, devolucion al proveedor, retiro, priorizacion FEFO. "
                . "Moneda: $simbolo. Empresa: $nomEmpresa.";

        $user = "Genera el correo de alerta de vencimientos. Datos crudos (JSON):\n\n"
              . json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $svc = new AIService();
        $r = $svc->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ], ['temperature' => 0.3, 'max_tokens' => 2500]);

        return [
            'html'             => $this->limpiarHTML($r['contenido']),
            'tokens_prompt'    => $r['tokens_prompt'],
            'tokens_respuesta' => $r['tokens_respuesta'],
        ];
    }

    public function generarHTMLStockCritico($datos)
    {
        $empresa = (new Empresa())->obtener();
        $nomEmpresa = $empresa['nombre_comercial'] ?? 'Farmacia';

        $system = "Eres un jefe de compras de farmacia. Redactas un correo de alerta de stock critico con sugerencias de reposicion. "
                . "Escribes en espanol, tono profesional y practico. Devuelves SOLO HTML valido (sin markdown, sin ```). "
                . "Usa tablas HTML simples con border/padding, colores sobrios (rojo #b91c1c para agotados, naranja #ea580c para criticos, azul #0f766e para titulos). "
                . "Estructura obligatoria: "
                . "1) Titulo con fecha del reporte y resumen ejecutivo (total criticos + total agotados), "
                . "2) Tabla de productos AGOTADOS (stock<=0) si los hay, "
                . "3) Tabla de productos CRITICOS (bajo stock minimo) con columnas: producto, categoria, stock actual, minimo, promedio diario 30d, sugerencia de compra, "
                . "4) 3 recomendaciones al final (priorizar por categoria, negociar con proveedor, revisar rotacion). "
                . "Empresa: $nomEmpresa.";

        $user = "Genera el correo de alerta de stock critico. Datos crudos (JSON):\n\n"
              . json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $svc = new AIService();
        $r = $svc->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ], ['temperature' => 0.3, 'max_tokens' => 2500]);

        return [
            'html'             => $this->limpiarHTML($r['contenido']),
            'tokens_prompt'    => $r['tokens_prompt'],
            'tokens_respuesta' => $r['tokens_respuesta'],
        ];
    }

    /**
     * Elimina bloques ``` que a veces devuelve el modelo pese al system prompt.
     */
    private function limpiarHTML($html)
    {
        $html = trim($html);
        $html = preg_replace('/^```(html)?/i', '', $html);
        $html = preg_replace('/```$/', '', $html);
        return trim($html);
    }

    // ----- Ejecutar reporte end-to-end -----

    /**
     * Genera y envia el reporte indicado. Devuelve array con {ok, message, idlog, html}.
     */
    public function ejecutar($codigo, $opts = [])
    {
        $def = $this->obtenerDefinicion($codigo);
        if (!$def) return ['ok' => false, 'message' => "Reporte '$codigo' no encontrado."];

        $destinatarios = trim($opts['destinatarios'] ?? ($def['destinatarios'] ?? ''));
        if ($destinatarios === '') {
            return ['ok' => false, 'message' => "Configura destinatarios antes de enviar."];
        }

        try {
            if ($codigo === 'cierre_diario') {
                $fecha = $opts['fecha'] ?? date('Y-m-d');
                $datos = $this->datosCierreDiario($fecha);
                $g = $this->generarHTMLCierreDiario($datos);
                $asunto = "Cierre diario de ventas - " . date('d/m/Y', strtotime($fecha));

            } elseif ($codigo === 'vencimientos') {
                $dias = (int)($opts['dias'] ?? 60);
                $datos = $this->datosVencimientos($dias);
                $g = $this->generarHTMLVencimientos($datos);
                $asunto = "Alerta de vencimientos ({$dias} dias) - " . date('d/m/Y');

            } elseif ($codigo === 'stock_critico') {
                $datos = $this->datosStockCritico();
                $g = $this->generarHTMLStockCritico($datos);
                $asunto = "Alerta de stock critico - " . date('d/m/Y');

            } else {
                return ['ok' => false, 'message' => "Reporte '$codigo' aun no implementado."];
            }

            $svc = new AIService();
            $svc->enviarCorreo($destinatarios, $asunto, $g['html']);

            $idlog = $this->guardarLog($def, $destinatarios, $asunto, $g, 'ok', null, $opts['idusuario'] ?? null);
            return [
                'ok'      => true,
                'message' => "Reporte enviado a: $destinatarios",
                'idlog'   => $idlog,
                'html'    => $g['html'],
            ];

        } catch (Throwable $e) {
            $this->guardarLog($def, $destinatarios, null, null, 'error', $e->getMessage(), $opts['idusuario'] ?? null);
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function guardarLog($def, $destinatarios, $asunto, $g, $status, $error, $idusuario)
    {
        global $conexion;
        $idreport = (int)$def['idreport'];
        $codigo   = $conexion->real_escape_string($def['codigo']);
        $dest     = $conexion->real_escape_string($destinatarios);
        $asu      = $asunto     ? "'" . $conexion->real_escape_string($asunto) . "'" : "NULL";
        $html     = ($g && !empty($g['html'])) ? "'" . $conexion->real_escape_string($g['html']) . "'" : "NULL";
        $tp       = $g['tokens_prompt']    ?? 0;
        $tr       = $g['tokens_respuesta'] ?? 0;
        $st       = $conexion->real_escape_string($status);
        $err      = $error ? "'" . $conexion->real_escape_string($error) . "'" : "NULL";
        $idu      = $idusuario ? (int)$idusuario : "NULL";

        $sql = "INSERT INTO ai_report_log
                (idreport, codigo, destinatarios, asunto, html_resultado, tokens_prompt, tokens_respuesta, status, error, idusuario)
                VALUES ($idreport, '$codigo', '$dest', $asu, $html, $tp, $tr, '$st', $err, $idu)";
        ejecutarConsulta($sql);
        return $conexion->insert_id;
    }
}
