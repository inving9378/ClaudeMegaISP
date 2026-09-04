# Item #909 — cierre del bucle reap sobre paraguas ya descompuesto (Torre 24/7 Pieza 5c)

## Contexto

`#909` (sub-item de seguimiento de `#904`, FASE 4 explícita de Irving: verificación "todo sigue
funcionando" tras una vuelta que tocó código, antes de integrarla) tuvo una vuelta previa (`wt-3`,
2026-09-03 15:58) que ya hizo el trabajo correcto:

1. Corrió `circuito:cabida` → **NO CABE** (histórico ~40min).
2. Descompuso el trabajo en 3 sub-items:
   - **#988** — motor de detección (`php -l` + boot de la app + tests del módulo tocado + reusar
     `deploy:dry-run-migrations` tal cual para la pata de migraciones).
   - **#989** — capa de acción: revertir la rama propia de la vuelta (nunca `main`) o escalar vía
     `circuito:consultar` cuando la verificación falla.
   - **#990** — verificación end-to-end: confirmar que el comando detecta una falla inyectada a
     propósito y la revierte/reporta.

Esa parte fue correcta y **no se repite**. Lo que faltó: esa vuelta nunca intentó **cerrar** `#909`
después de crear los sub-items. El ítem se quedó `en_progreso` con el `worker_sid` de esa sesión, sin
que nadie liberara el claim. El reaper de huérfanos (`reaper-rapido`) lo vio con el slot `wt-3` libre,
lo re-encoló a `aprobado_revisor` (`reap_count=1`, log `huerfano_reencolado` 2026-09-03 16:02:03), y
el pool lo repartió de nuevo (a esta terminal, `wt-1`) sin que hubiera trabajo propio que hacer —
mismo síntoma exacto que `#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#905`/`#878`/`#906`/`#907`: un
paraguas correctamente descompuesto que nunca recibió el intento de cierre que activa el guard de
"no completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- Query directa: `#988` (`origen_item_id=909`) sigue `estado_aprobacion=aprobado_revisor`, sin
  `worker_sid`. `#989` y `#990` (`origen_item_id=909`) siguen `estado_aprobacion=requiere_irving`,
  sin `worker_sid`. Ninguno fue tocado por nadie más — la descomposición original seguía siendo la
  correcta.
- Intento de cierre: `RoadmapItem::find(909)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (2b, `RoadmapItem.php` ~301-326) lo reenrutó automáticamente a `aprobado_irving` +
  `excluir_pool_automatico=true`, agregando al log el evento `paraguas_abierto` ("le quedan 3
  sub-item(s) abierto(s): no se completa. Queda como paraguas y cierra solo cuando el último de
  ellos cierre"). Confirmado leyendo `$item->log` tras el save (evento `paraguas_abierto` seguido
  del evento `flags` que audita el cambio de `excluir_pool_automatico`, y el `worker_sid`/
  `claimed_at` liberados por el mismo bloque).

## Resultado

`#909` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
`#988`, `#989` y `#990` cierren — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya
existente y verificado en las sesiones anteriores de este mismo bug) completa `#909` solo, sin
intervención manual.

**Sin cambio de código de negocio.** El trabajo técnico real (comando `circuito:verificar-vuelta`:
motor de detección, capa de acción revert/escalada, verificación end-to-end con falla inyectada)
sigue en `#988` (`aprobado_revisor`, listo para que otra terminal lo tome) y `#989`/`#990`
(`requiere_irving`, pendientes de que Irving los apruebe).
