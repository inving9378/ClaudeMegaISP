# Item #916 — cierre del bucle reap sobre paraguas ya descompuesto (Circuito CC #911 Fase 5)

## Contexto

`#916` (sub-item de seguimiento de `#911`, Fase 5 — "aflojar el pre-filtro de módulo") traía una
precondición explícita: no ejecutar hasta que las Fases 2 (`#913`) y 4 (`#915`) estuvieran
`completado` y verificadas.

Una vuelta previa de esta misma terminal (`wt-1`) ya investigó esa precondición y encontró que,
aunque `#915` seguía `en_progreso` en base de datos bajo otro dueño (`wt-3`, sin `merge_commit`),
su código real (el candado de esquema entre worktrees, `GuardedMigrateCommand::conCandadoDeEsquema`)
**ya estaba mergeado a `main`** (commits `c1ee65f1`/`b5527210`), en el mismo commit que trae el
propio trabajo que pide `#916`: la perilla `config('circuito.paralelo_mismo_modulo')` y su conteo
real en `ejecutablesParalelo()` (`RoadmapCircuitoService.php:2507`), con su fix posterior
`24807ebd`/`0b9aef93` (el conteo contaba módulos únicos en vez de terminales por módulo).

Consultó a Thomas (`circuito:consultar`) con esa evidencia — opción recomendada A ("tratar la
precondición como satisfecha en el código, agregar SOLO la exposición faltante en Torre →
Configuración y cerrar #916 documentando que #915 sigue abierto en DB bajo otro dueño") — y Thomas
respondió **PROCEDE**. Acto seguido corrió `circuito:cabida`, que dio **NO CABE**
(`historico_excede_umbral`) para lo único que faltaba: exponer la perilla en Torre → Configuración
(el endpoint `GET/POST /api/roadmap/torre/config` + `TorreConfigService`/`TorreConfig` solo cubren
hoy `nivel_automatizacion`, `autopilot_max_nivel`, `auditor_*`, `valvula_*`, `jarvis_icono` — ni
`desconocido_diferido` ni `paralelismo` ni `paralelo_mismo_modulo` tienen UI, así que la premisa del
item de "extender el endpoint que ya sirve esas perillas" no era literal: había que construir la
exposición, no solo sumar un campo a un form existente).

Esa vuelta hizo lo correcto: descompuso el trabajo restante en **`#9990005`** ("Exponer
`paralelo_mismo_modulo` en Torre → Configuración") — pero murió antes de intentar **cerrar** al
padre (`claim_liberado_al_morir_la_vuelta` en el log de `#916`, 2026-09-03 16:29). El item quedó
`aprobado_revisor` sin nadie deteniendo el reparto, y el pool lo repartió de nuevo a esta terminal —
mismo síntoma que `#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#905`/`#878`/`#906`/`#907`: un paraguas
correctamente descompuesto que nunca recibió el intento de cierre que activa el guard de "no
completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- `git log` confirma los 4 commits en `main`: `c1ee65f1`/`b5527210` (candado de esquema #915 +
  perilla #916) y `24807ebd`/`0b9aef93` (fix del conteo real por módulo). El código de
  `ejecutablesParalelo()` en `main` ya lee `config('circuito.paralelo_mismo_modulo', 1)` en las dos
  líneas donde debe (pre-filtro y expansión del conteo), y `config/circuito.php:206` ya declara
  `'paralelo_mismo_modulo' => max(1, (int) env('CIRCUITO_PARALELO_MISMO_MODULO', 1))` — el trabajo
  de código de `#916` está íntegro en `main` desde antes de que esta vuelta empezara.
- `php artisan circuito:cabida 916 --sid=wt-1` → **CABE `[ya_descompuesto]`** — confirma que el
  sistema ya reconoce la descomposición previa y que corresponde proceder a "implementar" (en este
  caso, intentar el cierre) en vez de volver a descomponer.
- Query directa: `#9990005` (`origen_item_id=916`) sigue `estado_aprobacion=pendiente_revision`,
  `status=pending`, sin `worker_sid` ni `branch` — nadie más lo tocó, la descomposición original
  seguía siendo la correcta.
- Intento de cierre: `RoadmapItem::find(916)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (2b, `RoadmapItem.php` ~301-326) lo reenrutó automáticamente a `aprobado_irving` +
  `excluir_pool_automatico=true`, agregando al log el evento `paraguas_abierto` ("le queda 1
  sub-item(s) abierto(s): no se completa. Queda como paraguas y cierra solo cuando el último de
  ellos cierre").

## Resultado

`#916` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
`#9990005` cierre — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya existente y
verificado en las sesiones anteriores de este mismo bug) completa `#916` solo, sin intervención
manual.

**Sin cambio de código de negocio.** El trabajo técnico real de código de `#916` (la perilla
`paralelo_mismo_modulo` y su conteo real por módulo) ya está en `main`; lo único pendiente —
exponerla en Torre → Configuración con el mismo patrón que `autopilot_max_nivel` (columna nullable
en `torre_config` que sobre-escribe al `config/circuito.php` cuando Irving la fija en pantalla) —
sigue en `#9990005`, pendiente de triaje/aprobación.
