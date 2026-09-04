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

### KMZController (`/maps/kmz`)
- [ ] `loadKMZ` (parseo KML/KMZ → `parseKmlToJson`/`saveKMZ`/`saveNode`/`saveLayersFromNode`/`normalizeNode`/`normalizeLayer` son privados internos del parser, no rutas propias)
- [ ] `getKML($path)` — verificar si tiene ruta o es interno

### LayersController (`/maps/layers*`, el más grande — 21 métodos)
- [ ] `index` · `store` · `update` · `destroy` · `destroyMultiple`
- [ ] `configuration` · `coords` · `changeClassification`
- [ ] `addClientToServiceBox` · `moveMarker`
- [ ] `convertLayersFromProject` · `convertLayerFromLayer` · `convertLayersFromTickeds`
- [ ] `avaiablesRoutes` · `assignRoutes` · `unassignRoute` · `changeRoutePosition`
- [ ] `createInput` · `updateInput` · `updateMarkersDistanceFromRoute`
- [ ] `updateConnections($layer,$route)` — verificar si expuesto por ruta
- ⚠️ **`devicesFromRack`** (ruta `GET /maps/layers/devices/{id}`) — **el método NO EXISTE en el
  controller** (confirmado MR-01c). Ruta rota y sin caller. **NO portar** — no hay comportamiento
  que igualar (portar un 500 no es paridad útil).

### ProyectsController (`/maps/projects*`, `/maps/get-clients`, `/maps/clients-without-project`)
- [ ] `index` · `store` · `update` · `destroy`
- [ ] `clients` — `/maps/get-clients`
- [ ] `clientsWithoutProject` — `/maps/clients-without-project`
- [ ] `moveFolder`

### ServiceBoxController (`/maps/service-box/*`)
- [ ] `getSelectedClients` · `getAvaiablesClients` · `removeClients` · `removeClient` · `addClients` · `removeClientFromDrop`
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

**Pendiente (para MR-06b, no resuelto aquí):** trazar el árbol nivel 2+ dentro de esos 22
componentes para separar vivo/muerto en el resto del directorio (`components/devices/*` —
Switch/Organizer/Olt/Router/Splitter/RouteComponent/Form*/DropOut/BufferRoute —,
`components/others/*`, `AwesomeMarkerIcon`, `PortNoteComponent`, `ClientToServiceBoxComponent`).

**Ya confirmado muerto/roto por MR-01c (no portar tal cual, documentar la excepción):**
- `helper/site-request.js::saveRack` → `POST /maps/sites/racks` — **ruta inexistente hoy**
  (bug real, vivo: `RackComponent.vue` sí lo llama). Paridad = portar el mismo bug (el botón
  falla igual) **o** decidir corregirlo — es una mejora, no paridad; si se corrige debe ser un
  item aparte, no colarse en MR-06 ("todavía sin mejoras").
- `helper/olt-request.js`, `helper/organizers-request.js`, `helper/switch-request.js`, y
  `saveSplitter`/`destroySplitter`/`splittersFromBox` de `helper/request.js` → apuntan a
  `/maps/olts`, `/maps/organizers`, `/maps/switchs`, `/maps/splitters*`, ninguna existe como
  ruta. **Sin importadores `.vue`** (código muerto en ambos lados) — no portar.

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
