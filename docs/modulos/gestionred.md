# Módulo Gestión de Red

> Gestión de routers MikroTik, OLTs/ONUs de fibra y subredes IPv4. `app/Modules/Addons/GestionRed/` · slug `addon-gestion-red` · addon, sin dependencias, activo.

**En simple:** es el panel donde el equipo técnico controla los routers, las cajas de fibra óptica (OLTs) y los aparatos de internet de los clientes (ONUs), y organiza las direcciones IP de la red.

## 1. Qué es
Addon que agrupa tres áreas de infraestructura de red del ISP: **Router** (integración con MikroTik RouterOS), **OLTs/ONUs** (fibra óptica GPON/EPON vía SmartOLT) y **Network/IPv4** (subredes y pools de IP).

## 2. Para qué sirve
Le da al equipo técnico un solo lugar para: sincronizar y administrar los routers MikroTik que controlan el ancho de banda de los clientes, dar de alta y monitorear OLTs y ONUs de fibra (altas, reinicios, resincronización, señal óptica, VLANs, interrupciones PON), y llevar el control de las subredes IPv4 e IPs asignadas por zona/cliente.

## 3. Cómo funciona
- **Network / IPv4** (`Controllers/Network/`):
  - `NetworkController` — CRUD de subredes (tabla `Network`/`network`) con cálculo de rango de IPs.
  - `NetworkIpController` — listado/edición de IPs individuales de una subred (`NetworkIp`).
  - `Ipv4CalculatorController` — calculadora de subredes (CIDR) sin persistencia.
- **Router (MikroTik)** (`Controllers/Router/`):
  - `RouterController` — CRUD de routers (`Router`) y su relación con `ClientInternetService`/`ClientUser`/`MikrotikClientPpoe`/`MikrotikClientHostpotUser`.
  - `MikrotikController` — conexión real a RouterOS vía `App\Services\MikrotikService` (API binaria MikroTik, toggle `.env` `ROUTER_LOCAL`): estatus del router, crear/quitar reglas, clonar cliente a MikroTik. Dispara jobs asíncronos `MikrotikRulesJob` / `MicrotikDeleteRulesJob` / `RectifyClientsInRouterJob`.
  - `MikrotikConfigController` — configuración por router (perfiles/colas).
  - `MikrotikItemToExcecuteActionController` — cola de acciones pendientes de ejecutar contra un router (`MikrotikItemToExcecuteAction`).
- **OLTs/ONUs** (`Controllers/OLTs/`, el bloque más grande del módulo):
  - `OLTsController` — panel principal: listado de OLTs (`Olt`), dashboard de interrupciones, zonas (`OltZone`), tipos de ONU (`OltTypeONU`), VLANs (`OltVlan`), tarjetas (`OltCard`), puertos PON/uplink (`OltUplinkPort`), perfiles de velocidad (`OltSpeedProfile`), ONUs no configuradas (`OltUnconfiguredOnu`) y acciones sobre ONU (habilitar/deshabilitar, reboot, resync, mover, restaurar a fábrica, ver señal). Usa `App\Services\OLTsService` (cliente HTTP a la API de **SmartOLT**, con presupuesto/throttling de requests por hora) y `App\Services\OltDriver\OltDriverManager` (abstracción de driver por marca de OLT).
  - `OLTsOnuController` — operaciones específicas de una ONU (crear, sincronizar, IP/MGMT, puerto ethernet/wifi, VLANs adjuntas, puerto VoIP, tipo de ONU, credenciales web, CATV, gráficas de tráfico/señal).
  - `OLTsProvisionController` — previsualización de aprovisionamiento de una ONU nueva antes de confirmarla (flujo de instalación, ver ítem #341/caso Alondra en la Hoja de Ruta).
  - `OLTsCardsController` / `OLTsPonPortsController` / `OLTsUplinkPortsController` / `OLTsVlansController` / `OLTsZonesController` / `OLTsODBsController` / `OLTsTypeONUsController` / `OLTsProfilesController` / `OLTsBillingController` — CRUD de catálogo/settings de cada entidad (tarjetas, puertos PON/uplink, VLANs, zonas, cajas ODB, tipos de ONU, perfiles de velocidad, facturación SmartOLT).
  - `OLTsConfigController` — credenciales/config de la integración SmartOLT (guardar, probar conexión, importar inventario).
  - `OltGeoController` — capas geográficas de red (mapa de red de fibra, `/olts/mapa-red`).
  - `Services/OltAlertService` — evalúa interrupciones PON detectadas por SmartOLT y notifica al rol configurado; opera en **dry-run por defecto** (`alertas_olt_activas=false` en `OltSmartoltConfig`), solo envía notificaciones reales si Irving activa el flag tras validar en log.
- **Sincronización periódica**: `OLTsService` expone métodos `syncOlts`/`syncBillings`/`syncTemperatures`/`syncOnusStatus`/`syncOnusSignals`/`syncUnconfiguredOnus`/`syncSpeedProfiles`, consumidos por comandos artisan programados en `Kernel.php` (`mikrotik:sync` cada 5 min, `smartolt:sync-critical` cada 10 min).
- **Autorización propia (distinta del gating estándar):** las acciones de ONU (`olt_view`/`onu_add`/`onu_edit`, etc.) **no** pasan por `config('route_permission')` como el resto del sistema — usan su propio verificador de permisos directos dentro de `OLTsController`/`OLTsOnuController` (deuda registrada en la Hoja de Ruta: Fase 3a-bis, pendiente de alinear con el flip directos∪rol del resto del sistema).

## 4. Qué EXPONE / qué CONSUME
**Expone**
- **Rutas** bajo `/red/*` (IPv4 y Router/MikroTik) y `/olts/*` (panel, dashboard, settings, ONUs) — todas middleware `web`+`auth`+`check_route_permission`, más 4 rutas MikroTik globales sin prefijo `red` (`/status-by-router/{id}`, `/remove-rules-by-router/{id}`, `/create-rules-by-router/{id}`, `/request-clone-client-to-mikrotik/{id}`).
- **Permisos**: `ipv4_view_ipv4`/`ipv4_add_ipv4`/`ipv4_edit_ipv4`/`ipv4_delete_ipv4`/`ipv4_export_ipv4`, `router_view_router`/`router_add_router`/`router_edit_router`/`router_delete_router`/`router_export_router`, `olt_view`/`olt_add`/`olt_edit`/`olt_remove`, `onu_add`/`onu_edit`/`onu_remove`/`onu_enable_disable`/`onu_reboot`/`onu_resync`/`onu_default`/`onu_type_add`.
- **Menú sidebar** "Red" (Routers, Red IPv4) y tarjeta de administración "Gestión de Red".
- **Config sections** (`/configuracion`): `red_mikrotik` (credenciales RouterOS) y `red_smartolt` (credenciales SmartOLT).
- **Jobs**: `MikrotikRulesJob`, `MicrotikDeleteRulesJob`, `RectifyClientsInRouterJob`.
- **Comandos artisan** de sincronización (`mikrotik:sync`, `smartolt:sync-critical`, ver `Kernel.php`).

**Consume**
- **`App\Services\MikrotikService`** — conexión RouterOS API (toggle `.env` `ROUTER_LOCAL`/`CONECTION_MIKROTIK`).
- **`App\Services\OLTsService`** — cliente HTTP a SmartOLT API (credenciales en `OltSmartoltConfig`).
- **`App\Services\OltDriver\OltDriverManager`** — abstracción de driver de OLT por marca/fabricante.
- **`App\Http\Traits\RouterConnection`** — trait compartido de conexión a router.
- **Modelos de otros dominios**: `Client`, `ClientUser`, `ClientInternetService` (Clientes/Facturación) para asociar routers e IPs a servicios de internet contratados.
- **`GeneralNotification` / `StandardNotification`** — notificaciones de alertas de interrupción PON (`OltAlertService`).
- **`Module`** (catálogo `modules`) — resolución de configuración de formularios dinámicos.

> _Servicios compartidos únicos respetados: no monta cliente HTTP propio de IA ni de WhatsApp. No hay entrada en el registro de contratos inter-módulos (`docs/contratos/`) para Gestión de Red al momento de esta doc._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
