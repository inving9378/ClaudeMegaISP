# Módulo Inventario

> `app/Modules/Addons/Inventario/` · slug `addon-inventario` · módulo **addon** (activo).

## 0. En simple
Es la bodega digital del ISP: lleva la cuenta de cuántos routers, ONUs, cables y demás materiales hay, en qué almacén están, quién los trae consigo y quién se los proveyó.

## 1. Qué es
Módulo addon que controla el **inventario de equipos y materiales**: catálogo de ítems (con su tipo y modelo), existencias por almacén/zona/persona, movimientos de entrada-salida-traslado, proveedores y sus facturas/precios, y una valuación general del inventario.

## 2. Para qué sirve
Le da al personal de bodega/administración un solo lugar para saber cuánto stock queda de cada equipo (ONUs, routers, splitters, cable, etc.), dónde está físicamente (almacén → zona) o quién lo trae asignado (técnico), registrar entradas cuando llega mercancía de un proveedor y salidas cuando se instala o se entrega a un técnico/cliente, y auditar el historial completo de movimientos. También sostiene la compra a proveedores (catálogo de precios, facturas, recepción) y da una vista de valuación del inventario (costo total en existencia).

## 3. Cómo funciona
- **Sin `Models/`/`Repositories/`/`Services/` propios dentro del módulo** (carpetas reservadas vacías, patrón visto en otros addons) — reusa modelos globales de `app/Models/` (`InventoryItem`, `InventoryItemType`, `InventoryItemStock`, `InventoryItemStoreZone`, `InventoryMovement`, `InventoryStore`, `InventoryReservation`, `InventoryItemCustom`/`InventoryItemCustomModel`, `InventoryItemMedia`, `Supplier` y afines), repositorios de `app/Http/Repository/Inventory*Repository.php` y el servicio central `App\Services\InventoryService`.
- **Controllers** organizados por entidad bajo `Controllers/` (`InventoryItem`, `InventoryItemType`, `InventoryItemStock`, `InventoryMovement`, `InventoryStore`, `StoreZone`, `InventoryItemCustom`, `Supplier/*`). Los de catálogo (`InventoryItemController`, `InventoryMovementController`, etc.) extienden `CrudModalController` (patrón CRUD estándar del sistema, datatable + modal).
- **Ítem vs. Stock:** `InventoryItem` es el catálogo (tipo, modelo, número de serie); `InventoryItemStock` es la **existencia** de ese ítem en un "poseedor" — relación polimórfica `modelable` que puede ser `InventoryStore` (un almacén) o `App\Models\User` (un técnico que trae equipo consigo). Así el mismo mecanismo de stock cubre "está en el almacén central" y "lo trae Brandon en su mochila".
- **Movimientos:** `InventoryMovement` registra cada entrada/salida/traslado (`ComunConstantsController::INVENTORY_MOVEMENT_TYPE_*`) con origen y destino (`movementable_from/to_id/type`, también polimórfico). `InventoryService::updateInventoryStock()` es el punto central que decide sumar/restar stock según el tipo de movimiento y actualiza además el conteo por zona (`InventoryItemStoreZone`).
- **Alta de ítem** (`InventoryItemController::store`): crea el `InventoryItem`, genera un movimiento de tipo "Entrada" con `is_initial=true` desde el usuario autenticado hacia el almacén elegido, y ajusta el stock inicial por zona — un solo flujo cubre alta de catálogo + stock inicial.
- **Almacenes y zonas:** `InventoryStore` (almacén físico) tiene `InventoryItemStoreZone` (subdivisión — pasillo/estante). Los movimientos y el stock por zona se filtran por `store_zone_id`.
- **Ítems personalizados** (`InventoryItemCustom`/`InventoryItemCustomModel`): variante de catálogo para ítems con atributos de modelo propios (reutiliza la tabla `inventory_items`).
- **Multimedia:** `InventoryItemMedia` guarda fotos ligadas a un ítem o a un stock específico (orden autoincremental por grupo); `InventoryItemMediaService` gestiona el copiado de medios al crear un nuevo stock.
- **Sub-módulo Proveedores** (`Controllers/Supplier/*`): `Supplier` (catálogo de proveedores), `SupplierVendorController` (vendedores del proveedor), `SupplierProductPriceController` (catálogo de precios por ítem), `SupplierInvoiceController` (facturas de compra: alta, recepción que dispara entrada de stock, o rechazo).
- **Valuación** (`InventoryValuationController` + `SupplierService::getInventoryValuation()`): agrega el costo del inventario en existencia usando los precios de proveedor.
- **Rutas** (`routes.php`) bajo `['web','auth','check_route_permission']`, prefijo `inventory/*` (preservado por compatibilidad con el frontend), con sub-prefijos por entidad (`inventory_item`, `inventory_item_type`, `inventory_item_stock`, `inventory_movement`, `inventory_store`, `store_zone`, `inventory_item_custom(_model)`, `supplier`, `supplier-invoice`, `inventory-valuation`). El comentario del archivo aclara que `web` se agrega a mano porque `loadRoutesFrom()` no aplica ese grupo automáticamente.
- **Frontend:** Vue en `resources/js/components/module/inventory/` (una carpeta `*Crud.vue`/`*Listar.vue` por entidad + `component/InventoryMovementAll.vue`/`InventoryIncrementDecrementStock.vue`). También hay componentes de inventario reutilizados **fuera** del módulo: `module/sellers/inventory_items/` y `module/client/document/inventory_items/` (para ver/mover equipo asignado a vendedores y a la ficha del cliente).

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Vistas** (`GET`, todas bajo `/inventory/*`): `inventory_item`, `inventory_item_type`, `inventory_item_stock`, `inventory_movement`, `inventory_store` (+ `my-store/{id}`, `get-all`, `get-by-id/{id}`), `store_zone` (+ búsquedas y consultas por almacén), `inventory_item_custom_model`, `supplier` (+ vendedores y catálogo de precios anidados), `supplier-invoice` (+ `receive`/`deny`), `inventory-valuation`.
- **Endpoints de acción sobre ítems**: `add`, `add-custom`, `update/{id}`, `destroy/{id}`, `assign_to_user/{id}`, `change_store/{id}`, `add_movement`, `table` (datatable).
- **Endpoints de stock**: `change_stock`, `get_items_by_user/{id}`, `get_items_by_store/{id}`, `get_items_by_client/{id}`, `accept_item_by_movement/{id}` / `reject_item_by_movement/{id}` (flujo de aceptar/rechazar una asignación), media (`upload_media`, `get_media_by_item/{id}`, `delete_media/{id}`).
- **Permisos** (declarados en `module.json`): `inventory_view_inventory` + `inventory_item_*`, `inventory_item_type_*`, `inventory_item_stock_*`, `inventory_movement_*`, `inventory_store_*`, `inventory_item_custom_model_*`, `store_zone_*` (view/add/edit/delete por entidad) — gatean rutas vía `check_route_permission` y el sidebar/menú.
- **Entradas de menú/admin card** "Inventario" (Ítems, Tipos, Stock, Movimientos, Almacenes) y secciones de configuración `inventario_general` consumidas por el catálogo DB-driven de módulos (`module.json` → `config_sections`/`screens`, con términos/pasos para la ayuda con IA).
- **Datos de stock por poseedor** (`inventory_item_stocks`, relación polimórfica `modelable`) — es el contrato real que otros módulos leen directamente por consulta SQL/Eloquent, sin endpoint dedicado.

**Consume**
- **Usuarios** (`App\Models\User`) — como "poseedor" polimórfico de stock (técnico que trae equipo) y como responsable de movimientos/facturas de compra.
- **Clientes** (`App\Models\Client` / `ClientRepository`) — el frontend `module/client/document/inventory_items/` muestra y mueve equipo asignado dentro de la ficha del cliente (p. ej. equipo instalado/retirado).
- **Vendedores** (`module/sellers/inventory_items/`) — vista de equipo asignado a un vendedor, mismo mecanismo de stock polimórfico.
- **Talento (`app/Modules/Addons/Talento/`)** — **consumidor de solo lectura sobre `inventory_item_stocks`**, sin tabla propia de custodia: `TalentoCustodiaController` (Portal de Colaborador, sección "Mi material") lee directamente `inventory_item_stocks` filtrando `modelable_type = App\Models\User` para mostrarle a cada colaborador lo que trae en custodia; `SettlementService` mueve material devuelto de vuelta al almacén reusando este mismo mecanismo (`InventoryService`/`inventory_item_stocks`/`inventory_movements`), y `talento_loans_and_settlements` referencia `inventory_item_stocks.id` como `stock_id`. Documentado en el propio código como "reusa el backend de Inventario" — no se duplicó lógica.
- **Documentos de compra** — `Supplier`/`SupplierInvoice` alimentan el costo usado por `InventoryValuationController`/`SupplierService::getInventoryValuation()`.
- **Sin integración externa propia** (WhatsApp/IA/Mapas): el módulo no monta canal ni cliente HTTP propio; su única superficie es interna (rutas `web`) y por lectura directa de tabla desde otros módulos.

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
