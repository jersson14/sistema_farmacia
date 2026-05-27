# CLAUDE.md — Sistema de Gestión para Farmacia

## Identidad del proyecto

- **Nombre base del sistema:** ITVentas (a renombrar según cliente)
- **Propósito:** Sistema de punto de venta, inventario, compras y reportes para farmacias independientes o cadenas pequeñas
- **Stack:** PHP 8.x + MySQL/MariaDB + AdminLTE + Bootstrap 3 + jQuery + FPDF
- **Arquitectura:** MVC artesanal sin framework, sin ORM, sin Composer
- **Entorno:** XAMPP (Windows), carpeta raíz `C:\xampp\htdocs\farmacia\`
- **BD:** `mi_tienda` (renombrar a `farmacia_db` o similar en producción)

---

## Estructura del proyecto

```
farmacia/
├── ajax/                # Controladores HTTP — responden JSON o HTML
├── config/              # Conexión y variables globales
│   ├── global.php       # Credenciales BD, nombre app
│   └── Conexion.php     # Helpers de consulta, sanitización, moneda
├── files/               # Archivos subidos (imágenes, backups)
├── fpdf181/             # Librería PDF (no tocar)
├── migrations/          # Scripts SQL versionados
├── modelos/             # Clases PHP (lógica de negocio)
├── public/              # CSS, JS, Bootstrap, AdminLTE, plugins
├── reportes/            # Generadores PDF con FPDF
├── vistas/              # Templates HTML (mezclan PHP y HTML)
│   └── scripts/         # JS por vista
└── index.php            # Entry point → redirige a login
```

### Convención de nombres de archivos

- Modelos: `modelos/NombreEntidad.php` — clase con métodos de negocio
- Controladores: `ajax/entidad.php` — switch por `$_GET["op"]`
- Vistas: `vistas/entidad.php` — HTML + PHP embebido
- Scripts JS: `vistas/scripts/entidad.js` (cuando se separa)
- Reportes PDF: `reportes/exEntidad.php`
- Migraciones: `migrations/YYYYMMDD_descripcion.sql`

---

## Base de datos

### Tablas existentes

| Tabla | Rol |
|---|---|
| `articulo` | Catálogo de productos |
| `categoria` | Clasificación de artículos |
| `unidad_medida` | Unidades de medida |
| `persona` | Clientes y proveedores |
| `usuario` | Cuentas de acceso |
| `usuario_permiso` | Relación usuario–permiso |
| `permiso` | Permisos por módulo |
| `ingreso` + `detalle_ingreso` | Compras |
| `venta` + `detalle_venta` | Ventas |
| `cuenta_cobrar` + `pago_cuenta_cobrar` | CxC |
| `cuenta_pagar` + `pago_cuenta_pagar` | CxP |
| `caja_diaria` + `caja_movimiento` | Control de caja |
| `configuracion_empresa` | Logo, series, moneda, impuesto |
| `backup_log` | Historial de respaldos |

### Reglas de base de datos

- Cada nueva tabla lleva un archivo de migración versionado en `migrations/`
- El nombre del archivo sigue el patrón `YYYYMMDD_descripcion.sql`
- Las migraciones usan `IF NOT EXISTS` para ser idempotentes
- Los triggers se documentan en el mismo archivo de migración
- Nunca modificar datos de producción sin script reversible

---

## Patrones de código establecidos

### Modelo (PHP)

```php
class NombreEntidad {
    private $tabla = "nombre_tabla";

    public function insertar() {
        $sql = "INSERT INTO {$this->tabla} (campo1, campo2) VALUES (...)";
        return ejecutarConsulta($sql);
    }

    public function listar() {
        $sql = "SELECT * FROM {$this->tabla} WHERE condicion=1";
        return ejecutarConsulta($sql);
    }
}
```

### Controlador AJAX

```php
// ajax/entidad.php
session_start();
require_once "../modelos/Entidad.php";

$op = $_GET["op"] ?? "";
$obj = new Entidad();

switch ($op) {
    case "listar":
        echo json_encode($obj->listar());
        break;
    case "guardar":
        // validar, luego...
        echo json_encode($obj->insertar());
        break;
}
```

### Respuesta JSON estándar

```json
{ "ok": true, "message": "...", "data": {...} }
{ "ok": false, "message": "Error: ..." }
```

### Sanitización (obligatoria en todo input externo)

```php
$valor = limpiarCadena($_POST["campo"]);
```

`limpiarCadena()` aplica `mysqli_real_escape_string` + `htmlspecialchars`. **Siempre usar antes de insertar en BD.**

---

## Seguridad

### Reglas críticas

- **Nunca** concatenar `$_POST`/`$_GET` directamente en una query SQL
- **Siempre** usar `limpiarCadena()` en cada input antes de cualquier uso
- **Siempre** verificar sesión activa al inicio de cada vista y controlador
- Las contraseñas se almacenan en **SHA256** (campo `clave` de `usuario`)
- Los archivos en `config/` y `files/backups/` deben estar fuera del web root en producción
- No mostrar errores PHP en producción (`display_errors = Off`)

### Validación de uploads

- Verificar extensión de imagen: solo `jpg`, `jpeg`, `png`, `gif`
- Limitar tamaño máximo del archivo
- Renombrar el archivo al guardar (nunca usar el nombre original del cliente)

### Sesión y permisos

- Cada vista válida que la sesión tiene `$_SESSION['idusuario']` antes de renderizar
- Los permisos se verifican via `$_SESSION['modulo']` (e.g., `$_SESSION['ventas']`)
- Redirigir a `vistas/noacceso.php` si el permiso no está en sesión

---

## Flujos de negocio core

### Venta

1. Seleccionar cliente (o crear cliente rápido inline)
2. Agregar artículos al carrito (valida stock en tiempo real)
3. Elegir tipo de comprobante (Boleta/Factura/Ticket)
4. El sistema auto-genera serie + número correlativo
5. Aplicar descuentos por línea e impuesto general
6. `ajax/venta.php?op=guardaryeditar` → `Venta::insertar()` en transacción
7. Se reduce stock, se emite comprobante PDF/ticket

### Compra (Ingreso)

1. Seleccionar proveedor
2. Agregar artículos con precio de compra y precio de venta
3. El trigger `tr_updStockIngreso` actualiza stock automáticamente
4. Genera comprobante PDF de ingreso

### Control de stock

- `articulo.stock` se incrementa por trigger al insertar en `detalle_ingreso`
- `articulo.stock` se decrementa en `Venta::insertar()` dentro de la transacción
- `articulo.stock_minimo` dispara alertas post-venta en la respuesta JSON

---

## Variables de configuración

Todas en `config/global.php`:

| Constante | Descripción |
|---|---|
| `DB_HOST` | Host de MySQL |
| `DB_NAME` | Nombre de la BD |
| `DB_USERNAME` | Usuario MySQL |
| `DB_PASSWORD` | Contraseña MySQL |
| `PRO_NOMBRE` | Nombre del sistema mostrado en UI |

Configuración dinámica en `configuracion_empresa` (BD):
- Logo, colores primario/secundario
- Series de comprobantes por tipo
- Moneda y símbolo
- Porcentaje de impuesto (IGV 18% por defecto)

---

## Monedas soportadas

`PEN` (S/), `USD` ($), `EUR`, `MXN`, `COP`, `CLP`, `ARS`, `BOB`

Usar `obtenerSimboloMoneda()` y `formatearMoneda()` en todo lugar donde se muestre precio.

---

## Reportes

- Los PDFs usan **FPDF 1.81** (`fpdf181/`)
- Nunca modificar los archivos del core de FPDF
- Cada reporte tiene su propio archivo en `reportes/`
- Los reportes leen directamente de la BD, no de sesión
- Para tablas en PDF usar la clase extendida `PDF_MC_Table`

---

## Convenciones de desarrollo

### Lo que SÍ hacer

- Usar transacciones MySQL (`START TRANSACTION / COMMIT / ROLLBACK`) en operaciones que tocan múltiples tablas
- Nombrar métodos del modelo en español: `insertar`, `editar`, `listar`, `mostrar`, `desactivar`, `activar`
- Validar stock antes de confirmar venta (no después)
- Devolver `"ok": false` con mensaje descriptivo ante cualquier error controlado
- Versionar toda modificación de esquema en `migrations/`

### Lo que NO hacer

- No usar `SELECT *` en queries de producción con tablas grandes — seleccionar solo columnas necesarias
- No hacer lógica de negocio en las vistas (solo presentación)
- No duplicar código de sanitización — usar siempre `limpiarCadena()`
- No eliminar registros físicamente si tienen relaciones — usar campo `condicion` (1=activo, 0=inactivo)
- No cambiar el esquema de respuesta JSON sin actualizar el JS consumidor
- No instalar dependencias sin documentarlo aquí

---

## Módulos y permisos del sistema

| Permiso (sesión) | Módulos que protege |
|---|---|
| `escritorio` | Dashboard, KPIs, gráficos |
| `almacen` | Artículos, categorías, unidades |
| `compras` | Ingresos, proveedores |
| `ventas` | Ventas, clientes |
| `acceso` | Usuarios, permisos, empresa |
| `consultac` | Reportes de compras |
| `consultav` | Reportes de ventas |
| `caja` | Caja diaria, movimientos |
| `kardex` | ProCenter, alertas de stock |
| `cuentas` | CxC, CxP |
| `backup` | Respaldos de BD |

---

## Archivos sensibles — nunca commitear con datos reales

```
config/global.php       # credenciales BD
files/backups/*.sql     # dumps de producción
files/empresa/*.png     # logo del cliente
```

Crear un `config/global.example.php` con valores en blanco como referencia.

---

## Comandos útiles de desarrollo

```bash
# Levantar XAMPP
# Start Apache + MySQL desde el panel XAMPP

# Acceder al sistema
http://localhost/farmacia/

# Acceder a PHPMyAdmin
http://localhost/phpmyadmin/

# BD de desarrollo
host: localhost | user: root | pass: (vacío) | db: mi_tienda
```

---

## Contexto de negocio — Farmacia

Este sistema está siendo adaptado para **gestión de farmacias**. El dominio farmacéutico tiene requerimientos adicionales más allá de un punto de venta genérico. Ver el archivo `ANALISIS_FARMACIA.md` para el detalle completo de brechas y cambios requeridos.

### Conceptos clave del dominio farmacéutico

- **Lote:** Conjunto de medicamentos fabricados en un mismo proceso. Identificado por número de lote. Permite trazabilidad y retiro de mercado.
- **Fecha de vencimiento:** Obligatoria en medicamentos. Determina el orden de despacho (FEFO).
- **FEFO:** First Expired First Out — política de despacho que prioriza el stock con menor fecha de vencimiento.
- **DCI:** Denominación Común Internacional — nombre genérico del principio activo.
- **DIGEMID:** Autoridad regulatoria de medicamentos en Perú.
- **Receta médica:** Documento legal requerido para la dispensación de ciertos medicamentos.
- **OTC:** Over The Counter — medicamentos que se venden sin receta.
- **Psicotrópico/narcótico:** Medicamento de control especial con registro obligatorio.

---

## Principios de UX — El sistema debe ser fácil de usar

Este es el principio rector más importante del proyecto. El usuario final es el **cajero o técnico de farmacia**: no es programador, trabaja bajo presión, atiende clientes en fila, y necesita completar una venta en segundos. Cada decisión de diseño de pantalla, formulario y flujo debe pasar por este filtro:

> **"¿Un cajero sin entrenamiento técnico puede hacer esto solo en menos de 1 minuto?"**

Si la respuesta es no, la pantalla está mal diseñada.

---

### Reglas de UX que se aplican a todo el sistema

#### 1. El estado de caja siempre visible

El estado de la caja (ABIERTA / CERRADA) debe mostrarse en el header en todo momento. Si la caja está cerrada, el menú de ventas debe estar bloqueado o mostrar un aviso destacado. El cajero nunca debe adivinar si puede vender o no.

#### 2. Una pantalla, una tarea

No mezclar acciones distintas en la misma pantalla. La pantalla de caja muestra solo lo que corresponde al estado actual:
- Si caja cerrada → solo botón de apertura
- Si caja abierta → resumen del día + movimientos + botón de cierre

#### 3. La venta debe completarse en el menor número de pasos posible

Flujo mínimo esperado:
```
Buscar medicamento → agregar cantidad → cobrar → emitir ticket
```
Todo lo que agregue pasos innecesarios a este flujo es un problema de diseño.

#### 4. Búsqueda que funciona como el usuario busca

Los cajeros buscan medicamentos como los conocen: por nombre comercial, por nombre genérico, por laboratorio, o escaneando el código de barras. La búsqueda debe cruzar todos esos campos a la vez. Presionar Enter debe disparar la búsqueda.

#### 5. Campos con valores por defecto inteligentes

- Boleta a consumidor final: el campo cliente debe llenarse automáticamente con "Consumidor Final"
- Tipo de comprobante: recordar el último usado por el cajero
- Método de pago: default EFECTIVO
- Impuesto: tomarlo de la configuración de empresa, no pedirlo en cada venta

#### 6. Errores en lenguaje humano y accionables

Nunca mostrar errores técnicos al cajero. Cada mensaje de error debe decirle qué pasó y qué hacer:

| MAL | BIEN |
|---|---|
| "Error: constraint violation" | "No hay stock suficiente de Paracetamol 500mg" |
| "Invalid date format" | "Ingresa la fecha en formato DD/MM/AAAA" |
| "Session expired" | "Tu sesión venció. Vuelve a iniciar sesión." |
| "No hay caja abierta" | "Debes abrir la caja antes de registrar ventas. [Abrir caja]" |

#### 7. Confirmaciones solo cuando el daño es irreversible

- Agregar un producto al carrito → sin confirmación
- Eliminar un producto del carrito → sin confirmación
- Anular una venta → con confirmación + motivo
- Cerrar caja → con confirmación + campo de monto real contado
- Eliminar un usuario → con confirmación

No pedir confirmación para acciones fáciles de deshacer o que el usuario acaba de elegir hacer.

#### 8. Feedback inmediato en cada acción

Después de cada acción el cajero debe saber en menos de 2 segundos si funcionó o no. Usar:
- Notificación verde/roja en la esquina superior derecha (ya existe `appNotify`)
- Para la venta: modal de éxito con opción de imprimir ticket inmediatamente
- Para apertura de caja: actualizar el header de estado sin recargar la página

#### 9. Ticket automático al confirmar venta

Después de registrar una venta exitosa, el ticket debe abrirse automáticamente en una nueva pestaña para imprimir. El cajero no debe tener que buscarlo. Si el navegador bloquea popups, mostrar botón prominente "Imprimir ticket".

#### 10. Sin tecnicismos en la UI

Los textos en pantalla deben estar en el lenguaje de la farmacia, no del desarrollador:

| Término técnico | Término en farmacia |
|---|---|
| "Artículo" | "Medicamento" o "Producto" |
| "Ingreso" | "Compra" o "Recepción de mercadería" |
| "Persona" | "Cliente" o "Proveedor" |
| "Condicion: 1/0" | "Activo / Inactivo" |
| "idventa" | Nunca mostrar al usuario |

#### 11. Diseño para pantalla pequeña de caja

El POS de farmacia suele usarse en monitores de 15"-17" con resolución 1366x768. Las vistas de venta y caja deben funcionar bien en esa resolución sin hacer scroll horizontal.

#### 12. Accesos rápidos con teclado

| Atajo | Acción |
|---|---|
| `Enter` en buscador | Buscar medicamento |
| `F2` | Limpiar carrito |
| `F10` | Confirmar venta |
| `Esc` | Cerrar modal activo |

Documentar los atajos en la pantalla de venta con un botón de ayuda visible.

---

### Perfiles de usuario y qué necesita cada uno

| Perfil | Usa principalmente | Necesita que sea rápido |
|---|---|---|
| **Cajero / Técnico** | Venta, Caja | Búsqueda de medicamentos, cierre de venta, ticket |
| **Químico Farmacéutico** | Venta (Rx), Alertas | Validación de receta, alertas de vencimiento |
| **Almacenero** | Ingresos, Artículos | Registro de lotes, actualización de stock |
| **Administrador** | Todo | Reportes, configuración, usuarios |
| **Dueño / Supervisor** | Dashboard, Reportes | KPIs del día, cierre de caja, resumen de ventas |

Diseñar cada pantalla pensando en el perfil que la usa el 90% del tiempo.

---

### Flujo de inicio de turno (lo más común del día)

Este flujo debe funcionar sin fricción:

```
1. Cajero ingresa al sistema (login)
2. Sistema detecta que no hay caja abierta
3. Muestra aviso: "Abre tu caja para comenzar"  ← pantalla clara, no escondida
4. Cajero ingresa monto de apertura y abre caja
5. Sistema confirma y habilita el módulo de ventas
6. Cajero atiende clientes
7. Al cierre: caja muestra resumen automático (ventas del día + movimientos)
8. Cajero ingresa monto contado en físico
9. Sistema calcula diferencia y genera reporte PDF de cierre
```

---

### Flujo de caja — diseño de pantalla por estado

**Pantalla cuando NO hay caja abierta:**
```
┌─────────────────────────────────────────┐
│  No tienes una caja abierta             │
│                                         │
│  Monto inicial (S/): [_________]        │
│  Observación:        [_________]        │
│                                         │
│           [ ABRIR CAJA ]                │
│                                         │
│  ─── Historial de cajas anteriores ─── │
│  (tabla con últimas 10)                 │
└─────────────────────────────────────────┘
```

**Pantalla cuando hay caja abierta:**
```
┌─────────────────────────────────────────────────┐
│  CAJA ABIERTA  |  Desde: 08:30 am               │
│                                                   │
│  Apertura:        S/  200.00                      │
│  Ventas (efectivo): S/ 1,200.00  (32 ventas)     │
│  Ingresos manuales: S/    50.00                  │
│  Egresos manuales:  S/    30.00                  │
│  ─────────────────────────────                   │
│  TOTAL ESPERADO:    S/ 1,420.00                  │
│                                                   │
│  [ + Agregar movimiento ]  [ CERRAR CAJA ]       │
│                                                   │
│  ─── Movimientos de hoy ───                      │
│  (tabla con movimientos de la caja abierta)      │
└─────────────────────────────────────────────────┘
```

---

## Estado del proyecto

- Commit inicial: sistema genérico funcional para tiendas/comercios
- En curso: adaptación al dominio farmacéutico
- Próximos pasos: ver `ANALISIS_FARMACIA.md`
