# Circuito CC #911/#912 — Inventario de estado compartido entre terminales (tabla final)

**Entregable pedido por `#912`** (Fase 1 de `#911`), consolidado en `#1000006` (Fase 1d) a partir
de los 3 docs parciales de sus fases hermanas. **Solo lectura + doc** — ningún comando de esta
vuelta tocó código funcional, configuración ni datos.

## Estado de esta consolidación (importante)

De los 7 puntos del spec original de `#912`, **5 están cerrados** con evidencia completa (Fase 1a +
Fase 1c, ambas ya en `main`). Los **puntos 2 y 5** (`storage/` symlinks y estrategia de locks de
archivo) dependen de `#1000004` (Fase 1b), que sigue **`requiere_irving`**: tiene 3 preguntas
estructuradas sin `opcion_elegida` (qué subdirectorios de `storage/` compartir vs aislar, y qué
mecanismo de lock usar — ver el propio item). Tras **10 reverificaciones** de sesiones anteriores
(`wt-1`/`wt-2`/`wt-5`, 2026-09-03 18:26→18:38) sin que esa dependencia avanzara, esta vuelta decide
**entregar la tabla parcial ahora** (5/7 puntos, honesta sobre lo que falta) en vez de repetir una
11ª comprobación idéntica sin producir nada — decisión registrada vía
`circuito:reportar --tipo=decision`. Cuando `#1000004` cierre, el sub-item de seguimiento creado
junto con este documento se encargará de completar las filas 2 y 5 sin rehacer el resto.

## Método

Consolidación directa de los 2 docs parciales ya mergeados a `main`:

- `docs/circuito-estado-compartido-item-911-fase-a-bd-cache-colas.md` (`#1000003`) — puntos 1, 3, 4.
- `docs/circuito-estado-compartido-item-911-fase-c-npm-puertos.md` (`#1000005`) — puntos 6, 7.

Sin re-verificación de campo (ya hecha en esas fases); esta vuelta solo fusiona y responde
explícitamente la pregunta de la asunción original.

## Tabla del entregable (7 puntos del spec original de `#912`)

| # | Recurso | ¿Compartido entre las 6 terminales? | Evidencia | Si 2+ vueltas coinciden |
|---|---|---|---|---|
| 1 | **BD — filas normales** (`roadmap_items`, `settings`, resto de tablas de negocio) | **SÍ** — misma BD `megaisp` en `127.0.0.1` para las 6 `wt-N` | `grep -c '^DB_DATABASE=megaisp$'`/`^DB_HOST=127.0.0.1$'` = 1 en los 6 `.env`; los ~40 comandos `circuito:*` y `RoadmapCircuitoService` solo hacen Eloquent/`DB::table()->insert\|update\|select`, cero `Schema::create/table/drop` fuera de las migraciones del propio módulo | **RUIDO**, no corrupción — concurrencia MySQL normal (locks de fila InnoDB estándar) |
| 1b | **BD — DDL** (`migrate`, `db:seed`, `migrate:fresh`) | **SÍ**, misma BD; es donde el riesgo se vuelve real | `GuardedMigrateCommand`+`MigrationGuardService`: exige migración pendiente commiteada y con ruta a `main`, bloquea patrones destructivos, y trae un candado de archivo que serializa `migrate` real entre las 6 `wt-N`. Incidente real documentado (`docs/bitacora-sesiones.md:1936`, 2026-08-25): `phpunit` mal apuntado en `wt-1` disparó `migrate:fresh --seed` contra la BD compartida y vació 500 tablas; recuperado por PITR | **CORRUPCIÓN REAL si corre sin guardrails** — ya pasó (dos veces). Con el candado de archivo + guard de commit vigentes hoy el riesgo está serializado, pero sigue siendo el único punto de la tabla donde "coincidir" puede significar pérdida de datos real |
| 2 | **`storage/` — symlinks y qué subdirectorios se comparten** | **⏳ PENDIENTE** — bloqueado en `#1000004` (Fase 1b), `requiere_irving` sin `opcion_elegida` (q1: symlinks + locks; q2: qué subdirs compartir vs aislar) | — | — |
| 3 | **Cache** (`file`) | **NO** — cada worktree tiene su propio directorio, no compartido | `grep -c '^CACHE_DRIVER=file$'` = 1 en los 6 `.env`; `config('cache.stores.file.path')` resuelve a `storage/framework/cache/data` propio de cada `wt-N`; `storage/` **NO es symlink** en ninguno de los 6 worktrees (verificado, `[ -L storage ]` = falso, `realpath` distinto por terminal) | **N/A — no hay coincidencia posible.** Corrige la premisa implícita del item original: el cache de archivo no está compartido |
| 4 | **Colas** (`database`, tabla `jobs`) | **SÍ** — conexión `database` usa `mysql` (misma BD compartida `megaisp`) | `grep -c '^QUEUE_CONNECTION=database$'` = 1 en los 6 `.env`; workers de Supervisor corren `queue:work` **siempre** contra el checkout PRINCIPAL (`/var/www/megaisp`), nunca contra el código de un `wt-N` | **Mayormente RUIDO** con riesgo acotado: `RoadmapItem::created` despacha `ClasificarRiesgoJob`, `RevisorService::triaje()` despacha `ProponerOpcionesJob` — si su código difiere entre un `wt-N` y `main` (aún sin mergear), el worker corre la versión vieja. Acotado a esos 2 Jobs; ningún comando `circuito:*` invocado directo por las terminales llama `dispatch()` |
| 5 | **Locks de archivo** (estrategia general, más allá del semáforo npm) | **⏳ PENDIENTE** — bloqueado en `#1000004` (Fase 1b), misma dependencia que el punto 2 (q3: `flock()` nativo vs `Cache::lock()` vs BD, sin `opcion_elegida`) | — | — |
| 6 | **Semáforo npm** — ranuras `flock` (`build-1..N.lock`) | **SÍ**, filesystem del host (`/home/meganet/circuito/`, ruta absoluta no bifurcada por worktree) | `npm-build.sh` líneas 7-26; locks vivos verificados (`ls -la /home/meganet/circuito/*.lock`, timestamps recientes de más de una terminal); `config('circuito.max_builds')` (default 3) es la fuente única de verdad del número de ranuras | RUIDO controlado — el diseño ES el candado: la terminal que pide ranura espera (`sleep 2` + reintento) hasta que se libera una; nunca corren más de `MAX` builds a la vez. `npm run dev\|prod` compila DENTRO del árbol propio de cada worktree — sin colisión de archivos de salida entre worktrees |
| 7 | **Puertos** (servidores locales / vhosts) | **NO** para las 6 terminales automáticas — ninguna abre puerto (grep de `serve\|--port\|listen(` sobre `vuelta.sh`/`cron-wrap.sh`/`vigilia-wrap.sh`/`npm-build.sh`/`prompt-item.txt` sin resultados; `ProvisionWorktreeCommand` sin lógica de nginx/puertos) | Grep del repo + inspección de `/etc/nginx/sites-enabled/` | **N/A entre las 6 terminales** — sin bind, sin colisión posible. Existe un vhost manual aparte (`:8081`, `/var/www/megaisp-cc`, vista previa de Irving) que **no** es parte del pool automático: hoy RUIDO (instancia única, sin incidente), pero **riesgo latente** sin candado si se repitiera el mismo puerto para un segundo worktree de vista previa manual |

## La asunción original: "BD+migraciones es el único recurso exclusivo real"

**Confirmada por la evidencia disponible (5 de 7 puntos), con una salvedad honesta.**

Entre los 5 puntos ya verificados de punta a punta (BD filas/DDL, cache, colas, semáforo npm,
puertos), **el único que califica como "corrupción real si dos vueltas coinciden" es la fila 1b (BD
— DDL/`migrate`)** — ya tiene un incidente real documentado (dos ocurrencias) y hoy está mitigado
por candado de archivo + guard de commit. Los demás son RUIDO tolerable (concurrencia MySQL normal
de filas, cola compartida con riesgo acotado a 2 Jobs específicos) o N/A (cache y puertos
automáticos no están compartidos; el semáforo npm es un candado por diseño, no una carrera). El
vhost manual `:8081` es el único riesgo latente adicional fuera de BD, pero es un procedimiento
manual fuera del pool automático, no una race entre las 6 terminales — no contradice la asunción
para el flujo automatizado.

**La salvedad:** los puntos 2 y 5 (`storage/` symlinks y locks de archivo en general) son
precisamente el área que `#1000004` está evaluando — y es plausible que ahí exista otro recurso con
riesgo de corrupción real (ej. si dos terminales escriben el mismo archivo de `storage/` sin lock).
No se puede confirmar ni descartar con la evidencia de hoy; **es la razón misma por la que
`#1000004` sigue abierto**. Este documento se actualizará (filas 2 y 5) cuando esa fase cierre, sin
necesidad de rehacer las filas 1, 3, 4, 6 y 7.

## Referencia — inventario previo más amplio (item #225)

`docs/circuito/inventario-recursos-globales.md` (2026-08-28) ya cubre un superset de recursos
globales (incluye `.env`, `node_modules`, `vendor`, freno de mano, claim, merge, crontab, PIDs de
Jarvis) con su propia clasificación aislado/compartido-a-propósito/compartido-por-accidente. Este
documento (`#911`/`#912`) es más angosto en alcance (los 7 puntos específicos del prompt original)
pero coincide en las conclusiones donde se solapan (BD/migraciones como único punto de corrupción
real cubierto hoy; cache aislada por worktree; sin candados nuevos de nivel A justificados sin
evidencia de choque real).
