# Circuito CC #911/#912 Fase 1c — Semáforo npm y colisión de puertos (item #1000005)

**Fecha:** 2026-09-03 · **Solo lectura, sin cambio de código.**

Cubre los puntos 6 y 7 del spec original de #912 (los puntos 1-5 — BD, cache, colas, storage/,
locks de archivo — quedaron en la Fase 1a `docs/circuito-estado-compartido-item-911-fase-a-bd-cache-colas.md`
y la Fase 1b, item #1000004). Este documento es **tabla parcial**, insumo directo de la Fase 1d
(consolidación, item #1000006).

**Método:** lectura completa de `deploy/circuito/npm-build.sh`,
`app/Modules/Addons/Roadmap/Console/FlagsCommand.php`, `config/circuito.php`, inspección del
filesystem compartido `/home/meganet/circuito/` (locks vivos), y grep de
`serve|--port|npm run dev|npm run watch|8080|8081` sobre `deploy/`, `docs/` y los scripts del
propio circuito (`vuelta.sh`, `cron-wrap.sh`, `prompt-item.txt`).

---

## Punto 6 — Semáforo npm (`deploy/circuito/npm-build.sh`)

### Cómo serializa/limita builds concurrentes

`npm-build.sh` es un **semáforo de N ranuras por `flock`**, no un mutex simple:

1. Calcula `MAX` (número de ranuras) leyendo `php artisan circuito:flags` → línea
   `max_builds=<N>` → fuente única de verdad `config('circuito.max_builds')`
   (`config/circuito.php:207`, default `env('CIRCUITO_MAX_BUILDS', 3)`, item #873). Si `artisan`
   falla (worktree roto, BD caída), cae a la env o al literal `3` — **nunca bloquea el build por
   una falla ajena**.
2. Bucle `while [ -z "$FD" ]`: recorre `build-1.lock` .. `build-$MAX.lock` en
   `$RUNTIME/build-$i.lock` (con `RUNTIME="/home/meganet/circuito"`, ruta ABSOLUTA hardcodeada,
   no relativa al worktree) e intenta `flock -n` (no bloqueante) sobre cada uno. La primera
   ranura libre gana; si las `MAX` están tomadas, duerme 2s y reintenta.
3. Con la ranura tomada corre `npm run "$MODE" "$@"` (`MODE` = `dev` por default, `prod` si
   `CIRCUITO_BUILD_MODE=prod`, usado por `MergeRunner` al rebuild-on-merge, #432 adenda A).
4. Libera el fd (y por tanto el `flock`) al terminar, pase lo que pase (no hay `trap`, pero el
   cierre del fd al final del script ya lo suelta; si el script muere a medias por señal, el `flock`
   se libera solo cuando el proceso termina — comportamiento estándar de `flock`, sin fd huérfano
   persistente).

`npm run dev` (y `prod`) son **builds de un solo tiro** (`webpack --config webpack.mix.js`), no
servidores persistentes — confirmado por `CLAUDE.md` ("`npm run dev # build único`") y porque
`npm-build.sh` espera a que `npm run "$MODE"` termine (`RC=$?`) antes de soltar la ranura. No hay
proceso que quede vivo tras el build, ni puerto que abrir.

### ¿El estado del semáforo es compartido o cada worktree tiene su copia sin coordinar?

**Compartido de verdad, no solo el código.** `npm-build.sh` está trackeado en git → cada worktree
(`wt-1`..`wt-6`) tiene su propia COPIA del archivo (normal: cada uno vive en su propia rama). Pero
la variable `RUNTIME="/home/meganet/circuito"` es una **ruta absoluta hardcodeada que no se
bifurca por worktree** (a diferencia de `$(pwd)` o `__DIR__`) — los locks `build-1.lock`,
`build-2.lock`, `build-3.lock` viven en el filesystem compartido del host, **fuera de cualquier
worktree**. Verificado en vivo (`ls -la /home/meganet/circuito/*.lock`): los 3 archivos de ranura
existen ahí, con timestamps recientes (`sep 3 17:40/17:41`) que confirman uso real reciente por
más de una terminal.

Conclusión: aunque el script "vive" 6 veces (una copia por worktree vía git), su **estado runtime**
(las ranuras `flock`) es una única instancia compartida por el host — exactamente el diseño
correcto para un semáforo cross-terminal. Ninguna terminal puede "colarse" con una ranura propia
sin coordinar: todas apuntan al mismo directorio absoluto.

---

## Punto 7 — Puertos fijos y colisión de servidores locales

### Grep del repo (deploy/, docs/, scripts del circuito)

- **Ningún wrapper del circuito** (`vuelta.sh`, `cron-wrap.sh`, `vigilia-wrap.sh`, `npm-build.sh`,
  `prompt-item.txt`) invoca `php artisan serve`, `npm run watch` (long-running) ni ningún proceso
  que haga `listen()`/`bind()` a un puerto. `npm-build.sh` solo corre builds de un tiro (ver punto
  6) — no hay servidor que levantar ni puerto que reservar en el flujo automático de las 6
  terminales.
- **Sí existe un servidor fijo, pero es de un patrón DISTINTO y ya documentado**: el vhost de
  vista previa `/etc/nginx/sites-enabled/megaisp-cc-preview.conf`, puerto **8081 fijo**, sirviendo
  `/var/www/megaisp-cc` (worktree manual de Irving para revisar frontend en rama antes de
  mergear — decisión del 2026-08-27, ver memoria del proyecto
  `project-worktree-cc-vista-previa`). Confirmado vivo en el filesystem actual:
  - `/etc/nginx/sites-enabled/megaisp-cc-preview.conf` existe (puerto `8081` hardcodeado en
    `listen 8081;` / `listen [::]:8081;`).
  - `/var/www/megaisp-cc/` existe como directorio de 21 entradas (worktree provisionado).
- **Este servidor NO es parte del circuito de 6 terminales paralelas** (`wt-1`..`wt-6`,
  `/home/meganet/circuito/wt-K`): es una **instancia única, manual, fuera del pool automático**,
  levantada una sola vez por Irving. `ProvisionWorktreeCommand.php` (el comando que SÍ usan las 6
  terminales del circuito para nacer) **no tiene ninguna lógica de puertos/nginx/vhost** (grep
  sin resultados) — confirma que el aprovisionamiento automático de `wt-1..wt-6` nunca crea
  servidores HTTP propios ni toca nginx. Los `wt-K` solo compilan a su propio `public/js` (aislado
  por directorio de worktree, fila ya documentada en `inventario-recursos-globales.md`); nadie les
  sirve tráfico HTTP — el único tráfico real lo sirve el checkout principal
  (`/var/www/megaisp`, puerto 80).

### ¿El patrón actual evita colisión o es riesgo latente?

Dos situaciones distintas, cada una con su propia respuesta:

1. **Las 6 terminales automáticas entre sí:** SIN RIESGO. Ninguna abre puerto — no hay colisión
   posible porque no hay bind. El único "servidor" que las 6 terminales tocan de forma automática
   es el checkout principal, que ya es una instancia única gestionada fuera del circuito (mismo
   patrón que Evolution API `:8080`/AMI `5038`/GPS listener `:5027`, fila ya cubierta en
   `inventario-recursos-globales.md`: "El SO ya es candado natural — un segundo `bind()` al mismo
   puerto falla ruidoso").
2. **El vhost manual `:8081` (`megaisp-cc`) si se repitiera:** RIESGO LATENTE, no cubierto por
   ningún candado de código. Es un procedimiento **manual** (comando `circuito:provision-worktree
   --path=... --base=main` + copiar a mano el `.conf` de nginx + `chgrp`/`chmod` a `www-data`), sin
   automatización que impida que alguien (Irving u otra sesión) repita el mismo puerto `8081` para
   un SEGUNDO worktree de "vista previa" — ahí sí colisionaría (`nginx -t`/reload fallaría o el
   segundo `server { listen 8081; }` pisaría al primero, dependiendo del orden de carga). Hoy es
   una sola instancia y nadie más lo ha vuelto a levantar (sin incidente registrado), así que no se
   propone candado nuevo — se documenta como riesgo latente de un procedimiento manual, no del
   circuito automático.

---

## Tabla parcial (puntos 6-7, para consolidar en Fase 1d)

| Recurso | Compartido SI/NO | Evidencia | Si 2 vueltas coinciden |
|---|---|---|---|
| Semáforo npm — ranuras `flock` (`build-1..N.lock`) | **SÍ**, filesystem del host (`/home/meganet/circuito/`, ruta absoluta no bifurcada por worktree) | `npm-build.sh` líneas 7-26; locks vivos verificados (`ls -la /home/meganet/circuito/*.lock`, timestamps recientes de más de una terminal) | RUIDO controlado — el diseño ES el candado: la 2ª/3ª/4ª terminal que pide ranura espera (`sleep 2` + reintento) hasta que se libera una; nunca corren más de `MAX` builds a la vez. Sin corrupción. |
| Config `max_builds` (`config('circuito.max_builds')` vía `circuito:flags`) | SÍ (config compartida, lectura) | `config/circuito.php:207`, `FlagsCommand.php:28` | RUIDO — es solo lectura por todas las terminales; ninguna la escribe en runtime. |
| `npm run dev\|prod` en sí (el build) | NO — cada worktree compila a su PROPIO `public/js` (árbol de trabajo propio, ya documentado en `inventario-recursos-globales.md`) | `CLAUDE.md` ("build único"); `npm-build.sh` línea 31 corre dentro del `cwd` del ejecutor | N/A — no hay recurso compartido en el build en sí, solo la ranura de concurrencia (fila de arriba). Sin colisión de archivos de salida entre worktrees. |
| Puertos de servidores automáticos del circuito (`wt-1..wt-6`) | NO — ninguna terminal automática abre puerto (grep sin resultados en `vuelta.sh`/`cron-wrap.sh`/`vigilia-wrap.sh`/`npm-build.sh`/`prompt-item.txt`; `ProvisionWorktreeCommand.php` sin lógica de nginx/puertos) | grep de `serve\|--port\|listen(` sobre los scripts del circuito → 0 resultados | N/A — no hay bind, no hay colisión posible por diseño. |
| Vhost manual de vista previa `:8081` (`/var/www/megaisp-cc`) | Compartido a propósito, pero es **UNA sola instancia manual**, fuera del pool automático | `/etc/nginx/sites-enabled/megaisp-cc-preview.conf` (vivo, `listen 8081`); `/var/www/megaisp-cc/` (vivo); memoria de proyecto `project-worktree-cc-vista-previa` | Hoy: RUIDO (una sola instancia, sin incidente). Riesgo LATENTE sin candado: si se repitiera el mismo procedimiento manual con el mismo puerto para un segundo worktree de vista previa, el segundo `nginx -t`/reload fallaría o pisaría al primero — no es corrupción de datos (nginx no arranca el conflictivo), pero sí bloquea la vista previa hasta corregir el `.conf`. |

## Conclusión

Ningún hallazgo cae en las cuatro fronteras duras (producción · borrar datos · gastar dinero ·
credenciales) ni tiene evidencia de choque real ocurrido — no se propone candado nuevo en esta
Fase (el propio spec de #912 pide "no inventar aislamiento sin medir primero", igual que ya
concluyó el inventario del item #225). El semáforo npm (punto 6) ya está correctamente diseñado
como recurso compartido coordinado. El único riesgo latente real (punto 7) es el procedimiento
MANUAL de vista previa en `:8081` si se repitiera sin asignar un puerto distinto — queda anotado
para que la Fase 1d lo incluya en la tabla consolidada, y para que si algún día se necesita un
segundo vhost de vista previa, quien lo levante sepa que debe usar un puerto distinto a `8081`.
