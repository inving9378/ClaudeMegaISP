# Item #878 — cierre del bucle reap sobre paraguas ya descompuesto (Torre 24/7 · Pieza 2 — freno de sequía)

## Contexto

`#878` ("Torre 24/7 · Pieza 2 — deadlock del freno de sequía: el generador de trabajo se apaga y
no se puede re-armar") es el ítem que pedía documentar el mecanismo de `AuditorService` y diseñar
+ implementar una vía de re-armado. Una vuelta previa (`wt-2`, 2026-09-03 11:06-11:12) ya hizo el
trabajo correcto:

1. Ejecutó FASE 1 (documentación del mecanismo: `gastoApagado()`, `rachaSeca()`, `debeCorrer()`,
   config `circuito.auditor.sequia.*`) y FASE 2 (diseño: eligió el candidato **(c) caducidad
   temporal / half-open**, descartando (a)/(b)/(d) con justificación) — ambas en modo solo-lectura,
   registradas como reporte tipo=decision (#4960) en `comentarios_claude`.
2. Determinó que FASE 3 (implementar) + FASE 4 (verificar) no cabían en la misma vuelta y las
   descompuso en **#891** ("Freno de sequía Nivel 2 (#712) — implementar re-armado por caducidad
   temporal (half-open) + exponer en Torre → Configuración"), con spec completo citando archivos y
   líneas exactas (`config/circuito.php`, `AuditorService.php`, migración de `torre_config`,
   `TorreAutomationPolicy`, `RoadmapController`, `TorreConfiguracion.vue`).
3. `#891` pasó el triaje normal y el **revisor lo escaló a Irving** (`requiere_irving`, confianza
   media, categoría negocio: define umbrales/tiempos de un guardrail del propio circuito) con un
   brief completo de 4 preguntas estructuradas (TTL, disparo del re-armado, qué exponer en Torre,
   dónde persistir el estado), cada una con opciones, pros/contras y recomendación — listo para que
   Irving decida.

Esa parte fue correcta y **no se repite**. Lo que faltó: esa vuelta nunca intentó **cerrar** `#878`
después de crear el sub-item. El ítem se quedó `en_progreso` con el `worker_sid` de esa sesión, sin
que nadie liberara el claim. El reaper de huérfanos (`reaper-rapido`) lo vio con el slot `wt-2`
libre, lo re-encoló a `aprobado_revisor` (`reap_count=1`, log `huerfano_reencolado`
2026-09-03 11:12:02), y el pool lo repartió de nuevo (a esta terminal, `wt-1`) sin que hubiera
trabajo propio que hacer — mismo síntoma exacto que `#738`/`#745`/`#830`/`#816`/`#818`/`#848`: un
paraguas correctamente descompuesto que nunca recibió el intento de cierre que activa el guard de
"no completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- Query directa: `#891` (único hijo, `origen_item_id=878`) sigue `estado_aprobacion=requiere_irving`,
  con su brief de 4 preguntas intacto y sin `worker_sid` (nadie lo reclamó ni lo tocó). La
  descomposición original seguía siendo la correcta.
- Intento de cierre: `RoadmapItem::find(878)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (2b, `RoadmapItem.php` ~301-326) lo reenrutó automáticamente a `aprobado_irving` +
  `excluir_pool_automatico=true`, agregando al log el evento `paraguas_abierto` ("le quedan 1
  sub-item(s) abierto(s): no se completa"). Confirmado leyendo `$item->log` tras el save (evento
  `paraguas_abierto` seguido del evento `flags` que audita el cambio de
  `excluir_pool_automatico`).

## Resultado

`#878` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
`#891` cierre — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya existente y verificado
en las sesiones anteriores de este mismo bug) completa `#878` solo, sin intervención manual.

**Sin cambio de código de negocio.** El trabajo técnico real (implementar el re-armado por
caducidad temporal del freno de sequía Nivel 2 + exponerlo en Torre → Configuración) sigue en
`#891`, pendiente de que Irving decida entre las 4 preguntas estructuradas de su brief (TTL,
mecanismo de disparo, qué exponer en Torre, dónde persistir el estado).
