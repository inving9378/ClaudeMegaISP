# Barrido de servicios con métodos de ciclo en módulo War Room (item #796, sub-item de #657)

**Alcance de esta pasada:** los 4 sitios que nombra la descripción del item dentro de
`app/Modules/Addons/WarRoom/` — `Services/InsightsService.php`, `Console/RefreshWarRoomCommand.php`,
`Jobs/` (`RefreshInsightsJob`, `SendMeetingMinutesJob`) y `Observers/` (`ActionItemObserver`). Se
amplió (mismo criterio que los precedentes #647/#792/#793/#794: "y equivalentes") a
`Services/ModeratorAi.php` y `Models/KpiSnapshot.php`, porque ambos aparecieron durante el rastreo
del pipeline de `RefreshWarRoomCommand` y comparten la misma pregunta del item (¿quién los invoca,
y qué hace alguien con lo que producen?).

**SOLO INVENTARIO** (mismo mandato que los precedentes): esta pasada NO borra, desconecta, corrige
ni ejecuta ningún comando de prueba. Cada veredicto es lectura para que Irving decida en una vuelta
futura.

## Método (sitios consultados)

1. `crontab -l` del usuario `meganet` — 11 líneas activas, **ninguna es `schedule:run`** (mismo
   hallazgo ya documentado en CLAUDE.md y en los precedentes #789/#792/#793/#794: en este DEV el
   schedule de Laravel no corre solo; cada job crítico se invoca uno por uno vía `cron-wrap.sh`).
2. `app/Console/Kernel.php::schedule()` — 1 línea War Room: `warroom:refresh --skip-insights`
   (`dailyAt('23:55')`, `withoutOverlapping(10)->onOneServer()`, nombrado
   `warroom:refresh-snapshot`).
3. `command_configs` (schedule dinámico en BD) — **0 filas** relacionadas con War Room (verificado
   por tinker sobre el listado completo de la tabla).
4. `app/Modules/Addons/WarRoom/ModuleServiceProvider.php` — registra `RefreshWarRoomCommand` (único
   comando del módulo) y `ActionItem::observe(ActionItemObserver::class)`; sin `Event::listen`
   adicional.
5. `grep -rn "Artisan::call\|->call("` sobre `app/` y `routes/` filtrado por "warroom" — **0
   resultados**: ningún comando/controlador dispara `warroom:refresh` programáticamente.
6. `grep` de cada clase candidata (`InsightsService`, `RefreshInsightsJob`, `SendMeetingMinutesJob`,
   `ActionItemObserver`, `ModeratorAi`, `KpiSnapshot`) sobre `app/`, `routes/` y `resources/js/`,
   para distinguir invocación real (`use`+instanciación/dispatch) de mención en comentario.
7. `app/Modules/Addons/WarRoom/routes.php` (rutas HTTP reales del módulo) + los `.vue`/composables
   de `resources/js/components/module/warroom/` (polling/llamadas desde el frontend).
8. `/etc/supervisor/conf.d/megaisp-queue.conf` — cola `default` cubierta por 2 workers
   (`--queue=cobranza,referrals,database,default`), relevante para los 2 jobs del módulo (ninguno
   declara `->onQueue()` propio).

---

## A. CONFIRMADOS — arranque real verificado (5, incluye 1 fuera del listado original del item)

| Servicio/comando | Arranque |
|---|---|
| `RefreshWarRoomCommand` (`warroom:refresh --skip-insights`) | `Kernel.php` `dailyAt('23:55')` — **condicionado a que corra `schedule:run`** (mismo matiz ya documentado para Marketing/Talento/Flotas en este DEV: la línea existe en `Kernel.php`, pero el crontab real de esta máquina no invoca `schedule:run`, así que **hoy no corre sola en dev**; si corriera en un servidor con `schedule:run` activo — el caso de PROD, ver checklist pendiente en CLAUDE.md — sí se dispararía a las 23:55). Ver Hallazgo I1 abajo sobre qué produce quien sí corre. |
| `InsightsService::generate()` | **Real y vivo, vía HTTP** — `RefreshInsightsJob::dispatch()` se llama desde `InsightsController::show()` (cache miss al abrir cualquier vista del War Room) y desde `InsightsController::regenerate()` (botón "Regenerar insights", permiso `warroom.insights.regenerate`), ambos en `routes.php:29-30`. Cola `default`, cubierta por los 2 workers de `megaisp-queue.conf`. También lo llamaría `RefreshWarRoomCommand` si corriera SIN `--skip-insights`, pero la línea de cron real usa ese flag — el camino vivo hoy es el HTTP, no el cron. |
| `ActionItemObserver::created()` | Evento Eloquent real: `ActionItem::observe()` en `ModuleServiceProvider.php:20`, disparado por `ActionItem::create()` en `ActionItemController::store()` (`routes.php:50`, POST `/warroom/api/action-items` — botón "Agregar plan de acción" en una junta en vivo). |
| `SendMeetingMinutesJob` | `dispatch()` real en `MeetingController::end()` (`MeetingController.php:217`), condicionado a `$meeting->settings['send_minutes_whatsapp'] ?? true` — se dispara automáticamente al cerrar cualquier junta (botón "Terminar junta"). Cola `default`, cubierta. |
| `ModeratorAi::getSuggestion()` (fuera del listado original del item, ver nota de alcance) | Polling real del frontend: `useMeeting.js::startSuggestionPolling()` hace `GET /warroom/api/meetings/{id}/suggestion` cada 30s mientras la junta está `in_progress` (`useMeeting.js:48-56` → `MeetingController::getSuggestion` → `routes.php:47`). No es huérfano — es el propio "ciclo" del item, solo que vive del lado del navegador en vez de un cron de servidor. |

## Hallazgo I1 — `RefreshWarRoomCommand` SÍ tiene invocador programado, pero su único efecto (`KpiSnapshot`) no lo lee nadie

La línea de cron real (`warroom:refresh --skip-insights`) hace UNA sola cosa cuando corre: escribe
`KpiSnapshot::updateOrCreate(['period' => ...], ['kpis' => $allKpis, ...])`
(`RefreshWarRoomCommand.php:64-68`). `grep -rn "KpiSnapshot"` sobre `app/`, `routes/` y
`resources/js/` da **cero resultados de lectura** — la única clase que instancia el modelo es el
propio comando que la escribe. Verificado además en `KpiController::show()`/`::raw()`
(`KpiController.php:17-36`): las 7 vistas de KPIs (`resumen`, `finanzas`, `operaciones`, `ventas`,
`red`, `marketing`, `talento`) se calculan **siempre en vivo** contra la BD, nunca contra el
snapshot. El permiso `warroom.snapshots.regenerate` (declarado en `module.json:34-37` y en el
editor de roles, `constants.js:2422`) tampoco tiene ninguna ruta ni controlador que lo verifique
(`grep` sobre `app/`: cero matches fuera del catálogo de permisos) — no existe un botón manual
equivalente al de insights.

**Esto no es el patrón "huérfano sin invocador" que el item pedía cazar — es el inverso: un
invocador real (aunque hoy dormido por el matiz de `schedule:run` en DEV) cuyo producto es letra
muerta.** Ningún dato se pierde si la tabla nunca se llena (nada la consulta), pero tampoco se gana
nada si se llena — es trabajo (una escritura diaria a las 23:55, más los `withoutOverlapping`) sin
ningún lector. Queda para que Irving decida en una vuelta futura: (a) conectar `KpiController` a
leer el snapshot cuando exista (ganar caché de KPIs históricos), (b) construir el botón manual que
el permiso `warroom.snapshots.regenerate` ya promete, o (c) retirar el paso de snapshot y el modelo
si el propósito original ya no aplica. No se decide aquí (fuera de alcance de un inventario).

## B. FALSO POSITIVO A EVITAR

Ninguno con el patrón estricto de #647 (servicio de ciclo llamado directo dentro del `handle()` de
OTRO comando, invisible para su comando gemelo). `RefreshWarRoomCommand::handle()` sí llama a
`InsightsService::generate()` y a `KpiController::raw()` directo, pero ambos tienen SU PROPIO
invocador real y documentado aparte (tabla A) — no es el caso "solo se ve corriendo escondido
adentro de otro comando".

## C. DUAL-PROPÓSITO — `InsightsService::generate()` (1)

Mismo patrón que `circuito:auditor`/`circuito:jarvis` del precedente #647: el motor real corre por
un camino (HTTP + job, en vivo, on-demand) y el comando (`warroom:refresh`, sin `--skip-insights`)
es una CLI alterna que ejecutaría el mismo método para las 7 vistas de golpe — pero la línea de cron
que sí está registrada usa `--skip-insights`, así que en la práctica ese segundo camino solo se
ejercería si alguien corre `php artisan warroom:refresh` a mano sin el flag. No es huérfano: tiene
un invocador real y vivo (tabla A), y el comando es la forma manual/batch, documentada en su propio
`--skip-insights`/`--view=` como opciones pensadas para uso ad-hoc.

## D. MANUAL POR DISEÑO

No aplica — el único comando del módulo (`warroom:refresh`) SÍ tiene cron propio en `Kernel.php`
(categoría A, con el matiz de `schedule:run`); no quedó ningún comando sin cron que se sostenga como
"manual a propósito".

## E. BACKFILLS DE UNA SOLA CORRIDA

No aplica — no existe ningún comando/servicio de backfill de una sola corrida dentro de War Room.

---

## F. HUÉRFANOS — sin arranque, con veredicto (0)

No se encontró ningún método de ciclo (`generate`/`tick`/`refresh`/tipo similar) del módulo sin
ningún invocador real. Los 5 de la tabla A cubren el universo completo hallado en el paso 6 del
método (grep de `function (tick|ciclo|drain|barrido|procesar|refresh|generate|dispatch|run)(` sobre
todo `app/Modules/Addons/WarRoom/` — 2 resultados: `InsightsService::generate` y
`ModeratorAi::generate`, ambos ya cubiertos por su invocador público (`::show`/`getSuggestion`)
documentado arriba). El único hallazgo de este barrido no es un huérfano sin invocador, sino el
inverso (I1): un invocador real cuyo producto no tiene lector.

---

## Resumen

| Categoría | Cantidad | Servicios/comandos |
|---|---|---|
| A. Confirmados (arranque real) | 5 | `RefreshWarRoomCommand` (cron, condicionado a `schedule:run`), `InsightsService::generate` (HTTP+job), `ActionItemObserver::created` (evento Eloquent), `SendMeetingMinutesJob` (dispatch al cerrar junta), `ModeratorAi::getSuggestion` (polling frontend 30s) |
| B. Falso positivo descartado | 0 | ninguno con el patrón estricto |
| C. Dual-propósito | 1 | `InsightsService::generate()` — vivo por HTTP+job; el comando (`warroom:refresh` sin `--skip-insights`) es la CLI batch alterna, hoy no usada por el cron real |
| D. Manual por diseño | 0 | no aplica |
| E. Backfill de una sola corrida | 0 | no aplica |
| F. Huérfanos con veredicto | 0 | ninguno sin invocador |
| **Hallazgo I1** | — | `RefreshWarRoomCommand` SÍ corre (condicionado a `schedule:run`) pero su único efecto, `KpiSnapshot`, no tiene NINGÚN lector (`KpiController` siempre calcula en vivo) — invocador real, producto sin consumidor; el permiso `warroom.snapshots.regenerate` tampoco tiene ruta que lo use |

FUERA DE ALCANCE DE ESTA PASADA (mismo mandato que #647/#789/#792/#793/#794): no se borró,
desconectó, corrigió ni ejecutó nada — no se corrió `warroom:refresh` de prueba, no se tocó
`KpiSnapshot` ni el permiso `warroom.snapshots.regenerate`. El hallazgo I1 queda para que Irving lo
revise en una vuelta futura; no requiere decisión urgente porque no es una regresión — es lectura de
cómo está el sistema hoy.
