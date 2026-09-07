# Item #9990488 — Verificación end-to-end con Guadalupe + 2° vendedor + tabla de referencia final (PASO 4 de #9990484) — CERRADO

Último sub-item de #9990484 (barrido `seller_id`↔`user_id`). Corre después de que los 3 hermanos
(#9990485 Billing, #9990486 Sales, #9990487 transactions_sellers/payments_sellers) cerraron sin
encontrar bugs adicionales — solo código muerto eliminado (dos copias de `TransactionController`
en #9990485). El trabajo de este item es **verificación empírica de extremo a extremo** con datos
reales de dev + consolidar la tabla de referencia definitiva.

## Caso 1 — Guadalupe (`sellers.id=12`, `users.id=9`)

| Fuente | Resultado |
|---|---|
| `client_main_information.seller_id = 9` (raw) | 474 |
| `ClientMainInformationRepository::getClientsBySellerId(9)` (soft-deletes excluidos, mismo query que usan `PaymentClientController::getListPaymentsOfCustomersBySeller` y `VendorService::getSalesBySeller` → pestañas Facturación/clientes y Ventas) | **473** |
| `crm_lead_information.owner_id = 9` (pestaña Prospectos, `ListProspects.vue` → `owner_id: [user_id]`) | **274** |

Coincide con lo medido por el item padre (474 clientes / 274 leads); la diferencia de 1 en
clientes es un registro con soft-delete (`deleted_at` no nulo), correcto — el listado real no debe
mostrar clientes borrados.

## Caso 2 — 2° vendedor con datos reales: Diana (`sellers.id=4`, `users.id=3`)

Elegido por `COUNT(client_main_information.seller_id) GROUP BY seller_id` (3er lugar por volumen
real después de Irving-admin y Guadalupe): 380 clientes / 55 leads en crudo. Mismo patrón de
mismatch que Guadalupe (`sellers.id ≠ users.id`), buen segundo caso de prueba.

| Fuente | Resultado |
|---|---|
| `client_main_information.seller_id = 3` (raw) | 380 |
| `ClientMainInformationRepository::getClientsBySellerId(3)` | **379** |
| `crm_lead_information.owner_id = 3` | **55** |

Ambos casos confirman que el panel (`SellerController::edit($seller_id, $user_id)` →
`panel.blade.php` → `Panel.vue`) reparte los dos ids por separado y cada pestaña consume el que le
corresponde:

- `ListProspects :id="user_id"`, `ListSales :id="user_id"`, `billing/index.vue`'s
  `list-customers :user_id="user_id"` → tablas keyed por `users.id` (CMI/CRM).
- `InformationSeller :id="seller_id"`, `ListPaymentsTemporal :seller_id`,
  `ListTransactionsTemporal :seller_id` → tablas keyed por `sellers.id` (payments_sellers,
  transactions_sellers, history_sellers_rules).

Sin bug — confirma lo que #9990485/#9990486 ya habían auditado por código; aquí se corrió el
mismo camino real con dos vendedores de datos reales distintos, no solo lectura de código.

## Tabla de referencia consolidada — "columna → qué id guarda"

| Columna | Guarda | Estado / evidencia |
|---|---|---|
| `client_main_information.seller_id` | `users.id` | Medido #9990484 (22/24 valores existen como `users.id`); reconfirmado aquí con Guadalupe (473) y Diana (379) |
| `crm_lead_information.owner_id` | `users.id` | Medido #9990484 (21/27); reconfirmado aquí (274 / 55) |
| `transactions_sellers.seller_id` | `sellers.id` | Confirmado #9990487 (código write+read consistente, `Seller::find()`) |
| `payments_sellers.seller_id` | `sellers.id` | Confirmado #9990487 (mismo flujo que transactions_sellers) |
| `history_sellers_rules.seller_id` | `sellers.id` | Confirmado #9990485 (usado para cruzar la Facturación del panel) |
| `commissions.seller_id` | `sellers.id` | Tabla **vacía** en dev — ver #9990471 (su detalle `commissions_details` con 566,780 filas quedó huérfano, sin padre) |
| `sales.seller_id` / `prospects.id_seller` | `sellers.id` | Tablas **muertas** — 0 consumidores en código; la UI real usa `client_main_information` (ventas) y `crm_lead_information` (prospectos) — ver #9990471 |

**Regla práctica:** las tablas propias de Vendedores (`sellers`, `transactions_sellers`,
`payments_sellers`, `history_sellers_rules`, `commissions`) usan `sellers.id`. Las tablas que
Vendedores consume de otros módulos (`client_main_information` de Clientes, `crm_lead_information`
de CRM) usan `users.id` — el vendedor tal como lo conoce el sistema de autenticación, no su fila en
`sellers`. `sales`/`prospects` son remanentes descontinuados del diseño original, sin relación con
el flujo vivo.

## Cierre

No se encontró ningún bug adicional a los ya corregidos en items previos del barrido (#9990470,
#9990483). Los 4 sub-items de #9990484 (#9990485/#9990486/#9990487/#9990488) quedan completos —
el paraguas puede cerrarse. **Sin cambio de código** en este item (verificación + documentación).
