# Item #907 — cierre del bucle reap sobre paraguas ya descompuesto (Torre 24/7 Pieza 5a — slots_libres como disparador)

## Contexto

`#907` (sub-item de seguimiento de `#904`) pedía tratar `slots_libres` como disparador de primera
clase en `AuditorService::debeCorrer()`, con 3 fases: (a) cambiar la condición de disparo para que
`slots_libres>0` cuente aunque la cola no baje del umbral fijo, (b) un nuevo parámetro configurable
en Torre → Configuración con el mismo patrón que `auditor_activo`/`auditor_cooldown_min`, y (c)
pintar la ocupación "N de 6 terminales trabajando" como métrica de primera línea en la Torre. Una
vuelta previa (`wt-2`, 2026-09-03 12:10) ya hizo el trabajo correcto:

1. Corrió `circuito:cabida` → **NO CABE**.
2. Descompuso el trabajo por fase, cada una con su propio detalle de implementación:
   - **#980** — Pieza 5a-i: condición de disparo (`slots_libres` cuenta aunque la cola no baje del
     umbral). `requiere_irving`.
   - **#981** — Pieza 5a-ii: nuevo parámetro configurable en Torre → Configuración (slots_libres
     mínimo para disparo). `aprobado_revisor`.
   - **#982** — Pieza 5a-iii: pintar "N de 6 terminales trabajando" como métrica de primera línea en
     la Torre. `aprobado_revisor`.

Esa parte fue correcta y **no se repite**. Lo que faltó: esa vuelta nunca intentó **cerrar** `#907`
después de crear los sub-items. El ítem se quedó en `aprobado_revisor`/`en_progreso` sin que nadie
liberara el claim. El reaper de huérfanos (`reaper-rapido`) lo vio con el slot libre dos veces
(`wt-2` a las 15:34, `wt-1` a las 15:44) y lo re-encoló a `aprobado_revisor` (`reap_count=2`), y el
pool lo repartió de nuevo (a esta terminal, `wt-1`) sin que hubiera trabajo propio que hacer — mismo
síntoma exacto que `#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#905`/`#878`/`#906`: un paraguas
correctamente descompuesto que nunca recibió el intento de cierre que activa el guard de "no
completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- Query directa: `#980`, `#981`, `#982` (`origen_item_id=907`) siguen intactos, sin `worker_sid` ni
  `claimed_at` — ninguno fue tocado por nadie más, la descomposición original seguía siendo la
  correcta.
- Intento de cierre: `RoadmapItem::find(907)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (2b, `RoadmapItem.php` ~301-326) lo reenrutó automáticamente a `aprobado_irving` +
  `excluir_pool_automatico=true`, agregando al log el evento `paraguas_abierto` ("le quedan 3
  sub-item(s) abierto(s): no se completa. Queda como paraguas y cierra solo cuando el último de
  ellos cierre"). Confirmado leyendo `$item->log` tras el save (evento `paraguas_abierto` seguido
  del evento `flags` que audita el cambio de `excluir_pool_automatico`).

## Resultado

`#907` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
`#980`, `#981` y `#982` cierren — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya
existente y verificado en las sesiones anteriores de este mismo bug) completa `#907` solo, sin
intervención manual.

**Sin cambio de código de negocio.** El trabajo técnico real (condición de disparo por
`slots_libres`, el nuevo toggle configurable, y la métrica de ocupación en la Torre) sigue en
`#980` (pendiente de que Irving lo apruebe — `requiere_irving`) y `#981`/`#982` (`aprobado_revisor`,
listos para que otra terminal los tome).
