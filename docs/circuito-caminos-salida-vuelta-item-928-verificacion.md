# Item #928 — Inventario completo de los caminos de salida de una vuelta (FASE 1, solo lectura)

Sub-item de #927. Objetivo: tabla completa (archivo:línea) de TODAS las formas en que
`ejecutar_una()` de `deploy/circuito/vuelta.sh` puede terminar, y para cada una: ¿suelta el
claim del item?, ¿decide su destino?, ¿registra el motivo real en el log del item?

## Hallazgo previo — el bug raíz que motivó #927/#928 YA ESTÁ RESUELTO

El commit `604b8bd2` ("fix(circuito#927): una vuelta que muere de cualquier forma suelta el item
y decide") aterrizó el **2026-09-03 16:01**, **después** de que se escribiera el spec de #928
(13:46) pero **antes** de que esta vuelta lo tomara. Ese commit ya implementa exactamente lo que
#928/#929 estaban investigando/diseñando:

1. `deploy/circuito/vuelta.sh` línea ~251: el chequeo pasó de `if [ "$RC" -eq 124 ]` (solo timeout)
   a `if [ "$RC" -ne 0 ]` (cualquier RC≠0), con una causa distinguida (`timeout`/`max_turns`/`error`)
   que viaja a `circuito:parquear-timeout --causa=...`.
2. `ParquearTimeoutCommand` (`app/Modules/Addons/Roadmap/Console/ParquearTimeoutCommand.php`)
   recibió el flag `--causa` y escribe el motivo real distinguido en el log del item
   (`"La vuelta agotó sus turnos (max-turns)"` / `"La vuelta terminó con error tras Ns"` / etc.),
   en vez del genérico `"se cortó a los Ns"` de antes.
3. Se agregó un **trap `EXIT`** (`limpiar_al_salir` → `soltar_claim_huerfano`, líneas ~139-146) que
   corre en **cualquier** salida del script (`exit 0`, `exit 1`, timeout, kill, crash) y llama a
   `circuito:soltar-claim` (`app/Modules/Addons/Roadmap/Console/SoltarClaimCommand.php`, nuevo):
   solo actúa si el item sigue `en_progreso` con el `worker_sid` de ESTA vuelta, y en ese caso lo
   libera + decide destino (`estado_previo_claim` o `aprobado_revisor`) + registra el motivo.

Esto cierra la mayoría de los huecos que el spec original describía como "no manejado". Lo que
sigue es la tabla completa verificada contra el código **actual** (post-`604b8bd2`), no contra el
estado descrito en el spec original (que ya estaba desactualizado al momento de ejecutarse).

## Tabla — caminos de salida de una vuelta

Columnas: ¿Suelta claim? / ¿Decide destino? / ¿Registra motivo REAL (no genérico)?

### A. Dentro de `ejecutar_una()` (`deploy/circuito/vuelta.sh`)

| # | Camino | Suelta claim | Decide destino | Motivo real |
|---|--------|:---:|:---:|:---:|
| 1 | `guard_bd_pruebas` falla (línea ~199, `return 1` antes de correr `claude`) | Diferido (ver nota) | Diferido | ❌ |
| 2 | `claude -p` RC=124 (timeout real) | ✅ `parquear-timeout --causa=timeout` | ✅ | ✅ "se cortó a los Ns" |
| 3 | `claude -p` RC≠0,≠124 con `Reached max turns` en el log | ✅ `parquear-timeout --causa=max_turns` | ✅ | ✅ "agotó sus turnos (max-turns)" |
| 4 | `claude -p` RC≠0 por cualquier otra causa (crash del CLI, OOM/SIGKILL del hijo=137, red) | ✅ `parquear-timeout --causa=error` | ✅ | ⚠️ solo dice "terminó con error tras Ns" — no distingue OOM de un crash normal, pero SÍ suelta+decide+registra algo real (ya no es "reclamo huérfano" genérico) |
| 5a | `claude -p` RC=0 y el propio agente cerró el item (integró, o consultó a Jarvis y quedó en `requiere_irving`) | ✅ (lo hace el propio cierre: `circuito:integrar`+tinker, o `JarvisService::escalar()`) | ✅ | ✅ |
| 5b | `claude -p` RC=0 pero el agente NO tocó `estado_aprobacion` (terminó en silencio) | Diferido (ver nota) | Diferido | ⚠️ genérico ("muerte del proceso: kill, OOM o freno a media vuelta" — no es preciso para este caso, pero no se pierde el item) |

**Nota caminos 1 y 5b — "diferido":** si el item en cuestión es el **último** que procesa esta
corrida de `vuelta.sh` (modo por-item sin pool continuo, o el último del pool), el `trap EXIT`
lo agarra al salir el script completo → soltado+decidido+registrado (motivo genérico, no la causa
real "guard_bd_pruebas" o "RC=0 silencioso"). Si es un item **intermedio** del pool continuo (el
worker sigue con más items después en la misma corrida), el trap solo mira el `$ITEM` vigente al
morir el script — el intermedio queda con el claim colgado hasta que **toda la corrida** termine
y el flock del slot se libere; ahí lo agarra `circuito:reap-stuck` **vía rápida** (chequeo por
slot libre, gracia ~3 min, `config('circuito.reaper.gracia_minutos')`) — no instantáneo, pero
cubierto en minutos, no en los 25 min de la vía lenta.

### B. Fuera de `ejecutar_una()`, mismo script

| # | Camino | Suelta claim | Decide destino | Motivo real |
|---|--------|:---:|:---:|:---:|
| 6 | Kill switch al ARRANCAR (`frenado`, línea ~156) / circuito pausado (línea ~165) — antes de llamar a `ejecutar_una` | ✅ trap EXIT (`$ITEM` ya está seteado desde la línea 20, antes del trap) | ✅ | ⚠️ genérico, no dice "frenado al arrancar" |
| 7 | Falla `circuito:provision-worktree` (línea ~185) | ✅ trap EXIT | ✅ | ⚠️ genérico |
| 8 | Kill switch A MITAD DEL POOL (línea ~322, tras `ejecutar_una`) | N/A — el item ya se resolvió dentro de su propio `ejecutar_una()` (casos 1-5 arriba) ANTES de este chequeo; solo evita tomar el SIGUIENTE item | — | — |
| 9 | TOPE_DE_TIEMPO_DEL_PADRE / TOPE_DE_ITERACIONES (líneas ~291-302 y ~326-330) | N/A, mismo razonamiento que #8 | — | — |
| 10 | `circuito:claim-next` no devuelve nada (sin más trabajo / pausa) | N/A, mismo razonamiento | — | — |
| 11 | Muerte del proceso PADRE completo por SIGKILL externo del host / OOM-killer / reinicio | ❌ (SIGKILL no es capturable — el trap `EXIT` NUNCA corre) | ❌ | ❌ |

**Caso 11 — cubierto por la red, no por el cierre** (confirmado, tal como sospechaba el spec
original): `circuito:reap-stuck` vía lenta (`updated_at`+`claimed_at` fríos por `--minutes`,
default 25 min) es la ÚNICA red para este caso. No es un bug: SIGKILL no permite ejecutar ningún
código de limpieza en bash, es el límite físico de lo capturable.

### C. Comando de auditoría

| # | Camino | Efecto sobre el claim |
|---|--------|---|
| 12 | `circuito:registrar-ejecucion` (`RegistrarEjecucionCommand.php`) | Ninguno — solo crea una fila en `CircuitoEjecucion` (histórico/métricas), nunca toca `RoadmapItem`. Confirmado tal como sospechaba el spec original. **Nota:** el camino 1 (`guard_bd_pruebas`) hace `return 1` sin pasar por `registrar()` → ese intento fallido no deja ninguna fila de auditoría; el único rastro es el `.log` de texto plano del worker. |

## Resumen de hallazgos (insumo para #929, FASES 2+3+5)

- **El hueco de CLAIM que #927/#928/#929 perseguían ya está cerrado** (commit `604b8bd2`, ver
  arriba). El spec de #929 (`app/Modules/Addons/Roadmap/Models/RoadmapItem` #929, en curso por
  `wt-2` al momento de escribir esto) describe el bug ANTES del fix y propone una implementación
  que **ya existe** (el flag `--causa` de `parquear-timeout` y el cierre-por-defecto vía trap, en
  vez de enumerar códigos). Quien retome #929 debe **releer el código actual** antes de
  implementar nada — la mayor parte de su ask ya está hecha; lo que queda son los matices de abajo,
  no la reescritura original.
- **Único hueco estructural que sigue sin cierre explícito posible:** muerte del proceso padre por
  señal no capturable (caso 11) — depende 100% de la red de antigüedad (`reap-stuck`, ~25 min).
  No es accionable (límite físico de bash), solo hay que dejarlo documentado.
- **Huecos de PRECISIÓN de diagnóstico (no de pérdida de claim), todos ya cubiertos en cuanto a
  soltar+decidir, solo falta que el motivo registrado sea el real y no el genérico:**
  - Caso 1 (`guard_bd_pruebas` falla): no llama a `registrar()` → sin fila de auditoría; el motivo
    real no llega al log del item.
  - Casos 6 y 7 (kill switch/pausa al arrancar, fallo de provision-worktree): el trap EXIT libera
    bien, pero con el motivo genérico de `soltar-claim` ("muerte del proceso…"), no la causa
    puntual que sí quedó en el `.log` de texto del worker.
  - Caso 5b (RC=0 silencioso): mismo matiz — se libera (eventualmente, ver nota de diferido
    arriba), pero el motivo no distingue "el agente no hizo nada" de "el proceso murió".
  - Caso 4 (RC≠0 no-timeout no-max_turns): agrupa OOM/SIGKILL del hijo junto con crashes normales
    del CLI bajo la misma causa genérica `error`.
- Si se retoma #929, el trabajo real que queda (nivel A: aditivo, sin tocar `reap-stuck` ni el
  anti-bucle, tal como ya limita el propio spec de #929) es **pasar la causa puntual** a
  `soltar-claim`/`parquear-timeout` en los casos 1, 4, 5b, 6 y 7 (hoy esas funciones no reciben
  ningún dato de "por qué" más allá de lo que ya tienen), y opcionalmente llamar a `registrar()`
  también en el aborto de `guard_bd_pruebas`. Es una mejora de diagnóstico/auditoría, no de
  pérdida de items — el sistema ya no pierde claims en ningún camino de los 12 mapeados salvo el
  caso 11 (irreducible).

## Verificación

Solo lectura: se releyó `deploy/circuito/vuelta.sh` completo (369 líneas) contra el estado actual
de `main` (post-`604b8bd2`), y se leyó el código fuente completo de `ParquearTimeoutCommand.php`,
`SoltarClaimCommand.php`, `ReapStuckCommand.php`, `ConsultarSupervisorCommand.php` +
`JarvisService::resolverConsulta/evaluar/escalar`, y `RegistrarEjecucionCommand.php`. Sin cambios
de código de aplicación — este item es 100% documentación, tal como pedía su propio entregable.
