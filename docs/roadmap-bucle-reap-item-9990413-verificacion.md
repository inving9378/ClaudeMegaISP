# Item #9990413 — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

## Contexto

Mismo patrón ya documentado repetidamente en `CLAUDE.md` (familia #738/#745/#830/#816/#818/#848/
#852/#905/#878/#906/#907/#924/#9990012/#917/#910/#936): un item se reclama, se descompone
correctamente en sub-items, pero el proceso muere/timeoutea **antes de intentar el cierre** del
padre → el guard de paraguas (`RoadmapItem.php` bloque "(2b) PARAGUAS") nunca llega a ejecutarse
→ el item queda `en_progreso` colgado → el reaper lo re-encola a `aprobado_revisor` → el pool lo
reparte de nuevo sin que haya trabajo propio que hacer.

## Lo que encontró esta vuelta

#9990413 ("FASE 3: visibilidad en la Torre del estado «cuenta sin límite»") ya había sido:

1. Triado (nivel B, sin frontera dura) y re-aprobado por el "des-trabe (Opus)".
2. Reclamado por `wt-3` (2026-09-06 16:34).
3. Correctamente evaluado con `circuito:cabida` → NO CABE (histórico ~482s) + bloqueado por
   dependencia real: #9990411/#9990412 (de los que depende como fuente de datos) seguían
   `en_progreso` sin `merge_commit`.
4. Descompuesto en **#9990419** (backend: exponer causa/expira_en del freno en `pausedInfo()`)
   y **#9990420** (frontend: banner distintivo en `TorreControl.vue`, depende de #9990419) —
   decisión registrada en `comentarios_claude` a las 16:42.

Pero esa vuelta (`wt-3`) nunca ejecutó el intento de cierre (`estado_aprobacion='completado'`)
que dispara el guard de paraguas. El item se quedó `en_progreso`; 25 minutos después el reaper
lo detectó huérfano y lo re-encoló a `aprobado_revisor` (`reap_count=1`, log
`huerfano_reencolado`). El pool lo repartió de nuevo (a `wt-1`, esta vuelta) sin que hubiera
trabajo propio pendiente — el trabajo real ya estaba correctamente delegado a #9990419/#9990420.

## Verificación

- #9990419: existe, `estado_aprobacion=aprobado_revisor`, `origen_item_id=9990413`, sin
  reclamar (`worker_sid` vacío).
- #9990420: existe, `estado_aprobacion=aprobado_revisor`, `origen_item_id=9990413`, sin
  reclamar (`worker_sid` vacío).

Ambos intactos — la descomposición original de `wt-3` seguía siendo correcta, nadie más los tocó.

## Corrección aplicada

Se ejecutó el intento de cierre faltante (`estado_aprobacion='completado'` vía tinker). El guard
del modelo (`RoadmapItem.php` bloque "(2b) PARAGUAS", ~333-360) detectó los 2 sub-items abiertos
y lo reenrutó automáticamente a:

- `estado_aprobacion = aprobado_irving`
- `excluir_pool_automatico = true`
- `worker_sid = null`, `claimed_at = null` (libera el reclamo)
- log: evento `paraguas_abierto`, "le quedan 2 sub-item(s) abierto(s)"

Con esto #9990413 sale del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:558-...`, "cuando un SUB-ITEM cierra, se revisa si era el último") lo complete
solo cuando #9990419 y #9990420 cierren ambos.

## Conclusión

**Sin cambio de código de negocio.** El trabajo técnico real (exponer causa/expira_en del freno
en la Torre + el banner distintivo en `TorreControl.vue`) sigue en #9990419/#9990420, ambos
`aprobado_revisor`, listos para que una terminal los reclame — bloqueados a su vez por que
#9990411/#9990412 mergeen a `main` (dependencia real declarada por la propia descomposición de
`wt-3`).
