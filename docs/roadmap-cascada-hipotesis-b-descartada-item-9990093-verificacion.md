# Item #9990093 — Descartar hipótesis (b): escritura cruda a `roadmap_items.estado_aprobacion` fuera del modelo

**Fecha:** 2026-09-04 · **Padre:** #9990088 (paso 1) · **Relacionado:** #9990076 (Fase 1, repro secuencial en el mismo proceso — NO reprodujo), #9990012/#924 (root-cause del cierre en cascada que dejó pasar a #32)

## Objetivo

Antes de montar el repro concurrente de 2 procesos (Fase 2b, ya creada como sub-item aparte),
descartar (o confirmar) que el estado incorrecto de #32 vino de una escritura **cruda** a
`roadmap_items.estado_aprobacion` — vía `DB::table('roadmap_items')->update(...)`,
`DB::statement(...)`, `DB::raw(...)`, `updateOrInsert(...)`, o incluso un `Model::where(...)->update(...)`
(mass-update de Eloquent, que TAMBIÉN salta `saving()`/`saved()`) — que salte los hooks del
modelo (guard(1) en `RoadmapItem.php:272-289` y el hook de cascada en `RoadmapItem.php:497-526`,
que solo corren si el `save()` pasa por una instancia Eloquent individual).

## Método

Grep dirigido en `app/` sobre los 4 patrones señalados en el spec del item, sobre los
consumidores nombrados (`MergeRunner`, `JarvisService`, `RoadmapController`, todos los comandos
`circuito:*`) y cualquier otro hallado, seguido de inspección de contexto línea por línea para
cada hit sobre la columna `estado_aprobacion`.

## Hallazgos

### A) Escrituras CRUDAS reales a `estado_aprobacion` en código vivo (bypasan los hooks)

| Archivo:línea | Método | Valor que escribe | ¿Toca `completado`? |
|---|---|---|---|
| `app/Modules/Addons/Roadmap/Services/RoadmapCircuitoService.php:2294-2295` | `claimNextParalelo()` — `DB::table('roadmap_items')->...->update($update)` | `'en_progreso'` (vía `$update['estado_aprobacion']`) | **No** |
| `app/Modules/Addons/Roadmap/Console/SchedulerCommand.php:161-168` | reclamo atómico inline (duplica la misma lógica que `claimNextParalelo`, pero sin pasar por el helper compartido) — `DB::table('roadmap_items')->...->update([...])` | `'en_progreso'` | **No** |

Ambas son escrituras crudas legítimas y a propósito (comentario explícito en
`RoadmapCircuitoService.php:2288-2293`: el `estado_previo_claim` y `estado_aprobacion` deben
salir en el MISMO `UPDATE` atómico para que una caída a medias no deje el item huérfano sin
rastro de dónde vino). **Ninguna de las dos escribe `'completado'`** — solo mueven el item de
`aprobado_*`/`pendiente_revision` (A) hacia `en_progreso`. El guard(1) (que bloquea que un
paraguas cierre con hijos abiertos) y el hook de cascada (que dispara `completado` en el padre)
son indiferentes a la transición hacia `en_progreso`: no hay superficie de ataque aquí para el
bug de #32.

### B) Escrituras crudas a `estado_aprobacion='completado'` — SOLO en migraciones históricas ya ejecutadas

| Archivo:línea | Contexto |
|---|---|
| `app/Modules/Addons/Roadmap/migrations/2026_07_13_160000_reconcile_pendiente_revision_stale_bandeja.php:26` | Migración de datos de un solo uso (2026-07-13), **idempotente** (`WHERE estado_aprobacion='pendiente_revision' AND status='done'`), decisión de Irving del item #429 para vaciar bandeja stale. Ya corrida y consumida — no es un consumidor recurrente ni parte de ningún flujo en caliente. |

Esta migración corrió una sola vez, 7 semanas antes de la investigación de #32 (2026-09), y su
`WHERE` excluye explícitamente cualquier fila que no estuviera ya en `pendiente_revision` — es
decir, no pudo tocar a #32 salvo que #32 estuviera exactamente en ese estado ese día, lo cual no
es el escenario que #9990012/#924 investigan (el bug de #32 es sobre un cierre en cascada
reciente, no sobre la bandeja stale de julio). Se documenta por completitud del grep, no como
causa plausible.

### C) Todas las escrituras que SÍ ponen `estado_aprobacion='completado'` en código vivo van por Eloquent (`->save()`)

| Archivo:línea | Save confirmado |
|---|---|
| `app/Modules/Addons/Roadmap/Services/MergeRunner.php:311` (`markMerged()`) | `$item->save()` en la línea 331 (tras armar `log`) |
| `app/Modules/Addons/Roadmap/Models/RoadmapItem.php:260` (dentro del propio hook `static::saving`, sincroniza `status='done'` → `estado_aprobacion`) | Es el mismo save en curso — no es una escritura aparte |
| `app/Modules/Addons/Roadmap/Models/RoadmapItem.php:524` (hook de cascada `static::saved`, cierre de paraguas) | `$padre->save()` en la línea 525, inmediatamente después |
| `app/Modules/Addons/Roadmap/Controllers/RoadmapController.php:1627` (`cerrar_origen` al crear un seguimiento) | `$origen->save()` en la línea 1638 |
| `app/Modules/Addons/Roadmap/Controllers/RoadmapController.php:2416` (validación funcional de Irving) | `$item->save()` en la línea 2421 |

Todas usan asignación de propiedad sobre una instancia Eloquent (`$item->`, `$padre->`,
`$origen->`) seguida de `->save()` — **disparan `saving()`/`saved()`**, así que guard(1) y el
hook de cascada SÍ corren en cada una de ellas.

### D) Otros `DB::table('roadmap_items')->update(...)` encontrados en el grep

Se revisaron todos los demás hits de `DB::table('roadmap_items')` en `RoadmapCircuitoService.php`
(`renovarLease` línea 1298, colisiones línea 2794), `FronterasService.php:496`,
`JarvisVigilarCommand.php` (solo lecturas `->count()`/`->where()->first()`), `EstadoItemCommand.php`,
`CoherenciaPoolCommand.php`. Ninguno incluye `estado_aprobacion` en su cláusula `SET` — todos
filtran por esa columna en el `WHERE` o tocan columnas distintas (`claimed_at`,
`colision_pausada_por`, etc.). Los mass-updates vía Eloquent (`RoadmapItem::where(...)->update(...)`)
tampoco aparecen para esta columna: los únicos consumidores que iteran `RoadmapItem::where('estado_aprobacion','en_progreso')->get()`
(`ReapStuckCommand.php`, `WatchdogService.php`) hacen `->get()` + `reencolarHuerfano()`, que opera
sobre instancias individuales con `$item->save()` (confirmado: `RoadmapCircuitoService.php:3194`/`3206`).

## Conclusión

**Hipótesis (b) DESCARTADA.** Ninguna escritura cruda (ni `DB::table`/`DB::statement`/`DB::raw`,
ni mass-update de Eloquent) pone `estado_aprobacion='completado'` en un flujo vivo/recurrente.
Las dos únicas escrituras crudas reales en código activo (`RoadmapCircuitoService::claimNextParalelo`
y el reclamo inline de `SchedulerCommand`) solo mueven el item a `en_progreso`, transición que no
pasa por guard(1) ni por el hook de cascada porque ninguno de los dos reacciona a esa transición.
La única escritura cruda que sí toca `'completado'` es una migración de datos ya ejecutada y
acotada por fecha/estado, incompatible con la ventana del incidente de #32.

**El trabajo pasa íntegro a la Fase 2b** (repro con 2 procesos concurrentes, ya creada como
sub-item aparte de #9990088/#9990076) — la causa de #32 sigue siendo o bien concurrencia real
entre procesos (dos `save()` de instancias Eloquent en carrera sobre el mismo padre) o algún otro
mecanismo aún no identificado, pero **no** una escritura cruda fuera del modelo.

## Hallazgo colateral (fuera de alcance de este item, no se toca aquí)

`SchedulerCommand.php:161-168` duplica inline la misma lógica de reclamo atómico que
`RoadmapCircuitoService::claimNextParalelo()` (`guardReclamoAtomico`), en vez de reusar el
helper compartido. No es la causa del bug de #32 (ambas escriben `en_progreso`, no `completado`),
pero es una duplicación de un candado crítico — dos lugares que hay que mantener sincronizados
si el candado cambia. Si se retoma, es candidato a un item propio de consolidación (no de
seguridad urgente).
