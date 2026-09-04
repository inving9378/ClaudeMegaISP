# Barrido de servicios con métodos de ciclo en módulo Talento (item #793, sub-item de #657)

**Nota sobre el hueco de spec detectado por el revisor** ("el alcance abarca 2 módulos: Talento,
Roadmap / Circuito CC — ¿cuál primero?"): es una falsa alarma de clasificación, no una ambigüedad
real. El campo `modulo` del item es metadata administrativa heredada de su padre #657 (el propio
paraguas de barridos vive bajo la categoría "Roadmap / Circuito CC" del sistema de items), **no**
un segundo cuerpo de trabajo. El texto completo de la descripción — título, cuerpo, prompt — solo
menciona rutas y servicios dentro de `app/Modules/Addons/Talento/`; no hay una sola mención a tocar
código del Circuito CC. Se procedió íntegro sobre Talento, sin dividir en fases.

**Alcance de esta pasada:** servicios con métodos de ciclo (tick/ciclo/drain/barrido/procesar/
liquidar/calcular periódico, y equivalentes) dentro de `app/Modules/Addons/Talento/` — mismo
criterio amplio que los barridos precedentes #647/#789/#792. Foco explícito del item en el motor
de compensación (`LiquidationService`/`HealthBonusService`/`ProjectBonusService`, `PayWeek`),
ya documentado en CLAUDE.md como disparado manualmente vía UI/controller.

**SOLO INVENTARIO** (mismo mandato que los precedentes): esta pasada NO ejecutó ninguna
liquidación, no tocó dinero/nómina, no borró ni desconectó nada. Todo el barrido fue lectura de
código (`grep`/`find`/lectura de archivos), `crontab -l`, `Schema`/consultas de solo-lectura por
tinker (conteos, existencia de filas) y `ls /etc/supervisor/conf.d/`.

## Método (sitios consultados)

1. `crontab -l` del usuario `meganet` — confirmado: **sin línea `schedule:run`** en este box (mismo
   hallazgo que los tres precedentes; el propio crontab lo documenta explícito en sus comentarios).
2. `app/Console/Kernel.php::schedule()` — **1 sola línea** de Talento:
   `talento:check-credential-expirations` (`dailyAt('07:00')`).
3. `command_configs` (schedule dinámico en BD) — **0 filas** con `process_name LIKE '%talento%'` o
   `'%colaborador%'` (verificado por tinker).
4. Listado completo de métodos públicos de cada archivo en `app/Modules/Addons/Talento/Services/`
   (26 archivos) — no solo grep de keywords, para no perder métodos de ciclo mal nombrados.
5. `grep -rn` de cada método/comando candidato sobre `app/`, `routes/`, `resources/js/`, `deploy/`
   para distinguir invocación real de mención en comentario/docblock.
6. Los 2 comandos propios del módulo (`app/Modules/Addons/Talento/Console/`):
   `CheckCredentialExpirationsCommand` y `SyncColaboradoresCommand` — registrados ambos en
   `ModuleServiceProvider::boot()`, se verificó su arranque real (Kernel/cron/`Artisan::call`/UI)
   por separado.
7. `grep -rn "::dispatch\|Bus::dispatch"` sobre todo el módulo — **0 resultados**: Talento no tiene
   carpeta `Jobs/` ni despacha nada a cola; todo corre síncrono dentro de request HTTP o comando
   artisan. Confirmado también contra `/etc/supervisor/conf.d/` (solo 2 workers activos en este
   box, `megaisp-deploy-worker` y `megaisp-queue-worker-{1,2}` con colas
   `cobranza,referrals,database,default` — ninguna cola propia de Talento).
8. Conteos de solo-lectura por tinker para dimensionar 2 hallazgos (ver F1 y F2 abajo): filas
   `talento_installation_surveys` pendientes de auto-cierre, y usuarios con rol de puesto sin fila
   `talento_colaboradores` correspondiente.

---

## A. CONFIRMADOS — arranque real verificado (1 comando + varios servicios event-driven)

| Servicio/comando | Arranque |
|---|---|
| `talento:check-credential-expirations` (`CheckCredentialExpirationsCommand`) | `Kernel.php` `dailyAt('07:00')` — actualiza estado de licencias/credenciales próximas a vencer y abre `TalentoFund` de renovación automáticamente. Mismo matiz que los precedentes: es "arranque real verificado en código", condicionado a que `schedule:run` corra (en este DEV no corre — punto 1 del método). |
| `HealthBonusService::evaluate()` / `::evaluateTask()` | Llamado directo dentro de `OrdenTrabajoUnifiedService::validarAdmin()` (líneas 943/963) — se dispara automáticamente cada vez que un admin valida una OT o task completada. Event-driven, no huérfano. |
| `WarrantyWindowService::refreshWindow()` | Llamado directo dentro de `OrdenTrabajoUnifiedService::validarAdmin()` (línea 969), inmediatamente después de `HealthBonusService::evaluate()`, solo para OTs facturables con cliente. Event-driven. |
| `CompositeScoreService::scoreAll()` | `TalentoEscalafonController` — computado **on-demand** en cada carga de la pantalla "Escalafón", cacheado 15 min (`Cache::remember(...,900,...)`). No necesita cron: es lectura bajo demanda con caché, no un batch que deba correr solo. |

## B. FALSO POSITIVO A EVITAR

Ninguno con el patrón estricto de #647 (servicio de ciclo llamado dentro del `handle()` de OTRO
comando, invisible para un comando gemelo). El único comando propio del módulo con arranque real
(`talento:check-credential-expirations`) no tiene ningún comando gemelo que compita por su motor.

## C. WIRED PERO DORMIDO A PROPÓSITO

No aplica en este universo — no se encontró ningún feature-flag de este tipo dentro de Talento.

## D. MANUAL POR DISEÑO — sin invocador automático, y no debería tenerlo (8)

Verificado en los 4 sitios (crontab, Kernel, `Artisan::call`/`->call(`, deploy/*.sh): cero arranque
automático en los 4. Cada uno se dispara desde un controller vía HTTP (botón de UI o llamada de la
app móvil) para una acción deliberada de un humano sobre UN registro puntual — no un barrido de N
registros que "debería" correr solo:

| Servicio | Por qué es manual a propósito |
|---|---|
| `LiquidationService::calculate()` / `::close()` | El motor de compensación completo — ya documentado en CLAUDE.md como disparado por `TalentoLiquidacionController` (botón "Calcular liquidación" / "Cerrar"). **Confirmado que sigue siendo así**: sin cambios desde la última documentación, sigue sin invocador automático. Es la frontera dura de dinero/nómina — correcto que sea manual. |
| `ProjectBonusService::award()` | `TalentoProjectController` — acción puntual "otorgar bono de proyecto" sobre un proyecto específico. |
| `RouteDeviationService::analyze()` | `TalentoRouteController::analyzeDeviations()` — el propio docblock dice `/** Run deviation analysis on demand */`. |
| `CorridorDeviationService::analyze()` | `TalentoProjectController::analyzeCorridor()` — comentario de ruta explícito `// corre detección de desvíos` sobre rango de fechas elegido por el admin. |
| `WarrantyWindowService::classifyWarrantyOrder()` | `TalentoWarrantyController` — clasifica un caso de garantía puntual al reportarse. |
| `WarrantyWindowService::override()` | Override manual de un caso específico (mismo controller). |
| `FieldIaValidationService::validateOrder()` | `TalentoFieldFlowController` — botón "Validar con IA" sobre una OT puntual. |
| `FundService`/`LoanService` (`applyDeductions`, `authorize`, `markSpent`) | Se ejecutan **dentro** de `LiquidationService::calculate()` (mismo flujo manual de arriba) o vía acciones puntuales de autorización en sus controllers — no son procesos de barrido independientes. |

---

## E. BACKFILLS DE UNA SOLA CORRIDA

No aplica — no se encontró ningún comando de backfill de una sola corrida dentro de Talento (a
diferencia de `Active/`, que sí tenía 7 en el barrido #647).

---

## F. HUÉRFANOS — sin arranque, con veredicto (2)

### F1. `FieldFlowService::autoCloseSurveys()` — FALTA CONECTAR (evidencia máxima: el propio docblock declara el invocador que no existe)

- **Sitios buscados:** `grep -rn "autoCloseSurveys"` sobre `app/`, `routes/`, `resources/js/`,
  `deploy/` — **cero resultados fuera de su propia definición**. Sin cron, sin `Kernel.php`, sin
  `Artisan::call`, sin botón de UI.
- El propio método trae el docblock: *"Auto-close survey after configured days without response.
  Called by a scheduled command / cron."* — es la declaración de intención más explícita posible,
  y ese cron **nunca se escribió**.
- **Qué hace y qué rompe su ausencia:** cuando un cliente no responde la encuesta de satisfacción
  post-instalación dentro de `talento_survey_autoclose_days` (setting, default 7 días si no existe
  — verificado: **la fila del setting tampoco existe en BD**, corre siempre con el default de
  código), la orden de trabajo se queda **indefinidamente** sin `status='validated'`/
  `validated_at`. Esto es relevante porque `LiquidationService::countBillableUnits()` (el punto
  único de verdad del pago, ver CLAUDE.md §"Motor de compensación") filtra exactamente por
  `validated_at`/`status` — una OT de instalación cuyo cliente nunca contesta la encuesta **nunca
  se cuenta como facturable para el técnico**, salvo que un admin la valide a mano por otra vía.
  Sin el cron, el único cierre posible es manual.
- **Estado actual en DEV:** 0 encuestas pendientes de auto-cierre ahora mismo (verificado por
  tinker) — sin daño activo hoy, pero el mecanismo de contención simplemente no existe.
- **Veredicto: FALTA CONECTAR.** Candidato natural a línea de `Kernel.php` diaria (es idempotente
  por diseño: solo toca `auto_closed=false` con `created_at <= cutoff`) — mismo patrón que
  `talento:check-credential-expirations`, que si ya vive en `Kernel.php` justo al lado. Irving
  decide si se agrega el `$schedule->call(...)` (o se envuelve en un comando `talento:*` propio,
  más consistente con el resto del módulo) en una vuelta futura — no se tocó código de ejecución
  en esta pasada de inventario.

### F2. `talento:sync-colaboradores` (`SyncColaboradoresCommand`) — FALTA CONECTAR (o backfill que se olvidó ser backfill)

- **Sitios buscados:** `grep -rn "sync-colaboradores\|SyncColaboradoresCommand"` sobre `app/`,
  `routes/`, `resources/js/`, `deploy/` — **cero resultados** fuera de su propia definición y su
  registro en `ModuleServiceProvider`. Sin cron, sin `Kernel.php`, sin `Artisan::call`, sin botón
  de UI, sin fila en `command_configs`.
- **Qué hace:** reconcilia `talento_colaboradores` contra los roles Spatie de "puesto"
  (`TECNICO`, `TECNICO_INSTALADOR`, `TECNICO_PLANTA`, `Mostrador`) — da de alta automáticamente a
  cualquier usuario que reciba uno de esos roles y no tenga fila de colaborador, e inactiva al que
  perdió el rol. Su propio `$description` dice **"Idempotente"**, y su primer commit (`519a42b7`,
  2026-06-08) reporta *"Primera ejecución: 26 altas"* — nació como bootstrap, pero quedó escrito
  para poder re-correrse indefinidamente sin efectos secundarios (a diferencia de los backfills de
  una sola corrida de la categoría E, que se duermen a propósito tras cumplir su lote).
- **Por qué importa que nada lo dispare:** `TalentoColaboradorObserver` (el mecanismo que da
  `portal.colaborador` automáticamente) solo reacciona a `created`/`updated` de una fila
  `TalentoColaborador` ya existente — **no** a que un admin le asigne el rol `TECNICO` a un
  usuario desde `Administradores`. Existe una vía manual paralela
  (`TalentoColaboradorController::store`, botón "Agregar colaborador" en la UI), así que un admin
  que se acuerde de hacerlo a mano cubre el caso — pero si asigna el rol de puesto y **no** da de
  alta al colaborador aparte, ese técnico nunca aparece en Talento (sin dashboard, sin acceso al
  Portal de Colaborador, sin poder recibir OTs vía el motor de compensación) hasta que alguien
  corra el comando a mano.
- **Estado actual en DEV:** 0 de deriva ahora mismo — los 5 usuarios con rol de puesto activo ya
  tienen su fila `talento_colaboradores` (verificado por tinker). Sin daño activo hoy.
- **Veredicto: FALTA CONECTAR** (si el diseño pretende que sea reconciliación continua — candidato
  a cron diario/semanal de bajo riesgo, es solo altas/inactivaciones dentro del propio módulo) **o
  DOCUMENTAR COMO BACKFILL CERRADO** (si el flujo real esperado es que el alta a colaborador SIEMPRE
  pase por el botón manual de `TalentoColaboradorController::store`, y este comando fue solo el
  bootstrap inicial del 8 de junio que ya cumplió su propósito). Ambas lecturas son válidas sin que
  el código decida por sí solo — mismo patrón que F1 del precedente #792
  (`PublicationSchedulerService::rescheduleFailedSlots`). Irving decide.

---

## Resumen

| Categoría | Cantidad | Servicios/comandos |
|---|---|---|
| A. Confirmados (arranque real) | 4 | `talento:check-credential-expirations` (cron), `HealthBonusService::evaluate/evaluateTask` (event), `WarrantyWindowService::refreshWindow` (event), `CompositeScoreService::scoreAll` (on-demand + caché) |
| B. Falso positivo descartado | 0 | ninguno con el patrón estricto |
| C. Wired pero dormido por feature-flag | 0 | no aplica en este universo |
| D. Manual por diseño (intencional, correcto así) | 8 | `LiquidationService::calculate/close` (motor de compensación, ya documentado en CLAUDE.md), `ProjectBonusService::award`, `RouteDeviationService::analyze`, `CorridorDeviationService::analyze`, `WarrantyWindowService::classifyWarrantyOrder/override`, `FieldIaValidationService::validateOrder`, `FundService`/`LoanService` (dentro del flujo de liquidación) |
| E. Backfill de una sola corrida | 0 | no aplica en este universo |
| F. Huérfanos con veredicto | 2 | **F1** `FieldFlowService::autoCloseSurveys()` (docblock declara cron inexistente; sin él, OTs de instalación sin encuesta respondida nunca se cuentan como facturables) — **F2** `talento:sync-colaboradores` (reconciliación rol→colaborador idempotente sin invocador; alta manual vía UI existe como vía paralela) |

**Confirmación explícita del foco del item** (motor de compensación): `LiquidationService`,
`HealthBonusService` y `ProjectBonusService` **siguen** disparándose solo manual/event-driven vía
UI/controller — CLAUDE.md sigue vigente en ese punto, sin deriva. Lo que sí quedó sin conectar es
periférico al pago directo pero alimenta sus datos de entrada (F1) y su universo de colaboradores
elegibles (F2) — ninguno de los dos toca el cálculo de dinero en sí.

FUERA DE ALCANCE DE ESTA PASADA (mismo mandato que #647/#789/#792): no se conectó, corrigió,
ejecutó ni desconectó nada — no se disparó ninguna liquidación real ni se tocó nómina/dinero. Los 2
huérfanos con veredicto quedan para que Irving decida (conectar F1 a `Kernel.php`, decidir si F2
sigue siendo backfill cerrado o necesita cron) en una vuelta futura.
