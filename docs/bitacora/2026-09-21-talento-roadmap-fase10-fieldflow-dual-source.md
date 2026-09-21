## 2026-09-21 — Roadmap interno de Talento, Fase 10: FieldFlowController dual-source

**Item revisado:** "FieldFlowController dual-source: aceptar/activar/onboarding" —
"Endpoints POST /campo/{id}/aceptar, /activar, /onboarding y /firma aún usan
TalentoWorkOrder::findOrFail directo."

**Hallazgo al investigar `/firma`:** ya estaba resuelto antes de esta sesión —
`TalentoFieldFlowController::storeSignature()` y `SignatureService` ya
resuelven dual-source (`origen: work_order|task`) desde otra vuelta. La nota
del roadmap quedó desactualizada en ese punto; los 3 que sí seguían rotos eran
`accept()`/`confirmActivation()`/`onboard()` de `FieldFlowService`.

**Hallazgo de schema (mismo patrón que Fase 11):** `talento_work_order_activations`
y `talento_installation_surveys` YA tenían `tarea_id` (nullable, FK a `tasks`) y
`work_order_id` YA nullable — de la misma migración de Capa 4.1/4.3 que preparó
`talento_responsibility_windows`. El hueco real: los modelos Eloquent
(`TalentoWorkOrderActivation`, `TalentoInstallationSurvey`) nunca declararon
`tarea_id` en `$fillable`, y `FieldFlowService` nunca lo usaba.

**Decisión de diseño (documentada en la propia clase):** el estado del
sub-flujo (aceptada/activada/onboarding) se rastrea por la presencia y
timestamps de la fila hija — **no se empuja a `tasks.status`**. `tasks` es una
tabla compartida con Scheduling/CRM/otros módulos que solo conocen
`ToDo/InProgress/Done/Archivado`; escribir `pending_activation`/`active`/
`survey_pending` ahí (aunque la columna es `varchar` y lo aceptaría sin error)
rompería esos consumidores en silencio. Con la fila de activación/encuesta
como fuente de verdad del sub-estado, no hace falta.

**Custodia de módem:** se omite para tasks a propósito — requiere
`inventory_item_id` resuelto, que hoy solo existe en `talento_work_orders`.
Mismo guard condicional que YA existía para work_orders sin ese campo resuelto
(`if ($order->modem_sn && $order->inventory_item_id && ...)`) — no es un caso
especial nuevo, es el mismo guard aplicándose naturalmente.

**Activación OLT SÍ funciona igual para tasks** — solo necesita `olt_onu_id`,
que `tasks` ya tiene como columna propia (confirmado en BD).

**Bug encontrado y corregido en el propio diseño antes de mergear:** mi primer
borrador de `getActivation()`/`getSurvey()`/`submitSurvey()` usaba
`where('work_order_id', $id)->orWhere('tarea_id', $id)` — pero
`work_order_id` y `tarea_id` son secuencias de ids **independientes**, así que
un id=5 de work_order y un id=5 de task (de una orden totalmente distinta)
podrían mezclarse. Corregido a resolver el origen real primero
(`TalentoWorkOrder::whereKey($id)->exists()`) y consultar solo esa columna —
mismo criterio que ya usa `OrdenTrabajoUnifiedService::resolveTask()`.

**Verificado (datos reales de dev, en transacciones con rollback — nunca se
escribió nada real):**
- Ciclo completo sobre una task real: guard sin firmas → falla correcto;
  con firmas → `accept()` crea la fila de activación con `tarea_id`; segundo
  `accept()` → falla "ya fue aceptada"; `confirmActivation()` → actualiza la
  MISMA fila; `onboard()` antes de activar → falla correcto; después de
  activar → crea la encuesta con `tarea_id`; `submitSurvey()` → marca
  `task.status='Done'` + `validated_at`.
- Ciclo completo sobre una OT sintética (`TalentoWorkOrder`) dentro de la
  misma transacción: accept→pending_activation, confirmActivation→active,
  onboard→crea encuesta con `work_order_id`, submitSurvey→status `validated`
  — **idéntico al comportamiento original**, cero regresión.
- `getActivation()`/`getSurvey()` (controller) resuelven correctamente por
  `tarea_id` para una task.
- `autoCloseSurveys()` (cron) cierra correctamente una encuesta vieja
  originada en task (antes solo intentaba `TalentoWorkOrder::where('id',
  $survey->work_order_id)` con `work_order_id=null` → no-op silencioso).

**Hallazgo relacionado, fuera de alcance de este item (documentado, no
tocado):** `TalentoFieldFlowController::fieldFlowState()` — el endpoint que
alimenta la pantalla admin completa (`TalentoCampo.vue`, ya con tema Torre)
— sigue siendo `TalentoWorkOrder::with(...)->findOrFail($workOrderId)`
directo, sin dual-source. La pantalla usa `flow.order?.status ===
'pending_activation'/'active'/'survey_pending'` para decidir qué tarjetas
mostrar; como decidí NO escribir esos valores en `tasks.status` (ver arriba),
arreglar `fieldFlowState()` requeriría computar un "status virtual" solo para
esa respuesta JSON (derivado de la presencia/timestamps de
activation/survey), no una simple resolución dual-source como los otros 4.
Los 4 endpoints de escritura (el alcance literal de este item) ya funcionan
correctamente por API — lo que falta es que la PANTALLA ADMIN los pueda
alcanzar para una task real. Se encontró además un comentario en
`PortalTecnicoController::aceptarOt()` ("NO invoca FieldFlowService::accept
(state-machine WO-only)") que confirma que este mismo hueco ya se había
trabajado por fuera con un handoff simplificado propio — no se tocó, es una
implementación deliberada aparte.

Rama `fix/talento-roadmap-fase10-fieldflow-dual-source`, mergeada a `main`.
Sin migraciones (el schema ya estaba listo).
