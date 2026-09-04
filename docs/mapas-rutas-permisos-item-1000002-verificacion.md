# MR-01c — Mapas: rutas vivas vs muertas, relación cliente-caja y permisos que gatean (item #1000002)

Auditoría **READ-ONLY** (sub-item de seguimiento de #937, puntos 3, 5 y 7 de su prompt). Cero
escrituras a código/esquema/datos — este documento es el entregable.

---

## Contexto: dos sub-módulos que conviven bajo "Mapas"

`app/Modules/Addons/Mapas/routes.php` (311 líneas, 176 declaraciones `Route::`) agrupa **dos
sub-dominios** con prefijos e historias distintas — el propio archivo lo documenta en su
cabecera ("Geo... Mapas... Pendiente de migrar"):

| Grupo | Prefijo | Namespace controllers | Rutas (route:list, expandidas) |
|---|---|---|---|
| **Geo** | `maps/*` | `Controllers\Geo\*` (Connections, Devices, KMZ, Layers, Proyects, ServiceBox) | **64** |
| **Mapas (infra FTTH)** | `mapas/*` | `Controllers\Mapas\*` (MapasController + 29 controllers: Box, Pole, Splitter, Rack, Site, Trench, Tube, Transceiver, Card, Port…) | **131** |

Total **195** rutas vía `php artisan route:list --json` filtrado a URIs que empiezan con
`maps/` o `mapas/` (el conteo de 176 del prompt original cuenta líneas `Route::` en el archivo
fuente, sin expandir los `Route::resource(...)`; ambos números son correctos, solo miden cosas
distintas — 176 declaraciones fuente → 195 rutas HTTP reales tras expandir 2 `Route::resource`
completos + 2 `->except('index')`).

---

## Punto 3 — Rutas vivas vs muertas

**Metodología:** para cada ruta se buscó su nombre (`route('nombre')`) y su URI literal
(`axios.get/post/put/delete('/maps/...')`, `fetch`, `href`) en **todo** `resources/js/**` y
`resources/views/**` (no solo el módulo Mapas — un consumidor podría vivir en otro módulo).
Se verificó además, para los casos ambiguos, si el método del controlador referenciado por la
ruta existe de verdad.

### Grupo Geo (`maps/*`, 64 rutas) — **VIVO en su inmensa mayoría**

Es el motor real del mapa actual: `LeafletMap.vue` + sus componentes (`ProjectsComponent`,
`KMZComponent`, `ClientComponent`, `RouteComponent`, …) llaman a estas rutas vía los helpers
`resources/js/components/module/maps/helper/{request,connections-request,devices-request,
layers-request}.js`. Confirmado con evidencia directa (URIs literales encontradas en el código
fuente, no en el bundle compilado):
- `layers-request.js` → `avaiables-routes`, `create-input`, `update-input`,
  `update-markers-distance-from-route`, `change-route-position`, `assign-routes`,
  `unassign-route`, `layers/configuration/{id}` — todas con caller real.
- `request.js` → `/maps/zones`, `/maps/get-clients` (via `ClientComponent.vue`),
  `/maps/clients-without-project`, `/maps/layers*`, `/maps/projects*`, `/maps/kmz`,
  `/maps/change-classification`, `/maps/client-to-service-box/{client}/{box}`,
  `/maps/service-box/{selected-clients,avaiables-clients,remove-clients,remove-client,
  add-clients,remove-client-from-drop}`.
- `connections-request.js` / `devices-request.js` → los `Route::resource('/connections')`,
  `Route::resource('/devices')` y sus rutas extra (`save-port`, `add-ports`,
  `change-card-olt-direction`, `connections-multiple`, `connections/cut`) — todas con caller.

**Excepciones muertas/rotas dentro de este grupo (evidencia puntual):**
1. **`GET /maps/layers/devices/{id}` → `LayersController::devicesFromRack` — el método
   NO EXISTE en el controlador** (`grep "public function"` sobre
   `Controllers/Geo/LayersController.php` no lo lista). Ruta sin nombre, sin ningún caller en
   frontend → muerta **y** rota (si alguien la invocara, 500 `Method does not exist`).
2. **`POST /maps/service-box/save-port/{id}` (`ServiceBoxController::savePort`)** — sin ningún
   caller en `resources/js`/`resources/views`. Método existe pero la ruta está huérfana.

### Grupo Mapas/infra (`mapas/*`, 131 rutas) — **MUERTO como bloque, con evidencia directa**

Ningún endpoint de este grupo (ni el genérico de `MapasController`: `get_form`,
`object/create`, `objects/get`, `info_window`, `data_form`, `catalog_form`,
`site/poles`, `object/position/update`, etc.; ni los ~29 CRUD de Box/Pole/Splitter/Rack/
Trench/Tube/Card/Port/Transceiver/…) tiene **un solo** caller en `resources/js/**` ni en
`resources/views/**` fuera de sí mismo. Verificado por triangulación, no solo ausencia de grep:

- `MapasController::index()` (la única ruta de este grupo alcanzable por navegación —
  `/mapas/` está en el sidebar, `module-sidebar/mapas.blade.php:3`) **renderiza el mismo
  `meganet.module.mapas.index` que solo monta `<leaflet-map />`** — el componente del grupo
  Geo. O sea: visitar `/mapas/` en el navegador de hecho carga la SPA del grupo Geo; nunca
  llega a pintar las vistas Blade legacy de este grupo (`data/*.blade.php`,
  `objectForms/*.blade.php`).
- Esas vistas Blade legacy (`box.blade.php`, `point.blade.php`, `splitter.blade.php`, …)
  **sí existen** y contienen `onclick="destroyObject(...)"` / `onclick="backObject(...)"`
  apuntando a `route('maps.'.$objectTable->type.'.destroy')` / `route('maps.getDataFormById')`
  — pero **`destroyObject` y `backObject` no están definidos en ningún archivo de
  `resources/js`** (`grep -rn "function destroyObject\|window.destroyObject"` → 0 resultados).
  Son vistas huérfanas: ni siquiera el HTML que las serviría (`getDataForm`/`getCatalogView`)
  tiene quién lo pida.
- Esto **confirma con evidencia el propio comentario del archivo fuente**
  (`app/Modules/Addons/Mapas/routes.php:44-46`: *"Mapas (legacy prefix=mapas)... Pendiente de
  migrar"*): es el sub-módulo predecesor, reemplazado por Geo/`LeafletMap.vue`, nunca retirado.

**Hallazgo colateral (bug real, fuera de alcance arreglar — item READ-ONLY):** el propio grupo
Geo tiene componentes de **configuración de rack** (`RackComponent.vue`, importado y usado)
cuyo helper `site-request.js::saveRack` llama a `POST /maps/sites/racks` — ruta que **no existe
en ningún `routes.php` del repo** (`php artisan route:list | grep 'maps/sites'` → vacío).
Mismo patrón en `olt-request.js` (`/maps/olts`), `organizers-request.js` (`/maps/organizers`),
`switch-request.js` (`/maps/switchs`) y en `request.js` (`saveSplitter`/`destroySplitter`/
`splittersFromBox` → `/maps/splitters*`) — pero estas 4 últimas **no están importadas por
ningún `.vue`** (solo `saveRack` lo está), así que solo `RackComponent.vue` es un bug vivo; las
otras son código muerto en ambos lados (frontend Y backend). Explicación con evidencia: la
migración `2025_08_13_103707_create_map_devices_table.php` migra los datos de los modelos
viejos `MapOlt`/`MapOrganizer`/`MapSwitch`/`MapSiteRack` al nuevo esquema unificado
`map_devices`/`map_devices_ports` — sus rutas CRUD dedicadas se dieron de baja en ese momento,
pero `RackComponent.vue` se quedó llamando al endpoint viejo.

---

## Punto 5 — Relación cliente ↔ caja hoy

**No existe** ninguna columna directa en `clients`/`client_main_information` (`box_id`,
`caja_id`) ni en `boxes` (`client_id`) — verificado con `Schema::hasColumn()` en vivo:

```
map_devices_ports.client_id ......... SI
map_layers.service_box_id ........... SI
client_main_information.box_id ...... NO
client_main_information.caja_id ..... NO
clients.box_id ....................... NO
boxes.client_id ...................... NO
```

La relación real, **vigente y usada** (grupo Geo, el que está vivo) es:

- **`map_devices_ports.client_id`** — FK directa a `client_main_information.id`
  (`->foreign('client_id')->references('id')->on('client_main_information')->cascadeOnDelete()`,
  migración `2025_08_13_103707_create_map_devices_table.php`). Cada puerto de un dispositivo
  del mapa (splitter, organizador, switch, OLT — todos modelados como filas de `map_devices`)
  puede tener un cliente colgado. `ServiceBoxController::getSelectedClients` /
  `getAvaiablesClients` consultan `client_main_information` vía subquery
  `map_devices_ports.client_id` join `map_devices` (evidencia: SQL crudo en
  `Controllers/Geo/ServiceBoxController.php:20-27`).
- **`map_layers.service_box_id`** — FK **auto-referencial** (`map_layers.id` → otra fila de
  `map_layers`), añadida por la migración `2025_07_21_113642_add_service_box_to_client.php`.
  ⚠️ **El nombre de la migración es engañoso**: dice "add_service_box_to_client" pero **no
  toca ninguna tabla de clientes** — modifica `map_layers`. La "caja de servicio" de un cliente
  es otra fila `map_layers` (un marcador del mapa), no un registro de `clients`.

El modelo **`Box.php`** (`App\Models\Box`, el "caja" del grupo Mapas/infra, el que está
**muerto** según el punto 3) **no tiene ninguna relación con cliente** — solo
`mapProyect()`, `type()` (BoxType), `inputs()` (BoxInput), `trays()`. Confirma que ese
sub-módulo legacy nunca llegó a modelar la relación cliente-caja; quien la resolvió fue el
reemplazo (grupo Geo).

**Conclusión punto 5:** hoy la relación cliente↔caja es **indirecta, vía tabla intermedia**
(`map_devices_ports`, con FK real a `client_main_information`) — no es un campo de texto
libre ni una FK directa en la tabla de clientes.

---

## Punto 7 — Qué permiso Spatie gatea cada grupo

`config/route_permission.php` — bloque `//Mapas` (líneas 553-810 aprox.) declara **44 llaves
`maps_*`** distintas (`maps_view_maps`, `maps_kmz_{load,edit,remove}`,
`maps_folder_{add,edit,remove}`, `maps_region_*`, `maps_route_*`, `maps_service_box_*`,
`maps_junction_box_*`, `maps_pack_*`, `maps_cupboard_*`, `maps_source_*`, `maps_pole_*`,
`maps_site_*`, `maps_building_*`, `maps_client_*`, `maps_note_*`, `maps_change_classification`).
**Todas** gatean patrones de URL bajo el prefijo `/maps/*` (grupo Geo) — verificado con
`grep -n "'/mapas/` sobre el archivo completo: **0 resultados**.

- **Grupo Geo (`/maps/*`)**: cubierto por esas 44 llaves. Granularidad por acción
  (add/edit/remove) y por tipo de objeto de mapa (kmz, folder, region, route, service_box,
  junction_box, pack, cupboard, source, pole, site, building, client, note).
- **Grupo Mapas/infra (`/mapas/*`, 131 rutas)**: **cero patrones declarados** en
  `route_permission.php`. Efecto en `CheckRoutePermission::handle()`
  (`app/Modules/Core/Auth/Middleware/CheckRoutePermission.php:58-93`):
  1. Paso 3 — si el usuario es `isAdmin()`/`isDevelopment()`/`isSuperAdmin()` → pasa sin
     revisar nada.
  2. Paso 4 — para cualquier otro usuario, se filtra `route_permission` buscando una llave
     cuyo patrón matchee el path Y que el usuario la tenga. Como **ninguna llave** tiene un
     patrón `/mapas/...`, la colección siempre queda vacía para este prefijo.
  3. Paso 5 — sin match → denegado (403, o redirección silenciosa a Dashboard si es
     navegación de página completa, según la política del item #537).
  → **Efecto neto: el grupo Mapas/infra es admin/DESARROLLADOR-only por omisión**, no por
  una regla explícita — nadie más puede alcanzarlo aunque quisiera (y, por el punto 3,
  tampoco tendría con qué: la UI que lo invocaría no existe).

**Hallazgo colateral (config, no ruta):** 3 patrones dentro del bloque `maps_*` están **mal
escritos** (les falta el prefijo `/maps`) y por tanto no matchean ninguna ruta real:
`maps_pack_edit` → `/devices`, `/devices/{id}`, `/devices/save-port/{id}` (línea ~696-698);
`maps_site_edit` → `/sites/racks`, `/sites/racks/{id}` (línea ~769-770, el mismo path relativo
que el bug de `RackComponent.vue` del punto 3 — **consistente**: ambos apuntan al viejo
endpoint sin prefijo `/maps`, ninguno de los dos existe). No se corrigen aquí (fuera de
alcance de una auditoría read-only) — quedan documentados para quien retome el punto 3
(reconectar `RackComponent.vue` al esquema `map_devices` actual).

---

## Resumen ejecutivo

| Punto | Respuesta corta |
|---|---|
| 3 — vivas/muertas | Grupo **Geo** (`maps/*`, 64 rutas) casi todo vivo, 2 rutas huérfanas puntuales (una además rota). Grupo **Mapas/infra** (`mapas/*`, 131 rutas) **muerto en bloque** — reemplazado por Geo, nunca retirado. Bug colateral real: `RackComponent.vue` (vivo) llama a un endpoint que no existe. |
| 5 — cliente↔caja | FK real `map_devices_ports.client_id` → `client_main_information.id` (grupo Geo). El modelo `Box` del grupo muerto no tiene relación con cliente. |
| 7 — permisos | 44 llaves `maps_*` cubren el grupo Geo con granularidad fina. El grupo Mapas/infra no tiene ninguna llave propia → cae al bypass admin-only del middleware (nadie más puede entrar, ni falta que le hace). |

**Sin cambios de código/esquema/datos** — este documento es el entregable completo del item.
