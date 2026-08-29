# Barrido de comandos en `app/Console/Commands/Active/` (item #789, sub-item de #657)

**Alcance de esta pasada:** los 56 comandos de `app/Console/Commands/Active/` — el cuerpo de
trabajo que el item #647 (barrido del módulo Circuito CC) dejó registrado como pendiente aparte,
por ser "un cuerpo de trabajo separado y mucho más grande" con precedente ya documentado en
CLAUDE.md como "one-off, algunos destructivos, revisar antes de correr".

**SOLO INVENTARIO** (mismo mandato que el precedente #647): esta pasada NO borra ni desconecta
nada. Cada veredicto es lectura para que Irving decida en una vuelta futura.

## Método (sitios consultados, para los 56)

1. `crontab -l` del usuario `meganet` (12 líneas activas — 11 vía `cron-wrap.sh`/`vigilia-wrap.sh`
   del Circuito CC + 1 línea directa `backup_db:process`).
2. `app/Console/Kernel.php::schedule()` — schedule hard-coded (~30 líneas `$schedule->command(...)`/
   `$schedule->job(...)`/`$schedule->call(...)`) **+** `command_configs` (schedule dinámico en BD,
   7 filas activas: `suspends_services_early_in_the_day:process`, `billing_service_command:process`,
   `backup_db:process`, `update_task_old_command:process`, `check_conection_mikrotik:process`,
   `app:rectify-clients-in-mikrotik`, `mikrotikBackup:process`).
3. `grep -rn "Artisan::call\|->call("` sobre `app/` y `routes/` — invocaciones internas (52 sitios).
4. `grep` de cada una de las 56 signatures sobre `app/`, `routes/`, `resources/js/`, `config/`,
   `deploy/`, `database/` (migraciones + seeders + dumps SQL), para distinguir invocación real de
   mención en docblock/comentario/config-catalog.
5. Vistas Vue que llaman esas rutas por HTTP (`resources/js/components/module/adminstration/`,
   `resources/js/components/module/setting/CommandConfig.vue`) — el patrón de "botón admin"
   confirmado como arranque real cuando el botón existe en la vista montada, no solo la ruta.
6. `/etc/supervisor/conf.d/*.conf` (2 archivos: `megaisp-queue.conf`, `megaisp-deploy-worker.conf`)
   y `systemctl list-timers` — cero llamadas a `schedule:run` ni a ninguno de los 56 comandos.

### ⚠️ Hallazgo de contexto — `schedule:run` NO corre en este DEV (afecta la lectura de la tabla A)

A diferencia del barrido #647 (donde el arranque real de los comandos del Circuito CC es 1:1 con
líneas de crontab que invocan el comando directo), la mayoría de los comandos de negocio de este
barrido dependen de `Kernel.php::schedule()` — que **solo corre si algo dispara
`php artisan schedule:run` cada minuto**. En este box **no existe esa línea** (confirmado:
`crontab -l` no la tiene, no hay timer de systemd, no hay entrada de supervisor para ella). Esto
ya está documentado como comportamiento esperado en CLAUDE.md (§"Requisito de producción — cron de
schedule:run": *"En desarrollo NO hay cron activo"*) y en el propio comentario de la línea de
`backup_db:process` en el crontab (*"este crontab NUNCA tuvo una línea `schedule:run`"*). Por
consistencia con la metodología del precedente #647 (que sí contó `circuito:reactivar-agendados`
como confirmado por su entrada en `Kernel.php`, pese a depender del mismo mecanismo), esta pasada
clasifica en **A** todo comando registrado en `Kernel::schedule()` o en `command_configs` — es
arranque real y verificable en el código, condicionado a que PROD sí tenga el cron de
`schedule:run` (ítem de infraestructura ya registrado como pendiente de verificar en CLAUDE.md).
Se anota aquí para que quede explícito y no se lea como "confirmado = corriendo ahora mismo en
dev", que sería engañoso.

---

## A. CONFIRMADOS — arranque real verificado (28)

| Comando | Arranque |
|---|---|
| `smart-import:analyze` | `ImportExportController::launchAnalyzeProcess()` — `nohup php artisan smart-import:analyze {token}` (proceso desprendido tras iniciar un análisis desde la UI de SmartImport) |
| `smart-import:run` | `ImportExportController::launchSmartImportProcess()` — mismo patrón nohup, tras confirmar el análisis |
| `activitylog:archive` | `Kernel.php` `dailyAt('02:00')` |
| `config:auditar-env` | `EnvironmentHealthService.php:405` (`Artisan::call`, gate antes de `config:cache`, botón "Limpiar y recalentar cachés" de la Torre) **+** paso manual documentado en CLAUDE.md (candado del `&&` antes de `config:cache`) |
| `backup_db:process` | **Triple cobertura**: `Kernel.php` `dailyAt('02:00')` **+** `command_configs` (fila id=27, `01:05`) **+** línea directa de crontab `0 2 * * * php artisan backup_db:process` (la única de las 56 con invocación directa fuera de `cron-wrap.sh`, agregada a propósito tras el incidente P0 del 22-ago) |
| `billing_service_client_with_promise_payment_command:process` | `AdministracionController::billingServiceToClientActivePromise()` + botón real en `ShowScripts.vue` ("Cobrar Servicios A Clientes con Promesa de Pago activa") |
| `billing_service_command:process` | `AdministracionController::billigProcess()` + botón real en `ShowScripts.vue` ("Cobrar Servicios") **+** `command_configs` (fila id=15, `00:03`) |
| `check_conection_mikrotik:process` | `command_configs` (fila id=30, frequency_id=6) |
| `updates:check-github` | `Kernel.php`, condicional `if (config('updates.enabled'))` `everyThirtyMinutes()` — real en instancias "consumidoras" (no en dev/publicador) |
| `invoice:create-proformas` | `Kernel.php` `dailyAt('03:00')` |
| `release:deploy` | `ReleaseController.php:165` — `nohup php artisan release:deploy {id}` (proceso desprendido al publicar una release desde la UI) |
| `deploy:dry-run-migrations` | Paso `migrate_dryrun` del pipeline de `RemoteDeployCommand.php:115` (`'cmd' => 'php artisan deploy:dry-run-migrations'`), que a su vez corre vía `remote:deploy` (ver abajo) |
| `marketing:publish-due` | `Kernel.php` `everyMinute()` |
| `mikrotikBackup:process` | `command_configs` (fila id=32, `23:00`) |
| `mikrotik:reintentar-sync` | `Kernel.php` `everyThirtyMinutes()` |
| `app:mikrotik-sync-command` | `Kernel.php` `everyFiveMinutes()` |
| `auditoria:minar-bitacora` | `Kernel.php` `everyFifteenMinutes()` |
| `embajadores:rebuild-kpis` | `Kernel.php` `dailyAt('04:00')` ("respaldo de auto-sanación") |
| `app:rectify-clients-in-mikrotik` | `command_configs` (fila id=31, frequency_id=5) |
| `remote:deploy` | `SelfUpdateJob.php:36` (`Artisan::call`) **+** `UpdateController.php:151` (nohup, botón "Actualizar ahora") **+** `RunPendingDeploysCommand.php:41` (ver abajo) |
| `promociones:revision-diaria` | `Kernel.php` `dailyAt('06:00')` |
| `remote:deploy-run-pending` | `Kernel.php`, condicional `if (!config('updates.enabled'))` `everyMinute()` — real en el box publicador (dev), inverso al de `updates:check-github` |
| `billing:send-pending-notifications` | `Kernel.php` `everyFifteenMinutes()` |
| `start:schedule-process` | `AdministracionController::activeCommands()`, invocado desde el botón "Activar" de `resources/js/components/module/setting/CommandConfig.vue` (pantalla de Configuración de comandos) — **ver Hallazgo H1 abajo: el comando en sí está roto** |
| `suspends_services_early_in_the_day:process` | `AdministracionController::suspendProcess()` + botón real en `ShowScripts.vue` ("Suspender Clientes") **+** `command_configs` (fila id=14, `10:00`) |
| `mikrotik:sync-consumption` | `Kernel.php` `everyTenMinutes()` |
| `mikrotik:sync-ping` | `Kernel.php` `everyFiveMinutes()` |
| `update_task_old_command:process` | `command_configs` (fila id=29, `07:25`) |

## B. FALSO POSITIVO A EVITAR

Ninguno detectado en este universo — a diferencia del módulo Circuito CC (#647), donde
`JarvisService::tick()` se llama directo sin pasar por su comando gemelo, en `Active/` no se
encontró ningún servicio de ciclo con el mismo patrón (llamada directa a un método de servicio que
haga invisible a un comando gemelo con arranque propio real).

## C. DUAL-PROPÓSITO

Ninguno detectado. El patrón del precedente (motor real = llamada directa a un servicio, comando
= CLI manual aparte con arranque diagnóstico propio) es específico de los servicios de ciclo del
Circuito CC (`AuditorService`, `JarvisService`, `MergeRunner`); en `Active/` no aparece nada
equivalente — cada comando de negocio ES su propia lógica, sin un servicio hermano que la
duplique por fuera.

## D. MANUAL POR DISEÑO — sin invocador automático, y no debería tenerlo (12)

| Comando | Por qué es manual a propósito |
|---|---|
| `megaisp:check-migrations` | Uso manual + hook de pre-commit **opcional** (`scripts/pre-commit-check-migrations.sh`, el propio script dice en su cabecera *"Instalación OPCIONAL (no se auto-instala, cada quien decide si lo quiere)"* — no está symlinkeado a `.git/hooks/pre-commit`). Item #1018. |
| `promociones:corregir-onu` | Docblock con instrucciones explícitas de uso manual por cliente ("Uso en producción: `php artisan promociones:corregir-onu 2905 50M 50M`"), corrección puntual caso-por-caso, no un batch. |
| `marketing:download-broll` | Bootstrap de la librería de B-roll de Pexels por `--company`; se corre a mano cuando se activa un nicho/empresa nueva en el Motor de Video, no algo que deba repetirse en cron. |
| `finanzas:paridad-invoices` | Diagnóstico de solo lectura, on-demand, para la decisión de la Fase 4 de la unificación invoices/client_invoices (item #723). No escribe nada. |
| `invoices:reporte-reconciliacion-backfill` | Reporte de solo lectura sobre el backfill de #749 ("reporte que Irving aprueba antes de cerrar", su propio docblock). No escribe nada. |
| `mikrotik:audit-api-exposure` | Auditoría de solo lectura (item #979), referenciada como paso manual del runbook `docs/runbook-mikrotik-restriccion-api-address.md` — ningún cron la dispara, ni su docblock lo sugiere. |
| `releases:publish-github` | Existe **porque** el paso automático `github_release` del pipeline se omite en dev (`skip_if_not_production`, item #245/#529) — es el complemento manual documentado en el propio `DeploymentService.php`. |
| `megafamilia:publish-apk` | Build+publish interactivo (`flutter build` con salida en vivo al operador) — inherentemente un comando de release disparado por un desarrollador, no un batch desatendido. |
| `backups:purge-test` | Docblock con instrucciones de "EJECUCIÓN REAL" explícitas para PROD vía consola; seguro por defecto (dry-run sin `--force`). |
| `restore_db:process` | Herramienta de recuperación ante desastres — restaurar desde un zip es, por diseño, una acción deliberada de un humano, nunca automática. |
| `embajadores:simulate-clean` | Limpiador del generador de datos demo del programa de Embajadores; se referencia a sí mismo y a su gemelo (`embajadores:simulate`) por nombre en la salida de consola — par de herramientas de desarrollo/demo, con flag `--confirm` explícito para uso "peligroso". |
| `embajadores:simulate` | Generador de datos demo del programa de Embajadores (`--confirm` para saltar el prompt interactivo) — mismo par que arriba, uso manual en desarrollo/demos. |

## E. BACKFILLS DE UNA SOLA CORRIDA — dormidos a propósito (7)

| Comando | Qué backfillea | Evidencia de que ya cumplió o de que sigue pendiente de una corrida |
|---|---|---|
| `payments:backfill-references` | `client_payment_references` para clientes que predatan `ClientObserver` | Idempotente ("solo genera para clientes que aún NO tienen referencia"); los nuevos ya la reciben por el observer — se queda en no-op si no hay huecos. |
| `invoices:backfill-from-client-invoices` | Espejo `invoices` desde `client_invoices` (Fase 3a/3b, #632/#748/#749) | El propio docblock dice que las 110,801 filas **ya fueron escritas** en una corrida previa (2026-08-28) — el archivo formaliza en código lo ya aplicado; re-correrlo es un no-op seguro (`insertOrIgnore` sobre el UNIQUE). |
| `multiolt:capture-b3a` | Captura fixtures reales de ONTs (item #153) | Docblock explícito "One-shot... 112 ONTs as of 2026-06-12" — cumplió su propósito puntual de capturar datos para validar parsers. |
| `multiolt:capture-w0` | Captura de config de aprovisionamiento Huawei (item #153) | Mismo patrón: derivar la secuencia real de comandos contra un ONT ya provisionado, una sola vez. |
| `client:normalizar-usuario-web` | Quita padding de ceros en `client_main_information.user` (item #155) | Docblock: "SOLO DEV... prod queda para un plan aparte, fuera de este item" — ya corrido en dev; prod pendiente de una decisión aparte, no de este comando repitiéndose. |
| `permisos:reforma-b2` | Adopción por prevalencia + limpieza de directos del staff (Reforma B2) | Idempotente por diseño ("re-correrlo tras aplicar no adopta ni limpia nada, directos ya en 0") — dry-run default, reforma de un incidente puntual de permisos. |
| `permisos:registrar-nuevos` | Registra permisos referenciados en código pero ausentes en BD (Reforma A2) | Idempotente (`firstOrCreate` + `givePermissionTo`), dry-run default — catch-up de un gap puntual, no un proceso recurrente. |

---

## F. HUÉRFANOS — sin arranque, con veredicto (9)

### F1. `radius:listen` (`RadiusListener`) — FALTA CONECTAR o retirar (con hallazgo de seguridad)

- **Sitios buscados:** crontab (0), `Kernel.php` (0), `Artisan::call` (0), `deploy/*.sh` (0),
  `/etc/supervisor/conf.d/*.conf` (0 — solo existen `megaisp-queue.conf` y
  `megaisp-deploy-worker.conf`, ninguno para un listener UDP). Ningún systemd timer/service.
- Es un servidor RADIUS dual (puertos 1812/1813) implementado con sockets UDP crudos, pensado para
  correr **indefinidamente** (`while(true)`) — el tipo de proceso que necesita supervisor/systemd,
  no cron. No tiene ninguno de los dos.
- **Hallazgo de seguridad (se documenta, no se toca — fuera de alcance de este inventario):** el
  secreto está hardcodeado en el código (`"meganet123"`) y la lógica de autenticación **siempre**
  responde Access-Accept sin validar nada (`$this->warn("Aceptando conexión de: " . $from)` sin
  ninguna comparación previa). Luce como una implementación de prueba/prototipo, no la integración
  RADIUS real del sistema — CLAUDE.md documenta por separado una integración FreeRADIUS vía
  segunda conexión de BD (`DB_RADIUS_*`), que es a la que apunta `radius:sync` (ver F8).
- **Veredicto: FALTA CONECTAR (si de verdad se necesita) o RETIRAR** — si esta es una
  implementación de prueba superada por la integración FreeRADIUS real, no debería quedar viva con
  un secreto fijo y auto-accept. Irving decide.

### F2. `radius:sync` (`SyncRadiusServices`) — candidato a FALTA CONECTAR (destructivo)

- **Sitios buscados:** los mismos 4, cero resultados fuera de su propio archivo.
- Sincroniza `radcheck`/`radreply` (BD `radius`, la integración FreeRADIUS real de CLAUDE.md) desde
  los servicios activos de Meganet, y **borra** (`->delete()`) los usuarios de Radius que ya no
  estén en Meganet. Su propio flag `--force` ("Sincronizar todo sin preguntar") sugiere que sin él
  debería pedir confirmación interactiva — no se encontró ese camino tampoco en ningún sitio.
- **Veredicto: FALTA CONECTAR (si el sistema depende de mantener sincronizado FreeRADIUS) o queda
  manual a propósito** — es un borrado real de filas en una BD externa, así que automatizarlo sin
  supervisión merece la decisión explícita de Irving, no una suposición de esta pasada.

### F3. `smart-import:sync-migrations` (`SyncSmartImportMigrations`) — candidato fuerte a FALTA CONECTAR

- **Sitios buscados:** los mismos 4 + grep específico dentro de todo
  `app/Modules/Addons/SmartImportExport/` (cero resultados).
- **Evidencia de que el propio autor lo diseñó para colgarse del flujo real y se quedó sin
  conectar:** su propio docblock dice *"Sincroniza la tabla migrations... tras un SmartImport"* —
  pero los dos pasos reales del pipeline SmartImport, confirmados vía `nohup` en
  `ImportExportController` (`launchAnalyzeProcess` → `smart-import:analyze`,
  `launchSmartImportProcess` → `smart-import:run`), nunca lo invocan a él como tercer paso.
- **Veredicto: FALTA CONECTAR.** Mismo patrón que F1-F5 del precedente #647 — un comando escrito
  para un enganche específico ("tras un SmartImport") que nunca se implementó.

### F4. `app:update-password-all-clients-in-mikrotik` (`UpdatePasswordAllClientsInMikrotik`) — AMBIGUO

- **Sitios buscados:** los mismos 4, cero resultados fuera de su propio archivo. Tampoco tiene
  botón en `ShowScripts.vue` ni en `IndexAdministration.vue` (a diferencia de sus 3 primos que sí
  están cableados ahí: suspender/cobrar/cobrar-con-promesa).
- Es una mutación masiva: actualiza la contraseña de **todos** los clientes en Mikrotik (con
  soporte opcional para acotar por `client_id`/`limit`).
- **Veredicto: AMBIGUO, sin evidencia textual que decida entre las dos lecturas** — (a) manual a
  propósito, porque automatizar un reset masivo de contraseñas en producción sería temerario, o
  (b) herramienta de mantenimiento que se usó una vez (p.ej. tras una migración/incidente) y quedó
  huérfana sin que nadie la retirara ni la documentara como backfill. Irving decide.

### F5. `embajadores:rebuild-closures` (`RebuildReferralClosuresCommand`) — AMBIGUO

- **Sitios buscados:** los mismos 4, cero resultados fuera de su propio archivo.
- Es hermano directo de `embajadores:rebuild-kpis`, que SÍ corre a diario vía `Kernel.php`
  (`dailyAt('04:00')`, comentado explícitamente como "respaldo de auto-sanación"). Este comando en
  cambio hace `TRUNCATE` de `referral_closures` completa y la reconstruye desde `chain_path`.
- **Veredicto: AMBIGUO** — automatizarlo a diario sería más riesgoso que su hermano (un `TRUNCATE`
  en cron es una decisión de diseño distinta a un recompute aditivo), así que podría ser manual a
  propósito; pero también podría ser el mismo tipo de respaldo de auto-sanación que su hermano y
  simplemente no se conectó. No se puede distinguir sin que Irving lo decida — mismo patrón que
  F5 del precedente (`circuito:retriar-bandeja` vs `circuito:destrabar-bandeja`).

### F6. `app:send-all-emails-command` (`SendAllEmailsCommand`) — HUÉRFANO confirmado, desactivado a propósito en algún momento

- **Sitios buscados:** los mismos 4. Única mención fuera de su archivo: `Kernel.php:35`, dentro de
  un bloque de comentario de bloque PHP (`/* ... */`) que también envuelve a `ServerStatusCommand`
  y `ReminderPaymentCommand` (ver F7/F9) — las 3 líneas están **literalmente comentadas**, no
  activas:
  ```php
  /*  $schedule->command('app:server-status-command')->everyMinute();
  $schedule->command('app:reminder-payment-command')->dailyAt('03:00');
  $schedule->command('app:send-all-emails-command')->everyFiveMinutes(); */
  ```
- **Veredicto: HUÉRFANO.** Alguien las desactivó a propósito en algún momento (no hay fecha ni
  motivo en el propio comentario) y quedaron así. Candidato a duplicado/superado por
  `app:send-emails-proforma-invoice-command` (ver F8) para el tipo `proforma_invoice` — pero
  **ninguno de los dos corre hoy**, así que no hay un reemplazo vivo, solo dos candidatos igual de
  dormidos.

### F7. `app:server-status-command` (`ServerStatusCommand`) — HUÉRFANO confirmado, mismo bloque comentado

- Mismo bloque comentado de `Kernel.php:33` que F6. Sin ningún otro invocador.
- El propio `handle()` hace `if (config('app.env') !== "production") return false;` — diseñado
  para no hacer nada en dev de todos modos, lo cual explica por qué nadie notó su ausencia de cron
  aquí, pero no explica por qué está comentado en vez de activo (para cuando SÍ corra en prod).
- **Veredicto: HUÉRFANO.**

### F8. `app:send-emails-proforma-invoice-command` (`SendEmailsProformaInvoiceCommand`) — HUÉRFANO, candidato a duplicado

- **Sitios buscados:** los mismos 4, cero resultados en ningún sitio (ni siquiera comentado).
- Su lógica (enviar emails pendientes de `proforma_invoice`) se solapa con el tipo
  `'proforma_invoice'` que también maneja `SendAllEmailsCommand` (F6, igual de huérfano) en su
  mapeo `$emailTypes`.
- **Veredicto: HUÉRFANO, candidato a duplicado/reemplazado** — mismo patrón que F7 del precedente
  #647 (`circuito:revisar` reemplazado por `circuito:revisar-backlog`), con la diferencia de que
  aquí **ninguno** de los dos candidatos corre actualmente. Irving decide si uno reemplaza al otro
  o si ambos deben conectarse (con cuidado de no duplicar el envío si se activan los dos).

### F9. `app:reminder-payment-command` (`ReminderPaymentCommand`) — HUÉRFANO confirmado, mismo bloque comentado

- Mismo bloque comentado de `Kernel.php:34` que F6/F7. Sin ningún otro invocador.
- Su propio flag `--force` ("ignorando la verificación diaria") sugiere que fue diseñado para
  correr por cron una vez al día y desde ahí decidir si ya corrió hoy — justo el mecanismo que
  está comentado.
- **Veredicto: HUÉRFANO.**

---

## Hallazgos adicionales (fuera de la clasificación A-F, mismo espíritu que el hallazgo D1 del precedente)

### H1. `start:schedule-process` apunta a un directorio que ya no existe — el botón "Activar" de Configuración de comandos está roto

`StartScheduleCommand::handle()` (usado por el botón real "Activar" de
`resources/js/components/module/setting/CommandConfig.vue`, vía
`AdministracionController::activeCommands()`) ejecuta:

```php
exec('cd /var/www/MEGANET && nohup php artisan schedule:work > /dev/null 2>&1 &', $output, $return_var);
```

`/var/www/MEGANET` **no existe en este servidor** (verificado: `ls` devuelve "No existe el fichero
o el directorio"). CLAUDE.md ya documenta ese path como el árbol de pruebas viejo, vhost
deshabilitado, "No tocar" — pero aquí el problema no es que alguien lo toque, es que el comando
real **depende** de él para arrancar `schedule:work` y ya no puede. Como el `exec` de la parte
`nohup ... &` corre en segundo plano, la función PHP no espera su resultado, así que el `if
(count($output) == 0)` (que solo mide si `schedule:work` YA estaba corriendo antes) sigue su curso
normal e imprime `'ok'` **incondicionalmente** — el botón le reportaría éxito al operador aunque el
intento de arranque haya fallado en el primer `cd`. Es arranque real (confirmado en A), pero
**roto**: si algún día alguien nota que `schedule:work` nunca corre en este box y presiona
"Activar" esperando que se resuelva solo, seguirá sin correr y la UI dirá que sí. Corrección
propuesta para un item aparte (fuera de alcance de este inventario, que es solo lectura): apuntar
el `cd` a `base_path()` en vez de la ruta hardcodeada, y verificar el `$return_var` real del exec
en vez de asumir éxito.

---

## Resumen

| Categoría | Cantidad | Comandos |
|---|---|---|
| A. Confirmados (arranque real) | 28 | ver tabla A (incluye 1 con bug funcional, H1) |
| B. Falso positivo descartado | 0 | ninguno en este universo |
| C. Dual-propósito | 0 | ninguno en este universo |
| D. Manual por diseño (intencional, correcto así) | 12 | ver tabla D |
| E. Backfill de una sola corrida (dormido a propósito) | 7 | ver tabla E |
| F. Huérfanos con veredicto | 9 | `radius:listen` (falta conectar/retirar, hallazgo de seguridad), `radius:sync` (falta conectar, destructivo), `smart-import:sync-migrations` (falta conectar), `app:update-password-all-clients-in-mikrotik` (ambiguo), `embajadores:rebuild-closures` (ambiguo), `app:send-all-emails-command` (huérfano, desactivado a propósito), `app:server-status-command` (huérfano, mismo bloque comentado), `app:send-emails-proforma-invoice-command` (huérfano, candidato a duplicado), `app:reminder-payment-command` (huérfano, mismo bloque comentado) |
| **Total comandos de `Active/`** | **56** | — |

Hallazgo adicional fuera de la tabla: **H1** — `start:schedule-process` apunta a un directorio
inexistente (`/var/www/MEGANET`) y su botón de UI reporta éxito aunque el arranque falle.

FUERA DE ALCANCE DE ESTA PASADA (mismo mandato que #647): no se borró, desconectó ni corrigió
nada. Los 9 huérfanos con veredicto y el hallazgo H1 quedan para que Irving decida item por item
(conectar, retirar, corregir o dejar así) en una vuelta futura — igual que los 6 "falta
conectar/ambiguo/sobra" del barrido #647.
