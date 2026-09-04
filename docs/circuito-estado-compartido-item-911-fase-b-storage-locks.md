# Circuito CC #911/#912 — Fase 1b: `storage/` symlinks y locks de archivo

Sub-item de seguimiento de `#912` (Fase 1 de `#911`). **Solo lectura** — ningún comando de esta
vuelta modificó código funcional, configuración ni datos; todo lo de abajo es verificación directa
contra el filesystem (`stat`/`find`) y lectura de fuente (`grep`/`Read`) de los comandos y scripts
del circuito.

Hermanos de esta fase (ver `docs/roadmap-bucle-reap-item-912-verificacion.md`): `#1000003`/Fase 1a
ya cubrió BD/Cache/Colas (`docs/circuito-estado-compartido-item-911-fase-a-bd-cache-colas.md` —
adelantó ahí, de paso, que `storage/` no es symlink; este documento confirma ese punto con
evidencia de inodo y añade el catálogo completo de locks de archivo, punto 5 del spec original que
la Fase 1a no cubrió), `#1000005` (Fase 1c — semáforo npm y colisión de puertos), `#1000006` (Fase
1d — consolidación final). Este documento cubre **solo** los puntos 2 y 5 del spec original de
`#912`: `storage/` y locks de archivo.

## Método

- `stat storage` en la raíz de los 6 worktrees (`/home/meganet/circuito/wt-{1..6}`) y de
  `/var/www/megaisp` — compara `Inode:` y `Device:` para confirmar si apuntan al mismo directorio
  físico o son directorios independientes.
- `find storage -maxdepth 3 -type l` en `wt-1` — busca symlinks dentro del árbol (no solo en la
  raíz), por si algún subdirectorio puntual sí lo fuera aunque `storage/` en sí no lo sea.
- Locks: grep de `flock\|LOCK_EX\|\.lock` en `app/Modules/Addons/Roadmap/Console/`,
  `app/Modules/Addons/Roadmap/Services/MergeRunner.php`, `app/Console/Commands/GuardedMigrateCommand.php`
  y los scripts `deploy/circuito/*.sh`, seguido de lectura directa de cada archivo fuente para
  documentar el mecanismo real (no solo el nombre del lock).
- `ls -la /home/meganet/circuito` (el directorio `RUNTIME` fijo, fuera de todo worktree) para
  catalogar qué archivos `.lock` existen hoy en vivo.
- Config: `config/session.php`, `config/cache.php`, `.env` de `wt-1` y `/var/www/megaisp`, y
  `vendor/laravel/framework/.../Illuminate/Cache/` para confirmar soporte de `Cache::lock()`.

## Hallazgo principal — `storage/` NO es symlink, cada worktree tiene el suyo real

```
wt-1: Inode 3846535   wt-4: Inode 3977991
wt-2: Inode 4109292   wt-5: Inode 3978049
wt-3: Inode 3978194   wt-6: Inode 3977942
/var/www/megaisp: Inode 558582
```

7 inodos distintos, mismo `Device: 8,1` (mismo filesystem, así que la comparación de inodo es
válida). Los 7 son `directorio` en `stat` (un symlink mostraría `enlace simbólico` + `-> destino`).
`find storage -maxdepth 3 -type l` en `wt-1` no devolvió nada — tampoco hay symlinks sueltos
*dentro* del árbol de `storage/`. **Confirmado: cada worktree (y el checkout principal) tiene su
propio `storage/` físico e independiente.** `logs/`, `framework/cache`, `framework/sessions` y
`framework/views` **NO están compartidos entre terminales** — corrige de raíz la premisa del punto
2 del spec original de `#912` ("determinar si es symlink a un directorio compartido").

Consecuencia directa ya verificada en la Fase 1a: como `CACHE_DRIVER=file` y `SESSION_DRIVER=file`
resuelven vía `storage_path(...)`, cache y sesiones de archivo son 100% privados por terminal — sin
ningún riesgo de colisión entre vueltas, pero también sin ningún beneficio de compartir estado ahí.

## Catálogo de locks de archivo del circuito

| Lock | Ruta (fija/relativa) | Mecanismo | Qué pasa si 2 vueltas coinciden |
|---|---|---|---|
| **`scheduler.lock`** (`SchedulerCommand.php:31`) | `/home/meganet/circuito/scheduler.lock` — **fija**, fuera de todo worktree | `flock(LOCK_EX\|LOCK_NB)`; si no lo consigue, `return SUCCESS` inmediato (no espera, no falla) | **RUIDO** — el cron corre cada minuto; si el scheduler anterior sigue vivo, la corrida nueva simplemente no hace nada y sale limpia. Sin deadlock posible (no-bloqueante). |
| **`{sid}.lock`** patrón `wt-K.lock` (`SchedulerCommand.php:249` lo *sondea*; `vuelta.sh:27,80` lo *sostiene*) | `/home/meganet/circuito/{SID}.lock` (ej. `wt-1.lock`) — **fija**, fuera del worktree que representa | `vuelta.sh` abre el fd 9 sobre el lock y hace `flock -n 9` **al arrancar y lo mantiene todo el proceso** (se libera solo al terminar el script); el scheduler solo lo *prueba* (`LOCK_EX\|LOCK_NB` + `LOCK_UN` inmediato) para saber "¿este slot está libre?" sin tomarlo él mismo | **RUIDO** — es justo el mecanismo anti-solape: si el slot `wt-K` ya tiene una vuelta corriendo, `vuelta.sh` nuevo loggea "Otra vuelta en curso (lock ocupado). Salgo." y sale con `exit 0`. Es el semáforo de paralelismo (#334), no puede corromper nada — impide que dos vueltas compartan el mismo worktree a la vez. |
| **`merge.lock`** (`MergeRunner.php:27`) | `/home/meganet/circuito/merge.lock` — **fija** | `flock(LOCK_EX\|LOCK_NB)`; si ocupado, la corrida de `drain()` simplemente no hace nada esa vez (el scheduler la reintenta en la siguiente pasada) | **RUIDO** — serializa los merges a `main` (aunque varias vueltas terminen a la vez y llamen a `MergeRunner`, solo una drena la cola por corrida; nunca dos `git merge` en paralelo). |
| **`claim.lock`** (`ClaimNextCommand.php:20`) | `/home/meganet/circuito/claim.lock` — **fija** | `flock(LOCK_EX)` **bloqueante** (sin `LOCK_NB`) — espera su turno en vez de fallar | **RUIDO** — serializa el reclamo atómico de items (#341): dos workers nunca "ganan" el mismo item porque el segundo espera a que el primero suelte el lock antes de leer/actualizar `estado_aprobacion`. |
| **`watchdog.lock`** (`WatchdogCommand.php:19`) | `/home/meganet/circuito/watchdog.lock` — **fija** | `flock(LOCK_EX\|LOCK_NB)`; ocupado → sale sin relanzar nada | **RUIDO** — un solo watchdog vivo a la vez, igual patrón que `scheduler.lock`. |
| **`build-{1..MAX}.lock`** (`npm-build.sh`) | `/home/meganet/circuito/build-N.lock` — **fija**, N = `config('circuito.max_builds')` vía `circuito:flags` | Semáforo de **hasta MAX ranuras**: cada ejecutor intenta `flock -n` sobre `build-1.lock`…`build-N.lock` en orden hasta conseguir una; si todas están tomadas, `sleep 2` y reintenta (bloqueante con backoff, no falla nunca) | **RUIDO por diseño** — el box es de 4 cores y varios `npm run dev`/`prod` simultáneos lo ahogarían; el semáforo limita concurrencia real de builds, no arbitra acceso a un recurso compartido con estado. |
| **Candado de migraciones** (`GuardedMigrateCommand.php:109-129`) | `config('circuito.candado_migraciones')` = `/var/www/megaisp/storage/app/circuito/migrate-esquema.lock` — **fija, apunta al checkout PRINCIPAL**, nunca `storage_path()` del worktree que ejecuta | `flock(LOCK_EX\|LOCK_NB)`; si ocupado, reintenta `flock(LOCK_EX)` **bloqueante** (espera su turno, no falla) | **CORRUPCIÓN REAL evitada por diseño** — `migrate` modifica el ESQUEMA de la BD compartida (`megaisp`, ver Fase 1a). El comentario del propio archivo (líneas 102-103) documenta que antes usaba `storage_path()` y por eso "cada worktree tomaba SU candado privado" (candado inútil, cada terminal creía tener exclusividad sobre un archivo que nadie más veía) — bug ya corregido (ver item `#9990003` en `CLAUDE.md`, verificado ahí con prueba de concurrencia real entre `wt-1` y `wt-3`). Es el único lock de esta tabla donde el bug de raíz (usar una ruta que `storage/` NO comparte) ya causó una falsa sensación de exclusión mutua en el pasado. |
| **CENTINELA del freno de mano** (`vuelta.sh:23-25`, item `#170`) | `/var/www/megaisp/storage/app/circuito/PAUSA` — **fija, checkout principal** (`CIRCUITO_FRENO_CENTINELA` env, default a esa ruta) | No es un `flock`: es un archivo-bandera que se consulta con `test -e` (sin PHP, sin BD) en cada iteración | **N/A (no es lock de exclusión, es una señal de lectura)** — el propio comentario del script explica por qué la ruta es absoluta al checkout principal y no relativa: *"cada worktree tiene su propio `storage/` real — una ruta relativa daría un freno por terminal"*. Mismo patrón de corrección que el candado de migraciones, aplicado preventivamente aquí. |
| **`guard-bd-pruebas.log`** (`guard-bd-pruebas.sh:30`) | `/var/www/megaisp/storage/app/circuito/guard-bd-pruebas.log` — **fija, checkout principal** | Sin lock propio; es un log de auditoría del guard de BD de pruebas (incidente 2026-08-25, ver Fase 1a) | **N/A** — no arbitra concurrencia, solo registra. |
| **`Cache::lock()` (Laravel, driver `file`)** | N/A — no está en uso | `vendor/.../Illuminate/Cache/FileLock.php` existe → el driver `file` **sí soporta** `Cache::lock()`. Grep de `Cache::lock\|Cache::restoreLock` en `app/Modules/Addons/Roadmap/` = **0 resultados** | **No aplica hoy** — el circuito no usa esta API; todos sus locks son `flock()` crudo sobre rutas fijas. Si algún día se usara `Cache::lock()` con el driver `file` (que resuelve a `storage_path('framework/cache/data')`), **repetiría el mismo bug que ya se corrigió en el candado de migraciones**: cada worktree tiene su propio `storage/`, así que el lock sería privado por terminal, no real. Riesgo latente a vigilar si se introduce, no un problema hoy — no se toca código, solo se deja anotado. |

## Patrón consistente que emerge de la tabla

Todo lock que necesita **exclusión real entre las 6 terminales** vive en una ruta **absoluta y
fija** fuera de cualquier `storage/` de worktree — o en `/home/meganet/circuito/` (el directorio
`RUNTIME` del circuito, dedicado, fuera de todo checkout de git) o en el `storage/` del **checkout
principal** `/var/www/megaisp` (nunca relativo/`storage_path()` del worktree que ejecuta). Los dos
casos donde el código en algún momento usó `storage_path()` (candado de migraciones) o podría
tentar a usarlo (`Cache::lock()` con driver `file`) son precisamente los puntos de riesgo — el
primero ya se corrigió (`#9990003`), el segundo no está en uso. `storage/` de worktree, al no ser
compartido, es el lugar **equivocado** para cualquier coordinación entre terminales; el circuito ya
lo sabe y evita esa ruta en todos sus locks activos.

## Resumen de una línea por recurso

- **`storage/`:** NO es symlink — cada worktree (`wt-1`…`wt-6`) y el checkout principal tienen su
  propio directorio físico e independiente (7 inodos distintos verificados). Corrige la premisa del
  punto 2 del spec original.
- **Locks del circuito propiamente (`scheduler`/`wt-K`/`merge`/`claim`/`watchdog`/`build-N`):** todos
  `flock()` sobre rutas fijas en `/home/meganet/circuito/`, fuera de cualquier `storage/` — RUIDO
  tolerado por diseño (no-bloqueantes que se rinden limpio, o bloqueantes que esperan su turno).
- **Candado de migraciones + centinela del freno de mano:** fijos al `storage/` del checkout
  PRINCIPAL a propósito — el único lock donde coincidir sin candado sería CORRUPCIÓN REAL (esquema
  de BD compartida), y es justo el que ya sufrió el bug de raíz de `storage_path()` (corregido en
  `#9990003`).
- **`Cache::lock()` con driver `file`:** no está en uso en el circuito hoy; si se introdujera,
  heredaría el mismo bug de `storage/` no-compartido — queda anotado como riesgo latente, no como
  hallazgo activo.
