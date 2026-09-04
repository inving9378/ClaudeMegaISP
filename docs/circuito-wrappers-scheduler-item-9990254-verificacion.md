# Item #9990254 — Wrappers de `circuito:scheduler` que no mueren + merges ambiguos: veredicto de Parte C

**Fecha:** 2026-09-04 · **Worktree:** wt-4

## Qué observó el padre (#9990254)

El 2026-09-04, mientras se mergeaban a mano items nivel C que esperaban a Irving, se detectaron dos
síntomas en el propio Circuito CC:

- **Síntoma 1 — procesos que se acumulan.** `ps` mostró 13 procesos de `circuito:scheduler` vivos a
  la vez, con edades escalonadas (22 min, 12 min, 5 min…). Los procesos viejos no tenían ningún
  `php artisan` como hijo (`ps --ppid` vacío): lo que sobrevivía era el wrapper `bash cron-wrap.sh`
  solo, después de que su `php` ya había terminado.
- **Síntoma 2 — merges que se quedan encolados sin avisar.** `php artisan circuito:merge-run`
  respondió tres veces *"Cola de merge vacía (o ya hay un drain en curso)"* y el merge encolado no
  se aplicó en ese momento, entrando minutos después cuando lo drenó el scheduler del cron.

El item original dejó explícito que **no estaba probado** que los wrappers colgados fueran la
causa del síntoma 2 (con `fuser`/`lsof`, nadie tenía tomado `merge.lock` en ese momento) y se
descompuso en tres partes: **A** (causa del síntoma 1), **B** (causa del síntoma 2) y **C** (este
item — veredicto sobre la relación entre ambos, y decisión sobre un guard de vida máxima).

## Parte A — causa y fix aplicado (#9990293, `completado`, merge `66035769`)

**Causa confirmada con `/proc`:** `cron-wrap.sh` invoca `php artisan` dentro de `$(...)` (sustitución
de comando de bash), que abre una *pipe* cuyo extremo de escritura ese proceso PHP hereda.
`SchedulerCommand::lanzarVueltaItem()` lanzaba la vuelta detached vía `Process::fromShellCommandline`
(`setsid nohup env … vuelta.sh … &`), y `proc_open` **no cierra por sí solo** los fds >2 heredados al
lanzar el hijo — ese fd de la pipe se propagaba a `setsid`/`nohup`/`timeout`/`claude`. Mientras la
vuelta siguiera viva, `cron-wrap.sh` quedaba bloqueado en `pipe_read` esperando un EOF que nunca
llegaba, **aunque su propio `php artisan` ya hubiera terminado** — de ahí el wrapper "zombie" sin
hijo `php` debajo.

**Fix aplicado:** `SchedulerCommand.php:302-304` antepone `exec 3>&- 4>&- 5>&- 6>&- 7>&- 8>&- 9>&-
2>/dev/null` al `setsid nohup …` dentro del mismo `sh -c`, cerrando esos fds heredados ANTES de que
el hijo detached los herede. Verificado que cerrar un fd no abierto no falla (con `dash`, el shell
real de `/bin/sh` en esta máquina).

## Parte B — causa y fix aplicado (#9990294, `completado`, merge `91cb2585`)

**Causa:** `MergeRunner::drain()` (línea 43) usa `flock($lock, LOCK_EX | LOCK_NB)` — no bloqueante —
sobre `/home/meganet/circuito/merge.lock`. Antes, si no conseguía el lock, devolvía el mismo tipo de
resultado ("nada que hacer") que cuando la cola de merges estaba genuinamente vacía, así que
`circuito:merge-run` (`MergeRunCommand.php:22-29`) mostraba el mismo mensaje ambiguo para ambos
casos: *"Cola de merge vacía (o ya hay un drain en curso)"*.

**Fix aplicado:** `drain()` ahora devuelve `null` cuando no pudo tomar el lock y `[]` cuando la cola
está vacía de verdad; `MergeRunCommand` distingue el mensaje según cuál de los dos ocurrió.
`SchedulerCommand` (que ignora el valor de retorno) no se vio afectado.

## Parte C — veredicto

### (b) ¿Están relacionados causalmente el síntoma 1 y el síntoma 2?

**Veredicto explícito: NO. Son dos problemas DISTINTOS, sin relación causa-efecto entre sí,**
sostenido por evidencia directa de ambas partes:

- El mecanismo del síntoma 1 (fd de pipe heredado por el wrapper detached) es enteramente interno al
  árbol de procesos `cron-wrap.sh → php artisan → setsid/nohup/timeout/claude`; no toca ni depende de
  `merge.lock` en ningún punto.
- El mecanismo del síntoma 2 (dos llamadores legítimos — el tick del scheduler cada minuto vía
  `SchedulerCommand`, y una corrida manual de `circuito:merge-run` — compitiendo por el mismo
  `flock()` no bloqueante) es una **carrera momentánea** (dura lo que tarda un `drain()`, no lo que
  tarda una vuelta completa) entre dos consumidores normales del sistema.
- El propio item padre ya había verificado con `fuser`/`lsof`, en el momento del síntoma 2, que
  **nadie tenía tomado** `merge.lock` — descartando que fuera un wrapper colgado el que retuviera el
  lock. Esto es consistente con la carrera legítima descrita arriba, no con un efecto secundario de
  los wrappers zombis.

No hay evidencia, en ninguna de las dos investigaciones, de una causa raíz compartida.

### (d) ¿Se construye un guard de vida máxima para los procesos del circuito?

**Recomendación original de este mismo item** (registrada en su spec, antes de escalarse): dado que
Parte A ya corrige la CAUSA del síntoma 1 (cierre de fds heredados) en vez de solo mitigar el
síntoma, un guard de tiempo-máximo adicional sería redundante — y matar wrappers a la fuerza toca el
despachador de TODA la flota (alto riesgo, `pkill -f` explícitamente prohibido).

**Decisión operativa registrada por Irving:** al escalarse el item (categoría *negocio*, trade-off
arquitectónico), el proceso de des-trabe generó un brief estructurado con 3 preguntas (q1 relación
causal, q2 si se construye guard, q3 qué hacer al exceder el timeout). Irving las respondió
explícitamente (log `2026-09-04 09:43:47`, `respuestas: {q1,q2,q3}`, `decision: aprobar`):

- **q1** → declarar que SÍ existe relación causal entre ambos síntomas.
- **q2** → SÍ construir un guard, pero **suave**: timeout diferenciado por tipo de brief
  (A=10 min, B=20 min, C=45 min) + señal SIGTERM antes de SIGKILL (deliberadamente **no** eligió la
  opción recomendada del brief, que era "no poner guard todavía").
- **q3** → al exceder el timeout: matar el proceso, marcar el item como FALLIDO con motivo
  `timeout`, y escalar a la bandeja de Irving con traza parcial.

Esa respuesta a q1 no coincide con el veredicto técnico de (b) — el brief generado para la
escalación planteó la pregunta de relación causal en términos genéricos ("faltan datos: ¿comparten
causa raíz?"), sin reflejar que Parte A y Parte B ya habían aislado cada mecanismo por separado con
evidencia de `/proc` y `fuser`/`lsof`. Este documento dado registra ambas cosas sin forzar que una
tape a la otra: el veredicto **técnico** de (b) es el que la evidencia sostiene (independientes); la
**decisión operativa** de construir un guard es la que Irving tomó explícitamente al resolver el
brief, y es la que gobierna lo que se ejecuta — un guard de defensa-en-profundidad no necesita que
los síntomas compartan causa para justificarse (es una capa adicional de robustez, no un parche que
sustituye al fix de raíz ya aplicado en Parte A).

**Lo que ya existe hoy (no es una construcción desde cero):** `deploy/circuito/vuelta.sh:268`
ya envuelve la llamada a `claude -p` en `timeout "$TIMEOUT" …` (`TIMEOUT` default 600 s / 10 min,
**uniforme para todos los items**, sin distinguir nivel de riesgo A/B/C). GNU `timeout` sin `-k`
manda **solo SIGTERM** al expirar — no hay SIGKILL de respaldo si el proceso lo ignora. Al terminar
mal (`RC != 0`, cualquier causa: timeout/max-turns/error — endurecido en #927), `vuelta.sh:305-313`
ya delega en `circuito:parquear-timeout`, que decide con más matiz que "matar y escalar siempre":
reanuda automáticamente (hasta un tope) si la rama tiene commits (avanzó), y solo manda a la bandeja
de Irving (`requiere_irving`) si no avanzó o agotó las reanudaciones.

Lo que la decisión de Irving pide **además** de lo que ya existe: (1) diferenciar el timeout por
nivel de riesgo del item en vez del valor uniforme de 600 s; (2) agregar el `-k` (grace SIGKILL) al
`timeout` existente; (3) revisar si el comportamiento de `circuito:parquear-timeout` en el caso
"marcar FALLIDO y escalar siempre" debe reemplazar o convivir con su lógica actual de reanudación.

**Por qué esto no se implementa en este mismo item:** #9990295 es, por su propio título y tarea, un
entregable de **documento** ("veredicto escrito"), no de código. Además, tocar el mecanismo de
timeout del despachador de la flota es exactamente el "alto riesgo" que el spec original de este
item señalaba (nunca `pkill -f`; cualquier corte manual pasa por `circuito:cortar-vuelta`) — amerita
su propio ciclo de implementación con verificación dedicada, no un cambio apurado dentro de un item
de documentación. Se deja registrado como sub-item de seguimiento (ver abajo).

## Sub-item de seguimiento creado

Implementación del guard suave decidido por Irving (q2/q3): timeout por nivel de riesgo
(A=10min/B=20min/C=45min) + `-k` (SIGKILL de respaldo tras el SIGTERM) en `deploy/circuito/vuelta.sh`,
y decidir cómo convive con la lógica de reanudación ya existente en `circuito:parquear-timeout`
(`app/Modules/Addons/Roadmap/Console/ParquearTimeoutCommand.php`).

## Conclusión

- Parte A y Parte B: causa raíz de cada síntoma confirmada y corregida, ambas mergeadas a `main`.
- Parte C: veredicto técnico explícito — síntomas independientes, sin causa común comprobada.
- Guard de vida máxima: decisión operativa de Irving es construirlo (versión suave, per-tipo,
  SIGTERM antes de SIGKILL); queda especificado como sub-item de implementación, no ejecutado aquí.
