# ANALISIS_FARMACIA.md — Brechas y Cambios Requeridos

## Diagnóstico general

El sistema base (ITVentas) es un POS genérico para comercios. Cubre correctamente:
- Control de inventario por unidades
- Flujo de compra/venta con comprobantes
- Gestión de proveedores y clientes
- Reportes básicos
- Caja diaria

**Veredicto:** El sistema SÍ puede funcionar como base para una farmacia, pero requiere cambios estructurales en BD, lógica de negocio y UI. No es solo un cambio de nombre. Hay funcionalidades que en farmacia son obligatorias por ley y que el sistema actual no tiene.

---

## BRECHAS CRÍTICAS (bloqueantes para operar como farmacia)

Estas brechas impiden operar legalmente o generan riesgo sanitario real.

---

### BRECHA 1 — Sin trazabilidad de lotes y vencimientos

**Problema:**
La tabla `articulo` no tiene campos de lote ni fecha de vencimiento. El sistema trata todos los medicamentos como unidades intercambiables. En farmacia, un mismo medicamento puede tener múltiples lotes con distintas fechas de vencimiento coexistiendo en el mismo almacén.

**Riesgo:**
- Vender medicamentos vencidos sin saberlo
- Imposibilidad de ejecutar un retiro de mercado (recall)
- Incumplimiento de normativa sanitaria (DIGEMID, INVIMA, ANMAT según país)

**Cambios requeridos en BD:**
```sql
-- Nueva tabla: lotes por artículo
CREATE TABLE lote_articulo (
    idlote           INT AUTO_INCREMENT PRIMARY KEY,
    idarticulo       INT NOT NULL,
    numero_lote      VARCHAR(50) NOT NULL,
    fecha_vencimiento DATE NOT NULL,
    fecha_fabricacion DATE,
    cantidad_inicial INT NOT NULL DEFAULT 0,
    cantidad_actual  INT NOT NULL DEFAULT 0,
    idingreso        INT,              -- de qué compra proviene
    condicion        TINYINT DEFAULT 1,
    FOREIGN KEY (idarticulo) REFERENCES articulo(idarticulo),
    FOREIGN KEY (idingreso)  REFERENCES ingreso(idingreso)
);

-- detalle_ingreso debe registrar el lote
ALTER TABLE detalle_ingreso ADD COLUMN idlote INT;
ALTER TABLE detalle_ingreso ADD FOREIGN KEY (idlote) REFERENCES lote_articulo(idlote);

-- detalle_venta debe registrar el lote despachado
ALTER TABLE detalle_venta ADD COLUMN idlote INT;
ALTER TABLE detalle_venta ADD FOREIGN KEY (idlote) REFERENCES lote_articulo(idlote);
```

**Cambios en lógica:**
- Al ingresar mercadería: registrar número de lote y fecha de vencimiento
- Al vender: seleccionar lote siguiendo política FEFO (primero el que vence antes)
- El `stock` de `articulo` debe calcularse sumando `cantidad_actual` de todos sus lotes activos, o mantenerse sincronizado
- Trigger `tr_updStockIngreso` debe actualizarse para crear/actualizar lote

**Módulo nuevo requerido:** Alerta de próximos vencimientos (30/60/90 días)

---

### BRECHA 2 — Sin gestión de receta médica

**Problema:**
En farmacia, los medicamentos se clasifican como OTC (sin receta) o Rx (con receta). Para los medicamentos Rx, la venta requiere presentar una receta médica válida. El sistema actual no distingue entre tipos de medicamentos ni solicita receta.

**Riesgo:**
- Venta ilegal de medicamentos con receta sin documento que lo respalde
- Imposibilidad de auditoría sanitaria
- Multas y cierre por parte del regulador

**Cambios requeridos en BD:**
```sql
-- Clasificación del medicamento
ALTER TABLE articulo ADD COLUMN tipo_venta ENUM('OTC', 'RX', 'CONTROL_ESPECIAL') DEFAULT 'OTC';

-- Tabla de recetas
CREATE TABLE receta_medica (
    idreceta         INT AUTO_INCREMENT PRIMARY KEY,
    idventa          INT,
    idcliente        INT,              -- persona que la presenta
    nombre_medico    VARCHAR(150),
    colegiatura      VARCHAR(50),      -- número de colegio médico
    establecimiento  VARCHAR(200),
    fecha_emision    DATE,
    tipo_receta      ENUM('SIMPLE', 'ESPECIAL', 'RETENIDA') DEFAULT 'SIMPLE',
    imagen_receta    VARCHAR(200),     -- foto/scan de la receta
    observaciones    TEXT,
    fecha_registro   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idventa)   REFERENCES venta(idventa),
    FOREIGN KEY (idcliente) REFERENCES persona(idpersona)
);
```

**Cambios en UI (venta.php):**
- Antes de confirmar una venta que contenga artículos `tipo_venta = 'RX'`, mostrar modal para registrar datos de receta
- Indicador visual en la lista de artículos (etiqueta OTC / Rx / Control)

---

### BRECHA 3 — Sin atributos farmacéuticos del medicamento

**Problema:**
La tabla `articulo` solo tiene `nombre`, `codigo`, `descripcion`. Un medicamento en farmacia requiere atributos específicos para búsqueda, dispensación correcta y cumplimiento regulatorio.

**Cambios requeridos en BD:**
```sql
-- Ampliar tabla articulo con atributos farmacéuticos
ALTER TABLE articulo ADD COLUMN principio_activo    VARCHAR(200);   -- DCI / nombre genérico
ALTER TABLE articulo ADD COLUMN concentracion       VARCHAR(100);   -- ej: "500 mg", "250 mg/5 ml"
ALTER TABLE articulo ADD COLUMN forma_farmaceutica  VARCHAR(100);   -- tableta, cápsula, jarabe, inyectable, crema, etc.
ALTER TABLE articulo ADD COLUMN via_administracion  VARCHAR(100);   -- oral, tópica, IV, IM, etc.
ALTER TABLE articulo ADD COLUMN laboratorio         VARCHAR(200);   -- fabricante
ALTER TABLE articulo ADD COLUMN registro_sanitario  VARCHAR(100);   -- número DIGEMID/INVIMA/ANMAT
ALTER TABLE articulo ADD COLUMN requiere_frio       TINYINT DEFAULT 0;  -- 1 = cadena de frío
ALTER TABLE articulo ADD COLUMN tipo_venta          ENUM('OTC','RX','CONTROL_ESPECIAL') DEFAULT 'OTC';
ALTER TABLE articulo ADD COLUMN es_fraccionable     TINYINT DEFAULT 0;  -- permite venta unitaria
ALTER TABLE articulo ADD COLUMN unidad_fraccion     VARCHAR(50);    -- "tableta", "cápsula"
```

**Cambios en UI:**
- Formulario de artículo/medicamento debe incluir estos campos
- Búsqueda en venta: buscar por nombre comercial, principio activo, laboratorio
- En ticket/factura: imprimir principio activo y concentración

---

### BRECHA 4 — Sin manejo de medicamentos de control especial

**Problema:**
Los psicotrópicos y narcóticos (benzodiacepinas, opioides, etc.) requieren un libro de control especial independiente de las ventas normales, con registro de nombre completo del paciente, DNI, nombre del médico prescriptor y número de colegiatura.

**Cambios requeridos en BD:**
```sql
CREATE TABLE control_especial (
    idcontrol        INT AUTO_INCREMENT PRIMARY KEY,
    idventa          INT,
    idlote           INT,
    idarticulo       INT NOT NULL,
    nombre_paciente  VARCHAR(200) NOT NULL,
    dni_paciente     VARCHAR(20) NOT NULL,
    nombre_medico    VARCHAR(200) NOT NULL,
    colegiatura      VARCHAR(50) NOT NULL,
    cantidad         DECIMAL(10,2) NOT NULL,
    fecha_despacho   DATETIME NOT NULL,
    idusuario        INT NOT NULL,     -- químico farmacéutico responsable
    observaciones    TEXT,
    FOREIGN KEY (idventa)    REFERENCES venta(idventa),
    FOREIGN KEY (idarticulo) REFERENCES articulo(idarticulo)
);
```

**Reporte nuevo:** Libro de control especial exportable a PDF para auditoría DIGEMID.

---

## BRECHAS IMPORTANTES (operacionales, no bloqueantes en día 1)

---

### BRECHA 5 — Sin venta fraccionada

**Problema:**
Muchos medicamentos se venden por unidades sueltas (tabletas individuales), no por caja completa. El sistema actual solo maneja unidades enteras del SKU.

**Impacto:** La farmacia no puede registrar correctamente la venta de medicamentos fraccionados, lo que afecta el control de stock y la facturación.

**Cambios requeridos:**
- En `articulo`: campos `es_fraccionable` y `unidad_fraccion` (ya propuesto en BRECHA 3)
- En `detalle_venta`: cambiar `cantidad INT` a `cantidad DECIMAL(10,2)`
- En `detalle_ingreso`: cambiar `cantidad INT` a `cantidad DECIMAL(10,2)`
- En `lote_articulo`: usar `DECIMAL(10,2)` para cantidades
- En UI (venta): si el artículo es fraccionable, permitir ingresar decimales y mostrar "x tabletas" con el precio unitario calculado
- El stock se descontaría en fracciones correspondientes (ej: caja de 20 tab → si se venden 5 tab, quedan 15 tab)

---

### BRECHA 6 — Categorías no adaptadas al dominio farmacéutico

**Problema:**
Las categorías actuales son genéricas (POLOS, PANTALONES, DISCOS). Para una farmacia, las categorías deben seguir clasificación ATC (Anatómica Terapéutica Química) o al menos grupos farmacológicos.

**Cambios requeridos:**
- Poblar `categoria` con grupos estándar: Analgésicos, Antibióticos, Antihipertensivos, Vitaminas, Dermatología, Oftalmología, etc.
- Opcionalmente agregar campo `codigo_atc` a `categoria` para clasificación internacional
- Agregar sub-categorías si el volumen lo requiere

---

### BRECHA 7 — Sin perfil de paciente/cliente farmacéutico

**Problema:**
La tabla `persona` almacena clientes genéricos. En farmacia, el cliente es un paciente que puede tener alergias, medicamentos crónicos y condiciones de salud relevantes para la dispensación.

**Cambios requeridos en BD:**
```sql
CREATE TABLE paciente_perfil (
    idpaciente       INT AUTO_INCREMENT PRIMARY KEY,
    idpersona        INT NOT NULL UNIQUE,
    fecha_nacimiento DATE,
    sexo             ENUM('M','F','OTRO'),
    alergias         TEXT,
    condiciones      TEXT,             -- diabetes, hipertensión, embarazo, etc.
    medicacion_cronica TEXT,
    eps_aseguradora  VARCHAR(150),
    num_afiliacion   VARCHAR(100),
    observaciones    TEXT,
    FOREIGN KEY (idpersona) REFERENCES persona(idpersona)
);
```

**Uso:** Al dispensar, el sistema puede mostrar alertas si el medicamento tiene contraindicación con las alergias registradas.

---

### BRECHA 8 — Sin integración con seguros/EPS

**Problema:**
Muchos pacientes tienen cobertura de seguro que cubre total o parcialmente el costo del medicamento. El sistema no maneja copagos ni descuentos por cobertura.

**Cambios requeridos en BD:**
```sql
ALTER TABLE venta ADD COLUMN idpaciente       INT;
ALTER TABLE venta ADD COLUMN eps_aseguradora  VARCHAR(150);
ALTER TABLE venta ADD COLUMN num_autorizacion VARCHAR(100);
ALTER TABLE venta ADD COLUMN monto_seguro     DECIMAL(10,2) DEFAULT 0;
ALTER TABLE venta ADD COLUMN monto_copago     DECIMAL(10,2) DEFAULT 0;
```

**UI:** Campo de seguro en el formulario de venta; el total a cobrar sería `total_venta - monto_seguro`.

---

### BRECHA 9 — Reportes no adaptados a farmacia

**Problema:**
Los reportes actuales son genéricos (ventas por fecha, compras por fecha). Una farmacia necesita reportes regulatorios y operacionales específicos.

**Reportes nuevos requeridos:**

| Reporte | Descripción | Obligatoriedad |
|---|---|---|
| Medicamentos por vencer | Lista ordenada por fecha de vencimiento (30/60/90 días) | Crítica operacional |
| Stock bajo mínimo | Artículos bajo su stock mínimo | Crítica operacional |
| Trazabilidad de lote | Todas las ventas de un lote específico | Recall/auditoría |
| Libro de control especial | Movimientos de psicotrópicos y narcóticos | Legal DIGEMID |
| Ventas por principio activo | Agrupa genéricos y marcas del mismo DCI | Operacional |
| Recetas atendidas | Relación de recetas registradas por período | Auditoría |
| Rotación de inventario | Medicamentos sin movimiento en N días | Operacional |
| Ingresos por laboratorio | Compras agrupadas por fabricante | Operacional |

---

### BRECHA 10 — Sin químico farmacéutico responsable

**Problema:**
En Perú y la mayoría de países latinoamericanos, cada establecimiento farmacéutico debe tener un Químico Farmacéutico (QF) director técnico responsable. Ciertos actos farmacéuticos (dispensación de Rx, control especial) deben registrar quién los ejecutó y si tenía título de QF.

**Cambios requeridos en BD:**
```sql
ALTER TABLE usuario ADD COLUMN es_quimico_farmaceutico TINYINT DEFAULT 0;
ALTER TABLE usuario ADD COLUMN num_colegiatura         VARCHAR(50);
ALTER TABLE usuario ADD COLUMN cargo                   VARCHAR(100);  -- QF Director, Técnico, Cajero
```

**Lógica:** Solo usuarios con `es_quimico_farmaceutico = 1` pueden aprobar despachos de medicamentos Rx o control especial.

---

## BRECHAS MENORES (mejoras de UX para el dominio)

---

### BRECHA 11 — Ticket/factura no incluye datos farmacéuticos

Los tickets actuales imprimen: artículo, cantidad, precio. El ticket de farmacia debe incluir:
- Principio activo (DCI) además del nombre comercial
- Número de lote
- Fecha de vencimiento
- Indicaciones básicas de uso (si aplica)
- Número de receta (si aplica)

Cambios en: `reportes/exTicket.php` y `reportes/exFactura.php`

---

### BRECHA 12 — Búsqueda de artículos insuficiente

La búsqueda actual es por `nombre` o `codigo`. En farmacia el paciente puede llegar pidiendo:
- El nombre genérico (principio activo)
- El nombre de marca
- La concentración
- La forma farmacéutica

La búsqueda debe cruzar `nombre`, `principio_activo`, `concentracion`, `laboratorio` y `codigo`.

---

### BRECHA 13 — Sin control de temperatura en recepción

Los medicamentos que requieren cadena de frío (insulina, vacunas, etc.) deben registrar temperatura de recepción. Cambio simple:

```sql
ALTER TABLE detalle_ingreso ADD COLUMN temperatura_recepcion DECIMAL(5,2);
ALTER TABLE detalle_ingreso ADD COLUMN observaciones_recepcion TEXT;
```

---

## Resumen de cambios por archivo

### Base de datos (nuevas migraciones)

| Migración | Tablas afectadas |
|---|---|
| `20260525_lotes_vencimientos.sql` | `lote_articulo`, `detalle_ingreso`, `detalle_venta` |
| `20260525_atributos_medicamento.sql` | `articulo` (nuevos campos) |
| `20260525_receta_medica.sql` | `receta_medica` (nueva tabla) |
| `20260525_control_especial.sql` | `control_especial` (nueva tabla) |
| `20260525_paciente_perfil.sql` | `paciente_perfil` (nueva tabla) |
| `20260525_usuario_farmaceutico.sql` | `usuario` (nuevos campos) |
| `20260525_cantidades_decimales.sql` | `detalle_venta`, `detalle_ingreso` (DECIMAL) |
| `20260525_venta_seguros.sql` | `venta` (nuevos campos) |

### Modelos a crear o modificar

| Archivo | Cambio |
|---|---|
| `modelos/Articulo.php` | Nuevos campos en `insertar/editar`, búsqueda extendida |
| `modelos/Venta.php` | Lógica FEFO, validación Rx, fraccionados, descuento seguro |
| `modelos/Ingreso.php` | Registro de lote en detalle, temperatura |
| `modelos/Lote.php` | Nuevo — CRUD de lotes, cálculo FEFO, alertas vencimiento |
| `modelos/Receta.php` | Nuevo — CRUD recetas médicas |
| `modelos/ControlEspecial.php` | Nuevo — libro de psicotrópicos |
| `modelos/Paciente.php` | Nuevo — perfil farmacéutico del cliente |

### Vistas a crear o modificar

| Archivo | Cambio |
|---|---|
| `vistas/articulo.php` | Nuevos campos farmacéuticos en formulario |
| `vistas/venta.php` | Modal de receta, selector de lote, fraccionados, copago |
| `vistas/ingreso.php` | Registro de lote y vencimiento en detalle |
| `vistas/lote.php` | Nueva — gestión y consulta de lotes |
| `vistas/vencimientos.php` | Nueva — dashboard de próximos vencimientos |
| `vistas/receta.php` | Nueva — registro y consulta de recetas |
| `vistas/control_especial.php` | Nueva — libro de control especial |
| `vistas/paciente.php` | Nueva o modificar `cliente.php` — perfil farmacéutico |

### Reportes a crear

| Archivo | Descripción |
|---|---|
| `reportes/rptVencimientos.php` | PDF de medicamentos por vencer |
| `reportes/rptLote.php` | Trazabilidad de lote |
| `reportes/rptControlEspecial.php` | Libro oficial de psicotrópicos |
| `reportes/rptRecetas.php` | Recetas atendidas por período |
| `reportes/rptRotacion.php` | Rotación de inventario |

---

## BRECHA 0 — Caja desconectada del sistema (bloqueante operacional)

Esta brecha se agregó después del análisis de UX. Aunque el módulo de caja existe, tiene problemas que lo hacen inoperable y debe resolverse antes que cualquier adaptación farmacéutica.

### Problema 0.1 — El módulo está desactivado manualmente

`vistas/caja.php` línea 7: `$moduloCajaHabilitado = false;` redirige al dashboard. Nadie puede acceder al módulo actualmente.

**Fix:** Cambiar a `true` una vez resueltos los problemas de fondo.

### Problema 0.2 — Las ventas no generan movimiento de caja automáticamente

`Venta::insertar()` no registra el cobro en la caja abierta. Caja y ventas son módulos separados sin comunicación. Al cerrar caja, el resumen no refleja las ventas del día.

**Fix en `modelos/Venta.php`** — dentro de `insertar()`, después del COMMIT:
```php
require_once "Caja.php";
$cajaMdl = new Caja();
$cajaAbierta = $cajaMdl->cajaAbiertaUsuario($this->idusuario);
if ($cajaAbierta) {
    $cajaMdl->agregarMovimiento(
        $cajaAbierta['idcaja'],
        $this->idusuario,
        'INGRESO',
        'Venta ' . $tipo_comprobante . ' ' . $serie . '-' . $num,
        $monto_efectivo  // solo la parte en efectivo, no tarjeta
    );
}
```

Lo mismo al anular: generar un movimiento EGRESO automático.

### Problema 0.3 — Se puede vender sin caja abierta

`ajax/venta.php` no verifica si el cajero tiene caja abierta antes de permitir una venta. Esto rompe el control financiero.

**Fix en `ajax/venta.php`**, operación `guardaryeditar`, al inicio:
```php
require_once "../modelos/Caja.php";
$cajaMdl = new Caja();
if (!$cajaMdl->cajaAbiertaUsuario($_SESSION['idusuario'])) {
    echo json_encode(['ok' => false, 'message' => 'Debes abrir la caja antes de registrar ventas']);
    exit;
}
```

### Problema 0.4 — Sin separación de método de pago

Todo el cobro se trata como un único monto. En farmacia hay que saber qué parte fue efectivo (va a la caja física) vs. tarjeta/transferencia/Yape (va a liquidación bancaria). Sin esto el cuadre es imposible.

**Cambios en BD:**
```sql
ALTER TABLE venta
  ADD COLUMN metodo_pago    ENUM('EFECTIVO','TARJETA','TRANSFERENCIA','YAPE','PLIN','MIXTO') DEFAULT 'EFECTIVO',
  ADD COLUMN monto_efectivo  DECIMAL(10,2) DEFAULT 0,
  ADD COLUMN monto_tarjeta   DECIMAL(10,2) DEFAULT 0,
  ADD COLUMN monto_digital   DECIMAL(10,2) DEFAULT 0;
```

Solo `monto_efectivo` entra al saldo de caja. El total de ventas para el resumen del día considera los tres.

### Problema 0.5 — UX de la pantalla de caja mezcla todo en una pantalla

El formulario actual muestra apertura + movimiento rápido + cierre + historial simultáneamente. Confunde al cajero.

**Fix:** La vista debe renderizar condicionalmente según el estado de la caja (ver diseño de pantallas en CLAUDE.md).

### Problema 0.6 — Sin reporte PDF de cierre de caja

No hay documento imprimible del cierre. En farmacia el cierre se imprime, firma el cajero y queda archivado.

**Nuevo archivo `reportes/rptCierreCaja.php`** con:
- Fecha y hora de apertura y cierre
- Cajero responsable
- Monto de apertura
- Ventas del día desglosadas por método de pago
- Movimientos manuales (ingresos y egresos)
- Total sistema vs. total real contado
- Diferencia (sobrante / faltante)
- Espacio para firma

### Problema 0.7 — Historial de caja solo muestra el usuario actual

El supervisor o administrador no puede ver las cajas de otros cajeros. En farmacia con múltiples cajas, el administrador necesita ver todas.

**Fix en `Caja::historialCajas()`** — agregar parámetro de rol:
```php
public function historialCajas($idusuario, $esAdmin = false) {
    $filtro = $esAdmin ? "" : "WHERE c.idusuario='$idusuario'";
    $sql = "SELECT c.*, u.nombre AS cajero ... FROM caja_diaria c
            INNER JOIN usuario u ON u.idusuario = c.idusuario
            $filtro ORDER BY c.idcaja DESC";
    ...
}
```

---

## Orden de implementación recomendado

### Fase 0 — Caja operativa (días 1-3) ← HACER PRIMERO

Sin esto el sistema no puede usarse en producción aunque todo lo demás funcione.

1. Migración: agregar `metodo_pago`, `monto_efectivo`, `monto_tarjeta`, `monto_digital` a `venta`
2. Activar módulo: `$moduloCajaHabilitado = true` en `vistas/caja.php`
3. Rediseñar `vistas/caja.php` — pantalla condicional por estado (abierta/cerrada)
4. Conectar `Venta::insertar()` con `Caja::agregarMovimiento()` (solo monto efectivo)
5. Conectar anulación de venta con movimiento EGRESO en caja
6. Validar caja abierta en `ajax/venta.php` antes de procesar venta
7. Agregar selector de método de pago en `vistas/venta.php`
8. Crear `reportes/rptCierreCaja.php` — PDF de cierre
9. Mostrar estado de caja (abierta/cerrada) en el header del sistema
10. Hacer historial de caja visible para administrador (todas las cajas)

### Fase 1 — Fundamentos regulatorios (semana 1-2)
1. Migración: atributos farmacéuticos en `articulo`
2. Migración: tabla `lote_articulo`
3. Actualizar `detalle_ingreso` y `detalle_venta` para lotes
4. Actualizar UI de ingreso para registrar lote y vencimiento
5. Lógica FEFO en `Venta::insertar()`
6. Alerta de medicamentos por vencer en dashboard

### Fase 2 — Dispensación correcta (semana 2-3)
7. Campo `tipo_venta` en artículo (OTC/Rx/Control)
8. Modal de receta en venta cuando hay artículo Rx
9. Tabla `receta_medica` y modelo `Receta.php`
10. Búsqueda extendida por principio activo y laboratorio

### Fase 3 — Control especial y trazabilidad (semana 3-4)
11. Tabla `control_especial` y módulo completo
12. Campos de QF en `usuario`
13. Reporte: libro de control especial PDF
14. Reporte: trazabilidad por lote

### Fase 4 — Mejoras operacionales (semana 4-5)
15. Venta fraccionada (cantidades decimales)
16. Perfil de paciente (`paciente_perfil`)
17. Integración básica de seguros en venta
18. Reportes adicionales (rotación, por laboratorio)

### Fase 5 — Ticket y documentos (semana 5)
19. Actualizar `exTicket.php` con datos farmacéuticos
20. Actualizar `exFactura.php` con DCI y lote
21. Poblar categorías con grupos farmacológicos ATC

---

## Lo que SÍ sirve del sistema actual sin cambios

- Flujo de compra/venta con comprobantes electrónicos
- Gestión de proveedores (distribuidoras farmacéuticas)
- Control de caja diaria
- Sistema de permisos por usuario
- Generación de PDF con FPDF
- Configuración de empresa (logo, moneda, impuesto)
- Backups automáticos de BD
- Reportes de ventas por fecha
- Dashboard con KPIs y gráficos
- Gestión de múltiples usuarios con roles
- CxC y CxP (crédito a clientes, deuda a proveedores)
- AdminLTE UI (fácil de entender por operadores)

---

## Estimación de esfuerzo

| Fase | Complejidad | Tiempo estimado |
|---|---|---|
| Fase 0 — Caja operativa | Media | 3 días |
| Fase 1 — Lotes y vencimientos | Alta | 2 semanas |
| Fase 2 — Recetas y Rx | Media | 1.5 semanas |
| Fase 3 — Control especial | Media | 1 semana |
| Fase 4 — Mejoras operacionales | Media-Baja | 1 semana |
| Fase 5 — Documentos y UX final | Baja | 0.5 semanas |
| **Total** | | **~6 semanas + 3 días** |

Esto asumiendo un desarrollador dedicado. Con dos desarrolladores paralelos en Fase 1 + 2, el tiempo se reduce a ~4 semanas para tener un sistema operativo legalmente.

La Fase 0 puede hacerse en paralelo con la planificación de la Fase 1 sin riesgo de conflictos.
