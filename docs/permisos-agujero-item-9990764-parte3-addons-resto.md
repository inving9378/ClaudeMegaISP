# Agujero de permisos — Lote 3/3: addons restantes (~29 módulos)

Sub-item de #9990764 (a su vez sub-item de #9990745). SOLO LECTURA — no se tocó ninguna ruta,
middleware, config ni permiso; este documento es el único artefacto de esta rama.

## Metodología (idéntica a #9990769 / #9990770)

Clasificación por ruta:

- **1 PROTEGIDA** — dentro de un grupo con middleware `check_route_permission`.
- **2 GATEADA POR ROL-GUARD** — `role:`, `permission:`, `can:`, `auth.portal`, `auth:sanctum`
  (+ scoping en el controller), o un guard/middleware dedicado propio del módulo
  (ej. `rol.instancia:operador` en VozMayorista).
- **3 AUTHORIZE INLINE** — `->authorize(`, `Gate::allows(`/`Gate::authorize(`, `->can(`/
  `abort_unless(...->can(...))` dentro del método del controller, o middleware `permission:`/
  `can:` declarado en el **constructor** del controller (`$this->middleware(...)`) en vez de en
  `routes.php` — mismo efecto de protección, solo distinto lugar de declaración.
- **4 AGUJERO REAL** — ninguna de las anteriores. No se encontró ninguna en este lote (ver
  "Resumen" abajo).
- **5 PUBLIC_ROUTES / público intencional (no reportar como riesgo)** — ruta deliberadamente sin
  auth (formulario público, landing de referidos, webhook con su propia verificación, liga de
  token con throttle). Se lista igual en la tabla para que el inventario quede completo, pero no
  cuenta como hallazgo.

### Corrección importante al modelo de riesgo del padre #9990764

La descripción del padre asume que una ruta bajo `check_route_permission` **sin** entrada en
`config/route_permission.php` "pasa el middleware sin exigir nada" (fail-open). Verificado
directo contra el código real
(`app/Modules/Core/Auth/Middleware/CheckRoutePermission.php:64-93`): **es exactamente lo
contrario — el middleware es fail-closed**. Para un usuario no-admin, si ninguna entrada del
config coincide con el path, `$has_permission` queda vacío y la petición se **deniega**
(redirect silencioso o 403); solo `Auth::user()->isAdmin() || isDevelopment() || isSuperAdmin()`
se saltan la verificación (bypass intencional documentado en varios sitios de CLAUDE.md). Por lo
tanto: **el hueco de seguridad real no es "falta la entrada en el config"** (eso a lo sumo deja
una pantalla inaccesible para no-admins, un bug funcional, no un agujero) — **es la ausencia
total del middleware/guard en el grupo de rutas**, que es justo lo que se auditó abajo. No se
cruzó cada ruta contra las 2490 líneas de `config/route_permission.php` porque, con este modelo
confirmado, ese cruce no cambia la clasificación de seguridad (solo detectaría funcionalidad
rota, fuera del alcance de este item).

---

## Inventario por módulo (29 archivos, prefijo `app/Modules/Addons/` salvo que se indique)

### 1. `Flotas/routes.php` (180 líneas)

Todo el árbol vive dentro de un único `Route::middleware(['web','auth','check_route_permission'])
->prefix('flotas')->group(...)` (líneas 18-179), salvo el bloque de suscripciones que además
exige `permission:fleet.subscriptions.manage` (líneas 166-179, anidado dentro del mismo grupo
externo).

| Método | URI | Controller@método | Línea | Clase |
|---|---|---|---|---|
| GET | /flotas, /flotas/vehiculos, /flotas/nuevo, /flotas/mapa, /flotas/geocercas(+nueva/{id}/editar/{id}), /flotas/notificaciones-log, /flotas/reglas, /flotas/documentos, /flotas/comparativo, /flotas/{id} | vistas Blade inline (closures) | 24-47 | 1 |
| GET/POST/PATCH/DELETE | /flotas/api/vehiculos/** (index/store/data/*/asignaciones/gps*/notification-preference/show/update/destroy/restore) | FleetVehicleController, FleetAssignmentController, FleetGpsController, FleetNotificationController | 51-77 | 1 |
| GET | /flotas/api/gps/flota | FleetGpsController@fleet | 81 | 1 |
| GET/POST | /flotas/api/notificaciones-log/**, /flotas/api/push-tokens/** | FleetNotificationController, FleetPushTokenController | 86-95 | 1 |
| GET/POST/PUT/DELETE | /flotas/api/reglas/** | FleetRuleController | 98-104 | 1 |
| GET/POST/PUT/PATCH/DELETE | /flotas/api/geocercas/** | FleetGeofenceController | 108-115 | 1 |
| GET/POST/PATCH/DELETE | /flotas/api/mantenimientos/** | FleetMaintenanceController | 119-125 | 1 |
| GET/POST/PATCH/DELETE | /flotas/api/documentos/** (incluye `/ocr`) | FleetDocumentController | 129-141 | 1 |
| GET/POST/PATCH/DELETE | /flotas/api/proveedores/** | FleetProviderController | 145-149 | 1 |
| GET/POST/DELETE | /flotas/api/combustible/** | FleetFuelLogController | 153-156 | 1 |
| POST/DELETE | /flotas/api/fotos/** | FleetPhotoController | 160-162 | 1 |
| GET | /flotas/suscripciones (vista) | closure | 166-167 | 1 (+ `permission:fleet.subscriptions.manage`) |
| GET/POST | /flotas/api/suscripciones/** | FleetSubscriptionController | 172-177 | 1 (+ `permission:fleet.subscriptions.manage`) |

**Sin agujeros.**

### 2. `PortalCliente/routes.php` (166 líneas) — guard propio `cliente`

Todo montado dos veces vía `$portalRoutes` (prefijo `/portal` y dominio dedicado). Guard de
sesión de cliente = `auth.portal` (distinto del panel admin).

| Método | URI | Controller@método | Línea | Clase |
|---|---|---|---|---|
| POST | /portal/openpay/webhook | OpenpayWebhookController@handle | 45-47 | 3 (Basic Auth inline contra `webhook_secret`, verificado en el controller líneas 32-37; además `withoutMiddleware(['auth','auth.portal'])` explícito) |
| GET/POST | /portal/login, /portal/registro, /portal/recuperar (+ sus POST) | AuthController | 50-55 | 5 (público intencional — entrada al portal) |
| POST | /portal/logout | AuthController@logout | 60 | 2 (`auth.portal`) |
| GET | /portal/, /portal/dashboard | DashboardController@index | 63-64 | 2 |
| GET | /portal/mi-plan | PlanController@index | 67 | 2 |
| GET/POST | /portal/facturas** (index/show/pagar/cfdi xml/pdf) | FacturasController, PortalPagoController | 70-76 | 2 |
| GET | /portal/pagos | PagosController@index | 79 | 2 |
| GET | /portal/consumo | ConsumoController@index | 82 | 2 |
| GET/POST | /portal/tickets** (index/show/store/reply) | TicketsController | 85-88 | 2 |
| GET/POST | /portal/perfil, /portal/perfil/cambiar-password | PerfilController | 91-92 | 2 |
| GET/POST | /portal/servicios** (marketplace + activar/desactivar megafamilia + interes) | MarketplaceController | 96-99 | 2 |
| GET | /portal/embajadores | EmbajadoresController@index | 102 | 2 |
| GET | /portal/flotas, /portal/flotas/tracking | FlotasController | 105-106 | 2 |
| GET/POST/PUT/DELETE | /portal/megafamilia** (G1-G7: perfiles/dispositivos/bloqueos/horarios/geocercas/tareas/recompensas/solicitudes) | MegaFamilia*Controller (9 controllers) | 109-155 | 2 |
| GET | /portal/hijo-megafamilia/{profile_id} | HijoMegaFamiliaController@index | 158 | 2 |

**Sin agujeros.**

### 3. `Inventario/routes.php` (164 líneas)

Todo bajo un único `Route::middleware(['web','auth','check_route_permission'])->prefix('inventory')
->group(...)` (línea 31-164).

| Sub-área | Rutas | Controller | Línea | Clase |
|---|---|---|---|---|
| inventory_item | index/add/add-custom/update/destroy/assign_to_user/change_store/add_movement/table | InventoryItemController | 34-43 | 1 |
| inventory_item_type | index/add/update/destroy/table | InventoryItemTypeController | 46-51 | 1 |
| inventory_item_stock | index/add/change_stock/update/destroy/table/get_items_by_*/media | InventoryItemStockController | 54-68 | 1 |
| inventory_movement | index/update/destroy/table | InventoryMovementController | 71-75 | 1 |
| inventory_store | index/add/update/destroy/table/my-store/get-all/get-by-id/scope-status | InventoryStoreController | 78-87 | 1 (nota: `/my-store/{id}` está listado además en `CheckRoutePermission::PUBLIC_ROUTES` — categoría 5 para ese patrón exacto, no reportar) |
| store_zone | index/add/update/destroy/table/get-store-zones*/show-zones*/search/get-by-id/update-zone | StoreZoneController | 90-100 | 1 |
| inventory_item_custom_model | index/add/table | InventoryItemCustomModelController | 103-106 | 1 |
| inventory_item_custom | items/{id}, table | InventoryItemCustomController | 109-111 | 1 |
| supplier (+ vendors, product-prices anidados) | index/create/add/update/destroy/table/show/get-all/get-by-id + sub-recursos | SupplierController, SupplierVendorController, SupplierProductPriceController | 115-141 | 1 |
| supplier-invoice | index/create/by-supplier/generate-number/add/show/update/destroy/table/receive/deny | SupplierInvoiceController | 145-157 | 1 |
| inventory-valuation | index/data | InventoryValuationController | 161-163 | 1 |

**Sin agujeros.**

### 4. `Vendedores/routes.php` (160 líneas)

Dos grupos, ambos `['web','auth','check_route_permission']` (líneas 34 y 68).

| Sub-área | Rutas | Controller | Línea | Clase |
|---|---|---|---|---|
| sellers/seller | index/add/edit/update/destroy/table/get-prospects | SellersSellerController | 36-43 | 1 |
| sellers/cuts/extras-incomes | `Route::resource(...)->except('index')` → create/store/show/edit/update/destroy (6 rutas) + POST `/extras-incomes-list/{id}` (index real) | ExtraIncomeController | 46-47 | 1 |
| sellers/cuts/installations | idem (6 rutas resource + index vía `/installations-list/{id}`) | InstallationController | 48-49 | 1 |
| sellers/cuts/suppliers-expenses | idem | SuppliersExpensesController | 50-51 | 1 |
| sellers/cuts/observations | idem | ObservationsController | 52-53 | 1 |
| sellers/cuts (caja) | get-user-current-box/box/box-pdf/get-received-payments-by-box/close-user-current-box/technicals/{id}(cuts) | BoxController | 54-60 | 1 |
| vendedores/ (dashboard, seguimiento, getDataById, get-status/type-sellers, update, pdf) | 8 rutas | VendorsSellerController | 69-87 | 1 |
| vendedores/dashboard | index | VendorController | 90-92 | 1 |
| vendedores/prospectos | index/getById/statusProspects | ProspectController | 95-99 | 1 |
| vendedores/ventas | index/salesBySeller/get-sales-by-seller/salesByMedium/salesByMonth/salesAndProspectsByDateRange/rankingSales/total-prospects/total-sales/total-lost-sales | SaleController | 102-113 | 1 |
| vendedores/payments | index/getListPayments/getPayments/getRuleDataSeller/getPeriodsFromSeller/getMontlyCommissionsBySeller | PaymentClientController | 118-124 | 1 |
| vendedores/payments-sellers | 21 rutas (get-all-payments/get-ticket/download-receipt/edit*/create/update/destroy/store/details*/receipt PDFs/statement/incomes/expenses/debt/discount accounts/payments-by-seller/payment-signature/pending-*/collect-debt) | PaymentSellerController | 128-153 | 1 |
| vendedores/transacciones | get-transactions-by-seller | SellerTransactionController | 158 | 1 |

**Sin agujeros.**

### 5. `Payments/routes.php` (145 líneas)

| Método | URI | Controller@método | Línea | Clase |
|---|---|---|---|---|
| POST | /payments/spei/webhook | SpeiWebhookController@handle | 32-33 | 3 (Basic Auth contra `payment_providers.config.webhook_secret`, verificado líneas 71-87 del controller; además listado en `PUBLIC_ROUTES`) |
| GET | /finanzas/metodos-pago (vista) | closure | 44-46 | 1 |
| GET/POST | /finanzas/conciliacion, /list, /{id}/resolver, /{id}/descartar | ReconciliationController | 50-53 | 1 |
| GET/POST | /finanzas/captura-pago, /buscar-cliente, /{id}/comprobante | ManualPaymentController | 57-60 | 1 |
| GET/POST/PUT/DELETE | /finanzas/payment-providers** | PaymentProviderController | 63-67 | 1 |
| GET/POST | /finanzas/clients/{id}/clabe, /assign-clabe | ClabeAssignmentController | 70-71 | 1 |
| GET | /finanzas/payments/{id}/receipt, /{payment}/receipt/{receipt}/download | ReceiptController | 74-77 | 1 |
| GET/POST | /finanzas/extraccion-comprobante(+/procesar) | ReceiptExtractionTestController | 88-89 | 2 (`role:super-administrator\|DESARROLLADOR`) |
| GET/POST | /finanzas/whatsapp-comprobantes(+/media/+/extraer/+/identificar) | WhatsappReceiptReviewController | 93-97 | 2 (mismo `role:`) |
| GET/POST | /finanzas/conciliacion-sim(+/iniciar/responder/avanzar/reiniciar) | ReconciliationSimulatorController | 101-105 | 2 (mismo `role:`) |
| GET/POST | /finanzas/conciliacion-cola** (index/pendientes/list/clientes buscar/{session} detalle/media/reasignar/confirmar/rechazar) | ReconciliationQueueController | 114-122 | 2 (`permission:conciliacion.manage`) |
| GET/POST | /finanzas/conciliacion-config | ConciliationConfigController | 131-132 | 2 (`role:super-administrator`, frontera dura explícita) |
| GET/POST | /api/megafamilia/payments/clabe, /notify-transfer | MobilePaymentController | 143-144 | 2 (`auth:sanctum` + `log_api_mobile`) |

**Sin agujeros.**

### 6. `DocumentacionCorporativa/routes.php` (135 líneas)

Todo bajo `['web','auth','check_route_permission']->prefix('documentacion-corporativa')` (línea
28-135). ~40 rutas API (tablero, apartado+exportar, cartera-detalle-nominal, acuse/exportar,
empresa+plazo, concesiones CRUD+pagos, pendientes CRUD, registros estructurados CRUD,
inventario CRUD, plantillas/generar, documentos CRUD+versiones+lote, entregas zip/acta,
solicitudes CRUD+entregas, bitácora+exportar, offboarding pendientes/revocar/otros-items) — todas
dentro del mismo grupo, sin excepción. **Clase 1 en las ~40 rutas. Sin agujeros.**

### 7. `IA/routes.php` (133 líneas)

Grupo raíz `['web','auth']->prefix('ia')` (línea 28); **cada** sub-bloque de rutas está anidado
en su propio `Route::middleware('permission:ia_*')`. Verificado: no queda ninguna ruta directamente
bajo el grupo raíz sin ese anidamiento (chat/historial/prompts/enviar/configuración/proveedores/
proyectos/conversaciones CRUD/tareas/notas/memoria/sesiones/prompts CRUD — 11 sub-bloques, cada
uno con su `permission:ia_*` propio). **Clase 2 en todas. Sin agujeros.**

### 8. `Embajadores/routes.php` (113 líneas)

| Método | URI | Controller | Línea | Clase |
|---|---|---|---|---|
| GET | /embajadores/, /dashboard/summary, /clientes**, /metrics**, /video, /comisiones** | DashboardController, ClientesController, MetricsController, VideoController, ComisionesController | 22-43 | 2 (`permission:embajadores.view`) |
| GET/POST | /embajadores/configuracion**, /tiers** | SettingsController, TiersController | 47-59 | 2 (`permission:embajadores.configure`) |
| POST | /embajadores/comisiones/{id}/approve, /cancel | ComisionesController | 63-64 | 2 (`permission:embajadores.commissions.approve`) |
| GET | /api/megafamilia/embajadores/terms | EmbajadorApiController@terms | 74 | 5 (documentado público a propósito — pantalla de registro) |
| GET/POST | /api/megafamilia/embajadores/status, activate, link, share-log, dashboard, notifications-log, red, recompensas(+aplicar), comisiones, share-masivo, prospects** (CRUD+followups) | EmbajadorApiController, EmbajadorExtApiController, NotificationsLogApiController, ProspectsApiController, ProspectFollowupsApiController, ProspectImportApiController | 77-104 | 2 (`auth:sanctum`) |
| GET | /registro (landing) | LandingController@index | 112 | 5 (documentado público a propósito — el embajador la comparte) |

**Sin agujeros.**

### 9. `Planes/routes.php` (90 líneas)

Todo bajo `['web','auth','check_route_permission']` sin prefix de módulo (línea 23-90):
`/internet**`, `/paquetes**`, `/voz**`, `/custom**` (8 rutas CRUD cada uno = 32),
`/planes/contratables**` (9 rutas), `/cliente/contratables/{clientId}/**` (4 rutas).
**Clase 1 en las 45 rutas. Sin agujeros.**

### 10. `Finanzas/routes.php` (89 líneas) — addon distinto de `Payments` (ambos usan prefijo `finanzas` pero sub-rutas distintas, sin colisión)

Todo bajo `['web','auth','check_route_permission']->prefix('finanzas')` (línea 29-89):
`/transacciones**`, `/facturas**`, `/pagos**`, `/invoices**` (9 rutas), `/general-accounting**`
(income/expense/operation/category, 11 rutas). **Clase 1 en las ~19 rutas. Sin agujeros.**

### 11. `WhatsAppAgent/routes.php` (76 líneas)

| Método | URI | Controller | Línea | Clase |
|---|---|---|---|---|
| POST | /whatsapp/webhook/{slug} | WhatsAppWebhookController@handle | 23-24 | 3 (header `X-Webhook-Secret` verificado inline, línea 29-35 del controller; también en `PUBLIC_ROUTES`) |
| GET/POST/PATCH/DELETE | /whatsapp/** (panel, instances**, funciones, api/conversations**, api/technicians, api/instances**+functions, api/functions**, api/send) | WhatsAppPanelController, WhatsAppInstanceController, WhatsAppFunctionController, WhatsAppSendController | 34-75 | 1 (`check_route_permission`, prefix `whatsapp`) |

**Sin agujeros.**

### 12. `VoIP/routes.php` (68 líneas)

**Hallazgo relevante (no agujero, pero merece constancia):** el grupo raíz es
`Route::middleware(['web','auth'])->prefix('voip')` (línea 9) — **sin** `check_route_permission`,
`permission:`, `role:` ni `can:` en `routes.php`, a diferencia de casi todo el resto del lote. A
primera vista clasificaría como agujero (categoría 4), pero verificado contra los controllers:
**cada método de TroncalController, ExtensionController y GrupoTimbradoController abre con
`if (! auth()->user()?->can('voip.*.*')) { ... }`** (26 checks inline distintos, uno por método,
cubriendo el 100% de las rutas) e **IaBotController usa `$this->authorize('voip.ia-bot.*')`** (9
checks, también 100% de cobertura). Verificado método por método contra las rutas declaradas —
no hay ningún método sin su check.

| Sub-área | Rutas | Controller | Línea | Clase |
|---|---|---|---|---|
| Vista principal + troncales | index, troncales/data/store/{troncal}(update/destroy/provisionar/desprovisionar/verificar/toggle), probar-conexion | TroncalController | 12-26 | 3 (`can()` inline, controller líneas 26/36/68/115/172/194/212/227/249/275) |
| extensiones | index/data/usuarios/estados/store/{extension}(update/destroy/provisionar/desprovisionar/toggle/verificar) | ExtensionController | 31-41 | 3 (`can()` inline, controller líneas 22/30/56/84/114/148/167/185/200/288/311) |
| grupos-timbrado | index/data/extensiones-disponibles/store/{grupoTimbrado}(update/destroy) | GrupoTimbradoController | 46-51 | 3 (`can()` inline, controller líneas 18/26/50/61/85/109) |
| ia-bot | index/config(get/save)/conversaciones/leads(+update)/kb(CRUD) | IaBotController | 56-67 | 3 (`$this->authorize()`, controller líneas 19/27/33/59/75/86/103/109/126/143) |

**Sin agujeros** — pero se recomienda (fuera de alcance de este item, solo constancia) agregar
`check_route_permission` al grupo de `routes.php` como defensa en profundidad adicional, ya que
hoy la única barrera es el `can()`/`authorize()` inline: un método nuevo que se añada sin copiar
ese patrón quedaría abierto a cualquier usuario autenticado sin que el grupo de rutas lo detenga.

### 13. `PortalPago/routes.php` (65 líneas)

| Método | URI | Controller | Línea | Clase |
|---|---|---|---|---|
| GET/POST | /f/{token}, /f/{token}/reportar, /f/{token}/estado | PublicPagoController | 20-22 | 5 (público por token + `throttle:10,1`, diseño intencional — liga de pago) |
| GET | /pagos, /pagos/conciliacion, /pagos/cuentas, /pagos/links, /pagos/comprobante/{report} | DashboardController, ConciliacionController, CuentasController, LinksController | 33-39 | 2 (`can:pagos.*` por ruta individual) |
| GET/POST/PUT | /api/pagos/kpis, /conciliacion**, /cuentas**, /links** | mismos controllers | 43-63 | 2 (`can:pagos.*` anidado) |

**Sin agujeros.**

### 14. `WarRoom/routes.php` (59 líneas)

Grupo raíz `['web','auth']->prefix('warroom')` (línea 15) con **todo** su contenido anidado
dentro de `Route::middleware('permission:warroom.view')->group(...)` (línea 17-58) — incluye la
página principal, el lookup `/api/users`, KPIs, insights, meetings (10 rutas), desempeño, y action
items (4 rutas). No queda ninguna ruta fuera de ese bloque. **Clase 2 en las ~22 rutas. Sin
agujeros.**

### 15. `Scheduling/routes.php` (57 líneas)

Todo bajo `['web','auth','check_route_permission']->prefix('scheduling')` (línea 16-57):
redirect de task-templates, `/project**` (7 rutas), `/task**` (17 rutas). **Clase 1 en las ~25
rutas. Sin agujeros.**

### 16. `Mensajes/routes.php` (51 líneas)

Todo bajo `['web','auth','check_route_permission']->prefix('message')` (línea 23-51): `/inbox**`,
`/reminder**`, `/invoice_email**`, `/proforma_invoice_email**` (8 rutas activas; `payment_email`
queda comentado, no registrado — no aplica clasificación). **Clase 1. Sin agujeros.**

### 17. `Tickets/routes.php` (51 líneas)

Todo bajo `['web','auth','check_route_permission']->prefix('tickets')` (línea 17-51): ~26 rutas
(dashboard, abiertos/cerrados/reciclados, CRUD ticket, thread, notificaciones, lookups internos,
estadísticas de dashboard). **Clase 1. Sin agujeros.**

### 18. `CobranzaBlaster/routes.php` (50 líneas)

Grupo raíz `['web','auth']->prefix('cobranza')` (línea 7) — **sin** `check_route_permission` ni
`permission:` en `routes.php`, igual que VoIP. Verificado contra los controllers: **CampanaController**
(8 métodos: index/data/kpis/store/activar/pausar/destroy/llamadas) tiene `auth()->user()->can('cobranza.view'|'cobranza.manage')`
en cada uno (líneas 19/28/52/71/94/111/128/145) y **VoipConfiguracionController** (4 métodos:
index/show/store/testConexion) tiene `can('cobranza.configure')` en cada uno (líneas 16/26/56/116)
— cobertura 100%.

| Sub-área | Rutas | Controller | Línea | Clase |
|---|---|---|---|---|
| Campañas (vistas+JSON) | /, /campanas, /campanas/data, /kpis, store, {id}/activar, /pausar, DELETE {id}, {id}/llamadas | CampanaController | 10-36 | 3 (`can()` inline) |
| VoIP config | /voip, /voip/configuracion (get/store), /voip/test | VoipConfiguracionController | 39-49 | 3 (`can()` inline) |

**Sin agujeros** (misma recomendación que VoIP: agregar `check_route_permission`/`permission:` al
grupo de rutas como defensa en profundidad adicional, fuera de alcance de este item).

### 19. `SmartImportExport/routes.php` (46 líneas)

Todo bajo `['web','auth','check_route_permission']->prefix('configuracion')` (línea 17-46):
`smart-import**` (6 rutas), `smart-export**` (4 rutas), `smart-import-export**` (4 rutas).
**Clase 1. Sin agujeros.**

### 20. `Domiciliacion/routes.php` (45 líneas)

| Método | URI | Controller | Línea | Clase |
|---|---|---|---|---|
| GET/POST/DELETE | /portal/domiciliacion/tarjeta(+enrolar/{card}), /portal/domiciliacion/(show/store/{card} destroy) | PortalDomiciliacionController, DomiciliacionController | 13-20 | 2 (`auth.portal`) |
| GET/POST/DELETE | /domiciliacion/clientes/{clientId}(+/{cardId}), /liga | DomiciliacionController, EnrollmentLinkController | 27-31 | 1 (`check_route_permission`) |
| GET/POST | /d/{token}(+POST con `throttle:5,1` adicional) | EnrollmentLinkController | 41-44 | 5 (público por token + `throttle:30,1`/`5,1`, diseño intencional documentado — liga de enrolamiento) |

**Sin agujeros.**

### 21. `DevTools/routes.php` (39 líneas)

| Método | URI | Controller | Línea | Clase |
|---|---|---|---|---|
| GET/POST | /devtools, /context, /chat, /nav-items | DevToolsController | 25-28 | 2 (`role:DESARROLLADOR\|super-administrator`, exclusión deliberada de `check_route_permission` documentada en el propio archivo) |
| GET | /git/get-tags | GitController@getTags | 38 | 1 (`check_route_permission`) |

**Sin agujeros.**

### 22. `EvaluadorEmpresarial/routes.php` (39 líneas)

| Método | URI | Controller | Línea | Clase |
|---|---|---|---|---|
| GET/POST | /evaluador-empresarial, /guardar (`throttle:5,1`), /enviar-email (`throttle:5,1`), /resultado/{token} | EvaluadorEmpresarialController | 21-26 | 5 (público intencional — cuestionario. Nota: el comentario del archivo dice que deben listarse en `PUBLIC_ROUTES`, pero como el grupo NO incluye `check_route_permission`, esa constante nunca llega a evaluarse aquí; el comentario quedó desactualizado, sin efecto práctico en la seguridad) |
| GET | /admin/evaluaciones-panel, /evaluaciones, /estadisticas, /exportar, /{id} | EvaluadorEmpresarialController | 33-38 | 1 (`check_route_permission`, prefix `admin`) |

**Sin agujeros.**

### 23. `Manual/routes.php` (38 líneas)

| Método | URI | Controller | Línea | Clase |
|---|---|---|---|---|
| GET | /manual | ManualController@view | 21 | 1 |
| GET/POST | /api/manual/sections, /sections/{slug}, /generate | ManualController | 27-29 | 1 |
| GET | /api/manual/help | ManualController@help | 37 | 2-equivalente — deliberadamente solo `['web','auth']` (sin `check_route_permission`), documentado en el propio archivo: "disponible en TODAS las pantallas para CUALQUIER usuario autenticado". Riesgo BAJO (contenido de ayuda contextual, no sensible; no modifica datos) |

**Sin agujeros.**

### 24. `Empresa/routes.php` (36 líneas)

Todo bajo `['web','auth','check_route_permission']` sin prefix de módulo (línea 19-36):
`/empresa/manual`, `/pdf`, `/api/data`, CRUD de capítulos (4 rutas) y secciones (5 rutas,
incluye `/publicar`). **Clase 1 en las 12 rutas. Sin agujeros** (el propio archivo documenta
además re-autorización inline en el controller como defensa en profundidad adicional).

### 25. `VozMayorista/routes.php` (36 líneas, solo 1 ruta activa en este bloque)

| Método | URI | Controller | Línea | Clase |
|---|---|---|---|---|
| GET | /voz-mayorista/ | PanelController@index | 33-35 | 2 (`rol.instancia:operador` a nivel de grupo, línea 28 — middleware verificado registrado en `app/Http/Kernel.php:67` — + `can:voz.panel.view` a nivel de ruta) |

**Sin agujeros.**

### 26. `Hub/routes.php` (25 líneas)

| Método | URI | Controller | Línea | Clase |
|---|---|---|---|---|
| GET | /integraciones (vista) | closure | 8 | 1 (`check_route_permission`) |
| GET/POST/PUT/DELETE | /api/hub/providers, /integrations**, /{id}/validate, /set-default, /rotate, /logs, /usage | ApiIntegrationController | 14-24 | 3 — el grupo de `routes.php` es solo `['web','auth']` (línea 13), pero el **constructor** del controller aplica `$this->middleware('permission:...')` por método (`view-integrations`, `manage-integrations`, `rotate-integration-keys`, `view-integration-logs`, `validate-integrations`; verificado líneas 17-21 del controller) — cobertura 100% de los 11 métodos. |

**Sin agujeros.** Módulo sensible (guarda/rota API keys de Anthropic, Evolution, Pexels, etc.) —
protección confirmada aunque no viva en `routes.php`.

### 27. `Demo/routes.php` (10 líneas)

| Método | URI | Controller | Línea | Clase |
|---|---|---|---|---|
| GET | /api/demo/items | DemoController@index | 9 | 1 (`check_route_permission`) |

**Sin agujeros.**

### 28. `Inversiones/routes.php` (9 líneas)

| Método | URI | Controller | Línea | Clase |
|---|---|---|---|---|
| GET | /inversiones/ | closure (vista) | 8 | 1 (`check_route_permission`) |

**Sin agujeros.**

### 29. `CentroProyecto/routes.php` (8 líneas)

| Método | URI | Controller | Línea | Clase |
|---|---|---|---|---|
| GET | /centro-proyecto/ | CentroProyectoController@index | 7 | 3 — grupo solo `['web','auth']` (línea 6), pero el método abre con `abort_unless(auth()->user()->can('centro-proyecto.view'), 403)` (controller línea 11) |

**Sin agujeros.**

---

## Resumen

- **29/29 archivos auditados**, ~250 rutas inventariadas (incluye las 24 rutas de
  `Route::resource(...)->except('index')` de Vendedores, expandidas conceptualmente en su fila).
- **Categoría 4 (agujero real): 0.** No se encontró ninguna ruta de este lote que modifique
  datos o exponga información sensible sin ninguna capa de protección real.
- **Dos módulos (`VoIP`, `CobranzaBlaster`) declaran su grupo de rutas sin
  `check_route_permission`/`permission:`/`role:`, pero tienen protección `->can()`/`->authorize()`
  inline al 100% de cobertura verificada método por método** — quedan en categoría 3, no 4. Se
  deja registrada la recomendación (fuera de alcance de este item) de sumarles
  `check_route_permission` en el propio `routes.php` como defensa en profundidad, para que un
  método nuevo que olvide el check inline no quede expuesto por descuido.
- **Un módulo (`Hub`) protege por middleware declarado en el constructor del controller**
  (`ApiIntegrationController`) en vez de en `routes.php` — mismo efecto, solo distinto lugar;
  relevante porque gestiona credenciales de proveedores externos (Anthropic, Evolution, Pexels).
- **Comentario desactualizado detectado (sin impacto de seguridad):**
  `EvaluadorEmpresarial/routes.php` referencia `PUBLIC_ROUTES` para sus rutas públicas, pero esas
  rutas nunca pasan por `CheckRoutePermission` (el grupo no incluye ese middleware) — el
  comentario quedó obsoleto de una versión anterior del archivo.
- **Corrección de modelo mental documentada arriba**: `check_route_permission` es fail-closed
  para usuarios no-admin; la ausencia de entrada en `config/route_permission.php` bloquea, no
  abre. El riesgo real que vale la pena vigilar es la ausencia total del middleware/guard, no la
  entrada faltante en el config — eso último es, cuando mucho, una pantalla rota para no-admins.
- Módulos de dinero/pago del lote (`Payments`, `Domiciliacion`, `PortalPago`, `Finanzas`,
  `CobranzaBlaster`) — los 3 webhooks del lote (`SpeiWebhookController`, `WhatsAppWebhookController`,
  `OpenpayWebhookController`) tienen verificación de firma/secreto inline confirmada; ninguna ruta
  de cobro quedó sin alguna capa de protección.

No se cerró #9990764 ni #9990745 (indicación explícita del spec de este item).
