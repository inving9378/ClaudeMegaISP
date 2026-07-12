# Módulo Dashboard

> Página de inicio del sistema con indicadores generales. `app/Modules/Core/Dashboard/` · slug `core-dashboard` · módulo **core** (siempre activo).

## 0. En simple
Es la pantalla de bienvenida que ves al entrar al sistema: te muestra de un vistazo cuántos clientes, tickets, pagos y servidores necesitan tu atención hoy.

## 1. Qué es
Módulo **core** que sirve la vista `/` (y `/home`) con el panel de control principal: tarjetas de indicadores (clientes en línea, clientes nuevos, tickets abiertos, dispositivos sin respuesta) y bloques de estadísticas (clientes, tickets, finanzas, servidor), más un set de endpoints de estadísticas de ventas/prospectos usados por otras pantallas del sistema (p. ej. estadísticas de vendedores).

## 2. Para qué sirve
Le da a cualquier usuario logueado (admin, mostrador, vendedor) un resumen accionable al entrar al sistema, sin tener que navegar módulo por módulo: estado de la red/clientes, tickets pendientes, cobranza del día, salud del servidor. Los usuarios con rol `client` ven una vista distinta (placeholder, sin datos operativos).

## 3. Cómo funciona
- **Controllers** (`Controllers/`): `HomeController` (vista principal + 5 endpoints AJAX de tarjetas/bloques) y `StaticsController` (11 endpoints de estadísticas de ventas/prospectos, reutilizados fuera del dashboard).
- **`HomeController::index()`** decide la vista según `auth()->user()->isClient()`: `core-dashboard::started-page` (dashboard completo, Vue) para staff, `core-dashboard::started-page-client` (placeholder "Vista de cliente") para clientes.
- **Sin modelos ni tablas propias** — el módulo solo agrega/consulta datos de otros módulos (no tiene `Models/`, `Repositories/` ni `migrations/` propias, salvo carpetas vacías reservadas):
  - `getHomeStatisticsForTarjetsByStatus()` — cuenta clientes online/nuevos (`ClientMainInformation`) y tickets nuevos (`Ticket`).
  - `getStatisticsForTextCardInDashBoard()` — facturas y transacciones creadas hoy (`ClientInvoice`, `Transaction`).
  - `getStatsCardClientInDashBoard()` — desglose de clientes por estado + "toca cobrar/suspender hoy" vía `ClientRepository`.
  - `getStatsCardTicketsInDashBoard()` — desglose de tickets por estado.
  - `getStatsCardFinanceInDashBoard()` — resumen financiero del mes actual vs. anterior vía `App\Services\FinanceService::getInfoFinanceDashboard()`.
  - `getStatsCardServerInDashBoard()` — salud del servidor (CPU/RAM/disco/uptime/último backup) vía `App\Services\ServerInfoService::serverInform()`.
  - `StaticsController` — ventas/prospectos por rango de fecha, por medio, comparativa mes actual/anterior, ranking de vendedores; consulta `ClientMainInformation` y `App\Modules\Core\CRM\Models\CrmLeadInformation`.
- **Rutas** (`routes.php`): `/`, `/index` y los 5 endpoints `/get-*-card-in-dashboard-c` bajo `['web','auth','check_route_permission']`; el bloque `/statics/*` (11 endpoints) igual; `/home` (nombrada `home`) solo bajo `['web','auth']` (sin `check_route_permission`, `auth` ya lo aplica el constructor de `HomeController`).
- **Frontend:** árbol Vue en `resources/js/components/module/dashboard/` — `Dashboard.vue` (orquesta las tarjetas y bloques, filtra cada uno por permiso `dashboard_view_*` vía `hasPermission`, hace polling del bloque servidor cada 3s con corte a las 500 vueltas → recarga la página), `CardStats.vue`/`CardTextDashboard.vue`/`CardStatsFinance.vue` (presentación), `helper/request.js` (llamadas Axios a los 5 endpoints de `HomeController`). Registrado en `resources/js/app.js`; montado desde `views/started-page.blade.php` (`@extends('core-layout::master')` + `<dashboard></dashboard>`).
- **Deuda conocida** (`MIGRATION.md`): los permisos `dashboard_view_card_client_inline`/`_client_new`/`_tickets_open_new`/`_device_not_responding` usados en el código no tenían migración propia sembrándolos (ver también migraciones archivadas `add_permision_dashboard` en `database/migrations_old/`); no hay todavía un contrato `DashboardWidget` para que otros módulos aporten tarjetas de forma desacoplada (hoy todo vive hardcodeado en `HomeController`).

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Vistas:** `/` , `/index`, `/home` (dashboard principal, distinto para staff vs. cliente).
- **Endpoints AJAX del dashboard** (POST, bajo `check_route_permission`): `/get-home-statistics-for-tarjets-by-status-c`, `/get-home-statistics-for-text-card-in-dashboard-c`, `/get-stats-client-card-in-dashboard-c`, `/get-stats-ticket-card-in-dashboard-c`, `/get-stats-finance-card-in-dashboard-c`, `/get-stats-server-card-in-dashboard-c`.
- **Endpoints de estadísticas de ventas/prospectos** bajo `/statics/*` (POST): `sales-and-prospects[/{id}]`, `sales-by-medium[/{id}]`, `compare-sales[/{id}]`, `prospects-by-status[/{id}]`, `ranking-sales`, `total-prospects`, `total-sales`, `total-lost-sales`. Consumidos también fuera del dashboard (p. ej. el módulo de estadísticas de vendedores, `resources/js/components/module/vendors/statistics/`).
- **Permisos** `dashboard_view_card_client_inline`, `dashboard_view_card_client_new`, `dashboard_view_card_tickets_open_new`, `dashboard_view_card_device_not_responding`, `dashboard_view_info_invoice_transaction`, `dashboard_view_block_client`, `dashboard_view_block_ticket`, `dashboard_view_block_finance` (gatean cada tarjeta/bloque en el frontend vía `hasPermission.data.canView`).

**Consume**
- **Clientes** — `ClientMainInformation` (estados, altas por fecha), `ClientRepository` (cobranza/suspensión del día, total de clientes) del módulo Clientes.
- **Facturación/Pagos** — `ClientInvoice`, `Transaction`, y `App\Services\FinanceService` (resumen financiero mensual).
- **Tickets** — modelo `Ticket` (conteos por estado) del módulo Tickets.
- **CRM** — `App\Modules\Core\CRM\Models\CrmLeadInformation` (prospectos, para las estadísticas de ventas).
- **Infra** — `App\Services\ServerInfoService` (métricas de sistema operativo/servidor, incluida la fecha del último backup de BD).
- **Permisos/sesión** — `auth()->user()->isClient()` (módulo Auth/Usuarios) para decidir la vista; helper Vue `allViewHasPermission`/`Permission` para gatear tarjetas en el frontend.

> _Servicios compartidos únicos: este módulo no monta WhatsApp/IA/Mapas propios; no consume ninguno de los tres tampoco — es puramente de lectura/agregación sobre datos de otros módulos._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
