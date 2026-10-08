<!-- Generada por `php artisan asistente:aprender inventario` desde docs/modulos/inventario.md, el menú y las pantallas de alta · huella c9fbc035ff210417e8b67696abe23faa17b35640 · 2026-10-08. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# Inventario
**En pocas palabras:** Módulo para el control y seguimiento del inventario de equipos y materiales del ISP.

## Para qué sirve
- Registra y organiza los equipos y materiales utilizados para instalaciones y mantenimientos.
- Permite conocer la disponibilidad y ubicación de los materiales en tiempo real.
- Proporciona trazabilidad de los movimientos de inventario (entradas y salidas).
- Facilita la gestión de proveedores y sus facturas.

## Pantallas
- [Inventario › Almacenes › Listar](/inventory/inventory_store) — Gestiona los almacenes donde se almacenan los ítems.
- [Inventario › Tipo de Artículos › Listar](/inventory/inventory_item_type) — Administra las categorías de los ítems.
- [Inventario › Artículos › Listar](/inventory/inventory_item_stock) — Muestra el stock actual de los ítems.
- [Inventario › Artículos › Artículos Custom](/inventory/inventory_item_custom_model) — Gestiona ítems personalizados.
- [Inventario › Movimientos › Listar](/inventory/inventory_movement) — Registra los movimientos de entrada y salida del inventario.
- [Inventario › Proveedores › Crear](/inventory/supplier/create) — Da de alta un nuevo proveedor.
- [Inventario › Proveedores › Listar](/inventory/supplier) — Muestra la lista de proveedores registrados.
- [Inventario › Proveedores › Facturas › Crear](/inventory/supplier-invoice/create) — Da de alta una nueva factura de compra.
- [Inventario › Proveedores › Facturas › Listar](/inventory/supplier-invoice) — Muestra la lista de facturas de proveedores.
- [Inventario › Valuación Inventario](/inventory/inventory-valuation) — Muestra la valuación general del inventario.

## Cómo se hace
### Alta de proveedore
1. Ir a la pantalla [Inventario › Proveedores › Crear](/inventory/supplier/create) y llenar los campos requeridos.

### Registro de un ítem
1. Dentro de la pantalla de ítems, registrar un nuevo ítem con los datos solicitados.

### Registro de un movimiento
1. Acceder a la pantalla [Inventario › Movimientos › Listar](/inventory/inventory_movement) y registrar la entrada o salida.

## Qué datos pide
### Alta de proveedor ([Inventario › Proveedores › Crear](/inventory/supplier/create))
- **Obligatorios:** Nombre, RFC, Teléfono, Email, Dirección, Estado
- **Opcionales:** No aplica
- **Se llenan solos:** No aplica

### Alta de ítem (dentro de la pantalla de ítems)
- **Obligatorios:** Store zone, Nombre del Artículo, Descripción del Artículo, Tipo de Artículo, Cantidad Inicial, Almacén
- **Opcionales:** Activar Numero de Serie, Estado del Articulo, Numero de Serie, Imagen
- **Se llenan solos:** Límite Alto, Límite Medio

### Alta de factura (dentro de la pantalla de facturas)
- **Obligatorios:** No aplica
- **Opcionales:** No aplica
- **Se llenan solos:** No aplica

## Quién lo usa
Lo utilizan principalmente los equipos técnicos, de logística y de administración para el control y seguimiento del inventario.

## Con JARVIS
Por ahora JARVIS te explica cómo hacerlo y te da el enlace a la pantalla.
