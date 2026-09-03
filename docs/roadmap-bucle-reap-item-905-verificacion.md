# Item #905 — cierre del bucle reap sobre paraguas ya descompuesto (Válvula: sellar frontera_valvula)

## Contexto

`#905` ("Válvula: sellar frontera_valvula en el mismo acto que el log + backfill de 112 items +
test de regresión — Defecto 1 de #902") es el ítem que pedía implementar el sellado de
`frontera_valvula` en `RevisorService::aplicarTriajeNull()`, el backfill de los 112 items
existentes y un test de regresión. Una vuelta previa (`wt-2`, 2026-09-03 15:18) ya hizo el trabajo
correcto:

1. Corrió `circuito:cabida` → NO CABE (`historico_excede_umbral`).
2. Descompuso el trabajo siguiendo las fases ya delineadas en el propio spec del item:
   - **#975** — Fase 2 (sellar `frontera_valvula` en `RevisorService::aplicarTriajeNull()`).
   - **#976** — Fase 3 (backfill de los 112 items, depende de #975).
   - **#977** — Fase 5 (test de regresión, depende de #975).
3. Dejó `#905` como paraguas.

Esa parte fue correcta y **no se repite**. Lo que faltó: esa vuelta nunca intentó **cerrar**
`#905` después de crear los sub-items. El ítem se quedó `en_progreso` sin que nadie liberara el
claim. El reaper de huérfanos (`reaper-rapido`) lo vio con el slot `wt-2` libre, lo re-encoló a
`aprobado_revisor` (`reap_count=1`, log `huerfano_reencolado` 2026-09-03 15:22:02), y el pool lo
repartió de nuevo (a esta terminal, `wt-1`) sin que hubiera trabajo propio que hacer — mismo
síntoma exacto que `#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#878`: un paraguas correctamente
descompuesto que nunca recibió el intento de cierre que activa el guard de "no completar mientras
queden hijos abiertos".

## Verificación de esta vuelta

- Query directa: los 3 hijos (`origen_item_id=905`) siguen abiertos e intactos, sin `worker_sid`
  (nadie los reclamó): `#975` en `requiere_irving`, `#976` y `#977` en `pendiente_revision`. La
  descomposición original seguía siendo la correcta; nadie más la tocó.
- Intento de cierre: `RoadmapItem::find(905)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (2b, `RoadmapItem.php` ~301-326) lo reenrutó automáticamente a `aprobado_irving` +
  `excluir_pool_automatico=true`, agregando al log el evento `paraguas_abierto` ("le quedan 3
  sub-item(s) abierto(s): no se completa"). Confirmado leyendo `$item->log` tras el save (evento
  `paraguas_abierto` con `subitems_abiertos: 3`, seguido del evento `flags` que audita el cambio de
  `excluir_pool_automatico` a `true`). `worker_sid` quedó liberado (`null`).

## Resultado

`#905` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
`#975`, `#976` y `#977` cierren — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya
existente y verificado en las sesiones anteriores de este mismo bug) completa `#905` solo, sin
intervención manual.

**Sin cambio de código de negocio.** El trabajo técnico real (sellar `frontera_valvula` en
`RevisorService::aplicarTriajeNull()`, el backfill de los 112 items y el test de regresión) sigue
en `#975`/`#976`/`#977`. `#975` ya está `requiere_irving` (pendiente de que Irving lo apruebe);
`#976` y `#977` siguen `pendiente_revision` (y `#976` depende explícitamente de que `#975` esté
commiteado y verificado antes de correr el backfill).
