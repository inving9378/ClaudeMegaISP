# Módulo Clientes

> Gestión integral de clientes activos del ISP. `app/Modules/Core/Clientes/` · slug `core-clientes` · módulo **core** (siempre activo).

**En simple:** es la ficha central de cada cliente del ISP — ahí se ve y se gestiona todo lo suyo: sus datos, sus servicios contratados, sus pagos y su estado.

## 0. En simple
Es la ficha central de cada cliente del ISP: ahí viven sus datos, sus servicios contratados, su facturación y su estado (activo, moroso, suspendido).

## 1. Qué es
Módulo **core** que modela al **cliente** del ISP (`Client` + `ClientMainInformation`) y todo lo que cuelga de él: servicios contratados (Internet, VoIP, Custom, Bundle), facturación, pagos, documentos, red (IP/Mikrotik), tickets y estadísticas. Es el módulo más consumido del sistema — casi todos los demás módulos (Facturación, Pagos, Tickets, CRM, Talento, Embajadores, PortalCliente, CobranzaBlaster, etc.) referencian al `Client` para saber "de quién estamos hablando".

## 2. Para qué sirve
Le da a mostrador/administración/cobranza un lugar único para: dar de alta un cliente nuevo (datos + domicilio + servicio contratado), buscarlo y abrir su ficha, ver su saldo y estado de morosidad, gestionar sus servicios y promociones, subir/generar sus documentos y contratos, y consultar sus pagos/facturas/estadísticas de ping — todo desde una sola pantalla con pestañas.

## 3. Cómo funciona
- **Modelo central:** `App\Modules\Core\Clientes\Models\Client` (tabla `clients`, soft-deletes) — es el "hub": relaciones a `client_main_information` (datos personales/contacto/domicilio, modelo `ClientMainInformation`), `billing_configuration`, `billing_address`, `payments`, `client_invoices`/`invoices`, `transactions`, `balance`, `tickets`, `internet_service`/`voz_service`/`custom_service`/`bundle_service` (los 4 tipos de plan), `documents`, `network_ip`, `mikrotik_client_ppoe`/`mikrotik_client_hostpot_user`, `pingStatistics`, `commissions_details`, `boxes`, `inventoryItemStocks`, entre otras.
- **Controllers** (`app/Modules/Core/Clientes/Controllers/`, ~20): `ClientController` (alta/edición/listado/borrado/saldo/promociones), `DashboardController` (indicadores), `ClientInformationController` (ficha con saldo, estado, tickets abiertos), `ClientBundleServiceController`/`ClientCustomServiceController`/`ClientInternetServiceController`/`ClientVozServiceController` (los 4 tipos de servicio), `ClientBillingAddressController`/`ClientBillingConfigurationController`/`ClientBillingRemindersConfigurationController`, `ClientFiscalDataController`, `ClientInvoiceController`/`ClientInvoiceServiceController`, `ClientPaymentController`/`ClientPaymentServiceController`, `ClientPlanPromotionController`, `ClientPingController`, `ClientStatisticsController`, `ClientTransactionController`, `DocumentClientController` (documentos + generación de contrato).
- **Repositorio:** `app/Modules/Core/Clientes/Repositories/` (uno por sub-entidad: `ClientRepository`, `ClientMainInformationRepository`, `ClientBundleServiceRepository`, etc.) — capa sobre Eloquent para queries no triviales, siguiendo el patrón repositorio del proyecto.
- **Servicios:** `app/Modules/Core/Clientes/Services/` — lógica de dominio pesada: `ClientBillingService`, `ClientMainInformationService`, `BillingExpirationService`, `BillingPaymentDateService`, `ContractClientService` (generación de contratos), `PromisePaymentClientService` (promesas de pago), `SuspendService` (suspensión por morosidad), `UpdateStartAndFinishDateService`, más un servicio por tipo de plan (`ClientBundleService`/`ClientCustomService`/`ClientInternetService`/`ClientVozService`).
- **Rutas:** todas bajo `/cliente/*` (`app/Modules/Core/Clientes/routes.php`), con middleware `['web','auth','check_route_permission']` (autorización por URL vía spatie/laravel-permission).
- **Frontend:** un solo árbol de componentes Vue en `resources/js/components/module/client/` — `ClientCrud.vue` es la ficha con pestañas (info, billing, document, service, statistics...), `AddClientCrud.vue` el alta, `DatatableClient.vue`/`ClientFilters.vue` el listado, `DashboardClient.vue` los KPIs. Otros módulos extienden la ficha vía el mecanismo `client_tab` (infra documentada en memoria del proyecto).

## 4. Qué EXPONE / qué CONSUME
**Expone**
- **Rutas web** bajo `/cliente/*`: dashboard (`/cliente`), listado (`/cliente/listar`), alta (`/cliente/crear`), edición, saldo, promociones, servicios por tipo, documentos/contratos, y decenas de endpoints AJAX de soporte (filtros, estatus, periodo de pago, etc.).
- **58 permisos** `client_*` (view/add/edit/delete por sub-entidad — dashboard, cliente, servicios, documentos, facturación...).
- El **modelo `Client`** en sí: es el punto de anclaje (`client_id`) que consumen prácticamente todos los demás módulos de negocio.
- **Menú** Clientes en el sidebar (Dashboard / Listar / Crear).

**Consume**
- **Usuarios** (`user`, `user_seller`, `user_system`) — dueño de cuenta, vendedor asignado, usuario de sistema.
- **Facturación/Pagos** — `Invoice`, `Payment`, `Transaction`, `Balance`, `Receipt` (módulos de facturación/pagos).
- **Red** — `NetworkIp`, `MikrotikClientPpoe`, `MikrotikClientHostpotUser`, `PingStatistic`/`DailyPingStatistic` (integración MikroTik).
- **Tickets** — relación `tickets`/`tickets_open`/`tickets_closed` (módulo Tickets).
- **Inventario** — `InventoryItemStock` (custodia de equipo instalado en el cliente).
- **Comisiones** — `CommissionDetail` (módulo de vendedores/comisiones).
- **Config de campos/columnas** — `getRequestAndStoreMethod()` para integración con SmartImport (import/export de módulos).

> _Servicios compartidos únicos: este módulo no monta WhatsApp/IA/Mapas propios; consume los adaptadores existentes cuando los necesita (p. ej. geolocalización del domicilio vía el mapa compartido)._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
