# Barrido de comandos y servicios registrados que nada invoca (item #647)

**Alcance de esta pasada:** los 46 comandos registrados en
`app/Modules/Addons/Roadmap/ModuleServiceProvider.php` (módulo Circuito CC / Torre de control) —
el mismo universo del que salen los dos casos confirmados en la descripción del item
(`circuito:re-triage`, `circuito:medir-valvula`) y el falso positivo a evitar
(`ThomasService::tick()`, hoy `JarvisService::tick()` tras el rename #650). El item mencionaba 3
módulos (Torre de control / Circuito CC / Roadmap) sin decidir cuál primero (hueco sin resolver
por Irving); se decidió empezar por éste porque es donde vive toda la evidencia del propio item.
El barrido de los ~120 comandos genéricos de negocio (`app/Console/Commands/Active|Scripts|Olts`)
queda registrado como sub-item aparte — es un cuerpo de trabajo separado y grande por sí mismo,
ya documentado en CLAUDE.md como "one-off, algunos destructivos, revisar antes de correr".

**SOLO INVENTARIO** (decisión de Irving, opción 1 de q1): esta pasada NO borra ni desconecta nada.
Cada veredicto es lectura para que Irving decida en una vuelta futura.

## Método (sitios consultados, para los 46)

1. `crontab -l` del usuario `meganet` (11 líneas activas, todas vía `cron-wrap.sh`/`vigilia-wrap.sh`).
2. `app/Console/Kernel.php::schedule()` — schedule hard-coded (una sola línea `circuito:*`:
   `circuito:reactivar-agendados`) + `command_configs` (schedule dinámico, 0 filas `circuito/jarvis/advisor`).
3. `grep -rn "Artisan::call\|->call("` sobre `app/` y `routes/` — invocaciones internas.
4. `grep` de cada nombre de comando sobre `app/`, `routes/`, `resources/js/`, `config/`, `deploy/`
   (para distinguir invocación real de mención en docblock/comentario/config-catalog).
5. `deploy/circuito/*.sh` (`vuelta.sh`, `cron-wrap.sh`, `vigilia-wrap.sh`, `npm-build.sh`) y
   `deploy/circuito/prompt-item.txt` (el prompt que arma la vuelta de cada agente ejecutor — ESTA
   MISMA vuelta corre bajo ese prompt).
6. `app/Http/Controllers/` — 0 controladores llaman `Artisan::call`; el kill switch y el flag del
   revisor se togglean escribiendo el `setting`/servicio directo desde HTTP, no por comando.
7. Servicios con métodos de ciclo (`tick`/`ciclo`/`drain`/`barrido`/`procesar`) dentro de
   `Roadmap/Services/`: solo 3 existen (`AuditorService::ciclo()`, `JarvisService::tick()`,
   `MergeRunner::drain()`) y los 3 están enganchados directo en `SchedulerCommand::handle()` —
   el caso "enganchado dentro del handle() de otro comando" que el item pide no repetir.
8. Jobs del módulo (`app/Modules/Addons/Roadmap/Jobs/`): solo 2 existen
   (`ClasificarRiesgoJob`, `ProponerOpcionesJob`), ambos con `::dispatch()` confirmado.

---

## A. CONFIRMADOS — arranque real verificado (25)

| Comando | Arranque |
|---|---|
| `circuito:brief-c` | cron `*/10 * * * *` |
| `circuito:compuertas-sonda` | cron `* * * * *` |
| `circuito:destrabe` | cron `*/4 * * * *` |
| `circuito:digest` | cron `40 6 * * *` |
| `circuito:disparo-check` | cron `* * * * *` |
| `circuito:priorizar-seguridad` | cron `20 6 * * *` **+** `Artisan::call` en `ClasificarRiesgoJob.php:52` (disparo por evento al crear item) |
| `circuito:reactivar-agendados` | cron `*/10 * * * *` **+** `Kernel.php` `dailyAt('00:05')` (doble cobertura) |
| `circuito:reap-stuck` | cron `*/2 * * * *` |
| `circuito:revisar-backlog` | cron `*/2 * * * *` |
| `circuito:scheduler` | cron `* * * * *` **+** `Artisan::call` en `DisparoCheckCommand.php:48` |
| `circuito:watchdog` | cron `*/2 * * * *` |
| `circuito:jarvis-vigilar` | cron `* * * * *` vía `deploy/circuito/vigilia-wrap.sh:23` |
| `circuito:flags` | `deploy/circuito/vuelta.sh:88` + `npm-build.sh:14` |
| `circuito:claim-next` | `deploy/circuito/vuelta.sh:318` |
| `circuito:parquear-timeout` | `deploy/circuito/vuelta.sh:239` |
| `circuito:provision-worktree` | `deploy/circuito/vuelta.sh:167` |
| `circuito:registrar-ejecucion` | `deploy/circuito/vuelta.sh:130` |
| `circuito:vivo` | `deploy/circuito/vuelta.sh` (×6, start/watch/end) |
| `circuito:destrabar-bandeja` | `Artisan::call` en `SchedulerCommand::tickDestrabe()` (throttle 5 min, beat propio `circuito_destrabe_bandeja_beat`) |
| `circuito:cabida` | `deploy/circuito/prompt-item.txt` — paso obligatorio del prompt de cada vuelta-agente |
| `circuito:rama` | `deploy/circuito/prompt-item.txt` — ídem |
| `circuito:reportar` | `deploy/circuito/prompt-item.txt` — ídem |
| `circuito:integrar` | `deploy/circuito/prompt-item.txt` — ídem |
| `circuito:consultar` | `deploy/circuito/prompt-item.txt` — ídem |
| `circuito:sub-item` | `deploy/circuito/prompt-item.txt` — ídem |

Nota sobre la fila 23/24 (`cabida`…`sub-item`): el arranque no es un cron ni un `Artisan::call`
programático, es un prompt fijo que `vuelta.sh` le da a cada agente y que el agente ejecuta como
parte de su tarea (así corrió esta misma auditoría). Es un arranque real y verificable — está en
`prompt-item.txt`, no es intuido — pero merece constar aparte porque su forma es distinta a las
demás filas de esta tabla.

## B. FALSO POSITIVO A EVITAR — confirmado NO huérfano

**`JarvisService::tick()`** (ex `ThomasService::tick()`, rename #650) — invocado DIRECTO (no vía
comando) en `SchedulerCommand.php:86`, dentro del `handle()` del scheduler, cada minuto. El comando
gemelo `circuito:jarvis` existe aparte (ver sección C) pero el motor real no depende de él. Sella su
propio latido vía `SupervisorService::estado()` (no vía el listener genérico de
`CommandStarting`/`CommandFinished`, que solo se dispara si el COMANDO artisan corre). **No aparece
en la lista de huérfanos**, como exige el criterio de aceptación del item.

## C. DUAL-PROPÓSITO — el motor real corre por llamada directa a servicio/job; el comando es una CLI manual aparte (5, NO huérfanos)

Éste es el patrón que el item advierte que hay que saber distinguir de un huérfano real: "el
enganche dentro del `handle()` de otro comando es el caso que más se escapa". Los 3 servicios de
ciclo del módulo (`AuditorService::ciclo()`, `JarvisService::tick()`, `MergeRunner::drain()`) se
llaman así, y sus comandos gemelos SÍ tienen arranque real propio distinto (diagnóstico/manual):

| Comando | Motor real (arranque confirmado) | Rol del comando propio |
|---|---|---|
| `circuito:auditor` | `AuditorService::ciclo()` llamado directo en `SchedulerCommand.php:106-114` (gateado por `debeCorrer()`, autosella su propio beat `circuito_auditor_ultima_corrida` en `AuditorService.php:1363`, NO depende del listener genérico) | CLI manual: `--dry`/`--dod`/`--detalle`/`--modulo` — "para poder AUDITAR el motor sin encenderlo" (docblock propio) |
| `circuito:jarvis` | `JarvisService::tick()` — ver sección B | CLI manual: `--dry`/`--diagnostico`/`--politica` |
| `circuito:autopilot` | `AutopilotService::intentar()` llamado directo en `RevisorService.php:942`, disparado por cada brief (`aplicarPreguntas`) | CLI manual/backfill: `--dry`/`--id`, para items con brief previo al contrato `confianza`/`reversible` (docblock propio lo declara: "el camino normal es automático… este comando existe para 3 cosas" de catch-up) |
| `circuito:proponer-opciones` | `ProponerOpcionesJob` (`Jobs/ProponerOpcionesJob.php`), `::dispatch()` confirmado en `RevisorService.php:328` cuando un item C llega sin opciones | CLI manual/backfill idempotente para rezagados (`--rebrief`/`--todos`) |
| `circuito:merge-run` | `MergeRunner::drain()` llamado directo en `SchedulerCommand.php:60`, cada minuto | CLI manual on-demand — **con un matiz: su propio docblock está DESACTUALIZADO** (ver hallazgo D1 abajo) |

### Hallazgo D1 — docblock de `circuito:merge-run` describe un invocador que ya no existe

`MergeRunCommand.php` dice en su docblock: *"Lo llama el picker on-box (`circuito:disparo-check`)
cada pocos segundos"*. Falso hoy: `DisparoCheckCommand::handle()` (leído completo) SOLO llama
`Artisan::call('circuito:scheduler')` — nunca a `circuito:merge-run`. El propio docblock de
`DisparoCheckCommand` lo confirma: *"Lo único que hace es INVOCAR a `circuito:scheduler`… este
comando no lanza vueltas ni reclama items"*. El drenado de merges se movió a `SchedulerCommand`
directo (comentario `#432 B1`: "el scheduler es el ÚNICO despachador"), y el docblock de
`circuito:merge-run` nunca se actualizó para reflejarlo. No es código muerto (el drenado SÍ corre,
cada minuto, vía la llamada directa) — es **deriva de documentación**: alguien leyendo solo ese
docblock concluiría un invocador que no existe. Vale una corrección de una línea en un item aparte
(fuera de alcance de este barrido, que es solo inventario).

## D. MANUAL POR DISEÑO — sin invocador automático, y no debería tenerlo (7)

Verificado en los 4 sitios (crontab, Kernel, `Artisan::call`/`->call(`, deploy/*.sh): cero arranque
en los 4. Cada uno declara en su propio docblock por qué es correcto que sea así:

| Comando | Por qué es manual a propósito |
|---|---|
| `circuito:pausar` | Freno de mano **desde consola, con la BD caída** (#170) — escribe un centinela en archivo, a propósito fuera de cualquier automatismo. El botón de la Torre pausa por el servicio directo (`RoadmapController.php:1186`), no por este comando; éste es el respaldo de emergencia. |
| `circuito:reanudar` | Contraparte de arriba — libera el centinela con sus propias condiciones de salud. Mismo patrón. |
| `circuito:revisor` | Toggle on/off/status del flag `circuito_revisor` — "Es de Irving" (docblock), "el EJECUTOR NO debe tocarlo". Control humano por diseño. |
| `circuito:inventario-spec` | READ-ONLY, Fase 2B Paso 0 — referenciado como paso de verificación manual en los docblocks de `AuditorService.php` (líneas 382/423). |
| `jarvis:iconos-importar` | Decisión explícita de Irving (2026-08-27): set de iconos FIJO, sin subida desde el panel — "menos superficie que asegurar". |
| `advisor:cobranza` | "corrida MANUAL bajo demanda (sin agenda/scheduler todavía — así lo pidió Irving)" — docblock propio, item #344 piloto. Su "todavía" es una nota a favor de revisar en el futuro, no una urgencia. |
| `circuito:rebrief-bandeja` | Backfill de un solo lote (#507) para briefs escritos ANTES del contrato `confianza`/`reversible` — dormido a propósito una vez cerrado el rezago. |

## E. BACKFILLS DE UNA SOLA CORRIDA — dormidos a propósito (3)

Mismo patrón que `circuito:rebrief-bandeja` arriba, pero sin ninguna mención fuera de su propio
archivo (ni siquiera en un docblock ajeno):

| Comando | Qué backfillea | Evidencia de que ya cumplió o de que sigue pendiente de una corrida |
|---|---|---|
| `roadmap:backfill-reporte-coloquial` | `reporte_coloquial` vacío (#427) | Idempotente, dry-run inexistente (siempre escribe lo que falta) — se queda en no-op si ya no hay vacíos. |
| `circuito:backfill-enlace-revision` | `enlace_revision` vacío en items completados (#1006) | `--apply` explícito, dry-run default. |
| `circuito:backfill-bloqueos` | `origen_bloqueo` (separa freno humano del aviso del clasificador, Fase 2A.3) | Única mención fuera de su archivo: comentario histórico en la migración `2026_08_18_150000_limpia_rotulos_del_titulo.php:15` ("los 33 quedan sellados `humano`") — ya corrió sobre el lote conocido. |

---

## F. HUÉRFANOS — sin arranque, con veredicto (7)

### F1. `circuito:re-triage` (`RetriageFrenosCommand`) — confirmado en la descripción del item, verificado independiente

- **Sitios buscados:** crontab (0), `Kernel.php` (0), `Artisan::call`/`->call(` en todo `app/` (0),
  `deploy/circuito/*.sh` (0). Únicas menciones: comentario en `RoadmapItem.php:503`, comentario en
  la migración `2026_08_18_140000_*`, y `DigestCommand.php:144`, que usa el método ESTÁTICO
  `frenosHumanos()` de la misma clase para CONTAR — no ejecuta el comando.
- **Veredicto: FALTA CONECTAR o retirar el semáforo.** La Torre le pinta un semáforo (motor
  registrado en `config/circuito.php`) que nunca se enciende porque nada lo dispara. Decisión de
  Irving: agregarle cron propio, o quitarle el semáforo si ya no aplica.

### F2. `circuito:medir-valvula` (`MedirValvulaContextoCommand`, #902) — confirmado en la descripción del item, verificado independiente

- **Sitios buscados:** los mismos 4 que F1, más `grep` de "MedirValvula" en `app/`. Cero
  invocadores fuera de su propio registro.
- **Veredicto: FALTA CONECTAR.** El propio item ya midió la consecuencia concreta: su ventana de
  datos empieza el 2026-08-25 16:50:53 y deja fuera el caso #191 (sellado a las 12:14 del mismo
  día) — un medidor sin cron mide tarde, con el hueco justo donde importaba.

### F3. `circuito:coherencia-pool` — candidato fuerte a FALTA CONECTAR

- **Sitios buscados:** crontab (0), `Kernel.php` (0), `Artisan::call` (0), único match fuera de su
  archivo es un comentario en `RoadmapItem.php:890`.
- **Evidencia de que el propio autor lo diseñó para cron y se quedó sin conectar:** el docblock del
  comando dice textual: *"Contrato = exit code (igual que `circuito:consultar`): 0 coinciden, 1
  divergen. Así se puede colgar del scheduler o de un cron sin leer la salida."* — describe la
  forma de conectarlo y nunca se hizo.
- **Veredicto: FALTA CONECTAR.** Es READ-ONLY (compara dos conjuntos de elegibilidad de items, sin
  escribir), así que conectarlo no toca frontera dura — pero eso lo decide Irving en un item aparte.

### F4. `circuito:verificar-solo-lectura` — candidato a FALTA CONECTAR (con una ironía propia)

- **Sitios buscados:** los mismos 4, cero resultados fuera de su registro.
- El propio docblock del comando es sobre EXACTAMENTE el patrón que este item #647 investiga:
  *"un camino que nadie ejecuta se rompe en silencio"* — fue creado para ejercer el modo
  solo-lectura de la Torre porque nadie más lo ejercía. Pero el comando mismo solo lo ejerce un
  humano que se acuerde de correrlo — el mismo problema, un nivel más adentro.
- **Veredicto: FALTA CONECTAR** (cron de baja frecuencia, p.ej. semanal — hace `ROLLBACK` de todo
  lo que crea, es seguro por diseño) **o, como mínimo, documentar que es un chequeo manual y no un
  vigía**. Irving decide.

### F5. `circuito:retriar-bandeja` (#566) — candidato a FALTA CONECTAR

- **Sitios buscados:** los mismos 4, cero resultados fuera de su registro.
- Es hermano directo, del mismo item #566, de `circuito:destrabar-bandeja` — y ESE sí quedó
  enganchado en `SchedulerCommand::tickDestrabe()` (ver sección A). `circuito:retriar-bandeja`
  ("re-triaje de la bandeja con el carril mecánico") se quedó sin el mismo enganche.
- **Veredicto: FALTA CONECTAR (probable) o SOBRA si `destrabar-bandeja` ya cubrió lo que éste
  hacía** — no se puede distinguir sin que Irving diga si ambos siguen siendo necesarios o si uno
  reemplazó al otro. Marcado para su revisión, no se decide aquí (fuera de alcance de un
  inventario).

### F6. `circuito:clasificar-modulo` (#566) — AMBIGUO, marcado para revisión

- **Sitios buscados:** los mismos 4 (cero) + se verificó que NO es lo mismo que
  `JarvisService::clasificarModulo()` (método distinto, SÍ invocado en vivo desde
  `RoadmapController.php:2564` al crear un item nuevo).
- Este comando es el backfill RETROACTIVO para items que quedaron "Sin clasificar" pese a la
  clasificación automática al crear. Su propio docblock dice que corre "SOLO y bloquea las 6
  terminales" mientras dura — diseño a propósito para no correr en paralelo con nada más, lo cual
  es un argumento en contra de un cron apretado.
- **Veredicto: sin arranque, posible acumulación silenciosa de items "Sin clasificar" si nadie lo
  corre a mano.** No hay evidencia de que deba tener cron (a diferencia de F3/F4, no dice
  "cuélgame del scheduler" en su propio texto) — pero tampoco hay evidencia de que alguien lo
  corra periódicamente. Irving decide si merece una cadencia baja (p.ej. semanal, fuera de horas
  pico) o si sigue siendo puramente manual.

### F7. `circuito:revisar` (`RevisarItemCommand`, ítem único B) — probable SOBRA (superado)

- **Sitios buscados:** los mismos 4 (cero) + búsqueda específica del marcador
  `REVISOR_VEREDICTO=` (que el propio comando imprime como contrato machine-readable) en TODO el
  repo — cero consumidores.
- El docblock propio dice *"Lo llama el ejecutor on-box cuando decide un B"* — pero se leyó
  completo `RevisarBacklogCommand::handle()` (el hermano que SÍ corre por cron cada 2 min) y
  llama a `RevisorService` DIRECTO, en lote, sin pasar por este comando de-a-uno. El invocador
  que el docblock describe no existe en el pipeline actual.
- **Veredicto: código de una fase anterior, aparentemente reemplazado por `circuito:revisar-backlog`
  (que cubre el mismo trabajo en lote).** No es un gap — es más probable que sea residuo de un
  diseño de-a-uno que se sustituyó por el de lote y nadie retiró el comando viejo ni corrigió su
  docblock. Sin evidencia de consumo externo real (nada parsea su salida), es un candidato de
  borrado en un item aparte — no se borra aquí (fuera de alcance).

---

## Resumen

| Categoría | Cantidad | Comandos |
|---|---|---|
| A. Confirmados (arranque real) | 25 | ver tabla A |
| B. Falso positivo descartado | — | `JarvisService::tick()` |
| C. Dual-propósito (motor directo + CLI manual, no huérfano) | 5 | `circuito:auditor`, `circuito:jarvis`, `circuito:autopilot`, `circuito:proponer-opciones`, `circuito:merge-run` (+ docblock desactualizado, hallazgo D1) |
| D. Manual por diseño (intencional, correcto así) | 7 | `circuito:pausar`, `circuito:reanudar`, `circuito:revisor`, `circuito:inventario-spec`, `jarvis:iconos-importar`, `advisor:cobranza`, `circuito:rebrief-bandeja` |
| E. Backfill de una sola corrida (dormido a propósito) | 3 | `roadmap:backfill-reporte-coloquial`, `circuito:backfill-enlace-revision`, `circuito:backfill-bloqueos` |
| F. Huérfanos con veredicto | 7 | `circuito:re-triage` (falta conectar), `circuito:medir-valvula` (falta conectar), `circuito:coherencia-pool` (falta conectar), `circuito:verificar-solo-lectura` (falta conectar), `circuito:retriar-bandeja` (falta conectar probable), `circuito:clasificar-modulo` (ambiguo), `circuito:revisar` (sobra probable) |
| **Total comandos del módulo** | **46** | — |

FUERA DE ALCANCE DE ESTA PASADA (según el propio item): no se borró ni desconectó nada; los 4
"falta conectar" y los 2 "sobra/ambiguo" quedan para que Irving decida item por item (conectar,
archivar o borrar es la Fase 2 del propio q3 de este item, aún sin resolver).

## Pendiente registrado como sub-item

El barrido de los ~120 comandos genéricos de negocio (`app/Console/Commands/Active|Scripts|Olts`)
y de los servicios/jobs del resto de módulos (Torre de control como frontend, Marketing, Talento,
Flotas, CobranzaBlaster) es un cuerpo de trabajo separado y mucho más grande — no cabe en esta
vuelta y merece su propia metodología (esos comandos ya tienen precedente documentado en CLAUDE.md
como "revisar antes de correr", con varios explícitamente destructivos). Registrado como sub-item
del #647.
