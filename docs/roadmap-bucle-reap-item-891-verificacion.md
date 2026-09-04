# Item #891 — cierre del bucle reap sobre paraguas ya descompuesto (Freno de sequía N2 — Fase 3+4)

## Contexto

`#891` ("Freno de sequía Nivel 2 (#712) — implementar re-armado por caducidad temporal (half-open) +
exponer en Torre → Configuración") es el sub-item de `#878` que cubría FASE 3 (implementar) + FASE 4
(verificar) del re-armado half-open. Irving ya había aprobado el brief de 4 preguntas estructuradas
(TTL en minutos default 30, mecanismo de disparo lazy, toggle+indicador visual en Torre, tabla nueva
de estado) el 2026-09-03 11:43.

Una vuelta previa (`wt-1`, 2026-09-03 13:34) ya hizo el trabajo correcto:

1. Corrió `circuito:cabida` → NO CABE (histórico ~37948s).
2. Detectó una contradicción real entre la spec literal de `#891` (TTL en días, sin tabla nueva, sin
   toggle — heredada de la redacción original del item) y las respuestas q1-q4 que Irving ya había
   aprobado (TTL en minutos, default 30, toggle + indicador visual, tabla nueva de estado).
3. Resolvió la contradicción con criterio propio: reusar el mecanismo existente de `settings`
   (el mismo que ya usa `#712`) en vez de crear una tabla nueva — minimalismo/estabilidad — pero
   **honrando** minutos + toggle + indicador, que eran decisión explícita de Irving, no negociable.
4. Descompuso el trabajo en dos sub-items propios: **#925** ("Fase 3a: half-open en AuditorService,
   backend, TTL en minutos, sin tabla nueva") y **#926** ("Fase 3b: exponer half-open en Torre →
   Configuración, migración + UI").

Esa parte fue correcta y **no se repite**. Lo que faltó: esa vuelta nunca intentó **cerrar** `#891`
después de crear los sub-items. El ítem se quedó `en_progreso` con el `worker_sid` de esa sesión
(`wt-1`), sin que nadie liberara el claim. El reaper de huérfanos (`reaper-rapido`) lo vio con el
slot `wt-1` libre, lo re-encoló a `aprobado_revisor` (`reap_count=1`, log `huerfano_reencolado`
2026-09-03 13:38:02), y el pool lo repartió de nuevo (a esta terminal, `wt-2`) sin que hubiera
trabajo propio que hacer — mismo síntoma exacto que `#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#878`:
un paraguas correctamente descompuesto que nunca recibió el intento de cierre que activa el guard de
"no completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- Query directa: `#925` y `#926` (los dos hijos, `origen_item_id=891`) siguen intactos —
  `#925` en `aprobado_revisor`, `#926` en `aprobado_irving`, ambos sin `worker_sid` (nadie los
  reclamó ni los tocó). La descomposición original seguía siendo la correcta.
- Intento de cierre: `RoadmapItem::find(891)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (2b, `RoadmapItem.php` ~301-326) lo reenrutó automáticamente a `aprobado_irving` +
  `excluir_pool_automatico=true`, agregando al log el evento `paraguas_abierto` ("le quedan 2
  sub-item(s) abierto(s): no se completa"). Confirmado leyendo `$item->log` tras el save (evento
  `paraguas_abierto` seguido del evento `flags` que audita el cambio de
  `excluir_pool_automatico`).

## Resultado

`#891` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
`#925` y `#926` cierren ambos — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya
existente y verificado en las sesiones anteriores de este mismo bug) completa `#891` solo, sin
intervención manual.

**Sin cambio de código de negocio.** El trabajo técnico real (implementar el half-open en
`AuditorService` con TTL en minutos vía `settings` + exponerlo en Torre → Configuración con
migración/UI) sigue en `#925`/`#926`, pendiente de que alguna vuelta futura los reclame y ejecute.
