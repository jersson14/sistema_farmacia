<?php
require_once __DIR__ . "/../config/Conexion.php";
require_once __DIR__ . "/AIService.php";
require_once __DIR__ . "/DBSchema.php";

/**
 * AI Chat: lenguaje natural -> SQL -> resultados -> respuesta formateada.
 *
 * Seguridad:
 * - Solo se ejecutan SELECT o WITH ... SELECT.
 * - Whitelist de tablas (definida en DBSchema).
 * - Blacklist de keywords destructivas (INSERT/UPDATE/DELETE/DROP/...).
 * - Un solo statement por query.
 * - LIMIT forzado (max 500 filas) si no lo trae.
 * - No se expone la contrasena de la BD ni tablas sensibles (usuario, ai_*).
 */
class AIChat
{
    /** Keywords prohibidas. Cualquier match anula la ejecucion. */
    private $blacklist = [
        'INSERT','UPDATE','DELETE','DROP','ALTER','TRUNCATE','RENAME',
        'CREATE','REPLACE','GRANT','REVOKE','LOAD','HANDLER','LOCK','UNLOCK',
        'CALL','EXECUTE','MERGE','SET','START','COMMIT','ROLLBACK','SAVEPOINT',
        'INTO OUTFILE','INTO DUMPFILE','LOAD_FILE'
    ];

    private $limitMax = 500;

    public function preguntar($pregunta, $idusuario = null, $idconv = null)
    {
        $pregunta = trim($pregunta);
        if ($pregunta === '') {
            return ['ok' => false, 'message' => 'La pregunta esta vacia.'];
        }

        // Si no viene idconv, arrancar una conversacion nueva.
        if (empty($idconv)) $idconv = $this->nuevoIdConv();

        $tokensTot = 0;
        $sqlGenerado = null;
        $respuesta   = null;

        try {
            // Contexto conversacional: solo mensajes previos de ESTA conversacion.
            $contexto = $this->contextoConversacion($idconv, 8);

            // Paso 1: preguntar a la IA por la SQL
            $paso1 = $this->generarSQL($pregunta, $contexto);
            $tokensTot += $paso1['tokens'];
            $sqlGenerado = $paso1['sql'];

            // Paso 2: validar SQL
            $sqlSeguro = $this->validarSQL($sqlGenerado);

            // Paso 3: ejecutar
            $filas = $this->ejecutarSQL($sqlSeguro);

            // Paso 4: pedir a la IA que formatee la respuesta en lenguaje natural
            $paso2 = $this->formatearRespuesta($pregunta, $sqlSeguro, $filas);
            $tokensTot += $paso2['tokens'];
            $respuesta = $paso2['texto'];

            $this->guardarLog($idusuario, $idconv, $pregunta, $sqlSeguro, $respuesta, $paso1['tokens'], $paso2['tokens'], 'ok', null);

            return [
                'ok'         => true,
                'idconv'     => $idconv,
                'respuesta'  => $respuesta,
                'sql'        => $sqlSeguro,
                'filas'      => $filas,
                'num_filas'  => count($filas),
                'tokens'     => $tokensTot,
            ];

        } catch (Throwable $e) {
            $this->guardarLog($idusuario, $idconv, $pregunta, $sqlGenerado, null, 0, 0, 'error', $e->getMessage());
            return [
                'ok'      => false,
                'idconv'  => $idconv,
                'message' => $e->getMessage(),
                'sql'     => $sqlGenerado,
            ];
        }
    }

    public function nuevoIdConv()
    {
        // ID unico legible: timestamp + 6 chars random
        return date('YmdHis') . '-' . bin2hex(random_bytes(3));
    }

    // ----- Paso 1: LLM -> SQL -----

    private function generarSQL($pregunta, array $contexto = [])
    {
        $schema = (new DBSchema())->describir();

        $system = "Eres un experto en SQL de MySQL/MariaDB. Traduces preguntas de negocio "
                . "en consultas SELECT sobre la base de datos de una farmacia.\n\n"
                . "REGLAS ESTRICTAS:\n"
                . "- SOLO SELECT (o WITH...SELECT). NUNCA INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE, CREATE, GRANT.\n"
                . "- Un solo statement por respuesta, sin ; final.\n"
                . "- Usa solo las tablas del esquema. NUNCA inventes columnas ni tablas. Si una columna no aparece "
                . "textualmente en el esquema listado abajo, NO existe, aunque suene logica.\n"
                . "- CASO CRITICO: para preguntas de vencimiento/caducidad/vencidos NO uses articulo.fecha_vencimiento "
                . "(esa columna NO existe). Usa lote_articulo (alias 'la') con JOIN a articulo. Ejemplos:\n"
                . "    * 'que productos ya vencieron' -> SELECT a.nombre, la.numero_lote, la.fecha_vencimiento, la.cantidad_actual "
                . "FROM lote_articulo la JOIN articulo a ON a.idarticulo=la.idarticulo "
                . "WHERE la.condicion=1 AND la.cantidad_actual>0 AND la.fecha_vencimiento < CURDATE() "
                . "ORDER BY la.fecha_vencimiento ASC LIMIT 100\n"
                . "    * 'que vence este mes' -> ... WHERE la.fecha_vencimiento BETWEEN CURDATE() AND LAST_DAY(CURDATE())\n"
                . "- Excluye ventas anuladas: agrega v.estado <> 'Anulada' cuando consultes ventas.\n"
                . "- Filtra articulos activos con condicion=1 cuando aplique.\n"
                . "- Usa alias claros (v, dv, a, c, p, u, i, di, la=lote_articulo).\n"
                . "- Fechas: usa CURDATE(), DATE_SUB, DATE_FORMAT segun necesites.\n"
                . "- Si aplica, agrega LIMIT razonable (max 500).\n"
                . "- Si el usuario pide algo destructivo o fuera de solo lectura, responde con sql=null.\n\n"
                . "MEMORIA DE CONVERSACION:\n"
                . "- El historial previo (mensajes anteriores) es CONTEXTO real. Si el usuario dice 'esos', "
                . "'los anteriores', 'muestrame esos productos', DEBES reinterpretar usando la ULTIMA consulta SQL "
                . "que hiciste. Ejemplo: si antes contaste productos con stock<10 con COUNT(*), y ahora dicen "
                . "'muestrame esos 2 productos', devuelve la MISMA condicion pero con SELECT de las columnas "
                . "relevantes (nombre, stock, categoria) en vez del COUNT.\n"
                . "- Solo devuelve sql=null si la pregunta es genuinamente ambigua incluso con contexto.\n\n"
                . $schema
                . "\nRESPONDE SOLO EN JSON: {\"sql\": \"SELECT ...\", \"explicacion\": \"...\"}";

        $messages = [['role' => 'system', 'content' => $system]];

        // Inyectar historial como turnos de conversacion (mas natural para el modelo)
        foreach ($contexto as $turn) {
            $messages[] = ['role' => 'user', 'content' => $turn['pregunta']];
            $asistente = "SQL usado: " . $turn['sql_generado'] . "\nRespuesta dada: " . $turn['respuesta'];
            $messages[] = ['role' => 'assistant', 'content' => $asistente];
        }

        $messages[] = ['role' => 'user', 'content' => $pregunta];

        $svc = new AIService();
        $r = $svc->chat($messages, [
            'temperature'     => 0.1,
            'max_tokens'      => 600,
            'response_format' => ['type' => 'json_object'],
        ]);

        $decoded = json_decode($r['contenido'], true);
        if (!$decoded || empty($decoded['sql']) || strtolower(trim($decoded['sql'])) === 'null') {
            $motivo = $decoded['explicacion'] ?? 'sin detalle';
            throw new RuntimeException("No pude traducir la pregunta a una consulta segura. Intenta reformularla. ($motivo)");
        }

        return [
            'sql'    => $decoded['sql'],
            'tokens' => $r['tokens_prompt'] + $r['tokens_respuesta'],
        ];
    }

    /**
     * Historial de la conversacion actual, ordenado cronologicamente.
     */
    private function contextoConversacion($idconv, $limit = 8)
    {
        if (!$idconv) return [];
        global $conexion;
        $idconv = $conexion->real_escape_string($idconv);
        $limit  = (int)$limit;

        $sql = "SELECT pregunta, sql_generado, respuesta
                FROM ai_chat_log
                WHERE idconv='$idconv' AND status='ok' AND sql_generado IS NOT NULL
                ORDER BY idchat DESC
                LIMIT $limit";
        $rs = ejecutarConsulta($sql);
        $out = [];
        if ($rs) while ($r = $rs->fetch_assoc()) $out[] = $r;
        return array_reverse($out);
    }

    // ----- Paso 2: validar SQL -----

    public function validarSQL($sql)
    {
        $sql = trim($sql);
        $sql = rtrim($sql, ';');

        // Solo un statement
        if (strpos($sql, ';') !== false) {
            throw new RuntimeException("Consulta rechazada: mas de un statement.");
        }

        // Debe empezar con SELECT o WITH
        if (!preg_match('/^\s*(SELECT|WITH)\s+/i', $sql)) {
            throw new RuntimeException("Consulta rechazada: solo se permiten SELECT.");
        }

        // Blacklist de keywords
        foreach ($this->blacklist as $kw) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $sql)) {
                throw new RuntimeException("Consulta rechazada: contiene la palabra prohibida '$kw'.");
            }
        }

        // Whitelist de tablas: extraer todas las palabras que sigan a FROM/JOIN y validar
        $tablasPermitidas = (new DBSchema())->tablas();
        preg_match_all('/\b(FROM|JOIN)\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?/i', $sql, $matches);
        foreach ($matches[2] as $tabla) {
            if (!in_array($tabla, $tablasPermitidas, true)) {
                throw new RuntimeException("Consulta rechazada: la tabla '$tabla' no esta permitida.");
            }
        }

        // Ninguna tabla fuera de la whitelist puede aparecer en NINGUNA parte del SQL
        // (cierra joins con coma: "FROM articulo, ai_config" y referencias a otros esquemas).
        if (preg_match('/\b(information_schema|performance_schema|mysql|sys)\s*\./i', $sql)) {
            throw new RuntimeException("Consulta rechazada: no se permite consultar tablas del sistema.");
        }
        $rsTablas = ejecutarConsulta("SHOW TABLES");
        if ($rsTablas) {
            while ($fila = $rsTablas->fetch_row()) {
                $t = $fila[0];
                if (!in_array($t, $tablasPermitidas, true)
                    && preg_match('/(?<![a-zA-Z0-9_])`?' . preg_quote($t, '/') . '`?(?![a-zA-Z0-9_])/i', $sql)) {
                    throw new RuntimeException("Consulta rechazada: la tabla '$t' no esta permitida.");
                }
            }
        }

        // Forzar LIMIT
        if (!preg_match('/\bLIMIT\s+\d+/i', $sql)) {
            $sql .= " LIMIT {$this->limitMax}";
        } else {
            // Si trae LIMIT muy grande, forzar el max
            $sql = preg_replace_callback('/\bLIMIT\s+(\d+)/i', function ($m) {
                $n = min((int)$m[1], $this->limitMax);
                return "LIMIT $n";
            }, $sql);
        }

        return $sql;
    }

    // ----- Paso 3: ejecutar SQL -----

    private function ejecutarSQL($sql)
    {
        global $conexion;

        // Timeout tolerante: MySQL usa max_execution_time (ms), MariaDB usa max_statement_time (seg).
        // Envolvemos en try/catch porque una de las dos no existe segun el motor y PHP 8+ tira excepcion.
        try { $conexion->query("SET SESSION max_execution_time=10000"); } catch (Throwable $e) {}
        try { $conexion->query("SET SESSION max_statement_time=10");    } catch (Throwable $e) {}

        $rs = $conexion->query($sql);
        if ($rs === false) {
            throw new RuntimeException("Error SQL: " . $conexion->error);
        }
        $filas = [];
        if ($rs instanceof mysqli_result) {
            while ($r = $rs->fetch_assoc()) $filas[] = $r;
            $rs->free();
        }
        return $filas;
    }

    // ----- Paso 4: formatear respuesta -----

    private function formatearRespuesta($pregunta, $sql, $filas)
    {
        $simbolo = obtenerSimboloMoneda();
        $numFilas = count($filas);

        // Si son muchas filas, cortamos para el contexto pero avisamos a la IA
        $muestra = $numFilas > 30 ? array_slice($filas, 0, 30) : $filas;

        $system = "Eres un asistente de negocios de una farmacia. Recibes: la pregunta del usuario, "
                . "el SQL ejecutado y los resultados en JSON. Debes redactar una respuesta breve, "
                . "clara y en espanol, para un dueno de farmacia (no tecnica). "
                . "Reglas:\n"
                . "- Empieza con la respuesta directa a la pregunta.\n"
                . "- Si hay numeros de dinero, usalos con el simbolo $simbolo.\n"
                . "- Si hay una lista con varias filas, resumela en 1-2 frases y menciona el top.\n"
                . "- Si el resultado esta vacio, dilo con naturalidad ('no hay registros').\n"
                . "- No inventes datos que no vienen en las filas.\n"
                . "- Maximo 4 frases. Nada de markdown ni HTML.";

        $user = "Pregunta original: $pregunta\n\n"
              . "SQL ejecutado:\n$sql\n\n"
              . "Total filas: $numFilas" . ($numFilas > 30 ? " (mostrando las primeras 30)" : "") . "\n\n"
              . "Resultados (JSON):\n" . json_encode($muestra, JSON_UNESCAPED_UNICODE);

        $svc = new AIService();
        $r = $svc->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ], ['temperature' => 0.4, 'max_tokens' => 400]);

        return [
            'texto'  => trim($r['contenido']),
            'tokens' => $r['tokens_prompt'] + $r['tokens_respuesta'],
        ];
    }

    // ----- Log e historial -----

    private function guardarLog($idusuario, $idconv, $pregunta, $sql, $respuesta, $tp, $tr, $status, $error)
    {
        global $conexion;
        $idu = $idusuario ? (int)$idusuario : 'NULL';
        $ic  = $conexion->real_escape_string($idconv);
        $pr  = $conexion->real_escape_string($pregunta);
        $sq  = $sql       ? "'" . $conexion->real_escape_string($sql) . "'" : "NULL";
        $rp  = $respuesta ? "'" . $conexion->real_escape_string($respuesta) . "'" : "NULL";
        $tp  = (int)$tp;
        $tr  = (int)$tr;
        $st  = $conexion->real_escape_string($status);
        $er  = $error ? "'" . $conexion->real_escape_string($error) . "'" : "NULL";

        // Titulo: primera pregunta de la conversacion (primeros 120 chars).
        // try/catch por si la columna no existe (migracion no aplicada).
        $tit = 'NULL';
        try {
            $tituloExist = ejecutarConsultaSimpleFila("SELECT titulo FROM ai_chat_log WHERE idconv='$ic' AND titulo IS NOT NULL LIMIT 1");
            if (!$tituloExist) {
                $t = substr($pregunta, 0, 120);
                $tit = "'" . $conexion->real_escape_string($t) . "'";
            }
        } catch (Throwable $e) {
            throw new RuntimeException("Falta aplicar la migracion 20260817_ai_chat_conversacion.sql en la BD farmacia2. Error: " . $e->getMessage());
        }

        $sqlIns = "INSERT INTO ai_chat_log
                   (idusuario, idconv, titulo, pregunta, sql_generado, respuesta, tokens_prompt, tokens_respuesta, status, error)
                   VALUES ($idu, '$ic', $tit, '$pr', $sq, $rp, $tp, $tr, '$st', $er)";
        ejecutarConsulta($sqlIns);
    }

    /**
     * Lista conversaciones (agrupadas) del usuario, con ultima actividad y titulo.
     */
    public function listarConversaciones($idusuario = null, $limit = 50)
    {
        $limit = (int)$limit;
        $where = '';
        if ($idusuario) {
            $idusuario = (int)$idusuario;
            $where = "WHERE idusuario=$idusuario";
        }
        $sql = "SELECT idconv,
                       MAX(titulo) AS titulo,
                       COUNT(*) AS num_mensajes,
                       MAX(creado_en) AS ultima_actividad
                FROM ai_chat_log
                $where
                GROUP BY idconv
                ORDER BY ultima_actividad DESC
                LIMIT $limit";
        return ejecutarConsulta($sql);
    }

    /**
     * Trae todos los mensajes de una conversacion.
     */
    public function cargarConversacion($idconv, $idusuario = null)
    {
        global $conexion;
        $idconv = $conexion->real_escape_string($idconv);
        $filtroUsr = $idusuario ? "AND idusuario=" . (int)$idusuario : "";
        $sql = "SELECT idchat, pregunta, respuesta, sql_generado, status, error, creado_en
                FROM ai_chat_log
                WHERE idconv='$idconv' $filtroUsr
                ORDER BY idchat ASC";
        return ejecutarConsulta($sql);
    }

    public function eliminarConversacion($idconv, $idusuario = null)
    {
        global $conexion;
        $idconv = $conexion->real_escape_string($idconv);
        $filtroUsr = $idusuario ? "AND idusuario=" . (int)$idusuario : "";
        return ejecutarConsulta("DELETE FROM ai_chat_log WHERE idconv='$idconv' $filtroUsr");
    }
}
