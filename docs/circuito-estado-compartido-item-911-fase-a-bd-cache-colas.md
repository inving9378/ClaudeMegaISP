# Circuito CC #911/#912 — Fase 1a: Inventario BD, Cache y Colas por terminal

Sub-item de seguimiento de `#912` (Fase 1 de `#911`). **Solo lectura** — ningún comando de esta
vuelta modificó código funcional, configuración ni datos; todo lo de abajo es verificación directa
contra el filesystem, los `.env` de las 6 terminales, `config()` en runtime y `docs/bitacora-sesiones.md`.

Hermanos de esta fase (ver `docs/roadmap-bucle-reap-item-912-verificacion.md`): `#1000004` (Fase
1b — `storage/` symlinks y locks de archivo), `#1000005` (Fase 1c — semáforo npm y colisión de
puertos), `#1000006` (Fase 1d — consolidación final, depende de las tres anteriores). Este
documento cubre **solo** BD/Cache/Colas (puntos 1, 3, 4 del spec original de `#912`).

## Método

- Los 6 `.env` de `/home/meganet/circuito/wt-{1..6}/.env` se verificaron con `grep -c` de patrones
  exactos (`^CLAVE=valor$`), sin volcar contenido — evita exponer secretos, confirma solo si la
  clave/valor coincide.
- `config('cache.*')`, `config('queue.*')` y `config('database.default')` se leyeron en runtime
  (`php artisan tinker`) desde este worktree (`wt-2`).
- Comandos del circuito (`app/Modules/Addons/Roadmap/Console/`, 40+ comandos `circuito:*`) y
  `RoadmapCircuitoService.php` (3357 líneas) se revisaron por grep de `Schema::`, `Artisan::call`,
  `::dispatch(`, `migrate`, `db:seed`.
- `/etc/supervisor/conf.d/megaisp-*.conf` se leyó directo (acceso de lectura al sistema disponible).
- Incidente histórico: grep de "migrate" + "worktree"/"wt-" en `docs/bitacora-sesiones.md`.

## Tabla del entregable (parcial — BD / Cache / Colas)

| Recurso | ¿Compartido entre las 6 terminales? | Evidencia | Si 2+ vueltas coinciden |
|---|---|---|---|
| **BD — filas normales** (`roadmap_items`, `settings`, `circuito_disparos`, resto de tablas de negocio) | **SÍ** — misma BD `megaisp` en `127.0.0.1` | `grep -c '^DB_DATABASE=megaisp$'` = 1 y `grep -c '^DB_HOST=127.0.0.1$'` = 1 en los 6 `.env` (`wt-1`…`wt-6`); `config('database.default')` = `mysql`. Los ~40 comandos `circuito:*` (`CabidaCommand`, `RamaItemCommand`, `IntegrarItemCommand`, `ReportarItemCommand`, `SubItemCommand`, `ConsultarSupervisorCommand`, `EstadoItemCommand`, etc.) y `RoadmapCircuitoService` solo hacen `Eloquent`/`DB::table(...)->insert\|update\|select` — **cero** `Schema::create/table/drop` fuera de `app/Modules/Addons/Roadmap/migrations/`. | **RUIDO**, no corrupción — es concurrencia MySQL normal (transacciones/locks de fila estándar de InnoDB) sobre lecturas/escrituras de fila. |
| **BD — DDL** (`migrate`, `db:seed`, `migrate:fresh`) | **SÍ**, misma BD; es el punto donde el riesgo se vuelve real | `GuardedMigrateCommand` (reemplaza el `migrate` nativo, inyectado en `AppServiceProvider::boot()`) + `MigrationGuardService`: en dev exige que cada migración pendiente esté commiteada y en una rama con ruta a `main`, bloquea patrones destructivos (`dropColumn`, `Schema::drop`, `truncate`, `change()`) salvo excepción madura, y trae un **candado de archivo** (`GuardedMigrateCommand.php:107-132`) que serializa `migrate` real entre las 6 `wt-N`. Incidente real documentado en `docs/bitacora-sesiones.md:1936` (2026-08-25 18:14): `phpunit` corrido en `wt-1` disparó `migrate:fresh --seed` contra la BD compartida (`phpunit.xml` mal apuntado) y vació **500 tablas**; recuperado vía PITR (`docs/bitacora-sesiones.md:1965-1975`). | **CORRUPCIÓN REAL si corre sin guardrails** — ya pasó una vez (dos, según el propio incidente "P0 segunda ocurrencia"). Con el candado de archivo + el guard de commit vigentes hoy, el riesgo está mitigado (serializado, no simultáneo), pero sigue siendo el único punto de esta tabla donde "coincidir" puede significar pérdida de datos real, no solo ruido. |
| **Cache** (`file`) | **NO** — cada worktree tiene su propio directorio, no está compartido | `grep -c '^CACHE_DRIVER=file$'` = 1 en los 6 `.env`. `config('cache.stores.file.path')` resuelve a `/home/meganet/circuito/wt-2/storage/framework/cache/data` (verificado desde `wt-2`; misma resolución relativa aplica a cada `wt-N` por tener su propio `.env`). Verificado además que `storage/` **NO es symlink** en ninguno de los 6 worktrees (`[ -L storage ]` = falso, `realpath` distinto por terminal) — es un directorio real e independiente por terminal. | **N/A — no hay coincidencia posible.** Corrige la premisa implícita del item: el cache de archivo **no** está compartido; cada terminal lee/escribe únicamente su propia copia. (`config:cache` sigue prohibido por regla dura aparte — documentado aquí, no se toca.) |
| **Colas** (`database`, tabla `jobs`) | **SÍ** — la conexión de colas `database` usa la conexión BD por defecto (`mysql`), misma tabla `jobs` de la BD compartida `megaisp` | `grep -c '^QUEUE_CONNECTION=database$'` = 1 en los 6 `.env`; `config('queue.connections.database.connection')` = `null` (usa el `database.default` = `mysql`). Los workers de Supervisor (`/etc/supervisor/conf.d/megaisp-queue.conf` ×2 procesos, `megaisp-deploy-worker.conf`) corren `php /var/www/megaisp/artisan queue:work ...` — **siempre contra el checkout PRINCIPAL**, nunca contra el código de un `wt-N`. | **Mayormente RUIDO** (jobs normales se procesan una vez, con `tries`/backoff estándar) **pero con un riesgo real acotado**: `RoadmapItem::created` (`RoadmapItem.php:507-509`) despacha `ClasificarRiesgoJob::dispatch($item->id)->afterCommit()` en cada alta de item — y `circuito:sub-item` (usado por las terminales) crea items. `RevisorService::triaje()` (línea 338) despacha `ProponerOpcionesJob`. Si el código de esos Jobs se modificó en un `wt-N` y aún no está mergeado a `main`, el worker (que ejecuta `/var/www/megaisp`) corre la versión **vieja** o falla por clase inexistente — el job se encola desde el worktree pero lo procesa el checkout principal. Acotado: solo esos 2 Jobs del módulo Roadmap despachan algo hoy; ninguno de los ~40 comandos `circuito:*` invocados directo por las terminales llama `dispatch()` — el despacho ocurre indirecto, vía el hook de modelo al crear/guardar. |

## Notas adicionales

- **Reinicio de colas desde el circuito:** ningún comando `circuito:*` ni script de
  `deploy/circuito/*.sh` ejecuta `queue:restart`/`queue:work`/`supervisorctl`. El único lugar del
  repo que hace `queue:restart` es el pipeline de **producción**
  (`app/Console/Commands/Active/RemoteDeployCommand.php:141`, tras `migrate --force`) — ajeno al
  circuito de dev. Los usos de `supervisorctl`/`queue:work` dentro de Roadmap
  (`CompuertasService.php:345,359`, `EnvironmentHealthService.php:391`,
  `CompuertasSondaCommand.php:151-155`, `JarvisVigilarCommand.php:370`) son **solo lectura de
  estado** (monitoreo), no reinicios.
- **Alcance de terminales cubierto:** las 6 `wt-N` (`wt-1`…`wt-6`), que es el pool de ejecutores del
  circuito — no se auditó el checkout principal `/var/www/megaisp` por fuera de leer su config de
  Supervisor (ese es el consumidor de colas, no un worktree del pool).

## Resumen de una línea por recurso

- **BD:** compartida siempre; filas normales = ruido tolerable; DDL (`migrate`/`seed`) = corrupción
  real si corre sin guardrails — ya pasó una vez, hoy está mitigado por candado de archivo + guard
  de commit (`MigrationGuardService`).
- **Cache:** NO compartida — cada terminal tiene su propio `storage/framework/cache/data` real (no
  symlink). Corrige la premisa del item.
- **Colas:** tabla `jobs` compartida, procesada siempre por el checkout principal
  (`/var/www/megaisp`) vía Supervisor; riesgo acotado a los 2 Jobs que el módulo Roadmap despacha
  hoy (`ClasificarRiesgoJob`, `ProponerOpcionesJob`) si su código difiere entre un worktree y `main`.
