# Módulo Planes

> Catálogo de planes/servicios contratables del ISP. `app/Modules/Addons/Planes/` · slug `addon-planes` · addon activo.

## 0. En simple
Es el catálogo donde se dan de alta los planes que Meganet vende: Internet, teléfono (VoIP), servicios a la medida y paquetes combinados; de ahí se elige lo que se contrata a cada cliente.

## 1. Qué es
Módulo que administra el catálogo de planes/servicios contratables del ISP: **Internet** (velocidad/precio), **VoIP** (telefonía), **Custom** (servicios a medida) y **Paquetes/Bundles** (combos de varios planes con precio especial). Además incluye un catálogo aparte de **Servicios Contratables** (add-ons de otros módulos, ej. Flotas/MegaFamilia) que se activan/suspenden por cliente con precio escalonado por rango de uso.

## 2. Para qué sirve
Le sirve al equipo comercial/administrativo para definir la oferta de productos del ISP (qué planes existen, a qué precio, con qué velocidad o minutos) y, al momento de contratar/editar el servicio de un cliente, elegir de este catálogo en vez de capturar datos sueltos. El sub-catálogo de "Servicios contratables" resuelve un caso distinto: add-ons opcionales (ej. control parental MegaFamilia, gestión de flotilla) que un cliente activa/suspende, con precio que escala según cuántas unidades use (vehículos, dispositivos, etc.), incluyendo meses de prueba gratuita.

## 3. Cómo funciona
- **Los 4 catálogos "clásicos"** (Internet/VoIP/Custom/Paquetes) son CRUDs paralelos, cada uno con su controller y modelo, siguiendo el patrón estándar de módulo (helper de datatable + `ValidationImportModuleTrait` + `PromotionService` para promociones + soporte de importación desde dump):
  - `InternetController` → modelo `App\Models\Internet` (tabla `internets`). Trunca el precio a 2 decimales sin redondear; permite relaciones múltiples (partners, tipos de facturación) y promociones.
  - `VozController` → modelo `App\Models\Voise` (planes VoIP).
  - `CustomController` → modelo `App\Models\Custom` (servicios a medida, ej. enlaces dedicados).
  - `BundleController` → modelo `App\Models\Bundle` (paquetes que combinan Internet + VoIP con precio único).
  - Los 4 comparten el mismo patrón de acciones (`index/create/store/edit/update/destroy/table` + import) y viven bajo `/internet`, `/voz`, `/custom`, `/paquetes` respectivamente.
- **Catálogo de Servicios Contratables** (`ContratableCatalogController`, prefijo `/planes/contratables`) es un sub-sistema aparte, más nuevo:
  - Modelos `App\Models\Contratable\ContratableService` (tabla `contratable_services`) y `ContratablePackage` (tabla `contratable_packages`, precio por rango de unidades — ej. 1-5 vehículos a un precio, 6+ a otro).
  - El **módulo contratable** se elige de una lista FIJA en código (`ContratableCatalogController::MODULOS`: hoy `flotas` y `megafamilia`), nunca texto libre; la **métrica** (vehículos/dispositivos) se deriva automáticamente del módulo elegido.
  - Reglas duras de validación de rangos: sin solape entre paquetes, máximo un paquete con `rango_max` abierto (NULL) y debe ser el de mayor rango.
  - `ClientContratableController` (prefijo `/cliente/contratables`) es la pestaña **"Servicios contratados"** de la ficha del cliente: para cada servicio activo del catálogo, calcula el conteo real de unidades del cliente (vehículos vía `FleetVehicle::forClient()`, dispositivos vía `ParentalDevice::forClient()`), resuelve el paquete/precio que le corresponde por rango y expone activar/suspender/reactivar vía `ContratableSubscriptionService` (modelo `ClientContratableSubscription`, tabla `client_contratable_subscriptions`) + detección de prueba gratuita vía `ContratableTrialService`. **No factura** — eso corresponde a un motor de facturación aparte (F2), hoy gateado/pendiente.
- **Nota de código muerto detectado (no tocado, fuera del alcance de esta doc):** la migración `app/Modules/Addons/Planes/migrations/2026_06_01_000001_create_contractable_services_table.php` crea una tabla `contractable_services` (con "c", overlay de manifiestos de módulo pensado para un `ServiceCatalogService` que nunca se implementó) que **no tiene modelo, servicio ni consumidor** en el código actual — es distinta de `contratable_services` (sin "c", la que sí se usa arriba). Parece deuda de un diseño abandonado.

## 4. Qué EXPONE / qué CONSUME
**Expone**
- **Rutas web** (middleware `web`+`auth`+`check_route_permission`, sin prefijo de módulo — legacy):
  - `/internet/*`, `/voz/*`, `/custom/*`, `/paquetes/*` — CRUD completo (index, crear, editar, add, update, destroy, table) de cada catálogo.
  - `/planes/contratables/*` — CRUD del catálogo de servicios contratables (index, data, modulos, form, show, add, update, destroy).
  - `/cliente/contratables/{clientId}/*` — data/activar/suspender/reactivar para la pestaña de la ficha del cliente.
- **21 permisos**: `plan_view/add/edit/delete/export_{internet,voz,custom,package}` (20) + `contratables.manage`.
- **`client_tab`** declarado en `module.json`: componente Vue `ContratablesClientTab`, se integra en la ficha del cliente vía la infraestructura genérica de pestañas extensibles (gate `contratables.manage`).
- **`service_type`** declarado en `module.json` (capacidades del tipo "internet": configurable en precio, soporta promociones, combinable en bundle) — metadato consumido por el catálogo/documentación, no por lógica de negocio activa (el `ServiceCatalogService` que lo fusionaría no existe).

**Consume**
- **Módulo Flotas** — `FleetVehicle::forClient()` para contar vehículos del cliente (métrica `vehiculos` del catálogo contratable).
- **Módulo MegaFamilia** — `ParentalDevice::forClient()` para contar dispositivos del cliente (métrica `dispositivos`).
- **`PromotionService`** (servicio compartido) — crea/actualiza promociones al guardar planes de Internet.
- **`ImportdDBService`** — importación de filas de planes desde un dump/BD anterior (import legacy, no SmartImport).
- **Ficha del cliente** — las relaciones `client_internet_services`/`client_custom_services`/`client_bundle_services` en `App\Models\Internet` ligan estos planes al servicio contratado de un cliente real (fuera de este módulo).

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
