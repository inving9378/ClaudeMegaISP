# Item #9990261 — Fase 2 (cablear DependenciaGate al despacho + visibilidad del bloqueo) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

**Fecha:** 2026-09-04 · **Worktree:** wt-1

## Qué pedía el item

#9990261 pedía cablear `DependenciaGate` al despacho del pool (`RoadmapItem::scopeDespachable()` /
`RoadmapCircuitoService::ejecutablesParalelo()`) detrás de un feature flag, más visibilidad del
bloqueo en `motivoNoDespachable()`, con una batería de regresión sobre cadenas `depende_de`. El
propio spec advertía que depende de que la Fase 1 (#9990260, sub-item del padre #9990228) esté
commiteada en `main`, y traía tres preguntas estructuradas (q1 cableado, q2 visibilidad, q3
reevaluación) ya resueltas con opciones recomendadas/reversibles.

## Lo que ya había pasado antes de esta vuelta

Una vuelta previa (**wt-4**, 2026-09-04 09:11) ya hizo lo correcto:

- Corrió `circuito:cabida` y obtuvo **NO CABE** (histórico ~774s).
- Verificó que la Fase 1 (#9990260) **no** estaba commiteada en `main` (seguía en
  `aprobado_irving`, sin `branch`/`merge_commit`) — confirmando la propia advertencia del spec.
- Descompuso el trabajo en 3 fases ejecutables con specs detallados (`origen_item_id=9990261`):
  - **#9990274** — Fase 2a: cablear `DependenciaGate` **dentro de** `scopeDespachable()` (no en
    `ejecutablesParalelo()`), para que los 3 consumidores (scheduler, `motivoNoDespachable()`,
    `DiagnosticoItemService`) queden correctos sin divergencia — la opción estructural que el
    propio spec señalaba como más alineada con el docblock del scope.
  - **#9990276** — Fase 2b: visibilidad del bloqueo, rama `bloqueado_por_dependencia` en
    `motivoNoDespachable()`.
  - **#9990277** — Fase 2c: test de regresión — cadena `depende_de` nunca despacha fuera de orden.

Pero esa vuelta **nunca intentó cerrar** al padre tras crear los 3 sub-items — el log solo
registra `claim_liberado_al_morir_la_vuelta` (wt-4, 09:12:05): la vuelta murió antes del intento de
cierre. El reaper devolvió #9990261 a `aprobado_revisor`, el carril mecánico
(`jarvis-mecanico`, 09:07:12 — previo a la muerte de wt-4, re-aprobación normal por señal
`footprint`) ya lo había vuelto a dejar disponible, y el pool lo repartió de nuevo (esta vez a
wt-1) sin que hubiera trabajo propio que hacer.

## Verificado en esta vuelta

- `circuito:cabida 9990261 --sid=wt-1` → `CABE [ya_descompuesto]` (exit 0): confirma que el item ya
  tiene sub-items y que el paso correcto es intentar el cierre, no re-descomponer
  (`JarvisService::caberEnVuelta()`, `yaFueDescompuesto()`).
- Los 3 hijos siguen intactos, sin reclamar, y con el estado esperado tras el triaje automático:

  | id | estado_aprobacion | worker_sid | branch | merge_commit |
  |----|--------------------|------------|--------|----------------|
  | #9990274 | pendiente_revision | — | — | — |
  | #9990276 | pendiente_revision | — | — | — |
  | #9990277 | pendiente_revision | — | — | — |

  La descomposición original (2a estructural / 2b visibilidad / 2c regresión) seguía siendo
  correcta — nadie más la tocó.

## Corrección aplicada

Esta vuelta ejecuta el intento de cierre que faltaba: al intentar
`estado_aprobacion='completado'` sobre #9990261, el guard **(2b) PARAGUAS**
(`app/Modules/Addons/Roadmap/Models/RoadmapItem.php`, ~301-340) lo reenruta a `aprobado_irving` +
`excluir_pool_automatico=true` (evento `paraguas_abierto` en el log: "le quedan 3 sub-item(s)
abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`/`497-526`) lo complete solo cuando #9990274, #9990276 y #9990277 cierren
los tres.

**Sin cambio de código de negocio** — el trabajo técnico real (cablear el gate dentro del scope,
la visibilidad del bloqueo, y el test de regresión de la cadena `depende_de`) sigue en esos tres
sub-items, pendientes de que una terminal los reclame (y de que la Fase 1, #9990260, quede
commiteada en `main` primero — precondición que ya señalaba el spec original).
