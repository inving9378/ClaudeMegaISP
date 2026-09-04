# Item #930 — ¿debe agotar los 60 turnos disparar la misma descomposición (`circuito:cabida`) que un timeout?

Sub-item FASE 4 de #927 ("CAUSA RAIZ de los claims huérfanos: una vuelta que muere por «Reached max
turns» no suelta el item ni escala"). Objetivo: solo evaluación/reporte — responder 3 preguntas y,
como mínimo, aplicar la conclusión al caso de prueba real (#871).

Irving ya aprobó el item eligiendo la **Opción 1** de la pregunta estructurada (`q1`, clave
`18440df10af7ca82`): *"Sí, tratar '60 turnos agotados sin cerrar' como señal equivalente a timeout →
disparar `circuito:cabida` automáticamente para descomponer el item"*. Este reporte responde las 3
preguntas del spec y documenta que, al momento de ejecutarse (2026-09-03, con #927-FASE1-3 y #928 ya
mergeados a `main`), **el efecto que pide la Opción 1 ya está en vigor** sin necesidad de código
nuevo — es un hallazgo, no una suposición: se verificó leyendo el código real en `main`, citado abajo.

## Pregunta 1 — ¿agotar 60 turnos es evidencia suficiente de "item demasiado grande"?

**No por sí sola.** Puede ser síntoma de al menos 4 causas distintas:

1. **Tamaño real** — la spec tiene más pasos discretos de los que caben en 60 turnos.
2. **Bucle del agente** — turnos consumidos re-verificando lo mismo sin decidir. Evidencia real en
   este mismo repo: el historial de #871 (ver Pregunta 3) tiene **8 sesiones** que re-confirmaron el
   mismo bloqueador externo turno tras turno sin avanzar — ninguna de esas 8 llegó a agotar los 60
   turnos (todas cerraron limpio, "libero el área"), pero es la forma exacta de bucle que SÍ podría
   agotarlos si el agente no corta a tiempo.
3. **Prompt ambiguo** — oscilación exploración↔re-exploración sin nunca decidir un camino.
4. **Herramienta lenta / bootstrap** — turnos "caros" en tiempo real pero baratos en trabajo hecho.

**Criterio de distinción razonable:** no mirar el número de turnos, mirar el **artefacto dejado**.
¿La rama tiene commits (`git rev-list --count main..rama`)? ¿hay un `circuito:reportar
--tipo=decision`? ¿un sub-item creado? Cero de eso tras agotar turnos es señal fuerte de
bucle/bloqueo/ambigüedad, no necesariamente de tamaño — un item genuinamente grande pero bien
encarrilado normalmente deja commits incrementales antes de quedarse sin turnos.

Dato del único caso medido: la sesión de #871 que agotó turnos corrió `13:05:04→13:09:56` (~5 min
para 60 turnos, ~5s/turno) — ritmo compatible con exploración de muchas llamadas cortas, no con
trabajo sostenido de escritura. No hay comentario de esa sesión en el log del item (ver Pregunta 3
para la causa exacta) así que no se puede confirmar cuál de las 4 causas fue, pero el patrón de
timing es consistente con "bucle/exploración", no con "picando código de una spec grande".

**Conclusión:** agotar turnos, por sí solo, no distingue causa. Pero el remedio correcto es el mismo
sea cual sea la causa real: no reintentar a ciegas, y forzar que el siguiente intento pase primero
por el chequeo de cabida antes de tocar código otra vez — de ahí que el sistema ya trate max-turns y
timeout con la misma vara (Pregunta 2).

## Pregunta 2 — ¿el disparo debe ser automático o quedar como señal para el próximo ejecutor?

Irving ya decidió (Opción 1 = automático). Verificado en el código real de `main` al día de hoy que
**esa decisión ya está implementada**, como consecuencia correcta de #927-FASE1-3 (ya mergeado) — no
hace falta escribir código nuevo para #930:

1. **`deploy/circuito/vuelta.sh:262-278`** — desde el fix de #927, la vuelta detecta `Reached max
   turns` en su log (`grep -aq 'Reached max turns' "$LOG"`) y llama a `circuito:parquear-timeout
   --causa=max_turns`, exactamente el mismo comando que ya llamaba para `timeout` (antes: solo
   timeout lo invocaba, max-turns "se iba de largo" sin soltar el claim — el bug original de #927).

2. **`ParquearTimeoutCommand::handle()`** (`app/Modules/Addons/Roadmap/Console/ParquearTimeoutCommand.php`)
   — incrementa `veces_timeouteo` para **ambas** causas por igual (la variable `$causa` solo cambia
   el texto del motivo en el log, no la lógica de decisión) y aplica la MISMA regla de
   reanudar-si-avanzó / escalar-si-no a ambas.

3. **`JarvisService::caberEnVuelta()`** (línea 1279) — la función que responde `circuito:cabida`:
   ```php
   if ((int) $item->veces_timeouteo >= 1) {
       return ['cabe' => false, 'motivo' => 'ya_timeouteo_antes', 'eta_segundos' => null];
   }
   ```
   No mira la causa — solo el contador compartido. Y `circuito:cabida` es un paso **obligatorio**
   del protocolo de cada vuelta (`deploy/circuito/prompt-item.txt:103-106`, paso 2, "antes de tocar
   código"), que corre en TODA vuelta — fresca o reanudada, después de timeout o después de max-turns.

**Efecto neto (ya en vigor, verificado leyendo el código de `main`):** un item que agota sus 60
turnos, en su **siguiente intento** (reanudado automáticamente si hubo avance, o tras pasar por la
bandeja de Irving si no lo hubo), topa con `NO CABE [ya_timeouteo_antes]` y el protocolo obliga al
ejecutor a descomponerlo con `circuito:sub-item` antes de escribir una línea de código. Es
"automático" en el sentido que importa — el próximo ejecutor no puede saltárselo — sin el riesgo que
la propia Opción 1 señalaba como contra (una máquina sin criterio decidiendo CÓMO cortar el item): el
punto de control sigue en manos de quien se lleva la vuelta, que sí tiene criterio para partirlo bien.

**No se requiere cambio de código adicional** para satisfacer la Opción 1 elegida por Irving — ya
está cubierta desde que #927-FASE1-3 se mergeó, el mismo día. Si en el futuro se quisiera un disparo
verdaderamente inmediato (sin esperar a la siguiente vuelta — p. ej. que el propio `vuelta.sh`
invoque `circuito:sub-item` en el momento del fallo, sin supervisión), eso sí sería tocar la
semántica de control de flujo del orquestador que el revisor de #927 marcó como frontera de diseño —
y no hay evidencia en este análisis de que haga falta: el "próximo intento" ya queda bloqueado.

## Pregunta 3 — aplicación al caso de prueba real (#871)

Historial completo revisado (`reap_count=10` al momento de este reporte). El patrón dominante **no
fue** "agotar turnos repetidamente":

- **Solo 1 de los ~10 ciclos** (13:05→13:09) agotó max-turns — y corrió con el `vuelta.sh` VIEJO
  (antes del fix de #927), por eso no dejó ningún registro en `comentarios_claude`/`log` del item
  (el bug original de #927 es justo que esa rama no llamaba a nada que escribiera el motivo).
- **Los otros ~9 reclamos huérfanos** fueron un problema DISTINTO, ya diagnosticado por el propio
  #927 y explícitamente fuera de su alcance (lo tratan #897/#898): #871 estaba bloqueado por una
  dependencia externa (#870, tabla `talento_puesto_document_templates`, sin mergear a `main` hasta
  el 2026-09-03 14:22, commit `0be1a158`) + una interacción reaper↔`jarvis-ya-decidido` que
  re-encolaba el item sin que nadie notara que el bloqueo seguía vigente. Ninguna de esas sesiones
  tocó código (todas: "no creo rama, libero el área").

Ese bloqueo **ya se resolvió**: #870 está en `main` desde el 2026-09-03 14:22 (confirmado por wt-1
en el log de #871), e Irving volvió a aprobar #871 a las 15:44 con las mismas 5 respuestas.

**Estado verificado de #871 al cerrar este reporte:** `worker_sid=null` (nadie lo tiene reclamado
— libre para tocar), `estado_aprobacion=aprobado_irving`, pero `bloqueado_por_bucle=true` /
`excluir_pool_automatico=true` (el freno anti-bucle de "3 escalaciones seguidas por la misma causa"
sigue puesto — mecanismo de otra familia, no se toca aquí).

**Decisión tomada (regla de oro, registrada en el log de #871):** NO se fuerza la descomposición de
#871 con `circuito:sub-item` en este item. La Pregunta 1 concluye que un único agotamiento de turnos
no es evidencia inequívoca de "demasiado grande", y en este caso concreto lo más probable —dado que
9 de 10 ciclos fueron por el bloqueo externo, ya resuelto— es que el próximo intento limpio (sin el
bloqueo) simplemente funcione o, si no cabe, quede atrapado solo por el mecanismo de la Pregunta 2.
Forzar un corte ahora sería el "falso positivo" que la propia Opción 1 señala como contra: partir un
item que quizás solo necesitaba que se le quitara el obstáculo de encima. En vez de forzarlo, se dejó
la recomendación escrita en `comentarios_claude` de #871 para el próximo ejecutor, con la sugerencia
de corte en 3 fases si vuelve a agotar turnos: (A) migración + modelo + `EmployeeDocumentPackageService`
(arma `$data` y renderiza), (B) enganche en el observer del alta (try/catch best-effort), (C) UI
"Documentos" en la ficha del colaborador.

## Resumen

| Pregunta | Respuesta |
|---|---|
| 1. ¿Max-turns = item grande? | No por sí solo; mirar el artefacto dejado (commits/decisión/sub-item), no el conteo de turnos. |
| 2. ¿Automático o señal? | Irving eligió automático (Opción 1) — y ya está en vigor sin código nuevo: `veces_timeouteo` unificado + `circuito:cabida` obligatorio en cada vuelta ya lo garantizan desde #927-FASE1-3. |
| 3. ¿Aplica a #871? | Recomendación registrada en su log, sin forzar el corte — su causa real (9/10 ciclos) fue un bloqueo externo ya resuelto, no tamaño. |

**Sin cambio de código de aplicación** — el mecanismo que satisface la Opción 1 ya existía en `main`
antes de este item, como efecto correcto de #927-FASE1-3 (`vuelta.sh` + `ParquearTimeoutCommand`) más
la protección pre-existente de `JarvisService::caberEnVuelta()`. Este item deja el hallazgo y la
recomendación por escrito.
