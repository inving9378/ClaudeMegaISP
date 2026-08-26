# Item #159 — Deuda técnica: dos tablas de facturas (`invoices` vs `client_invoices`)

> Investigación solo-lectura + plan de unificación/sincronización. Ninguna tabla ni flujo de
> dinero se tocó al producir este documento — es exactamente el entregable que pide el item
> ("definir tabla canónica y plan de unificación/sincronización").

## 1. Resumen ejecutivo

El sistema tiene **dos tablas de facturas totalmente paralelas, sin ningún puente entre
ellas**, cada una alimentando un universo de consumidores distinto:

| | `client_invoices` (LEGACY) | `invoices` (MODERNA) |
|---|---|---|
| Filas en dev (2026-08-26) | **110,860** | **93,203** |
| Modelo | `App\Modules\Core\Clientes\Models\ClientInvoice` (+ proxy `App\Models\ClientInvoice`) | `App\Models\Invoice` |
| Esquema creado por | dump SQL crudo (`migrations_old/2024_06_02_161716_dump_bd.php` → `database/sql/clientes_ultima_dump.sql`), sin migración `Schema::create` versionada | migración limpia `2025_08_30_065032_create_invoices_table.php` |
| `estado`/`status` | `varchar(255)` libre: `Pagado`, `Atrasado`, `Partially paid`, `impagado`, `Pagar (del saldo de la cuenta)`, `Pagado (del saldo de la cuenta)` | `enum`: `draft`, `issued`, `partially_paid`, `paid`, `overdue`, `cancelled` |
| Fechas | `payment_date`/`document_date` **VARCHAR** (formato `d/m/Y`, sin validar) | `payment_date`/`due_date` tipo `date` real |
| FK a `clients` | Lógica (`belongsTo`) pero **sin constraint en BD** | `foreignId('client_id')->constrained()->onDelete('cascade')` real |
| Relación a servicios | `morphedByMany` a `ClientBundleService`/`ClientInternetService`/`ClientVozService`/`ClientCustomService` vía `client_serviceable` | ninguna (solo `items()` → `InvoiceItem`) |
| Quién la alimenta HOY | Flujo de cobro **vivo**: Portal de Pago (SPEI/CEP), Portal Cliente (OpenPay), pagos reportados por WhatsApp, captura manual histórica | Cron diario `invoice:create-proformas` (03:00) + alta manual desde Finanzas (`InvoiceController::createForClient`) |

**No existe ningún job, observer o comando que sincronice una tabla con la otra.** Un pago que
entra por SPEI/Portal de Pago marca `client_invoices.estado='Pagado'` pero **nunca toca**
`invoices`; un pago capturado en la ficha del cliente ("Crear Gasto") actualiza
`invoices.pending_balance`/`status` vía `InvoiceService::updateProformaInvoicePendingDespuesDeUnPago()`
pero **nunca toca** `client_invoices`. Son dos ledgers independientes que conviven sobre el
mismo cliente sin reconciliarse — el riesgo de divergencia que describe el item ya está
materializado estructuralmente, no es solo una posibilidad teórica.

## 2. Evidencia — quién consume cada tabla

### `client_invoices` (24 archivos con `ClientInvoice::`, 10 con `DB::table('client_invoices')`)
- **Cobro vivo:** `PaymentApplicationService.php` (Payments), `PortalPagoController`/`ConciliacionService`/`CepValidatorService`/`LinksController`/`PortalPagoPaymentLink` (PortalPago — SPEI), `PortalCliente\Controllers\{PortalPagoController,FacturasController,DashboardController,OpenpayWebhookController}` (tarjeta OpenPay), `DomiciliacionCobrarCommand`.
- **CRUD legacy:** `Core\Clientes\Controllers\ClientInvoiceController` + `Repositories\ClientInvoiceRepository`.
- **Reportes/paneles:** `WarRoom\KpiController`/`InsightsService`, `Dashboard\HomeController`, `ConfigFinanceNotificationService`.
- **Import:** `SmartImportExport\SmartImportService`.

### `invoices` (8 archivos con `Invoice::`, 2 con `DB::table('invoices')`)
- **Finanzas (admin manual):** `Addons\Finanzas\Controllers\Invoice\InvoiceController` (`markAsPaid`, `createForClient`, `editPeriod`).
- **Generación automática:** `Console\Commands\Active\CreateProformaInvoiceCommand` (cron 03:00) + `CreateProformaInvoiceOverdueClientActiveCommand`.
- **Servicios:** `Services\Finance\Invoice\{InvoiceService,AvailablePeriodsService}`, `Services\Finance\Billing\BillingDocumentService`, `Services\Cobranza\InvoiceAgingService`.
- **Puente parcial (una sola dirección):** `Core\Clientes\Controllers\ClientPaymentController` — al capturar un pago manual en la ficha del cliente, llama `InvoiceService::updateProformaInvoicePendingDespuesDeUnPago()`, que sí actualiza `invoices.pending_balance`/`status`. Es el único punto del sistema que escribe en `invoices` como reacción a un pago — y aun así **no toca `client_invoices`** en el mismo flujo.

## 3. El intento previo de puente — por qué se abandonó (contexto ya en el código)

`PaymentApplicationService::matchPendingInvoice()` (`app/Modules/Addons/Payments/Services/PaymentApplicationService.php:166-187`)
existe desde un ciclo anterior del roadmap (comentarios internos referencian #191/#193) como un
intento de que el motor de pagos SPEI matcheara automáticamente contra `client_invoices`. Quedó
**deliberadamente desconectado** de `applyPayment()` — el propio código lo marca:

> "⚠️ DESCONECTADO del default de applyPayment — sigue SIN llamadores; NO invocar... sin decisión
> aparte de Irving para reconectarlo (eso es dinero en vivo, fuera de alcance de este item)."

Motivo histórico: el matcher viejo enlazaba el pago a `paymentable_type=ClientInvoice`, lo cual
salta el `PaymentObserver`→`PaymentClientJob` (solo dispara para `paymentable_type=Client`) → el
pago quedaba sin abonar saldo ni dejar transacción. Es la prueba concreta de que tocar esta unión
sin control estricto rompe dinero real. Esto confirma que cualquier trabajo de unificación cae en
la frontera dura de dinero y necesita decisión explícita de Irving, no una implementación
automática del circuito.

## 4. Opciones de plan de unificación/sincronización (para decisión de Irving)

No se elige ni se ejecuta ninguna aquí — son las rutas viables, con su costo/riesgo relativo:

**Opción A — `invoices` como tabla canónica futura, migración gradual de los consumidores vivos.**
Mejor esquema (enum, FK real, tipos de fecha reales). Requiere: (1) job de sincronización
transicional que refleje el estado vivo de `client_invoices` en `invoices` mientras dura la
migración, (2) mover los flujos de cobro vivo (Portal de Pago SPEI, OpenPay, WhatsApp) a escribir
en `invoices`, (3) resolver que `invoices` no tiene la relación morph a
`client_bundle_services`/`client_custom_services` que sí usa `ClientInvoice` — hay que agregarla o
resolverla distinto. Mayor esfuerzo, mejor resultado a largo plazo.

**Opción B — `client_invoices` como canónica (es la que ya vive el cobro real), `invoices` pasa a
ser solo un borrador que se "materializa" en `client_invoices` al confirmarse.** Menor esfuerzo
relativo (no hay que tocar el motor de cobro vivo), pero conserva el esquema débil (VARCHAR
libre, fechas VARCHAR, sin FK), y exige reescribir el cron de proformas + Finanzas para escribir
sobre la tabla legacy.

**Opción C — No fusionar todavía; cerrar solo el riesgo de divergencia con una auditoría
read-only periódica** que detecte clientes/periodos donde ambas tablas cuentan una historia
distinta (p. ej. proforma `invoices` en `paid` sin `client_invoices` equivalente marcado, o
viceversa), sin tocar ningún dato. Es aditivo y de bajo riesgo, pero no resuelve la deuda de
fondo — solo la vuelve visible antes de que cause un problema real de cobranza.

**Recomendación de este documento (no ejecutada):** Opción C como paso inmediato de bajo riesgo
(visibilidad sin tocar dinero), seguida de Opción A a mediano plazo por calidad de esquema —
pero la semántica exacta de "qué cuenta como divergencia" toca datos de facturación/cobro real y
debe validarla Irving antes de construir la lógica de matching (mismo tipo de trampa que ya
atrapó al matcher viejo de la sección 3).

## 5. Siguiente paso

Se registra como sub-item del roadmap (nivel C — decisión de diseño de Irving, toca dinero) la
implementación de la opción elegida. Este item #159 se cierra con el hallazgo + plan documentado,
que es su alcance declarado.
