<?php
// ============================================================
// Presentaciones de venta de un producto (Tableta, Blíster x10, Caja x100...)
// ============================================================
// - articulo.stock y los lotes están SIEMPRE en la unidad base (factor 1).
// - La presentación base es la unidad del artículo (articulo.idunidad) y su
//   precio es articulo.precio_venta; el resto tiene su propio precio.
// - Stock que mueve una línea de venta/compra = cantidad * factor.
// Ver migrations/20261108_presentaciones_venta.sql
require_once __DIR__ . "/../config/Conexion.php";

class Presentacion {

	/** ¿Ya se ejecutó la migración? (permite que el sistema siga funcionando sin ella) */
	public static function disponible() {
		static $ok = null;
		if ($ok === null) {
			$r = ejecutarConsultaSimpleFila("SELECT COUNT(*) AS n FROM information_schema.TABLES
				WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='articulo_presentacion'");
			$ok = $r && (int)$r['n'] > 0;
		}
		return $ok;
	}

	/**
	 * Para consultas de detalle (venta/compra): unidad de la presentación vendida.
	 * Devuelve [expresión SELECT de la abreviatura, JOINs]. $d = alias del detalle, $u = alias de la unidad del artículo.
	 */
	public static function sqlUnidadDetalle($d = 'd', $u = 'u') {
		if (!self::disponible()) {
			return array("IFNULL($u.abreviatura,'und')", "");
		}
		return array(
			"IFNULL(pu_$d.abreviatura, IFNULL($u.abreviatura,'und'))",
			" LEFT JOIN articulo_presentacion ap_$d ON ap_$d.idpresentacion=$d.idpresentacion
			  LEFT JOIN unidad_medida pu_$d ON pu_$d.idunidad=ap_$d.idunidad "
		);
	}

	/** Expresión de cantidad en unidades base de una línea de detalle */
	public static function sqlCantidadBase($d = 'd') {
		return self::disponible() ? "($d.cantidad*$d.factor)" : "$d.cantidad";
	}

	/** Precio con la oferta vigente del artículo aplicada (misma regla que sqlPrecioFinalExpr) */
	private static function precioConOferta($precio, $vigente, $pct) {
		$precio = (float)$precio;
		return ($vigente && $pct > 0) ? round($precio * (1 - $pct / 100), 2) : round($precio, 2);
	}

	/**
	 * Presentaciones activas de varios artículos: [idarticulo => [pres, ...]] (base primero).
	 * Cada pres: id, idunidad, unidad, abrev, factor, precio (con oferta), precio_lista, codigo, es_base
	 */
	public function mapaPorArticulos(array $ids) {
		$ids = array_values(array_filter(array_map('intval', $ids)));
		$out = array();
		if (!$ids) return $out;
		$lista = implode(',', $ids);
		$precioBase = sqlPrecioBaseExpr('a');
		$vigente    = sqlOfertaVigenteExpr('a');

		if (!self::disponible()) {
			$rs = ejecutarConsulta("SELECT a.idarticulo, a.idunidad, u.nombre, u.abreviatura, $precioBase AS precio,
				$vigente AS vigente, a.descuento_porcentaje
				FROM articulo a LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad WHERE a.idarticulo IN ($lista)");
			while ($rs && $r = $rs->fetch_assoc()) {
				$out[(int)$r['idarticulo']] = array(array(
					'id' => 0, 'idunidad' => (int)$r['idunidad'], 'unidad' => $r['nombre'], 'abrev' => $r['abreviatura'] ?: 'und',
					'factor' => 1, 'precio' => self::precioConOferta($r['precio'], $r['vigente'], (float)$r['descuento_porcentaje']),
					'precio_lista' => (float)$r['precio'], 'codigo' => '', 'es_base' => 1));
			}
			return $out;
		}

		$sql = "SELECT p.idpresentacion, p.idarticulo, p.idunidad, p.factor, p.es_base, IFNULL(p.codigo,'') AS codigo,
				u.nombre AS unidad, u.abreviatura,
				IF(p.es_base=1, $precioBase, p.precio_venta) AS precio,
				$vigente AS vigente, a.descuento_porcentaje
			FROM articulo_presentacion p
			INNER JOIN articulo a ON a.idarticulo=p.idarticulo
			LEFT JOIN unidad_medida u ON u.idunidad=p.idunidad
			WHERE p.idarticulo IN ($lista) AND p.condicion=1
			ORDER BY p.idarticulo, p.factor ASC";
		$rs = ejecutarConsulta($sql);
		while ($rs && $r = $rs->fetch_assoc()) {
			$out[(int)$r['idarticulo']][] = array(
				'id'           => (int)$r['idpresentacion'],
				'idunidad'     => (int)$r['idunidad'],
				'unidad'       => $r['unidad'],
				'abrev'        => $r['abreviatura'] ?: 'und',
				'factor'       => (float)$r['factor'],
				'precio'       => self::precioConOferta($r['precio'], $r['vigente'], (float)$r['descuento_porcentaje']),
				'precio_lista' => round((float)$r['precio'], 2),
				'codigo'       => $r['codigo'],
				'es_base'      => (int)$r['es_base'],
			);
		}
		return $out;
	}

	/** Texto para el cliente: "Tableta", "Blíster x10", "Caja x100" */
	public static function etiqueta($p) {
		$nom = !empty($p['unidad']) ? $p['unidad'] : (!empty($p['abrev']) ? $p['abrev'] : 'Unidad');
		return $nom . ((float)$p['factor'] > 1 ? ' x' . (0 + (float)$p['factor']) : '');
	}

	/**
	 * Presentación activa elegida (con precio vigente) dentro de una lista de mapaPorArticulos.
	 * $idpresentacion <= 0 → la base. Devuelve null si no existe o ya no está activa.
	 */
	public static function elegirDeLista($lista, $idpresentacion) {
		$idpresentacion = (int)$idpresentacion;
		foreach ((array)$lista as $p) {
			if ($idpresentacion > 0 ? ((int)$p['id'] === $idpresentacion) : ((int)$p['es_base'] === 1)) return $p;
		}
		return ($idpresentacion <= 0 && !empty($lista)) ? $lista[0] : null;
	}

	public function listarPorArticulo($idarticulo) {
		$m = $this->mapaPorArticulos(array((int)$idarticulo));
		return isset($m[(int)$idarticulo]) ? $m[(int)$idarticulo] : array();
	}

	/**
	 * Resuelve la presentación con la que se vende/compra una línea.
	 * $idpresentacion <= 0 → presentación base. Devuelve null si no pertenece al artículo.
	 * Retorna: idpresentacion (o null sin migración), factor, abrev, unidad, es_base
	 */
	public function resolver($idarticulo, $idpresentacion) {
		$idarticulo = (int)$idarticulo;
		$idpresentacion = (int)$idpresentacion;
		if (!self::disponible()) {
			return array('idpresentacion' => null, 'factor' => 1.0, 'abrev' => '', 'unidad' => '', 'es_base' => 1);
		}
		$cond = $idpresentacion > 0 ? "p.idpresentacion='$idpresentacion'" : "p.es_base=1";
		$r = ejecutarConsultaSimpleFila("SELECT p.idpresentacion, p.factor, p.es_base, p.condicion, u.abreviatura, u.nombre
			FROM articulo_presentacion p LEFT JOIN unidad_medida u ON u.idunidad=p.idunidad
			WHERE p.idarticulo='$idarticulo' AND $cond LIMIT 1");
		if (!$r && $idpresentacion <= 0) {
			// artículo creado sin presentación base (p. ej. antes de la migración)
			$this->asegurarBase($idarticulo);
			return $this->resolver($idarticulo, 0);
		}
		if (!$r) return null;
		return array(
			'idpresentacion' => (int)$r['idpresentacion'],
			'factor'         => max(1.0, (float)$r['factor']),
			'abrev'          => $r['abreviatura'],
			'unidad'         => $r['nombre'],
			'es_base'        => (int)$r['es_base'],
			'activa'         => (int)$r['condicion'] === 1,
		);
	}

	/** Crea o alinea la presentación base con la unidad y el precio del artículo */
	public function asegurarBase($idarticulo) {
		if (!self::disponible()) return;
		$idarticulo = (int)$idarticulo;
		$a = ejecutarConsultaSimpleFila("SELECT idunidad, precio_venta FROM articulo WHERE idarticulo='$idarticulo' LIMIT 1");
		if (!$a) return;
		$idunidad = (int)$a['idunidad'];
		$precio   = (float)$a['precio_venta'];
		$base = ejecutarConsultaSimpleFila("SELECT idpresentacion, idunidad FROM articulo_presentacion WHERE idarticulo='$idarticulo' AND es_base=1 LIMIT 1");
		if ($base) {
			ejecutarConsulta("UPDATE articulo_presentacion SET idunidad='$idunidad', precio_venta='$precio', factor=1, condicion=1
				WHERE idpresentacion='".(int)$base['idpresentacion']."'");
			return;
		}
		$otra = ejecutarConsultaSimpleFila("SELECT idpresentacion FROM articulo_presentacion WHERE idarticulo='$idarticulo' AND idunidad='$idunidad' LIMIT 1");
		if ($otra) {
			ejecutarConsulta("UPDATE articulo_presentacion SET es_base=1, factor=1, precio_venta='$precio', condicion=1
				WHERE idpresentacion='".(int)$otra['idpresentacion']."'");
		} else {
			ejecutarConsulta("INSERT INTO articulo_presentacion (idarticulo, idunidad, factor, precio_venta, es_base, condicion)
				VALUES ('$idarticulo', '$idunidad', 1, '$precio', 1, 1)");
		}
	}

	/** ¿El código de barras ya lo usa otro producto o presentación? */
	private function codigoOcupado($codigo, $idarticulo) {
		$c = limpiarCadena($codigo);
		$idarticulo = (int)$idarticulo;
		$a = ejecutarConsultaSimpleFila("SELECT nombre FROM articulo WHERE codigo='$c' AND idarticulo<>'$idarticulo' AND condicion<>2 LIMIT 1");
		if ($a) return $a['nombre'];
		$p = ejecutarConsultaSimpleFila("SELECT a.nombre FROM articulo_presentacion p INNER JOIN articulo a ON a.idarticulo=p.idarticulo
			WHERE p.codigo='$c' AND p.idarticulo<>'$idarticulo' AND p.condicion=1 LIMIT 1");
		return $p ? $p['nombre'] : '';
	}

	/** Texto en minúsculas y sin tildes, para comparar nombres de unidades y formas. */
	private static function normalizar($texto) {
		$t = mb_strtolower(trim((string)$texto), 'UTF-8');
		return strtr($t, array('á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n'));
	}

	/**
	 * Familia de una unidad: 'solido' (tabletas, cápsulas y su blíster), 'liquido' (ampollas, viales,
	 * frascos y medidas de volumen) o '' si sirve para cualquiera (caja, unidad, paquete, sobre...).
	 */
	public static function familiaUnidad($nombre) {
		$n = self::normalizar($nombre);
		foreach (array('tableta', 'capsula', 'blister', 'gragea', 'comprimido', 'ovulo') as $p) {
			if (strpos($n, $p) !== false) return 'solido';
		}
		foreach (array('ampolla', 'vial', 'frasco', 'gota', 'mililitro', 'litro', 'botella', 'jarabe') as $p) {
			if (strpos($n, $p) !== false) return 'liquido';
		}
		return '';
	}

	/** Familia según la forma farmacéutica del producto, o '' si no se reconoce (crema, dispositivo...). */
	public static function familiaForma($forma) {
		$f = self::normalizar($forma);
		foreach (array('tableta', 'capsula', 'comprimido', 'gragea', 'ovulo') as $p) {
			if (strpos($f, $p) !== false) return 'solido';
		}
		foreach (array('inyectable', 'solucion', 'jarabe', 'suspension', 'gota', 'elixir', 'emulsion', 'ampolla', 'vial') as $p) {
			if (strpos($f, $p) !== false) return 'liquido';
		}
		return '';
	}

	/** «1 ampolla», «10 ampollas», «2 tabletas», «5 blísteres»: cantidad con la unidad en palabras. */
	private static function cantidadUnidad($n, $nombre) {
		$n = (int)$n;
		$p = mb_strtolower(trim((string)$nombre), 'UTF-8');
		if ($n === 1) return "1 $p";
		return $n . ' ' . (preg_match('/[aeiouáéíóú]$/u', $p) ? $p . 's' : $p . 'es');
	}

	/**
	 * Revisa que las presentaciones tengan sentido antes de guardar nada:
	 *  1. La unidad corresponde a la forma del producto (un inyectable no va en blíster; una tableta no va en ampolla).
	 *  2. Tabletas y ampollas no se mezclan en un mismo producto: son productos distintos.
	 *  3. Los precios van en orden: un paquete cuesta más que una unidad suelta, no más que sus unidades sueltas,
	 *     y uno más grande cuesta más que uno más chico.
	 * Devuelve el primer problema encontrado, o '' si todo está bien.
	 */
	public function validar($idunidadBase, $precioBase, $forma, $items) {
		$idunidadBase = (int)$idunidadBase;
		$precioBase = round((float)$precioBase, 2);
		$base = ejecutarConsultaSimpleFila("SELECT nombre FROM unidad_medida WHERE idunidad='$idunidadBase' LIMIT 1");
		$nomBase = $base ? $base['nombre'] : 'unidad';
		$unidades = array(array('nombre' => $nomBase, 'factor' => 1, 'precio' => $precioBase));
		foreach ((array)$items as $it) {
			$idu = isset($it['idunidad']) ? (int)$it['idunidad'] : 0;
			if ($idu <= 0) continue;
			$u = ejecutarConsultaSimpleFila("SELECT nombre FROM unidad_medida WHERE idunidad='$idu' LIMIT 1");
			if (!$u) continue; // sincronizar() avisa de la unidad inexistente
			$unidades[] = array('nombre' => $u['nombre'], 'factor' => isset($it['factor']) ? (float)$it['factor'] : 0,
				'precio' => isset($it['precio']) ? round((float)$it['precio'], 2) : 0);
		}

		// 1. Unidad según la forma farmacéutica
		$famForma = self::familiaForma($forma);
		if ($famForma !== '') {
			foreach ($unidades as $u) {
				$famU = self::familiaUnidad($u['nombre']);
				if ($famU !== '' && $famU !== $famForma) {
					return $famForma === 'liquido'
						? "Un producto «" . trim($forma) . "» no se vende en «{$u['nombre']}»: esa presentación es para tabletas o cápsulas. Quítela, o registre las tabletas como otro producto."
						: "Un producto «" . trim($forma) . "» no se vende en «{$u['nombre']}»: esa presentación es para inyectables o líquidos. Quítela, o registre el inyectable como otro producto.";
				}
			}
		}

		// 2. No mezclar tabletas y ampollas en un mismo producto
		$porFamilia = array();
		foreach ($unidades as $u) {
			$famU = self::familiaUnidad($u['nombre']);
			if ($famU !== '' && !isset($porFamilia[$famU])) $porFamilia[$famU] = $u['nombre'];
		}
		if (count($porFamilia) > 1) {
			return "«{$porFamilia['solido']}» y «{$porFamilia['liquido']}» no pueden ser presentaciones del mismo producto. Las tabletas o cápsulas y los inyectables o líquidos son productos distintos: regístrelos por separado, cada uno con su unidad base.";
		}

		// 3. Precios en orden (si el producto tiene precio; sin precio se vende «a consultar»)
		if ($precioBase > 0) {
			foreach ($unidades as $i => $u) {
				if ($i === 0 || $u['factor'] < 2 || $u['precio'] <= 0) continue;
				if ($u['precio'] <= $precioBase) {
					return "«{$u['nombre']}» trae " . self::cantidadUnidad($u['factor'], $nomBase) . " y cuesta S/ " . number_format($u['precio'], 2)
						. ", lo mismo o menos que comprar " . self::cantidadUnidad(1, $nomBase) . " (S/ " . number_format($precioBase, 2) . "). Revise el precio.";
				}
				$sueltas = round($precioBase * $u['factor'], 2);
				if ($u['precio'] > $sueltas) {
					return "«{$u['nombre']}» cuesta S/ " . number_format($u['precio'], 2) . ", más que comprar sus " . self::cantidadUnidad($u['factor'], $nomBase)
						. " por separado (S/ " . number_format($sueltas, 2) . "). Un paquete no debería salir más caro. Revise el precio.";
				}
			}
			$paquetes = array_slice($unidades, 1);
			usort($paquetes, function ($a, $b) { return $a['factor'] <=> $b['factor']; });
			for ($i = 1; $i < count($paquetes); $i++) {
				$chico = $paquetes[$i - 1];
				$grande = $paquetes[$i];
				if ($grande['factor'] > $chico['factor'] && $grande['precio'] > 0 && $grande['precio'] <= $chico['precio']) {
					return "«{$grande['nombre']}» (" . self::cantidadUnidad($grande['factor'], $nomBase) . ") cuesta S/ " . number_format($grande['precio'], 2)
						. ", lo mismo o menos que «{$chico['nombre']}» (" . self::cantidadUnidad($chico['factor'], $nomBase) . ", S/ " . number_format($chico['precio'], 2) . "). Revise los precios.";
				}
			}
		}
		return '';
	}

	/**
	 * Guarda las presentaciones adicionales del formulario de producto.
	 * $items: [{idunidad, factor, precio, codigo}] (sin la base, que sale del artículo).
	 * Las que se quitan: se borran si no tienen historial, si no se desactivan.
	 */
	public function sincronizar($idarticulo, $items) {
		if (!self::disponible()) {
			return array('ok' => true, 'message' => '');
		}
		$idarticulo = (int)$idarticulo;
		$this->asegurarBase($idarticulo);
		$art = ejecutarConsultaSimpleFila("SELECT idunidad, codigo, precio_venta, forma_farmaceutica FROM articulo WHERE idarticulo='$idarticulo' LIMIT 1");
		if (!$art) return array('ok' => false, 'message' => 'El producto no existe');
		$idunidadBase = (int)$art['idunidad'];
		// Segunda barrera: el formulario ya se validó antes de guardar el producto (ajax/articulo.php).
		$problema = $this->validar($idunidadBase, $art['precio_venta'], $art['forma_farmaceutica'], $items);
		if ($problema !== '') return array('ok' => false, 'message' => $problema);

		$limpios = array();
		$vistos = array($idunidadBase => true);
		$codigos = array();
		foreach ((array)$items as $it) {
			$idu    = isset($it['idunidad']) ? (int)$it['idunidad'] : 0;
			$factor = isset($it['factor']) ? (float)$it['factor'] : 0;
			$precio = isset($it['precio']) ? round((float)$it['precio'], 2) : 0;
			$codigo = isset($it['codigo']) ? trim((string)$it['codigo']) : '';
			if ($idu <= 0) continue;
			$u = ejecutarConsultaSimpleFila("SELECT nombre FROM unidad_medida WHERE idunidad='$idu' LIMIT 1");
			if (!$u) return array('ok' => false, 'message' => 'Una de las presentaciones tiene una unidad que no existe');
			$nom = $u['nombre'];
			if (isset($vistos[$idu])) {
				return array('ok' => false, 'message' => ($idu === $idunidadBase)
					? "\"$nom\" ya es la unidad base del producto. Elige otra unidad para la presentación"
					: "La presentación \"$nom\" está repetida");
			}
			if ($factor < 2 || floor($factor) != $factor) {
				return array('ok' => false, 'message' => "Indica cuántas unidades base trae 1 \"$nom\" (un número entero desde 2)");
			}
			if ($precio <= 0) {
				return array('ok' => false, 'message' => "Falta el precio de venta de la presentación \"$nom\"");
			}
			if ($codigo !== '') {
				if ($codigo === (string)$art['codigo'] || isset($codigos[$codigo])) {
					return array('ok' => false, 'message' => "El código de barras $codigo está repetido en este producto");
				}
				$ocupado = $this->codigoOcupado($codigo, $idarticulo);
				if ($ocupado !== '') {
					return array('ok' => false, 'message' => "El código de barras $codigo ya lo usa el producto \"$ocupado\"");
				}
				$codigos[$codigo] = true;
			}
			$vistos[$idu] = true;
			$limpios[] = array('idunidad' => $idu, 'factor' => $factor, 'precio' => $precio, 'codigo' => $codigo);
		}

		// Desactivar / borrar las que ya no están
		$rs = ejecutarConsulta("SELECT idpresentacion, idunidad FROM articulo_presentacion WHERE idarticulo='$idarticulo' AND es_base=0");
		while ($rs && $r = $rs->fetch_assoc()) {
			if (isset($vistos[(int)$r['idunidad']])) continue;
			$idp = (int)$r['idpresentacion'];
			$uso = ejecutarConsultaSimpleFila("SELECT
				(SELECT COUNT(*) FROM detalle_venta WHERE idpresentacion='$idp') +
				(SELECT COUNT(*) FROM detalle_ingreso WHERE idpresentacion='$idp') AS n");
			if ($uso && (int)$uso['n'] > 0) {
				ejecutarConsulta("UPDATE articulo_presentacion SET condicion=0 WHERE idpresentacion='$idp'");
			} else {
				ejecutarConsulta("DELETE FROM articulo_presentacion WHERE idpresentacion='$idp'");
			}
		}

		// Insertar / actualizar las del formulario
		foreach ($limpios as $p) {
			$codSql = $p['codigo'] !== '' ? "'".limpiarCadena($p['codigo'])."'" : 'NULL';
			$ex = ejecutarConsultaSimpleFila("SELECT idpresentacion FROM articulo_presentacion
				WHERE idarticulo='$idarticulo' AND idunidad='{$p['idunidad']}' LIMIT 1");
			if ($ex) {
				ejecutarConsulta("UPDATE articulo_presentacion SET factor='{$p['factor']}', precio_venta='{$p['precio']}',
					codigo=$codSql, es_base=0, condicion=1 WHERE idpresentacion='".(int)$ex['idpresentacion']."'");
			} else {
				ejecutarConsulta("INSERT INTO articulo_presentacion (idarticulo, idunidad, factor, precio_venta, codigo, es_base, condicion)
					VALUES ('$idarticulo', '{$p['idunidad']}', '{$p['factor']}', '{$p['precio']}', $codSql, 0, 1)");
			}
		}
		return array('ok' => true, 'message' => '');
	}

	/** Precio fijado desde una compra para la presentación comprada */
	public function actualizarPrecio($idarticulo, $idpresentacion, $precio) {
		$precio = round((float)$precio, 2);
		if ($precio <= 0) return;
		$idarticulo = (int)$idarticulo;
		$idpresentacion = (int)$idpresentacion;
		if (!self::disponible() || $idpresentacion <= 0) {
			ejecutarConsulta("UPDATE articulo SET precio_venta='$precio' WHERE idarticulo='$idarticulo'");
			return;
		}
		$p = ejecutarConsultaSimpleFila("SELECT es_base FROM articulo_presentacion WHERE idpresentacion='$idpresentacion' AND idarticulo='$idarticulo' LIMIT 1");
		if (!$p) return;
		ejecutarConsulta("UPDATE articulo_presentacion SET precio_venta='$precio' WHERE idpresentacion='$idpresentacion'");
		if ((int)$p['es_base'] === 1) {
			ejecutarConsulta("UPDATE articulo SET precio_venta='$precio' WHERE idarticulo='$idarticulo'");
		}
	}

	/** Busca un código de barras de presentación: [idarticulo, idpresentacion] o null */
	public function buscarPorCodigo($codigo) {
		if (!self::disponible() || trim((string)$codigo) === '') return null;
		$c = limpiarCadena($codigo);
		$r = ejecutarConsultaSimpleFila("SELECT p.idarticulo, p.idpresentacion FROM articulo_presentacion p
			INNER JOIN articulo a ON a.idarticulo=p.idarticulo
			WHERE p.codigo='$c' AND p.condicion=1 AND a.condicion=1 LIMIT 1");
		return $r ? array((int)$r['idarticulo'], (int)$r['idpresentacion']) : null;
	}

	/**
	 * Pasa un producto que hoy se cuenta en una unidad grande (ej. CAJA) a una más
	 * pequeña (ej. TABLETA). Multiplica stock, lotes e historial por $n, la unidad
	 * anterior queda como presentación (Caja x$n) con su precio y su código de barras.
	 */
	public function convertirUnidadBase($idarticulo, $idunidadNueva, $n, $precioNuevaBase) {
		global $conexion;
		if (!self::disponible()) {
			return array('ok' => false, 'message' => 'Primero ejecuta la migración de presentaciones');
		}
		$idarticulo = (int)$idarticulo;
		$idunidadNueva = (int)$idunidadNueva;
		$n = (float)$n;
		$precioNuevaBase = round((float)$precioNuevaBase, 2);

		$art = ejecutarConsultaSimpleFila("SELECT a.idunidad, a.codigo, a.nombre, u.nombre AS unidad
			FROM articulo a LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad WHERE a.idarticulo='$idarticulo' LIMIT 1");
		if (!$art) return array('ok' => false, 'message' => 'El producto no existe');
		$un = ejecutarConsultaSimpleFila("SELECT nombre FROM unidad_medida WHERE idunidad='$idunidadNueva' LIMIT 1");
		if (!$un) return array('ok' => false, 'message' => 'Elige la unidad más pequeña en la que vas a vender');
		if ($idunidadNueva === (int)$art['idunidad']) {
			return array('ok' => false, 'message' => '"'.$un['nombre'].'" ya es la unidad base de este producto');
		}
		if ($n < 2 || floor($n) != $n) {
			return array('ok' => false, 'message' => 'Indica cuántas '.$un['nombre'].' trae 1 '.$art['unidad'].' (número entero desde 2)');
		}
		if ($precioNuevaBase <= 0) {
			return array('ok' => false, 'message' => 'Indica el precio de venta de 1 '.$un['nombre']);
		}
		$choque = ejecutarConsultaSimpleFila("SELECT idpresentacion, condicion FROM articulo_presentacion
			WHERE idarticulo='$idarticulo' AND idunidad='$idunidadNueva' LIMIT 1");
		if ($choque && (int)$choque['condicion'] === 1) {
			return array('ok' => false, 'message' => 'Este producto ya tiene la presentación "'.$un['nombre'].'". Quítala primero de las presentaciones');
		}
		$this->asegurarBase($idarticulo);
		$base = ejecutarConsultaSimpleFila("SELECT idpresentacion FROM articulo_presentacion WHERE idarticulo='$idarticulo' AND es_base=1 LIMIT 1");
		$idBaseVieja = (int)$base['idpresentacion'];
		$codigoArt = limpiarCadena((string)$art['codigo']);

		$conexion->autocommit(false);
		try {
			ejecutarConsulta("SELECT idarticulo FROM articulo WHERE idarticulo='$idarticulo' FOR UPDATE");
			ejecutarConsulta("UPDATE detalle_venta   SET idpresentacion='$idBaseVieja' WHERE idarticulo='$idarticulo' AND idpresentacion IS NULL");
			ejecutarConsulta("UPDATE detalle_ingreso SET idpresentacion='$idBaseVieja' WHERE idarticulo='$idarticulo' AND idpresentacion IS NULL");
			ejecutarConsulta("UPDATE detalle_venta   SET factor = factor * $n WHERE idarticulo='$idarticulo'");
			ejecutarConsulta("UPDATE detalle_ingreso SET factor = factor * $n WHERE idarticulo='$idarticulo'");
			ejecutarConsulta("UPDATE lote_articulo SET cantidad_inicial = cantidad_inicial * $n, cantidad_actual = cantidad_actual * $n WHERE idarticulo='$idarticulo'");
			ejecutarConsulta("UPDATE control_especial SET cantidad = cantidad * $n WHERE idarticulo='$idarticulo'");
			ejecutarConsulta("UPDATE articulo_presentacion SET factor = factor * $n, es_base=0 WHERE idarticulo='$idarticulo'");
			// El código de barras del producto es el de la presentación grande (lo que llega del proveedor)
			if ($codigoArt !== '') {
				ejecutarConsulta("UPDATE articulo_presentacion SET codigo='$codigoArt' WHERE idpresentacion='$idBaseVieja' AND (codigo IS NULL OR codigo='')");
			}
			if ($choque) {
				ejecutarConsulta("UPDATE articulo_presentacion SET factor=1, es_base=1, condicion=1, precio_venta='$precioNuevaBase', codigo=NULL
					WHERE idpresentacion='".(int)$choque['idpresentacion']."'");
			} else {
				ejecutarConsulta("INSERT INTO articulo_presentacion (idarticulo, idunidad, factor, precio_venta, es_base, condicion)
					VALUES ('$idarticulo', '$idunidadNueva', 1, '$precioNuevaBase', 1, 1)");
			}
			$ok = ejecutarConsulta("UPDATE articulo SET stock = stock * $n, stock_minimo = stock_minimo * $n,
				idunidad='$idunidadNueva', precio_venta='$precioNuevaBase' WHERE idarticulo='$idarticulo'");
			if (!$ok) throw new Exception('No se pudo actualizar el producto');
			$conexion->commit();
			$conexion->autocommit(true);
		} catch (Throwable $e) {
			$conexion->rollback();
			$conexion->autocommit(true);
			return array('ok' => false, 'message' => 'No se pudo convertir el producto. No se cambió nada. ('.$e->getMessage().')');
		}
		$n0 = (int)$n;
		return array('ok' => true, 'message' => 'Listo: ahora '.$art['nombre'].' se cuenta en '.$un['nombre'].'. 1 '.$art['unidad'].' = '.$n0.' '.$un['nombre'].'.');
	}

	/** Texto amigable del stock: "250 TAB (2 CJ + 5 BLS)" */
	public static function desglosarStock($stockBase, $presentaciones) {
		$stock = (float)$stockBase;
		$base = null; $mayores = array();
		foreach ((array)$presentaciones as $p) {
			if ((int)$p['es_base'] === 1) $base = $p; else $mayores[] = $p;
		}
		$abrevBase = $base ? $base['abrev'] : 'und';
		$txt = rtrim(rtrim(number_format($stock, 2, '.', ''), '0'), '.') . ' ' . $abrevBase;
		if (!$mayores || $stock <= 0) return $txt;
		usort($mayores, function ($a, $b) { return $b['factor'] <=> $a['factor']; });
		$resto = $stock; $partes = array();
		foreach ($mayores as $p) {
			$q = floor($resto / $p['factor']);
			if ($q >= 1) { $partes[] = $q . ' ' . $p['abrev']; $resto -= $q * $p['factor']; }
		}
		if ($resto > 0 && $partes) $partes[] = rtrim(rtrim(number_format($resto, 2, '.', ''), '0'), '.') . ' ' . $abrevBase;
		return $partes ? $txt . ' (' . implode(' + ', $partes) . ')' : $txt;
	}
}
