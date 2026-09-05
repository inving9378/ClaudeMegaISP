# MR-06 — Checklist de paridad funcional 1:1, MAPA DE RED (item #942)

Fase 1 de #942 (no cabía completo en una vuelta — ver decisión en el log del item). Este
documento es el **checklist punto por punto** que pide el DoD del item, construido sin tocar
código ni esquema, reutilizando los audits READ-ONLY ya hechos por MR-01a/b/c (#1000000,
#1000001, #1000002). El port real (backend + frontend) queda descompuesto en sub-items
(MR-06a/MR-06b) porque depende de que **MR-04 (#940, esquema `mapared_*`)** y **MR-05 (#941,
copia de datos)** existan en `main` — ninguno de los dos ha mergeado todavía (verificado
2026-09-04: #940 `en_progreso` por `wt-3`, #941 `aprobado_revisor` sin reclamar, cero migraciones
`mapared_*` en `database/migrations/`).

---

## 0. Delimitación de alcance — qué es "el módulo viejo" que hay que igualar

`app/Modules/Addons/Mapas/routes.php` agrupa DOS sub-dominios (ver comentario propio del
archivo, confirmado con evidencia en MR-01c / `docs/mapas-rutas-permisos-item-1000002-verificacion.md`):

| Grupo | Prefijo | Rutas | Estado real (MR-01c) |
|---|---|---|---|
| **Geo** | `maps/*` | 64 | **VIVO** — es el motor real de la pantalla `/mapas` actual (`<leaflet-map/>` = `LeafletMap.vue`) |
| **Mapas/infra FTTH** | `mapas/*` | 131 | **MUERTO en bloque** — 0 callers en frontend, reemplazado por Geo, nunca retirado |

**Decisión de alcance (aplicando MINIMALISMO del ADN del circuito):** la paridad 1:1 de "la
pantalla actual" solo cubre el grupo **Geo**. El grupo Mapas/infra no se porta — portar código
muerto sería trabajo sin efecto observable y contradice la instrucción del propio item
("mantener... aunque los vayamos a reemplazar: primero paridad" — paridad es igualar lo que
FUNCIONA hoy, no resucitar 131 rutas sin un solo consumidor). Si en el futuro se decide rescatar
el "inventario físico formal" (Box/Pole/Site/Splitter/Rack/...), es una funcionalidad NUEVA, no
paridad — fuera de alcance de MR-06.

Esto también fija las tablas que MR-04/MR-05 deben cubrir con prioridad (ya lo hicieron según su
propio prompt, que cita "la estructura auditada en MR-01" — las 8 tablas vivas de MR-01a):
`map_devices`, `map_devices_ports`, `map_devices_ports_connections`, `map_fibers`,
`map_fibers_cut`, `map_layers`, `map_layers_routes`, `map_proyects` (91% del peso en disco del
módulo, 100% de la data geográfica real vía `map_layers.coords`).

---

## 1. Backend — checklist de los 6 controllers Geo (grupo vivo)

Fuente: métodos reales (`grep "public function"`) + cruce vivo/muerto de MR-01c.

### ConnectionsController (`/maps/connections*`, `/maps/zones`)
- [ ] `store` — crear conexión
- [ ] `update` — actualizar conexión
- [ ] `destroy` — eliminar conexión
- [ ] `connectionsMultiple` — conexión múltiple
- [ ] `cutConnections` — cortar conexiones
- [ ] `updateCut($layerId,$fiberId,$input,$state)` — helper interno (verificar si tiene ruta propia o solo se usa internamente)
- [ ] `zones()` — `/maps/zones`

### DevicesController (`/maps/devices*`)
- [ ] `store` / `update` / `destroy`
- [ ] `savePort` — `/maps/devices/save-port/{id}`
- [ ] `addPorts` — `/maps/devices/add-ports/{id}`
- [ ] `changeCardOLTDirection` — `/maps/devices/change-card-olt-direction/{id}`

### KMZController (`/maps/kmz`) — ✅ portado a MapaRed (item #9990335, `/mapa-red/api/kmz`)
- [x] `loadKMZ` (parseo KML/KMZ → `parseKmlToJson`/`saveKMZ`/`saveNode`/`saveLayersFromNode`/`normalizeNode`/`normalizeLayer` son privados internos del parser, no rutas propias)
- [x] `getKML($path)` — confirmado interno (llamado solo desde `loadKMZ`, sin ruta propia)

### LayersController (`/maps/layers*`, el más grande — 21 métodos) — ✅ portado (MR-06a-4, item #9990336)
- [x] `index` · `store` · `update` · `destroy` · `destroyMultiple`
- [x] `configuration` · `coords` · `changeClassification`
- [x] `addClientToServiceBox` · `moveMarker`
- [x] `convertLayersFromProject` · `convertLayerFromLayer` · `convertLayersFromTickeds`
- [x] `avaiablesRoutes` · `assignRoutes` · `unassignRoute` · `changeRoutePosition`
- [x] `createInput` · `updateInput` · `updateMarkersDistanceFromRoute`
- [x] `updateConnections($layer,$route)` — helper interno sin ruta propia, igual que el original;
  portado como método público invocado desde `assignRoutes`
- ⚠️ **`devicesFromRack`** (ruta `GET /maps/layers/devices/{id}`) — **el método NO EXISTE en el
  controller** (confirmado MR-01c). Ruta rota y sin caller. **NO portar** — no hay comportamiento
  que igualar (portar un 500 no es paridad útil).

### ProyectsController (`/maps/projects*`, `/maps/get-clients`, `/maps/clients-without-project`) — ✅ portado (MR-06a-5, item #9990337)
- [x] `index` · `store` · `update` · `destroy`
- [x] `clients` — `/maps/get-clients`
- [x] `clientsWithoutProject` — `/maps/clients-without-project`
- [x] `moveFolder`

### ServiceBoxController (`/maps/service-box/*`) — ✅ portado (MR-06a-5, item #9990337)
- [x] `getSelectedClients` · `getAvaiablesClients` · `removeClients` · `removeClient` · `addClients` · `removeClientFromDrop`
- ⚠️ **`savePort`** (ruta `POST /maps/service-box/save-port/{id}`) — método existe pero **sin
  ningún caller** en frontend (MR-01c). **NO portar como acción activa** — documentar que existe
  en el viejo sin uso; si Irving confirma que debe usarse, es un hallazgo aparte, no bloquea MR-06.

**Total a portar: ~60 de las 64 rutas Geo** (se excluyen las 2 marcadas ⚠️ arriba, ambas ya
confirmadas muertas/rotas en el módulo viejo — portarlas sería inventar comportamiento que hoy no
existe, lo opuesto a "paridad").

---

## 2. Frontend — árbol de componentes vivos (punto de partida)

`LeafletMap.vue` (1,753 líneas) es el único punto de montaje (`<leaflet-map/>` en
`resources/views/meganet/module/mapas/index.blade.php`). Importa DIRECTAMENTE (nivel 1):

```
ProjectsComponent · KMZComponent · RegionComponent · RouteComponent · ClientComponent ·
BuildingComponent · BoxJunctionComponent · BoxServiceComponent · CupboardComponent ·
NoteComponent · PackComponent · PoleComponent · SourceComponent · ClientToProjectComponent ·
FolderComponent · ObjectsInSerieComponent · DialogComponent ·
configuration/{ServiceBoxConfiguration,SiteConfiguration,RackConfiguration,JunctionBoxConfiguration} ·
SiteComponent
```

### Trazado nivel 2+ (MR-06b, 2026-09-04) — completo

Grafo de imports `from "...vue"` resuelto por BFS desde `LeafletMap.vue` sobre los 54 `.vue` del
módulo (`resources/js/components/module/maps/`, incluye `ApiKey.vue` suelto en la raíz). Resultado:
**52 de 54 alcanzables → VIVOS**; solo **2 inalcanzables**:

| Archivo | Estado | Motivo |
|---|---|---|
| `ApiKey.vue` | Vivo, pero **fuera del árbol de `LeafletMap.vue`** | No lo importa ningún `.vue`; se registra global en `app.js:246` como `ApiKeyConfig` y se monta por su propio tag Blade, no por el mapa. Fuera de alcance de MR-06b (no es parte de "lo que `LeafletMap.vue` porta"), no requiere acción aquí. |
| `components/configuration/RackComponent.vue` | **MUERTO** (⚠️ corrige un hallazgo previo, ver abajo) | Cero importadores en todo `resources/js/` (ni `.vue` ni `app.js`) y `grep -rn "RackComponent"` en `resources/`+`app/` no devuelve nada fuera de su propia definición (`name: "RackComponent"`, línea 86). No confundir con `RackConfiguration.vue` (otro archivo, sí vivo, importado directo por `LeafletMap.vue`) — nombres casi idénticos. |

**Todo `components/devices/*` (16 archivos) y `components/others/*` (10 archivos) están VIVOS**
— alcanzables transitivamente desde `LeafletMap.vue` vía las 4 `configuration/*Configuration.vue`
(`ServiceBoxConfiguration`, `SiteConfiguration`, `RackConfiguration`, `JunctionBoxConfiguration`).
`AwesomeMarkerIcon.vue`, `PortNoteComponent.vue` y `ClientToServiceBoxComponent.vue` — los 3
también VIVOS (el primero importado por 11 componentes distintos; `PortNoteComponent` por
`devices/SplitterComponent.vue` y `devices/ClientComponent.vue`; `ClientToServiceBoxComponent`
por `JunctionBoxConfiguration.vue` y `ServiceBoxConfiguration.vue`).

**⚠️ Corrección a un hallazgo previo (MR-01c, `docs/mapas-rutas-permisos-item-1000002-verificacion.md`
punto 3):** ese audit clasificó el bug de `saveRack`→`/maps/sites/racks` como "vivo: `RackComponent.vue`
importado y usado". Verificado ahora con grafo completo + grep directo: **es código muerto**, no
vivo — probable confusión de nombre con `RackConfiguration.vue` (sí vivo) en el audit original. Como
consecuencia, `helper/site-request.js` completo (`saveRack`+`destroyRack`, únicos exports, único
importador es el propio `RackComponent.vue`) también es 100% muerto — no solo la ruta backend, todo
el par frontend+backend. **Efecto en el alcance de MR-06b:** ni `RackComponent.vue` ni
`site-request.js` se portan (no hay comportamiento vivo que igualar); el "bug real vivo" que el
checklist original pedía documentar/replicar **no existe como tal** — era un bug en código ya muerto.

**Helpers (`helper/*.js`) — vivo/muerto verificado por grep de importadores:**
- `connections-request.js`, `devices-request.js`, `layers-request.js`, `mapUtils.js`, `request.js`
  (salvo 3 funciones puntuales, ver abajo) → **VIVOS**, múltiples importadores reales.
- `olt-request.js`, `organizers-request.js`, `switch-request.js` → **0 importadores** en ningún
  `.vue` — muertos (ya documentado, confirmado de nuevo).
- `site-request.js` → **muerto** (corrección de arriba: su único importador, `RackComponent.vue`,
  también está muerto).
- `request.js::saveSplitter`/`destroySplitter`/`splittersFromBox` → 0 llamadas en ningún `.vue`
  (`grep` sin resultados) — muertas dentro de un archivo por lo demás vivo; no portar solo esas 3.

**Conclusión para el port de MR-06b:** el conjunto a portar es `LeafletMap.vue` + los 51 `.vue`
vivos bajo `components/` (excluyendo `RackComponent.vue`) + los 6 helpers vivos (excluyendo
`olt-request.js`, `organizers-request.js`, `switch-request.js`, `site-request.js`, y las 3
funciones muertas de `request.js`). `ApiKey.vue` queda fuera por no ser parte del árbol del mapa.

---

## 3. Plan de sub-items (secuencia obligatoria por la dependencia de esquema)

1. **MR-06a** — Backend: portar los 6 controllers Geo (sección 1, ~60 acciones) al namespace
   `App\Modules\Addons\MapaRed\Controllers`, rutas nuevas bajo `/mapa-red/api/*` (o el prefijo que
   el propio MR-03 ya estableció), apuntando a `mapared_*`. **Bloqueado hasta que #940 (MR-04)
   tenga `merge_commit` en `main`** — sin las tablas, ni siquiera se puede migrar/bootear una
   query real.
2. **MR-06b** — Frontend: trazar el árbol vivo completo (sección 2, pendiente nivel 2+), portar
   `LeafletMap.vue` + componentes/helpers vivos a un namespace propio de MapaRed, repuntados a
   las rutas de MR-06a. Cierra con el DoD original: checklist marcado 1:1 + screenshot lado a
   lado del troncal de Tultitlán. Depende de MR-06a (necesita las rutas nuevas para repuntar).

Ambos sub-items deben citar este documento en su `prompt` para que quien los tome no repita el
inventario. Los checkboxes de la sección 1 son el criterio de aceptación literal del DoD
("checklist punto por punto").
