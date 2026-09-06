# Item #9990410 — bucle reap sobre paraguas ya descompuesto (verificación)

## Contexto

El item #9990410 ("El circuito no distingue «la cuenta se quedó sin límite» de «este item es
demasiado grande»: 36 vueltas culparon al item") es el mismo patrón documentado repetidamente en
`CLAUDE.md` (#738, #745, #830, #816, #818, #848, #852, #905, #878, #906, #907, #924, #9990012,
#917, #910, #936, …): un paraguas que ya fue descompuesto correctamente en sub-items, pero cuya
vuelta murió **antes de intentar el cierre** del padre, dejándolo `en_progreso` sin dueño vivo.

## Qué encontró esta vuelta

Al arrancar, el item ya estaba `en_progreso` (reclamado por wt-3) con un log que mostraba:

1. `2026-09-06 16:35:12` — `soltar-claim`: una vuelta previa de wt-3 murió sin cerrar el item
   (kill/OOM/freno a media vuelta) → liberó el reclamo, volvió a `aprobado_revisor`.
2. El pool lo repartió de nuevo (a esta misma terminal, wt-3).

`php artisan circuito:cabida 9990410 --sid=wt-3` devolvió **CABE [ya_descompuesto]** — señal de
que el trabajo de descomposición ya existía. Verificado contra la BD: 4 sub-items con
`origen_item_id=9990410`, los 4 en `pendiente_revision`, sin reclamar:

| id | título | estado |
|----|--------|--------|
| #9990411 | FASE 1+2: detectar causa=limite_cuenta en vuelta.sh y no castigar el item | pendiente_revision |
| #9990412 | FASE 4: pausar el scheduler mientras la cuenta esté sin límite | pendiente_revision |
| #9990413 | FASE 3: visibilidad en la Torre del estado «cuenta sin límite» | pendiente_revision |
| #9990414 | FASE 5: reportar (sin cambiar) items con veces_timeouteo inflado por límite de cuenta | pendiente_revision |

La descomposición por fase (1+2 juntas por compartir el mismo punto de detección en `vuelta.sh`,
3/4/5 separadas) sigue el propio orden del `prompt` original del item. Nadie más la tocó desde
que se creó.

## Corrección aplicada

Se ejecutó el intento de cierre que faltaba: `estado_aprobacion = 'completado'` sobre #9990410.
El guard de paraguas (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo detectó y reenrutó:

```
antes:   en_progreso,   excluir_pool_automatico=0
después: aprobado_irving, excluir_pool_automatico=1
```

Log resultante (evento `paraguas_abierto`, "le quedan 4 sub-item(s) abierto(s)"). El item queda
fuera del pool/reaper hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo
complete solo cuando los 4 sub-items (#9990411–#9990414) cierren.

## Sin cambio de código de negocio

El trabajo técnico real (detección de `limite_cuenta` en `vuelta.sh`, no castigar contadores,
banner en la Torre, pausa del scheduler, y el reporte de items ya contaminados) sigue en los 4
sub-items, pendientes de que el revisor los tríe.
