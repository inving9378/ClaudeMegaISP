# Item #9990385 — Seguimiento de las 2 preguntas sin resolver de #9990373 (verificación)

## Contexto

`#9990373` ("MR-36 `depende_de` nunca cierra sobre un paraguas sin `merge_commit` propio, bloquea
`#9990329`→`#942`") se cerró con 2 preguntas `requiere_irving` sin `opcion_elegida`. El generador de
seguimientos creó `#9990385` para traérselas de vuelta. Irving ya las respondió (log de `#9990385`,
2026-09-06 11:25:40, `decision: aprobar`), eligiendo en ambos casos la **Opción 1 recomendada**:

- **q1** — "¿Cómo resolver que `depende_de` no cierre sobre paraguas sin `merge_commit` propio?" →
  Opción 1: tratar el paraguas cerrado como "transparente" (si sus hijos verificados ya cerraron,
  contar la dependencia como satisfecha aunque el paraguas no tenga rama/merge propio).
- **q2** — "¿Cómo desbloquear `#9990329`→`#942` AHORA mientras se aplica el fix estructural?" →
  Opción 1: override manual puntual + registrar en bitácora, en paralelo al fix de fondo.

## Hallazgo — ambas ya estaban resueltas antes de que este item llegara a ejecutarse

**q1 (el fix estructural) ya está en `main`**, y coincide exactamente con la Opción 1 elegida:
commit `1f872e15` ("fix(circuito#9990373): depende_de reconoce paraguas cerrado sin merge propio"),
integrado a `main` vía `93a88800`, ambos el mismo día que se generó `#9990385` (2026-09-05
16:42/16:45), es decir **antes** de que Irving llegara a aprobar las preguntas (2026-09-06 11:25).
El método único `RoadmapCircuitoService::estaCerradoParaDependencia()`
(`app/Modules/Addons/Roadmap/Services/RoadmapCircuitoService.php:2576-2588`), usado tanto por
`dependenciasCerradas()` como por `esperandoDependencias()`, ya implementa la Opción 1 al pie de la
letra: un id cuenta como cerrado para `depende_de` si (a) es hoja `completado` con `merge_commit`
propio, o (b) es un paraguas `completado` que ya fue descompuesto y no le quedan sub-items abiertos
(`RoadmapItem::yaFueDescompuesto()` + `!tieneSubItemsAbiertos()`). El propio docblock del método cita
literalmente el caso real de `#9990329`/`#942` como la motivación.

**q2 (el desbloqueo puntual) resultó innecesario** — no porque se haya aplicado el override manual
de la Opción 1, sino porque el fix estructural de q1 llegó primero y basta por sí solo. Verificado
en la BD de dev (2026-09-06):

- `#942` y `#9990329` **ya tienen `merge_commit` propio** (`d6f1f4a7…` y `04b16538…`
  respectivamente) — llegaron a `main` por el `merge-runner` normal de sus propias ramas, no
  necesitaron el camino "paraguas transparente" para sí mismos.
- El log de ambos (`roadmap_items.log`) **no tiene ninguna entrada de override manual** — nadie
  marcó a mano una dependencia como satisfecha.
- `#943` (que depende de `#942`) resuelve hoy correctamente vía
  `esperandoDependencias(943)` → `{"faltan":[942],...}`: sigue esperando a `#942`, pero por una
  razón **real** (`#942` a su vez espera a `#941`, que es trabajo pendiente genuino de la cadena
  MR-04..MR-07), no por el bug de MR-36.
- `#9990329` (paraguas de 4 hijos) sigue con 1 hijo (`#9990391`) sin cerrar — también trabajo
  pendiente genuino, no el bug.

Es decir: la cadena de dependencias hoy funciona exactamente como debía tras el fix de q1, sin rastro
de que la situación de bloqueo descrita en `#9990373` siga vigente ni de que se haya necesitado un
override manual.

## Conclusión

Ambas preguntas de `#9990385` ya tenían respuesta aplicada en el código antes de que este item se
ejecutara — carrera de timing entre el generador de seguimientos (que lee `preguntas[]` sin
`opcion_elegida` al cerrar el padre) y el propio commit de cierre de `#9990373`, que trajo el fix en
el mismo acto. Mismo patrón ya documentado varias veces en `CLAUDE.md` (`#733`, `#741`, `#753`,
`#9990003`, `#9990353`, entre otros).

**Sin cambio de código de negocio.** Este item solo deja constancia por escrito de la verificación.
