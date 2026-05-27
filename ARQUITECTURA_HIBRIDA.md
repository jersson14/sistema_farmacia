# Arquitectura Híbrida — Local + Web

## El problema

La gaveta de dinero y la ticketera térmica están conectadas físicamente a la PC de la farmacia.
Ningún servidor remoto puede abrir un dispositivo USB/RJ11 que está en otro lugar.
Por eso no se puede mover todo al hosting sin resolver primero ese problema.

---

## Opción A — Local primario + Web solo para consultas (Recomendada)

```
FARMACIA (XAMPP local)              HOSTING WEB
┌───────────────────────┐           ┌──────────────────────────┐
│ Ventas / Caja         │──sync───▶ │ Dashboard / Reportes     │
│ Ticketera / Gaveta    │           │ (solo lectura)            │
│ Stock en tiempo real  │           │ Dueño lo ve desde casa    │
│ Sin depender internet │           │ o desde el celular        │
└───────────────────────┘           └──────────────────────────┘
```

### Cómo funciona

- La farmacia trabaja 100% en local — rápido, sin depender del internet
- Al cerrar caja (o cada cierto tiempo), un script empuja los datos al hosting
- El dueño o supervisor puede ver reportes y ventas desde cualquier dispositivo
- La gaveta y ticketera siempre las controla el servidor local

### Ventajas

- Sin riesgo de quedarse sin internet en plena venta
- La gaveta funciona igual que ahora
- El dueño puede supervisar desde casa
- Bajo costo — solo necesita un hosting básico

### Desventaja

- Si hay más de una caja en distintos locales, cada local tiene su propio XAMPP

---

## Opción B — Web primario + Puente local para gaveta

```
HOSTING WEB                         PC FARMACIA
┌───────────────────────┐           ┌──────────────────────────┐
│ Todo el sistema       │ ◀───────▶ │ QZ Tray (puente local)   │
│ Ventas, caja, stock   │           │ Solo maneja:             │
│ Accesible desde web   │           │  - Impresora térmica     │
│ Cualquier dispositivo │           │  - Gaveta de dinero      │
└───────────────────────┘           └──────────────────────────┘
```

### Cómo funciona

- El sistema completo vive en el hosting
- Cualquier PC o tablet puede registrar ventas desde el navegador
- En la PC de caja se instala **QZ Tray** (programa gratuito, corre en segundo plano)
- Cuando se confirma una venta, el sistema web le ordena a QZ Tray imprimir el ticket y abrir la gaveta

### Ventajas

- Múltiples cajeros desde cualquier lugar
- No se instala nada pesado en las PCs de caja

### Desventaja

- Si se cae el internet, no se puede vender
- Requiere instalar y configurar QZ Tray en cada PC de caja
- Mayor complejidad técnica

---

## Comparación

| Característica              | Opción A        | Opción B             |
|-----------------------------|-----------------|----------------------|
| Complejidad de implementación | Baja          | Media-alta           |
| Funciona sin internet       | ✅ Sí           | ❌ No                |
| Múltiples locales           | Un XAMPP c/u    | ✅ Centralizado      |
| Gaveta funciona             | ✅ Sí           | ✅ Sí (con QZ Tray)  |
| Costo mensual               | Hosting básico  | Hosting + soporte    |
| Riesgo operativo            | Muy bajo        | Depende del internet |

---

## Recomendación

Para una farmacia pequeña o mediana con una o dos cajas → **Opción A**.

### Plan de implementación Opción A

1. XAMPP sigue siendo el corazón del sistema (ventas, caja, gaveta, impresora)
2. Se agrega un módulo de sincronización que al cerrar caja envía los datos al hosting
3. El hosting tiene una versión de solo lectura del sistema (reportes, dashboard, historial)
4. El dueño accede al hosting desde su celular o PC para supervisar en tiempo real

### Módulos que van en el hosting (solo lectura)

- Dashboard con KPIs del día
- Historial de ventas
- Estado de inventario
- Reportes de caja
- Estado de resultados

### Módulos que se quedan en local (XAMPP)

- Caja (apertura, movimientos, cierre)
- Ventas (POS, ticketera, gaveta)
- Ingresos / Compras
- Gestión de artículos y stock

---

## Siguiente paso

Implementar el módulo de sincronización:
- Al cerrar caja → envía ventas del día al hosting vía API
- El hosting recibe y guarda en su propia base de datos
- El dueño ve el dashboard actualizado al instante
