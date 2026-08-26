# Item #222 — "Talento: DashboardService::tecnicoPreview y ::team truenan por with('level')" (VERIFICACIÓN — premisa incorrecta)

## Premisa del item

> Ambos métodos hacen `with('level')` sobre `TalentoColaborador`, que NO tiene esa relación →
> excepción en tiempo de ejecución. Bug preexistente, arreglo acotado: definir la relación o quitar
> el eager-load. _(Detectado en el inventario de módulos del 2026-08-08.)_

## Hallazgo

La premisa **"`TalentoColaborador` no tiene la relación `level()`"** es falsa al día de hoy: la
relación ya existe, fue agregada por el commit `c37feef2` ("fix(talento): relación level para
DashboardService", 2026-07-11), **más de un mes antes** de que el auditor (#559) generara este
item (2026-08-25). El fix de entonces:

- Agregó `level_id` a `$fillable` (antes se descartaba silenciosamente por mass-assignment, y
  `LevelService::promote()` no persistía el nivel real).
- Declaró `TalentoColaborador::level()` → `belongsTo(TalentoLevel::class, 'level_id')`
  (`app/Modules/Addons/Talento/Models/TalentoColaborador.php:47-50`).

Los dos consumidores que el item señala —`DashboardService::tecnicoPreview()` (línea 127,
`with('level')`) y `DashboardService::equipoPreview()` (línea 276, `with(['user', 'level'])`; es
el método al que el item se refiere como "`::team`") — ya resuelven la relación sin excepción.

**Verificado en esta sesión** (tinker, sin rollback necesario — solo lectura):
- `tecnicoPreview($colaboradorId)` → ejecuta completo, sin `RelationNotFoundException`.
- `TalentoColaborador::with(['user','level'])->first()` → `level` resuelve (`null` cuando el
  colaborador no tiene `level_id` asignado, que es el comportamiento correcto de un `belongsTo`
  opcional, no un error).

## Por qué el auditor lo re-detectó

`CLAUDE.md` (sección "MOTOR DE COMPENSACIÓN TALENTO") traía una nota de deuda **desactualizada**
("🐛 Deuda registrada aparte... arreglar en sesión futura") que describía el bug como pendiente
pese a que ya se había cerrado en julio. El auditor de módulos escanea ese tipo de notas como
fuente de gaps — con la nota stale, regeneró el mismo hallazgo ya resuelto. Se corrige esa nota en
este mismo commit para que no se vuelva a recrear.

## Conclusión

Sin cambio de código — el gap que pedía el item ya no existe (fix de `c37feef2`, previo a la
creación del item). Se corrige únicamente la documentación stale que causaba la re-detección.
