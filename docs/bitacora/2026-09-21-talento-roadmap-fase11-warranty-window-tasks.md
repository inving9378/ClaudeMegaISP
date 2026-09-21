## 2026-09-21 — Roadmap interno de Talento, Fase 11: WarrantyWindowService para tasks (Capa 6.1)

**Item revisado:** "WarrantyWindowService::refreshWindow() para tasks (Capa 6.1)" —
`validarAdmin` rama task omitía `refreshWindow()` porque el servicio exigía un
`TalentoWorkOrder` model. Afecta tareas con `is_billable=true` y
`client_main_information_id` (instalaciones nuevas hechas como Task en vez de
`TalentoWorkOrder`).

**Investigación previa a tocar código:** el trabajo de fondo (Capa 4.1/4.3, otra
sesión) ya había agregado `tarea_id` (nullable, FK a `tasks`) a
`talento_responsibility_windows` — confirmado por `SHOW COLUMNS` contra la BD
real de dev. Lo que faltaba era más angosto de lo que sugería la nota del
roadmap:
1. `TalentoResponsibilityWindow::$fillable` nunca incluyó `tarea_id` (columna
   existía en BD, Eloquent la descartaba en silencio).
2. `talento_responsibility_windows.source_work_order_id` seguía **NOT NULL** —
   la migración que relajó `work_order_id` en las demás tablas hijas
   (`2026_06_08_214726`) excluyó esta tabla a propósito porque su columna se
   llama distinto (`source_work_order_id`, no `work_order_id`) y el `WHERE
   COLUMN_NAME = 'work_order_id'` de esa migración no la encontró.
3. `WarrantyWindowService::refreshWindow()` solo aceptaba un `TalentoWorkOrder`
   model completo.

**Fix:**
- Migración nueva (aditiva, mismo patrón que `2026_06_08_214726`): relaja
  `source_work_order_id` a nullable, recrea el FK igual.
- `TalentoResponsibilityWindow::$fillable` += `tarea_id`.
- `WarrantyWindowService::refreshWindow()` reescrito con **parámetros
  desacoplados** (`colaboradorId, clientId, cajaId, sourceWorkOrderId=null,
  sourceTaskId=null`, exige exactamente uno de los dos últimos) — exactamente
  la opción que la propia nota del roadmap proponía. Se agregó
  `refreshWindowForOrder(TalentoWorkOrder $order)` como conveniencia para no
  tocar la forma de llamar desde la rama work_order existente.
- `OrdenTrabajoUnifiedService::validarAdmin()`: rama work_order reapuntada a
  `refreshWindowForOrder()` (mismo comportamiento); rama task ahora SÍ llama
  `refreshWindow()` — resuelve `clients.id` desde
  `client_main_information_id` y el colaborador desde el primer usuario
  asignado a la task (mismo patrón que `adminItemFromTask()`), solo si
  `task->is_billable` y ambos resuelven.

**Verificado (con datos reales de dev, en transacción con rollback — nunca se
escribió nada real):** simulé el flujo completo con una task billable real
(is_billable=1, client_main_information_id real) → `refreshWindow()` creó la
ventana correctamente con `tarea_id` poblado y `source_work_order_id=null`;
verifiqué que desactiva ventanas previas del mismo colaborador+cliente igual
que la rama work_order; verifiqué la rama work_order sigue funcionando
idéntico (`refreshWindowForOrder`). Rollback aplicado — la BD real quedó
exactamente como estaba.

Rama `fix/talento-roadmap-fase11-warranty-window-tasks`, mergeada a `main`.
Migración corrida en `main` tras el merge.
