# Fix — /talento/campo "Ver flujo" no mostraba nada

## 2026-09-21 12:30 — Reporte de Irving y corrección

### Reporte

"aca, /talento/campo al dar lick en ver flujo no se ve nada" — al hacer clic en
"Ver flujo" de cualquier orden listada en Órdenes de Campo, el panel se abría
pero quedaba en blanco (solo el botón "Volver" y un título vacío "OT # —").

### Causa raíz

El listado de "Órdenes de Campo" (`OrdenTrabajoUnifiedService::listForAdmin()`)
mezcla dos fuentes de datos con **secuencias de id independientes**:
`talento_work_orders` y `tasks` (tipo=campo). En dev, `talento_work_orders`
tiene **0 filas** — todo lo que aparece hoy en la lista viene de `tasks`.

El endpoint que arma la pantalla completa (`GET /talento/api/campo/{id}/estado`,
`TalentoFieldFlowController::fieldFlowState()`) seguía buscando la orden
únicamente en `talento_work_orders` (`::findOrFail($workOrderId)`), así que
para cualquier fila de la lista (todas, hoy) el backend respondía 404. El Vue
no capturaba ese error, así que la pantalla se quedaba vacía sin ningún aviso.

Otros sub-pasos del mismo flujo (Firmas, Aceptar, Activación, Onboarding/
Encuesta) **ya sabían** resolver la orden por las dos vías — el trabajo de
soportar `tasks` ya se había hecho para esos, incluidas migraciones aditivas
que agregaron una columna `tarea_id` (nullable, FK a `tasks`) a las 6 tablas
hijas del flujo. Pero quedaron 2 piezas sin ese soporte: **Evidencia
fotográfica** y **Validación IA**, además del propio endpoint agregador
`fieldFlowState()`. Sus modelos Eloquent (`TalentoWorkOrderMedia`,
`TalentoWorkOrderIaValidation`) ni siquiera declaraban `tarea_id` en
`$fillable`, así que aunque se hubiera intentado escribir ahí, se habría
descartado en silencio.

### Corrección

- **Nuevo:** `app/Modules/Addons/Talento/Support/FieldFlowEntity.php` — punto
  único para resolver "esta orden de campo, ¿es una fila de `talento_work_orders`
  o de `tasks`?" (antes vivía duplicado, con variantes, en 3 sitios distintos).
- `TalentoWorkOrderMedia`/`TalentoWorkOrderIaValidation`: agregado `tarea_id` a
  `$fillable` (ya existía la columna en BD desde una migración previa, solo
  faltaba en el modelo).
- `FieldMediaService::store()`: ahora recibe el origen (`work_order`/`task`) y
  escribe en la columna correcta; el chequeo de proximidad GPS se salta para
  `tasks` (no tienen `latitude`/`longitude` propias, no hay contra qué
  comparar) sin romper nada.
- `FieldIaValidationService::validateOrder()`: resuelve la orden por las dos
  vías y arma sus consultas de media/registro con la columna correcta.
- `TalentoFieldFlowController`: `fieldFlowState()` (el endpoint reportado),
  `uploadMedia()`, `listMedia()`, `getIaValidation()`, `getSignatures()`,
  `storeSignature()` — todos alineados al mismo patrón ya usado por
  `getActivation()`/`getSurvey()`. `fieldFlowState()` reutiliza
  `OrdenTrabajoUnifiedService::showForAdmin()` para armar el objeto `order`
  (evita reinventar esa resolución una vez más).
- `FieldFlowService::resolveEntity()`/`clientIdForTask()`: pasan a delegar en
  el helper nuevo (eran la fuente original del patrón, quedan sin duplicación).

### Verificación (tinker, datos reales, limpiados al terminar)

Con la tarea de campo real #1681 (técnico ISAAC, "Instalación nueva",
`status=Done`):

- `fieldFlowState(1681)` → antes 404, ahora 200 con `order.id/status/
  colaborador/type` completos.
- Subida de evidencia (`uploadMedia`) → 201, archivo guardado en
  `talento/media/tarea_1681/`, fila con `tarea_id=1681`/`work_order_id=NULL`.
- Ejecutar Validación IA (`runIaValidation`) → corre sin error, persiste con
  `tarea_id` correcto.
- `fieldFlowState()` tras la subida → refleja la foto nueva con su URL.
- **Regresión:** una orden real de `talento_work_orders` (creada y revertida
  dentro de una transacción) sigue resolviendo por `work_order_id` exactamente
  como antes — cero cambio de comportamiento para ese camino.

Sin cambios de frontend — el bug era 100% de backend.
