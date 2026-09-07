# Item #9990434 — MR-20 frontend (semáforo/tooltip/filtro) — ya implementado por el propio #956

**Fecha:** 2026-09-06
**Resultado:** RESUELTO sin cambio de código — el trabajo pedido ya está en `main`.

## Contexto

#9990434 se creó como sub-item de seguimiento de #956 (MR-20), pidiendo cablear al mapa Leaflet
el semáforo de color D16, el tooltip `usados/totales (%)` y el filtro cliente-side "solo NAPs con
puertos libres", condicionado a que el backend hermano (#9990432) ya expusiera la ocupación de
puertos.

Al llegar a este item, **el propio #956 ya había hecho exactamente ese trabajo**, en su propia
rama, con el mismo backend (`MapaRedNapOcupacionService`, commit `701b3d12`) y el frontend
(commit `0b033f1b`, "MR-20 (#956): frontend — color por ocupación, filtro y tooltip en el mapa"),
mergeado a `main` vía `ea8f5e40` ("Integra circuito #956 ... a main"). Es la misma carrera de
timing documentada varias veces en `CLAUDE.md` (#733/#741/#753/#9990003/#9990353): el sub-item de
seguimiento nació antes de que el ítem padre terminara/mergeara su propio cierre.

## Verificación (esta vuelta)

- `git merge-base --is-ancestor 0b033f1b HEAD` → **0b033f1b IS ancestor of HEAD** (mi `main` ya
  lo trae).
- `resources/js/components/module/mapared/LeafletMapRed.vue` ya contiene
  `aplicarSemaforoOcupacion`, `toggleFiltroPuertosLibres`, `aplicarFiltroACapa`,
  `aplicarOcupacionNaps` y el botón `easyButton` "Mostrar solo NAPs con puertos libres".
- `resources/js/components/module/mapared/helper/naps-request.js` expone `getOcupacionLote()`
  (llamada en lote tras cada `drawLayers`, sin fetch por marcador).
- Backend `MapaRedNapOcupacionService::semaforo()` implementa la escala D16 **exacta** pedida por
  el item: gris ≤50%, amarillo 51–70%, naranja 71–99%, rojo ≥100% (además gris si `totalPuertos===0`,
  caso "capacidad desconocida").
- Tooltip: `${text_node_base} · ${puertos_usados}/${puertos_totales} puertos (${porcentaje}%)` —
  cumple el DoD ("un solo dato, sin popup extendido"); difiere solo en la palabra "puertos" extra
  respecto al formato literal del spec, sin efecto funcional.
- Filtro: cliente-side, `puertos_usados < puertos_totales` (equivalente a `porcentaje < 100`), por
  opacidad (no quita el layer del cluster group, para no romper los lookups por key) — cumple la
  decisión ya tomada (cliente-side, sin round-trip).

## Diferencia de implementación vs. el spec original (sin impacto)

El spec de #9990434 asumía que el endpoint principal del mapa devolvería
`properties.puertos_usados/puertos_totales/porcentaje_ocupacion` inline por NAP. La implementación
real (#956) usa un endpoint de **lote separado** (`/mapa-red/api/naps/ocupacion-lote`), llamado una
vez por tanda de `drawLayers` — logra el mismo resultado (menos llamadas HTTP que una por
marcador) sin necesitar tocar el endpoint principal del mapa.

## Conclusión

Nada que implementar. Item cerrado documentando la verificación, sin cambio de código.
