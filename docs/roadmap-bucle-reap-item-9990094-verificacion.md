# Item #9990094 — cierre del bucle reap sobre paraguas ya descompuesto (Fase 2b, repro concurrente del cascade sobre #32)

## Contexto

`#9990094` ("Fase 2b - repro con 2 procesos PHP concurrentes del cascade sobre #32") es
continuación de `#9990088` pasos 2-4, condicionada a que `#9990093` (Fase 2a) descartara primero
la hipótesis de escritura cruda a `roadmap_items.estado_aprobacion` fuera de Eloquent. `#9990093`
ya cerró **completado** confirmando esa hipótesis descartada (las 5 escrituras de
`estado_aprobacion='completado'` en `app/` pasan todas por asignación Eloquent + `save()`; el
único `DB::table()` crudo que toca esa columna solo escribe `'en_progreso'`, nunca `'completado'`).

Una vuelta previa (`wt-3`, 2026-09-03 22:15) ya hizo el trabajo correcto: repitió el mismo grep de
`#9990093` de forma independiente (confirmando la misma conclusión sin esperar a que ese item
terminara — ambas son lecturas puras, sin conflicto), y en consecuencia descompuso el repro
ejecutable de concurrencia real en dos sub-items:

- **#9990109** — "Fase 2b-i - construir harness de repro concurrente (2 procesos PHP reales) +
  fixture + instrumentación temporal de guard(1)".
- **#9990110** — "Fase 2b-ii - ejecutar el harness concurrente de #9990109, analizar resultados y
  documentar (o cerrar inconclusa)".

Esa parte fue correcta y **no se repite**. Lo que faltó: esa vuelta nunca intentó **cerrar**
`#9990094` tras crear los sub-items. El log del item solo registra el evento
`huerfano_reencolado` del `reaper-rapido` (2026-09-03 22:18:01, `reap_count=1`, "el slot wt-3 está
libre... reclamo huérfano") — el reclamo se devolvió a `aprobado_revisor` sin que nadie hubiera
pasado por el guard de paraguas, y el pool lo repartió de nuevo (a `wt-2`, esta vuelta) sin que
hubiera trabajo propio que hacer — mismo síntoma exacto que
`#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#905`/`#878`/`#906`/`#907`/`#924`/`#9990012`: un
paraguas correctamente descompuesto que nunca recibió el intento de cierre que activa el guard de
"no completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- Query directa: `#9990109` y `#9990110` (`origen_item_id=9990094`) siguen intactos —
  `estado_aprobacion = aprobado_revisor`, `worker_sid = null` en ambos — nadie más los tocó, la
  descomposición original seguía siendo la correcta.
- `#9990093` (dependencia) confirmado `completado`.
- Intento de cierre: `RoadmapItem::find(9990094)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (bloque 2b PARAGUAS, `RoadmapItem.php` ~301-332) lo reenrutó automáticamente a
  `aprobado_irving` + `excluir_pool_automatico=true` (+ liberó `worker_sid`/`claimed_at`),
  agregando al log el evento `paraguas_abierto` ("le quedan 2 sub-item(s) abierto(s): no se
  completa. Queda como paraguas y cierra solo cuando el último de ellos cierre"). Confirmado
  leyendo `$item->log` tras el save (evento `paraguas_abierto` seguido del evento `flags` que
  audita el cambio de `excluir_pool_automatico`).

## Resultado

`#9990094` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta
que `#9990109` y `#9990110` cierren — en ese momento el hook `saved` (`RoadmapItem.php:459-491`,
ya verificado en las sesiones anteriores de este mismo bug) completa `#9990094` solo, sin
intervención manual.

**Sin cambio de código de negocio.** El trabajo técnico real (construir el harness de repro
concurrente y ejecutarlo/analizarlo) sigue en `#9990109` y `#9990110` (`aprobado_revisor`,
pendientes de que una terminal los reclame).
