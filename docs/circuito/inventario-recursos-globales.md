# Inventario de recursos globales compartidos por las seis terminales (item #225)

**Fecha:** 2026-08-28 · **Paso 0 (read-only) + clasificación + candados de nivel A.**

## Contexto

El aislamiento por worktree (#334 Fase 0) separó los ARCHIVOS de las seis terminales del Circuito
(cada una vive en `/home/meganet/circuito/wt-K`, con su propia rama y su propio `App\` resuelto por
Composer). Pero todas corren contra los MISMOS recursos globales del box. El 22-ago y el 25-ago el
recurso que estalló fue `phpunit.xml` → la base `megaisp` (candado ya aplicado: 04ec4395 + bcf478c2
+ este mismo inventario lo confirma abajo). Este documento es el inventario exhaustivo pedido por el
item: qué toca una terminal fuera de su propio worktree, y para cada recurso, si ya está candado, si
necesita candado, o si el aislamiento es imposible y hay que convivir.

**Método:** lectura de `deploy/circuito/vuelta.sh`, `cron-wrap.sh`, `vigilia-wrap.sh`,
`npm-build.sh`, `guard-bd-pruebas.sh`, `prompt-item.txt`, `ProvisionWorktreeCommand.php`, y grep de
`flock`/`crontab`/`Redis::` sobre `app/`. No se corrió nada destructivo; todo lo de abajo es
observación de código + configuración ya vigente.

## Corrección de dos premisas del propio item (hallazgo, no candado)

El texto del item asumía dos cosas que la lectura del código desmiente:

1. **"`.env` — uno por worktree; verificar que no diverjan"** — FALSO. `ProvisionWorktreeCommand`
   symlinkea `.env` desde el checkout principal (`SHARED_LINKS`): las seis terminales usan el
   **mismo archivo** (mismo inode), no seis copias que puedan divergir. No pueden divergir porque
   no hay six copias.
2. **"Caché — un `cache:clear` de una invalida el de las otras cinco"** — FALSO hoy. `CACHE_DRIVER`
   y `SESSION_DRIVER` son `file` (no Redis), y `storage/framework/cache/data` /
   `storage/framework/sessions` / `bootstrap/cache` son directorios **propios** de cada worktree
   (`ProvisionWorktreeCommand::OWN_DIRS`, no symlink). Un `cache:clear` en wt-2 no toca wt-3.

## Tabla de clasificación

| Recurso | Tipo | Quién lo toca | Riesgo de choque | Estrategia / estado | Patrón reusado |
|---|---|---|---|---|---|
| BD pruebas `megaisp_test` (phpunit) | Compartido a propósito | Suite (`php artisan test`) desde cualquier worktree | Ya candado — origen del incidente 2x | ✅ Candado DUAL: `tests/GuardBaseDePruebas.php` (PHP, en el repo) + `guard-bd-pruebas.sh` (bash, ruta ABSOLUTA que no se bifurca, invocado por `vuelta.sh` y `cron-wrap.sh` ANTES de soltar el agente). Fail-closed, log en `storage/app/circuito/guard-bd-pruebas.log`. | — (es el patrón madre) |
| BD app `megaisp` (dev, real) | Compartido a propósito | `migrate`, `db:seed`, `tinker`, cualquier DDL/DML de un item | Medio (mitigado) | ✅ `migrate` candado por `MigrationGuardService` (#534): exige migración pendiente commiteada + con ruta a main antes de correr; fail-open solo ante fallas de infraestructura ajenas (git caído), nunca fail-closed sobre el propio estado que protege (lección del 25-ago). `db:seed`/`tinker`/DDL directo **NO** están candados (no se puede candar código arbitrario) — riesgo residual cubierto por `backup_db:process` diario (retención 14 días). | MigrationGuardService (git-based, no consulta la BD que protege) |
| Instancia de vuelta por worktree | Aislado | `vuelta.sh` | Nulo | ✅ `flock -n 9` sobre `$RUNTIME/${SID}.lock`: una vuelta a la vez POR slot. | flock por-SID |
| Claim de items de la Hoja de Ruta | Compartido a propósito | `circuito:claim-next` (todas las terminales) | Nulo | ✅ `ClaimNextCommand` flock `claim.lock` — reclamo atómico (#341), dos workers nunca toman el mismo item. | flock global serializado |
| Merge a `main` | Compartido a propósito | `circuito:integrar` → `MergeRunner` (todas) | Nulo | ✅ `MergeRunner` flock `merge.lock` — un merge a la vez aunque lo disparen 6 workers a la vez. | flock global serializado |
| Scheduler / Watchdog / PriorizarSeguridad / DisparoCheck / AuditorService (cron) | Compartido a propósito | Cron de `meganet` | Nulo | ✅ Cada comando trae SU PROPIO flock de instancia única (`LOCK_EX \| LOCK_NB`); una segunda invocación se va sin hacer nada. | flock por-comando |
| Semáforo de builds `npm run dev/prod` | Compartido a propósito | `npm-build.sh` (todas las terminales que compilan frontend) | Bajo | ✅ Semáforo de MAX ranuras (`config('circuito.max_builds')`, default 3) por flock — evita ahogar los 4 cores del box. Nota: los builds concurrentes (hasta MAX) sí comparten el mismo `node_modules`; no se encontró caché persistente de webpack configurada en `webpack.mix.js` que pueda chocar entre builds paralelos. | flock de ranuras (semáforo) |
| Freno de mano (`circuito_pausado`, centinela `storage/app/circuito/PAUSA`) | Compartido a propósito, SOLO-LECTURA para el ejecutor | Todas las terminales lo consultan; solo Irving/la Torre lo escriben | Nulo | ✅ Ruta ABSOLUTA (no se bifurca por rama), consultado antes de arrancar, a mitad del pool y después de cada item. | centinela en archivo, ruta absoluta |
| Registro de PIDs de Jarvis (`storage/app/circuito/jarvis/pids/`) | Compartido a propósito | `vuelta.sh` (escribe), vigilante (lee) | Nulo | ✅ Un archivo por SID, escritura atómica (tmp + rename), identidad PID+starttime (inmune a reciclaje de PID). | escritura atómica por-SID |
| `.env` | Compartido A PROPÓSITO (symlink, no copia) | Las seis terminales + el checkout principal | Nulo — no puede divergir (mismo inode) | ✅ Ya es imposible que diverja. El "riesgo" es que un edit manual afecta a las 6 a la vez — aceptado a propósito (todas deben apuntar a la misma BD/servicios). | symlink desde el principal |
| `node_modules` | Compartido A PROPÓSITO (symlink, 520 MB) | `npm-build.sh` (`npm run dev\|prod`, nunca `install`) | Bajo, sin incidente registrado | ⚠️ Sin candado: ningún wrapper del circuito corre `npm install`/`ci`, pero tampoco hay nada que lo impida si un item lo hiciera a mano — afectaría a las 6 terminales a la vez. No se agrega guardia nueva hoy (el prompt del propio item pide no candar sin medir un choque real; no lo hay). Queda documentado como riesgo latente. | — (candidato a guardia si se materializa) |
| `vendor` (Composer) | Aislado | Composer autoload de cada worktree | Nulo | ✅ COPIADO (no symlink) por `ProvisionWorktreeCommand` a propósito: el autoloader resuelve `App\` al código del propio worktree. Reprovisionar con `--resync-vendor` tras un `composer install` en main (ya documentado en el comando). | copia física por worktree |
| Caché de archivos (`storage/framework/cache/data`) | Aislado | `cache:clear`/`cache:*` de cualquier item | Nulo | ✅ Directorio PROPIO del worktree (`OWN_DIRS`), no symlink. Ver corrección de premisa arriba. | directorio propio, no symlink |
| Config cache (`bootstrap/cache`) | Aislado | `config:clear` (nunca `config:cache`, prohibido por candado dura del ejecutor) | Nulo | ✅ Directorio propio del worktree. | directorio propio, no symlink |
| Sesiones (`storage/framework/sessions`) | Aislado | N/A (el circuito no abre sesiones HTTP) | Nulo | ✅ Directorio propio del worktree. | directorio propio, no symlink |
| Cola / `jobs` (`QUEUE_CONNECTION=database`) | Compartido por accidente (parcial) | Cualquier item que despache un job real | Bajo, sin incidente registrado | ⚠️ La tabla `jobs` vive en la BD compartida `megaisp`, pero los workers de Supervisor (`megaisp-queue`, etc.) SIEMPRE procesan con el código del checkout PRINCIPAL — nunca con el del worktree que despachó el job. Un item que dispare un job real de negocio se ejecutaría con código potencialmente distinto al que está probando. No es nuevo (ya pasaba antes del paralelo con un solo ejecutor); no se agrega candado — se documenta. | — (arquitectura de una app corriendo, no una race) |
| Redis (`REDIS_HOST/PORT` en `.env`) | Configurado, sin uso detectado | — | Nulo | ✅ `grep -rl "Redis::" app/` → 0 resultados; `CACHE_DRIVER`/`SESSION_DRIVER`/`QUEUE_CONNECTION` no lo usan hoy. No es un recurso vivo compartido actualmente. | — |
| `storage/app/circuito/` (centinela, PIDs, logs de guard) | Compartido A PROPÓSITO | Todas las terminales, por ruta ABSOLUTA al principal | Nulo | ✅ Ya documentado en el propio item — es el mecanismo mismo de coordinación, no un accidente. | ruta absoluta que no se bifurca |
| Puertos de servicio (Evolution :8080 Docker, AMI 5038, GPS listener :5027) | Compartido a propósito | Servicios de sistema (Docker/systemd/Supervisor), NO comandos por-item | Bajo, latente | ✅ Instancia única gestionada fuera del circuito; ningún wrapper de `vuelta.sh` los arranca. El SO ya es candado natural (un segundo `bind()` al mismo puerto falla ruidoso). Sin guardia nueva — no hay ningún item hoy que los toque de forma concurrente. | — (candado natural del SO) |
| Crontab del usuario `meganet` | Compartido, escritura restringida | Lectura: varios comandos (`crontab -l`, vía `EnvironmentHealthService`/`CompuertasService`/`DigestCommand`) | Nulo | ✅ Confirmado por grep: NINGÚN comando del circuito escribe el crontab programáticamente (`DigestCommand.php:260`: "ningún ejecutor on-box puede escribirla por sí solo"). Solo Irving lo edita a mano (`crontab -e`). De facto ya es solo-lectura para las seis terminales. | — |
| Compilados `public/js` / `public/css` | Aislado | `npm run dev\|prod` de cada worktree | Nulo | ✅ Cada worktree compila DENTRO de su propio árbol (no symlink); solo el checkout principal sirve tráfico real, y a ese solo llega lo que ya se mergeó a `main`. | árbol de trabajo propio (git worktree) |
| Log de vuelta (`$LOGDIR/vuelta-*.log`) | Compartido a propósito | `vuelta.sh` de cada SID | Nulo | ✅ Un archivo por SID+timestamp; rotación por tamaño (20 MB, #175) + retención (14 días, #175). | archivo por-SID + rotación |

## Conclusión — Paso 4 (aplicar nivel A / subir B-C)

**No se aplicó ningún candado nuevo de nivel A en esta vuelta.** No porque se saltara el paso, sino
porque la auditoría no encontró ningún recurso "compartido por accidente" que hoy tenga un choque
real sin cubrir: cada incidente de las últimas semanas (#170 freno, #334 aislamiento, #341 claim
atómico, #534 guardrail de migraciones, #873 semáforo de builds, #175 retención de logs, y el propio
candado dual de la BD de pruebas) ya cerró el hueco que abrió. El propio prompt del item lo pide
explícito: *"No inventar aislamiento nuevo sin medir primero."* — construir una guardia para un
choque que nunca ocurrió (`npm install` concurrente, jobs de cola cruzando código de otro worktree)
sería exactamente eso.

Quedan documentados arriba **dos riesgos residuales de nivel bajo/latente, sin incidente
registrado** (`node_modules` sin guardia contra `npm install` manual, y la cola compartida
procesando con el código del checkout principal). Ninguno cae en las cuatro fronteras duras
(producción · borrar datos · gastar dinero · credenciales) ni tiene evidencia de haber ocurrido, así
que no se escala — quedan anotados en esta tabla para que el próximo incidente (si lo hay) tenga
contexto inmediato en vez de empezar de cero, que es justo el patrón que este mismo item pide seguir.

La implementación de nuevas guardias por-recurso (si algún día se necesitan) sigue el patrón ya
probado dos veces: **una sola definición de la regla (`guard-<recurso>.sh` o su equivalente PHP),
invocada en el punto único por el que todo pasa, con rastro en archivo cuando el crontab se traga la
salida** — como `guard-bd-pruebas.sh` y `MigrationGuardService`.
