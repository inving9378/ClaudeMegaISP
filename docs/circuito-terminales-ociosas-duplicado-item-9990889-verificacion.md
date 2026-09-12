# Item #9990889 (CIRC-06) — Terminales ociosas / "Listos para terminal" miente (RESUELTO — duplicado de #9990863, ya diagnosticado y decompuesto)

## Hallazgo

El item #9990889 (título "CIRC-06: El despachador no llena las terminales ociosas —
instrumentar, exentar docs del detector de colisiones y que Listos para terminal no mienta")
es uno de los 11 sub-items (#9990881-#9990891) materializados a partir del documento
"CIRC-00 a CIRC-08" (#9990874, creado 2026-09-11 18:24:59). Su diagnóstico — 5 sesiones de
terminal ociosas con "cola vacía" pese a 169+27 items aprobados, y las cinco causas candidatas
C1 (detector de colisiones bloquea por `CLAUDE.md`/docs, evidencia #9990810), C2 ("Listos para
terminal" incluye bloqueados de verdad, evidencia #9990798 tomado 3 veces por depender de
#9990790 sin texto de Irving), C3 (paraguas parqueados inflan el conteo ~23%), C4 (tope de
concurrencia/modo por-item) y C5 (frenos/sin modelo) — es **textualmente el mismo diagnóstico**
que ya hizo el item **#9990863** ("Terminales ociosas con items aprobados: por qué el
despachador no asigna y por qué 'Listos para terminal' miente"), creado **21 minutos antes**
(2026-09-11 18:15:11) y con evidencia idéntica citada (#9990810, #9990798).

## Verificación

`circuito:cabida 9990889 --sid=wt-5` devolvió NO CABE (histórico ~539s) — antes de decomponer
por fases como indica el protocolo, se investigó si el trabajo ya existía en otro lugar,
siguiendo la regla de "no dupliques lo que otro item ya está haciendo".

Confirmado contra la BD real de dev:

1. **#9990863 ya tiene su Fase 1 (instrumentación) mergeada a `main`.** `merge_commit =
   4c473d85085b28e20008aa6584b5ac7fda79694a` (18:39:03 CST), que modifica
   `app/Modules/Addons/Roadmap/Console/SchedulerCommand.php` (+43 líneas: `persistirOciosidad()`,
   invocada tras cada ronda del scheduler para dejar **una línea por terminal ociosa por ciclo**
   con el código y motivo real — `sin_candidatos`/`colision:...`/`excluido_pool`/etc.) y
   `config/logging.php` (+17 líneas: canal `circuito_despacho`, driver `daily`, retención 14
   días). Verificado leyendo el código real de `SchedulerCommand.php` en este worktree — el
   bloque de comentario del método `persistirOciosidad()` cita explícitamente "#9990863 Fase 1"
   y describe el mismo síntoma (169+27 aprobados, 5 de 6 terminales ociosas).
2. **Las Fases 2-5 de CIRC-06 (exentar docs del detector de colisiones, que "Listos para
   terminal" no mienta + cablear #9990798, C3/C4/C5 según el log, y el tablero permanente) ya
   existen como sub-items de #9990863**, creados el mismo día a las 18:37 (antes de que
   #9990889 llegara a reclamarse), con specs más detallados que el propio CIRC-06 (incluyen
   puntos de inserción de código exactos y decisiones ya aprobadas por Irving vía preguntas
   estructuradas q2-q5):
   - **#9990892** (Fase 2/C1) — `aprobado_revisor`. Localiza el punto de inserción exacto
     (`RoadmapCircuitoService::footprintDeRama()`/`footprintEnVivo()` ~líneas 2929/2966, antes
     de `decidirColisiones()` ~línea 3191) y la lista exenta aprobada por Irving (q2, opción 1):
     `CLAUDE.md`, `docs/**`, `CHANGELOG*`, la bitácora, `*.md` de documentación. Exige simular
     sobre el histórico de colisiones antes de activar (q3 aprobada) — si el resultado es 0, no
     activar y documentar en vez de forzar.
   - **#9990893** (Fase 3/C2) — `requiere_irving`. Ya localiza el bug real
     (`SupervisorService::listosParaTerminal()`/`listosParaTerminalTotal()` líneas 137-162 no
     excluyen `depende_de` sin resolver, a diferencia del despachador real que sí usa
     `estaCerradoParaDependencia()`/MR-36 #9990332) y trae las 3 reglas aprobadas por Irving
     (q4, opción 1) + la instrucción explícita de cablear `#9990798→depende_de=[9990790]`
     (verificando primero que el bloqueador siga vigente).
   - **#9990894** (Fase 4/C3-C5) — `aprobado_revisor`. Depende de que #9990892 y #9990893 estén
     mergeadas y de tener horas/días reales de datos en el log de despacho.
   - **#9990895** (Fase 5/tablero) — `requiere_irving`. Fuente de datos ya definida (mismo canal
     `circuito_despacho`), métricas "terminales ociosas × minutos" y "elegibles reales" para
     la Torre.
3. **El log de despacho aún no tiene entradas** (`storage/logs/circuito-despacho-*.log` no
   existe todavía, ni en este worktree ni en el checkout principal) porque el merge de la Fase 1
   ocurrió a las 18:39 CST y la verificación de este item se hizo a las ~18:43 CST — apenas 4
   minutos después, insuficiente para que el cron del scheduler (cada minuto) acumule una
   ventana útil. Esto es exactamente lo que #9990892/#9990893/#9990894 ya anticipan como
   pre-requisito ("depende de leer ≥15 min ya acumulados... NO adivinar desde el código") — no
   es un hallazgo nuevo de este item, es el estado esperado a los pocos minutos del merge.

## Conclusión

Re-decomponer #9990889 en fases propias (como indicaría el flujo normal ante un NO CABE)
duplicaría exactamente el trabajo ya registrado en #9990892/#9990893/#9990894/#9990895 —
mismo diagnóstico, misma evidencia, mismos puntos de inserción de código, y en el caso de
#9990893, decisiones de Irving ya tomadas que CIRC-06 ni siquiera tiene. Crear sub-items
gemelos habría sido el desperdicio de trabajo que el `CLAUDE.md` de arquitectura (regla
BALANCE/minimalismo) pide evitar.

**Item #9990889 cerrado como duplicado, sin sub-items nuevos.** El trabajo real de las 5 fases
de esta épica sigue su curso bajo la cadena de #9990863 → #9990892/#9990893/#9990894/#9990895,
sin relación de dependencia formal necesaria con #9990889 (no hay nada que #9990889 aporte que
esos 4 sub-items no cubran ya). **Sin cambio de código de aplicación** — solo esta verificación
documental.
