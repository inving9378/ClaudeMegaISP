# Barrido de servicios con métodos de ciclo en módulo Flotas (item #794, sub-item de #657)

**Alcance de esta pasada:** los servicios/comandos listados en la descripción del item dentro de
`app/Modules/Addons/Flotas/` — `GpsDriverInterface`/`DeviceFactory`, `FleetPositionService`,
`GeofenceDetectionService`, `FleetNotificationDispatcher`, `RuleEvaluator`, y los 8 comandos
`flotas:*` registrados en `ModuleServiceProvider`. Se amplió además (mismo criterio que los
precedentes #647/#789/#792/#793: "y equivalentes") a los métodos con semántica de barrido/ciclo
del resto de `Services/` del módulo que el texto del item no nombró (`FleetSubscriptionService::
runDailyMaintenance()`, `DocumentAlertDispatcher`, `SubscriptionAlertDispatcher`,
`FleetDocumentOcrService`, `FlotasProrrateoService`), para no dejar huecos de cobertura dentro del
mismo árbol.

**SOLO INVENTARIO** (mismo mandato que los precedentes): esta pasada NO borra, desconecta, corrige
ni ejecuta ningún comando de prueba/simulación. No se instaló el systemd del GPS listener, no se
abrió el puerto 5027, no se disparó ninguna notificación real. Cada veredicto es lectura para que
Irving decida en una vuelta futura.

## Método (sitios consultados)

1. `crontab -l` del usuario `meganet` — 11 líneas activas, **ninguna es `schedule:run`** (mismo
   hallazgo ya documentado en CLAUDE.md y en los precedentes #789/#792: en este DEV el schedule de
   Laravel no corre solo, cada job crítico se invoca uno por uno).
2. `app/Console/Kernel.php::schedule()` — 2 líneas Flotas: `flotas:check-subscriptions`
   (`dailyAt('07:00')`) y `flotas:check-document-expirations` (`dailyAt('08:00')`), ambas
   `withoutOverlapping()->onOneServer()`.
3. `command_configs` (schedule dinámico en BD) — **0 filas** `LIKE '%flota%'` (verificado por
   tinker).
4. `app/Modules/Addons/Flotas/ModuleServiceProvider.php` — los 8 comandos registrados
   (`$this->commands([...])`); **sin** `Event::listen` ni listeners propios del módulo.
5. `grep -rn "Artisan::call\|->call("` sobre `app/` y `routes/` filtrado por "flota" — **0
   resultados**: ningún comando ni controlador dispara un comando `flotas:*` programáticamente.
6. `grep` de cada servicio candidato (`FleetPositionService`, `GeofenceDetectionService`,
   `FleetNotificationDispatcher`, `RuleEvaluator`, `DeviceFactory`, `GpsDriverInterface`,
   `SendGeofenceNotificationsJob`, `DocumentAlertDispatcher`, `SubscriptionAlertDispatcher`,
   `FleetDocumentOcrService`, `FlotasProrrateoService`, `FleetSubscriptionService`) sobre `app/` y
   `routes/`, para distinguir invocación real (`use`+instanciación) de mención en comentario.
7. **systemd del GPS listener** — en este box de dev no hay bus de systemd accesible
   (`systemctl`: *"Failed to connect to bus"*, entorno contenedor/worktree); se verificó por disco:
   `/etc/systemd/system/*.service` **no tiene ninguna unit de GPS** (ni `megaisp-gps-listener`), el
   puerto 5027 **no está en escucha** (`ss -tlnp`) y **no hay proceso** `flotas:gps-listen` corriendo
   (`ps aux`). `deploy/README-gps-listener.md` lo confirma en su propio encabezado: *"NO instalar
   hasta que se vaya a apuntar un GPS real al servidor. El puerto 5027 está cerrado... mientras
   tanto el listener no es necesario."*
8. `/etc/supervisor/conf.d/*.conf` — solo `megaisp-deploy-worker.conf` (cola `deploy`) y
   `megaisp-queue.conf` (`--queue=cobranza,referrals,database,default`). Sin cola dedicada a Flotas
   — verificado abajo que no hace falta (los jobs del módulo usan la cola `default`, que sí está
   cubierta).
9. `module.json` del addon — solo declara permisos y menú; sin mención de cron/schedule.

---

## A. CONFIRMADOS — arranque real verificado (con un hallazgo relevante, ver más abajo)

| Servicio/comando | Arranque |
|---|---|
| `flotas:check-subscriptions` → `FleetSubscriptionService::runDailyMaintenance()` | `Kernel.php` `dailyAt('07:00')` (condicionado a que corra `schedule:run`, ver punto 1 — mismo matiz ya documentado para los cron de Marketing/Talento en este DEV) |
| `flotas:check-document-expirations` → `DocumentAlertDispatcher::dispatch()` | `Kernel.php` `dailyAt('08:00')` (mismo matiz) |
| `FleetPositionService::saveBatch()` | **`POST` real** `ConductorApiController::reportarPosicion` (`app/Modules/Addons/MegaFamilia/Controllers/ConductorApiController.php:213-247`, ruta `megafamilia/conductor/.../posicion`) — el celular del conductor en la app MegaFamilia reporta su posición y esto llama `saveBatch` directo, en cada reporte. Además invocado manualmente por `flotas:simulate-gps` (categoría D) y quedaría cubierto también por `flotas:gps-listen` cuando se instale (categoría D, ver abajo). |
| `GeofenceDetectionService::processBatch()` | Llamado **directo dentro de** `FleetPositionService::saveBatch()` (`FleetPositionService.php:59`, try/catch, post-commit) — se dispara automáticamente con cualquier posición real guardada (ver fila anterior). También invocable manualmente vía `flotas:reprocess-geofences` (categoría D). |
| `SendGeofenceNotificationsJob` | `::dispatch()` en `GeofenceDetectionService.php:122`, dentro de `processBatch()`, cuando se crean eventos nuevos. Cola `default` (sin `->onQueue()` explícito) → **cubierta** por `megaisp-queue.conf` (`--queue=cobranza,referrals,database,default`). |
| `FleetNotificationDispatcher::dispatch()` | Llamado dentro de `SendGeofenceNotificationsJob::handle()` (real, automático vía el job de arriba). También manual vía `flotas:test-notification` (D) y `FleetNotificationController::resend` (botón de reintento en `/flotas/notificaciones-log`). |
| `RuleEvaluator::evaluate()` | Llamado dentro de `FleetNotificationDispatcher::dispatch()` (`FleetNotificationDispatcher.php:52`) — automático con cualquier notificación real despachada. También manual vía `flotas:test-rule` (D). |
| `FleetDocumentOcrService::extract()` | `POST /api/vehiculos/documentos/ocr` real (`FleetDocumentController::ocr`, botón de "leer documento con IA" al subir un documento) — acción manual **por diseño** de un humano al cargar un archivo, no un ciclo, pero SÍ tiene invocador real (no huérfano). |

### Hallazgo G1 — el pipeline GPS→geocercas→notificaciones→reglas SÍ está wireado de punta a punta, pero NO por el camino que sugiere la descripción del item

La descripción de este item asume que `FleetPositionService`/`GeofenceDetectionService`/
`FleetNotificationDispatcher`/`RuleEvaluator` "deberían tener invocador automático", dando a
entender que hoy no lo tienen porque el único productor de posiciones sería el TCP listener
(`flotas:gps-listen`, que en efecto NO corre — puerto cerrado, sin systemd instalado, confirmado en
el punto 7 del método). Verificado el código completo: **sí tienen invocador automático real, hoy,
en este DEV** — el puente `ConductorApiController::reportarPosicion` de MegaFamilia (celular del
conductor reportando posición) alimenta `FleetPositionService::saveBatch()`, que en cascada dispara
`GeofenceDetectionService` → `SendGeofenceNotificationsJob` (cola `default`, con worker vivo) →
`FleetNotificationDispatcher` → `RuleEvaluator`. Los 5 servicios completan su ciclo real cada vez
que un conductor de MegaFamilia reporta su posición, sin que nadie tenga que instalar el listener
GPS físico. **Esto NO es un huérfano** — es un caso donde la premisa del item ("debería tener
invocador") ya se resolvió, solo que por un camino distinto al que el texto describía. La
instalación de `flotas:gps-listen` (cuando Irving apunte un GPS Ruptela real) sería un **segundo**
productor de posiciones sobre la misma tubería ya viva, no el primero.

## B. FALSO POSITIVO A EVITAR

Ninguno con el patrón estricto de #647 (servicio de ciclo llamado directo dentro del `handle()` de
OTRO comando, invisible para su comando gemelo). Los casos de "servicio A llama a servicio B
directo" de este módulo (`FleetPositionService`→`GeofenceDetectionService`,
`FleetNotificationDispatcher`→`RuleEvaluator`) ya están documentados en la sección A/hallazgo G1 —
son la forma normal en que este pipeline funciona, no una trampa de invocador oculto.

## C. DUAL-PROPÓSITO — no aplica

No se encontró ningún comando `flotas:*` que sea la CLI manual de un motor que además corre por
cron/evento con arranque **propio y distinto** (patrón de `circuito:auditor`/`circuito:jarvis` del
precedente #647). Los 8 comandos del módulo, o son la única forma de invocar su servicio (ver
tabla A: `check-subscriptions`, `check-document-expirations`) o son puramente de prueba/manual (ver
categoría D).

## D. MANUAL POR DISEÑO — sin invocador automático, y no debería tenerlo (6)

Confirmado en los sitios del método (crontab, Kernel, `Artisan::call`/`->call(`, deploy/systemd):
cero arranque automático en los 6. El propio item ya advertía de varios de estos ("no confundirlos
con huérfanos, son manuales por diseño") — se documenta la razón concreta de cada uno:

| Comando | Por qué es manual a propósito |
|---|---|
| `flotas:gps-listen` | Docblock propio: *"NO se ejecuta en automático (puerto 5027 cerrado). Se activa vía systemd cuando Irving apunte el GPS real"*. `deploy/README-gps-listener.md` lo repite como advertencia de cabecera. Confirmado en disco: sin unit systemd instalada, puerto cerrado, sin proceso corriendo (punto 7 del método). Cuando se instale, se vuelve un SEGUNDO productor sobre la tubería que ya vive por el puente MegaFamilia (hallazgo G1) — no el primero. |
| `flotas:simulate-gps` | Genera posiciones con `MockDriver` para pruebas/demos — mismo patrón que `flotas:simulate-gps` en el precedente de Fase 2 de este mismo módulo (CLAUDE.md ya lo documenta como herramienta de simulación). |
| `flotas:simulate-ruptela` | Simula un dispositivo Ruptela hablando TCP contra `flotas:gps-listen` para validar el pipeline completo **sin hardware físico** — herramienta de prueba explícita del propio docblock ("Coexiste con flotas:simulate-gps... este habla TCP"). |
| `flotas:test-notification` | Crea un evento sintético y despacha notificaciones reales (email+whatsapp) para probar el canal — herramienta de diagnóstico manual, no un ciclo. |
| `flotas:test-rule` | Evalúa las reglas del Sub-fase 3.4 contra un evento sintético (con `--dispatch` opcional) — mismo patrón de diagnóstico manual. |
| `flotas:reprocess-geofences` | Reprocesa posiciones ya guardadas para un vehículo en una ventana de horas — herramienta operativa on-demand (backfill re-ejecutable, no de una sola corrida), para cuando se ajustan geocercas o se detecta un hueco puntual. No tiene ni debería tener cron: es una acción correctiva bajo demanda de un operador. |

`GpsDriverInterface`/`DeviceFactory`/`RuptelaDriver` quedan atados al mismo veredicto D que
`flotas:gps-listen`: su único invocador real posible es ese comando (`DeviceFactory::fromHandshake`
en `GpsListenCommand.php:77`), que hoy no corre por las mismas razones documentadas arriba. `MockDriver`
tiene invocador real via `flotas:simulate-gps` (también D). No son huérfanos sin explicar — están
en la misma espera declarada que su único comando.

## E. BACKFILLS DE UNA SOLA CORRIDA

No aplica en este universo — no se encontró ningún comando/servicio de backfill de una sola corrida
dentro del alcance de Flotas (a diferencia de `Active/`, que sí tenía 3 en el precedente #647).

---

## F. HUÉRFANOS — sin arranque, con veredicto (0 huérfanos "puros"; 1 caso con veredicto propio, ver H1)

No se encontró ningún servicio de ciclo sin invocador Y sin explicación documentada de por qué —
el único candidato real (`FlotasProrrateoService`) tiene su propia razón declarada en el código, se
documenta aparte como hallazgo (H1) en vez de forzarlo dentro de la tabla F, porque su ausencia de
cableado no es un gap sin explicar: es una espera declarada sobre un item concreto todavía sin
mergear.

## Hallazgo adicional H1 — `FlotasProrrateoService::calcularLineas()` sin ningún invocador, bloqueado a propósito por el item #720

`grep -rn "FlotasProrrateoService"` sobre `app/` y `routes/` da **cero resultados fuera de su propio
archivo** (`Services/Billing/FlotasProrrateoService.php`) — ni comando, ni controller, ni job lo
llama. A diferencia de un huérfano sin explicar, su propio docblock (líneas 10-28) lo declara texto
explícito: *"Standalone a propósito: todavía NO se engancha a `ClientRepository::
calculateAmounts()`/`resolveFleetSubscriptionLines()`... Esa base la introduce el item #720 (branch
aprobada por Irving, nivel C, pendiente de su merge manual vía la Torre) y todavía no está en main.
Enganchar aquí antes de que esa base exista duplicaría/pelearía ese cambio... El cableado queda
registrado como sub-item de seguimiento para correrse en cuanto #720 aterrice en main."*
**Veredicto: no es un huérfano ni un gap sin explicar — es una dependencia externa declarada
(#720) todavía sin resolver.** No se investigó en esta pasada (fuera de alcance de un inventario)
si el sub-item de seguimiento que el propio comentario promete ya existe en la Hoja de Ruta o si
falta crearlo — eso lo decide Irving.

---

## Resumen

| Categoría | Cantidad | Servicios/comandos |
|---|---|---|
| A. Confirmados (arranque real) | 8 | ver tabla A — incluye el pipeline completo GPS→geocercas→notif→reglas (hallazgo G1) |
| B. Falso positivo descartado | 0 | ninguno con el patrón estricto |
| C. Dual-propósito | 0 | no aplica en este módulo |
| D. Manual por diseño (intencional, correcto así) | 6 | `flotas:gps-listen`, `flotas:simulate-gps`, `flotas:simulate-ruptela`, `flotas:test-notification`, `flotas:test-rule`, `flotas:reprocess-geofences` (+ `GpsDriverInterface`/`DeviceFactory`/`RuptelaDriver`/`MockDriver` atados al mismo veredicto) |
| E. Backfill de una sola corrida | 0 | no aplica |
| F. Huérfanos con veredicto | 0 | ninguno sin explicación documentada |
| **Hallazgo G1** | — | el pipeline GPS→geocercas→notificaciones→reglas SÍ tiene arranque real automático, vía el puente de MegaFamilia (celular del conductor), no vía el TCP listener que la descripción del item asumía como único productor |
| **Hallazgo H1** | — | `FlotasProrrateoService::calcularLineas()` sin invocador, bloqueado a propósito por el item #720 (dependencia declarada en su propio docblock) |

FUERA DE ALCANCE DE ESTA PASADA (mismo mandato que #647/#789/#792/#793): no se borró, desconectó,
corrigió ni ejecutó nada — no se instaló el systemd del GPS listener ni se disparó ninguna
notificación real. El hallazgo G1 (la tubería ya está viva por un camino distinto al esperado) y el
H1 (`FlotasProrrateoService` bloqueado por #720) quedan para que Irving los revise en una vuelta
futura; ninguno de los dos requiere una decisión urgente porque ninguno es una regresión — son
lectura de cómo está el sistema hoy.
