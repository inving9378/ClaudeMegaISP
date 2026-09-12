# El despacho del Circuito CC es por FLOCK, no cola-DB (item #9991027)

Nota de aclaración solicitada por la Q3 de `#9990863` (retomada por `#9991024`, sub-item
`#9991027`). **Sin cambio de código de aplicación** — es documentación de un hecho técnico ya
existente. La única edición de código es este archivo + un puntero corto en el comentario de
`paralelismo` en `config/circuito.php`.

## La premisa que había que verificar

La pregunta original de `#9990863` asumía un modelo de **cola en base de datos**: que
`roadmap_items` tuviera columnas propias `estado_cola`, `terminal_asignada` y `asignado_at`, y
que un item pudiera quedar en un limbo "asignado pero sin terminal" si esas columnas quedaban
inconsistentes.

**Esas tres columnas NO EXISTEN en la tabla `roadmap_items`.** Verificado por grep contra
`app/`, `database/migrations/` y `config/` (2026-09-12): las únicas apariciones de esos tres
nombres en el módulo Roadmap son:

- `RoadmapItem::getEstadoColaAttribute()` (`app/Modules/Addons/Roadmap/Models/RoadmapItem.php`,
  ~línea 1891) — un **accessor derivado**, no una columna.
- Las claves `estado_cola` / `terminal_asignada` / `asignado_at` dentro de los arreglos que
  arman `RoadmapCircuitoService::compact()` (~línea 1021) y `::serialize()` (~línea 1043) — son
  **nombres de campo en la respuesta de la API/Torre**, no columnas de la tabla.

(Las otras coincidencias del grep — `VentaCustodia::asignado_at` en el módulo Ventas — son de
una tabla completamente distinta, sin relación con el Circuito.)

## Qué son realmente esos tres campos

| Campo en la API | De dónde sale | Es… |
|---|---|---|
| `estado_cola` | `RoadmapItem::getEstadoColaAttribute()` | Accessor **derivado en caliente** de `estacion` + `estado_aprobacion` + `branch` (nunca se guarda; el propio docblock del método explica por qué: "una columna paralela solo agregaría una segunda verdad que se desincroniza"). |
| `terminal_asignada` | `$i->worker_sid` | Columna real (`roadmap_items.worker_sid`), solo renombrada en la salida para que la API sea legible desde fuera. |
| `asignado_at` | `$i->claimed_at` | Columna real (`roadmap_items.claimed_at`), mismo caso — renombrada en la salida. |

Es decir: `terminal_asignada`/`asignado_at` sí "existen" — pero son alias de columnas que ya
tenían otro nombre, no una segunda fuente de verdad que se pueda desincronizar de forma
independiente. Y `estado_cola` nunca se persiste — se recalcula cada vez que se pide.

## Cómo despacha de verdad — FLOCK sobre worktrees, no filas de una cola

El mecanismo real vive en `SchedulerCommand` (`app/Modules/Addons/Roadmap/Console/
SchedulerCommand.php`), que corre on-box como `meganet` vía cron cada minuto:

- **Un slot = un worktree físico** `wt-1` .. `wt-N` (N = paralelismo efectivo, ver abajo). Cada
  worktree tiene su propio archivo de lock: `/home/meganet/circuito/wt-{K}.lock`.
- `SchedulerCommand::slotFree(string $sid)` (~línea 382) decide si un slot está libre
  intentando `flock($f, LOCK_EX | LOCK_NB)` sobre `wt-{K}.lock`. Si el `flock` se obtiene, el
  slot está libre (y se libera de inmediato — es solo una prueba); si falla, una vuelta viva ya
  lo tiene tomado.
- No hay tabla ni fila que diga "wt-3 está ocupada por el item #123" — la ocupación se infiere
  en vivo preguntándole al sistema de archivos, no leyendo un estado guardado. Por eso no puede
  existir el limbo "asignado pero sin terminal" que temía la pregunta original: si nadie tiene
  el `flock`, el slot está libre, punto — no depende de que ninguna columna se haya escrito o
  actualizado correctamente.
- El propio `SchedulerCommand` tiene, además, su propio `flock` (`scheduler.lock`) para que solo
  una instancia del scheduler corra a la vez.
- `roadmap_items.worker_sid` / `claimed_at` / `estado_aprobacion` sí son el registro de **a quién
  quedó reclamado** un item una vez que el scheduler decide lanzarlo — pero eso es reclamo de
  ITEM, no de slot/cola: el candado de concurrencia real (cuántas terminales a la vez) es el
  `flock` de los worktrees, no esas columnas.

## El tope de concurrencia (Q4 de #9990863, ya resuelta, referenciada aquí)

`RoadmapCircuitoService::getParalelismo()` (~línea 2146): lee `settings.circuito_paralelismo`
en runtime (override de Irving), con fallback a `config('circuito.paralelismo', 6)` — clamp
`[1, 12]`. Hoy son 6 worktrees físicos (`wt-1`..`wt-6`).

## Alcance de este item

Puramente documental — **no se implementa ningún diseño de cola-DB**. Si en el futuro Irving
decide migrar el modelo de despacho de flock-sobre-worktrees a una cola persistida en
`roadmap_items` (por ejemplo para soportar más slots de los que caben en flocks locales, o para
que el estado sobreviva a un reinicio del host), eso es una decisión de arquitectura aparte que
merece su propio item — no se abre aquí.
