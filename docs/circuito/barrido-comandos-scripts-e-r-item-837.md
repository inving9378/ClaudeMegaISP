# Barrido de comandos en `app/Console/Commands/Scripts/` — rango alfabético E-R (item #837, sub-item de #790)

**Alcance de esta pasada:** los 18 comandos de `app/Console/Commands/Scripts/` cuyo nombre de
archivo cae en el rango E-R (`ExampleBillingReminderCommand` … `RollbackOrphanClientBackfillCommand`),
continuando el barrido de `Scripts/` que dejó registrado el precedente `#836` (rango A-D) como
"pendiente registrado como sub-item" — misma metodología de `#647` (módulo Circuito CC) y
`#789`/`#657` (`Active/`, `Olts/`).

**SOLO INVENTARIO** (mandato explícito del item, heredado de `#836`): esta pasada NO corrió, borró
ni desconectó nada. Cada veredicto es lectura estática (+ un puñado de consultas de solo lectura
contra la BD de dev, cuando la clasificación dependía de si una condición seguía vigente) para que
Irving decida en una vuelta futura.

## Método (sitios consultados, para los 18 — mismos 7 del precedente `#836` + 1 nuevo)

1. `crontab -l` del usuario `meganet` (13 líneas activas, todas `circuito:*`/`backup_db:process`
   vía `cron-wrap.sh`/`vigilia-wrap.sh` — cero líneas para cualquiera de los 18 comandos de este rango).
2. `app/Console/Kernel.php::schedule()` — schedule hard-coded (~45 líneas `$schedule->command(...)`/
   `->job(...)`/`->call(...)`) — cero coincidencias con los 18.
3. `command_configs` (schedule dinámico en BD) — consultado directo por tinker con los 18
   `signature`/`process_name` (o su prefijo, para los que llevan argumentos): **0 filas**.
4. `grep -rn "Artisan::call\|->call("` sobre `app/` y `routes/`, cruzado contra los 18 signatures —
   **1 coincidencia real** (ver categoría A).
5. `grep` de cada `signature` y de cada nombre de clase sobre `app/`, `routes/`, `resources/js/`,
   `config/`, `deploy/`, `database/` — 3 coincidencias adicionales, las 3 mención en **comentario/
   docblock** de otro archivo (no invocación): `client:references` citado en
   `ClientInformationController.php:190` (explicando `app:ajuste-cliente`), `crm:purge-orphan-documents`
   citado en `CrmDocumentStorage.php:11` (documentando que comparten la regla de borrado seguro), y
   `auth:rehash-passwords` citado en `PasswordService.php:22` (documentando el mecanismo híbrido).
6. `resources/js/components/module/adminstration/show_scripts/ShowScripts.vue` (pantalla "Scripts
   Ejecutables", 5 botones reales, ninguno de los 18 de este rango) y `IndexAdministration.vue`
   (mosaico de tarjetas de Administración, cero enlaces a los 18) — mismo patrón de "botón admin"
   del precedente `#789`.
7. `app/Modules/Core/Usuarios/routes.php` (grupo `/administracion`) leído completo — los métodos de
   `AdministracionController` uno por uno.
8. **Sitio nuevo respecto a `#836`:** `resources/js/components/module/setting/IndexSetting.vue` (la
   pantalla "Configuración", distinta de "Scripts Ejecutables") — ahí apareció el único botón real
   de este rango (ver categoría A). Se revisó porque el precedente `#836` no lo necesitó (sus 4
   comandos A vivían todos en `ShowScripts.vue` o en una feature de Configuración ya conocida), pero
   `grep` del signature apuntó ahí y había que seguirlo.

### Nota metodológica — 2 verificaciones de datos en vivo (solo lectura)

A diferencia de `#836` (que clasificó sin consultar la BD), en este rango 2 comandos tenían
descripciones que solo se pueden leer correctamente sabiendo si la condición que corrigen **sigue
vigente hoy** — sin ese dato, "ya cumplió su propósito" y "sigue pendiente" son indistinguibles
desde el código solo. Se corrieron 3 queries de solo lectura (conteos, sin escribir nada — ver
categorías E/F para el detalle):

- `RehashPasswordsCommand` (bcrypt) — **4,043 de 4,096 usuarios siguen en base64** (98.7%).
- `FixedClientsWithInternetCreadoManual` — **238 clientes** siguen con la condición sin corregir.
- `RemoveReminderConfigurationCommand` — 0 duplicados / 0 huérfanos en `reminders_configurations`
  hoy (el comando sería no-op si se corriera ahora, pero eso no valida su lógica — ver el hallazgo).

---

## A. CONFIRMADOS — arranque real verificado (1)

| Comando | Arranque |
|---|---|
| `populate_data_base_colony_state_municipality_command:process` (`PopulateDataBaseColonyStateMunicipalityCommand`) | Botón real "Repoblar Las Tablas de Estado municipio y colonia" en **Configuración** (`IndexSetting.vue:1966`, `<a :href="${url}/administracion/set-state-municipality-and-colony">`) → `AdministracionController::setStateMunicipalitiesAndColonies()` (`routes.php:28`) → `Artisan::call(...)` |

**Nota:** el comando hace `TRUNCATE` de `states`, `municipalities` y `colonies` y las recarga desde
los dumps SQL de `config/state_municipalities_and_colonies/` (ya documentados en CLAUDE.md §"GEO
DATA"). Es destructivo-pero-idempotente (recarga de datos de referencia geográfica, no de negocio) —
arranque real confirmado, no un hallazgo nuevo; se documenta el detalle porque el nombre del comando
no deja claro desde dónde se dispara (vive en Configuración, no en "Scripts Ejecutables").

## B. FALSO POSITIVO A EVITAR

Ninguno detectado en este rango.

## C. DUAL-PROPÓSITO

Ninguno detectado — mismo patrón que `#789`/`#836`: en `Scripts/` cada comando ES su propia lógica,
sin un servicio de ciclo hermano que la duplique por fuera.

## D. MANUAL POR DISEÑO — sin invocador automático, y no debería tenerlo (7)

| Comando | Por qué es manual a propósito |
|---|---|
| `app:excecute-script-restore-mikrotik-dev` (`ExcecuteScriptRestoreMikrotikDev`) | Su propia descripción lo dice: *"Este comando es para rellenar el mikrotik de PRUEBA"*. Pareja de `ObtenerTodosLosUsuariosDeMikrotik` (mismo rango, ver siguiente fila): importa a `router_id=2` el archivo `script_dev.rsc` que ese otro comando genera. Herramienta de desarrollo de 2 pasos, mismo espíritu que `embajadores:simulate`/`embajadores:simulate-clean` del precedente `#789`. |
| `app:obtener-todos-los-usuarios-de-mikrotik` (`ObtenerTodosLosUsuariosDeMikrotik`) | Genera `storage_path('script_client_mikrotik/script_dev.rsc')` (PPP secrets + address list del router de prueba) — el archivo que consume el comando de arriba. Sin este comando, el otro no tiene qué importar; ambos forman el mismo par manual. |
| `table:export-import {table} --export --import --file= --connection= --target-connection=` (`ExportImportTable`) | Utilidad genérica y reusable de export/import de tablas entre conexiones, sin ningún dato hardcodeado — herramienta de mantenimiento bajo demanda, mismo patrón que `app:crear-sql-con-todas-las-tablas-vacias-excepto-las-de-config` del precedente `#836`. |
| `client:references {id?}` (`ListClientReferences`) | El propio docblock lo declara: *"solo lectura, NO modifica datos"* — diagnóstico de auditoría de cobertura antes de usar `app:ajuste-cliente` (`ClientInformationController.php:190` lo cita explícitamente con ese propósito). Mismo patrón que `users:backfill-huerfanos`/`finanzas:paridad-invoices` de `#789`. |
| `crm:purge-orphan-documents --dry-run --files=0 --limit=200` (`PurgeOrphanCrmDocuments`) | Dry-run **por defecto**, idempotente y por lotes (el propio docblock: *"re-ejecutar encuentra los huérfanos restantes; cuando no quedan, reporta 0"*), documentado para el item #81. Mismo patrón que `crm:purge-orphan-documents` es tan explícito en su diseño manual que ni siquiera necesita el criterio de "podría tener cron" — ya declara su propio modo seguro. |
| `app:remove-grace-period-command {clientId}` (`RemoveGracePeriodCommand`) | Exige un `clientId` como argumento posicional — acción puntual caso-por-caso sobre UN cliente, mismo patrón que `app:remove-grace-period-command`/`promociones:corregir-onu` de `#789` (herramienta reusable, no un batch). |
| `users:rollback-orphan-backfill {batch} --dry-run` (`RollbackOrphanClientBackfillCommand`) | Compañero quirúrgico de `users:backfill-orphan-clients` (`#836`, categoría E — *"Aún sin corrida real decidida — dormido a propósito hasta que Irving lo autorice"*). Borra por `login_user` exacto (no solo por id, "por si el id fue reciclado") usando el log `orphan_client_backfill_log` del batch — solo tiene sentido una vez que el backfill que deshace corra; hoy no tiene nada que deshacer, lo cual es correcto. |

## E. BACKFILLS DE UNA SOLA CORRIDA — dormidos a propósito, o pendientes de decidir su corrida (4)

| Comando | Qué hace | Evidencia de que ya cumplió o de que sigue pendiente |
|---|---|---|
| `app:import-data-inventory` (`ImportDataInventory`) | `TRUNCATE` de 4 tablas de inventario (`inventory_item_stocks`, `inventory_item_store_zones`, `inventory_items`, `inventory_movements`) y reimporta desde un Excel **de ruta fija** (`database_path('files/inventory_import1.xlsx')`) | Ruta de archivo hardcodeada a un nombre específico — no es parametrizable para un import nuevo sin editar el código; migración puntual de una carga de inventario concreta. |
| `app:load-media-inventory-stock` (`LoadMediaInventoryStock`) | `TRUNCATE` de `inventory_item_media`, extrae un zip **de ruta fija** (`database_path('files/images.zip')`) y asocia cada imagen a un `InventoryItemStock` por nombre de archivo numérico | Mismo patrón que el anterior (ruta fija, `TRUNCATE`, ajuste de memoria a `8912M`) — compañero directo de `ImportDataInventory`: depende de que los IDs de `inventory_item_stocks` ya existan (los crea el comando de arriba). |
| `auth:rehash-passwords --dry-run --chunk=200` (`RehashPasswordsCommand`) | Migra `users.password` de base64 legacy a bcrypt (idempotente, dry-run soportado, no dispara eventos) | **Verificado en dev: 4,043 de 4,096 usuarios (98.7%) siguen en base64** — la migración NO se ha corrido. CLAUDE.md ya lista esto en su Hoja de Ruta como *"Admin: migración base64 → bcrypt — Pendiente, prioridad Baja"* (sección Portal Cliente). El comando es la herramienta lista para esa migración, esperando que Irving decida correrla. |
| `app:rectify-activation-date --chunk-size=500 --dry-run` (`RectifyActivationDate`) | Ajusta `client_main_information.activation_date` para que coincida con la fecha del primer pago del cliente, en chunks, con dry-run | Sin dato hardcodeado (a diferencia de sus vecinos de `#836`), pero es una rectificación de **drift histórico acumulado** (fecha de activación vs. fecha de primer pago), no un ajuste de formato. ⚠️ **Distinto** de `app:ajuste-client-activation-date` (`#836`, categoría E) — aquél normaliza el **formato** de la fecha ya guardada; éste **re-deriva** la fecha desde el primer pago. Nombres parecidos, propósitos distintos — fácil de confundir. |

## F. HUÉRFANOS — sin arranque, con veredicto (6)

### F1. `app:example-billing-reminder-command` (`ExampleBillingReminderCommand`) — HUÉRFANO, script de ejemplo/prueba con datos hardcodeados sensibles

- **Sitios buscados:** los 7 estándar — cero resultados fuera de su propio archivo.
- El propio nombre de la clase ("Example") y su descripción (*"Envía un recordatorio de pago de
  PRUEBA a un cliente"*) lo delatan como un harness de ejemplo, no una herramienta operativa.
- **Hallazgo:** el filtro de clientes está hardcodeado a **un cliente real** (`->where('id', '=',
  1437)`) y el email destino de TODOS los `Reminder::create(...)` que genera está hardcodeado a
  **`cr584136@gmail.com`** — un gmail personal, no un dominio de la empresa. Si se corriera, crearía
  filas reales en `reminders` apuntando ese correo, contra un cliente real de producción/dev.
- **Veredicto: HUÉRFANO, candidato a retirar** (o al menos a limpiar el email hardcodeado si se
  conserva como fixture de pruebas) — mismo espíritu que el F5/F6 de `#836` (script con residuo de
  una sesión interactiva sin limpiar), aquí el residuo es un destinatario personal en vez de un
  `dd()`.

### F2. `app:excecute-script-to-rectify-mikrotik` (`ExcecuteScriptToRectifyMikrotikCommand`) — HUÉRFANO, pipeline incompleto (cruza con el precedente `#836`)

- **Sitios buscados:** los 7 estándar — cero resultados fuera de su propio archivo.
- Importa `mikrotik_script.rsc` al router `router_id=2` vía `/import` de RouterOS — pero ese archivo
  **no existe en el router** por ningún camino automatizado: lo genera
  `CreateFilesToRectifyMikrotikCommand` (`#836`, ya clasificado **F3 "candidato a SOBRA"**), que lo
  escribe en disco LOCAL del servidor app (`storage_path('script_client_mikrotik/mikrotik_script.rsc')`)
  y define un método `uploadToMikrotik()` (FTP) para subirlo al router — **pero ese método nunca se
  llama, ni desde el propio `handle()` de `CreateFilesToRectifyMikrotikCommand` ni desde ningún otro
  sitio del repo** (`grep -rn "uploadToMikrotik" app/` → 1 sola coincidencia: su propia definición).
- **Veredicto: HUÉRFANO, mitad de una tubería de 3 pasos (generar → subir → ejecutar) donde el paso
  de en medio nunca se conectó.** Ya era candidato a SOBRA por el lado del generador (`#836`, F3:
  probable herramienta de una generación anterior al sync automático por API); este comando confirma
  que aunque alguien quisiera usar el flujo manualmente, tendría que subir el archivo a mano por FTP
  primero (el código para hacerlo existe pero está muerto). Irving decide si retira los 3 comandos
  del trío o los completa.

### F3. `app:fixed-clients-with-internet-creado-manual` (`FixedClientsWithInternetCreadoManual`) — candidato a FALTA CONECTAR (condición activa, sin hardcode)

- **Sitios buscados:** los 7 estándar — cero resultados fuera de su propio archivo.
- Migra clientes cuyo servicio de Internet quedó con `description = 'Internet creado manual'` (un
  placeholder histórico) hacia un bundle real (si son recurrentes) o al plan 100Mb/449 (si son
  prepago) — sin ningún id/fecha hardcodeado, a diferencia de sus primos de `#836` (categoría E).
- **Verificado en dev: 238 clientes siguen con esa condición sin corregir hoy.** No es un backfill
  que "ya cumplió" — es una condición **activa y sin resolver**, y nada la dispara automáticamente
  (ni cron, ni botón, ni evento al crear el servicio "Internet creado manual" en primer lugar).
- **Veredicto: candidato a FALTA CONECTAR** (si "Internet creado manual" sigue siendo un estado
  transitorio real que debería auto-sanarse, como `embajadores:rebuild-kpis`) **o a correr como
  backfill manual pendiente** — cualquiera de las dos requiere que Irving confirme que la regla de
  negocio (recurrente→bundle 16, prepago→100Mb/449) sigue vigente antes de tocar 238 clientes reales.

### F4. `app:genera-general-accounting-income-command` (`GeneraGeneralAccountingIncomeCommand`) — AMBIGUO, toca contabilidad

- **Sitios buscados:** los 7 estándar — cero resultados fuera de su propio archivo.
- Hace `TRUNCATE` completo de `general_accounting_incomes` y la reconstruye desde `Payment` del año
  actual a la fecha — un patrón de **rebuild total**, no de backfill incremental.
- Es el mismo patrón que `embajadores:rebuild-kpis` (`#789`, SÍ programado a diario como "respaldo de
  auto-sanación") y `embajadores:rebuild-closures` (`#789`, AMBIGUO — mismo tipo de `TRUNCATE`+rebuild
  sin cron, sin poder distinguir "manual a propósito" de "se quedó sin conectar").
- **Veredicto: AMBIGUO.** ⚠️ Toca contabilidad/ingresos — si el reporte de "ingresos contables" debe
  reflejar pagos nuevos cada día, este comando debería correr en cron (como su análogo de KPIs de
  Embajadores); si es un reporte que se regenera manualmente al cierre de cada período, es correcto
  que sea manual. No se puede distinguir sin que Irving lo confirme — mismo espíritu que F5 de
  `#789`, un nivel más adentro (aquí además hay dinero de por medio).

### F5. `app:rectify-auto-increment-in-client-table-and-relation` (`RectifyAutoIncrementInClientTableAndRelation`) — candidato a SOBRA (superado, confirmado)

- **Sitios buscados:** los 7 estándar — cero resultados fuera de su propio archivo.
- Hace `ALTER TABLE clients AUTO_INCREMENT = <max(id)+1>` — exactamente lo mismo que
  `ClientIdMigrator::migrate()` (`app/Services/Client/ClientIdMigrator.php:64-65`) hace **automáticamente**
  al final de cada migración de id de cliente:
  ```php
  // Reposicionar el contador interno. Es DDL (commit implícito) => va
  // fuera de la transacción. MySQL nunca lo baja por debajo de MAX(id).
  $nextId = (int) DB::table('clients')->max('id') + 1;
  DB::statement('ALTER TABLE clients AUTO_INCREMENT = ' . $nextId);
  ```
  `ClientIdMigrator` es el motor que usa `app:ajuste-cliente` (`#836`, categoría D) — el propio
  docblock de ese comando ya decía *"reemplazó una lista de tablas a mano que se quedaba corta"*;
  este hallazgo confirma que el reemplazo también absorbió el paso de re-cuadrar el AUTO_INCREMENT.
- **Veredicto: candidato a SOBRA (superado)** — mismo patrón que F7 de `#647`
  (`circuito:revisar` reemplazado por `circuito:revisar-backlog`): residuo de un flujo manual de
  varios pasos que el reemplazo (`app:ajuste-cliente` + `ClientIdMigrator`) ya cubre solo. No hay
  evidencia de que se necesite fuera de ese flujo (nadie más desalinea el AUTO_INCREMENT de
  `clients`). Irving decide si se retira.

### F6. `app:remove-reminder-configuration-command` (`RemoveReminderConfigurationCommand`) — HUÉRFANO con hallazgo de lógica (no hace lo que dice)

- **Sitios buscados:** los 7 estándar — cero resultados fuera de su propio archivo.
- Descripción propia: *"Remueve RemindersConfiguration **duplicados** o que no tienen clientes
  asignados"* — la palabra "duplicados" implica conservar uno y borrar el resto.
- **Hallazgo (bug de lógica):** el código real, para cada `client_id` con más de 1 fila, hace
  `RemindersConfiguration::where('client_id', $value->client_id)->delete()` — **borra TODAS las
  filas de ese cliente, no le deja ninguna**. No deduplica: aniquila. Un cliente con 2 configuraciones
  de recordatorio terminaría con **cero** tras correr este comando — peor que el problema original.
- **Verificado en dev: 0 client_id con más de 1 fila y 0 huérfanas hoy** — el comando sería un no-op
  si se corriera ahora mismo (no hay nada que romper con el bug tal cual está la BD), pero eso no
  valida la lógica: si algún día vuelve a haber duplicados, correrlo se comería la configuración
  completa del cliente en lugar de limpiarla.
- **Veredicto: HUÉRFANO, con bug confirmado por lectura de código.** Sin arranque hoy (por eso el bug
  nunca se manifestó), candidato a corregir-antes-de-conectar o a retirar si ya no se necesita. No se
  toca aquí (solo inventario) — se documenta para que la corrección, si se decide, no repita el
  patrón "duplicados" = "borra todo".

---

## Resumen

| Categoría | Cantidad | Comandos |
|---|---|---|
| A. Confirmados (arranque real) | 1 | `populate_data_base_colony_state_municipality_command:process` (botón real en Configuración, no en "Scripts Ejecutables") |
| B. Falso positivo descartado | 0 | ninguno en este rango |
| C. Dual-propósito | 0 | ninguno en este rango |
| D. Manual por diseño (intencional, correcto así) | 7 | `app:excecute-script-restore-mikrotik-dev`, `app:obtener-todos-los-usuarios-de-mikrotik`, `table:export-import`, `client:references`, `crm:purge-orphan-documents`, `app:remove-grace-period-command`, `users:rollback-orphan-backfill` |
| E. Backfill de una sola corrida (dormido a propósito o pendiente de decidir) | 4 | `app:import-data-inventory`, `app:load-media-inventory-stock`, `auth:rehash-passwords` (⚠️ **aún pendiente**, 98.7% de usuarios sin migrar), `app:rectify-activation-date` |
| F. Huérfanos con veredicto | 6 | `app:example-billing-reminder-command` (huérfano, datos hardcodeados sensibles), `app:excecute-script-to-rectify-mikrotik` (pipeline incompleto, cruza con `#836`), `app:fixed-clients-with-internet-creado-manual` (candidato a falta conectar, 238 clientes activos sin corregir), `app:genera-general-accounting-income-command` (ambiguo, toca contabilidad), `app:rectify-auto-increment-in-client-table-and-relation` (candidato a sobra, superado por `ClientIdMigrator`), `app:remove-reminder-configuration-command` (huérfano, bug de lógica confirmado) |
| **Total comandos del rango E-R en `Scripts/`** | **18** | — |

**Hallazgos que cruzan con el precedente `#836`:** el comando F3 de `#836`
(`app:create-files-to-rectify-mikrotik`, ya "candidato a SOBRA") y el F2 de este barrido
(`app:excecute-script-to-rectify-mikrotik`) son las dos mitades rotas de la misma tubería de 3 pasos
(generar → subir por FTP → ejecutar en el Mikrotik) — el paso de en medio (`uploadToMikrotik()`)
existe en código pero nunca se invoca desde ningún sitio. Y F5 de este barrido
(`app:rectify-auto-increment-in-client-table-and-relation`) quedó confirmado como superado por
`ClientIdMigrator`, el motor que usa `app:ajuste-cliente` (`#836`, categoría D) — mismo patrón de
"herramienta vieja de varios pasos, reemplazada por una nueva que absorbió todos los pasos".

FUERA DE ALCANCE DE ESTA PASADA (mismo mandato que los precedentes): no se corrió, borró, corrigió
ni desconectó nada — ni siquiera el bug confirmado de `RemoveReminderConfigurationCommand`. Los 6
huérfanos con veredicto, el ambiguo de contabilidad, y la decisión de correr o no
`auth:rehash-passwords` (migración de 4,043 usuarios pendiente) quedan para que Irving decida item
por item en una vuelta futura.

## Pendiente registrado como sub-item

El resto de `app/Console/Commands/Scripts/` (rango S-Z, el resto del directorio no cubierto por
`#836` ni por este rango E-R) queda fuera de esta pasada — mismo patrón de partición por sub-rango
que usaron `#647`→`#789`→`#836` cuando el barrido completo no cabe en una sola vuelta.
