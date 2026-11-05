# 01d · Auditoría de órdenes, tickets, instalaciones y garantías

> Épica UNI, Documento 1 (estado actual), pieza D. Etapa solo documental: la BD de dev se consultó únicamente con `COUNT`/`GROUP BY`; no se cambió código ni datos.
> Fecha de medición: 2026-10-24 (dev). Contrato: secciones 6, 7 y 9 del plan maestro.

## 1. Resumen ejecutivo

1. **No existe «una orden». Existen cuatro objetos** que hacen el mismo papel sin llave entre sí: `tickets`, `tasks` (Scheduling), `talento_work_orders` (OT histórica de Talento) y `cut_installations` (alta/cobro de instalación). Solo `tasks.ticket_id` une ticket con tarea y está casi sin usar (2 de 1,666).
2. **Las órdenes nuevas ya no se guardan en `talento_work_orders`**: `TalentoWorkOrderController::store` crea una `Task` con `talento_type_id` y la asigna por `task_user` (`TalentoWorkOrderController.php:113-246`). La tabla vieja quedó como histórico (208 filas, 206 `validated`, creadas en bloque en oct-2026). Toda la capa `OrdenTrabajoUnifiedService` existe para leer ambas fuentes (`:20-30`, «dual-source»).
3. **La garantía de 6 meses está construida pero no opera**: `talento_responsibility_windows` tiene **0 filas**; la columna `inicia_garantia` solo la usa la BD (0 referencias en `app/`); la ventana arranca en `now()` al validar, no en la aceptación técnica.
4. **Choca con el contrato (§9)**: `WarrantyWindowService::classifyWarrantyOrder` marca la ventana como «responsabilidad del técnico» y pone puntos 0 / no pagable automáticamente (`WarrantyWindowService.php:71-84`). El contrato prohíbe responsabilizar o descontar en automático.
5. **Trazabilidad medible = casi nula**: 207 de 208 OT tienen `client_id` NULL y 0 tienen `accepted_at`, así que no se puede ligar incidencia posterior con la instalación original por esa vía.
6. **Sin historial**: `activity_log` no tiene ni una fila de `Task`, `Ticket` ni `TalentoWorkOrder` (130,790 filas en total, solo de cliente/pagos/usuario).
7. **Sin vista de seguimiento**: no hay pantalla de «atrasadas / sin asignar» ni validación de conflictos de agenda. Medido: 150 tareas `ToDo`/`InProgress` con fecha vencida y 118 tickets `Nuevo` (48 sin responsable).

## 2. Entidades y cómo se relacionan

| Entidad | Tabla (dev) | Cliente | Vínculo con otras | Notas |
|---|---|---|---|---|
| Ticket | `tickets` (899; 1,470 mensajes en `ticket_threads`) | `customer_lead` → `clients.id` (888/899 resuelven; el doc `docs/modulos/tickets.md` lo describe como «responsable», el modelo lo usa como cliente: `Ticket.php:77-79`) | Ninguno hacia OT. `tasks.ticket_id` (2 filas) | `estado` solo `Nuevo`/`Cerrado`/`Reciclado`; `type` = Pregunta/Solicitud de función/Problema (8 «Problema») |
| Tarea (Scheduling) | `tasks` (1,666; 5 con `talento_type_id`) | `client_main_information_id` (931), `crm_lead_id` (1) | `project_id` (todas; el «proyecto» es el tablero), `ticket_id`, `olt_onu_id`, `caja_id` | Dos naturalezas: `tipo=interna` (1,661) y `tipo=campo` (5). Varios usuarios por `task_user` (1,374 tareas con más de uno, casi todas internas) |
| OT Talento (histórica) | `talento_work_orders` (208) | `client_id` (solo 1 con valor) | `olt_onu_id`, `caja_id`, `inventory_*`. **Sin `ticket_id`** | Un solo `colaborador_id`. 5 colaboradores distintos |
| Alta de instalación | `cut_installations` (175, ene–oct 2026) | `client_id` | `box_id`, `technical_id`, costos | Registro comercial; no apunta a OT ni a tarea |
| Planta externa | `projects` (9, son tableros de Scheduling) y `talento_projects` (0) | — | `ProjectActivityService` | Producción externa vive en actividades/reportes (`talento_activity_report_participants`, 0 filas) |
| Mantenimiento | No hay objeto propio de «orden de mantenimiento»; se modela como tipo de OT o tarea interna | — | — | Sin evidencia de un flujo preventivo programado |

**Dónde se pierde la trazabilidad:** ticket→orden (casi sin uso de `ticket_id` y la pantalla de creación de OT no lo pide), orden→instalación original (`client_id` vacío en la OT histórica), instalación→alta comercial (`cut_installations` sin llave a OT/tarea).

## 3. Creación desde mostrador hoy vs. los 14 pasos del §6

Camino real: botón **«Asignar trabajo»** (`resources/js/components/module/talento/AsignarTrabajo.vue`) → `POST /talento/api/ordenes` (+ `POST /talento/api/ordenes/asignables`). El botón también está en la ficha de cliente y de prospecto (el componente acepta `client_id` o `crm_lead_id`; una OT es de cliente o de prospecto, nunca ambos, `TalentoWorkOrderController.php:139-142`). Alternativa: `/talento/ordenes` (admin) y Scheduling `/scheduling/task/crear` (que sí acepta `ticket_id`, `TaskCreateRequest.php:36`).

Datos que se capturan: tipo, persona (agrupada por rol), fecha/hora opcional, notas. Del cliente se resuelve solo `client_main_information_id` (`:168-174`); la dirección, teléfono y plan **no** se copian a la orden, se leen al consultar (`OrdenTrabajoUnifiedService::clientByInfoId`).

| # | Paso del contrato | Estado hoy | Evidencia |
|---|---|---|---|
| 1 | Mostrador abre el cliente | Existe | Ficha de cliente con botón |
| 2 | «Crear orden» | Existe como «Asignar trabajo» | `AsignarTrabajo.vue` |
| 3 | Elegir tipo | Existe (5 tipos fijos, no hay plantillas por solicitud: faltan «sin servicio», «internet lento», «revisión de fibra/potencia», «visita técnica»…) | `talento_work_order_types` (5 filas) |
| 4 | Autocompletar datos | Parcial: enlaza cliente, no precarga domicilio/equipo/caja ni síntoma | `store()` |
| 5 | Observaciones | Existe (`notes`) | `store()` |
| 6 | Fecha y franja | Parcial: fecha+hora, sin franja ni duración (`scheduled`/`estimated_time` existen en `tasks` pero no se usan aquí) | `AsignarTrabajo.vue`, `store()` |
| 7 | Elegir técnico/cuadrilla | Parcial: un solo colaborador; sin cuadrilla; sin disponibilidad. Restringe por permiso o por `supervisor_id` | `asignables()` |
| 8 | Validar conflictos | **Falta**: no hay chequeo de traslape ni de carga | `store()` no lo consulta |
| 9 | Confirmar según autorizaciones | Parcial: autoriza creador (permiso o supervisor directo); no hay aprobación previa a la asignación | `store():127-137` |
| 10 | Aparece en agenda del técnico | Existe: `task_user`, push FCM, notificación en app y WhatsApp (freno `WHATSAPP_SENDER_ENABLED`) | `store():186-236` |
| 11 | Técnico confirma recepción | **Falta**: no hay estado «aceptada por técnico». `accept` actual es la aceptación técnica del trabajo ya terminado (exige firma de técnico) | `FieldFlowService.php:83-92` |
| 12 | Ejecuta y documenta | Existe (iniciar, evidencias, nota, incidencia, completar) | `TalentoMobileApiController` rutas `/ots/*` |
| 13 | Solicita cierre/validación | Parcial: `completar` deja la orden en `completed`/`Done`; no hay estado explícito «pendiente de validación» | `OrdenTrabajoUnifiedService::completar` |
| 14 | Responsable valida | Existe: `POST /ordenes/{id}/validate` (`validarAdmin`) | `:1032-1109` |

Contar clics no fue posible sin sesión de navegador; lo verificable es que el flujo es un único diálogo de 4 campos.

## 4. Estados y correspondencia con los 13 del §7

Estados reales por entidad:

| Entidad | Valores reales (medidos) |
|---|---|
| `tasks.status` | `ToDo` 126 · `InProgress` 24 · `Done` 851 · `Archivado` 665 |
| `talento_work_orders.status` | `validated` 206 · `completed` 1 · `incidencia` 1 (el modelo y el servicio también conocen `pending`, `in_progress`, `cancelled`) |
| `tickets.estado` | `Cerrado` 777 · `Nuevo` 118 · `Reciclado` 4 |
| Vista unificada (traducción) | `pending`, `in_progress`, `completed`, `validated`, `cancelled` (+ `incidencia`) |

Mapa de traducción real: `ToDo→pending`, `InProgress→in_progress`, `Done→completed` (`OrdenTrabajoUnifiedService.php:24-28`); cancelar = `Archivado`; transiciones permitidas por el admin: `pending→{in_progress,cancelled}`, `in_progress→{completed,cancelled}`, el resto sin salida (`:978-985`). `validated` se escribe solo con `validarAdmin`.

| Estado §7 | Equivalente hoy | Brecha |
|---|---|---|
| Orden recibida | `pending`/`ToDo` | No se distingue de «pendiente de asignación» |
| Pendiente de asignación | Una tarea sin `task_user` (50 tareas sin usuario, la mayoría internas) | No es un estado; `store()` exige colaborador, así que nunca nace así |
| Programada | `scheduled_at`/`start_date` presente | No es estado |
| Asignada | `task_user` presente | No es estado |
| Aceptada | — | Falta (ver paso 11) |
| En camino | — | Falta (la app guarda ubicación, no estado) |
| En ejecución | `in_progress`/`InProgress` | Existe |
| Pausada | — | Falta |
| Terminada por técnico | `completed`/`Done` | Existe |
| Pendiente de validación | `completed` + tipo con `requires_validation` | Derivable, no explícito |
| Observada | `incidencia` (solo en OT histórica; hay `talento_work_order_incidents`, 1 fila, con motivo y GPS) | Parcial |
| Cerrada | `validated` | Existe |
| Cancelada | `cancelled`/`Archivado` | Existe; no pide motivo |

**Historial:** no hay. `BaseModel` usa `LogsActivity`, pero `activity_log` tiene 0 filas de `Task`, `TalentoWorkOrder` y `Ticket`, y la transición admin hace `DB::table('tasks')->update(...)` directo (`:1004-1014`), que no dispara observers. Quedan solo `talento_work_order_activities` (1 fila), `task_notifications` (3,661, son avisos) y los hilos de ticket. **Reprogramación con motivo: no existe**; `actualizarAdmin` solo cambia fecha/notas y bloquea órdenes validadas o canceladas (`:914-962`).

## 5. Ejecución y cierre

| Capacidad | Estado | Evidencia |
|---|---|---|
| Iniciar | Existe (`/ots/{id}/iniciar`) | `TalentoMobileApiController` |
| Pausa | Falta | sin estado ni ruta |
| Evidencias por tipo de orden | Existe: 8/4/4/4/3 tipos requeridos para tipos 1/2/3/4/5 (`talento_ot_type_evidence_requirements`); una sola vez por tipo salvo `permite_varias`; «Otro» exige justificación | `subirEvidencia :474-520` |
| Pruebas técnicas (dBm) | Existe: umbrales bloquean cierre por sobrepotencia (≥ −8) y baja señal (≤ −27) | `completar` + `talento_dbm_thresholds` (`:1240-1251`) |
| Firma del cliente | Existe, cuenta como evidencia del requisito `es_firma` | `:1167-1185` |
| Materiales | Parcial: columnas `inventory_item_id`/`inventory_movement_id` en la OT histórica; no hay captura de materiales en el flujo de tarea | modelo `TalentoWorkOrder` |
| Incidencias | Existe (`/ots/{id}/incidencia`, motivo + nota + GPS) | `reportarIncidencia :417-466` |
| Qué impide cerrar sin evidencia | `completar` responde 422 `EVIDENCIAS_FALTANTES` con la lista de faltantes | `:1190-1198` |
| Cierre por el admin | **Evade el candado**: `PUT /ordenes/{id}/status` permite `in_progress→completed` sin pasar por evidencias (*Actualizado tras auditoría UNI-T A5 (2026-10-26): la evasión es más amplia que ese PUT; hay otros caminos que dejan `Done` sin pasar por `completar`*) | `cambiarEstadoAdmin :978-1029` |
| Validación supervisada | Existe (`validarAdmin`); al validar suma puntos/bonos y refresca la ventana de garantía. *Actualizado tras auditoría UNI-T A5 (2026-10-26): en `tasks` la validación no suma nada aparte, porque el cierre del técnico (`completar`) ya deja `validated_at`; `validarAdmin` solo confirma y refresca garantía* | `:1032-1109` |

> **Actualizado tras auditoría UNI-T A5 (2026-10-26):** (a) las 208 OT históricas: 205 son de cuentas demo; (b) sí existe «Crear tarea desde solicitud» desde el ticket (`CrearTareaModal.vue` → `POST /scheduling/task/add`), solo se usa en 2 tareas.

Hallazgo: la regla «no cerrar sin evidencias» (§7) solo se cumple por la ruta del técnico; la ruta de estado del admin no la aplica (`completed` directo).

## 6. Varios técnicos o cuadrilla

- **Tarea:** el pivote `task_user` admite varios usuarios, pero `store()` asigna siempre uno (`sync([$colaborador->user_id])`). En `LiquidationService::countBillableUnits` las tareas de campo se cuentan por usuario asignado (`:283-294`): si hubiera dos, **cada uno recibiría la unidad completa** (no se reparte). No se probó con datos porque solo hay 5 tareas de campo.
- **OT histórica:** un `colaborador_id`; no soporta cuadrilla.
- **Planta externa:** existe el reparto por `quantity_share` en `talento_activity_report_participants` (0 filas), que es el único mecanismo de crédito compartido y vive fuera del flujo de órdenes.
- **Rutas:** hay `TalentoRoute`/`TalentoRouteStop` (paradas), útil para agenda de cuadrilla pero sin conexión con la creación de órdenes.

Veredicto: **no se soporta de forma real**; el reparto de crédito hay que diseñarlo (pregunta 2).

## 7. Garantías e instalaciones (§9)

| Requisito del contrato | Estado | Evidencia |
|---|---|---|
| Qué marca el inicio | `inicia_garantia=1` solo en «Instalación nueva» (id 1), pero ningún código lo lee; la ventana se abre en `validarAdmin` si `is_billable` y hay cliente, con `starts_at = now()` | `OrdenTrabajoUnifiedService.php:1061-1103`, `WarrantyWindowService::refreshWindow` |
| Fecha de aceptación técnica | `accept` escribe `accepted_at`; valor en dev: 0 filas | `FieldFlowService.php:112` |
| 6 meses naturales | `addMonths(6)` desde la validación, no desde la aceptación | `WarrantyWindowService.php:13,38` |
| Ligar incidencia a la instalación | **No**: `classifyWarrantyOrder(clientId)` solo mira si hay ventana activa del cliente; no se llama desde tickets ni desde el alta de orden (solo `GET`/`override` de la pantalla de garantía) | `TalentoWarrantyController.php:16-30` |
| Causas registradas | **Ninguna** de las 8 del contrato; `talento_warranty_overrides` (0 filas) guarda texto libre de «reason» | tabla `talento_warranty_overrides` |
| Técnicos participantes, roles | Solo el primer usuario de la tarea (`$task->users->first()`) | `:1070-1073` |
| No culpar ni descontar en automático | **Se incumple en diseño**: dentro de ventana la garantía sale `no pagable`, 0 puntos | `WarrantyWindowService.php:75-83`. Además `Garantía pagable` / `no pagable` no existen entre los 5 tipos (`talento_work_order_types`), así que hoy `type_id` sale `null` y el `override` no actualiza nada |
| Penalizaciones | Existe `TalentoPenalty` (módulo aparte); no se verificó su enlace con garantías | modelo y controller presentes |

**Medición en dev (conteos agregados):**

| Medida | Valor |
|---|---|
| Ventanas de responsabilidad (`talento_responsibility_windows`) | 0 (0 activas) |
| OT de instalación nueva validadas con cliente y fecha | 0 (207 de 208 OT sin `client_id`) |
| Altas (`cut_installations`) con cliente | 175 (2026-01-03 → 2026-10-10) |
| Tickets del mismo cliente en los 6 meses posteriores al alta | **3 tickets en 3 de 175 instalaciones (1.7 %)** *(Actualizado tras auditoría UNI-T A5 (2026-10-26): con la llave correcta es **5.7 %**; el 1.7 % de arriba usó la llave equivocada)* |

Reservas: se uso `cut_installations.created_at` como fecha de instalación (la única fuente con cliente) y `tickets.customer_lead` como cliente; el periodo medido es de menos de 10 meses, por lo que las instalaciones recientes no han cumplido los 6 meses. El 1.7 % es piso, no tasa de garantía. No se puede distinguir cuáles tickets son fallas (solo 8 son «Problema»).

## 8. Seguimiento (§7)

- **Mostrador/supervisor:** `/talento/ordenes` (`TalentoOrdenes.vue`) filtra por colaborador, estado, tipo, rango de fechas, búsqueda y prospecto (`TalentoWorkOrderController::data :73`). No hay filtro ni indicador de **atrasadas** ni de **sin asignar**. El supervisor ve solo lo de sus subordinados (`idsColaboradoresVisibles`).
- **Tickets:** `/tickets` (KPIs por estado, «asignados a mí»), bandejas abiertos/cerrados/reciclados. No conoce órdenes.
- **Scheduling:** `/scheduling/task` y `/scheduling/task/calendar` muestran tareas de cualquier tipo, sin distinguir órdenes de campo.
- **App de campo:** `/talento/campo` y `/talento/api/ots/*` para el técnico.
- Medición del problema: 150 tareas `ToDo`/`InProgress` con fecha anterior a hoy; 118 tickets `Nuevo` (70 con `assigned_to`).

## 9. Tabla funciona / incompleto / duplicado / roto

| Pieza | Veredicto | Detalle |
|---|---|---|
| Alta de orden con «Asignar trabajo» (tipo, persona, fecha, notas, avisos) | **Funciona** | Con avisos push/app/WhatsApp |
| Autorización de quién asigna (permiso o supervisor directo) | **Funciona** | `asignables`, `store` |
| Evidencias obligatorias + dBm + firma al completar (ruta técnico) | **Funciona** | 422 con faltantes |
| Validación y puntos al validar | **Funciona** | `validarAdmin` |
| Lectura unificada de ambas fuentes | **Funciona** | `OrdenTrabajoUnifiedService` |
| Plantillas por tipo de solicitud | **Incompleto** | Solo 5 tipos; faltan los del §6 |
| Autocompletar datos del cliente | **Incompleto** | Enlaza, no precarga |
| Cuadrilla / varios técnicos / reparto | **Incompleto** | Solo uno; sin reparto de crédito |
| Franja horaria, duración, conflictos | **Incompleto** | Solo hora; sin chequeo |
| Confirmación de recepción, en camino, pausa | **Incompleto** | No hay esos estados |
| Reprogramación con motivo; cancelación con motivo | **Incompleto** | No existe |
| Historial de cambios | **Roto** | `activity_log` vacío para estas entidades; updates con `DB::table` |
| Cierre por admin con evidencias | **Roto** | `PUT /ordenes/{id}/status` evade la compuerta |
| Garantía de 6 meses | **Roto** | 0 ventanas; tipos `Garantía pagable`/`no pagable` inexistentes; `inicia_garantia` sin consumidor |
| Vínculo ticket ↔ orden ↔ instalación | **Roto** | `ticket_id` en 2 tareas; OT sin `client_id` |
| Doble fuente (`tasks` + `talento_work_orders`) | **Duplicado** | La vieja es solo histórico |
| `tickets` vs `tasks` vs OT vs `cut_installations` como «orden» | **Duplicado** | Sin objeto común |
| Vista de seguimiento de atrasadas/sin asignar | **Incompleto** | No existe |
| Mantenimiento preventivo | **No verificado** | Sin flujo propio localizado |

## 10. Implicaciones para el plan

- El «Crear orden» del contrato se resuelve **ampliando `AsignarTrabajo`/`TalentoWorkOrderController::store`** (plantillas, precarga, franja, conflicto) y no con otro sistema; el modelo vigente es `tasks` con `talento_type_id`.
- Antes de cualquier migración (Fase B/C del contrato) hay que decidir qué hacer con las 208 OT históricas (sin cliente) y con la doble fuente.
- La compuerta de evidencias debe vivir en un solo lugar (hoy se esquiva por la ruta de admin).
- El historial exige registrar cada transición con actor, fecha y motivo, hoy ausente.
- La garantía debe reescribirse alrededor de «evento de garantía + causa + revisión» en lugar de «ventana = culpa del técnico».

## Preguntas para Irving

**1. ¿La fecha de inicio de los 6 meses es la aceptación técnica (contrato) o la validación (hoy)?**
- A. Aceptación técnica (`accepted_at`). Pro: igual al contrato; Con: hoy `accepted_at` está vacío en dev, hay que usar `accept` de verdad.
- B. Validación. Pro: ya funciona; Con: el supervisor puede tardar y se corre el plazo.
- **Recomendación: A**, con respaldo a la fecha de validación si no hay aceptación.

**2. ¿Cómo se reparte el crédito cuando hay varios técnicos en una orden?**
- A. Partes iguales por defecto, editables por el supervisor. Pro: simple; Con: requiere captura al cerrar.
- B. El responsable cobra todo y reparte fuera del sistema. Pro: cero cambios; Con: perdemos trazabilidad.
- C. Porcentaje fijo por rol. Pro: justo; Con: más reglas.
- **Recomendación: A** (reusar `quantity_share` de planta externa).

**3. ¿Qué hacemos con el cierre del admin que evade las evidencias?**
- A. Bloquearlo salvo permiso explícito + motivo. Pro: cumple §7; Con: fricción puntual.
- B. Dejarlo como está. Pro: ninguna; Con: el candado de calidad no es real.
- **Recomendación: A.**

**4. ¿Qué hacemos con la lógica actual de garantía «no pagable / 0 puntos»?**
- A. Apagarla y pasar a «evento de garantía + causa + revisión», sin efecto económico automático. Pro: cumple §9; Con: se pierde la clasificación automática (hoy no opera).
- B. Mantenerla y solo agregar causas. Pro: menos trabajo; Con: contradice el contrato.
- **Recomendación: A.**

**5. ¿Qué pasa con las 208 OT históricas sin cliente?**
- A. Dejarlas como histórico de solo lectura. Pro: cero riesgo; Con: no alimentan la calidad.
- B. Completar `client_id` por serie/caja cuando se pueda. Pro: algo de trazabilidad; Con: manual y con dudas.
- **Recomendación: A**, y B solo si Irving quiere medir calidad de esas instalaciones.

**6. ¿La unión ticket–orden es obligatoria al crear desde un ticket?**
- A. Sí, con `ticket_id` en la tarea. Pro: trazabilidad; Con: hay que tocar la pantalla de tickets.
- B. Opcional. Pro: menos fricción; Con: seguirá casi vacío.
- **Recomendación: A** para tickets de tipo «Problema» o con visita.
