# Barrido de comandos en `app/Console/Commands/Scripts/` — rango alfabético A-D (item #836, sub-item de #790)

**Alcance de esta pasada:** los 20 comandos de `app/Console/Commands/Scripts/` cuyo nombre de
archivo cae en el rango A-D (`AddClientsImportedToMikrotikCommand` … `DeployClientsInAddressListCommand`),
aplicando la misma metodología de los precedentes `#647` (módulo Circuito CC) y `#789`/`#657`
(`Active/`, `Olts/`) al directorio `Scripts/`, que CLAUDE.md ya advertía como "one-off, algunos
destructivos, revisar antes de correr" — advertencia que esta pasada confirma con casos concretos
(ver sección F).

**SOLO INVENTARIO** (mandato explícito del item): esta pasada NO corrió, borró ni desconectó nada.
Cada veredicto es lectura estática para que Irving decida en una vuelta futura.

## Método (sitios consultados, para los 20)

1. `crontab -l` del usuario `meganet` (11 líneas activas, todas `circuito:*`/`backup_db:process`
   vía `cron-wrap.sh`/`vigilia-wrap.sh` — cero líneas para cualquiera de los 20 comandos de este
   rango).
2. `app/Console/Kernel.php::schedule()` — schedule hard-coded (~45 líneas `$schedule->command(...)`/
   `->job(...)`/`->call(...)`, incluido el bloque comentado histórico `app:server-status-command`/
   `app:reminder-payment-command`/`app:send-all-emails-command`) — cero coincidencias con los 20.
3. `command_configs` (schedule dinámico en BD) — consultado directo por tinker con los 20
   `process_name` exactos: **0 filas**.
4. `grep -rn "Artisan::call\|->call("` sobre `app/` y `routes/` (48 sitios totales) — cruzado contra
   los 20 `signature` uno por uno.
5. `grep` de cada `signature` y de cada nombre de clase sobre `app/`, `routes/`, `resources/js/`,
   `config/`, `deploy/`, `database/` — para distinguir invocación real de mención en
   docblock/comentario.
6. `resources/js/components/module/adminstration/show_scripts/ShowScripts.vue` (la pantalla
   "Scripts Ejecutables" de Administración — el patrón de "botón admin" ya confirmado como arranque
   real en el precedente `#789`) y `IndexAdministration.vue` — vistas Vue reales, no solo rutas
   declaradas.
7. `app/Modules/Core/Usuarios/routes.php` (grupo `/administracion`) leído completo — los 11 métodos
   de `AdministracionController` y sus rutas, uno por uno.

---

## A. CONFIRMADOS — arranque real verificado (4)

| Comando | Arranque |
|---|---|
| `addclientsimportedtomikrotik:process` (`AddClientsImportedToMikrotikCommand`) | Botón real "Montar Clientes Con Servicios de Internet en el Mikrotik" en `ShowScripts.vue` (`montarServiciosEnElMikrotik()`) → `GET /administracion/add-clients-imported-to-mikrotik` → `AdministracionController::addClientsImportedToMikrotik()` → `Artisan::call(...)` |
| `rectify_address_list:process` (`DeployClientsInAddressListCommand`) | Botón real "Rectifica el Address List" en `ShowScripts.vue` (`ejecutarScript('rectify_address_list')`) → `GET /administracion/rectify_address_list` → `AdministracionController::rectifyAddressList()` → `Artisan::call(...)` |
| `field_module_create_field_migration:process` (`CreateColumnInDataBaseCommand`) | Feature real "Campos Adicionales" (Configuración): `SettingAdditionalFieldController::store()` (`POST /configuracion/campos-adicionales/add`) → `FieldModuleService::createColumn()` → `callProcessToCreateColumn()` → `Artisan::call(...)`. Crea la columna física en la tabla del modelo al dar de alta un campo dinámico. |
| `delete_column__in_data_base:process` (`DeleteColumnInDatabaseCommand`) | Mismo feature, ruta hermana: `SettingAdditionalFieldController::destroy()` (`POST /configuracion/campos-adicionales/destroy/{id}`) → `FieldModuleService::deleteColumn()` → `callProcessToDeleteColumn()` → `Artisan::call(...)`. Borra la columna física al eliminar el campo dinámico. |

**Nota:** los 2 últimos (`field_module_create_field_migration:process` /
`delete_column__in_data_base:process`) ejecutan `ALTER TABLE ADD/DROP COLUMN` en vivo contra tablas
de negocio (clientes, bundles, etc.) al usar la UI de "Campos Adicionales" — arranque real
confirmado, no un hallazgo nuevo; se documenta el detalle porque no es obvio desde el nombre del
comando que estén conectados a una feature de uso frecuente.

## B. FALSO POSITIVO A EVITAR

Ninguno detectado en este rango.

## C. DUAL-PROPÓSITO

Ninguno detectado — a diferencia del módulo Circuito CC (`#647`), en `Scripts/` no hay servicios de
ciclo con comando gemelo.

## D. MANUAL POR DISEÑO — sin invocador automático, y no debería tenerlo (4)

| Comando | Por qué es manual a propósito |
|---|---|
| `app:ajuste-cliente {id_actual} {id_nuevo}` (`AjusteCliente`) | Recibe **parámetros** y valida existencia/colisión de IDs antes de delegar en `ClientIdMigrator` (que descubre TODAS las referencias del cliente desde el esquema). El propio docblock explica que reemplazó una lista de tablas a mano que se quedaba corta — herramienta de mantenimiento reusable caso-por-caso, mismo patrón que `promociones:corregir-onu` del precedente `#789` (D). |
| `app:crear-sql-con-todas-las-tablas-vacias-excepto-las-de-config` (`CrearSqlConTodasLasTablasVaciasExceptoLasDeConfig`) | Utilidad de generación de dumps SQL "plantilla" (todas las tablas vacías salvo config) — herramienta de desarrollo/provisioning bajo demanda, no un proceso recurrente. |
| `users:backfill-huerfanos --dry-run --sample=N` (`BackfillHuerfanosDiagnosticoCommand`) | El propio docblock lo declara: "Diagnóstico READ-ONLY... NUNCA escribe en la base de datos, solo mide y reporta", prerequisito explícito del item #654/#105. Mismo patrón que `circuito:inventario-spec`/`finanzas:paridad-invoices` de los precedentes (D). |
| `invoice:create-proformas-atrasadas-clientes-activos --client-id=\|--all-clients` (`CreateProformaInvoiceOverdueClientActiveCommand`) | Exige explícitamente `--client-id` o `--all-clients` (aborta si no se pasa ninguno) — diseño de herramienta manual, no de cron desatendido. ⚠️ Toca dinero (crea facturas proformas retroactivas "con carácter retroactivo para todos los períodos desde la creación del cliente") — de conectarse alguna vez a un botón/cron, merece decisión explícita de Irving, no se sugiere aquí. |

## E. BACKFILLS DE UNA SOLA CORRIDA — dormidos a propósito (6)

| Comando | Qué hace | Evidencia de que ya cumplió o de que sigue pendiente |
|---|---|---|
| `app:adjust_email_clients` (`adjust_email_clients`) | Cambia el email de 4 `client_id` hardcodeados (`6793,6794,6797,6798`) a un email fijo (`jgarcia@prk.com.mx`) | Valores literales de un caso puntual ya resuelto — no hay forma de que esto sea un proceso recurrente, es un fix de un ticket específico. |
| `app:ajuste-client-activation-date` (`AjusteClientActivationDate`) | Normaliza el formato de `activation_date` en **todos** los `client_main_information` | Comentario propio en el código: `// TODO SE va a quedar el usuario 4981 que es irvin con esta fecha...` — deja constancia de un caso conocido ya inspeccionado a mano; barrido masivo de formato, del tipo que se corre una vez tras detectar el problema. |
| `app:add-services-to-clients-where-has-ip-and-not-service` (`AddServicesToClientsWhereHasIpAndNotService`) | Crea `ClientInternetService` con **todos los campos hardcodeados** (`internet_id=34`, `router_id=2`, `price=449`, `start_date='2024-11-13T14:19'`, etc.) para clientes con IP pero sin servicio | La fecha fija `2024-11-13` en `start_date` ata el script a una corrida puntual de esa fecha — no es parametrizable, no puede repetirse para un caso nuevo sin editar el código. |
| `app:bloqued-client-with-older-payment` (`BloquedClientWithOlderPayment`) | Bloquea clientes activos cuyo último pago es anterior a una fecha de corte hardcodeada (`'2024-09-09'`) | Fecha de corte fija en el código + termina con `dump('Clients bloqued ' . $allClients)` (salida de depuración) — barrido puntual de una fecha de corte específica, ya superada. |
| `users:backfill-orphan-clients --report\|--dry-run\|--limit=N` (`BackfillOrphanClientUsersCommand`) | Crea la fila `users` "espejo" (rol `client`) para `client_main_information` huérfanos — backfill masivo real, con log de auditoría propio (`orphan_client_backfill_log`) y comando de rollback dedicado (`users:rollback-orphan-backfill {batch}`) | El propio docblock dice que es el "backfill masivo real... parqueado para producción" (item #105), prerequisito ya satisfecho por su hermano diagnóstico `users:backfill-huerfanos` (categoría D arriba). **Aún sin corrida real decidida** — dormido a propósito hasta que Irving lo autorice, no por abandono. |
| `roadmap:clasificar-items --dry-run` (`ClasificarRoadmapItemsCommand`) | Rellena `modulo`/`priority` faltantes en `roadmap_items` (conservador: nunca pisa un valor ya puesto) | Docblock fecha el barrido de datos que lo motivó ("barrido del 2026-07-14") y explicita que es idempotente/re-ejecutable sin efecto si ya no hay huecos — mismo patrón que `roadmap:backfill-reporte-coloquial` del precedente `#647` (E). No es lo mismo que `JarvisService::clasificarModulo()` (clasificación automática al crear un item, activa) ni que `circuito:clasificar-modulo` (comando aparte, F6 del precedente `#647`) — este opera sobre columnas distintas (`modulo`/`priority`, no el estado "Sin clasificar"). |

---

## F. HUÉRFANOS — sin arranque, con veredicto (6)

### F1. `app:assign-client-user-to-client` (`AssignClientUserToClient`) — AMBIGUO

- **Sitios buscados:** los 4 estándar (crontab, Kernel, `Artisan::call`, `deploy/*.sh`) + `ShowScripts.vue`/`IndexAdministration.vue` — cero resultados fuera de su propio archivo.
- A diferencia de los E de arriba, **no tiene ningún dato hardcodeado** (recorre TODOS los
  `ClientUser`, reasigna `client_id` desde el servicio de Internet vinculado o borra el registro si
  ya no hay servicio) — no se puede leer como "corrida puntual de un caso" ni como "diseñado para
  repetirse a propósito".
- **Veredicto: AMBIGUO** — puede ser un backfill genérico que se quedó sin conectar tras resolver el
  incidente que lo motivó, o una herramienta de reparación que debería tener cron de respaldo
  (mismo espíritu que `embajadores:rebuild-kpis`, que si tiene su `dailyAt` como "respaldo de
  auto-sanación"). Irving decide.

### F2. `app:blocked-clients-active-dont-have-payment-afeter-four-month` (`BlockedClientsActiveDontHavePaymentAfeterFourMonth`) — candidato a SOBRA (posible duplicado funcional)

- **Sitios buscados:** los 4 estándar + `ShowScripts.vue` — cero resultados.
- Sin fecha ni dato hardcodeado (a diferencia de su vecino alfabético `BloquedClientWithOlderPayment`,
  categoría E) — suspende **y** bloquea clientes activos morosos hace más de 3 meses sin plan de
  administración, con jobs reales (`SuspendServiceJob::dispatch`) y cambio de estado.
- Se solapa funcionalmente con dos piezas que **sí** están conectadas: `suspends_services_early_in_the_day:process`
  (botón real "Suspender Clientes" + cron `command_configs` id=14, confirmado en el precedente `#789`)
  y el propio `BloquedClientWithOlderPayment` (categoría E de este barrido, ya corrido una vez).
- **Veredicto: candidato a SOBRA (reemplazado por `suspends_services_early_in_the_day:process`) o
  FALTA CONECTAR si cubre un criterio distinto** (">4 meses sin plan de administración" es más
  específico que el criterio de suspensión diaria) — no se puede distinguir sin que Irving confirme
  si el criterio de 4 meses sigue vigente como regla de negocio independiente. ⚠️ Toca dinero/estado
  de cliente — cualquier decisión de conectarlo requiere su confirmación explícita.

### F3. `app:create-files-to-rectify-mikrotik` (`CreateFilesToRectifyMikrotikCommand`) — candidato a SOBRA (posible superado)

- **Sitios buscados:** los 4 estándar + `ShowScripts.vue` — cero resultados.
- Genera un archivo de script para subir a mano a un router Mikrotik hardcodeado (`router_id=2`),
  comparando PPP secrets/address list actuales contra los servicios reales.
- El flujo Mikrotik que **sí** corre hoy (confirmado en el precedente `#789`) es
  `app:mikrotik-sync-command` (`Kernel.php` `everyFiveMinutes()`), que sincroniza directo por API
  RouterOS (`MikrotikService`) sin generar archivos intermedios para subir a mano.
- **Veredicto: candidato a SOBRA** — probable herramienta de una generación anterior al sync
  automático por API, sin evidencia de que siga siendo necesaria. Irving decide si se retira o si
  cubre un caso (auditoría/diff manual) que el sync automático no cubre.

### F4. `app:create-service-charge-in-client-with-positive-grace-period` (`CreateServiceChargeInClientWithPositiveGracePeriod`) — candidato a FALTA CONECTAR

- **Sitios buscados:** los 4 estándar + `ShowScripts.vue` — cero resultados.
- Sin dato hardcodeado; despacha `RectifyBalanceAndCreateTransaction::dispatch()` (cargo real de
  servicio) para clientes activos con periodo de gracia positivo y saldo positivo — job real de
  dinero, sin ningún disparador.
- **Veredicto: candidato a FALTA CONECTAR** (si el cargo por periodo de gracia es una regla de
  negocio vigente) **o MANUAL A PROPÓSITO** (si es una corrección puntual que se aplica caso por
  caso). ⚠️ Dinero — decisión de Irving, no se sugiere conectar aquí.

### F5. `app:delete-client-bundle-service-with-duplicate-internet-service` (`DeleteClientBundleServiceWithDuplicateInternetService`) — HUÉRFANO, script de emergencia con debug sin limpiar

- **Sitios buscados:** los 4 estándar + `ShowScripts.vue` — cero resultados.
- **DESTRUCTIVO:** borra filas de 4 tablas (`client_bundle_services`, `client_internet_services`,
  `client_voz_services`, `client_custom_services`) para bundles cuyo servicio de Internet no tiene
  IP — sin transacción, sin dry-run, sin confirmación.
- **Hallazgo:** el método termina en `dd($clients)` (dump-and-die) — evidencia de que se ejecutó de
  forma interactiva desde consola al menos una vez y el debug nunca se retiró. No es código
  parametrizable ni pensado para repetirse desatendido.
- **Veredicto: HUÉRFANO, script de emergencia de una sola corrida** (mismo espíritu que
  `radius:sync`/`radius:listen` del precedente `#789`: destructivo, sin arranque, candidato a
  retirar una vez confirmado que ya cumplió su propósito puntual — o a documentar formalmente como
  herramienta de última instancia). Irving decide si se retira o se deja documentado.

### F6. `app:delete-client-internet-services-w-here-doest-have-ip` (`DeleteClientInternetServicesWHereDoestHaveIp`) — HUÉRFANO, mismo patrón que F5

- **Sitios buscados:** los 4 estándar + `ShowScripts.vue` — cero resultados.
- **DESTRUCTIVO:** borra filas de `client_internet_services` sin IP y sin bundle — sin transacción,
  sin dry-run.
- Mismo hallazgo que F5: termina en `dd($clients)`, debug de una corrida interactiva sin limpiar.
- **Veredicto: HUÉRFANO, script de emergencia de una sola corrida** — mismo tratamiento que F5.
  Ambos (F5/F6) son primos directos por nombre y por forma (`Delete*WHere*Ip`) — probablemente
  escritos y corridos en la misma sesión de limpieza de datos.

---

## Resumen

| Categoría | Cantidad | Comandos |
|---|---|---|
| A. Confirmados (arranque real) | 4 | `addclientsimportedtomikrotik:process`, `rectify_address_list:process`, `field_module_create_field_migration:process`, `delete_column__in_data_base:process` |
| B. Falso positivo descartado | 0 | ninguno en este rango |
| C. Dual-propósito | 0 | ninguno en este rango |
| D. Manual por diseño (intencional, correcto así) | 4 | `app:ajuste-cliente`, `app:crear-sql-con-todas-las-tablas-vacias-excepto-las-de-config`, `users:backfill-huerfanos`, `invoice:create-proformas-atrasadas-clientes-activos` |
| E. Backfill de una sola corrida (dormido a propósito) | 6 | `app:adjust_email_clients`, `app:ajuste-client-activation-date`, `app:add-services-to-clients-where-has-ip-and-not-service`, `app:bloqued-client-with-older-payment`, `users:backfill-orphan-clients`, `roadmap:clasificar-items` |
| F. Huérfanos con veredicto | 6 | `app:assign-client-user-to-client` (ambiguo), `app:blocked-clients-active-dont-have-payment-afeter-four-month` (candidato a sobra), `app:create-files-to-rectify-mikrotik` (candidato a sobra), `app:create-service-charge-in-client-with-positive-grace-period` (falta conectar), `app:delete-client-bundle-service-with-duplicate-internet-service` (huérfano destructivo, debug sin limpiar), `app:delete-client-internet-services-w-here-doest-have-ip` (huérfano destructivo, debug sin limpiar) |
| **Total comandos del rango A-D en `Scripts/`** | **20** | — |

**Confirma la advertencia previa de CLAUDE.md** ("varios son one-off y potencialmente
destructivos") con evidencia concreta: 6 de 20 son backfills de una sola corrida ya aplicados
(categoría E) y 2 de 20 son scripts destructivos sin transacción que terminan en `dd()` sin haberse
limpiado (F5/F6) — el tipo exacto de comando que **nunca debe correrse a ciegas** desde este
directorio.

FUERA DE ALCANCE DE ESTA PASADA (mismo mandato que los precedentes): no se corrió, borró ni
desconectó nada. Los 2 candidatos a "sobra", los 2 "falta conectar/ambiguo" y los 2 huérfanos
destructivos quedan para que Irving decida item por item (conectar, retirar o dejar documentado)
en una vuelta futura.

## Pendiente registrado como sub-item

El resto de `app/Console/Commands/Scripts/` (rango E-Z, ~el resto del directorio no cubierto por
este rango A-D) queda fuera de esta pasada — es el mismo patrón de "cuerpo de trabajo separado" que
los precedentes `#647`→`#789` ya usaron para partir el barrido por directorio/rango cuando no cabe
en una sola vuelta.
