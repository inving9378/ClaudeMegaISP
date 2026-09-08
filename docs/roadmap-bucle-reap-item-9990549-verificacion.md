# Item #9990549 — MR-24e Fase 3 (modo dibujo cable/troncal): bucle reap sobre paraguas ya descompuesto

## Contexto

`#9990549` ("MR-24e Fase 3 — Modo dibujo: cable/troncal con snap a extremos (frontend)") es la
misma familia de bug documentada repetidamente en `CLAUDE.md` (#738, #745, #830, #816, #818, #848,
#852, #905, #878, #906, #907, #924, #9990012, #917, #910, #936, #9990408, #962, #9990550, entre
otros): un item se descompone correctamente en sub-items, pero nadie ejecuta el intento de cierre
que dispara el guard de paraguas (`RoadmapItem.php` bloque "(2b) PARAGUAS"), así que el item se
queda `en_progreso` colgado hasta que el reaper lo re-encola y el pool lo vuelve a repartir sin que
haya trabajo propio que hacer.

## Lo que ya se hizo bien (sesión previa)

El item nació como sub-item de seguimiento de `#9990439`. Fue escalado inicialmente por el
DES-TRABE de Opus ("ANTI-LOOP: el ejecutor ya corrió este item 2× y NO lo ejecutó") por tener un
brief con 4 preguntas estructuradas (implementación del modo dibujo, distinción cable/troncal,
elementos válidos de snap y si la persistencia entra en esta fase). Irving aprobó las 4 preguntas
el 2026-09-07 18:39, incluida `q4` (recomendada: "Solo frontend en esta fase 3 — la persistencia
se hace en fase 4").

La vuelta `wt-5` (2026-09-07 18:51) investigó a fondo el código real (no solo el spec: ubicó el
patrón hermano de NAP ya implementado como Fase 1a/1b en `LeafletMapRed.vue` líneas ~1438-1566, y
confirmó que la Fase 2 — `CableAltaRapidaController::store` — ya está mergeada, commit `75ad6d05`)
y descompuso correctamente el trabajo respetando la decisión de Irving en `q4`
(`origen_item_id=9990549`):

- **#9990581** — "Fase 3a — toggle + selector Cable\|Troncal + trazo por clics + snap a extremos
  (frontend)" → `pendiente_revision`, sin reclamar.
- **#9990582** — "Fase 3b — finalizar trazo (Enter/doble-clic) + formulario mínimo + vista previa
  local, SIN persistencia (depende de Fase 3a)" → `pendiente_revision`, sin reclamar.
- **#9990583** — "Fase 4 — conectar la vista previa local al POST real de alta rápida (persistencia,
  depende de Fase 3a + Fase 3b)" → `pendiente_revision`, sin reclamar.

## Por qué se quedó colgado

Justo después de escribir la decisión de descomposición en `comentarios_claude` (el texto quedó
literalmente cortado a media frase: "Confirmé por hash sha1 (RoadmapItem::claveOpcion) que las 4
preguntas del brief"), el proceso de `wt-5` murió (kill/OOM/freno a media vuelta) sin haber
intentado el cierre del padre. El log lo confirma: el único evento tras la decisión es
`soltar-claim` ("La vuelta de wt-5 terminó sin cerrar el item... se libera el reclamo y vuelve a la
cola como aprobado_revisor"). Sin un intento de `estado_aprobacion='completado'` de por medio, el
guard de paraguas nunca se disparó — el item pasó por `jarvis-ya-decidido` (que solo re-side el
brief ya respondido, sin tocar la descomposición) y terminó reclamado de nuevo (`wt-3`, esta
vuelta) sin que quedara trabajo propio pendiente.

## Verificación del estado real (esta vuelta)

```
9990549 (yo) | en_progreso        | excl=false | sid=wt-3
  9990581    | pendiente_revision | sin reclamar (Fase 3a: toggle + snap)
  9990582    | pendiente_revision | sin reclamar (Fase 3b: finalizar + form, sin persistencia)
  9990583    | pendiente_revision | sin reclamar (Fase 4: persistencia real)
```

Los 3 hijos (`origen_item_id=9990549`) están intactos, ninguno tocado desde su creación — la
descomposición original sigue siendo correcta.

## Corrección aplicada

Se ejecuta el intento de cierre faltante sobre `#9990549` (`estado_aprobacion='completado'`). El
guard de paraguas del modelo lo reenruta a `aprobado_irving` + `excluir_pool_automatico=true` (log
`paraguas_abierto`, "le quedan 3 sub-item(s) abierto(s)"), liberando `worker_sid`/`claimed_at` y
sacándolo del pool/reaper hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo
complete solo cuando #9990581, #9990582 y #9990583 cierren los tres.

**Sin cambio de código de negocio.** El trabajo real del modo dibujo de cable/troncal (toggle +
snap a extremos, finalizar trazo + formulario mínimo con vista previa local, y conectar esa vista
previa al POST real de alta rápida) sigue en #9990581/#9990582/#9990583, pendientes de que una
terminal los reclame.
