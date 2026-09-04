# Item #1000013 — Seguimiento de la pregunta sin resolver de #1000001 (RESUELTO — la pregunta ya no tenía nada que autorizar)

Seguimiento auto-generado al cerrar #1000001 ("MR-01b — Mapas: entidades de puerto/hilo, conteo
por tipo de elemento y árbol de carpetas") con 1 pregunta `requiere_irving` sin `opcion_elegida`:
**"¿Autorizar MR-01b (entidades de puerto/hilo, conteo por tipo de elemento y árbol de carpetas
del módulo Mapas)?"**

## Carrera de timing (misma familia que #733/#741/#818)

El item #1000013 nació el 2026-09-03 16:13:49 (por `jarvis:generarSeguimientoPreguntas`), leyendo
el arreglo `preguntas[]` de #1000001 en el estado en que quedó tras la escalada inicial del
revisor (16:06:19, "categoría amplio/duda"). Pero esa escalada ya había sido **superada por un
des-trabe de Opus** dos minutos después (16:08:09, `categoria:tecnico_seguro`), que reclasificó el
item como auditoría read-only aditiva y reversible, sin frontera dura — y #1000001 se ejecutó,
verificó y **mergeó a `main`** a las 16:14:55 (commit `0afe1323`), **1 minuto después** de que
naciera el propio #1000013. Irving no respondió la pregunta de #1000013 hasta las 18:43:13 — más
de 2 horas después de que el trabajo ya estuviera cerrado y en `main`.

Irving eligió la **Opción 2** ("Autorizar ejecución como cambio aditivo — migraciones nuevas con
`down()`, modelos y conteos de solo lectura, sin tocar UI sensible"), consistente con lo que de
hecho ya se había entregado.

## Por qué no hay nada que ejecutar ahora

Se releyó el item #1000001 completo (`comentarios_claude`, `reporte_coloquial`,
`docs/mapas-mr01b-item-1000001-verificacion.md`) y el item padre #937 (DoD explícito: "los 8
puntos respondidos, **cero escrituras**"; título "Paso 0 del rediseño" — únicamente auditoría, el
rediseño en sí es un paso futuro no decidido). Los 3 puntos de #1000013/#1000001 (2, 4, 6 del
prompt de #937) están **verificados y documentados**, y ninguno requería ni generó código nuevo:

- **(2) Puerto/hilo**: ya son entidades reales con tabla propia en el sistema vivo —
  `map_devices_ports` (19,229 filas), `map_ports` (17, splitters), `map_fibers` (17,242 filas).
  **No hace falta ninguna migración nueva** porque el esquema que la Opción 2 autorizaba crear
  **ya existe**.
- **(4) Conteo por tipo de elemento**: ya calculado por `SELECT`/`Schema::` puro contra
  `map_devices.type` (9 valores reales, 0 huérfanos en las 3 relaciones con datos) — el "conteo de
  solo lectura" que autorizaba la Opción 2 **ya se entregó**, sin necesidad de un endpoint/modelo
  nuevo (el dato vive en `comentarios_claude` + el doc de verificación).
  el catálogo legacy (`box_types`/`passive_equipment_types`/`active_equipment_types`) sigue vacío.
- **(6) Árbol de carpetas**: ya es `map_proyects` (self-referencing `parent_id`), profundidad
  máxima 11 (CTE recursiva), 306 carpetas con exactamente un hijo — mismo caso, ya entregado.

La Opción 2 hablaba de "migraciones nuevas" y "modelos" como la vía aditiva **si hiciera falta
crear algo** — pero la propia auditoría que motivó la pregunta ya había confirmado que las
entidades, los modelos (`MapDevicePort`, `MapPort`, `MapFiber`, `MapProyect`) y los conteos **ya
existían y ya se habían leído**, sin escritura alguna (así lo exige el DoD de #937). Autorizar
"construir cambio aditivo" sobre algo que ya existe y ya se auditó no tiene ejecución pendiente:
es la misma autorización, aplicada retroactivamente a un trabajo que ya estaba en `main` dos horas
antes de que Irving la diera.

## Alcance NO cubierto (a propósito, fuera de este item)

El "rediseño" en sí (Paso 1+ que #937 menciona como "Paso 0 del rediseño") **no está decidido ni
alcanzado por esta pregunta** — #937/#1000001/#1000013 son solo la fase de auditoría. Si Irving
quiere construir algo nuevo sobre puerto/hilo/conteos/árbol de carpetas (UI, endpoints, reportes),
eso es una decisión de producto separada y explícita, no implícita en esta autorización retroactiva.

## Verificación

- Releído #1000001 completo (estado `completado`, `merge_commit` presente, doc de verificación
  intacto en `docs/mapas-mr01b-item-1000001-verificacion.md`).
- Releído #937 (DoD "cero escrituras"; sin decisión de rediseño tomada).
- Confirmado que `opcion_elegida` de #1000013 (`c22a269ec03de97d`) corresponde a la Opción 2 del
  propio arreglo de opciones (hash `sha1` de 16 caracteres del texto de la opción).
- Sin cambio de esquema/código de aplicación: este documento es el único artefacto de esta vuelta.
