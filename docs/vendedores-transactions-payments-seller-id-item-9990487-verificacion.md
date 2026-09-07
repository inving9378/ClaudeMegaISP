# Item #9990487 — Verificación empírica: `transactions_sellers.seller_id` / `payments_sellers.seller_id` (RESUELTO — confirma `sellers.id`, sin bug)

Sub-item de seguimiento de #9990484. A diferencia de `client_main_information.seller_id` y
`crm_lead_information.owner_id` (que el item padre midió = `users.id`), este item pedía verificar
si `transactions_sellers.seller_id` y `payments_sellers.seller_id` (tablas propias de Vendedores)
usan `sellers.id` — como sugieren las relaciones `Seller::hasMany(TransactionSeller::class,
'seller_id')` / `Seller::hasMany(PaymentSeller::class, 'seller_id')` — y auditar
`SellerTransactionController`, `PaymentSellerController` y `ProspectController` en consecuencia.

## Medición empírica (PASO 1 del padre) — inconclusa por tamaño de muestra

- `transactions_sellers`: 2 filas totales, **1 valor distinto** de `seller_id` (`4`). Ese `4`
  existe como PK en `sellers` (`Seller#4 → user_id=3`) **y** como PK en `users` (`User#4 =
  KATHYA`) — coincidencia de rango de ids en una BD de dev pequeña, no discrimina por sí sola.
- `payments_sellers`: **0 filas** — no hay nada que medir empíricamente.

Con datos tan escasos el conteo de valores distintos (el método del padre) no alcanza para
concluir. Se complementó con análisis de código (escritura + lectura), que sí es concluyente.

## Evidencia por código — concluyente: SÍ es `sellers.id`

**Camino completo, de punta a punta:**

1. `SellerController::showPanel()` (`app/Modules/Addons/Vendedores/Controllers/Vendors/SellerController.php:64-80`):
   ```php
   $seller = Seller::where('user_id', $user->id)->first();
   $seller_id = $seller->id;   // sellers.id, EXPLÍCITO y separado de $user_id
   $user_id = $user->id;
   ```
   `panel.blade.php` pasa ambos por separado al componente Vue (`:seller_id=".."
   :user_id=".."`), y `Panel.vue` los mantiene como props distintos hacia `Billing`
   (`index.vue`) → `PaymentsComponent.vue` → `AddPayment.vue` (`props.seller_id` viaja intacto
   hasta `formData.value.seller_id`, sin resolver contra `user`).

2. `PaymentSellerController::store()` (líneas ~185-259, el alta real de un pago) usa
   `$data['seller_id']` con `Seller::find($data['seller_id'])` — si fuera `users.id` esa búsqueda
   fallaría siempre. El mismo `$data['seller_id']` se escribe **tal cual** en
   `payments_sellers.seller_id` (líneas 200/240) **y** en `transactions_sellers.seller_id`
   (línea 258, alta de la transacción de saldo). Ambas tablas reciben el mismo valor en el mismo
   flujo — no pueden estar keyed distinto entre sí.

3. Otros filtros del mismo controller confirman el mismo criterio con una fuente independiente:
   `where('seller_id', $user->seller->id)` (líneas 666/697/699/791/891) — resuelven
   explícitamente `sellers.id` desde la relación, nunca usan `$user->id` a secas.

4. Los modelos (`Seller::paymentSeller()`, `Seller::transaction()`,
   `PaymentSeller::seller()`, `TransactionSeller::seller()`) son `hasMany`/`belongsTo` con llave
   local/foránea **por default** (`sellers.id` ↔ `seller_id`) — sin override, así que Eloquent ya
   asume `sellers.id`.

**Conclusión:** `transactions_sellers.seller_id` y `payments_sellers.seller_id` **sí** guardan
`sellers.id`, tal como sugería la declaración de los modelos. **`SellerTransactionController` y
`PaymentSellerController` ya están correctos — sin cambio de código.**

## `ProspectController::getById($id)` — filtra por `owner_id` (= `users.id`), pero es código muerto en la UI actual

- `ProspectController::getById()` (`app/Modules/Addons/Vendedores/Controllers/Vendors/Prospects/ProspectController.php:19-26`)
  filtra por `crm_lead_information.owner_id`, columna que el item padre (#9990484) ya midió
  empíricamente = `users.id`.
- El único caller Vue de esta ruta (`GET /vendedores/prospectos/{id}/getById`) es
  `resources/js/components/module/vendors/prospects/helper/request.js` (`export const getById`)
  — **grep de todo `resources/js/` confirma CERO importadores** de ese archivo. No hay ningún
  componente montado que invoque este endpoint hoy.
- El flujo real de "prospectos del vendedor" en el Panel (`Panel.vue:58`,
  `<ListProspects :id="user_id" />`) **no pasa por esta ruta en absoluto**: `ListProspects.vue`
  delega a `CrmDatatable.vue` con `owner_id: [props.id]` (siendo `props.id = user_id`, ya
  correcto = `users.id`).
- El comentario del commit `12832794` (fix de #9990483 en `PortalTecnicoController`) que el item
  cita como contraste ("ProspectController::getById($id) en Vendedores, que sí recibe el id de la
  URL") es sobre **IDOR** (el id llega directo de la URL sin resolverse server-side vía Actor), no
  sobre el tipo de id — tema distinto y ya documentado como característica conocida de ese
  endpoint, no un hallazgo nuevo.

**Conclusión:** no hay invocación viva que le pase un `seller_id` (o cualquier id incorrecto) a
esta ruta — el helper que lo llamaría no tiene consumidores. Nada que corregir en tiempo de
ejecución; la ruta/controller quedan tal cual (borrarlos está fuera del alcance de este item, que
pedía verificar consistencia de ids, no limpiar endpoints huérfanos).

## Tabla de referencia (completa la del item padre)

| Columna | Guarda | Evidencia |
|---|---|---|
| `client_main_information.seller_id` | `users.id` | Medido por el item padre #9990484 |
| `crm_lead_information.owner_id` | `users.id` | Medido por el item padre #9990484 |
| `transactions_sellers.seller_id` | **`sellers.id`** | Este item — código write+read consistente |
| `payments_sellers.seller_id` | **`sellers.id`** | Este item — código write+read consistente |

**Sin cambio de código** (los 3 controllers auditados ya son correctos o no tienen invocación viva
con el tipo de id equivocado).
