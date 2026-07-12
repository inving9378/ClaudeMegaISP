# Módulo Configuración

> Ajustes globales del sistema: empresa, pagos, notificaciones, API móvil y catálogos operativos. `app/Modules/Core/Configuracion/` · slug `core-configuracion` · módulo core, activo.

**En simple:** es el panel donde el equipo ajusta cómo funciona el sistema por dentro — datos de la empresa, formas de pago, plantillas y catálogos — sin tocar código.

## 1. Qué es
Módulo **core** que agrupa las pantallas de configuración administrativa de MegaISP: datos de la empresa, campos adicionales, métodos/reglas de pago, notificaciones (email/financieras/recordatorios), catálogos operativos (nomenclatura, equipos, plantillas de tareas y verificación, medios/tipos/estados de vendedor) y la administración de la **API Móvil** (config, tokens Sanctum, docs, logs). También aloja, por razones históricas, algunas pantallas de otros módulos (créditos de Google Maps, reglas de comisión, rangos de venta) montadas bajo el mismo prefijo `/configuracion`.

## 2. Para qué sirve
Le da al **staff administrativo** (permisos `config_view_*`, uno por sub-sección) un único lugar para parametrizar el sistema: desde datos fiscales que aparecen en facturas hasta qué tokens de la app móvil están activos. Evita que estos ajustes vivan hardcodeados o dispersos, y centraliza catálogos que consumen otros módulos (planes, ventas, scheduling, red).

## 3. Cómo funciona
- **Rutas** (`routes.php`, 399 líneas): casi todo bajo el prefijo `/configuracion` con middleware `['web','auth','check_route_permission']` — cada sub-prefijo (`company-information`, `additional-fields`, `metodos-de-pago`, `work-flow`, `nomenclature`, `team`, `rules`, `api-movil`, `data-plan-promotions`, etc.) mapea a un controller propio con el patrón CRUD estándar del sistema (`index`/`store`/`edit`/`update`/`destroy`/`table` para DataTables server-side). Excepción notable: `credenciales-google-maps/render-config` va **fuera** de `check_route_permission` (solo `web`,`auth`) a propósito, porque es infraestructura de mapas que debe alcanzar a cualquier staff logueado (ver comentario en el propio archivo).
- **Panel dinámico nuevo** (`ModuleConfigPanelController` → `/admin/configuracion-nueva`): vista paralela (`config-panel-nueva.blade.php`) que convive con el índice clásico (`SettingController::index` → `/configuracion`); el frontend (`ModuleManager.vue`) arma sus tarjetas leyendo el bloque `config_sections` de cada `module.json` del sistema (24 secciones propias, agrupadas en categorías Sistema/Finanzas/Red/Scheduling/Mensajería/API/Ventas), cada una con `doc.terms/steps/actions` para ayuda contextual.
- **Controllers propios** (uno por sub-catálogo, en `Controllers/`): `SettingController` (índice + config de deuda recurrente/custom), `CompanyInformationController`, `SettingAdditionalFieldController`, `MethodPaymentController`/`MethodOfPaymentController`, `WorkFlowController`, `TeamController`, `NomenclatureController`, `TemplateTaskController`, `ListTemplateVerificationController`, `RuleController` (reglas de comisión/venta), `ConfigFinanceNotificationController`, `EmailSettingController`, `BillingReminderController`, `CredentialUpdateController` (imagen de credencial/firma corporativa), `CatalogoApiController`, `SettingApiMovilController`, `ModuleVisibilityController` (visibilidad/orden de módulos en el sidebar), `ImportController` (herramientas de importación), `Commission/ComissionController`, `MediumSale`/`TypeSeller`/`StatusSeller` (catálogos de ventas), `ServiceInAddressListController`, `DataPlanPromotions`/`Ift`/`Partner` (catálogos plegados desde "Administración" el 2026-05-20, rutas conservadas bajo `/administracion/*` por compatibilidad de frontend).
- **Servicios clave:** `CatalogoApiService` (recorre TODOS los `module.json` del repo y agrega los `api_endpoints` declarados por cada módulo — es el motor del "Catálogo de API"), `ModuleSidebarConfigService` (gestiona qué módulos pueden hospedar hijos dinámicos en el sidebar, persistido en `ModuleSidebarConfig`), `ConfigFinanceNotificationService`, `DefaultValueService`, `EmailConfigService`.
- **Modelos/tablas propias:** `CompanyInformation`, `BillingConfiguration`, `BillingReminder`, `CommandConfig` (+ `CommandConfigRepository`, consumido por `Kernel.php` para el schedule dinámico desde BD — ver `CLAUDE.md`), `ConfigFinanceNotification`, `DefaultValue`, `FieldModule`/`FieldType` (motor de campos adicionales dinámicos por módulo), `ApiMobileConfig`/`ApiMobileLog` (tablas propias vía migraciones del módulo, `2026_05_17_*`).
- **API Móvil** (`api-movil/*`): pantalla de configuración + gestión de tokens Sanctum activos (revocar uno o todos) + documentación auto-generada de los endpoints `/api/megafamilia/*` + logs de acceso (con export CSV), middleware propio `LogApiMobileAccess`.

## 4. Qué EXPONE / qué CONSUME
**Expone**
- **Rutas web** bajo `/configuracion/*` (decenas de sub-secciones CRUD) y `/administracion/{socios,ift,metotdo-de-pago}/*` (catálogos legacy plegados aquí).
- **Ruta pública para staff logueado** `/configuracion/credenciales-google-maps/render-config` (config de render del mapa: api_key + centro/zoom por defecto) — consumida por `/mapas`, MegaFamilia, `LeafletMap.vue` y otros widgets geo.
- **Panel dinámico** `/admin/configuracion-nueva` que renderiza las tarjetas de `config_sections` de TODOS los módulos (no solo el propio).
- **`CatalogoApiService::getAllEndpoints()`** — agrega y publica (`/configuracion/api/catalogo/catalog`) los `api_endpoints` que cada módulo declara en su `module.json`; es el registro central de contratos de API consumido por la pantalla "Catálogo de API".
- **Bloque `ai`** en su propio `module.json` (`knowledge`, `example_intents`) — lo lee `IAChatController` para construir el conocimiento del asistente IA del sistema (igual que todos los demás módulos).
- **Tarjetas de administración** (`admin_cards`): "Configuración General", "Socios", "IFT", "Métodos de Pago".
- **Permisos** `config_view_*` (uno por sub-sección: system/main/finance/finance_notification/network_management/helpdesk/scheduling/potencial_customer/inventory/integrations/voice/tools/sales) + `module.visibility.manage` (gatea `/admin/modules/visibility/*`, aparte de `check_route_permission`).
- **`ModuleSidebarConfig`** (visibilidad/orden de módulos en sidebar) — lo usa `SidebarComposer` para render dinámico de sub-menús (`location:submenu`).

**Consume**
- **Controllers de otros módulos montados aquí por razones históricas de UI:** `MapCredentialController` (Mapas), `CommissionRuleController`/`RangeSaleController` (Vendedores) — el módulo solo los enruta bajo su prefijo, no los posee.
- **Catálogo `modules`** (DB-driven, ver nota de data-drift en `CLAUDE.md`) — varios controllers (campos adicionales, import) resuelven configuración de formulario vía `Module::getfields()`.
- **`module.json` de TODOS los módulos del sistema** — para agregar `api_endpoints` (Catálogo de API) y `config_sections` (panel dinámico); es el único módulo que hace esta lectura cross-módulo.
- **Sanctum** (tokens de la API Móvil) y las rutas `/api/megafamilia/*` (para generar la documentación auto-generada de `api-movil/docs`).

> _Servicios compartidos únicos: no monta WhatsApp/IA propios (usa los adaptadores centrales vía el bloque `ai` de su `module.json`); para Mapas, delega el render en el módulo Mapas y solo expone la ruta de config compartida._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
