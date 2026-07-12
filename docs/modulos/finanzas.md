# Módulo Finanzas

> `app/Modules/Addons/Finanzas/` · slug `addon-finanzas` · módulo **addon** (activo).

## 0. En simple
Es donde el sistema junta el dinero del ISP: las facturas de los clientes, los pagos que entran, los movimientos de caja y los gastos/ingresos generales del negocio.

## 1. Qué es
Módulo addon que centraliza la vista de **facturación, pagos, transacciones y contabilidad general** del ISP: listados con filtros/exportación de cada rubro, acciones sobre facturas (crear proforma, enviar por email, imprimir PDF, marcar pagada, editar periodo, borrar) y captura manual de ingresos/gastos operativos con su balance.

## 2. Para qué sirve
Le da al personal de cobranza/administración (Diana, Ariana, Tere y roles con permiso `finance_*`) un solo lugar para ver cuánto se facturó, qué se cobró y qué falta por cobrar, sin tener que entrar a la ficha de cada cliente uno por uno; y a quien lleva la contabilidad, un registro simple de ingresos/gastos no automatizados con su balance para el estado de resultados.

## 3. Cómo funciona
- **Controllers** organizados en 4 sub-dominios (namespaces de 3 niveles, URL prefix `finanzas/*` preservado por compatibilidad con el frontend):
  - `Invoice/` — `FinanceInvoiceController` (listado simple de facturas, modelo `App\Models\Invoice`) e `InvoiceController` (extiende `CrudModalController`; lógica real: `createForClient` genera una proforma vía `InvoiceService::createProformaInvoice()`, `sendInvoice` renderiza y manda por email la plantilla `FacturaProforma`, `printInvoice` genera el PDF (DomPDF), `markAsPaid` liga la factura a un `Payment` existente, `editPeriod` cambia el periodo de una factura y su pago asociado, `getPendingByClient`/`getAvailablePeriodsByClient` alimentan combos del frontend, `destroy` borra).
  - `Payment/` — `FinancePaymentController`, listado de pagos (modelo `App\Models\Payment`).
  - `Transaction/` — `FinanceTransactionController`, listado de movimientos (modelo `App\Models\Transaction`).
  - `GeneralAccounting/` — `GeneralAccountingController` (dashboard: `getData`/`getBarData`/`getDonutData` agregan `GeneralAccountingIncome`/`GeneralAccountingExpense` por categoría dinámica y por mes) + sub-controllers `Income/`, `Expense/`, `Operation/`, `Category/` (altas/bajas vía datatable, cada uno con su `*DatatableHelper` en `app/Http/HelpersModule/module/finance/`).
- Los controllers de listado (`Finance*Controller`) son delgados: delegan el fetch de la tabla a un `*DatatableHelper` inyectado y usan `includeLibraryDinamic()` (patrón CRUD estándar del sistema) para resolver campos dinámicos de la vista.
- **Sin `Models/`/`Repositories/`/`Services/` propios** (carpetas reservadas vacías) — el módulo reusa modelos globales de `app/Models/` (`Invoice`, `InvoiceItem`, `Payment`, `Transaction`, `GeneralAccountingIncome/Expense/Category/Operation`, `MethodOfPayment`, `PaymentDetail`, …) y servicios de `app/Services/Finance/` (`InvoiceService`, `AvailablePeriodsService`, `BillingDocumentService`, `GeneralAccountingService`, `Timbrado/*`).
- **Rutas** (`routes.php`) bajo `['web','auth','check_route_permission']`, prefijo `finanzas`: `transacciones/*`, `facturas/*`, `pagos/*`, `invoices/*` (acciones de `InvoiceController`) y `general-accounting/*` (+ sub-prefijos `income`/`expense`/`operation`/`category`). El comentario del archivo aclara que `web` se agrega a mano porque `loadRoutesFrom()` no aplica ese grupo automáticamente.
- **Generación automática de proformas:** el comando `invoice:create-proformas` corre por cron diario a las 03:00 (`Kernel.php`) — separado del alta manual (`createForClient`) que usa este módulo.
- **Frontend:** Vue en `resources/js/components/module/finance/` — `invoice/InvoiceListar.vue`, `payment/PaymentListar.vue`, `general_accounting/GeneralAccountingIndex.vue` (+ `components/` con `Dashboard`, `Balance`, `Add{Income,Expense,Operation,Category}`), `ManualPaymentCapture.vue`, `ReconciliationQueue.vue`, `PaymentMethods.vue`. Vistas Blade en `resources/views/meganet/module/finance/{invoice,invoice_new,payment,transaction,general_accounting}/`.
- Cada tabla usa el patrón datatable estándar del sistema: vista Blade con `actions.blade.php`/columnas custom (`resources/views/meganet/shared/table/module/finance*`) + helper PHP que arma la query.

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Vistas** (`GET`): `/finanzas/facturas`, `/finanzas/pagos`, `/finanzas/transacciones`, `/finanzas/general-accounting`, `/finanzas/invoices`.
- **Endpoints de acciones sobre facturas** (bajo `/finanzas/invoices/*`): `create-for-client/{id}`, `send/{id}`, `print/{id}` (PDF), `mark-as-paid/{id}`, `edit-period/{id}`, `get-pending-by-client/{id}`, `get-available-periods-by-client/{id}`, `destroy/{id}`.
- **Endpoints de contabilidad general**: `general-accounting/get-data`, `/get-bar-data`, `/get-donut-data` (balance/series por categoría y mes) y `income|expense|operation|category` con `table`/`add`/`destroy`.
- **Permisos** `finance_view_billing`, `finance_view_invoices`, `finance_edit_billing`, `finance_delete_billing`, `finance_export_billing`, `finance_view_payments`, `finance_edit_payments`, `finance_delete_payments`, `finance_export_payments`, `finance_view_transactions`, `finance_edit_transactions`, `finance_delete_transactions`, `finance_export_transactions`, `finance_view_general_accounting[_income|_expense]`, `finance_add/edit/delete_general_accounting_income/expense` (declarados en `module.json`, gatean rutas vía `check_route_permission` y el sidebar).
- **Entradas de menú/admin card** "Finanzas" (Facturas, Pagos, Transacciones, Contabilidad) y secciones de configuración `finanzas_facturacion`/`finanzas_contabilidad` consumidas por el catálogo DB-driven de módulos (`module.json` → `config_sections`/`screens`, con términos/pasos para la IA de ayuda).

**Consume**
- **Clientes** — `ClientRepository::calculateAmounts()` (calcula subtotal/tax/total a facturar según los servicios activos del cliente) y el modelo `Client` del módulo Clientes.
- **Payments (addon separado)** — `PaymentReferenceService::ensureFor()` (asegura la referencia MEG del cliente al crear una proforma, red de seguridad para clientes que nacieron por import). La conciliación de pagos por WhatsApp/IA, captura de pago reportado y `ReconciliationTicket` viven en `app/Modules/Addons/Payments/` (módulo hermano, no documentado aquí).
- **Configuración** — `CompanyInformationRepository` (datos de la empresa para el PDF/email de la factura), `ConfigFinanceNotificationRepository` (plantilla de notificación `proforma_invoice`), `EmailConfigService` (arma el email a enviar).
- **Notificaciones** — `StandardNotification` + canal `mail` de Laravel para el envío de la factura proforma al cliente.
- **PDF** — `Barryvdh\DomPDF` para imprimir la factura (`FacturaProformaPdf`).
- **Logs** — `App\Services\LogService` registra en la bitácora del cliente cada envío/marcado-como-pagada de factura.
- **Scheduler** — el cron `invoice:create-proformas` (03:00 diario, `Kernel.php`) genera las proformas automáticas del corte mensual; este módulo solo expone la creación **manual** (`createForClient`).

> _Servicios compartidos únicos: este módulo no monta WhatsApp/IA/Mapas propios; el envío de facturas usa el canal `mail` estándar de Laravel, no una integración propia._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
