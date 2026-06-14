# MultiOLT / GestionRed — Inventario completo del módulo

> Documento generado: 2026-06-13 · Servidor: 192.168.105.11 · Rama: `main`
> Naturaleza: read-only. Evidencia de código real; nada asumido.

---

## 1. Identidad y ubicación

### module.json

| Campo | Valor |
|---|---|
| `slug` | `addon-gestion-red` |
| `name` | `Gestión de Red` |
| `version` | `0.1.0` |
| `type` | `addon` |
| `dependencies` | `[]` (ninguna) |
| `config_sections` | `red_mikrotik` (MikroTik RouterOS) + `red_smartolt` (SmartOLT — solo metadata, sin UI real aún) |

### Registro en BD (`module_registry`)

| Campo | Valor |
|---|---|
| `id` | 11 |
| `slug` | `addon-gestion-red` |
| `name` | `Gestión de Red` |
| `installed_version` | `0.1.0` |
| `type` | `addon` |
| `active` | `1` |
| `installed_at` | `2026-05-29 13:25:44` |

### Dispersión de archivos (patrón mixto — deuda GR-6)

Los archivos del módulo están **dispersos en dos capas**. El módulo en sí es solo envoltura de controllers y rutas; los modelos y el servicio viven fuera:

| Capa | Ruta | Contenido |
|---|---|---|
| Módulo (controllers) | `app/Modules/Addons/GestionRed/Controllers/OLTs/` | 11 controllers OLT |
| Módulo (controllers) | `app/Modules/Addons/GestionRed/Controllers/Router/` | 4 controllers MikroTik |
| Módulo (controllers) | `app/Modules/Addons/GestionRed/Controllers/Network/` | 3 controllers IPv4 |
| Módulo (rutas) | `app/Modules/Addons/GestionRed/routes.php` | Todas las rutas del módulo |
| Módulo (proveedor) | `app/Modules/Addons/GestionRed/ModuleServiceProvider.php` | Boot del módulo |
| **Disperso** (modelos) | `app/Models/Olt*.php` | 13 modelos OLT fuera del módulo |
| **Disperso** (servicio) | `app/Services/OLTsService.php` | Servicio HTTP SmartOLT (~1,310 líneas) |
| **Disperso** (driver) | `app/Services/OltDriver/` | Capa de driver multi-marca |
| **Disperso** (comandos) | `app/Console/Commands/Olts/` | 6 comandos artisan |
| **Disperso** (vistas Blade) | `resources/views/meganet/module/olts/` | `panel.blade.php`, `dashboard.blade.php` |
| **Disperso** (Vue) | `resources/js/components/module/olts/` | ~75 componentes Vue |

Las carpetas `Models/`, `Services/`, `Repositories/` dentro del módulo están **vacías** (solo `.gitkeep`).

---

## 2. Tablas (13 tablas)

| Tabla | Propósito | Filas | PK | Índices únicos / FKs clave |
|---|---|---|---|---|
| `olts` | OLTs registradas (espejo de SmartOLT) | 3 | `id` (SmartOLT id, no-incremental) | — |
| `olt_onus` | ONUs configuradas (inventario completo) | 2 954 | `id` auto | `UNIQUE(sn, olt_id)`, `UNIQUE(unique_external_id)`, FK `olt_id`, FK `onu_type_id`, FK `zone_id`, index `service_id` |
| `olt_cards` | Tarjetas de línea por OLT | 19 | `id` auto | `UNIQUE(olt_id, slot)` |
| `olt_pon_ports` | Puertos PON por OLT | 144 | `id` auto | `UNIQUE(olt_id, board, pon_port)` |
| `olt_uplink_ports` | Puertos uplink por OLT | 20 | `id` auto | `UNIQUE(olt_id, name)` |
| `olt_zones` | Zonas geográficas (catálogo SmartOLT) | 518 | `id` | — |
| `olt_odbs` | Splitters/ODBs con coordenadas lat/lng | 671 | `id` | — |
| `olt_type_onus` | Catálogo de tipos/modelos de ONU | 47 | `id` | — |
| `olt_speed_profiles` | Perfiles de velocidad/QoS | 31 | `id` | — |
| `olt_unconfigured_onus` | ONUs detectadas sin autorizar | 1 | `id` auto | — |
| `olt_interruption_pons` | Interrupciones activas en puertos PON | 5 | `id` auto | — |
| `olt_vlans` | VLANs por OLT | 36 | `id` | — |
| `olt_billings` | Facturación/suscripción SmartOLT por OLT | 3 | `id` | — |

**`olt_smartolt_config` — NO existe todavía** (estaba propuesta en Paso 0 como mejora, aún no ejecutada).

### Columnas clave de las tablas principales

**`olts`:** `id`, `name`, `olt_hardware_version`, `ip`, `snmp_port`, `telnet_port`, `env_temp`, `uptime`, `status`, `driver` (enum `smartolt`/`huawei`), `last_synced_at`

**`olt_onus`:** `sn` (serial de la ONU), `unique_external_id` (ID de SmartOLT, llave upsert), `olt_id`, `client_id` (→ `clients`), `service_id` (→ `client_internet_services`), `board`, `port`, `onu` (número en puerto), `status`, `signal`, `signal_1310`, `signal_1490`, `zone_id`, `odb_name`, `latitude`, `longitude`, `last_synced_at`, `last_status_change`

---

## 3. Modelos

Todos los modelos están en `app/Models/Olt*.php` (fuera del módulo — deuda GR-6).

| Modelo | Tabla | Relaciones Eloquent | Apunta fuera del módulo |
|---|---|---|---|
| `Olt` | `olts` | `hasMany` OltCard, OltPonPort, OltUplinkPort, OltVlan, OltOnu, OltInterruptionPon, OltUnconfiguredOnu, OltOdb | No directamente |
| `OltOnu` | `olt_onus` | `belongsTo` Olt; `belongsTo` OltTypeONU (`onu_type_id`); `belongsTo` **ClientInternetService** (`service_id`) | ✅ → `ClientInternetService` |
| `OltCard` | `olt_cards` | `belongsTo` Olt | No |
| `OltPonPort` | `olt_pon_ports` | — (no relaciones declaradas) | No |
| `OltUplinkPort` | `olt_uplink_ports` | — | No |
| `OltZone` | `olt_zones` | `hasMany` OltOdb (`zone_id`) | No |
| `OltOdb` | `olt_odbs` | — (solo accessors: `label`, `last_synced_at_humans`) | No |
| `OltTypeONU` | `olt_type_onus` | — | No |
| `OltSpeedProfile` | `olt_speed_profiles` | — | No |
| `OltUnconfiguredOnu` | `olt_unconfigured_onus` | — | No |
| `OltInterruptionPon` | `olt_interruption_pons` | `belongsTo` Olt | No |
| `OltVlan` | `olt_vlans` | — | No |
| `OltBilling` | `olt_billings` | — | No |

**Conexión externa principal:** `OltOnu::internetService()` → `belongsTo(ClientInternetService, 'service_id')` — cobertura real: 2,606 de 2,954 ONUs (88 %) tienen `service_id`, 2,903 tienen `client_id`.

`client_id` se deriva al momento del sync de SmartOLT vía `SmartOltTrait::extractClientId($name)` — extrae el número más alto de dígitos del campo `name` de la ONU (ej. `"Cliente 4118"` → `4118`).

---

## 4. Rutas y controladores

Total de rutas: **73 rutas** (supera las 68 estimadas).

### Grupo Red (prefix `/red`) — IPv4 + Router/MikroTik

| HTTP | URI | Controlador::método | Función | L/E |
|---|---|---|---|---|
| GET | `/red/ipv4/listar` | NetworkController::index | Lista subredes IPv4 | L |
| GET | `/red/ipv4/crear` | NetworkController::create | Formulario nueva subred | L |
| GET | `/red/ipv4/success` | NetworkController::success | Confirmación éxito | L |
| POST | `/red/ipv4/add` | NetworkController::store | Crear subred IPv4 | E |
| POST | `/red/ipv4/table` | NetworkController::table | Datos tabla datatable | L |
| POST | `/red/ipv4/update/{id}` | NetworkController::update | Editar subred | E |
| POST | `/red/ipv4/destroy/{id}` | NetworkController::destroy | Eliminar subred | E |
| POST | `/red/ipv4/network/{id}` | NetworkController::getIpByNetwork | IPs de una red | L |
| GET | `/red/ipv4/ver/{id}` | NetworkIpController::show | Detalle de IPs | L |
| POST | `/red/ipv4/ip/table` | NetworkIpController::table | Tabla de IPs | L |
| POST | `/red/ipv4/ip/update/{id}` | NetworkIpController::update | Editar IP | E |
| POST | `/red/ipv4/calculator` | Ipv4CalculatorController::calculator | Calculadora CIDR | L |
| GET | `/red/router/listar` | RouterController::index | Lista de routers MikroTik | L |
| GET | `/red/router/crear` | RouterController::create | Formulario nuevo router | L |
| POST | `/red/router/add` | RouterController::store | Crear router | E |
| GET | `/red/router/editar/{id}` | RouterController::edit | Formulario editar | L |
| POST | `/red/router/update/{id}` | RouterController::update | Actualizar router | E |
| POST | `/red/router/destroy/{id}` | RouterController::destroy | Eliminar router | E |
| POST | `/red/router/table` | RouterController::table | Tabla datatable | L |
| GET | `/red/router/success/{id}` | RouterController::success | Confirmación | L |
| GET | `/red/router/mikrotik/crear` | MikrotikController::create | Formulario MikroTik | L |
| POST | `/red/router/mikrotik/add` | MikrotikController::store | Crear config MikroTik | E |
| GET | `/red/router/mikrotik/editar/{id}` | MikrotikController::edit | Editar MikroTik | L |
| POST | `/red/router/mikrotik/update/{id}` | MikrotikController::update | Actualizar | E |
| POST | `/red/router/mikrotik/crear/{id}` | MikrotikController::store | Crear subordinado | E |
| POST | `/red/router/mikrotik/destroy/{id}` | MikrotikController::destroy | Eliminar | E |
| POST | `/red/router/mikrotik/table` | MikrotikController::table | Tabla | L |
| GET | `/red/router/mikrotik/cleantails` | MikrotikController::clearMikrotikTails | Limpiar colas | E |
| GET | `/red/router/mikrotik/read-notification/{id}` | RouterController::readNotification | Marcar notif leída | E |
| GET | `/red/router/mikrotik/config/editar/{id}` | MikrotikConfigController::edit | Config MikroTik | L |
| POST | `/red/router/mikrotik/config/update/{id}` | MikrotikConfigController::update | Actualizar config | E |
| POST | `/red/router/mikrotik/config/crear/{id}` | MikrotikConfigController::store | Crear config | E |
| POST | `/red/router/mikrotik/config/destroy/{id}` | MikrotikConfigController::destroy | Eliminar config | E |

### Rutas MikroTik globales (sin prefix)

| HTTP | URI | Controlador::método | Función | L/E |
|---|---|---|---|---|
| POST | `/status-by-router/{id}` | MikrotikController::getMikrotikStatus | Estado del router | L |
| POST | `/remove-rules-by-router/{id}` | MikrotikController::getMikrotikRemoveRules | Eliminar reglas | E |
| POST | `/create-rules-by-router/{id}` | MikrotikController::getMikrotikCreateRules | Crear reglas | E |
| POST | `/request-clone-client-to-mikrotik/{id}` | MikrotikController::cloneClientToMikrotik | Clonar cliente | E |

### Grupo OLTs (prefix `/olts`)

| HTTP | URI | Controlador::método | Función | L/E |
|---|---|---|---|---|
| GET | `/olts` | OLTsController::panel | Vista principal SPA | L |
| GET | `/olts/dashboard` | OLTsController::dashboard | Vista dashboard | L |
| POST | `/olts/list` | OLTsController::oltList | Lista de OLTs | L |
| POST | `/olts/zones` | OLTsController::zones | Zonas | L |
| POST | `/olts/type-onus` | OLTsController::typeONUs | Tipos de ONU | L |
| POST | `/olts/nomenclatures` | OLTsController::nomenclatures | Nomenclaturas globales | L |
| POST | `/olts/uptime-env-temp` | OLTsController::getUptimeEnvTemp | Uptime y temperatura | L |
| POST | `/olts/cards/{id}` | OLTsController::oltCardsDetails | Tarjetas de OLT | L |
| POST | `/olts/pon-ports/{id}` | OLTsController::getOltPonPortsDetails | Puertos PON con señal | L |
| POST | `/olts/outage-pons/{id?}` | OLTsController::getOutagePons | PONs con interrupciones | L |
| POST | `/olts/uplink-ports/{id}` | OLTsController::getOltUplinkPortsDetails | Puertos uplink | L |
| POST | `/olts/vlans/{id}` | OLTsController::getVLANs | VLANs de OLT | L |
| POST | `/olts/dashboard-interruptions` | OLTsController::getDashboardInterruptions | Interrupciones para dashboard | L |
| POST | `/olts/dashboard-onus-status/{id?}` | OLTsController::getDashboardOnusStatus | Estado ONUs para dashboard | L |
| POST | `/olts/onus/create` | OLTsOnuController::store | Autorizar/crear ONU | **E** |
| POST | `/olts/onus/sync/{id}` | OLTsOnuController::sync | Sync individual de ONU | **E** |
| POST | `/olts/onus/get-by-client/{id}` | OLTsOnuController::getByClient | ONUs de un cliente | L |
| POST | `/olts/onus/get-mgmt-ip/{id}` | OLTsOnuController::getMgmTIp | IP de gestión | L |
| POST | `/olts/onus/get-ip-address/{id}` | OLTsOnuController::getIpAddress | IP de ONU | L |
| POST | `/olts/onus/get-signal-and-status/{id}` | OLTsOnuController::getSignalAndStatus | Señal + estado live | L |
| POST | `/olts/onus/configure-ethernet-port/{id}` | OLTsOnuController::configureEhernetPort | Config puerto eth | **E** |
| POST | `/olts/onus/configure-wifi-port/{id}` | OLTsOnuController::configureWifiPort | Config puerto WiFi | **E** |
| POST | `/olts/onus/update-service-port/{id}` | OLTsOnuController::updateServicePort | Actualizar service port | **E** |
| POST | `/olts/onus/update-attached-vlans/{id}` | OLTsOnuController::changeAttachedVlans | Cambiar VLANs | **E** |
| POST | `/olts/onus/set-onu-voip-port/{id}` | OLTsOnuController::setOnuVoipPort | Config puerto VoIP | **E** |
| POST | `/olts/onus/update-channel/{id}` | OLTsOnuController::updateChannel | Cambiar canal PON | **E** |
| POST | `/olts/onus/update-mode/{id}` | OLTsOnuController::updateMode | Cambiar modo ONU | **E** |
| DELETE | `/olts/onus/remove/{id}` | OLTsOnuController::remove | Eliminar/desautorizar ONU | **E** |
| POST | `/olts/onus/traffic-graph/{id}` | OLTsOnuController::getTrafficGraph | Gráfica de tráfico | L |
| POST | `/olts/onus/image/{id}` | OLTsOnuController::getImageONU | Imagen de ONU | L |
| POST | `/olts/onus/signal-graph/{id}` | OLTsOnuController::getSignalGrap | Gráfica de señal | L |
| POST | `/olts/onus/update-mgmt-and-vo-ip/{id}` | OLTsOnuController::updateMgmtAndVoIp | Actualizar gestión + VoIP | **E** |
| POST | `/olts/onus/full-status/{id}` | OLTsOnuController::getFullStatus | Estado completo live | L |
| POST | `/olts/onus/running-config/{id}` | OLTsOnuController::getRunningConfig | Config activa | L |
| POST | `/olts/onus/update-external-id/{id}` | OLTsOnuController::updateExternalId | Actualizar ID externo | **E** |
| POST | `/olts/onus/change-web-user-pass/{id}` | OLTsOnuController::changeWebUserPass | Cambiar contraseña web | **E** |
| POST | `/olts/onus/set-catv/{id}` | OLTsOnuController::setCATV | Habilitar/deshabilitar CATV | **E** |
| POST | `/olts/onus/change-onu-type/{id}` | OLTsOnuController::changeOnuType | Cambiar tipo de ONU | **E** |
| POST | `/olts/onus/unconfigured/{id?}` | OLTsOnuController::getUnconfigured | ONUs no configuradas | L |
| POST | `/olts/onus/saved-unconfigured` | OLTsOnuController::getSavedUnconfigured | No-configuradas guardadas | L |
| POST | `/olts/onus/configured/{id?}` | OLTsOnuController::index | ONUs configuradas | L |
| POST | `/olts/onus/signal/{sn}` | OLTsController::getSignalByOnu | Señal por SN | L |
| POST | `/olts/onus/enable-disable/{id}` | OLTsController::enableDisableOnu | Habilitar/deshabilitar | **E** |
| POST | `/olts/onus/resync/{id}` | OLTsController::resyncOnuConfig | Resincronizar config | **E** |
| POST | `/olts/onus/reboot/{id}` | OLTsController::rebootOnu | Reiniciar ONU | **E** |
| POST | `/olts/onus/move/{id}` | OLTsController::moveOnu | Mover ONU a otro puerto | **E** |
| POST | `/olts/onus/details/{id}` | OLTsController::getDetailsByONU | Detalle completo (driver-aware) | L |
| POST | `/olts/onus/update-location/{id}` | OLTsController::updateOnuLocation | Actualizar ubicación | **E** |
| POST | `/olts/onus/nomenclatures` | OLTsController::nomenclaturesFromOnus | Nomenclaturas desde ONUs | L |
| POST | `/olts/settings/billings` | OLTsBillingController::index | Facturación | L |
| POST | `/olts/settings/zones` | OLTsZonesController::index | Lista zonas | L |
| POST | `/olts/settings/zones/store` | OLTsZonesController::store | Crear zona (→ SmartOLT) | **E** |
| POST | `/olts/settings/odbs` | OLTsODBsController::index | Lista ODBs | L |
| POST | `/olts/settings/odbs/store` | OLTsODBsController::store | Crear ODB (→ SmartOLT) | **E** |
| POST | `/olts/settings/type-onus` | OLTsTypeONUsController::index | Tipos de ONU | L |
| POST | `/olts/settings/type-onus/store` | OLTsTypeONUsController::store | Crear tipo ONU (→ SmartOLT) | **E** |
| POST | `/olts/settings/profiles` | OLTsProfilesController::index | Perfiles de velocidad | L |
| POST | `/olts/settings/profiles/store` | OLTsProfilesController::store | Crear perfil (→ SmartOLT) | **E** |
| POST | `/olts/settings/olts` | OLTsController::index | Lista OLTs para settings | L |
| POST | `/olts/settings/olts/{id}/cards` | OLTsCardsController::index | Tarjetas de OLT | L |
| POST | `/olts/settings/olts/{id}/pon-ports` | OLTsPonPortsController::index | Puertos PON | L |
| POST | `/olts/settings/olts/{id}/uplink-ports` | OLTsUplinkPortsController::index | Puertos uplink | L |
| POST | `/olts/settings/olts/{id}/vlans` | OLTsVlansController::index | VLANs | L |
| POST | `/olts/settings/olts/{id}/vlans/store` | OLTsVlansController::store | Crear VLAN (→ SmartOLT) | **E** |

---

## 5. Componentes Vue (~75 componentes)

| Componente | Ruta relativa | Función / pantalla |
|---|---|---|
| `OltsPanel` | `olts/OltsPanel.vue` | Contenedor principal SPA — orquesta tabs OLTs/ONUs |
| `OltsDashboard` | `olts/OltsDashboard.vue` | Dashboard con KPIs de OLTs |
| `DashboardPanel` | `components/dashboard/DashboardPanel.vue` | Shell del dashboard con carrusel de OLTs |
| `CardsInfo` | `components/dashboard/CardsInfo.vue` | Tarjetas resumen (online/offline/total ONUs) |
| `OltCard` | `components/dashboard/OltCard.vue` | Tarjeta resumen por OLT (uptime, temp, ONUs) |
| `OltList` | `components/dashboard/OltList.vue` | Lista de OLTs con estado |
| `OltOnusStatus` | `components/dashboard/OltOnusStatus.vue` | Dona / gráfico de estado de ONUs por OLT |
| `OltInterruptionsPon` | `components/dashboard/OltInterruptionsPon.vue` | Lista de interrupciones PON activas |
| `PonOutage` | `components/dashboard/PonOutage.vue` | Detalle de interrupción en un PON |
| `FilterOnus` | `components/FilterOnus.vue` | Filtros de búsqueda de ONUs (zona, estado, señal) |
| `SyncFromApi` | `components/SyncFromApi.vue` | Botón para forzar sync desde SmartOLT API |
| `OltOnus` | `components/OltOnus.vue` | Tabla de ONUs configuradas de una OLT |
| `OltCards` | `components/OltCards.vue` | Tabla de tarjetas de línea |
| `OltDetails` | `components/OltDetails.vue` | Ficha de OLT con pestañas (tarjetas/puertos/etc.) |
| `OltDiagnostics` | `components/OltDiagnostics.vue` | Diagnóstico avanzado de OLT |
| `OltInterruptionPon` | `components/OltInterruptionPon.vue` | Detalle de interrupción en un PON |
| `OltPonPorts` | `components/OltPonPorts.vue` | Lista de puertos PON |
| `OltUnconfiguredOnus` | `components/OltUnconfiguredOnus.vue` | ONUs detectadas no autorizadas |
| `OltUpLinkPortDetails` | `components/OltUpLinkPortDetails.vue` | Puertos uplink de OLT |
| `OltVlans` | `components/OltVlans.vue` | VLANs de OLT |
| `DialogUnconfiguredOnus` | `components/dialogs/DialogUnconfiguredOnus.vue` | Modal de ONUs no configuradas |
| **PanelOnu** | `components/onus/PanelOnu.vue` | **Ficha completa de ONU** — señal live, indicador `syncing` (B3) |
| `DefaultOnu` | `components/onus/DefaultOnu.vue` | Acción: restaurar ONU a fábrica |
| `EnableOnu` | `components/onus/EnableOnu.vue` | Acción: habilitar/deshabilitar ONU |
| `EthPorts` | `components/onus/EthPorts.vue` | Vista/edición de puertos ethernet |
| `ImageComponent` | `components/onus/ImageComponent.vue` | Imagen/foto de ONU desde SmartOLT |
| `RebootOnu` | `components/onus/RebootOnu.vue` | Acción: reiniciar ONU |
| `RemoveOnu` | `components/onus/RemoveOnu.vue` | Acción: eliminar/desautorizar ONU |
| `ResyncOnu` | `components/onus/ResyncOnu.vue` | Acción: resincronizar config ONU |
| `SpeedProfile` | `components/onus/SpeedProfile.vue` | Cambio de perfil de velocidad |
| `Status` | `components/onus/Status.vue` | Indicador de estado de ONU |
| `VoipPorts` | `components/onus/VoipPorts.vue` | Puertos VoIP de ONU |
| `WifiPorts` | `components/onus/WifiPorts.vue` | Puertos WiFi de ONU |
| `FormOnu` | `components/form/FormOnu.vue` | Formulario alta/edición de ONU |
| `FormAttachmentsVlans` | `components/form/FormAttachmentsVlans.vue` | Form: cambiar VLANs asociadas |
| `FormCatv` | `components/form/FormCatv.vue` | Form: habilitar CATV |
| `FormChangeLocation` | `components/form/FormChangeLocation.vue` | Form: cambiar ubicación ONU |
| `FormChannel` | `components/form/FormChannel.vue` | Form: cambiar canal PON |
| `FormEthPort` | `components/form/FormEthPort.vue` | Form: configurar puerto ethernet |
| `FormMgmtIpAndVoIp` | `components/form/FormMgmtIpAndVoIp.vue` | Form: gestión IP + VoIP |
| `FormMoveOnu` | `components/form/FormMoveOnu.vue` | Form: mover ONU a otro puerto |
| `FormOnuExternalId` | `components/form/FormOnuExternalId.vue` | Form: editar ID externo |
| `FormOnuType` | `components/form/FormOnuType.vue` | Form: cambiar tipo de ONU |
| `FormSpeedProfile` | `components/form/FormSpeedProfile.vue` | Form: cambiar perfil velocidad |
| `FormUpdateMode` | `components/form/FormUpdateMode.vue` | Form: cambiar modo ONU |
| `FormVoIpPort` | `components/form/FormVoIpPort.vue` | Form: configurar puerto VoIP |
| `FormWebPassword` | `components/form/FormWebPassword.vue` | Form: cambiar contraseña web ONU |
| `FormWifiPort` | `components/form/FormWifiPort.vue` | Form: configurar puerto WiFi |
| `IpLabel` | `components/form/IpLabel.vue` | Sub-componente: etiqueta IP |
| `LocationComponent` | `components/form/LocationComponent.vue` | Sub-componente: selección de ubicación |
| `ModeToggle` | `components/form/ModeToggle.vue` | Sub-componente: toggle de modo |
| `PasswordLabel` | `components/form/PasswordLabel.vue` | Sub-componente: contraseña enmascarada |
| `SelectComponent` | `components/form/SelectComponent.vue` | Sub-componente: select genérico |
| `SelectFormComponent` | `components/form/SelectFormComponent.vue` | Sub-componente: select con form |
| `SignalToggle` | `components/form/SignalToggle.vue` | Sub-componente: indicador señal |
| `StatusToggle` | `components/form/StatusToggle.vue` | Sub-componente: toggle estado |
| `UseVlansComponent` | `components/form/UseVlansComponent.vue` | Sub-componente: selector VLANs |
| `MgmtIp` | `components/form/components/MgmtIp.vue` | Sub-componente: IP gestión |
| `OnuMode` | `components/form/components/OnuMode.vue` | Sub-componente: modo ONU |
| `Tr069` | `components/form/components/Tr069.vue` | Sub-componente: TR-069 |
| `Vlan` | `components/form/components/Vlan.vue` | Sub-componente: VLAN |
| `VoIpService` | `components/form/components/VoIpService.vue` | Sub-componente: servicio VoIP |
| `WanMode` | `components/form/components/WanMode.vue` | Sub-componente: modo WAN |
| `Billings` | `components/settings/Billings.vue` | Settings: facturación por OLT |
| `FormOdb` | `components/settings/FormOdb.vue` | Settings: crear ODB |
| `FormTypeOnu` | `components/settings/FormTypeOnu.vue` | Settings: crear tipo ONU |
| `FormVlan` | `components/settings/FormVlan.vue` | Settings: crear VLAN |
| `FormZone` | `components/settings/FormZone.vue` | Settings: crear zona |
| `Odbs` | `components/settings/Odbs.vue` | Settings: lista de ODBs |
| `OltSettingPanel` | `components/settings/olts/OltSettingPanel.vue` | Settings: panel de OLT individual |
| `Cards` | `components/settings/olts/Cards.vue` | Settings: tarjetas de OLT |
| `PonPorts` | `components/settings/olts/PonPorts.vue` | Settings: puertos PON de OLT |
| `UplinkPorts` | `components/settings/olts/UplinkPorts.vue` | Settings: uplinks de OLT |
| `Vlans` | `components/settings/olts/Vlans.vue` | Settings: VLANs de OLT |
| `Olts` | `components/settings/Olts.vue` | Settings: lista de OLTs con links |
| `Profiles` | `components/settings/Profiles.vue` | Settings: perfiles de velocidad |
| `SettingsPanel` | `components/settings/SettingsPanel.vue` | Settings: contenedor con tabs |
| `TypeOnus` | `components/settings/TypeOnus.vue` | Settings: tipos de ONU |
| `Zones` | `components/settings/Zones.vue` | Settings: zonas geográficas |

---

## 6. Capa de driver

### 6.1 `OltDriverInterface` — contrato núcleo

| Método | L/E | Descripción |
|---|---|---|
| `getName(): string` | L | Nombre legible del driver |
| `listOlts(): array` | L | Lista de OLTs gestionadas |
| `listSpeedProfiles(): array` | L | Catálogo de perfiles de velocidad |
| `getUnconfiguredOnus(?$oltId): array` | L | ONUs detectadas sin autorizar |
| `getOnusByOlt($oltId): array` | L | ONUs completas de una OLT (bulk sync) |
| `getOnusSignals(?$oltId): array` | L | Señales de todas las ONUs |
| `getOnusStatus(?$oltId): array` | L | Estados de todas las ONUs |
| `findOnuBySn($sn): array` | L | Busca ONU por número de serie |
| `getOnuDetails($onuId): array` | L | Detalle completo por ID externo |
| `getOnuSignal($onuId): array` | L | Señal óptica actual de ONU |
| `getOnuStatus($onuId): array` | L | Estado operativo actual |
| `authorizeOnu(array $data): array` | **E** | Autoriza/provisiona ONU en OLT |
| `deauthorizeOnu($onuId): array` | **E** | Elimina/desautoriza ONU |
| `setOnuEnabled($onuId, $enabled): array` | **E** | Habilita/deshabilita servicio |
| `rebootOnu($onuId): array` | **E** | Reinicia ONU remotamente |
| `setOnuSpeedProfile($onuId, $data): array` | **E** | Aplica perfil de velocidad |

### 6.2 Interfaces de capacidades opcionales

| Interface | Métodos clave |
|---|---|
| `SupportsCatalog` | `listZones()`, `listOnuTypes()`, `listOdbs()` |
| `SupportsOltTopology` | `getOltCards()`, `getOltPonPorts()`, `getOltUplinkPorts()`, `getOltOutagePons()`, `getOltVlans()`, `getOltEnvironmentStats()` |
| `SupportsAdvancedDiagnostics` | `getOnuFullStatus()`, `getOnuRunningConfig()`, `getOnuMgmtIp()`, `getOnuIpAddress()`, `getOnuDetailsBySn()` |
| `SupportsOnuPorts` | `configureOnuEthernetPort()`, `shutdownOnuEthernetPort()`, `configureOnuWifiPort()`, `shutdownOnuWifiPort()` |
| `SupportsVoip` | `setOnuVoipPort()`, `updateOnuMgmtAndVoip()` |
| `SupportsCatv` | `setOnuCatv()` |
| `SupportsVlanManagement` | `setOnuVlans()`, `addVlan()` |
| `SupportsAdvancedOnuConfig` | `setOnuWanMode()`, `updateOnuMode()`, `changeOnuType()`, `updateOnuPonChannel()`, `changeOnuWebPassword()`, `resyncOnuConfig()`, `moveOnu()`, `updateOnuServicePort()`, `setOnuMgmtIp()` |
| `SupportsBulkOperations` | `bulkSetSpeedProfile()`, `getOnusBulk()` |

### 6.3 `SmartOltDriver`

Implementa `OltDriverInterface` + las 9 capacidades opcionales. Delega todo en `OLTsService` por composición (sin duplicar lógica HTTP, budget ni jitter).

### 6.4 `HuaweiDriver`

Implementa solo `OltDriverInterface`. Estado: **READ-ONLY** (Bloque B completo, escritura bloqueada).

| Aspecto | Estado |
|---|---|
| Métodos de lectura | Implementados con `withSession()` — `listOlts`, `listSpeedProfiles`, `getUnconfiguredOnus`, `getOnusByOlt`, `getOnusSignals`, `getOnusStatus`, `getOnuDetails`, `getOnuSignal`, `getOnuStatus`, `findOnuBySn` |
| Métodos de escritura | `authorizeOnu`, `deauthorizeOnu`, `setOnuEnabled`, `rebootOnu`, `setOnuSpeedProfile` → lanzan `WriteNotEnabledException` (stubs explícitos) |
| Sesión Telnet | Sesión única por operación vía `withSession()` — evita múltiples logins que disparan ACL anti-ataque |
| `HuaweiTransport` | `open()` + `exec()` + `close()` + `isOpen()`. `exec()` pasa por `ReadOnlyGuard` antes de enviar |
| `ReadOnlyGuard` | Whitelist de comandos permitidos: `display *`, `screen-length 0 temporary`, `enable`, `config`, `quit`, `return`, `interface gpon N/N`. Todo lo demás lanza excepción |
| `TelnetSession` | Implementa `SessionInterface`. TCP nativo con `stream_socket_client`, manejo de paginador `---- More ----`, strip ANSI |
| `OltDriverManager` | Resuelve driver por `Olt::driver` (match `'smartolt'` → `SmartOltDriver`, `'huawei'` → `HuaweiDriver`, default → `UnknownOltDriverException`) |
| Parsers | `BoardParser`, `OntListParser`, `OpticalBatchParser`, `OpticalInfoParser`, `OntInfoParser`, `AutofindParser`, `VersionParser` — todos probados contra fixtures reales MA5800-X7 |
| Tests | **238 tests verdes** (incluye B2+B3) |

### 6.5 `OltDriverManager`

- Inyectado en `OLTsController` y `OLTsOnuController` (desde B3c/post-B3c).
- `driverFor(Olt $olt)` resuelve por `$olt->driver` — permite flip por fila sin tocar código.
- `default()` retorna el binding global `OltDriverInterface::class` (aún apunta a `SmartOltDriver`) para consumidores no migrados.

---

## 7. Servicio SmartOLT (`OLTsService`)

### Endpoints GET (lectura pura)

| Endpoint SmartOLT API | Método PHP | Uso |
|---|---|---|
| `system/get_olts` | `getOlts()` | Lista de OLTs |
| `olt/get_olts_uptime_and_env_temperature` | `getUpTimeEnviromentTemperatureByOlt()` | Uptime + temperatura |
| `system/get_zones` | `getZones()` | Zonas |
| `system/get_speed_profiles` | `getSpeedProfiles()` | Perfiles velocidad |
| `system/get_odbs` | `getODBs()` | ODBs/splitters |
| `system/get_onu_types` | `getTypeONUs()` | Tipos de ONU |
| `system/get_olt_cards_details/{id}` | `getCardsByOlt($id)` | Tarjetas de OLT |
| `system/get_pon_ports/{id}` | `getPonPortsByOlt($id)` | Puertos PON |
| `olt/get_vlans/{id}` | `getVLANsByOlt($id)` | VLANs de OLT |
| `olt/get_uplink_ports/{id}` | `getUplinkPortsByOlt($id)` | Puertos uplink |
| `olt/get_outage_pons/{id}` | `getOutagePONsByOlt($id)` | PONs con interrupción |
| `onu/get_all_onus_details?olt_id={id}` | `getONUsByOlt($id)` | ONUs completas por OLT |
| `onu/get_onus_signals[?olt_id={id}]` | `getONUsSignals($id)` | Señales bulk |
| `onu/get_onus_statuses[?olt_id={id}]` | `getONUsStatus($id)` | Estados bulk |
| `onu/get_onu_signal/{id}` | `getSignalByExternalId($id)` | Señal individual |
| `onu/get_onu_status/{id}` | `getStatusByExternalId($id)` | Estado individual |
| `onu/unconfigured_onus_for_olt/{id}` | `getUnconfiguredONUs($id)` | No configuradas |
| `onu/get_onu_details_by_sn/{sn}` | `getOnuDetailsBySN($sn)` | Detalle por SN |
| `onu/get_onu_details_by_external_id/{id}` | `getOnuDetailsByExternalId($id)` | Detalle por ID externo |
| `onu/get_mgmt_ip/{id}` | `getMgmTIp($id)` | IP de gestión |
| `onu/get_ip_address/{id}` | `getIpAddress($id)` | IP de ONU |
| `onu/get_full_status/{id}` | `getFullStatus($id)` | Estado completo |
| `onu/get_running_config/{id}` | `getRunningConfig($id)` | Config activa |
| `onu/get_traffic_graph/{id}` | `getTrafficGraph($id)` | Gráfica tráfico |
| `onu/get_signal_graph/{id}` | `getSignalGraph($id)` | Gráfica señal |
| `onu/get_image/{id}` | `getImageONU($id)` | Imagen ONU |

### Endpoints POST (escritura hacia SmartOLT)

| Endpoint SmartOLT API | Método PHP | Acción |
|---|---|---|
| `onu/authorize_onu` | `registerOnu($onu)` | Autorizar/provisionar ONU |
| `onu/delete/{id}` | `removeONU($id)` | Eliminar ONU |
| `onu/enable/{id}` / `disable/{id}` | `enableDisableONU($id, $enabled)` | Habilitar/deshabilitar |
| `onu/reboot/{id}` | `rebootONU($id)` | Reiniciar ONU |
| `onu/resync_config/{id}` | `resyncONUConfig($id)` | Resincronizar config |
| `onu/move/{id}` | `moveONU($id, $data)` | Mover ONU de puerto |
| `onu/update_location_details/{id}` | `updateLocationDetails($id, $data)` | Actualizar ubicación |
| `onu/update_unique_external_id/{id}` | `updateExternalId($id, $data)` | Actualizar ID externo |
| `onu/update_attached_vlans/{id}` | `changeAttachedVlans($id, $data)` | Cambiar VLANs |
| `onu/update_service_port/{id}` | `updateServicePort($id, $data)` | Actualizar service port |
| `onu/set_ethernet_port_{type}/{id}` | `configureEhernetPort()` | Config puerto eth |
| `onu/set_wifi_port_{type}/{id}` | `configureWifiPort()` | Config puerto WiFi |
| `onu/set_onu_mgmt_ip_{type}/{id}` | `setOnuMgmtIp()` | Config IP gestión |
| `onu/set_onu_wan_mode_{mode}/{id}` | `setWanMode()` | Config modo WAN |
| `onu/{enable\|disable}_onu_voip_port/{id}` | `setOnuVoipPort()` | Config puerto VoIP |
| `onu/update_pon_channel/{id}` | `updateChannel()` | Cambiar canal PON |
| `onu/shutdown_ethernet_port/{id}` | `shutdownEhernetPort()` | Apagar puerto eth |
| `onu/shutdown_wifi_port/{id}` | `shutdownWifiPort()` | Apagar puerto WiFi |
| `onu/{enable\|disable}_catv/{id}` | `setCATV()` | CATV on/off |
| `onu/update_onu_speed_profiles/{id}` | `updateSpeedProfile()` | Cambiar perfil velocidad |
| `onu/bulk_update_speed_profiles` | `updateBulkSpeedProfile()` | Cambio bulk perfiles |
| `onu/change_web_user_pass/{id}` | `changeWebUserPass()` | Cambiar contraseña web |
| `system/add_zone` | `addZone()` | Crear zona |
| `system/add_odb` | `addOdb()` | Crear ODB |
| `system/add_onu_type` | `addOnuType()` | Crear tipo ONU |
| `olt/add_vlan/{id}` | `addVlan()` | Agregar VLAN a OLT |

### Control de presupuesto y jitter (GR-1) ✅

`OLTsService` tiene sistema completo:
- `guardBudget(priority, endpoint, count)` — evalúa uso vs budget antes de cada llamada.
- `trackBudget(count)` — incrementa contador en caché por ventana horaria.
- `jitter()` — `usleep(random_int(50,250)*1000)` antes de llamadas `'sync'`.
- Budget configurable: `SMARTOLT_HOURLY_BUDGET` (default 1,000/h).
- Throttle sync al 90 %, bloqueo interactive al 100 %.

---

## 8. Comandos artisan + crons

| Comando | Frecuencia (Kernel.php) | Qué hace | Tablas que toca | L/E hacia SmartOLT |
|---|---|---|---|---|
| `smartolt:sync-inventory` | Diario 05:00, `withoutOverlapping` | Sincroniza OLTs, tarjetas, puertos PON/uplink, VLANs, zonas, ODBs, tipos ONU, perfiles, ONUs | `olts`, `olt_cards`, `olt_pon_ports`, `olt_uplink_ports`, `olt_vlans`, `olt_zones`, `olt_odbs`, `olt_type_onus`, `olt_speed_profiles`, `olt_onus` | L (solo GET) |
| `smartolt:sync-critical` | Cada 10 min, `withoutOverlapping` | Señales, estados, temperaturas, no-configuradas, interrupciones | `olt_onus`, `olt_unconfigured_onus`, `olt_interruption_pons` | L (solo GET) |
| `gestionred:sync-huawei` | Cada 10 min, `withoutOverlapping(15)` | Inventario y señales desde OLTs Huawei por Telnet (sesión única) | `olt_onus` | L (solo display) |
| `smartolt:sync-promotions` | Cada hora, `withoutOverlapping`, `onOneServer` | Revierte perfiles de velocidad en promociones caducadas | `olt_onus` (lectura), SmartOLT API (escritura) | **E** (change_onu_speed → POST) |
| `smartolt:sync-clients-with-ont` | No está en Kernel (manual) | Actualiza `client_additional_information.gpon_ont` con `unique_external_id` | `client_additional_information` | L |
| `promociones:corregir-onu` | Cron a definir | Restaura perfil de velocidad original de una ONU vía SmartOLT | `olt_onus` (lectura) | **E** |

**Nota de escritura:** `smartolt:sync-promotions` y `promociones:corregir-onu` son los únicos comandos que hacen POST hacia SmartOLT API. El resto es lectura pura.

---

## 9. Permisos Spatie (21 permisos registrados en BD)

| Permiso | Descripción |
|---|---|
| `olt_view` | Ver panel de OLTs |
| `olt_add` | Agregar OLT |
| `olt_edit` | Editar OLT |
| `olt_remove` | Eliminar OLT |
| `onu_add` | Agregar/autorizar ONU |
| `onu_edit` | Editar ONU |
| `onu_remove` | Eliminar/desautorizar ONU |
| `onu_enable_disable` | Habilitar o deshabilitar ONU |
| `onu_reboot` | Reiniciar ONU remotamente |
| `onu_resync` | Resincronizar ONU |
| `onu_default` | Restaurar ONU a fábrica |
| `onu_type_add` | Agregar tipo de ONU al catálogo |
| `ipv4_view_ipv4` | Ver subredes IPv4 |
| `ipv4_add_ipv4` | Crear subredes IPv4 |
| `ipv4_edit_ipv4` | Editar subredes IPv4 |
| `ipv4_delete_ipv4` | Eliminar subredes IPv4 |
| `ipv4_export_ipv4` | Exportar subredes IPv4 |
| `router_view_router` | Ver listado de routers MikroTik |
| `router_add_router` | Agregar router MikroTik |
| `router_edit_router` | Editar router MikroTik |
| `router_delete_router` | Eliminar router MikroTik |
| `router_export_router` | Exportar lista de routers |

El módulo **no tiene** `olt_config_smartolt` — ese permiso fue propuesto en Paso 0 pero aún no creado.

---

## 10. Conexiones / integraciones

### 10.1 Clientes

| Punto de conexión | Mecanismo | Cobertura |
|---|---|---|
| `olt_onus.client_id` → `clients.id` | `extractClientId($name)` extrae el número mayor del campo `name` de SmartOLT (ej. `"Cliente 4118"` → `4118`). Heurístico, no garantizado. | 2,903 / 2,954 ONUs (98 %) |
| `ClientDatatableHelper` | JOIN `client_main_information` con `olt_onus` por `client_id` para mostrar `service_ports` en ficha de cliente | Lista de clientes |
| `OLTsOnuController::getByClient($id)` | `POST /olts/onus/get-by-client/{id}` — devuelve ONUs de un cliente | Ficha cliente |
| `smartolt:sync-clients-with-ont` | SQL raw: actualiza `client_additional_information.gpon_ont` con `unique_external_id` de `olt_onus` | Manual/one-shot |

### 10.2 Servicios (GR-2)

| Punto de conexión | Mecanismo | Cobertura |
|---|---|---|
| `olt_onus.service_id` → `client_internet_services.id` | Columna agregada en migración `2026_06_10_200001_backfill_service_id_olt_onus`. Relación: `OltOnu::internetService()` → `belongsTo(ClientInternetService)`. Hay un índice no-único en `service_id`. | 2,606 / 2,954 ONUs (88 %) |
| `PaymentApplicationService` | Código comentado/pendiente — contiene `TODO: wiring Client→OltOnu sin confirmar` (línea ~150-193). **No está activo.** | Pendiente |

### 10.3 Promociones

`RevisionDiariaPromociones` (cron diario):
1. Carga promociones expiradas del módulo Promociones.
2. Busca `OltOnu::firstWhere('client_id', $p->client_id)`.
3. Compara `service_ports` (velocidad real en SmartOLT) contra el plan contratado.
4. Si difieren, llama `Artisan::call('smartolt:sync-promotions')` que hace POST `onu/change_onu_speed` para revertir el perfil.

**Escritura crítica hacia SmartOLT:** el único flujo que escribe perfiles de velocidad a OLTs reales.

### 10.4 Mapas

| Dato | Tabla/Columna | Consumidor |
|---|---|---|
| Coordenadas ODB | `olt_odbs.latitude`, `olt_odbs.longitude` | `OltOdb::label` accessor; `OLTsODBsController` lo sirve al componente `Odbs.vue`. No hay integración con el módulo de Mapas (`system_map_credentials`). |
| Coordenadas ONU | `olt_onus.latitude`, `olt_onus.longitude` | `PanelOnu.vue` puede mostrar la ubicación. No consumido por el módulo de Mapas. |
| ODB con zona | `olt_odbs.zone_id` → `olt_zones.id` | Relación `OltZone::splitters()`. |

**Módulo Mapas (`system_map_credentials`):** no consume datos OLT/ODB directamente — son ecosistemas separados.

### 10.5 Alertas / Notificaciones

`olt_interruption_pons` se usa **solo** en:
- `OLTsController::getDashboardInterruptions()` — sirve la lista de interrupciones al componente `OltInterruptionsPon.vue` del dashboard.
- No está enganchado al motor de notificaciones (`StandardNotification`, `TicketOpen`, etc.). **Suelto** — no genera alertas automáticas.

### 10.6 Dashboard / War Room

| Módulo | Qué consulta | Cómo |
|---|---|---|
| War Room (`KpiController`) | Total, online y offline de ONUs (`olt_onus`) | `DB::table('olt_onus')->count()`, `->where('status','Online')`, etc. (líneas 418-427) |
| War Room (`InsightsService`) | Total, online y offline de ONUs | Mismos conteos directos vía `DB::table('olt_onus')` (líneas 54-56) |
| Talento (`HealthBonusService`) | `odb_name` y `signal_1310` desde `olt_onus` para calcular bono de salud de red | Por `olt_onu_id` o `client_id` de la OT de campo |
| Talento (`TalentoWorkOrderController`) | `olt_onu_id` como campo de la OT | FK a `olt_onus.id`, valida `exists:olt_onus,id` |

### 10.7 SmartImport/Export

`SmartImportService` referencia `olt_onus` e `olt_interruption_pons` como parte del mapa de entidades importables del sistema.

---

## 11. Estado vs roadmap

| Ítem | Estado | Evidencia |
|---|---|---|
| **A0 Fundación BD** | ✅ Hecho | 13 tablas, 13 modelos, migraciones aplicadas |
| **A2 Lectora (sync)** | ✅ Hecho | `smartolt:sync-inventory` + `sync-critical` + crons en Kernel.php |
| **A3 UI** | ✅ Hecho | 75 componentes Vue, panel + dashboard, formularios completos |
| **A4 Clientes/Promos** | ✅ Hecho | `client_id` via heurística, `service_id` con cobertura 88 %, promo-reversion activa |
| **A5 Mapas** | ⚠️ Parcial | Lat/lng en `olt_onus` y `olt_odbs`, pero sin integración con el módulo de Mapas |
| **A6 Alertas** | ⚠️ Parcial | `olt_interruption_pons` se muestra en dashboard; sin motor de notificaciones/alertas automáticas |
| **GR-1 Budget/jitter** | ✅ Hecho | `guardBudget` + `trackBudget` + `jitter()` en `OLTsService` |
| **GR-2 service_id** | ✅ Hecho | Columna existe, backfill aplicado, relación Eloquent declarada; wiring de Pagos pendiente |
| **GR-3 Índice único** | ✅ Hecho | `UNIQUE(sn, olt_id)` + `UNIQUE(unique_external_id)` en `olt_onus` |
| **GR-4 Interfaz** | ✅ Hecho | `OltDriverInterface` + 9 `Supports*` interfaces; `SmartOltDriver` implementa todo |
| **GR-5 Credenciales en tabla** | ❌ Pendiente | `olt_smartolt_config` **NO existe**. Credenciales en `.env` vacíos (`SMARTOLT_DOMAIN`, `SMARTOLT_TOKEN`) |
| **GR-6 Encapsular modelos** | ❌ Pendiente | 13 modelos en `app/Models/Olt*.php` — fuera del módulo |
| **Bloque B — B1–B3 lectura** | ✅ Hecho | `HuaweiDriver` + `HuaweiTransport` + `ReadOnlyGuard` + 7 parsers + 238 tests; `gestionred:sync-huawei` integrado; `OltDriverManager` inyectado en controllers; `PanelOnu.vue` con indicador `syncing` |
| **Bloque B — escritura nativa** | ❌ Pendiente | Stubs de escritura en `HuaweiDriver` lanzan `WriteNotEnabledException`. Requiere implementación real (Bloque C) |
| **Bloque C — SaaS** | ❌ Pendiente | No iniciado |

---

## Deuda técnica no registrada previamente

1. **`PaymentApplicationService` — wiring ONU→Pago comentado** (`TODO: wiring Client→OltOnu sin confirmar` en líneas 150–193). El flujo de activación automática de ONU al aplicar un pago está scaffoldeado pero desactivado. No está en la Hoja de Ruta como ítem abierto.

2. **`smartolt:sync-clients-with-ont` — no tiene cron** — solo se puede correr manualmente. Sin schedulear, `client_additional_information.gpon_ont` puede desincronizarse. No está marcado como pendiente.

---

## Resumen ejecutivo

**Sólido:** La pila es madura y bien probada. El stack SmartOLT (servicio, drivers, models, 75 Vue components, 73 rutas, crons, budget/jitter, permisos) está 100 % operativo. El Bloque B (driver Huawei nativo, lectura validada contra MA5800-X7 real, 238 tests verdes, sync-huawei cada 10 min) está completo.

**Parcial:** Mapas (lat/lng existen pero no se conectan al módulo de Mapas). Alertas (interrupciones visibles en dashboard pero sin motor de notificaciones). Service-id tiene 88 % de cobertura pero el wiring con Pagos está comentado.

**Pendiente crítico:** Credenciales SmartOLT vacías (`SMARTOLT_DOMAIN`/`SMARTOLT_TOKEN`). Sin ellas, los crons de sync y toda la UI interactiva fallan. La tabla `olt_smartolt_config` propuesta en Paso 0 aún no fue creada — es el siguiente paso más urgente.

**Siguiente paso lógico:** ejecutar los Pasos 1–4 del plan aprobado en sesión anterior: crear `olt_smartolt_config`, rewire de `AppServiceProvider`, UI de configuración con credenciales enmascaradas, botón "Probar conexión" y botón "Importar inventario" (que reutilizará `syncOlts()` + `Olt::syncOnus()` existentes).
