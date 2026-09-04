# Item #924 — cierre del bucle reap sobre paraguas ya descompuesto (root-cause de #32)

## Contexto

`#924` ("Root-cause: paraguas cierre-en-cascada dejó pasar un nivel-C sin merge a 'completado' —
item #32", sub-item de seguimiento de #883) pedía investigar en dev por qué el hook de cierre en
cascada de `RoadmapItem::boot()` dejó completar a `#32` (nivel-C, con rama propia y sin
`merge_commit`) cuando el guard bloque (1) debía impedirlo, y — según lo que se confirmara —
endurecer el punto exacto que lo esquivó más un test de regresión. Irving aprobó explícitamente
las 3 preguntas estructuradas del item, todas con la **Opción 1 recomendada**:

- **q1**: investigar primero (reconstruir la secuencia, reportar hallazgos con propuesta) antes de
  tocar código.
- **q2**: reabrir #32, ejecutar el merge faltante del nivel-C y re-cerrarlo por el flujo normal.
- **q3**: agregar un guard pre-cierre en el paraguas que valide que TODOS los sub-items (niveles C)
  tienen `merge_commit` registrado antes de permitir `completado`; si falta alguno, escalar en vez
  de cerrar.

Una vuelta previa (`wt-2`) ya hizo el trabajo correcto de investigación y descomposición:

1. Corrió `circuito:cabida` → **NO CABE** (el propio comando ahora reporta `ya_descompuesto`,
   confirmando que la descomposición ya existe).
2. Descompuso el trabajo en 2 sub-items (`origen_item_id=924`):
   - **#9990012** — "Reproducir en dev la carrera del cierre-en-cascada de #32 y confirmar el
     mecanismo exacto que esquivó el guard (1)" (`aprobado_revisor`, sin reclamar).
   - **#9990013** — "Endurecer el punto exacto que dejó pasar a #32 sin merge (según causa
     confirmada en el sub-item de reproducción) + test de regresión" (`requiere_irving`, sin
     reclamar — depende del hallazgo de #9990012, correctamente bloqueado hasta entonces).

Esa parte fue correcta y **no se repite aquí**. Lo que faltó: esa vuelta nunca intentó **cerrar**
`#924` después de crear los sub-items. El item quedó sin que nadie liberara el claim correctamente
como paraguas; el reaper de huérfanos lo re-encoló (`reap_count=1`, log `huerfano_reencolado` /
`claim_liberado_al_morir_la_vuelta`) y un timeout adicional lo escaló a `requiere_irving` con
`veces_timeouteo=1` — mismo síntoma exacto que la familia ya documentada en `CLAUDE.md`:
`#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#905`/`#878`/`#906`/`#907`: un paraguas correctamente
descompuesto que nunca recibió el intento de cierre que activa el guard de "no completar mientras
queden hijos abiertos".

## Verificación de esta vuelta

- Query directa: `#9990012` y `#9990013` (`origen_item_id=924`) siguen intactos, `worker_sid=null`
  — ninguno fue tocado por nadie más, la descomposición original seguía siendo la correcta.
- Guard vigente: `grep` confirma que el bloque "(2b) PARAGUAS" (`RoadmapItem.php` ~301-326) sigue
  presente sin cambios estructurales.
- Intento de cierre: `RoadmapItem::find(924)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` lo reenrutó automáticamente a `aprobado_irving` + `excluir_pool_automatico=true`,
  agregando al log el evento `paraguas_abierto` ("le quedan 2 sub-item(s) abierto(s): no se
  completa. Queda como paraguas y cierra solo cuando el último de ellos cierre."). Confirmado
  leyendo `$item->log` tras el `save()` (evento `paraguas_abierto` seguido del evento `flags` que
  audita el cambio de `excluir_pool_automatico`).

## Resultado

`#924` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
`#9990012` y `#9990013` cierren — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya
verificado en las sesiones anteriores de este mismo bug) completa `#924` solo, sin intervención
manual.

**Sin cambio de código de negocio.** El trabajo técnico real de la investigación aprobada por
Irving — reproducir la carrera exacta que dejó pasar a #32 sin merge (#9990012, `aprobado_revisor`,
lista para tomarse) y endurecer el guard con test de regresión según lo que confirme esa
reproducción (#9990013, `requiere_irving`, correctamente bloqueado hasta tener la causa exacta) —
sigue en esos dos sub-items.
