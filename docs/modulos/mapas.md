# Módulo Mapas

> Mapa GIS de la red FTTH (postes, rutas de fibra, NAPs, cajas, equipos activos/pasivos, clientes) + capa legacy de proyectos/zonas georreferenciadas. `app/Modules/Addons/Mapas/` · slug `addon-mapas` · addon, depende de `core-configuracion`, activo.

**En simple:** es el mapa donde el equipo técnico ve y dibuja toda la red de fibra óptica (postes, cables, cajas, cuadros de distribución) y ubica a cada cliente sobre el terreno.

## 1. Qué es
Addon que agrupa dos sub-dominios georreferenciales: **Mapas** (infraestructura física FTTH — postes, rutas de fibra, cajas, equipos activos/pasivos, splitters, puertos) y **Geo/Zonas** (capa legacy de proyectos/carpetas con capas, marcadores y rutas, incluida la carga de planos KMZ/KML).

## 2. Para qué sirve
Le da al equipo de red un único mapa interactivo (Leaflet + Google Maps) para trazar y mantener la planta externa de fibra: dar de alta postes, gabinetes, cajas de empalme/distribución (NAP), rutas de fibra y equipos (activos/pasivos, splitters, transceivers), ubicar clientes sobre el mapa y ver las zonas de cobertura. También sirve como bandeja de entrada para importar planos externos en formato KMZ/KML y organizarlos por proyecto/capa.

## 3. Cómo funciona
- **Sub-dominio "Mapas" — infraestructura física FTTH** (`Controllers/Mapas/`, prefix `/mapas`):
  - `MapasController` — controlador central: vista principal (`index`), formularios dinámicos por tipo de objeto (`getForm`/`getDataForm`), alta/edición genérica de objetos del mapa (`objectCreate`/`updatePosition`), ventanas de info, listados para selects y catálogo de cortes de fibra (`fiberCutStore/Update/Destroy`).
  - Controladores por entidad (uno por tipo de elemento del mapa): `PoleController`/`PoleAccessoryController` (postes), `BoxController`/`BoxTypeController`/`BoxInputController` (cajas de distribución/NAP), `SiteController` (sitios de red), `SplitterController` (divisores ópticos), `PortController`/`EquipmentLinkController` (puertos y sus conexiones/fusiones), `ActiveEquipmentController`/`PassiveEquipmentController` (+ sus `*TypeController` de catálogo), `RackController`/`CardController`/`TrayController`/`TransceiverController` (equipo activo en rack), `TrenchController`/`TrenchTypeController`/`TubeController`/`TubeTypeController` (obra civil/ductos), `FiberController`/`BufferController` (hilos y buffers de fibra), `PointController`/`PointAccessoryController` (puntos genéricos), `MaplinkController`/`MapRouteController` (enlaces y rutas trazadas sobre el mapa), `BrandController`/`TableController` (catálogos de marca/tabla).
  - `MapCredentialController` — CRUD de la credencial de mapa (`api_key`, latitud/longitud/zoom por defecto) y el endpoint compartido `renderConfig()`, consumido por cualquier componente que renderice un mapa en el sistema (ver sección 4).
  - Modelos en `App\Models\*` (no en `Mapas/Models/`, que está vacío): `Pole`, `Box`, `BoxInput`, `Site`, `Splitter`, `Port`, `ActiveEquipment`, `PassiveEquipment`, `Tray`, `Transceiver`, `Trench`, `Point`, `PoleAccessory`, `Table`, `Color`, `CutFiber`, `MapCredential`, entre otros.
- **Sub-dominio "Geo/Zonas" — legacy** (`Controllers/Geo/`, prefix `/maps`):
  - `ProyectsController` (`MapProyectRepository`) — árbol de proyectos/carpetas (`MapProyect`); lista clientes con/sin proyecto asignado y zonas de cobertura (`ConnectionsController::zones`).
  - `LayersController` (`MapLayerRepository`, traits `LayerConfig`/`LayerRoutes`) — CRUD de capas (`MapLayer`) dentro de un proyecto: marcadores, coordenadas, rutas de fibra asociadas (`MapLayerRoute`/`MapFiber`), conversión de capas desde proyectos/tickets y clasificación de elementos.
  - `KMZController` — parsea archivos `.kmz`/`.kml` (vía `XMLReader`) y los convierte en capas/marcadores del proyecto.
  - `ConnectionsController`/`DevicesController` — conexiones entre dispositivos de red (`MapDevicePortConnection`) y catálogo de dispositivos (`MapDevice`/`MapDevicePort`).
  - `ServiceBoxController` — asignación/remoción de clientes a una caja de servicio y su puerto.
- **Frontend:** `resources/js/components/module/maps/LeafletMap.vue` (mapa Leaflet reusable) + `ApiKey.vue` (config de credencial); helpers `googleMapsConfig.js`/`googleMapsVariables.js` y composables `useNodeMap.js`/`useMapConnections.js` consumen `renderConfig()` para pintar el mapa con la key/posición configurada.
- **Rutas:** ambas familias (`/maps/*` y `/mapas/*`) declaradas en `Mapas/routes.php` bajo `Route::middleware(['web','auth','check_route_permission'])` (el `web` explícito es necesario porque `loadRoutesFrom()` no aplica ese grupo automáticamente).

## 4. Qué EXPONE / qué CONSUME
**Expone**
- **Rutas** bajo `/mapas/*` (infraestructura FTTH, ~90 endpoints por tipo de entidad) y `/maps/*` (proyectos/capas/KMZ/conexiones/dispositivos/service-box), vista principal `GET /mapas` y `GET /maps/zones`.
- **Permisos** (`maps_view_maps`, `maps_pole_*`, `maps_route_*`, `maps_pack_*` NAP, `maps_cupboard_*` gabinete, `maps_folder_*`/`maps_junction_box_*`/`maps_service_box_*` cajas, `maps_kmz_load/edit/remove`, `maps_change_classification`, entre otros — ver `module.json`).
- **Menú sidebar** "Mapas" (Mapa de Red, Zonas) y tarjeta de administración "Mapas de Red".
- **Servicio de credencial de mapa COMPARTIDO** (designado en CLAUDE.md como único para todo el sistema): `MapCredentialController::renderConfig()` — registrado en `Core/Configuracion/routes.php` como `GET /configuracion/credenciales-google-maps/render-config` — devuelve la `api_key` real (de cliente, viaja al navegador) + posición/zoom por defecto. Lo consumen `LeafletMap.vue`, MegaFamilia y cualquier widget de posición geo del sistema; **nadie más debe montar su propio cliente de Google Maps ni su propia key**.
- **Config section** `/configuracion/credenciales-google-maps` (alta/edición/borrado de la credencial).

**Consume**
- **`ClientMainInformation`** (Clientes) — para listar clientes con/sin proyecto y ubicarlos en el mapa.
- **`Module`** (catálogo `modules`) — resolución de formularios dinámicos por tipo de objeto (`getForm`/`getDataForm`).
- Middleware estándar `check_route_permission` (mismo gating que el resto del sistema, sin verificador propio).

> _Servicios compartidos únicos respetados: Mapas ES el proveedor designado de credenciales/mapas para todo el sistema (no lo consume de otro módulo). No monta cliente HTTP propio de IA ni de WhatsApp. Sin entrada propia en el registro de contratos inter-módulos (`docs/contratos/`) al momento de esta doc._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
