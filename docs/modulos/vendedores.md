# Módulo Vendedores

> Gestión de la fuerza de ventas del ISP: comisiones, cortes de caja, prospectos y estadísticas de venta. `app/Modules/Addons/Vendedores/` · slug `addon-vendedores` · addon activo.

## 0. En simple
Es la parte del sistema donde se lleva el control de los vendedores: cuánto venden, cuánto ganan de comisión, sus gastos y cortes de caja mensuales, y los clientes potenciales (prospectos) que traen.

## 1. Qué es
Módulo que administra vendedores (internos/externos), sus reglas de comisión, cortes de caja mensuales (instalaciones, gastos, ingresos extra) y el seguimiento de prospectos hasta que se convierten en clientes.

## 2. Para qué sirve
Le sirve a los vendedores para llevar su corte de caja del mes y su seguimiento de prospectos, y a administración/finanzas para calcular y liquidar comisiones, controlar el estatus/tipo de cada vendedor y ver estadísticas y rankings de ventas por período.

## 3. Cómo funciona

**Piezas clave (legacy — tablas creadas antes del sistema modular, ver `MIGRATION.md` del módulo):**
- `sellers` — vendedor (liga a `users` por `user_id`), con `status_id` (Activo/Inactivo/Suspendido, tabla `seller_status`), `type_id` (Interno/Externo, tabla `seller_types`) y `balance`.
- `commissions_rules` / `commissions_rules_sellers` / `history_sellers_rules` — reglas de comisión (porcentaje, monto fijo, por plan, bono mensual, comisión a distribuidor) y el historial de qué regla tuvo asignada cada vendedor.
- `commissions` / `commissions_details` — comisiones calculadas y su detalle por regla.
- `cut_boxs` (+ `cut_installations`, `cut_extras_incomes`, `cut_suppliers_expenses`, `cuts_observations`) — el **corte de caja**: registro mensual del vendedor con instalaciones que generan comisión, gastos a proveedores, ingresos extra y observaciones; se cierra una vez al mes y calcula la comisión final.
- `prospects` / `prospect_followups` — prospectos asignados al vendedor y su historial de seguimiento hasta la conversión a cliente.
- `payment_sellers` / `transaction_sellers` — pagos liquidados al vendedor y sus movimientos.
- `discounts` — descuentos aplicables en el cálculo de comisión.

**Controllers** (dos sub-dominios aplanados por decisión de arquitectura 2026-05-20, pendiente de unificar):
- `Controllers/Sellers/*` (prefijo de ruta `sellers/*`): `SellerController` (alta/edición/estatus/tipo), `BoxController` (caja activa, cierre, PDF), `InstallationController`, `ExtraIncomeController`, `SuppliersExpensesController`, `ObservationsController` (líneas del corte de caja).
- `Controllers/Vendors/*` (prefijo `vendedores/*`): `VendorController`, `Sellers\SellerController` (datos por vendor), `Sales\SaleController`, `Prospects\ProspectController`, `Billing\{TransactionController, CommissionRuleController, SellerTransactionController, PaymentSellerController, PaymentClientController, RangeSaleController}`.

**Servicios de dominio:**
- `App\Services\CalculateBalanceSellerService` — motor de cálculo de comisiones vía `Illuminate\Pipeline\Pipeline` con pipes encadenados: `FixedSalaryPayment → SalesCommissionPayment → AdditionalSalesCommissionPayment → DistributorsCommissionPayment → DiscountPayment` (más `MonthlyBonusPayment` en el catálogo de pipes). Cada pipe agrega su parte al balance del vendedor sobre los datos de clientes/pagos/contratos del período.
- `App\Http\HelpersModule\module\sellers\seller\SellerDatatableHelper` — helper del datatable de listado.

**Flujo principal:** el vendedor (o admin) abre su corte de caja del mes (`cut_boxs`), registra instalaciones (altas que generan comisión), gastos a proveedores e ingresos extra; al cerrar el corte se corre `CalculateBalanceSellerService` con la regla de comisión asignada al vendedor (`commissions_rules_sellers`) y se calcula el balance final, que luego se liquida como `payment_sellers`. En paralelo, los prospectos asignados se dan seguimiento (`prospect_followups`) hasta convertirse en cliente. Reglas de comisión y rangos de venta (categorías bronce/plata/oro por volumen) se configuran desde `Configuración → Reglas de Comisiones` / `Rangos de Venta` (`app/Modules/Core/Configuracion/Controllers/{TypeSeller,StatusSeller}`).

**Rutas:** declaradas en `routes.php` del módulo bajo `middleware(['web','auth','check_route_permission'])`, con dos prefijos (`sellers/*` legacy y `vendedores/*` pendiente de migrar). Los endpoints que solo devuelven vistas Blade (`edit`, `showView`, PDFs) se omiten del catálogo `api_endpoints` del `module.json` (documentado en `MIGRATION.md`).

**Permisos:** ~34 permisos Spatie con prefijo `seller_*`/`selleritems_*` (ver `module.json`), granulares por acción (ver, agregar, editar, eliminar) y por sub-recurso del corte de caja (instalaciones, gastos, ingresos extra, comentarios).

## 4. Qué EXPONE / qué CONSUME

**Expone**
- Endpoints internos autenticados (`web`+`auth`+`check_route_permission`) bajo `/sellers/*` y `/vendedores/*`: CRUD de vendedores, caja activa/cierre/PDF del corte, líneas del corte (instalaciones/gastos/ingresos extra/observaciones), prospectos del vendedor autenticado, ventas totales, ranking por rango de fechas, comisiones mensuales por vendedor, historial y alta/baja de pagos a vendedor. Lista completa con `scope`/`permission` en `module.json` → `api_endpoints`.
- Menú "Vendedores" en el sidebar (Dashboard, Vendedores, Cortes, Prospectos, Estadísticas) gateado por `seller_view_panel` + permisos por hijo.
- Config sections en `Configuración`: Reglas de Comisiones (`vendedores_reglas`) y Rangos de Venta (`vendedores_rangos`).
- Bloque de conocimiento/acciones para el asistente IA (`module.json` → `ai`): intents como "ver ventas del mes", "cerrar caja de un vendedor", acciones marcadas como sensibles (`seller_cuts_close_box`, `seller_delete_seller`, `seller_delete_payment`).
- Pestaña de ficha de cliente: **diferida** (`client_tab.deferred=true` en `module.json`) — el vendedor tiene relación real con clientes (`Seller` → `ClientMainInformation`), pero aún no existe la infraestructura de pestañas extensibles de la ficha para exponerla ahí.

**Consume**
- `App\Models\{Client, ClientMainInformation, Payment, DurationContract}` y `App\Modules\Core\CRM\Models\Crm` — el cálculo de comisión (`CalculateBalanceSellerService`) lee datos de clientes, pagos y contratos de otros módulos para determinar ventas e instalaciones del período.
- Sistema de permisos Spatie (`check_route_permission` + permisos declarados en `module.json`) — no implementa autorización propia.
- Generación de PDF (corte de caja, credencial del vendedor) vía la infraestructura de reportes/PDF compartida del sistema.
- No consume WhatsApp, IA ni mapas (los tres servicios compartidos únicos documentados en `CLAUDE.md`) — es un módulo autocontenido de negocio sobre datos de clientes/pagos ya existentes.

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
