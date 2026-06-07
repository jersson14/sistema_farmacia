# ITVentas — Sistema de Gestión para Farmacia

Sistema de punto de venta, inventario, compras y reportes diseñado para **farmacias independientes o cadenas pequeñas** en Perú. Basado en una arquitectura MVC artesanal en PHP, adaptado al dominio farmacéutico con trazabilidad de lotes, control de vencimientos (FEFO), recetas médicas y registro de medicamentos de control especial.

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-3-7952B3?logo=bootstrap&logoColor=white)
![AdminLTE](https://img.shields.io/badge/AdminLTE-2.x-3C8DBC)
![FPDF](https://img.shields.io/badge/FPDF-1.81-orange)
![XAMPP](https://img.shields.io/badge/XAMPP-Windows-FB7A24?logo=xampp&logoColor=white)

---

## Tecnologías

| Capa | Herramienta |
|---|---|
| Backend | PHP 8.x — sin framework, sin ORM, sin Composer |
| Base de datos | MySQL / MariaDB (`mysqli` nativo) |
| Frontend | AdminLTE + Bootstrap 3 + jQuery |
| Gráficos | Chart.js |
| Tablas | DataTables + Buttons (Excel / CSV) |
| Reportes PDF | FPDF 1.81 |
| Tickets | HTML imprimible (thermal printer) |
| Periféricos | Impresora térmica + gaveta de dinero vía nombre compartido Windows |
| Entorno | XAMPP (Windows) |

---

## Estructura del proyecto

```
farmacia/
├── ajax/                  # Endpoints HTTP — responden JSON o HTML
├── config/
│   ├── global.php         # Constantes de conexión y nombre del sistema
│   └── Conexion.php       # Helpers: ejecutarConsulta(), limpiarCadena(), formatearMoneda()
├── files/
│   ├── articulos/         # Imágenes de productos
│   ├── usuarios/          # Fotos de usuarios
│   ├── empresa/           # Logo del cliente
│   └── backups/           # Dumps SQL generados desde el panel
├── fpdf181/               # Librería FPDF (no modificar)
├── migrations/            # Scripts SQL versionados (YYYYMMDD_descripcion.sql)
├── modelos/               # Clases PHP con lógica de negocio
├── public/                # CSS, JS, Bootstrap, AdminLTE, plugins
├── reportes/              # Generadores PDF individuales
├── vistas/                # Templates HTML con PHP embebido
│   └── scripts/           # JS por vista (cuando se separa del HTML)
└── index.php              # Entry point → redirige a login
```

### Convención de nombres

| Tipo | Patrón |
|---|---|
| Modelo | `modelos/NombreEntidad.php` |
| Controlador | `ajax/entidad.php` — switch por `$_GET["op"]` |
| Vista | `vistas/entidad.php` |
| Script JS | `vistas/scripts/entidad.js` |
| Reporte PDF | `reportes/exEntidad.php` |
| Migración | `migrations/YYYYMMDD_descripcion.sql` |

---

## Módulos del sistema

| Módulo | Permiso de sesión | Descripción |
|---|---|---|
| Dashboard | `escritorio` | KPIs del día, gráficos de ventas |
| Almacén | `almacen` | Medicamentos, categorías, unidades de medida |
| Compras | `compras` | Ingresos de mercadería, proveedores |
| Ventas | `ventas` | POS, carrito, comprobantes |
| Caja | `caja` | Apertura/cierre de caja, movimientos |
| Reportes compras | `consultac` | Historial e informes de compras |
| Reportes ventas | `consultav` | Historial e informes de ventas |
| Kardex / Alertas | `kardex` | ProCenter, alertas de stock y vencimientos |
| Cuentas | `cuentas` | CxC y CxP |
| Acceso | `acceso` | Usuarios, permisos, configuración de empresa |
| Backup | `backup` | Respaldos de base de datos |

---

## Esquema de base de datos

### Tablas principales

| Tabla | Rol |
|---|---|
| `articulo` | Catálogo de medicamentos y productos |
| `categoria` | Clasificación (incluye categorías ATC farmacéuticas) |
| `unidad_medida` | Unidades (tableta, frasco, ampolla, etc.) |
| `persona` | Clientes y proveedores |
| `usuario` | Cuentas de acceso al sistema |
| `usuario_permiso` | Relación usuario–permiso |
| `permiso` | Permisos por módulo |
| `ingreso` + `detalle_ingreso` | Compras / recepción de mercadería |
| `venta` + `detalle_venta` | Ventas / despacho |
| `lote_articulo` | Lotes con fecha de vencimiento (FEFO) |
| `receta_medica` | Registro de recetas para medicamentos Rx |
| `control_especial` | Registro de psicotrópicos y narcóticos |
| `cuenta_cobrar` + `pago_cuenta_cobrar` | CxC |
| `cuenta_pagar` + `pago_cuenta_pagar` | CxP |
| `caja_diaria` + `caja_movimiento` | Control de caja por turno |
| `configuracion_empresa` | Logo, series, moneda, impuesto, impresora |
| `backup_log` | Historial de respaldos |

### Reglas de BD

- Toda modificación de esquema va en `migrations/` con nombre `YYYYMMDD_descripcion.sql`
- Las migraciones usan `IF NOT EXISTS` — son idempotentes
- Los triggers se documentan en el mismo archivo de migración
- Los registros no se eliminan físicamente: campo `condicion` (1 = activo, 0 = inactivo)

### Migraciones disponibles (en orden de aplicación)

Todos los archivos están en `migrations/`. Aplíquelos en este orden sobre la BD importada:

| # | Archivo | Descripción |
|---|---|---|
| 01 | `20260321_unidades_medida.sql` | Tabla de unidades de medida |
| 02 | `20260321_fase_comercial.sql` | Fase comercial de artículos |
| 03 | `20260525_metodo_pago_venta.sql` | Método de pago por venta |
| 04 | `20260525_atributos_medicamento.sql` | DCI, laboratorio, forma farmacéutica, condición de venta |
| 05 | `20260525_lotes_vencimientos.sql` | Control de lotes y fechas de vencimiento (FEFO) |
| 06 | `20260525_receta_medica.sql` | Registro de recetas médicas |
| 07 | `20260525_control_especial.sql` | Libro de control de psicotrópicos y narcóticos |
| 08 | `20260525_tienda_online.sql` | Módulo de pedidos online |
| 09 | `20260525_categorias_atc.sql` | Clasificación ATC de medicamentos |
| 10 | `20260525_temperatura_ingreso.sql` | Condición de temperatura en recepciones |
| 11 | `20260525_seguro_venta.sql` | Ventas con seguro médico |
| 12 | `20260525_cantidad_decimal.sql` | Soporte de cantidades decimales |
| 13 | `20260525_paciente_perfil.sql` | Perfil clínico del paciente |
| 14 | `20260525_permisos_granulares.sql` | Sistema de permisos granulares por módulo |
| 15 | `20260525_permisos_base_completo.sql` | Permisos base del sistema |
| 16 | `20260526_insert_unidades_medida.sql` | Datos semilla — unidades de medida |
| 17 | `20260526_insert_categorias_y_articulos.sql` | Datos semilla — categorías y artículos de farmacia |
| 18 | `20260526_insert_proveedores_peru.sql` | Datos semilla — proveedores del mercado peruano |
| 19 | `20260526_add_precio_venta_articulo.sql` | Campo precio de venta en artículo |
| 20 | `20260526_add_tipo_entrega_pedido.sql` | Tipo de entrega en pedidos |
| 21 | `20260526_add_metodo_pago_ingreso.sql` | Método de pago en ingresos |
| 22 | `20260527_consumidor_final.sql` | Cliente por defecto "Consumidor Final" |
| 23 | `20260527_printer_config.sql` | Configuración de impresora de tickets |
| 24 | `20260531_fix_tipo_venta_null.sql` | Corrección de nulos en tipo de venta |

> Todas las migraciones usan `IF NOT EXISTS` y son idempotentes — se pueden aplicar más de una vez sin error.

---

## Requisitos

- PHP >= 8.1 con extensiones: `mysqli`, `mbstring`, `gd`
- MySQL >= 5.7 o MariaDB equivalente
- Apache (XAMPP en desarrollo)

---

## Instalación local (XAMPP)

```bash
# 1. Copiar la carpeta del proyecto a:
#    C:\xampp\htdocs\farmacia\

# 2. Crear la base de datos en phpMyAdmin e importar el dump SQL principal

# 3. Aplicar las migraciones en el orden listado arriba

# 4. Copiar el archivo de configuración de ejemplo
cp config/global.example.php config/global.php
#    Editar config/global.php con tus propias credenciales locales (no se versiona)

# 5. Levantar Apache + MySQL desde el panel XAMPP

# 6. Abrir en el navegador
#    http://localhost/farmacia/
```

> `config/global.php` está en `.gitignore` — nunca se sube al repositorio.  
> Usa `config/global.example.php` como plantilla de referencia.

---

## Configuración dinámica (panel Empresa)

Se edita desde el módulo **Acceso → Empresa** y se guarda en la tabla `configuracion_empresa`:

| Campo | Descripción |
|---|---|
| `logo` | Imagen del negocio (aparece en reportes y login) |
| `color_primario` / `color_secundario` | Colores del tema |
| `serie_boleta`, `serie_factura`, `serie_ticket` | Series de comprobantes |
| `impuesto_default` | IGV (18% por defecto) |
| `moneda` | PEN, USD, EUR, MXN, COP, CLP, ARS, BOB |
| `nombre_impresora` | Nombre compartido de la ticketera (para gaveta) |

---

## Gaveta de dinero y ticketera térmica

La gaveta se abre automáticamente al confirmar cada venta.

**Configuración (una sola vez):**

1. En Windows: `Inicio → Dispositivos e impresoras → clic derecho en ticketera → Propiedades → Compartir`
2. Anotar el **nombre del recurso compartido** (ej. `XP365B`)
3. En el sistema: `Gestión Pro → Empresa → campo "Nombre de impresora (gaveta)"` → ingresar el nombre → Guardar

---

## Flujos de negocio

### Venta

```
1. Buscar medicamento (nombre comercial, genérico, laboratorio o código de barras)
2. Agregar al carrito — se valida stock en tiempo real
3. Elegir tipo de comprobante (Boleta / Factura / Ticket)
4. Ingresar método de pago (default: Efectivo)
5. Confirmar venta → transacción MySQL reduce stock y registra lote despachado (FEFO)
6. Ticket se abre automáticamente en nueva pestaña para imprimir
7. Gaveta se abre si es pago en efectivo
```

**Atajos de teclado en pantalla de venta:**

| Atajo | Acción |
|---|---|
| `Enter` en buscador | Buscar medicamento |
| `F2` | Limpiar carrito |
| `F10` | Confirmar venta |
| `Esc` | Cerrar modal activo |

### Compra / Recepción de mercadería

```
1. Seleccionar proveedor
2. Agregar medicamentos con precio de compra, precio de venta, número de lote y fecha de vencimiento
3. Guardar → trigger tr_updStockIngreso actualiza stock y registra lote
4. Se genera comprobante PDF de ingreso
```

### Control de stock

- `articulo.stock` sube por trigger al insertar en `detalle_ingreso`
- `articulo.stock` baja dentro de la transacción en `Venta::insertar()`
- Al llegar a `stock_minimo` se dispara alerta en la respuesta JSON
- El despacho sigue política **FEFO** (primero vence, primero sale)

### Caja diaria

```
Inicio de turno:
  → Sistema detecta caja cerrada
  → Cajero ingresa monto de apertura
  → Caja queda ABIERTA — módulo de ventas habilitado

Durante el turno:
  → Ventas, ingresos y egresos se acumulan automáticamente
  → Panel muestra total esperado en tiempo real

Cierre:
  → Cajero ingresa monto contado en físico
  → Sistema calcula diferencia
  → Genera reporte PDF de cierre
```

---

## Dominio farmacéutico — conceptos clave

| Término | Descripción |
|---|---|
| **Lote** | Conjunto de medicamentos de un mismo proceso de fabricación. Permite trazabilidad y retiro de mercado (recall). |
| **FEFO** | First Expired First Out — el lote que vence antes se despacha primero. |
| **DCI** | Denominación Común Internacional — nombre genérico del principio activo. |
| **OTC** | Over The Counter — medicamentos sin receta. |
| **Rx** | Medicamentos que requieren receta médica para su dispensación. |
| **Psicotrópico / narcótico** | Medicamento de control especial con registro obligatorio por ley. |
| **DIGEMID** | Autoridad regulatoria de medicamentos en Perú. |

---

## Seguridad

- Toda entrada de usuario pasa por `limpiarCadena()` antes de usarse en queries (`mysqli_real_escape_string` + `htmlspecialchars`)
- Nunca concatenar `$_POST` / `$_GET` directamente en SQL
- Contraseñas almacenadas en SHA-256
- Cada vista verifica `$_SESSION['idusuario']` antes de renderizar
- Los permisos se verifican por `$_SESSION['modulo']` — sin permiso redirige a `vistas/noacceso.php`
- Uploads: solo `jpg`, `jpeg`, `png`, `gif`; se renombra el archivo al guardar
- En producción: `display_errors = Off` en `php.ini`

**Archivos que nunca deben commitearse con datos reales:**

```
config/global.php        # credenciales BD
files/backups/*.sql      # dumps de producción
files/empresa/*.png      # logo del cliente
```

**Cambiar clave de un usuario (SQL):**

```sql
UPDATE usuario
SET clave = SHA2('NuevaClaveSegura', 256)
WHERE login = 'nombre_usuario';
```

---

## Patrones de código

### Modelo

```php
class Articulo {
    private $tabla = "articulo";

    public function listar() {
        $sql = "SELECT idarticulo, nombre, stock FROM {$this->tabla} WHERE condicion = 1";
        return ejecutarConsulta($sql);
    }

    public function insertar() {
        $sql = "INSERT INTO {$this->tabla} (nombre, stock) VALUES (...)";
        return ejecutarConsulta($sql);
    }
}
```

### Controlador AJAX

```php
// ajax/articulo.php
session_start();
require_once "../modelos/Articulo.php";

$op  = $_GET["op"] ?? "";
$obj = new Articulo();

switch ($op) {
    case "listar":
        echo json_encode($obj->listar());
        break;
    case "guardar":
        $nombre = limpiarCadena($_POST["nombre"]);
        echo json_encode($obj->insertar());
        break;
}
```

### Respuesta JSON estándar

```json
{ "ok": true,  "message": "Venta registrada correctamente", "data": {} }
{ "ok": false, "message": "No hay stock suficiente de Paracetamol 500mg" }
```

### Convenciones obligatorias

- Métodos del modelo en español: `insertar`, `editar`, `listar`, `mostrar`, `desactivar`, `activar`
- Usar `START TRANSACTION / COMMIT / ROLLBACK` en operaciones que tocan múltiples tablas
- No usar `SELECT *` en tablas grandes — seleccionar solo las columnas necesarias
- No hacer lógica de negocio en las vistas
- No eliminar registros físicamente si tienen relaciones — usar campo `condicion`

---

## Monedas soportadas

`PEN` (S/) · `USD` ($) · `EUR` · `MXN` · `COP` · `CLP` · `ARS` · `BOB`

Usar siempre `obtenerSimboloMoneda()` y `formatearMoneda()` para mostrar precios.

---

## Arquitectura de despliegue

El sistema opera en modo **local primario** — la venta, caja, gaveta y ticketera corren en XAMPP dentro de la farmacia sin depender de internet. Opcionalmente se puede sincronizar con un hosting para que el dueño vea reportes y dashboard en tiempo real desde cualquier dispositivo (ver `ARQUITECTURA_HIBRIDA.md`).

---

## Paso a producción — checklist

- [ ] Copiar proyecto al servidor (sin `.git`)
- [ ] Crear BD y usuario dedicado en hosting / cPanel
- [ ] Importar dump SQL y aplicar migraciones
- [ ] Editar `config/global.php` con credenciales reales
- [ ] Verificar permisos de escritura en `files/articulos/`, `files/usuarios/`, `files/empresa/`, `files/backups/`
- [ ] Activar HTTPS
- [ ] Configurar `display_errors = Off` en `php.ini`
- [ ] Cambiar todas las claves demo
- [ ] Configurar nombre de impresora térmica / gaveta
- [ ] Probar login, venta, compra, caja y reportes PDF

---

## Perfiles de usuario

| Perfil | Módulos principales | Lo que más necesita que sea rápido |
|---|---|---|
| Cajero / Técnico | Venta, Caja | Búsqueda de medicamentos, cierre de venta, ticket |
| Químico Farmacéutico | Venta (Rx), Alertas | Validación de receta, alertas de vencimiento |
| Almacenero | Compras, Almacén | Registro de lotes, actualización de stock |
| Administrador | Todo | Reportes, configuración, usuarios |
| Dueño / Supervisor | Dashboard, Reportes | KPIs del día, cierre de caja, resumen de ventas |

---

## Referencias internas

| Archivo | Contenido |
|---|---|
| `CLAUDE.md` | Guía técnica completa para desarrollo (patrones, reglas, BD) |
| `ANALISIS_FARMACIA.md` | Brechas del sistema base vs. requisitos farmacéuticos |
| `ARQUITECTURA_HIBRIDA.md` | Opciones de despliegue local + web |
| `GAVETA_CONFIGURACION.md` | Configuración detallada de gaveta e impresora térmica |
| `CONTRATO_SOFTWARE.md` | Términos de entrega del sistema |
