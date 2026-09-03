# Item #986 — cierre del bucle reap sobre paraguas ya descompuesto (Torre 24/7 Pieza 5b — FASE 2b: ejecución del barrido)

## Contexto

`#986` (sub-item de seguimiento de `#908`) pedía la FASE 2b de la Pieza 5b: ejecutar el barrido
solo-lectura sobre el módulo elegido por la FASE 2a (`#985`, ya mergeada a `main`) y crear los
hallazgos vía `RoadmapIntakeService::crear()` directo. Una vuelta previa (`wt-1`, 2026-09-03
17:49) ya hizo el trabajo correcto:

1. Commiteó `4db3d298` (`feat(circuito#986): expone detectoresCrossCutting() + visibilidad
   pública de archivosPhp/relativo`) en la rama `circuito/item-986-torre-247-pieza-5b-fase-2b-ejecuci`,
   preparando `AuditorService` para que `BarridoService` reuse los detectores ya mergeados
   (`#899`/`#901`) sin reimplementarlos.
2. Corrió `circuito:cabida` → **NO CABE** (`ya_timeouteo_antes`, `veces_timeouteo=1`).
3. Descompuso el resto de FASE 2b por pieza, cada una con spec detallado citando líneas exactas
   de `AuditorService`/`BarridoService`/config:
   - **#9990032** — FASE 2b-i: motor de detección del barrido (solo lectura, sin crear items).
     `pendiente_revision`.
   - **#9990033** — FASE 2b-ii: creación de hallazgos del barrido + liberación del candado.
     `pendiente_revision`.

Esa parte fue correcta y **no se repite** (el commit `4db3d298` sigue intacto en la rama). Lo que
faltó: esa vuelta murió (kill/OOM) antes de intentar **cerrar** `#986` después de crear los
sub-items — el log muestra `timeout_reanudable` (17:49, max_turns, 1 commit) seguido de
`claim_liberado_al_morir_la_vuelta` (17:52). El item volvió a `aprobado_revisor` sin que nadie lo
aparcara como paraguas, el pool lo repartió de nuevo (a esta misma terminal, `wt-1`, reclamado
23:52:10) — mismo síntoma exacto que `#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#905`/`#878`/
`#906`/`#907`: un paraguas correctamente descompuesto que nunca recibió el intento de cierre que
activa el guard de "no completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- Query directa: `#9990032` y `#9990033` (`origen_item_id=986`) siguen intactos, `pendiente_revision`,
  sin `worker_sid` — ninguno fue tocado por nadie más, la descomposición original seguía siendo la
  correcta.
- Rama `circuito/item-986-torre-247-pieza-5b-fase-2b-ejecuci`: el único commit (`4db3d298`) sigue
  presente y limpio (diff aislado a `AuditorService.php`, +23/-3 líneas).
- Intento de cierre: `RoadmapItem::find(986)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (2b, `RoadmapItem.php` ~301-326) lo reenrutó automáticamente a `aprobado_irving` +
  `excluir_pool_automatico=true`, liberando `worker_sid`/`claimed_at`. Confirmado leyendo
  `$item->log` tras el save (evento `flags` auditando `excluir_pool_automatico: false → true`).

## Resultado

`#986` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
`#9990032` y `#9990033` cierren — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya
existente y verificado en las sesiones anteriores de este mismo bug) completa `#986` solo, sin
intervención manual.

**Sin cambio de código de negocio adicional.** El único cambio de código de esta pieza
(`detectoresCrossCutting()` + visibilidad pública de `archivosPhp()`/`relativo()`) ya estaba
commiteado en `4db3d298` desde la vuelta anterior. El trabajo real que falta (motor de detección
del barrido y creación de hallazgos) sigue en `#9990032`/`#9990033`, pendientes de triaje/revisión.
