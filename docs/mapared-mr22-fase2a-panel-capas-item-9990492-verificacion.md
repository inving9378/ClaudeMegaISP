# Item #9990492 — MR-22 Fase 2a: panel de capas encendibles OLT/Troncales/Mufas/NAPs/Postes (RESUELTO — ya implementado por el padre)

## Contexto

`#9990492` es un sub-item de seguimiento de `#9990458` ("MR-22 Fase 2 — panel de capas
encendibles + render dependiente de zoom"), creado el 2026-09-07 08:58 (`wt-5`, como
descomposición temprana de la fase 2 en tres piezas: **2a** este item, **2b** `#9990493`
(capa Clientes), **2c** `#9990494` (Drops/Cobertura)).

Entre ese momento y el reclamo de este item, otra vuelta (`wt-5`, 09:26–09:30) retomó
directamente el item padre `#9990458` y lo implementó **completo** — no solo la porción 2a,
sino las 8 capas del panel (`olt, troncales, mufas, naps, drops, clientes, postes,
cobertura`) — y lo mergeó a `main` (commit `39cc8399`, integrado en `da0185b4`, 2026-09-07
09:26–09:30). El log de `#9990458` documenta la verificación (`npm-build.sh` OK, mapeo
`dialog→capa` coherente, panel implementado como `L.Control` nativo).

Es la misma carrera de timing ya documentada varias veces en `CLAUDE.md` (#733/#741/#753/
#9990003/#9990353): el sub-item de seguimiento nace de un estado del padre que cambia antes
de que alguien llegue a trabajar el hijo.

## Verificación de este item específico (2a): OLT/Troncales/Mufas/NAPs/Postes

Revisado `resources/js/components/module/mapared/LeafletMapRed.vue` (estado actual en
`main`, sin cambios de esta vuelta):

- **Panel flotante arriba-derecha, colapsable** — `crearControlCapas()` (línea 561) crea un
  `L.Control.extend({options:{position:"topright"}})` con header (`capas-panel__header`,
  click para expandir/colapsar con chevron mdi) + body (`capas-panel__body`) con un
  checkbox por capa. Registrado en `initMap()` (línea 713) → llamado desde `onMounted`.
  CSS dedicado en líneas 2014-2054 (incluye variante `body--dark`).
- **Las 5 capas pedidas por este item están cubiertas** dentro de `CAPAS_MAPA_RED` (línea
  479): `olt→site`, `troncales→route`, `mufas→junction_box`, `naps→service_box`,
  `postes→pole` — exactamente el mapeo que este item ya traía investigado en su spec
  (`dialog` de cada layer, confirmado también contra `ImportadorRedService.php:31-42`).
- **Estado inicial correcto** (`capasEncendidas`, línea 497): `olt/troncales/mufas/naps =
  true`, `postes = false` — coincide con la decisión de Irving citada en el spec de este
  item (opción `c0ad1880fcd046c7`: OLT/Troncales/Mufas/NAPs=ON, Postes=OFF).
- **Toggle real de visibilidad** — `aplicarVisibilidadPorCapa()` (línea 519) recorre
  `drawnItems` (el único `L.markerClusterGroup` compartido, sin reconstruirlo — decisión q1
  ya citada en el spec) y, por cada layer, resuelve su capa vía `DIALOG_A_CAPA[dialog]` y
  aplica opacidad 0/1 según `capasEncendidas`. Es una implementación distinta a la que este
  item sugería (separar `drawnItems` en grupos `markerClusterGroup`/`layerGroup` por tipo y
  hacer `map.addLayer`/`removeLayer`) — el padre optó por **opacidad sobre el grupo único**
  en vez de partir el clustering, evitando el riesgo mayor (reconstruir el índice espacial
  de clusters) para un resultado equivalente (capa oculta = invisible en el mapa). El propio
  spec de este item dejaba abierta la forma de implementación ("Para hacerlos toggleables
  por tipo: separar... **o equivalente**" no está tan explícito, pero el resultado
  observable — checkbox oculta/muestra el tipo — es el que pedía la decisión de Irving).
  `watch(capasEncendidas, ...)` (línea 557) dispara `aplicarVisibilidadCapas()` en cada
  cambio de checkbox.
- **Troncales (route, polilínea)** no se agrupa en el `markerClusterGroup` de forma especial
  — el toggle por opacidad funciona igual para polylines (`layer.setStyle({opacity,
  fillOpacity})`, rama `typeof layer.setStyle === "function"` en línea 537-544), así que no
  hace falta el `L.layerGroup` aparte que sugería el spec original.
- **NAPs conviven con el filtro de puertos libres de MR-20** (`aplicarFiltroACapa`, MR-20 ya
  mergeado) sin pisarse: `aplicarVisibilidadPorCapa` delega en `aplicarFiltroACapa` para
  `service_box` (línea 521-525) en vez de duplicar la lógica de opacidad.

## Alcance NO tocado (correcto, por diseño de este item)

El propio spec de `#9990492` decía explícitamente "NO tocar drops/clientes/cobertura aquí".
Esas tres siguen siendo trabajo de los hermanos `#9990493` (2b — clientes, `aprobado_revisor`,
sin reclamar) y `#9990494` (2c — drops/cobertura, `aprobado_irving`, sin reclamar); ninguno se
tocó en esta verificación.

## Verificación de regresión

Sin cambios de código de aplicación en esta vuelta (solo este doc). El estado de
`LeafletMapRed.vue` es el mismo que verificó la vuelta que cerró `#9990458`
(`npm-build.sh` OK, según su propio log de cierre 2026-09-07 09:29).

## Conclusión

Sin cambio de código — el trabajo pedido por `#9990492` (panel de capas encendibles para
OLT/Troncales/Mufas/NAPs/Postes) ya estaba completo en `main` antes de que esta vuelta lo
reclamara.
