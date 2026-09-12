# Item #9990874 — cierre del bucle reap sobre paraguas ya descompuesto (Items de corrección del Circuito CC — para desatorar el flujo)

## Contexto

`#9990874` ("Items de corrección del Circuito CC — para desatorar el flujo") era un documento
redactado por Cowork ("v2 — se agrega CIRC-05") con 9 bloques listos para pegarse como items
nuevos en la Torre de Control (CIRC-00 a CIRC-08). El propio documento explicaba por qué nadie los
había creado ya: la API externa de escritura (`roadmap-externo`) solo permite tocar
`estado_aprobacion`/`nivel_riesgo`/`comentarios_claude`, no crear items — así que el trabajo real
del item no era "arreglar el circuito" directamente, sino **materializar el documento como items
reales de la Hoja de Ruta** usando `circuito:sub-item` (mecanismo on-box, sin esa limitación).

Una vuelta previa (`wt-1`, 2026-09-11 18:26-18:40) ya hizo exactamente eso, y bien:

1. **CIRC-00** (no era item nuevo, solo empujón): cerró `#9990756` (ya resuelto en `main` desde el
   merge de su item padre `#9990740`, solo le faltaba el cierre formal) y dejó `#9990075` intacto
   (correctamente parqueado esperando una credencial/decisión material de Irving).
2. **CIRC-01 a CIRC-08** creados como 11 sub-items reales de `#9990874` vía `circuito:sub-item`,
   con el spec completo copiado del documento, `depende_de` cableado a nivel MR-36 donde el
   documento declaraba dependencias reales, y CIRC-02 armado como paraguas propio de sus 3 hijos
   (02a/02b/02c) en vez de aplanarlos como hermanos sueltos.
3. Documentó todo en `docs/bitacora/2026-09-11-item-9990874.md` (commit `79815f23`, **ya mergeado
   a `main`** — confirmado con `git show main:docs/bitacora/2026-09-11-item-9990874.md`).

Esa parte fue correcta y **no se repite**. Lo que faltó: esa vuelta terminó por
`timeout_reanudable` (agotó turnos, la rama tenía 1 commit → reanudación 1 de 2) **antes de
intentar cerrar** el paraguas. El propio cierre de la bitácora dice "se intentará `completado`,
el guard de paraguas lo parqueará..." — pero ese intento nunca se ejecutó. El item quedó
`en_progreso` colgado hasta que el reaper/scheduler lo volvió a repartir (esta vuelta, reclamado
de nuevo como `wt-1` el 2026-09-12 00:39) — mismo síntoma exacto que `#738`/`#745`/`#830`/`#816`/
`#818`/`#848`/`#852`/`#905`/`#878`/`#906`/`#907`/`#924`/`#9990012`/`#917`/`#910`/`#936`/
`#9990408`/`#962`/`#9990554`/`#9990549`/`#9990624`/`#9990650`/`#9990733`/`#9990740`/`#9990807`/
`#9990826`/`#9990836`/`#9990856`: un paraguas correctamente descompuesto que nunca recibió el
intento de cierre que activa el guard de "no completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- `circuito:cabida 9990874 --sid=wt-1` → `CABE [ya_descompuesto] — procede a implementar
  #9990874.` (el candado de idempotencia detecta los 11 hijos existentes, no re-descompone).
- Query directa: los 11 hijos (`origen_item_id=9990874` o `9990882` para los 3 nietos de CIRC-02)
  existen intactos:
  - `#9990881` (CIRC-01) y `#9990889` (CIRC-06) — `en_progreso`, reclamados por `wt-6` y `wt-5`
    respectivamente (con dueño, no se tocan — aislamiento #334).
  - `#9990882` (CIRC-02, paraguas), `#9990883` (CIRC-02a), `#9990885` (CIRC-02c), `#9990886`
    (CIRC-03), `#9990887` (CIRC-04) — `aprobado_revisor`, sin reclamar.
  - `#9990884` (CIRC-02b) — `requiere_irving`, sin reclamar.
  - `#9990888` (CIRC-05), `#9990890` (CIRC-07), `#9990891` (CIRC-08) — `pendiente_revision`, sin
    reclamar.
- `#9990756` sigue `completado` con `excluir_pool_automatico=false` (confirmado). `#9990075` sigue
  `aprobado_irving` con `excluir_pool_automatico=true` (confirmado, sin cambios).
- El commit `79815f23` (bitácora) ya está en `main` — no hay nada nuevo que integrar de código.
- Intento de cierre: `RoadmapItem::find(9990874)->estado_aprobacion = 'completado'; ->save();` →
  el guard `saving` (2b, `RoadmapItem.php` ~301-332) lo reenrutó automáticamente a
  `aprobado_irving` + `excluir_pool_automatico=true`, con el evento `paraguas_abierto` en el log
  ("le quedan 11 sub-item(s) abierto(s): no se completa").

## Resultado

`#9990874` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta
que sus 11 hijos cierren — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya existente
y verificado en las sesiones anteriores de este mismo bug) completa `#9990874` solo, sin
intervención manual.

**Sin cambio de código de negocio.** El trabajo técnico real de "desatorar el flujo del circuito"
(CIRC-01 a CIRC-08) sigue en `#9990881`–`#9990891`, cada uno con su spec completo copiado del
documento origen, esperando triaje/aprobación/reclamo según su estado actual.
