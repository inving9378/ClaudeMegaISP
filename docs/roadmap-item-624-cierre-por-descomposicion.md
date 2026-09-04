# Item #624 — Cierre por descomposición (2026-08-29)

## Qué pedía el item

"Reconstruir items del incidente P0 que NO llegaron a mergear (pendientes/rechazados/abandonados)".
Sub-item de seguimiento de #177 (fase 1, ya cerrada): la fase 1 recuperó 326 items reconstruibles
100% desde git (commits `Integra circuito #N ... a main`). Quedaba sin reconstruir la parte que
**nunca llegó a mergearse** — sin fuente factual en git, solo en `/home/meganet/circuito/logs/`.

El propio item reconocía que esto era una decisión de alcance/criterio, no un hecho verificable con
un grep, y pedía escalar a Irving si hacía falta su decisión.

## Qué decidió Irving

El item se escaló (#338 revisor → requiere_irving, Des-trabe → brief con 3 preguntas). Irving
respondió las 3 preguntas del brief (2026-08-28, `aprobado_irving`):

- **q1** (cómo reconstruir): Opción 1 — generar un reporte de auditoría (query a la BD del circuito)
  con título/brief/estado final/razón de no-merge, entregado a Irving en tabla para que decida.
- **q2** (criterio de filtrado): Opción 1 — solo items con brief completo y nivel A/B que no
  llegaron a `merge_commit`.
- **q3** (cómo entran los reconstruidos): Opción 1 — nuevos items que referencian al original vía
  campo `reabre_item_id` (auditable, no ensucia el histórico del P0).

## Qué se hizo con esa decisión

`circuito:cabida` marcó el item como NO CABE en una sola vuelta (rama previa sin commits, ya
timeouteada antes). Siguiendo el flujo de descomposición del circuito, una vuelta anterior (wt-3,
2026-08-28) partió el trabajo en 3 sub-items concretos, uno por cada decisión de Irving, todos con
`origen_item_id=624`:

- **#742** — inventario crudo desde logs de vueltas (la materia prima para el reporte de q1).
- **#743** — tabla de auditoría para que Irving decida cuáles reconstruir (el entregable real
  que pide q1, con el criterio de q2 ya aplicado).
- **#744** — mecanismo de reconstrucción (`reabre_item_id`, la decisión de q3) — construir el
  mecanismo, **sin ejecutar** la reconstrucción todavía (eso espera a que Irving marque la tabla
  de #743).

Una vuelta posterior (wt-2) verificó esta descomposición contra `git diff main..rama` (0 commits
propios en la rama de #624, consistente con un cierre por descomposición) y no la repitió.

## Por qué #624 se cierra aquí

El alcance de #624 era **decidir y descomponer**, no escribir código de reconstrucción: la
reconstrucción real vive en #742/#743/#744, cada uno con su propio triaje y criterio de
aceptación. Ese trabajo ya está hecho y verificado dos veces (wt-3 lo creó, wt-2 lo confirmó). Este
cierre es solo para que #624 deje de quedar en un loop de re-encolado del reaper (bug ya anotado
por wt-2 en los comentarios del item: el reaper trataba el "0 commits" de un cierre limpio por
descomposición como si fuera una vuelta interrumpida sin avance).

**Sin cambio de código funcional** — este commit es el cierre documental del item; el trabajo
pendiente sigue abierto y rastreable en #742, #743 y #744.
