# Item #877 — cierre del bucle reap sobre paraguas ya descompuesto (Torre 24/7 · Pieza 1 — desatascar el pool)

## Contexto

`#877` ("Torre 24/7 · Pieza 1 — desatascar el pool: 162 items parqueados con 6 terminales
ociosas") es el propio item cuyo prompt pide diagnosticar y desatascar el pool del circuito
(Fases 1-3: diagnóstico read-only, cascada de paraguas cerrados, liberar `worker_sid`
huérfanos, encolar merges verificados, limpiar flags huérfanos, y dejar el desatasco corriendo
solo). Una vuelta previa (`wt-3`, 2026-09-03 10:56) ya hizo el diagnóstico correcto:

1. Corrió (implícitamente, vía el propio análisis del prompt) la evaluación de cabida y
   concluyó **NO CABE** (histórico ~37881s).
2. Descompuso el trabajo real en 6 sub-items siguiendo exactamente las fases del prompt de
   `#877`:
   - **#885** — Fase 1: diagnóstico de por qué el pool está atascado.
   - **#886** — Fase 2a: cerrar en cascada los paraguas cuyos hijos ya cerraron.
   - **#887** — Fase 2b: soltar los `worker_sid` huérfanos sin proceso vivo.
   - **#888** — Fase 2c: encolar a `merge-run` los `esperando_merge_irving` con rama verificada.
   - **#889** — Fase 2d: limpiar flags huérfanos sin causa conocida.
   - **#890** — Fase 3: que el desatasco corra solo y se vea si se cae.
3. Dejó nota de decisión en el log del item (`decision`, texto truncado en el propio log de BD,
   pero los 6 sub-items ya estaban persistidos antes del corte).

Esa parte fue correcta y **no se repite**. Lo que faltó: esa vuelta nunca terminó el intento de
cerrar `#877`. El item se quedó `en_progreso` con el `worker_sid` de esa sesión, sin que nadie
liberara el claim. El reaper de huérfanos (`reaper-rapido`) lo vio con el slot `wt-3` libre, lo
re-encoló a `aprobado_revisor` (`reap_count=1`, log `huerfano_reencolado` 2026-09-03 11:02:02),
y el pool lo volvió a repartir (a esta vuelta, `wt-1`) sin que hubiera trabajo propio que hacer
— el trabajo real ya estaba correctamente delegado a `#885`-`#890` — mismo síntoma exacto que
`#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#202`: un paraguas correctamente descompuesto que
nunca recibió el intento de cierre que activa el guard de "no completar mientras queden hijos
abiertos". Nótese la irónica auto-referencia: `#877` es justo el item que pide arreglar la causa
raíz de este tipo de atascos en el pool, y él mismo cayó en el patrón más común de atasco
documentado en el repo.

## Verificación de esta vuelta

- Query directa: los 6 hijos (`origen_item_id=877`) — `#885`, `#886`, `#887`, `#888`, `#889`,
  `#890` — existen, ninguno reclamado (`worker_sid` vacío). `#885`/`#886`/`#887` en
  `aprobado_revisor`; `#888`/`#889`/`#890` en `requiere_irving`. Ninguno se tocó.
- Intento de cierre: `RoadmapItem::find(877)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (2b, `RoadmapItem.php` ~301-326) lo reenrutó automáticamente a
  `aprobado_irving` + `excluir_pool_automatico=true`, y agregó al log el evento `paraguas_abierto`
  ("le quedan 6 sub-item(s) abierto(s): no se completa"). Confirmado leyendo `$item->log` tras el
  save (evento `paraguas_abierto` seguido del evento `flags` que audita el cambio de
  `excluir_pool_automatico` de `false` a `true`).

## Resultado

`#877` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta
que sus 6 hijos cierren — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya existente
y verificado en las sesiones anteriores de este mismo bug) completa `#877` solo, sin intervención
manual.

**Sin cambio de código de negocio.** El trabajo técnico real del desatasco del pool (diagnóstico,
cascada de paraguas, liberar huérfanos, encolar merges verificados, limpiar flags, motor
recurrente) sigue en `#885`-`#890`, esperando triaje/aprobación/ejecución antes de que una
terminal los reclame.
