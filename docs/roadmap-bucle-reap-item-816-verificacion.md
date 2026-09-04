# Item #816 — cierre del bucle reap sobre el paraguas #839/#840 (DocumentaciónCorporativa Fase 5d-2)

## Contexto

#816 es "Fase 5d-2" del checklist de offboarding de DocumentaciónCorporativa: ampliar la pantalla
de #815 (Fase 5d-1) con los 6 ítems de offboarding que no tienen tabla propia (correo, VPN,
WhatsApp, equipo, respaldo, finiquito RH). El propio item ya trae Irving aprobando sus 3 preguntas
estructuradas 3 veces (2026-08-29 13:42, 2026-08-31 17:09, 2026-09-01 13:03) — siempre la opción
recomendada.

Una vuelta previa (`wt-1`, 2026-09-01 13:09) ya hizo el diagnóstico correcto:

1. Corrió `circuito:cabida 816` → **NO CABE** (`ya_timeouteo_antes`).
2. En vez de picar código directo, descompuso el trabajo en dos sub-items:
   - **#839** — "Fase 5d-2a — backend de los 6 ítems fijos de offboarding sin tabla propia"
     (ejecutable ya, sin depender de #815).
   - **#840** — "Fase 5d-2b — wire 'Otros pendientes' dentro de DcOffboarding.vue" (bloqueado a
     propósito hasta que #815 tenga `merge_commit` en `main` — #815 sigue `aprobado_irving` con
     rama propia pero sin mergear, exactamente la contradicción de spec que una vuelta aún
     anterior, `wt-2`, ya había escalado y que Irving resolvió reaprobando #816 sin tocar #815).
3. Anotó explícitamente que no se re-escalaba la contradicción #815-sin-mergear porque Irving ya
   la conocía y re-aprobó #816 igual 3 veces.

Esa parte fue correcta. Lo que faltó: **esa vuelta nunca intentó cerrar #816** (a `completado` ni
de ninguna otra forma) tras descomponerlo. El item se quedó `en_progreso` con el `worker_sid` de
esa sesión, sin que nadie liberara el claim. El reaper de huérfanos (`reaper-rapido`) lo vio con
el slot libre, lo re-encoló a `aprobado_irving` (`reap_count=1`, evento `huerfano_reencolado` en
el log, 2026-09-01 19:14) — mismo síntoma que #738/#745/#830: un paraguas correctamente
descompuesto que nunca recibió el intento de cierre que activa el guard de "no completar mientras
queden hijos abiertos".

## Verificación de esta vuelta

- Query directa: `RoadmapItem::where('origen_item_id', 816)->get()` → dos hijos, **#839**
  ("Fase 5d-2a — backend...") y **#840** ("Fase 5d-2b — wire..."), ambos
  `estado_aprobacion=requiere_irving`, `worker_sid=null`, sin rama — siguen abiertos, esperando
  decisión de Irving (heredaron la misma escalación de #816: son documentación aditiva, pero
  #840 en particular sigue bloqueado por la contradicción de spec con #815).
- Estado de #815 confirmado: `aprobado_irving`, con rama `circuito/item-815-...` creada pero
  `merge_commit=null` — sigue sin mergear a `main`, confirma que #840 sigue correctamente
  bloqueado.
- Intento de cierre: `RoadmapItem::find(816)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (2b, `RoadmapItem.php` ~301-326) lo reenrutó automáticamente a
  `aprobado_irving` + `excluir_pool_automatico=true` (confirmado antes/después: `abiertos=2` →
  `excluir_pool_automatico=true` tras el save).

## Resultado

#816 queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
#839 y #840 cierren — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya existente y
verificado en las sesiones de #738/#745/#830) completa #816 solo, sin intervención manual.

**Sin cambio de código de negocio.** El trabajo técnico real (backend de los 6 ítems fijos y su
wire en la UI de #815) sigue en #839 y #840, pendientes de que Irving los resuelva.
