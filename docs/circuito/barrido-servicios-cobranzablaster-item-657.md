# Barrido de servicios con métodos de ciclo en módulo CobranzaBlaster (item #795, sub-item de #657)

**Alcance de esta pasada:** los servicios/comandos listados en la descripción del item dentro de
`app/Modules/Addons/CobranzaBlaster/` — `AmiConnectionService`, `CobranzaTtsService`,
`BlastCampanaJob`, `ProcessCallResultJob`, `CobranzaCampanaService`, el schedule
`cobranza:blast-activas` (closure nombrado en `Kernel.php`, no un comando propio) y el daemon
`cobranza:ami-listener` (`AmiEventListenerCommand`, bajo supervisor).

**SOLO INVENTARIO** (mismo mandato que los precedentes #647/#789/#792/#793/#794): esta pasada NO
borra, desconecta, corrige ni ejecuta ningún comando de prueba/simulación. No se originó ninguna
llamada real, no se instaló ningún supervisor conf, no se tocó Asterisk/AMI. Cada veredicto es
lectura para que Irving decida en una vuelta futura.

## Método (sitios consultados)

1. `crontab -l` del usuario `meganet` — 11 líneas activas, **ninguna es `schedule:run`** (mismo
   hallazgo ya documentado en CLAUDE.md y en los precedentes: en este DEV el schedule de Laravel
   no corre solo, cada job crítico se invoca uno por uno vía `cron-wrap.sh` o líneas dedicadas).
2. `app/Console/Kernel.php::schedule()` — el bloque `CobranzaBlaster (Fase 6)` (líneas 153-157): un
   `$schedule->call(closure)` que despacha `BlastCampanaJob` por cada `CobranzaCampana::activa()`,
   `everyFiveMinutes()->name('cobranza:blast-activas')->withoutOverlapping(10)`. Es un closure
   nombrado, **no** un comando `artisan` con `$signature` propio.
3. `command_configs` (schedule dinámico en BD) — **0 filas** `LIKE '%cobranza%'` (verificado por
   tinker).
4. `app/Modules/Addons/CobranzaBlaster/ModuleServiceProvider.php` — solo registra **un** comando
   (`AmiEventListenerCommand` → `cobranza:ami-listener`); sin `Event::listen` ni listeners propios.
5. `grep -rn "Artisan::call\|->call("` sobre `app/` y `routes/` filtrado por "cobranza" — **0
   resultados**: ningún comando ni controlador dispara un comando `cobranza:*` programáticamente.
6. `grep` de cada servicio/job candidato (`AmiConnectionService`, `CobranzaTtsService`,
   `BlastCampanaJob`, `ProcessCallResultJob`, `CobranzaCampanaService`, y sus métodos públicos uno
   por uno) sobre `app/`, `resources/` y `routes/`, para distinguir invocación real
   (`use`+instanciación/`::dispatch(`) de mención en comentario o docblock.
7. `app/Modules/Addons/CobranzaBlaster/routes.php` — las 12 rutas reales bajo `/cobranza/*`
   (`Route::middleware(['web','auth'])`), cruzadas contra los métodos de `CampanaController` para
   ver cuáles wirean a qué método del servicio.
8. **Supervisor** — `/etc/supervisor/conf.d/` solo tiene `megaisp-deploy-worker.conf` y
   `megaisp-queue.conf` (grupo `megaisp-queue`, **no** existe ningún `megaisp-cobranza*.conf`).
   `ps aux` — 2 procesos `queue:work --queue=cobranza,referrals,database,default` vivos (PIDs 1798,
   1799, `www-data`), **cero** procesos `cobranza:ami-listener`. `ss -tlnp` — puerto `5038` (AMI de
   Asterisk) en escucha, pero sin nada del lado de la app conectado a él como listener persistente.
9. `stubs/supervisor-ami-listener.conf` (dentro del propio módulo) — su encabezado instruye
   copiarlo a `/etc/supervisor/conf.d/megaisp-cobranza-ami.conf` + `supervisorctl reread && update
   && start megaisp-cobranza-ami`; ese paso **nunca se aplicó** en este DEV (confirmado por el
   punto 8).
10. `.env` — `AMI_HOST=127.0.0.1`, `AMI_PORT=5038`, `AMI_USERNAME=megaisp` presentes (Asterisk vivo
    y alcanzable), pero eso no sustituye al listener: solo abre la puerta, nadie está escuchando.

---

## A. CONFIRMADOS — arranque real verificado (con 2 hallazgos relevantes, ver más abajo)

| Servicio/comando | Arranque |
|---|---|
| `AmiConnectionService::connect()/disconnect()/isConnected()/originate()/sendRaw()` | Llamados dentro de `BlastCampanaJob::handle()` (`BlastCampanaJob.php:54-99`) — conexión persistente reusada por lote, real cuando el job corre. |
| `CobranzaTtsService::generateAudio()` | Llamado dentro de `BlastCampanaJob::handle()` (`BlastCampanaJob.php:67-73`), una vez por llamada del lote. |
| `BlastCampanaJob` | Dos disparadores reales distintos: **(a)** `CobranzaCampanaService::activarCampana()` → llamado por `CampanaController::activar()` (`POST /cobranza/campanas/{id}/activar`, botón "Activar" en `/cobranza/campanas`) — dispara una ronda inmediata al activar. **(b)** el closure `cobranza:blast-activas` de `Kernel.php` (cada 5 min, condicionado a `schedule:run`, ver hallazgo G1). |
| `CobranzaCampanaService::cargarMorosos()` | Llamado dentro de `activarCampana()` (mismo disparador real (a) de arriba). |
| `CobranzaCampanaService::activarCampana()` / `pausarCampana()` | Llamados desde `CampanaController::activar()` / `CampanaController::pausar()` — botones reales "Activar"/"Pausar" de `/cobranza/campanas`. |
| `AmiConnectionService::readEvent()` | Llamado dentro de `AmiEventListenerCommand::handle()` (bucle del daemon) — invocador real, pero el daemon mismo no corre en este DEV hoy (ver hallazgo G2). |
| `ProcessCallResultJob` | **Único** disparador real: `AmiEventListenerCommand::procesarEvento()` (`::dispatch()`, `AmiEventListenerCommand.php:107`) — mismo matiz que la fila anterior (ver G2). |

### Hallazgo G1 — `cobranza:blast-activas` solo re-dispara si algo invoca `schedule:run`, y en este DEV nada lo hace

El closure `Kernel.php:153-157` está bien declarado (`everyFiveMinutes()`, `withoutOverlapping(10)`,
consulta `CobranzaCampana::activa()` y despacha `BlastCampanaJob` por cada una) — **pero es un
`$schedule->call()`, no un comando independiente**: solo se ejecuta cuando algo corre
`php artisan schedule:run`. El `crontab -l` de este DEV (punto 1 del método) confirma **cero**
líneas `schedule:run`; las 11 líneas activas son invocaciones directas de comandos puntuales
(`circuito:*`, `backup_db:process`, `circuito:reactivar-agendados`) — mismo patrón ya documentado
en CLAUDE.md para `invoice:create-proformas`/`mikrotik:sync`/etc. **Consecuencia real y distinta a
los precedentes:** una campaña activada dispara **una sola ronda** de llamadas al momento de
activarse (vía el disparador (a) de la tabla A); el re-disparo periódico que debería recoger
llamadas nuevas o reintentos con `proximo_intento_at` vencido **no ocurre solo** en este DEV hasta
que alguien invoque `schedule:run` a mano. No es un huérfano de código (el closure está bien
escrito y correctamente registrado) — es la misma brecha operativa de infraestructura que ya
afecta a otros crons del sistema en este box, aplicada aquí por primera vez a CobranzaBlaster.

### Hallazgo G2 — el daemon `cobranza:ami-listener` NO está corriendo ni instalado bajo supervisor en este DEV

El propio item pedía "confirmar que el daemon ami-listener sigue corriendo bajo supervisor y no
quedó huérfano". Verificado (puntos 8-9 del método): **no está corriendo** (`ps aux` sin el
proceso) **ni está instalado** (`/etc/supervisor/conf.d/` no tiene ningún `.conf` para él; el
`stubs/supervisor-ami-listener.conf` sigue siendo solo un stub sin aplicar, con instrucciones
explícitas de copiarlo a mano — paso que nunca se ejecutó). El puerto AMI 5038 está abierto y
Asterisk responde, pero **nadie del lado de la app está leyendo ese stream de eventos**.
**Consecuencia real:** hoy, en este DEV, si `BlastCampanaJob` origina una llamada real (requiere
además troncal Servnet configurada — ver `CLAUDE.md` "Pendientes CRÍTICOS #1"), la llamada
quedaría en estado `marcando` para siempre — ningún evento `Newstate`/`Hangup`/`DTMFEnd` real
llegaría a `ProcessCallResultJob`, porque no hay listener leyendo el socket AMI. Esto contradice el
matiz de `CLAUDE.md` (tabla "Estado al 2026-05-29": *"Queue workers | ✅ supervisor: megaisp-cobranza
x2 RUNNING"*) en dos puntos: (1) el grupo de supervisor instalado se llama `megaisp-queue`
(cubre `--queue=cobranza,referrals,database,default`), no `megaisp-cobranza` — ese nombre no existe
en `/etc/supervisor/conf.d/`; (2) esos 2 workers cubren la **cola** `cobranza` (jobs), no el
**daemon** `cobranza:ami-listener` (proceso de larga vida aparte, con su propio stub de supervisor)
— son dos piezas de infraestructura distintas y solo la primera está viva. **No es una regresión de
código** (el comando existe, está bien escrito, y la sección "Pendientes CRÍTICOS #2" de CLAUDE.md
ya lo marca como paso operativo pendiente en su frase final: *"Correr el listener bajo supervisor"*)
— es confirmación de que ese paso operativo sigue sin darse, con la implicación concreta (llamadas
que se quedarían en `marcando` sin resolución) documentada aquí para que Irving decida cuándo
instalarlo (mismo patrón que el GPS listener de Flotas: stub listo, activación pendiente de una
decisión operativa, no de código).

## B. FALSO POSITIVO A EVITAR

Ninguno con el patrón estricto de #647 (servicio de ciclo llamado directo dentro del `handle()` de
OTRO comando, invisible para su comando gemelo). El único caso de "servicio A llama a servicio B"
de este módulo (`BlastCampanaJob` usa `AmiConnectionService`+`CobranzaTtsService` vía inyección en
`handle()`) es la forma normal en que el job funciona, documentado en la tabla A.

## C. DUAL-PROPÓSITO — no aplica

No hay ningún comando `cobranza:*` que sea a la vez CLI manual y motor con arranque automático
propio y distinto (patrón `circuito:auditor`/`circuito:jarvis` del precedente #647). El único
comando del módulo (`cobranza:ami-listener`) tiene un solo modo de arranque: manual/bajo supervisor
(daemon persistente), sin gemelo por cron.

## D. MANUAL POR DISEÑO — no aplica en este módulo

CobranzaBlaster no tiene comandos de simulación/diagnóstico manual (a diferencia de Flotas
`flotas:simulate-gps`/`flotas:test-notification` etc.) — el único comando (`cobranza:ami-listener`)
es un daemon de larga vida pensado para correr siempre bajo supervisor, no una herramienta de
prueba puntual; su situación real queda documentada en el hallazgo G2, no en esta categoría.

## E. BACKFILLS DE UNA SOLA CORRIDA

No aplica — no se encontró ningún comando/servicio de backfill de una sola corrida dentro del
alcance de CobranzaBlaster.

---

## F. HUÉRFANOS — 3 confirmados, sin invocador y sin explicación declarada en código

A diferencia de los precedentes (Flotas 0, con hallazgos H1 documentados en el propio código),
aquí sí aparecen métodos sin ningún consumidor y **sin** docblock que explique la espera — código
muerto simple, no una dependencia declarada:

| Método | Evidencia de orfandad |
|---|---|
| `CobranzaCampanaService::completarCampana()` | `grep -rn "completarCampana"` sobre `app/`, `resources/`, `routes/` → **cero resultados fuera de su propia definición**. Sin ruta (`routes.php` no tiene ningún endpoint `completar`/`finalizar`), sin botón en `views/campanas/index.blade.php`. Consecuencia: el estado `completada` del enum de `CobranzaCampana` **no es alcanzable por ningún camino del sistema hoy** — solo existen transiciones a `activa` (activar) y `pausada` (pausar/creación en borrador). |
| `CobranzaCampanaService::estadisticas()` | `grep -rn "->estadisticas("` → cero llamadas. `CampanaController::data()` y `::kpis()` (los que sí alimentan la UI real) **reimplementan la misma lógica con SQL crudo** en vez de llamar a este método — duplicación donde el método "canónico" del servicio quedó sin usar. |
| `CobranzaLlamada::scopeParaReintentar()` | `grep -rn "paraReintentar"` → cero llamadas fuera de la definición. Filtra por `estado IN ('no_contesto','ocupado')` + intentos-restantes, pero `BlastCampanaJob` solo consulta `estado = 'pendiente'` (`BlastCampanaJob.php:42-48|`) — el reintento real de BUSY/NOANSWER ya funciona por otro camino (`ProcessCallResultJob::handleReintento()` regresa el estado a `'pendiente'` con `proximo_intento_at` futuro, que sí cae en la query de `BlastCampanaJob`), así que este scope quedó sin rol. Relacionado: el estado `'ocupado'` que el scope y `estadisticas()` esperan **nunca se asigna** en ningún `->update(['estado' => ...])` del módulo (grep confirmado) — solo `'no_contesto'` se llega a escribir (`ProcessCallResultJob::handleHangup()`). |

Ningún docblock ni comentario en el código explica por qué estos tres quedaron sin conectar (a
diferencia del H1 de Flotas, que sí declaraba la espera sobre el item #720) — son huérfanos simples
sin justificación registrada, no dependencias pendientes de otro item.

---

## Resumen

| Categoría | Cantidad | Servicios/comandos |
|---|---|---|
| A. Confirmados (arranque real) | 7 | ver tabla A — `AmiConnectionService`(5 métodos), `CobranzaTtsService::generateAudio`, `BlastCampanaJob`, `CobranzaCampanaService::cargarMorosos/activarCampana/pausarCampana`, `ProcessCallResultJob` |
| B. Falso positivo descartado | 0 | ninguno con el patrón estricto |
| C. Dual-propósito | 0 | no aplica en este módulo |
| D. Manual por diseño | 0 | no aplica (sin comandos de simulación/diagnóstico en este módulo) |
| E. Backfill de una sola corrida | 0 | no aplica |
| F. Huérfanos sin explicación | 3 | `CobranzaCampanaService::completarCampana()`, `CobranzaCampanaService::estadisticas()`, `CobranzaLlamada::scopeParaReintentar()` (+ el estado `'ocupado'` nunca asignado) |
| **Hallazgo G1** | — | `cobranza:blast-activas` (re-disparo cada 5 min) depende de `schedule:run`, que no corre en este DEV — una campaña activa solo dispara UNA ronda automática al activarse |
| **Hallazgo G2** | — | el daemon `cobranza:ami-listener` NO está corriendo ni instalado bajo supervisor en este DEV (solo el stub sin aplicar) — sin él, ninguna llamada real resolvería su estado final; corrige además la tabla de CLAUDE.md que documentaba el grupo de supervisor con un nombre que no coincide con el instalado |

FUERA DE ALCANCE DE ESTA PASADA (mismo mandato que los precedentes): no se originó ninguna llamada
real, no se instaló el supervisor conf del listener, no se corrió `schedule:run`, no se borró
ningún método huérfano. Los hallazgos G1, G2 y F quedan para que Irving decida en una vuelta futura
(instalar el listener bajo supervisor, decidir si el re-disparo periódico se activa a propósito o
se rediseña el disparo único, y si el código muerto de F se elimina o se termina de conectar).
