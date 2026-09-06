# Item #9990414 — auditoría de `veces_timeouteo` inflado por límite de cuenta (2026-09-04)

Sub-item de seguimiento de #9990410 (FASE 5, solo investigación + reporte, sin escribir cambios de
negocio en BD). Objetivo: reconstruir, a partir de los logs históricos, qué items quedaron con
`veces_timeouteo`/`reanudaciones_timeout` contaminados por la ventana en que la cuenta de Claude
agotó su límite de sesión (2026-09-04, ~10:14 a 11:19 hora CDMX, reset a las 12:00), y dejar
constancia de cuáles ya fueron rescatados a mano y cuáles no — **sin tocar ningún contador**.

## Método

1. `grep -ril "session limit" storage-logs-circuito/*.log` → **36 archivos** de vuelta, todos con
   timestamp entre `vuelta-20260904-101603` y `vuelta-20260904-111903`.
2. Para cada archivo, se identificó el item que estaba `en_progreso` en el momento del choque
   (línea `modo POR-ITEM: trabajando SOLO el item #N` inmediatamente antes de la línea
   `You've hit your session limit`) y el resultado que imprimió `circuito:parquear-timeout`
   justo después (`REANUDADO`/`a la bandeja de Irving`/`ya está completado; no se toca`).
3. Se confirmó el resultado cruzando contra la columna `roadmap_items.log` (JSON) de cada item:
   se escaneraron los **1143 items con log no nulo** buscando eventos `timeout_escalado` /
   `timeout_reanudable` con `causa=error` (la causa `limite_cuenta` **no existe todavía** —
   depende de #9990411, `en_progreso` al momento de este item) cuyo `ts` cae en la ventana
   2026-09-04 10:00–12:05 hora CDMX. Los dos métodos (grep de logs vs. escaneo de BD) coincidieron
   exactamente en la misma lista de items — doble verificación cruzada.

## Resultado

**36 choques de "session limit" → 35 incrementos espurios de `veces_timeouteo`, repartidos en 11
items** (1 archivo, `vuelta-20260904-102102-wt-4.log` / item #9990333, fue no-op: el item ya
estaba `completado` cuando corrió `parquear-timeout`, así que no incrementó nada).

| Item | Eventos contaminados en la ventana | Rescatado a mano | `veces_timeouteo` actual | Nota |
|------|------|------|------|------|
| #9990244 | 1 | ✅ 2026-09-04 13:06:13 | 0 | limpio |
| #9990265 | 3 | ✅ 2026-09-04 13:06:13 | 0 | limpio |
| **#9990270** | **1** | **❌ NO rescatado** | **1** | **único pendiente real** |
| #9990328 | 4 | ✅ 2026-09-04 12:23:07 | 0 | limpio |
| #9990331 | 3 | ✅ 2026-09-04 13:06:13 | 0 | limpio |
| #9990334 | 3 | ✅ 2026-09-04 12:23:07 | 0 | limpio |
| #9990335 | 10 (el más golpeado) | ✅ 2026-09-04 12:23:07 | 0 | limpio |
| #9990336 | 4 | ✅ 2026-09-04 12:23:07 | 1 | ver nota |
| #9990337 | 4 | ✅ 2026-09-04 12:23:07 | 1 | ver nota |
| #9990338 | 4 | ✅ 2026-09-04 13:06:13 | 0 | limpio |
| #9990340 | 3 | ✅ 2026-09-04 13:06:13 | 0 | limpio |

Los 10 items marcados "rescatado a mano" tienen en su `log` un evento
`evento:"destrabe_por_limite_de_sesion"` (actor `claude-code`) en dos tandas — **12:23:07** (5
items: #9990328, #9990334, #9990335, #9990336, #9990337 — la "familia MR-06a" que cita #9990410) y
**13:06:13** (los otros 5: #9990244, #9990265, #9990331, #9990338, #9990340) — que coincide
exactamente con los "10 items ya rescatados... 5 de la familia MR-06a + 5 más" que menciona el
comentario del item padre #9990410.

**Nota #9990336 / #9990337:** hoy muestran `veces_timeouteo=1` / `reanudaciones_timeout=1` de
nuevo, pero **no es contaminación residual** — es un incremento legítimo y no relacionado: un
evento `timeout_reanudable` real con `causa=max_turns` (no `error`) ocurrió minutos **después**
del rescate (12:30:56 y 12:47:19 respectivamente), con commits reales de avance en su rama. El
rescate de las 12:23:07 sí puso ambos contadores en 0; lo que se ve hoy es trabajo posterior
genuino, no el eco del límite de cuenta.

**Único pendiente real: #9990270.** Nunca recibió el evento `destrabe_por_limite_de_sesion`.
Tiene un solo evento contaminado (`timeout_escalado`, `causa=error`, `2026-09-04T11:19:09-06:00`)
y hoy conserva `veces_timeouteo=1` / `reanudaciones_timeout=0` sin que ningún trabajo posterior lo
haya vuelto a tocar. El item ya está `completado`, así que el contador inflado no bloquea nada
operativamente hoy — pero sigue siendo un dato incorrecto (mide el límite de la cuenta, no el
item) hasta que alguien decida limpiarlo.

## Alcance

Sin cambios en BD salvo el propio cierre de este item (`reporte_coloquial`/`enlace_revision`/
`sin_ui`). Ningún `veces_timeouteo`/`reanudaciones_timeout` fue modificado — decisión de Irving,
tal como pide el propio prompt del item.
