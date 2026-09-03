# MR-01a — Mapas: inventario de tablas, relaciones y peso en disco (item #1000000)

Auditoría READ-ONLY (puntos 1 y 8 del prompt de #937). Cero escrituras: solo `SELECT` /
`information_schema`. Fecha: 2026-09-03. Entorno: DEV.

## 0. Ubicación de las migraciones (confirmado)

`app/Modules/Addons/Mapas/migrations/` está **vacío** (solo `.gitkeep`). Las tablas reales viven
en `database/migrations/` — 77 archivos cuyo nombre matchea
`map|geo|box|splitter|pole|fiber|tube|trench|port|rack|equipment|site|card|transceiver|brand|tray|point|position|zone`
(la estimación original del item era ~66; el conteo real con el patrón ampliado, incluyendo
`point`/`position`/`zone`, da 77).

## 1. Inventario de modelos → tabla → filas

Universo confirmado por controller→repository real dentro de
`app/Modules/Addons/Mapas/Controllers/{Mapas,Geo}/` (39 modelos; se descartaron `OltCard`,
`OltPonPort`, `OltUplinkPort` y `SettingToolsImport` — 0 referencias desde el módulo Mapas, son
de otros módulos aunque el grep de nombres los incluía).

| Modelo | Tabla | Filas |
|---|---|---|
| MapDevicePort | map_devices_ports | 19,229 |
| MapFiber | map_fibers | 17,242 |
| MapDevice | map_devices | 8,785 |
| MapDevicePortConnection | map_devices_ports_connections | 8,740 |
| MapLayer | map_layers | 6,689 |
| MapCutFiber | map_fibers_cut | 3,596 |
| MapLayerRoute | map_layers_routes | 2,130 |
| MapProyect | map_proyects | 1,715 |
| MapPort | map_ports | 17 |
| BoxZone | box_zones | 1 |
| MapCredential | system_map_credentials | 1 |
| MapConnection | *(no existe tabla `map_connections`)* | — |
| MapLink, MapRoute, ActiveEquipment(+Type+Peripheral), Box(+Input+Type), Brand, Card, CutFiber, EquipmentLink, Fiber, PassiveEquipment(+Type), Pole(+Accessory), Port, Rack, Site, Splitter, Transceiver, Tray, TrencheTypes, Trench, Tube, TubeType | (36 tablas restantes) | **0** cada una |

**Hallazgo:** el modelo `MapConnection` (`app/Models/MapConnection.php`) apunta a una tabla
`map_connections` que **no existe** en el esquema — clase huérfana o migración nunca corrida.
Su repositorio vive aparte en `app/Repositories/Maps/MapConnectionRepository.php` (namespace
distinto al resto de repos de Mapas, que están planos en `app/Repositories/`).

**Hallazgo mayor:** de los 39 modelos del "catálogo de inventario formal" (Box, Pole, Site,
Trench, CutFiber, Splitter, Rack, Card, Transceiver, Tray, ActiveEquipment,
PassiveEquipment, Brand, Port, Fiber, EquipmentLink, MapLink, MapRoute…) **36 están en 0 filas**
en DEV. Toda la data real y viva del módulo (33k+ filas) vive en la familia `map_devices*` /
`map_fibers*` / `map_layers*` / `map_proyects` — el "diagrama de topología/canvas", no el
"inventario físico formal".

## 2. Relaciones — FKs declaradas (constraint real, `information_schema.KEY_COLUMN_USAGE`)

Grafo completo (109 FKs) disponible en la corrida; resumen de las no-triviales (se omiten
`created_by`/`updated_by → users.id`, presentes en casi todas las tablas de `BaseModel`):

- `boxes.box_type_id → box_types.id`, `boxes.point_id → points.id`, `boxes.map_proyect_id → map_proyects.id`
- `splitters.box_id → boxes.id`, `trays.box_id → boxes.id`
- `box_inputs.box_id → boxes.id`
- `poles.map_proyect_id → map_proyects.id`, `pole_accessories.pole_id → poles.id`
- `racks.site_id → sites.id`, `active_equipments.rack_id / passive_equipments.rack_id → racks.id`
- `active_equipments.type_id → active_equipment_types.id` (mismo patrón en passive)
- `cards.active_equipment_id → active_equipments.id`, `transceivers.card_id → cards.id`
- `cut_fibers.passive_equipment_id → passive_equipments.id`
- `fibers.buffer_id → buffers.id`, `fibers.color_id → colors.id` (catálogos compartidos fuera del
  módulo Mapas propiamente)
- `equipment_links.fiber_id → fibers.id`, `.input_id/.output_id → ports.id`, `.map_link_id → map_links.id`
- `map_links.map_route_id → map_routes.id`, `.tube_id → tubes.id`
- `tubes.tube_type_id → tube_types.id`
- `trenches.trenche_type_id → trenche_types.id`
- `box_zones.zone_id → zones.id` (tabla `zones` fuera del inventario del item, 1 fila)
- `map_devices.layer_id → map_layers.id`, `map_devices.parent_id → map_devices.id` (auto-referencia, árbol)
- `map_devices_ports.device_id → map_devices.id`, `.client_id → client_main_information.id`
- `map_devices_ports_connections.layer_id/from_route_id/to_route_id → map_layers*`
- `map_fibers.fiber_id → map_layers.id` ⚠️ **nombre engañoso**: la columna se llama `fiber_id`
  pero NO apunta a `fibers`/`map_fibers`, apunta a `map_layers.id`.
- `map_fibers_cut.fiber_id → map_fibers.id`, `.layer_id → map_layers.id`, `.route_id → map_layers_routes.id`
- `map_layers.project_id → map_proyects.id`, `map_layers.service_box_id → map_layers.id` (auto-ref, **nunca usada**: 0/6689 filas con valor)
- `map_layers_routes.layer_id/.route_id → map_layers.id` (ambas apuntan a la misma tabla)
- `map_proyects.parent_id → map_proyects.id` (auto-referencia; **1,711 de 1,715** filas la usan → jerarquía profunda de proyectos/carpetas)
- `map_ports.client_id → client_main_information.id`
- `map_routes.map_proyect_id → map_proyects.id`

### Relaciones implícitas (columnas `*_id` sin FK formal — todas son polimórficas Eloquent `morphTo`, no FKs olvidadas)

| Tabla.columna | Su par `*_type` |
|---|---|
| `ports.portable_id` | `ports.portable_type` |
| `map_layers.layerable_id` | `map_layers.layerable_type` (**siempre NULL**, 0/6,689 — dead column) |
| `map_ports.device_id` | `map_ports.device_type` |
| `map_devices_ports_connections.from_id` / `.to_id` | `.from_type` / `.to_type` |
| `map_links.input_id` / `.output_id` | *(sin `*_type` acompañante — revisar si es FK simple sin declarar o polimórfica a medias; tabla en 0 filas, no verificable con datos)* |

MySQL no puede declarar FK real sobre una columna polimórfica (el tipo de la tabla referenciada
varía por fila), así que la ausencia de constraint ahí es **por diseño**, no un descuido — salvo
`map_links.input_id/output_id`, que al no tener columna `*_type` sí podría ser un caso legítimo de
FK simple no declarada (tabla vacía, sin forma de confirmar con datos reales).

## 3. El hallazgo central: TRES capas de representación geográfica, y solo una está viva

Investigando el punto 8 (lat/lng) se encontró que **ninguna** de las tablas de "inventario
formal" (Box, Pole, Site, Trench, CutFiber) tiene columnas `lat`/`lng`/`latitude`/`longitude`
directas. El diseño real usa una tabla polimórfica dedicada:

- **`positions`** (`point` MySQL spatial, `positionable_id`/`positionable_type` → Site, Box,
  Pole, CutFiber, Point, Trench), consumida por `PositionRepository` vía `ST_X()`/`ST_Y()`.
  **0 filas.** Como las tablas que referenciaría (boxes, poles, sites, trenches, cut_fibers,
  points) también están en 0, es un sistema completo (tabla + 6 entidades + repo) sin un solo
  dato cargado en DEV.
- **`map_devices`/`map_ports`** tienen `position_x`/`position_y` (`smallint`) — **no son
  coordenadas geográficas**, son coordenadas de canvas/diagrama (ej. árbol de splitters, layout
  de rack). Confirmado con muestra: `position_x/y` en rangos como `(20,20)`, `(456,-98)`.
- **`map_layers.coords`** (JSON) — **aquí vive el 100% de la data geográfica real y viva** del
  módulo: 6,689/6,689 filas con `coords` no-nulo, `0` vacíos, `0` malformados, `0` con algún punto
  en `(0,0)`. Formato `[{"lat":19.72...,"lng":-99.09...}, ...]`. Distribución por `type`:
  `marker` 3,351 · `polyline` 3,084 · `polygon` 254.

**Consecuencia para el punto 8 del prompt de #937:** la pregunta "¿cuántos elementos con lat/lng
tienen esas columnas NULL o en (0,0)?" tiene dos respuestas honestas:
- Sobre las tablas de inventario formal (Box/Pole/Site/Trench/CutFiber/Point) vía `positions`:
  **0 de 0** — no hay filas, el subsistema está en 0% de adopción en DEV.
  Sobre `map_layers.coords` (la representación que sí está viva y sí se renderiza en el mapa):
  **0 de 6,689** con problema — todas tienen coordenadas válidas y distintas de `(0,0)`.
- **Y lo más importante:** `map_layers.layerable_id`/`layerable_type` (el puente que debería
  ligar cada marker/polyline/polygon a su Box/Pole/Site real) está en **NULL el 100% de las
  veces**. Es decir, el mapa que se dibuja hoy es una capa de dibujo autónoma, desconectada del
  modelo de inventario formal — un box dibujado en el mapa no es fila de `boxes`, es una entrada
  de `map_layers` sin vínculo a ninguna tabla de negocio.

## 4. Peso en disco (`information_schema.TABLES`, MB = `(DATA_LENGTH+INDEX_LENGTH)/1024/1024`)

| Tabla | Total MB | Data MB | Index MB | Filas (aprox.) |
|---|---|---|---|---|
| map_devices_ports_connections | 6.453 | 2.516 | 3.938 | 8,593 |
| map_layers | 3.875 | 3.516 | 0.359 | 6,480 |
| map_devices_ports | 3.453 | 1.516 | 1.938 | 19,523 |
| map_devices | 2.172 | 1.516 | 0.656 | 8,414 |
| map_fibers | 1.906 | 1.516 | 0.391 | 17,054 |
| map_fibers_cut | 0.750 | 0.266 | 0.484 | 3,596 |
| map_proyects | 0.469 | 0.172 | 0.297 | 1,715 |
| map_layers_routes | 0.375 | 0.188 | 0.188 | 2,130 |
| *(resto: 30 tablas en 0 filas)* | ≤0.109 c/u | — | — | 0 |

**Total del módulo (39 tablas del inventario): ~21.5 MB.** Concentrado casi en su totalidad
(≈19.5 MB, 91%) en las 8 tablas de la capa de topología/canvas (`map_devices*`, `map_fibers*`,
`map_layers*`, `map_proyects`). Las 31 tablas restantes (todo el "inventario físico formal":
Box/Pole/Site/Rack/Card/Transceiver/Splitter/Trench/Tube/…) pesan ~2 MB en conjunto, casi todo
overhead de índice vacío.

Tablas auxiliares fuera del inventario de 39 pero referenciadas por FK desde él (para contexto):
`buffers` 0.063 MB (0 filas), `colors` 0.078 MB (0 filas), `zones` 0.031 MB (1 fila),
`points` 0.063 MB (0 filas), `point_accessories` 0.078 MB (0 filas), `positions` 0.047 MB
(0 filas), `system_map_credentials` 0.016 MB (1 fila).

## 5. Nota de seguridad (hallazgo colateral, fuera de alcance del item — no se actúa)

`system_map_credentials` (1 fila) guarda un `api_key` de Google Maps **en texto plano** en la
tabla, junto a `latitude`/`longitude`/`zoom` (centro/zoom default del mapa). No se reproduce el
valor aquí por la regla de secretos del proyecto (`CLAUDE.md` § Convenciones). Es un dato para
un futuro item de hardening (mover a `.env` / Integration Hub), no se toca en esta auditoría
read-only.

## 6. Resumen ejecutivo para #937

1. El módulo Mapas tiene una arquitectura de **dos sistemas paralelos que casi no se tocan**:
   (a) el "inventario físico formal" (Box/Pole/Site/Rack/Splitter/…, con FKs completas y bien
   diseñadas, pero **vacío en DEV**) y (b) la "capa de dibujo geográfico" (`map_layers.coords`
   JSON, con **toda la data real**, 6,689 filas, pero sin vínculo — `layerable_id` siempre NULL —
   al inventario formal).
2. `MapConnection` es un modelo huérfano (tabla inexistente).
3. El 91% del peso en disco del módulo está en la familia `map_devices*`/`map_fibers*`/
   `map_layers*`, no en el inventario físico.
4. Cero problemas de coordenadas `(0,0)`/NULL en la data que sí existe (`map_layers.coords`
   100% limpio); el "problema" de coordenadas es en realidad un problema de **adopción cero**
   del subsistema `positions`/Box/Pole/Site, no de calidad de datos.
