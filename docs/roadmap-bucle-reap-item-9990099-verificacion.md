# Item #9990099 — Fase 2 (repro 2 procesos concurrentes, cascade sobre #32) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

**Fecha:** 2026-09-04 · **Worktree:** wt-3 · **Continuación de:** #9990076 (Fase 1, secuencial
mismo-proceso, NO reprodujo).

## Qué pedía el item

#9990099 pedía la Fase 2 del repro del incidente de #32 (nivel-C sin merge que terminó
`completado`): montar un experimento con **2 procesos PHP del sistema operativo separados**
(conexiones DB reales) sobre un fixture sintético que replica el estado real de #32, capturar el
SQL emitido (`DB::listen()`) y confirmar o descartar la hipótesis del propio item — que el guard
(1) de `RoadmapItem.php:278-289` revierte `estado_aprobacion` en memoria a su valor original, pero
si ese valor coincide con lo que Eloquent ya tenía cargado, la columna queda fuera del `UPDATE`
(`getDirty()`), y un `completado` escrito por OTRO proceso concurrente sobrevive intacto sin que el
guard lo note ni lo corrija.

## Lo que ya había pasado antes de esta vuelta

Una vuelta previa de **esta misma terminal (wt-3, 2026-09-03 22:16:42-22:16:57)** ya hizo lo
correcto: reconoció que el trabajo no cabía en una sola vuelta y lo descompuso en dos fases
separadas, ambas como sub-items (`origen_item_id=9990099`):

- **#9990111** — "Fase 2a: construir y ejecutar el repro de 2 procesos PHP concurrentes (columna
  omitida del UPDATE)".
- **#9990112** — "Fase 2b: interpretar el resultado del repro (#9990111) y actuar: fix para #9990013
  o consulta a Thomas".

Pero esa vuelta **murió inmediatamente después de crear los dos sub-items**, sin intentar cerrar
al padre (log: `claim_liberado_al_morir_la_vuelta` a los 20 segundos). El siguiente intento de
trabajar #9990099 (también wt-3, 22:28:04) timeouteó sin commits y escaló a Irving por el
mecanismo anti-bucle ("des-trabe" — 2 corridas sin ejecutar). Irving reaprobó el item el
2026-09-04 05:55:33, y quedó de nuevo en el pool hasta que esta vuelta lo reclamó.

## Verificado en esta vuelta

- **#9990111 ya se ejecutó y cerró de verdad**: `estado_aprobacion=completado`,
  `merge_commit=412cb1e9f9e5b42afc07c97aadbff7fd5772cf15` (confirmado ancestro de `main` en este
  checkout). Su resultado — documentado en
  `docs/roadmap-repro-fase2a-cascade-guard-item-9990111-verificacion.md` — es exactamente lo que
  pedía #9990099: **bug CONFIRMADO** en el escenario asimétrico (proceso S con copia stale de
  `merge_commit=null` intenta cerrar "naive" DESPUÉS de que el proceso M ya escribió
  `estado_aprobacion='completado'` legítimamente vía `merge_commit` real; el `UPDATE` de S omite
  la columna `estado_aprobacion` del `SET` por no-dirty, así que el `completado` de M sobrevive sin
  que S lo detecte). SQL capturado y fixtures limpiados (`0` filas `[REPRO-FASE2]%` verificado en
  su propio cierre).
- **#9990112 sigue genuinamente abierto**: `aprobado_revisor`, `worker_sid=null`,
  `excluir_pool_automatico=false`, sin hijos propios — es trabajo real pendiente (interpretar el
  resultado ya confirmado por #9990111 y decidir/actuar sobre #9990013, que sigue en
  `requiere_irving`). Irving ya respondió sus preguntas estructuradas (05:57:21) y
  `jarvis-ya-decidido` lo devolvió al pool (05:58:03) — listo para que una terminal lo tome.
- **Nota de coordinación cruzada** (ya registrada por el propio #9990111): la misma pregunta
  también se investigó en paralelo por la cadena #9990088→#9990094→#9990109→#9990110, que llegó
  independientemente a intentar el mismo experimento. Ambos hallazgos son compatibles.

La descomposición original (Fase 2a ejecutable + Fase 2b de interpretación/acción) seguía siendo
correcta — nadie más la tocó ni hizo falta ajustarla.

## Corrección aplicada

Esta vuelta ejecuta el intento de cierre que faltaba: al intentar `estado_aprobacion='completado'`
sobre #9990099, el guard **(2b) PARAGUAS** (`RoadmapItem.php`, ~301-332) lo reenruta a
`aprobado_irving` + `excluir_pool_automatico=true` (evento `paraguas_abierto` en el log, "le queda
1 sub-item(s) abierto(s)" — cuenta solo a #9990112, ya que #9990111 está cerrado), sacándolo del
pool/reaper hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`/`497-526`) lo
complete solo cuando #9990112 cierre.

**Sin cambio de código de negocio** — el trabajo técnico real (confirmación del bug, ya hecha por
#9990111; interpretación + fix/consulta, pendiente en #9990112) sigue en esos dos sub-items.
