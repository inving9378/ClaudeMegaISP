# 11 · Ciclo de vida de la orden: estados, transiciones y quién puede cada una (UNI-T D1)

Proyecto UNI-T (paraguas #862), item #881. **Es un diseño para aprobar: no cambia código ni datos.** Solo conteos y nombres técnicos.
Leyenda: **[código]** leído en el repo (2026-10-26) · **[DEV]** observado en DEV · **[por verificar]** · **[propuesta]** decisión de diseño pendiente de aprobación.
Base: `08-mapa-escritores.md` (A3) y `09-consumidores-tasks.md` (A4).

## 1. Estado actual

Dos vocabularios conviven. `OrdenTrabajoUnifiedService` (OTU) los traduce con `TASK_STATUS_MAP`.

| Almacén | Valores | Notas |
|---|---|---|
| `tasks` | `ToDo`, `InProgress`, `Done`, `Archivado` (+ bandera `archived`) | [código] Cancelar pone `Archivado` con `archived=0`, por eso la orden sigue en el calendario. `validated_at` distingue «hecha» de «validada». WarRoom escribe `To Do` (inválido, falla en silencio; A4 §2.1). |
| `talento_work_orders` | `pending`, `in_progress`, `completed`, `validated`, `cancelled`, `incidencia` | [código] `incidencia` existe solo aquí; para `tasks` OTU responde `incidencia` sin guardar estado. |

No existen «aceptada», «en camino», «pausa» ni «devuelta». `accept` de FieldFlow ocurre después del cierre [código]. En `tasks` el cierre del técnico (`completar`) pone `Done` y `validated_at` a la vez (auto-validación, OTU :630): hoy **la validación del admin no es una compuerta real** para órdenes-tarea.

## 2. Estados propuestos (vocabulario único, lado OT)

| Estado | Significado | Equivale hoy a (`tasks`) | Equivale hoy a (WO) |
|---|---|---|---|
| `pending` Pendiente | Creada y asignada, sin iniciar | `ToDo` | `pending` |
| `in_progress` En curso | El técnico la inició (o subió la primera evidencia) | `InProgress` | `in_progress` |
| `completed` Completada, pendiente de validación | Cierre técnico hecho; **no cuenta para pago** | `Done` con `validated_at` nulo | `completed` |
| `validated` Validada | Un validador la aprobó; **única que paga** | `Done` con `validated_at` | `validated` |
| `returned` Devuelta con motivo | El validador la regresa al técnico para corregir | **nuevo** | **nuevo** |
| `cancelled` Cancelada | Baja lógica con motivo; conserva evidencias | `Archivado` | `cancelled` |
| `incidencia` (ya existe; se conserva como **evento**, no como estado final) | El técnico no pudo completar | sin estado | `incidencia` |

`incidencia` se mantiene como estado solo en WO por compatibilidad; en el modelo único pasa a ser un **evento** (`order.incident`) que deja la orden en `in_progress` o `pending` y avisa al validador. [propuesta]

## 3. Cómo se guarda sin romper `tasks.status` [propuesta]

`tasks.status` **no cambia de valores** (los consumidores de A4 leen `ToDo/InProgress/Done` literales: WarRoom, calendario, Scheduling, MegaFamilia, pago). Se agrega un estado aditivo:

- **Opción recomendada:** columna nueva `tasks.order_state` (string nullable, solo para filas con `talento_type_id`) con los valores de §2, **derivada y mantenida por OTU** junto a `status`. Los lectores viejos siguen viendo `status`; los nuevos leen `order_state`. `NULL` = fila legada, se interpreta con la tabla de §2 (`Done`+`validated_at` ⇒ `validated`).
- Alternativa descartada: derivar todo de `validated_at` sin columna. No distingue `returned` ni `cancelled` de `Archivado` genérico.

Reglas de coherencia (se mantienen en un solo método de OTU):

| `order_state` | `status` | `validated_at` | `finish_at` |
|---|---|---|---|
| `pending` | `ToDo` | nulo | nulo |
| `in_progress` | `InProgress` | nulo | nulo |
| `completed` | `Done` | nulo | fijo |
| `validated` | `Done` | fijo | fijo |
| `returned` | `InProgress` | nulo | nulo (`Task::boot` ya lo anula al salir de `Done`) |
| `cancelled` | `Archivado` | sin cambio | sin cambio |

Consecuencia para el pago: `LiquidationService::countBillableUnits` ya exige `status='Done'` + `validated_at` en la semana [código]; **no cambia**. `returned` limpia `validated_at` solo si la orden aún no se liquidó (ver §6, regla de bloqueo).

## 4. Tabla de transiciones

«Quién» = rol/permiso **y** relación con la orden. «Evento» = lo que se registra (actividad de Talento + `activity_log`).

| # | De → A | Quién | Evento | Compuerta |
|---|---|---|---|---|
| T1 | (nada) → `pending` | Admin/programador con permiso de crear orden (Scheduling o Talento) | `order.created` | Alta única por OTU; tipo y puntos snapshot |
| T2 | `pending` → `in_progress` | **Asignado** a la orden (relación `task_user`/colaborador) | `order.started` | Hoy `iniciar` exige `ToDo` [código] |
| T3 | `pending`/`in_progress` → `in_progress` (reasignar/reprogramar) | Admin con permiso de edición | `order.rescheduled`, `order.reassigned` | No cambia estado; usa `TaskScheduleService` |
| T4 | `in_progress` → `completed` | **Asignado** | `order.completed` | Checklist + evidencias obligatorias + gate dBm (OTU `validarEvidencias`) |
| T5 | `completed` → `validated` | Validador: permiso de validar (hoy `validarAdmin`), **distinto del asignado** | `order.validated` | Idempotente; es el único paso que paga |
| T6 | `completed` → `returned` | Validador (igual que T5) | `order.returned` con `motivo` obligatorio | Notifica al asignado |
| T7 | `returned` → `in_progress` | Asignado (al retomar) o automático al subir evidencia | `order.resumed` | — |
| T8 | `pending`/`in_progress`/`returned` → `cancelled` | Admin con permiso de cancelar | `order.cancelled` con `motivo` obligatorio | Prohibido si `validated` o ya liquidada |
| T9 | `in_progress` → `in_progress` (incidencia) | Asignado | `order.incident` | No cambia estado |
| T10 | `validated` → (ninguna) | — | — | Estado final; corrección solo por ajuste en el ledger (decisión económica, no aquí) |

Transiciones **prohibidas**: saltar `in_progress` salvo por reapertura administrativa; `completed` → `cancelled` sin devolver antes; cualquier cambio de un asignado sobre una orden ajena (404, igual que B5a).

## 5. Quién (resumen rol + relación)

| Actor | Puede |
|---|---|
| Técnico/colaborador asignado | T2, T4, T7, T9 sobre **sus** órdenes |
| Admin de Talento / programador | T1, T3, T8; T5 y T6 si tiene permiso de validar |
| Validador (≠ asignado) | T5, T6 |
| Super-administrator / DESARROLLADOR | Todas (bypass actual del middleware) |
| MegaFamilia (app del técnico) | T2 y T4 **solo vía OTU** (hoy escribe `status` directo, sin compuerta: A3/A4) |
| WarRoom, FieldFlow (`talento:cerrar-encuestas`) | No cambian estado de orden; FieldFlow sigue leyendo `validated_at` |

Los permisos concretos se definen en el item de matriz (10-matriz-permisos); este diseño solo pide dos nombres lógicos nuevos: `validar` y `cancelar` con alcance por relación. Se otorgan con `givePermissionTo`, nunca `sync*`.

## 6. Reglas transversales

1. **Un solo escritor:** todo cambio de estado pasa por OTU (método único `transicionar(orden, evento, actor, motivo)`). Dejan de escribir `status`: `Scheduling::update/archive`, `MegaFamilia::updateOrden`, WarRoom observer (A3 §5).
2. **Auto-validación (OTU :630):** hoy `completar` fija `validated_at`. En el diseño, `completar` deja `completed` y la validación es T5. Es un cambio de comportamiento que **mueve cuándo se paga**: va tras interruptor de `config/unificacion.php` (apagado = comportamiento actual) y requiere decisión de Irving (ver §9, P1).
3. **Bloqueo por liquidación:** una orden cuya semana de pago ya cerró no puede pasar a `returned` ni `cancelled`.
4. **Motivo obligatorio** en T6 y T8; texto libre más código corto.
5. **Auditoría:** cada evento escribe `activity_log` (`log_name='orden'`) y una fila en `talento_activities` si existe la tabla hija.

## 7. Efecto por consumidor (A4)

| Consumidor | Efecto | Acción |
|---|---|---|
| `LiquidationService::countBillableUnits` | Sin cambio (`Done`+`validated_at`). Solo cambia **cuándo** se fija `validated_at` si se retira la auto-validación | Mantener filtro; probar con conjunto fijo `UNI-PRUEBA` |
| `OrdenTrabajoUnifiedService` | Es el escritor único; agrega `returned` a `TASK_STATUS_MAP` y a `$transitions` | Cambio principal de D |
| MegaFamilia `tecnicoOrdenes`/`updateOrden` | `returned` ⇒ `en_proceso`; `completed` ⇒ `completada`; `validated` ⇒ `completada` (mapeo existente, sin estado nuevo visible). Debe llamar a OTU para T2/T4 | Adaptador: **no cambia la forma de respuesta** (regla 11) |
| App de Talento (`/ots/*`) | Agrega estado `returned` (mostrar como «Devuelta, corregir») y motivo; el resto igual | Campo opcional nuevo, retrocompatible |
| WarRoom KPI/Insights | `Done` sigue siendo cerrado; `returned` cuenta como abierta (`InProgress`) | Sin cambio; la separación ticket/orden queda como hallazgo de A4 |
| Scheduling (lista, calendario, filtros) | Sigue con `ToDo/InProgress/Done/Archivado`; `order_state` no se muestra | Sin cambio; `Task::TASK_COLOR` no necesita color nuevo |
| Fullcalendar | `Archivado` con `archived=0` sigue visible: una orden cancelada no sale del calendario. **Hallazgo:** debería ocultarse o tacharse | Fuera de D1; decisión de producto |
| FieldFlow (`accept`, encuestas) | Sin cambio; `accept` posterior al cierre se conserva | Documentar que no es un estado |
| HealthBonus / ledger | Siguen referenciando `Task`; la cancelación es lógica, no borra | Coherente con regla de no borrado |
| SmartImport | Importa `status`; `order_state` queda `NULL` ⇒ interpretación legada | Sin cambio |
| Portal cliente | No toca órdenes | Sin impacto |

## 8. Compatibilidad con la app y MegaFamilia

- No se renombra ni elimina ningún valor de `status` ni de las respuestas JSON vigentes; `returned` se **agrega** como campo/valor nuevo solo para clientes que lo pidan. [propuesta]
- El mapeo de MegaFamilia `ToDo/InProgress/Done ↔ pendiente/en_proceso/completada` queda intacto; un estado nuevo cae a `pendiente`/`ToDo` por diseño actual, por eso `returned` se proyecta a `InProgress`.
- El límite de 50 órdenes y la ausencia de filtro de fecha de MegaFamilia (A4 §2.2) no se tocan aquí.

## 9. Preguntas abiertas para Irving (no se resuelven aquí)

- **P1 (económica):** ¿retirar la auto-validación del cierre del técnico y exigir validación humana para pagar órdenes-tarea? Cambia el momento del pago de esas órdenes. Recomendación: sí, tras interruptor, con dry-run comparativo.
- **P2:** ¿quién valida cuando asignado y validador serían la misma persona (equipos de una sola persona)? Recomendación: permitir con permiso explícito y registrarlo.
- **P3:** ¿la orden `cancelled` debe salir del calendario? Hoy no sale.

## 10. Revisión contra A3/A4 (criterio «ningún escritor queda fuera»)

Cada escritor de estado de A3 §1 (Iniciar, Completar, Validar, Cancelar, Borrar, Incidencia) tiene su fila en §4 o §6.1; cada consumidor de A4 §2 tiene su efecto en §7. Escritores no cubiertos por transición propia, anotados a propósito: borrado en duro de `tasks` (A4 §5, SoftDeletes es item aparte) y `ConciliarFechasTasksCommand`/`UpdateTasksOldCommand` (solo fechas, no estado).

## 11. Pendientes para implementar (items siguientes, no D1)

1. Migración aditiva `tasks.order_state` + backfill idempotente con simulación.
2. Método único `transicionar` en OTU detrás de `UNIFICACION_CICLO_ORDEN` (apagado por defecto).
3. Estado y motivo «devuelta» en OTU, app y portal.
4. Adaptar `Scheduling::update/archive` y `MegaFamilia::updateOrden` para delegar en OTU.
5. Pruebas contra `megaisp_test` por nombre de archivo y la lista de regresión de A4 §4.
