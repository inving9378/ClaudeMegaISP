# Barrido de comandos en `app/Console/Commands/Olts/` (item #791, sub-item de #657)

**Alcance de esta pasada:** los 9 comandos de `app/Console/Commands/Olts/` (sincronización
SmartOLT/OLT-ONU + sondas/escrituras de desarrollo contra el driver Huawei) — mismo mandato que
los precedentes #647 (módulo Circuito CC) y #789 (56 comandos de `Active/`): aplicar la misma
metodología de secciones A-F.

**SOLO INVENTARIO** (mismo mandato que los dos precedentes): esta pasada NO borra ni desconecta
nada. Cada veredicto es lectura para que Irving decida en una vuelta futura.

## Método (sitios consultados, para los 9)

1. `crontab -l` del usuario `meganet` — ninguna de las 11 líneas activas invoca un comando
   `Olts/` directo (todas son `circuito:*`/`backup_db:process`, ver precedente #647).
2. `app/Console/Kernel.php::schedule()` — bloque `//Comandos OLT` (líneas 48-56).
3. `grep -rn "Artisan::call\|->call("` sobre `app/` y `routes/` — invocaciones internas.
4. `grep` de cada una de las 9 signatures y clases sobre `app/`, `routes/`, `resources/js/`,
   `config/`, `deploy/`, `database/` (migraciones) y `tests/`.
5. `command_configs` (schedule dinámico en BD) — 0 filas con `olt`/`smartolt`/`huawei`/`dedupe`.
6. `resources/js/components/module/olts/` — botón real `SyncFromApi.vue` ("Actualización" →
   "Desde API SmartOLT"), que dispara el `reload(true)` consumido por las vistas de OLTs
   (Vlans/Cards/PonPorts/UplinkPorts/Billing/ODBs/Zones/Profiles), cada una con su propio
   controller que hace `Artisan::call('smartolt:sync-inventory', [...])`.
7. `/etc/supervisor/conf.d/*.conf` — cero referencias a `smartolt`/`huawei`/`olt`.
8. `docs/multiolt-w3-backups/`, `docs/multiolt-w0-fixtures/`, `tests/Unit/OltDriver/fixtures/huawei/`
   y `docs/MULTIOLT_PROVISION_HUAWEI.md` — evidencia física de que las sondas de desarrollo del
   driver Huawei (fases `B1c`/`B2a`/`W3` del propio docblock de cada comando) ya corrieron.

---

## A. CONFIRMADOS — arranque real verificado (5)

| Comando | Arranque |
|---|---|
| `smartolt:sync-inventory` | `Kernel.php` `dailyAt('05:00')` **+** `Artisan::call('smartolt:sync-inventory', [...])` desde 9 controladores de `GestionRed/Controllers/OLTs/` (`OLTsController`, `OLTsTypeONUsController`, `OLTsPonPortsController`, `OLTsUplinkPortsController`, `OLTsVlansController`, `OLTsBillingController`, `OLTsCardsController`, `OLTsODBsController`, `OLTsZonesController`, `OLTsProfilesController`), disparados por el botón real "Actualización → Desde API SmartOLT" (`SyncFromApi.vue`) en cada vista de OLTs |
| `smartolt:sync-clients-with-ont` | `Kernel.php` `dailyAt('05:30')` **+** `Artisan::call` encadenado desde `SyncCritical.php:111` y desde `SyncInventory.php:185` (cada corrida de esos dos comandos también lo dispara) |
| `smartolt:sync-critical` | `Kernel.php` `everyTenMinutes()` (el mismo job que CLAUDE.md documenta como "crítico" — `smartolt:sync-critical (10m)`) |
| `gestionred:sync-huawei` | `Kernel.php` `everyTenMinutes(15)` — comentario propio del Kernel: "Huawei Telnet scan — una sesión por corrida, TTL lock 900s (scan ≈7-10 min). Si `duration_s` > 8 min en los logs, subir el intervalo a 15 min" |
| `smartolt:sync-promotions` | `Kernel.php` `hourly()` **+** `Artisan::call` en `Active/RevisionDiariaPromociones.php:34` (a su vez `dailyAt('06:00')` — doble cobertura, mismo patrón que `circuito:reactivar-agendados` del precedente #647) |

## B. FALSO POSITIVO A EVITAR

Ninguno detectado en este universo — no hay ningún servicio de ciclo (`tick`/`drain`/`ciclo`) del
driver Huawei que se llame directo por fuera de estos 9 comandos.

## C. DUAL-PROPÓSITO — patrón nuevo: reuso de métodos públicos por instanciación directa desde una migración (1, NO huérfano)

### `olts:dedupe-onus` (`DedupeOnus`)

- **Sitios buscados:** crontab (0), `Kernel.php` (0), `Artisan::call`/`->call(` en `app/`+`routes/`
  (0), `command_configs` (0). El comando en sí — su `handle()`, la entrada `php artisan
  olts:dedupe-onus [--execute]` — no tiene ningún invocador automático.
- **Pero su lógica SÍ tiene un consumidor real**, distinto a los patrones C de los dos
  precedentes (que eran servicio↔comando gemelo): la migración
  `database/migrations/2026_06_10_200002_delete_ghost_olt_onus.php` hace `new DedupeOnus()` e
  invoca DIRECTO sus métodos públicos `findDuplicateGroups()`, `pickWinner()` y
  `ensureBackupTable()` (declarados `public` a propósito — el propio docblock del último dice
  *"Public para que la migración pueda invocarlo sin reflexión"*) para deduplicar `olt_onus` por
  `unique_external_id`. La propia migración documenta: *"En dev esta migración ya está registrada
  ... y no volverá a ejecutarse. El cambio aplica a producción y cualquier entorno fresco donde
  aún no corrió."*
- **Veredicto: NO es huérfano.** El comando CLI (`--execute`/dry-run) es la herramienta manual de
  diagnóstico/limpieza bajo demanda; la migración es el motor real que garantiza el dedupe en
  cualquier entorno que corra `php artisan migrate` (incluida PROD, cuando corra esa migración).
  Ambos caminos comparten la misma lógica sin duplicar código — patrón sano, solo se documenta
  porque su forma (clase de comando reusada como librería PHP simple, no vía `Artisan::call`) no
  había aparecido en los dos precedentes.

## D. MANUAL POR DISEÑO

Ninguno en este universo bajo esa forma — los 3 candidatos que podrían parecer "manual por
diseño" (las sondas Huawei) ya tienen evidencia de haber corrido y cumplido su propósito puntual,
así que encajan mejor en **E** (ver abajo) que en D (que es para herramientas que se siguen usando
recurrentemente a mano, como `promociones:corregir-onu` del precedente #789).

## E. BACKFILLS/SESIONES DE UNA SOLA CORRIDA — dormidos a propósito, ya cumplieron su propósito (3)

Mismo patrón que `multiolt:capture-b3a`/`multiolt:capture-w0` del precedente #789 (item #153,
"One-shot... ya cumplió su propósito puntual de capturar datos para validar parsers") — estos 3
son fases previas/hermanas del **mismo** esfuerzo de desarrollo del driver Huawei:

| Comando | Qué hace (sesión única, según su propio docblock) | Evidencia de que ya corrió |
|---|---|---|
| `olt:huawei:probe` (fase **B1c** del propio docblock) | "Sesión única de validación Telnet contra Huawei MA5800-X7" — login→enable→config→interface gpon→display ont info/optical-info→quit→display version | `docs/MULTIOLT_PROVISION_HUAWEI.md:261` referencia "los tests de B1c-B3d" como ya corridos; el propio driver (`writeSlow()`) documenta el comportamiento validado en esa fase |
| `olt:huawei:probe-b2a` (fase **B2a**) | "Sesión única de captura de fixtures... NO parsea, solo guarda output crudo sanitizado" — batería de 6 `display` para alimentar los parsers de B2b (offline) | `tests/Unit/OltDriver/fixtures/huawei/` contiene EXACTAMENTE los 6 archivos `*_real.txt` que esta batería genera (`display_board_real.txt`, `display_autofind_real.txt`, `display_ont_info_by_sn_real.txt`, `display_ont_info_port_0_2_8_real.txt`, `display_ont_optical_batch_0_2_8_real.txt`, `display_version_real.txt`), fechados jul-11 — ya capturados y en uso por los parsers offline |
| `multiolt:w3-write` (fase **W3**) | Write supervisado (dry-run por default, `--confirm` para escribir de verdad) de una descripción de prueba sobre el ONT allow-listado `HWTCFEFCC9A2`, con rollback | `docs/multiolt-w3-backups/w3_backup_20260616_165715.txt` ("PUNTO DE ROLLBACK para W3 Fase 4") es el backup que el propio comando referencia por constante (`ROLLBACK_DESC`, línea 30 de ese archivo) — la sesión W3 ya corrió y dejó su punto de restauración documentado. Cubierto además por `tests/Unit/OltDriver/W3WriteCommandTest.php` (mocks, no toca la OLT real) |

**Veredicto para los 3: dormidos a propósito, no huérfanos ni gaps.** Quedan disponibles como
herramientas de diagnóstico manual si el driver Huawei necesita revalidarse contra hardware real
en el futuro (mismo espíritu que `promociones:corregir-onu` del precedente), pero no falta
conectarlos a nada — su propósito era puntual (validar el driver en desarrollo) y ya se cumplió.

## F. HUÉRFANOS

Ninguno. Los 9 comandos de este directorio caen limpio en A (5), C (1) o E (3) — a diferencia de
los dos precedentes, este universo no tiene ningún caso "se diseñó para engancharse y se quedó
sin conectar".

---

## Resumen

| Categoría | Cantidad | Comandos |
|---|---|---|
| A. Confirmados (arranque real) | 5 | `smartolt:sync-inventory`, `smartolt:sync-clients-with-ont`, `smartolt:sync-critical`, `gestionred:sync-huawei`, `smartolt:sync-promotions` |
| B. Falso positivo descartado | 0 | ninguno en este universo |
| C. Dual-propósito (motor real vía reuso de clase, no huérfano) | 1 | `olts:dedupe-onus` (consumido por la migración `2026_06_10_200002_delete_ghost_olt_onus.php`, patrón nuevo respecto a los precedentes) |
| D. Manual por diseño | 0 | ninguno bajo esa forma en este universo |
| E. Backfill/sesión de una sola corrida (dormido a propósito, ya cumplida) | 3 | `olt:huawei:probe` (B1c), `olt:huawei:probe-b2a` (B2a), `multiolt:w3-write` (W3) |
| F. Huérfanos con veredicto | 0 | ninguno |
| **Total comandos de `Olts/`** | **9** | — |

A diferencia de los precedentes #647 (6 huérfanos con veredicto) y #789 (9 huérfanos con
veredicto), este directorio **no tiene huérfanos**: es un universo pequeño y disciplinado — 5
comandos de sincronización recurrente con cron real, 1 comando reusado como librería por una
migración de saneo, y 3 sondas de desarrollo de un driver que ya hicieron su trabajo puntual y
quedaron documentadas con evidencia física (fixtures/backups) de que corrieron.

FUERA DE ALCANCE DE ESTA PASADA (mismo mandato que los precedentes): no se borró, desconectó ni
corrigió nada.
