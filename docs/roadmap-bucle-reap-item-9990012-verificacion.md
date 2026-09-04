# Item #9990012 — cierre del bucle reap sobre paraguas ya descompuesto (carrera del cierre-en-cascada de #32)

## Contexto

`#9990012` (sub-item de seguimiento de `#924`) pedía reproducir en dev la carrera del
cierre-en-cascada de `#32` y confirmar el mecanismo exacto que esquivó el guard(1) de
`RoadmapItem.php` (nivel C + branch + `merge_commit` vacío → reroutea a `aprobado_irving`). Una
vuelta previa (`wt-2`, 2026-09-03 19:36-19:54) ya hizo el trabajo correcto:

1. Corrió `circuito:cabida` → **NO CABE** (`ya_timeouteo_antes`).
2. Hizo el forense estático completo: localizó guard(1)/guard(2b)/el hook de cierre en cascada en
   `RoadmapItem.php` y los 3 `save()` de `MergeRunner.php`, y confirmó en el log real de `#32` que
   el bug SÍ se escribió en BD (no es solo hipótesis).
3. Descompuso el repro ejecutable (transacción + rollback, instrumentación temporal con
   `Log::debug`, sin PHPUnit) en **#9990063**, con spec detallado que ya descarta la hipótesis de
   merge directo del padre.

Esa parte fue correcta y **no se repite**. Lo que faltó: el proceso de esa vuelta murió a media
escritura del comentario de decisión (el texto quedó cortado en "No cr…") antes de intentar
**cerrar** `#9990012`. El log del item registra `claim_liberado_al_morir_la_vuelta` a las 19:54:41
— el reclamo se liberó (vuelve a `aprobado_irving`) sin que nadie hubiera pasado por el guard de
paraguas, y el pool lo repartió de nuevo (a esta misma etiqueta `wt-2`, en otra vuelta) sin que
hubiera trabajo propio que hacer — mismo síntoma exacto que
`#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#905`/`#878`/`#906`/`#907`: un paraguas correctamente
descompuesto que nunca recibió el intento de cierre que activa el guard de "no completar mientras
queden hijos abiertos".

## Verificación de esta vuelta

- Query directa: `#9990063` (`origen_item_id=9990012`) sigue intacto — `estado_aprobacion =
  pendiente_revision`, `worker_sid = null` — nadie más lo tocó, la descomposición original seguía
  siendo la correcta.
- Intento de cierre: `RoadmapItem::find(9990012)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (bloque 2b PARAGUAS, `RoadmapItem.php` ~301-332) lo reenrutó automáticamente a
  `aprobado_irving` + `excluir_pool_automatico=true` (+ liberó `worker_sid`/`claimed_at`),
  agregando al log el evento `paraguas_abierto` ("le quedan 1 sub-item(s) abierto(s): no se
  completa. Queda como paraguas y cierra solo cuando el último de ellos cierre"). Confirmado
  leyendo `$item->log` tras el save (evento `paraguas_abierto` seguido del evento `flags` que
  audita el cambio de `excluir_pool_automatico`).

## Resultado

`#9990012` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta
que `#9990063` cierre (y, en cascada, cualquier sub-item que `#9990063` genere si su propio trabajo
tampoco cupiera en una vuelta) — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya
verificado en las sesiones anteriores de este mismo bug) completa `#9990012` solo, sin
intervención manual.

**Sin cambio de código de negocio.** El trabajo técnico real (reproducir la carrera con
transacción + rollback e instrumentar el guard para confirmar el mecanismo exacto) sigue en
`#9990063` (`pendiente_revision`, pendiente de que el revisor lo trie).
