# Item #9990810 — Cerrar VoIP: de dev hasta que suene el teléfono en producción — bucle reap sobre paraguas ya descompuesto

## Contexto

Misma familia de bug documentada repetidamente en `CLAUDE.md` (#738, #745, #830, #816, #818,
#848, #852, #905, #878, #906, #907, #924, #9990012, #917, #910, #936, #9990408, #962, #9990554,
#9990549, #9990624, #9990650, #9990807, entre otros): un item se descompone correctamente en
sub-items, pero nadie ejecuta el intento de cierre que dispara el guard de paraguas
(`RoadmapItem.php` bloque "(2b) PARAGUAS"), así que el item se queda colgado hasta que el reaper
lo re-encola y el pool lo vuelve a repartir sin que haya trabajo propio que hacer.

## Lo que ya se hizo bien (sesiones previas)

`#9990810` continúa a `#9990718` (provisionador VoIP): cerrar la Fase 1 (provisión idempotente +
llamada real con audio) y llevar el trabajo a main + emitir versión. Dos vueltas previas hicieron
lo correcto, en orden:

1. **`wt-1` (2026-09-11 14:30)** — no pudo hacer `checkout` de la rama original
   `circuito/item-9990718-provisionador-voip` (ya montada en otro worktree), así que trajo sus 15
   commits vía `merge --no-ff` a la rama propia del item #9990810, resolviendo 1 conflicto menor en
   `PlanNumeracionTest.php`. Commit de merge: `c1df7ac1`.
2. **`wt-3` (2026-09-11 14:51, sesión anterior a ésta)** — corrió `circuito:cabida` → **NO CABE**
   (`ya_timeouteo_antes`). Investigó el estado real antes de descomponer: **la Fase 1 ya estaba
   completa**, verificada en `docs/bitacora/2026-09-10-item-9990718.md` (16/16 criterios, incluida
   una llamada real con audio bidireccional), y ya vivía en la rama del item (commit `c1df7ac1`
   de arriba). Descompuso **solo lo que faltaba**:
   - **#9990824** — "VoIP Fase 2 — mergear el provisionador (#9990718) a main y verificar".
   - **#9990825** — "VoIP Fase 3 — emitir versión, publicar GitHub Release y documentar el
     runbook de despliegue" (depende de que #9990824 esté en main).

   No tocó código ni creó rama propia — la rama del item (con los 24 commits, incluido el merge
   de arriba) quedó intacta para que `#9990824` la mergee.

## Por qué se quedó colgado

Esa misma vuelta (`wt-3`, 14:51) nunca llegó a intentar el cierre del padre tras decidir la
descomposición — el log solo registra:

```
{"ts":"...14:51:41-06:00","por":"soltar-claim","sid":"wt-3",
 "evento":"claim_liberado_al_morir_la_vuelta",
 "motivo":"La vuelta de wt-3 terminó sin cerrar el item (muerte del proceso: kill, OOM o freno
           a media vuelta). Se libera el reclamo..."}
```

El item volvió a `aprobado_revisor`, un `colision_reanudado` más tarde (14:56:04) lo dejó libre
de nuevo, y el pool lo repartió otra vez — esta vuelta, de nuevo a `wt-3`.

## Verificación del estado real (esta vuelta)

```
9990810 (yo)  | en_progreso     | branch con 24 commits (incl. merge c1df7ac1 del provisionador)
  9990824     | requiere_irving | origen_item_id=9990810 | sin reclamar | sin rama propia
  9990825     | requiere_irving | origen_item_id=9990810 | sin reclamar | sin rama propia
```

Los 2 hijos siguen intactos, sin reclamar (`worker_sid` vacío) — la descomposición original
seguía siendo correcta, nadie más la tocó. La rama del item (`circuito/item-9990810-cerrar-voip-
de-dev-hasta-que-suene-el-t`) sigue con sus 24 commits sin mergear, tal como los dejó esa vuelta
— es justo el trabajo que le corresponde integrar a `#9990824` (Fase 2), no a este cierre.

## Corrección aplicada

A diferencia de `#9990807` (donde había un commit de helpers reusable y de bajo riesgo que
convenía adelantar), aquí los 24 commits de la rama SON el objeto de trabajo declarado de
`#9990824` — mergearlos por esta vía duplicaría/adelantaría su alcance sin la verificación que
ese sub-item exige ("verificar en main que el provisionador quedó completo"). Se dejó la rama
intacta para que `#9990824` la mergee cuando se reclame.

Se ejecutó únicamente el intento de cierre faltante (`estado_aprobacion = 'completado'`). El
guard de paraguas (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo detectó y reenrutó:

```
{"por":"paraguas","evento":"paraguas_abierto",
 "motivo":"Este item se descompuso y le quedan 2 sub-item(s) abierto(s): no se completa. Queda
           como paraguas y cierra solo cuando el último de ellos cierre.",
 "subitems_abiertos":2}
```

Resultado final verificado: `estado_aprobacion=aprobado_irving`, `excluir_pool_automatico=true`,
`worker_sid`/`claimed_at` limpiados. Queda sacado del pool/reaper hasta que el hook de cierre en
cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #9990824 y #9990825 cierren los dos.

Las 2 preguntas estructuradas que seguían `requiere_irving=true` sin `opcion_elegida` (q3: qué
hacer con la rama de 15 commits una vez verde en dev; q4: cómo interpretar "hasta que suene el
teléfono en producción" dado que prod está fuera de alcance) quedan tal cual en el brief del
item — son insumo para que Irving las resuelva cuando revise el paraguas, no bloquean el parqueo.

## Sin cambio de código de negocio

El trabajo técnico real (mergear el provisionador a main + verificar, luego emitir versión y
publicar el release con su runbook) sigue en #9990824 y #9990825, pendientes de que una terminal
los reclame. Esta vuelta no picó código nuevo — solo completó el paso de cierre que la vuelta
anterior dejó pendiente.
