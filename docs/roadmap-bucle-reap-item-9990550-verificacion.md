# Item #9990550 — DC plazo 180d hábiles (fecha de inicio): bucle reap sobre paraguas ya descompuesto

## Contexto

`#9990550` ("Documentación Corporativa — fecha de inicio del plazo de 180 días hábiles (columna
nueva)") es la misma familia de bug documentada repetidamente en `CLAUDE.md` (#738, #745, #830,
#816, #818, #848, #852, #905, #878, #906, #907, #924, #9990012, #917, #910, #936, #9990408, #962,
entre otros): un item se descompone correctamente en sub-items, pero nadie ejecuta el intento de
cierre que dispara el guard de paraguas (`RoadmapItem.php` bloque "(2b) PARAGUAS"), así que el item
se queda `en_progreso` colgado hasta que el reaper lo re-encola y el pool lo vuelve a repartir sin
que haya trabajo propio que hacer.

## Lo que ya se hizo bien (sesión previa)

El item nació como sub-item de seguimiento de `#9990531` (tablero de apartados, que dejó el KPI
"Días restantes" en N/D a propósito). Fue escalado por el revisor (sin criterio de aceptación
explícito) y por el DES-TRABE de Opus (decisión de negocio: desde cuándo arranca el plazo legal).
Irving aprobó el 2026-09-07 18:38 respondiendo las 5 preguntas estructuradas del brief (q1: fecha
de emisión/expedición del documento; q2: Lun-Vie excluyendo festivos oficiales de México; q3: solo
almacenar `fecha_inicio_plazo`, calcular vencimiento on-the-fly; q4: backfill automático con la
fecha elegida; q5: alertas con umbrales configurables, pero marcada explícitamente Fase 2 futura).

La vuelta `wt-3` (2026-09-07 18:42) ya hizo lo correcto: corrió `circuito:cabida` (NO CABE) y
descompuso el trabajo en 4 fases encadenadas (`origen_item_id=9990550`):

- **#9990573** — "Fase 1: columna `fecha_inicio_plazo` + catálogo de festivos" → `pendiente_revision`,
  sin reclamar.
- **#9990574** — "Fase 2: servicio de cálculo de días hábiles + wiring en `CompletitudService`" →
  `pendiente_revision`, sin reclamar.
- **#9990575** — "Fase 3: UI de captura de `fecha_inicio_plazo` + KPI real en `DcExpediente.vue`" →
  `pendiente_revision`, sin reclamar.
- **#9990576** — "Fase 4 (futura, explícitamente fuera de esta iteración): alertas por proximidad
  al vencimiento" → `pendiente_revision`, sin reclamar (corresponde a q5, ya decidida por Irving
  como Fase 2 futura).

## Por qué se quedó colgado

Justo después de escribir la decisión de descomposición en `comentarios_claude`, el proceso de
`wt-3` murió (kill/OOM/freno a media vuelta) sin haber intentado el cierre del padre. El log lo
confirma: el único evento tras la decisión es `soltar-claim` ("La vuelta de wt-3 terminó sin
cerrar el item... se libera el reclamo y vuelve a la cola como aprobado_revisor"). Sin un intento
de `estado_aprobacion='completado'` de por medio, el guard de paraguas nunca se disparó — el item
volvió a `aprobado_revisor` y fue repartido de nuevo (a `wt-1`, esta vuelta) sin que quedara
trabajo propio pendiente.

## Verificación del estado real (esta vuelta)

```
9990550 (yo) | en_progreso     | excl=false | sid=wt-1
  9990573    | pendiente_revision | sin reclamar (Fase 1: columna + festivos)
  9990574    | pendiente_revision | sin reclamar (Fase 2: servicio días hábiles)
  9990575    | pendiente_revision | sin reclamar (Fase 3: UI + KPI real)
  9990576    | pendiente_revision | sin reclamar (Fase 4: alertas, futura)
```

Los 4 hijos (`origen_item_id=9990550`) están intactos, todos con `origen_item_id` correcto y
ninguno tocado desde su creación — la descomposición original sigue siendo correcta.

## Corrección aplicada

Se ejecuta el intento de cierre faltante sobre `#9990550` (`estado_aprobacion='completado'`). El
guard de paraguas del modelo lo reenruta a `aprobado_irving` + `excluir_pool_automatico=true` (log
`paraguas_abierto`, "le quedan 4 sub-item(s) abierto(s)"), liberando `worker_sid`/`claimed_at` y
sacándolo del pool/reaper hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo
complete solo cuando #9990573, #9990574, #9990575 y #9990576 cierren los cuatro.

**Sin cambio de código de negocio.** El trabajo real (columna `fecha_inicio_plazo` + catálogo de
festivos, servicio de cálculo de días hábiles + wiring en `CompletitudService`, UI de captura +
KPI real en `DcExpediente.vue`, y alertas de Fase 2 futura) sigue en #9990573/#9990574/#9990575/
#9990576, pendientes de que una terminal los reclame.
