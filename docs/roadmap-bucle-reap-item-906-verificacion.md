# Item #906 — cierre del bucle reap sobre paraguas ya descompuesto (Defecto 2 de #902 — mensajes de escalada)

## Contexto

`#906` (sub-item de seguimiento de `#902`) pedía corregir los 4 mensajes de escalada que siempre
dicen "la política no permite este nivel" aunque la causa real sea una frontera dura y no un techo
de nivel. Una vuelta previa (`wt-2`, 2026-09-03 15:24) ya hizo el trabajo correcto:

1. Corrió `circuito:cabida` → **NO CABE** (histórico ~2399s).
2. Descompuso el trabajo por archivo, para que no se pisaran entre sí:
   - **#978** — `JarvisService.php` (los carriles "ya decidido" y "mecánico": `evaluarYaDecidido()`
     línea 725 y `aprobarMecanico()` línea 830).
   - **#979** — `RevisorService.php` (`aplicarVeredicto()` línea 224 y el carril des-trabador/Opus
     línea 1077).
   Cada sub-item con el detalle de `fronteraDuraDeItemDetalle()`/`nivelEfectivo()` a usar y su
   propia verificación.

Esa parte fue correcta y **no se repite**. Lo que faltó: esa vuelta nunca intentó **cerrar** `#906`
después de crear los sub-items. El ítem se quedó `en_progreso` con el `worker_sid` de esa sesión,
sin que nadie liberara el claim. El reaper de huérfanos (`reaper-rapido`) lo vio con el slot `wt-2`
libre, lo re-encoló a `aprobado_revisor` (`reap_count=1`, log `huerfano_reencolado`
2026-09-03 15:28:02), y el pool lo repartió de nuevo (a esta terminal, `wt-1`) sin que hubiera
trabajo propio que hacer — mismo síntoma exacto que `#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#905`/
`#878`: un paraguas correctamente descompuesto que nunca recibió el intento de cierre que activa el
guard de "no completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- Query directa: `#978` (`origen_item_id=906`) sigue `estado_aprobacion=requiere_irving`, sin
  `worker_sid`. `#979` (`origen_item_id=906`) sigue `estado_aprobacion=aprobado_revisor`, sin
  `worker_sid`. Ninguno fue tocado por nadie más — la descomposición original seguía siendo la
  correcta.
- Intento de cierre: `RoadmapItem::find(906)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (2b, `RoadmapItem.php` ~301-326) lo reenrutó automáticamente a `aprobado_irving` +
  `excluir_pool_automatico=true`, agregando al log el evento `paraguas_abierto` ("le quedan 2
  sub-item(s) abierto(s): no se completa. Queda como paraguas y cierra solo cuando el último de
  ellos cierre"). Confirmado leyendo `$item->log` tras el save (evento `paraguas_abierto` seguido
  del evento `flags` que audita el cambio de `excluir_pool_automatico`).

## Resultado

`#906` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
`#978` y `#979` cierren — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya existente y
verificado en las sesiones anteriores de este mismo bug) completa `#906` solo, sin intervención
manual.

**Sin cambio de código de negocio.** El trabajo técnico real (distinguir frontera dura vs. techo de
nivel en los 4 mensajes de escalada) sigue en `#978` (pendiente de que Irving lo apruebe —
`requiere_irving`) y `#979` (`aprobado_revisor`, listo para que otra terminal lo tome).
