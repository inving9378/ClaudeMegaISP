# Item #882 — cierre del bucle reap sobre paraguas ya descompuesto (Torre 24/7 · reclamos worker_sid huérfanos)

## Contexto

`#882` ("Torre 24/7 · soltar reclamos worker_sid huérfanos sin proceso vivo") es sub-item de
seguimiento de `#873`. Una vuelta previa (`wt-1`, 2026-09-03 11:33) ya hizo el trabajo correcto:

1. Corrió `circuito:cabida` → **NO CABE** (histórico ~10.5h) — no picó código de implementación.
2. Investigó (solo-lectura) y descompuso el trabajo real en:
   - **#897** — liberar en bulk los 47 reclamos huérfanos actuales, vía el mismo efecto que
     `liberarReclamo` (no reinventa el mecanismo).
   - **#898** — causa raíz: el guard de paraguas (`RoadmapItem.php` ~301-326) parquea
     `aprobado_irving` + `excluir_pool_automatico` sin limpiar `worker_sid`/`claimed_at`, por eso
     el conteo de huérfanos pasó de 19 a 47 en 6h — sin ese fix, #897 se repetiría solo.
3. Registró la decisión (tipo=decision en `comentarios_claude`).

Esa parte fue correcta y **no se repite**. Lo que faltó: esa vuelta nunca intentó **cerrar**
`#882` después de crear los sub-items. El ítem se quedó `en_progreso` con el `worker_sid` de esa
sesión, sin que nadie liberara el claim. El reaper de huérfanos (`reaper-rapido`) lo vio con el
slot `wt-1` libre, lo re-encoló a `aprobado_revisor` (`reap_count=1`, log `huerfano_reencolado`
2026-09-03 11:38:02), y el pool lo repartió de nuevo (a esta misma terminal, `wt-1`) sin que
hubiera trabajo propio que hacer — mismo síntoma exacto que
`#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#878`: un paraguas correctamente descompuesto que
nunca recibió el intento de cierre que activa el guard de "no completar mientras queden hijos
abiertos".

## Verificación de esta vuelta

- Query directa: `#897` y `#898` (ambos `origen_item_id=882`) siguen `estado_aprobacion=aprobado_revisor`,
  sin `worker_sid` (nadie los reclamó ni los tocó). La descomposición original seguía siendo la
  correcta — nadie más la tocó.
- Intento de cierre: `RoadmapItem::find(882)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (2b, `RoadmapItem.php` ~301-326) lo reenrutó automáticamente a `aprobado_irving` +
  `excluir_pool_automatico=true`, agregando al log el evento `paraguas_abierto` ("le quedan 2
  sub-item(s) abierto(s): no se completa"). Confirmado leyendo `$item->log` tras el save (evento
  `paraguas_abierto` seguido del evento `flags` que audita el cambio de
  `excluir_pool_automatico`).

## Resultado

`#882` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
`#897` y `#898` cierren — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya existente
y verificado en las sesiones anteriores de este mismo bug) completa `#882` solo, sin intervención
manual.

**Sin cambio de código de negocio.** El trabajo técnico real (liberar en bulk los reclamos
huérfanos actuales + arreglar la causa raíz para que el guard de paraguas limpie
`worker_sid`/`claimed_at` al parquear) sigue en `#897`/`#898`, pendientes de ejecución.

⚠️ Nota heredada del spec original de #882: al re-verificar antes de liberar cualquier reclamo en
#897, cruzar sid+item contra `RegistroPids::todos()` **al momento de ejecutar**, no contra el
diagnóstico de 2026-09-03 ~16:30 citado en la descripción original (puede haber cambiado). #870 y
#873 en particular podrían ya no estar activos, o podría haber sesiones nuevas activas que no
estaban en el diagnóstico original.
