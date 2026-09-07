# Item #9990486 — SaleController (Vendors/Sales) `salesBySeller`/`getSalesBySeller`: verificación id vs CMI.seller_id

**Resultado: sin mismatch. Sin cambio de código.**

## Alcance del item

Sub-item de seguimiento de #9990484 (mismo criterio: `client_main_information.seller_id`
GUARDA `users.id`, no `sellers.id` — caso de prueba real: Guadalupe `sellers.id=12`,
`users.id=9`, 474 clientes con `seller_id=9`).

Pendiente de auditar (no se había leído su implementación ni consumidores):
- `SaleController::salesBySeller($id)` — ruta `GET {id}/salesBySeller`
- `SaleController::getSalesBySeller(Request $request, $id)` — ruta `POST /sales-by-seller/{id}`

(`rankingSales`, `getTotalSales`, `getTotalProspects`, `getLostSales` de este mismo archivo ya
estaban confirmados correctos por el item padre — no se tocan aquí.)

## Qué columna filtran (backend)

- `salesBySeller($id)` → `VendorService::getSalesBySeller($id)` →
  `App\Modules\Core\Clientes\Repositories\ClientMainInformationRepository::getClientsBySellerId($id)`
  → `$this->model->where('seller_id', $id)->get()` sobre `client_main_information`.
- `getSalesBySeller(Request, $id)` → agrega filtro `['column' => 'seller_id', 'value' => $id]`
  sobre la misma tabla, vía el datatable genérico (`getGeneralQuery`/`applyFilters`).

Ambos filtran directo por `client_main_information.seller_id = $id` — para ser correctos,
`$id` debe ser `users.id` (igual que el resto del módulo).

## De dónde viene el `$id` en el frontend (cadena completa verificada)

- `SaleController::edit($seller_id, $user_id)` (líneas 50-58) y `showPanel()` (líneas 68-88) del
  hermano `Vendors/SellerController.php` calculan `$seller_id = $seller->id` (PK de `sellers`) y
  `$user_id = $user->id` (PK de `users`) como **variables separadas**.
- `resources/views/meganet/module/vendors/menu.blade.php:9` y `panel.blade.php:14` pasan ambas
  por separado: `:seller_id="{{ $seller_id }}" :user_id="{{ $user_id }}"`.
- `Panel.vue` (props `user_id`/`seller_id` en líneas 114-115) reparte:
  - `InformationSeller :id="seller_id"` (línea 55) — correcto, ese componente sí necesita `sellers.id`.
  - `ListSales :id="user_id"` (línea 61) → `sales/Sales.vue` → `getSalesBySeller(props.id, ...)`
    (línea 368) → `POST /vendedores/ventas/sales-by-seller/${id}` — **recibe `user_id`**.
  - `Billing :user_id="user_id" :seller_id="seller_id"` (línea 70) →
    `billing/index.vue` → `ListCustomers :user_id="user_id"` →
    `getSalesBySeller(props.user_id, ...)` (línea 391) — **recibe `user_id`**.

Los dos únicos consumidores reales de `getSalesBySeller` (`sales/Sales.vue`,
`billing/ListCustomers.vue`) reciben el prop correcto (`user_id`), nunca `sellers.id`.
Los 11 archivos que el item señalaba como "consumidores a revisar" (`statistics/*.vue`,
`graphics/*.vue`, `statistics/helper/request.js`, `vendors/helper/request.js`) en realidad
**no llaman a ninguno de los dos endpoints auditados** — pegan a controllers/rutas distintos
(`/statics/*`, `/vendedores/ventas/salesByMedium|salesByMonth|rankingSales`).

## Hallazgo secundario (no accionado)

`sales/helper/request.js:3-10` exporta `getById(id)` (llama `GET {id}/salesBySeller`) pero
**no tiene ningún consumidor** — `Sales.vue` solo importa `getSalesBySeller`, no `getById`.
Confirmado con grep en todo `resources/js/components/module/vendors/`: ningún otro archivo
importa `getById` desde `./sales/helper/request` o `./helper/request` con esa ruta. Es código
muerto, pero **fuera del alcance de este item** (que pedía verificar mismatch de id, no auditar
uso de endpoints) — no se removió para no mezclar un cambio de alcance distinto en el mismo commit.
Si se retoma, es un candidato limpio de nivel A (borrar `getById` + la ruta
`GET {id}/salesBySeller` + `SaleController::salesBySeller` + `VendorService::getSalesBySeller`,
verificando antes que `ClientMainInformationRepository::getClientsBySellerId` no tenga otro
consumidor).

## Conclusión

Sin mismatch de id. `getSalesBySeller` recibe siempre `users.id` en ambos flujos (Ventas y
Facturación del panel de vendedor). Nada que corregir en este archivo.
