# Item #920 — cierre del bucle reap sobre paraguas ya descompuesto (Torre 24/7 Pieza 4, Fase 5)

## Contexto

`#920` (sub-item de seguimiento de `#880`, Fase 5 — "verificar el clasificador AUTO/BANDEJA de
hardening contra items de seguridad reales ya cerrados") trae una precondición explícita en su
propia descripción: dice que depende de `#919` (Fase 3+4 — wiring del carril AUTO en
`RevisorService`/`PriorizarSeguridadCommand` + candado de regresión) "ya mergeado a main".

Una vuelta previa de esta misma terminal (`wt-2`, 2026-09-03 16:40) ya investigó esa precondición
y encontró que era **falsa**: en ese momento `#919` seguía `aprobado_irving`/`branch=null`/
`merge_commit=null` (nunca se había empezado), y a su vez `#919` dependía de `#918` (declarar el
criterio en `config/circuito_hardening.php`), que en ese momento estaba `en_progreso` bajo `wt-1`.
Confirmó por grep en el código (`RevisorService.php`, `PriorizarSeguridadCommand.php`) que el
carril AUTO/BANDEJA no existía todavía. Esa vuelta hizo lo correcto — no ejecutó el dry-run sin
que el carril existiera y descompuso el trabajo real hacia adelante en **`#9990009`** ("Torre 24/7
Pieza 4 — Fase 5 (real): correr la verificación del clasificador AUTO/BANDEJA — SOLO cuando #918 y
#919 tengan merge_commit en main"), con el spec completo ya con las 3 decisiones de Irving/CARLOS
(umbral 100% en frontera dura real, ≥90% en hardening trivial, qué hacer ante un falso AUTO) — pero
murió antes de intentar **cerrar** al padre (`claim_liberado_al_morir_la_vuelta` en el log de
`#920`, 2026-09-03 16:40:32). El item quedó `aprobado_revisor` sin nadie deteniendo el reparto, y
el pool lo repartió de nuevo a esta misma terminal — mismo síntoma que
`#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#905`/`#878`/`#906`/`#907`/`#916`: un paraguas
correctamente descompuesto que nunca recibió el intento de cierre que activa el guard de "no
completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- `#918` **sí** avanzó desde la vuelta anterior: `estado_aprobacion=aprobado_irving`,
  `merge_commit=423123e06c034181798b9d4c5242f00269ac6526` (commit visible en `main`,
  `git log`: "Integra circuito #918 ... a main"). El archivo `config/circuito_hardening.php`
  (criterio AUTO/BANDEJA de hardening) ya está en `main`.
- `#919` **sigue sin empezar**: `estado_aprobacion=aprobado_irving`, `branch=null`,
  `merge_commit=null` — nadie lo ha reclamado todavía. Confirmado por grep directo:
  `grep -n "carril" app/Modules/Addons/Roadmap/Services/RevisorService.php
  app/Modules/Addons/Roadmap/Console/PriorizarSeguridadCommand.php` no devuelve nada — el wiring
  del carril AUTO/BANDEJA (el método que `#920`/`#9990009` necesitan invocar para el dry-run)
  todavía no existe en el código.
- `php artisan circuito:cabida 920 --sid=wt-2` → **CABE `[ya_descompuesto]`** — confirma que el
  sistema ya reconoce la descomposición previa y que corresponde proceder a "implementar" (en este
  caso, intentar el cierre) en vez de volver a descomponer.
- Query directa: `#9990009` (`origen_item_id=920`) sigue `estado_aprobacion=pendiente_revision`,
  `status=pending`, sin `worker_sid` ni `branch` — nadie más lo tocó, la descomposición original
  (con la precondición explícita "SOLO cuando #918 y #919 tengan merge_commit en main" en su
  propio título) sigue siendo la correcta y sigue bloqueada por la mitad pendiente (`#919`).
- Intento de cierre: `RoadmapItem::find(920)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (bloque "(2b) PARAGUAS", `RoadmapItem.php` ~301-326) lo reenrutó automáticamente
  a `aprobado_irving` + `excluir_pool_automatico=true`, agregando al log el evento
  `paraguas_abierto` ("le queda 1 sub-item(s) abierto(s): no se completa. Queda como paraguas y
  cierra solo cuando el último de ellos cierre").

## Resultado

`#920` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
`#9990009` cierre — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya existente y
verificado en las sesiones anteriores de este mismo bug) completa `#920` solo, sin intervención
manual. `#9990009` en sí sigue correctamente bloqueado: no puede tomarse hasta que `#919` (el
wiring del carril, todavía sin reclamar por nadie) tenga `merge_commit` en `main`.

**Sin cambio de código de negocio.** El trabajo técnico real — wiring del carril AUTO/BANDEJA
(`#919`, pendiente de que alguna terminal lo reclame) y, después, la verificación del clasificador
contra items históricos de seguridad (`#9990009`, gateado a `#919`) — sigue su curso aparte.
