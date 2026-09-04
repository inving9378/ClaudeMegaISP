# Item #743 — Cierre por descomposición (2026-08-29)

## Qué pedía el item

Fase 2 de la reconstrucción del #624 (sub-item de #624, criterio de q1/q2 del brief original): con
el inventario crudo de #742, cruzar cada id candidato contra `roadmap_items` (fila viva, rastro en
`comentarios_claude`/`log` de items relacionados, o inferencia del propio texto de los logs) y armar
una tabla — id, título, estado final, razón de no-merge, nivel de riesgo — filtrada por el criterio
que Irving ya eligió en q2 de #624 ("solo items con brief completo y nivel A/B que no llegaron a
`merge_commit`"), para que él decida manualmente cuáles reconstruir. El propio item aclaraba: "este
sub-item NO crea items nuevos, solo el reporte".

## Qué se hizo con esa decisión

`circuito:cabida` marcó el item como NO CABE en una sola vuelta (rama previa sin commits, ya
timeouteada antes: ver log del item, evento `timeout_escalado` de las 02:47). Siguiendo el flujo de
descomposición del circuito, una vuelta anterior de `wt-1` (2026-08-29 02:54) partió el trabajo de
cruce/filtrado en 5 sub-items, todos con `origen_item_id=743`:

- **#778** — lote A, 24 ids (rango 186-866).
- **#779** — lote B, 24 ids (rango 867-955).
- **#780** — lote C, 24 ids (rango 956-1013).
- **#781** — lote D, 24 ids (rango 1019-1078).
- **#782** — consolidación final: arma `docs/roadmap-p0-auditoria-item624.md`, la tabla real que
  #743 le debía a Irving, a partir del resultado de los 4 lotes.

Esa vuelta terminó correctamente sin rama ni commits propios (el flujo del circuito indica NO crear
rama cuando `circuito:cabida` da NO CABE), pero el item quedó sin el intento de cierre que dispara el
guard automático de paraguas del modelo (`RoadmapItem::boot()`, bloque `(2b)` — `saving()` sobre
`estado_aprobacion==='completado'` con `tieneSubItemsAbiertos()===true` reenruta a `aprobado_irving`
+ `excluir_pool_automatico=true` + log `paraguas_abierto`). Sin ese intento, el item quedó "abierto"
para el reaper de huérfanos, que lo re-encoló (`reap_count=1`, 2026-08-29 08:58) y esta vuelta lo
volvió a reclamar.

## Verificación contra la BD de dev (esta vuelta, 2026-08-29)

| Item | estado_aprobacion | status | worker_sid |
|---|---|---|---|
| 778 (lote A) | aprobado_revisor | pending | *(sin asignar)* |
| 779 (lote B) | aprobado_revisor | pending | *(sin asignar)* |
| 780 (lote C) | aprobado_revisor | pending | *(sin asignar)* |
| 781 (lote D) | aprobado_revisor | pending | *(sin asignar)* |
| 782 (consolidación) | aprobado_revisor | pending | *(sin asignar)* |

Los 5 hijos siguen aprobados y esperando ser reclamados por el pool de despacho. `yaFueDescompuesto()`
confirma que #743 no debe volver a descomponerse (evitar duplicar los 5 sub-items).

## Por qué #743 se cierra aquí (como paraguas, no como completado)

El alcance real de #743 — cruzar ids, filtrar por brief+nivel, armar la tabla — vive íntegro en
#778/#779/#780/#781/#782. Este item no tiene ya trabajo propio de implementación: es correcto que
quede retenido como paraguas (`aprobado_irving` + `excluir_pool_automatico=true`, mismo patrón que el
cierre de su padre #624, ver `docs/roadmap-item-624-cierre-por-descomposicion.md`) hasta que cierren
sus 5 hijos. Este commit + el intento de cierre que lo acompaña sacan a #743 del loop de
reap/re-escalación (bug ya documentado para el mismo patrón en `docs/roadmap-bucle-reap-item-745-verificacion.md`).

**Sin cambio de código funcional** — este commit es el cierre documental del item; el trabajo
pendiente sigue abierto y rastreable en #778, #779, #780, #781 y #782.
