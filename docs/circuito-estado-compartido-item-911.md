# Circuito CC #911/#912 — Inventario de estado compartido entre terminales (tabla final)

**Entregable pedido por `#912`** (Fase 1 de `#911`), consolidado en `#1000006` (Fase 1d) a partir
de los 3 docs parciales de sus fases hermanas. **Solo lectura + doc** — ningún comando de esta
vuelta tocó código funcional, configuración ni datos.

## Estado de esta consolidación (importante)

**Los 7 puntos del spec original de `#912` están cerrados** con evidencia completa: Fase 1a
(`#1000003`) + Fase 1c (`#1000005`) cubrieron los puntos 1, 3, 4, 6 y 7; Fase 1b (`#1000004`,
`docs/circuito-estado-compartido-item-911-fase-b-storage-locks.md`) cerró los puntos 2 y 5 el
2026-09-04 (commit `8ec87d68`) y este documento (`#9990052`, sub-item de seguimiento creado junto
con la primera versión de esta tabla en `#1000006`) fusiona esa evidencia sin rehacer el resto.

## Método

Consolidación directa de los 3 docs parciales ya mergeados a `main`:

- `docs/circuito-estado-compartido-item-911-fase-a-bd-cache-colas.md` (`#1000003`) — puntos 1, 3, 4.
- `docs/circuito-estado-compartido-item-911-fase-c-npm-puertos.md` (`#1000005`) — puntos 6, 7.
- `docs/circuito-estado-compartido-item-911-fase-b-storage-locks.md` (`#1000004`) — puntos 2, 5.

Sin re-verificación de campo (ya hecha en esas fases); esta vuelta solo fusiona y responde
explícitamente la pregunta de la asunción original.

## Tabla del entregable (7 puntos del spec original de `#912`)

| # | Recurso | ¿Compartido entre las 6 terminales? | Evidencia | Si 2+ vueltas coinciden |
|---|---|---|---|---|
| 1 | **BD — filas normales** (`roadmap_items`, `settings`, resto de tablas de negocio) | **SÍ** — misma BD `megaisp` en `127.0.0.1` para las 6 `wt-N` | `grep -c '^DB_DATABASE=megaisp$'`/`^DB_HOST=127.0.0.1$'` = 1 en los 6 `.env`; los ~40 comandos `circuito:*` y `RoadmapCircuitoService` solo hacen Eloquent/`DB::table()->insert\|update\|select`, cero `Schema::create/table/drop` fuera de las migraciones del propio módulo | **RUIDO**, no corrupción — concurrencia MySQL normal (locks de fila InnoDB estándar) |
| 1b | **BD — DDL** (`migrate`, `db:seed`, `migrate:fresh`) | **SÍ**, misma BD; es donde el riesgo se vuelve real | `GuardedMigrateCommand`+`MigrationGuardService`: exige migración pendiente commiteada y con ruta a `main`, bloquea patrones destructivos, y trae un candado de archivo que serializa `migrate` real entre las 6 `wt-N`. Incidente real documentado (`docs/bitacora-sesiones.md:1936`, 2026-08-25): `phpunit` mal apuntado en `wt-1` disparó `migrate:fresh --seed` contra la BD compartida y vació 500 tablas; recuperado por PITR | **CORRUPCIÓN REAL si corre sin guardrails** — ya pasó (dos veces). Con el candado de archivo + guard de commit vigentes hoy el riesgo está serializado, pero sigue siendo el único punto de la tabla donde "coincidir" puede significar pérdida de datos real |
| 2 | **`storage/` — symlinks y qué subdirectorios se comparten** | **NO** — cada worktree (`wt-1`…`wt-6`) y el checkout principal (`/var/www/megaisp`) tienen su propio `storage/` físico e independiente; NO es symlink | `stat storage` en los 7: **7 inodos distintos**, mismo `Device` (mismo filesystem, comparación válida); los 7 son `directorio` en `stat` (un symlink mostraría `enlace simbólico -> destino`); `find storage -maxdepth 3 -type l` sin resultados (tampoco hay symlinks sueltos dentro del árbol) | **N/A — no hay coincidencia posible.** Igual que la fila 3 (cache): corrige la premisa implícita del spec original ("determinar si es symlink a un directorio compartido") — no lo es, cada terminal es 100% privada (`logs/`, `framework/cache`, `framework/sessions`, `framework/views` incluidos) |
| 3 | **Cache** (`file`) | **NO** — cada worktree tiene su propio directorio, no compartido | `grep -c '^CACHE_DRIVER=file$'` = 1 en los 6 `.env`; `config('cache.stores.file.path')` resuelve a `storage/framework/cache/data` propio de cada `wt-N`; `storage/` **NO es symlink** en ninguno de los 6 worktrees (verificado, `[ -L storage ]` = falso, `realpath` distinto por terminal) | **N/A — no hay coincidencia posible.** Corrige la premisa implícita del item original: el cache de archivo no está compartido |
| 4 | **Colas** (`database`, tabla `jobs`) | **SÍ** — conexión `database` usa `mysql` (misma BD compartida `megaisp`) | `grep -c '^QUEUE_CONNECTION=database$'` = 1 en los 6 `.env`; workers de Supervisor corren `queue:work` **siempre** contra el checkout PRINCIPAL (`/var/www/megaisp`), nunca contra el código de un `wt-N` | **Mayormente RUIDO** con riesgo acotado: `RoadmapItem::created` despacha `ClasificarRiesgoJob`, `RevisorService::triaje()` despacha `ProponerOpcionesJob` — si su código difiere entre un `wt-N` y `main` (aún sin mergear), el worker corre la versión vieja. Acotado a esos 2 Jobs; ningún comando `circuito:*` invocado directo por las terminales llama `dispatch()` |
| 5 | **Locks de archivo** (estrategia general, más allá del semáforo npm) | **SÍ** — ya existen 9 locks catalogados, todos sobre rutas absolutas fijas fuera de cualquier `storage/` de worktree | Catálogo completo: `scheduler.lock`/`wt-K.lock`/`merge.lock`/`claim.lock`/`watchdog.lock`/`build-N.lock` en `/home/meganet/circuito/` (fuera de todo checkout de git); candado de migraciones + centinela del freno de mano fijos al `storage/` del checkout PRINCIPAL a propósito (nunca `storage_path()` relativo del worktree que ejecuta); `Cache::lock()` con driver `file` NO está en uso hoy (0 resultados de grep) | **RUIDO tolerado por diseño** en 8 de los 9 (no-bloqueantes que se rinden limpio, o bloqueantes que esperan su turno) — el único con riesgo de corrupción real es el candado de migraciones, que es la misma fila 1b (BD-DDL) vista desde el lado del lock: sufrió el bug de raíz de usar `storage_path()` (ruta NO compartida, candado inútil), ya corregido y verificado con concurrencia real entre `wt-1`/`wt-3` (`#9990003`) |
| 6 | **Semáforo npm** — ranuras `flock` (`build-1..N.lock`) | **SÍ**, filesystem del host (`/home/meganet/circuito/`, ruta absoluta no bifurcada por worktree) | `npm-build.sh` líneas 7-26; locks vivos verificados (`ls -la /home/meganet/circuito/*.lock`, timestamps recientes de más de una terminal); `config('circuito.max_builds')` (default 3) es la fuente única de verdad del número de ranuras | RUIDO controlado — el diseño ES el candado: la terminal que pide ranura espera (`sleep 2` + reintento) hasta que se libera una; nunca corren más de `MAX` builds a la vez. `npm run dev\|prod` compila DENTRO del árbol propio de cada worktree — sin colisión de archivos de salida entre worktrees |
| 7 | **Puertos** (servidores locales / vhosts) | **NO** para las 6 terminales automáticas — ninguna abre puerto (grep de `serve\|--port\|listen(` sobre `vuelta.sh`/`cron-wrap.sh`/`vigilia-wrap.sh`/`npm-build.sh`/`prompt-item.txt` sin resultados; `ProvisionWorktreeCommand` sin lógica de nginx/puertos) | Grep del repo + inspección de `/etc/nginx/sites-enabled/` | **N/A entre las 6 terminales** — sin bind, sin colisión posible. Existe un vhost manual aparte (`:8081`, `/var/www/megaisp-cc`, vista previa de Irving) que **no** es parte del pool automático: hoy RUIDO (instancia única, sin incidente), pero **riesgo latente** sin candado si se repitiera el mismo puerto para un segundo worktree de vista previa manual |

## La asunción original: "BD+migraciones es el único recurso exclusivo real"

**Confirmada por la evidencia completa de los 7 puntos — la Fase 1b no encontró un segundo recurso
con riesgo de corrupción real.**

Entre los 7 puntos ya verificados de punta a punta (BD filas/DDL, `storage/`, cache, colas, locks de
archivo, semáforo npm, puertos), **el único que califica como "corrupción real si dos vueltas
coinciden" sigue siendo la fila 1b (BD — DDL/`migrate`)** — ya tiene un incidente real documentado
(dos ocurrencias) y hoy está mitigado por candado de archivo + guard de commit. Los demás son RUIDO
tolerable (concurrencia MySQL normal de filas, cola compartida con riesgo acotado a 2 Jobs
específicos, locks de archivo diseñados para no-bloquear o esperar turno) o N/A (cache, `storage/` y
puertos automáticos no están compartidos entre terminales; el semáforo npm es un candado por diseño,
no una carrera). El vhost manual `:8081` es el único riesgo latente adicional fuera de BD, pero es
un procedimiento manual fuera del pool automático, no una race entre las 6 terminales — no
contradice la asunción para el flujo automatizado.

**Lo que trajo la Fase 1b (puntos 2 y 5):** cerró la duda que dejaban abierta las 5 filas
anteriores. `storage/` **no** es un directorio compartido — cada worktree tiene el suyo físico,
así que no hay ahí ninguna superficie de colisión que evaluar. Y los locks de archivo que el
circuito ya usa (`scheduler`/`wt-K`/`merge`/`claim`/`watchdog`/`build-N`/candado de migraciones/
centinela) viven todos en rutas absolutas fijas fuera del `storage/` de cualquier worktree — el
único que alguna vez usó una ruta relativa (el candado de migraciones, vía `storage_path()`) ya fue
corregido (`#9990003`) precisamente porque ese patrón repetiría, en cualquier lock nuevo que lo
usara, el mismo bug de exclusión-mutua-falsa. No quedó un segundo recurso con riesgo de corrupción
real: la asunción original se sostiene tal cual, ahora con las 7 filas cerradas en vez de 5.

## Referencia — inventario previo más amplio (item #225)

`docs/circuito/inventario-recursos-globales.md` (2026-08-28) ya cubre un superset de recursos
globales (incluye `.env`, `node_modules`, `vendor`, freno de mano, claim, merge, crontab, PIDs de
Jarvis) con su propia clasificación aislado/compartido-a-propósito/compartido-por-accidente. Este
documento (`#911`/`#912`) es más angosto en alcance (los 7 puntos específicos del prompt original)
pero coincide en las conclusiones donde se solapan (BD/migraciones como único punto de corrupción
real cubierto hoy; cache aislada por worktree; sin candados nuevos de nivel A justificados sin
evidencia de choque real).
