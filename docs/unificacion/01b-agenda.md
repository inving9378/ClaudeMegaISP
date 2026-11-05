# UNI-01b · Auditoría de Tareas y Scheduling: motor de agenda y calendario

> Épica UNI (plan maestro de unificación), Documento 1, pieza b. Etapa **solo documental**: no se cambió código, migraciones, permisos, config ni `.env`. BD de dev consultada **solo lectura** (conteos y agrupaciones). Fecha de la auditoría: 2026-10-24. Sin nombres de personas, contactos ni datos de clientes: solo conteos y nombres técnicos.
>
> Docs previos que se verifican y complementan: `docs/modulos/scheduling.md`, `docs/modulos/tickets.md`, `docs/modulos/talento.md`.

## 1. Resumen ejecutivo

1. **Hoy no existe «una agenda»: existen cuatro fuentes con fecha y responsable que no se ven juntas** — `tasks` (Scheduling), `talento_work_orders` (Talento), `tickets` (sin fecha) y la fecha de instalación del prospecto en el CRM (`crm_lead_information.instalation_date`). Ninguna fuente detecta choques de horario ni tiene recordatorios de actividad.
2. **El motor de datos más fiable para programar es el de `tasks`/Scheduling**, y no por calidad sino porque es donde Talento ya escribe: *«Asignar trabajo»* crea una fila en `tasks`, no en `talento_work_orders` (`TalentoWorkOrderController.php:176`). `talento_work_orders` quedó con 208 filas, todas cerradas, la última de `2026-10-24 06:00`.
3. **Pero `tasks` tiene un modelo de fechas roto para una agenda unificada**: `start_time`/`end_time`/`start_date` son `varchar(255)` (no fecha), conviven formatos distintos (datetime completo vs. solo hora) y el calendario y *Mi día* leen campos distintos (ver §6).
4. **Hallazgo que cambia el plan:** una orden creada con *«Asignar trabajo»* guarda `start_date` + `start_time` con **solo la hora** (`16:00:00`) y sin `end_time`; el calendario de Scheduling espera datetime completo en `start_time`/`end_time`. Y al revés: arrastrar la orden en el calendario cambiaba `start_time`/`end_time` pero **no** `start_date`, que es lo que usa *Mi día*. Mismo registro, dos «verdades» de fecha.
   > **Actualizado tras #691 (2026-10-26):** ya no es cierto. «Asignar trabajo» guarda datetime completo con fin (+1 h) y `TaskController::updatetaskToCalenddar` actualiza también `start_date` (deja `activity_log`); la fuente oficial es `start_date` (Y-m-d) + `start_time`/`end_time` datetime, leída con `App\Services\TaskScheduleService` (tolera el formato viejo solo-hora). Quedan 2 tareas viejas con solo-hora sin corregir.
5. **Dato histórico de dev poco confiable:** 1,662 de 1,666 tareas tienen `updated_at` y `start_time` del mismo día (`2026-10-14`), aunque se crearon entre 2024-09 y 2026-06 — la fecha original de programación se perdió en una reescritura masiva. No se pudo determinar la causa desde el código.
   > **Actualizado tras auditoría UNI-T A5 (2026-10-26):** sí hay causa probable: la tarea programada #29 «Actualizar Fechas Pasadas» (`update_task_old_command:process`, diaria 07:25, tabla `command_configs`) reescribe las fechas de tareas vencidas. Causa probable, no probada por reproducción.
6. **Recomendación:** agenda unificada como **índice/vista de lectura sobre las fuentes** (no tabla central con copias), con **una sola corrección previa** al modelo de fechas de `tasks` (Fase 0 del roadmap). Detalle y alternativas en §9 y «Preguntas para Irving».

## 2. Tareas (`tasks`)

### 2.1 Modelo y datos

| Tema | Hallazgo | Evidencia |
|---|---|---|
| Tabla | `tasks`, modelo `App\Models\Task`, `$guarded=[]`, SoftDeletes; 1,666 filas | `app/Models/Task.php`; `SELECT COUNT(*)` |
| Participantes | Pivote `task_user (task_id,user_id)`: 4,905 filas; **1,374 tareas (82 %) con 2+ asignados**; 50 sin ningún asignado. Además existe `tasks.assigned_to varchar` (24 filas con valor) que quedó como campo legado del formulario | `SELECT … GROUP BY task_id HAVING COUNT(*)>1` |
| Tipos | `tipo` enum `interna`/`campo`. 1,661 internas, 5 de campo | `GROUP BY tipo` |
| Estados (`status`, varchar libre) | `Done` 851, `Archivado` 665, `ToDo` 126, `InProgress` 24. El doc previo menciona también `PostponedByClient` — **no hay filas con ese valor** en dev | `GROUP BY status,tipo` |
| Fechas | `start_date`, `start_time`, `end_time`, `duration`, `estimated_time`, `dedicated_time`, `finish_at`, `archived_at` son **todos `varchar(255)`** | `SHOW COLUMNS FROM tasks` |
| Formatos de `start_time` | 1,663 con 19 caracteres (`YYYY-MM-DD HH:MM:SS`); 2 con 8 (solo hora); 1 nulo. `end_time` nulo en 3 | `GROUP BY LENGTH(start_time)` |
| Proyecto | Todas tienen `project_id`; 9 proyectos (INSTALACIONES 226, ORDEN DE SERVICIO 407, CAMBIO DE DOMICILIO 27, REPARACION 129, TENDIDO 24, DEPUERACION 6, Software de Gestion 623, Planta Externa Garantias 163, Habilitación de caja 60) | `LEFT JOIN projects` |
| Vínculos | `client_main_information_id` 931 tareas; `ticket_id` **2**; `crm_lead_id` **1**; `talento_type_id` 5 | `COUNT(col IS NOT NULL)` |
| Sin uso | `task_closures` 0 filas; `talento_projects` 0 | conteos |
| Historial de cambios | Una sola fila en `activity_log` en toda la BD; no hay registro de `Task`, orden ni ticket. Las notas (`observation_tasks`, 2,249) sirven de bitácora manual | consulta a `activity_log` |
| Creación reciente | Solo 1 tarea creada desde 2026-09 (la orden de *Asignar trabajo*) | `created_at` |

### 2.2 Quién crea, asigna y ejecuta

- **Crea/asigna:** `TaskController@store` (`app/Modules/Addons/Scheduling/Controllers/TaskController.php:120`) sincroniza `task_user` y notifica. El campo «Asignado a» se volvió selector rol → persona activa el 2026-10-22 (migración `2026_10_22_160000_asignado_a_por_rol.php`, `SelectRolUsuario.vue`); actúa sobre el formulario, no cambia la tabla.
- **Ejecuta:** los asignados en `task_user`. No hay «creó / asignó / ejecuta / aprueba» como roles separados: `created_by` y `updated_by` se estampan por `BaseModel`, pero el modelo `Task` no registra *quién asignó* distinto de quién creó, ni *quién aprueba* (solo `validated_at`).
- **Pantallas:** Tareas (`/scheduling/task`), archivadas (`/scheduling/task/show-archived`), Proyectos (`/scheduling/project`), Calendario (`/scheduling/task/calendar`). Rutas: `app/Modules/Addons/Scheduling/routes.php:16-55`.
- **Permisos:** `scheduling_*` y el set general `task_*` (listados en `docs/modulos/scheduling.md`). Las tablas filtran por usuario cuando falta `task_view_full_task` (`TaskDatatableHelper.php:28-46`).

### 2.3 Hallazgo de seguridad en lectura (CORREGIDO por #692 y #698)

> **Actualizado tras #692 (2026-10-26):** `TaskService::getTasksToEvents` ya aplica el mismo filtro que el listado (admin, super-admin o `task_view_full_task` ven todo; el resto solo lo asignado en `task_user`). Tras #697/#698 también el arrastre en el calendario (403 si no es asignado/supervisor/admin) y los endpoints de tarea por id. Lo de abajo es el hallazgo original, conservado como historia.

El **listado** restringe las tareas al usuario asignado salvo permiso `task_view_full_task` (`TaskDatatableHelper.php:28-46`), pero el endpoint que alimenta el **calendario** (`POST /fullcalendar/get-task-events`, `routes/web.php:199` → `FullcalendarController::getTaskEvents` → `TaskService::getTasksToEvents`, `app/Services/TaskService.php:12-21`) aplica los filtros pero **no se encontró** la misma restricción por usuario. No se ejecutó la prueba con un usuario sin permiso, por lo que queda como **hipótesis por lectura de código**: quien tenga acceso al calendario podría ver tareas ajenas. Importa porque la agenda unificada exigirá visibilidad por persona/equipo.

## 3. Scheduling (el módulo de calendario)

- **Módulo:** addon `addon-scheduling` (`app/Modules/Addons/Scheduling/`: 2 controladores, `routes.php`, `module.json`). Los modelos viven en `app/Models/`.
- **Calendario:** `CalendarIndex.vue` (filtros) + `resources/js/shared/QCalendar.vue` (FullCalendar: `dayGrid`, `timeGrid`, `interaction`, `listWeek`; vistas mes/semana/día/lista; `editable` y `droppable` activos — `QCalendar.vue:49-67`). Arrastrar y redimensionar llaman a `POST /scheduling/task/update-task-to-calendar` (`TaskController.php:386`).
- **Filtros:** módulo de catálogo `FiltersTaskCalendar` (valores persistentes por usuario vía `DefaultValueRepository`) y permisos `calendar_filter_*`.
- **Carga de eventos:** trae **todas** las tareas no archivadas **sin ventana de fechas** (`TaskService.php:16-20`). Hoy son 205; escala mal si la agenda absorbe órdenes, tickets y citas.
- **Recurrencia:** no existe (`grep` de `recurr|rrule|repeat` en Scheduling, `Task` y los Vue de scheduling: 0 coincidencias).
- **Recordatorios de actividad:** no existen. La única notificación es «Nueva tarea asignada» al crear (`Task::sendNotifications`, `app/Models/Task.php:357`, tablas `task_notifications` 3,661 y `notifications`). El único comando programado de recordatorios (`app:reminder-payment-command`, `app/Console/Kernel.php:34`) es de cobranza al cliente, no de actividades del personal. La tabla `reminders` (3,664 filas: 3,398 enviados, 266 fallidos) es de correos/avisos al cliente.
- **Choques de horario:** no existen (`grep` de `conflict|solap|overlap|choque` en Scheduling, `Task.php` y en los servicios y controladores de Talento: 0 coincidencias). Medición: **1,446 pares** de tareas pendientes con el mismo usuario y horario solapado — pero el dato está contaminado por el problema de fechas del §6, por lo que **no es una cifra operativa**, solo prueba de que nada lo impide.
- **Zona horaria:** `config/app.php:87` = `America/Mexico_City`. Las fechas se guardan como texto sin zona; el calendario manda ISO con offset y el servidor las convierte a texto al guardar (`TaskController.php:388-405`). Funciona mientras todo sea una sola zona; no hay manejo explícito.
- **Defectos de código vistos (no corregidos, etapa documental):**
  - `TaskController::getStartAndEndTime` (`:151-180`) calcula el inicio con la **hora actual del servidor** (`Carbon::now()->hour`), no con la hora elegida.
  - `initialView: events[0].initialView` (`QCalendar.vue:56`) falla si el calendario no tiene ningún evento.
  - `limpiaFechas()` (`FullcalendarController.php:143`) existe para quitar paréntesis tipo `(hora estándar…)`: señal de que `start_time` heredó cadenas de fecha de JavaScript en algún momento.
- **Pruebas existentes:** no había pruebas automatizadas de `Task`, de Scheduling ni del calendario al momento de la auditoría.
  > **Actualizado tras #691/#697/#698 (2026-10-26):** ya hay pruebas: `tests/Unit/TaskScheduleServiceTest.php` (4) y `tests/Feature/Scheduling/ReprogramacionOrdenesTest.php` (acceso por perfil y reprogramación, punta a punta; 3 + 4 según la auditoría A5).

## 4. Otras fuentes con fecha y responsable

| Fuente | Qué guarda | Fecha | Responsable | Conteo dev | Evidencia |
|---|---|---|---|---|---|
| **Órdenes de Talento (nuevas)** | Fila en `tasks` con `talento_type_id` | `start_date` + `start_time` (solo hora) | `task_user` | 1 (la prueba del 24-oct) + 5 históricas de campo | `TalentoWorkOrderController.php:176-197` |
| **Órdenes de Talento (legado)** | `talento_work_orders` | `scheduled_at` (timestamp real) | `colaborador_id` (1:1) | 208 (206 validadas, 1 completada, 1 con incidencia); creadas 2026-06-05 → 2026-10-24; 108 con fecha programada | `SHOW COLUMNS`, `GROUP BY status` |
| **Tickets** | Mesa de ayuda | **Sin fecha de atención** (solo `created_at`) | `assigned_to` int (usuario), `customer_lead` | 899 (Cerrado 777, Nuevo 118, Reciclado 4); 893 con responsable | `SHOW COLUMNS FROM tickets` |
| **Ticket → tarea** | Botón «Crear tarea desde solicitud» (`CrearTareaModal.vue` → `POST /scheduling/task/add`) | la de la tarea | la de la tarea | solo **2** tareas con `ticket_id`; `tickets.task` (varchar) vacío en las 899 | consultas |
| **Prospecto (CRM)** | `crm_lead_information.instalation_date` (varchar) y `crm_techical_user_id` (varchar) | fecha de instalación | técnico | 2,262 prospectos: **67 con fecha**, **15 con técnico**; **0** ligados a una tarea | consulta; usado en `CrmDatatableHelper.php:281`, `PortalTecnicoController.php:909`, `TalentoVentasController.php:140` |
| **Proyectos planta externa (Talento)** | `talento_projects`, `…_activities`, `talento_routes`, `…_route_stops` | `start_date`/`end_date`, `date` | `lead_colaborador_id`, `colaborador_id` | **0 filas** en las cuatro | conteos |
| **Asistencia (Talento)** | `talento_attendances` | `check_in_at`/`expected_end_at` | colaborador | 4 | conteo |
| **Reuniones (WarRoom)** | `warroom_meetings` + `warroom_action_items` (con `deadline`, `assignee_user_id`, `linked_task_id`) | `scheduled_at` | moderador/asignado | 10 reuniones; **0** acciones | conteos |
| **Flotas** | `fleet_maintenances` | `service_date`, `next_service_date` | — | 2 | conteo |
| **MegaFamilia** | `parental_tasks` | `due_date` | hijo | 4 (dominio distinto: familia del cliente, no personal) | conteo |
| Cortes de instalación | `cut_installations` | — | — | 175 (es un dato de facturación, no de agenda) | conteo |

**«Asignar trabajo»** (22-oct, `AsignarTrabajo.vue` en `InformationClientCrud.vue:52` y `InformationCrmCrud.vue:18`): no es un módulo propio, es el formulario del `POST /talento/api/ordenes` (`TalentoWorkOrderController::store`, `:113`). Crea la fila en `tasks`, la asigna en `task_user`, manda notificación en la aplicación y WhatsApp al técnico (freno `WHATSAPP_SENDER_ENABLED`) y push FCM. La lista de asignables sale de `asignables()` (`:338`), por puesto y limitada al equipo del supervisor.

## 5. Matriz por fuente (pregunta 4)

| Fuente | ¿Varios participantes? | ¿Detecta choques? | ¿Recordatorios? | ¿Historial de reprogramaciones? | ¿Sale en algún calendario? |
|---|---|---|---|---|---|
| `tasks` (Scheduling) | **Sí** (`task_user`) | No | Solo aviso al asignar | No (se sobrescribe `start_time`; sin `activity_log`) | **Sí** (calendario de Scheduling) |
| Orden de Talento (en `tasks`) | Sí en BD, la UI asigna **una** persona | No | Aviso al asignar + WhatsApp + push | No | Parcial: en el calendario solo si `start_time` es datetime completo; sí en *Mi día* (por `start_date`) |
| `talento_work_orders` (legado) | No (1 colaborador) | No | No | No | *Mi día* sí; calendario de Scheduling no |
| Tickets | No (1 responsable) | No aplica (sin fecha) | No | No | **No** |
| Instalación del prospecto (CRM) | No (1 técnico, texto) | No | No | No | **No** |
| Reuniones WarRoom | Sí (`warroom_meeting_attendees`) | No | No | No | No (no en Scheduling) |
| Proyectos planta externa | — | — | — | — | Sin datos; no verificable en dev |

## 6. Duplicidades e inconsistencias medidas en dev (pregunta 5)

| Caso | Cantidad | Lectura |
|---|---|---|
| Ticket que genera tarea (`tasks.ticket_id`) | 2 de 899 tickets | El puente existe pero casi no se usa |
| Tarea ↔ orden legado (misma actividad en dos tablas): coincidencia por tipo y fecha | **0** | No hay duplicidad detectada hoy; `talento_work_orders` **no** tiene `task_id` ni `ticket_id`, por lo que no habría forma de enlazar si la hubiera |
| Prospecto con fecha de instalación y tarea | 0 de 67 | La instalación agendada en el CRM vive solo ahí: ninguna agenda la ve |
| Orden de Talento y calendario | 1 orden nueva con `start_time` solo hora y sin `end_time` | El calendario la interpretaría con la fecha de hoy y fin «ahora» (`Carbon::parse` de un valor sin fecha/nulo) |
| Reprogramar en calendario vs. *Mi día* | por diseño | `updatetaskToCalenddar` modificaba `start_time`/`end_time`, no `start_date` (*corregido por #691: ahora también escribe `start_date`*); `summaryForHoy` filtra por `start_date` (`OrdenTrabajoUnifiedService.php:220-246`). Tras arrastrar, el técnico sigue viendo la fecha vieja |
| Fecha de programación original | 1,662 de 1,666 tareas con `updated_at` = `start_time` = 2026-10-14 | Reescritura masiva; creadas entre 2024-09 y 2026-06. Causa probable: tarea programada #29 (07:25 diaria), ver §1 punto 5 |
| Tareas con `start_date` poblado | 3 de 1,666 | `start_date` solo lo usa el flujo Talento nuevo |

Conclusión: **el riesgo de duplicidad real no es «misma actividad en dos tablas» (hoy 0), sino «dos campos de fecha para la misma actividad en la misma tabla» y «fuentes que nadie enlaza» (instalación en CRM, tickets).**

## 7. Relación con el pago de técnicos y con las órdenes internas

- `LiquidationService::countBillableUnits` suma órdenes (`talento_work_orders`), tareas (`tasks`) y puntos de proyecto externo; la rama de tareas filtra `tipo='campo'` y `talento_type_id` no nulo (`LiquidationService.php:283-294`). **Verificado.** Consecuencia para la agenda: cualquier cambio a `tasks` (tipos, estados, `validated_at`, `task_user`) toca el cálculo de pago; la agenda unificada debe ser **de solo lectura sobre esos campos**.
- Desde `2b4c24c1` (24-oct) las órdenes de tipos internos (hoy solo «Reubicación», `category='interna'`) salen en *Mi día* y en Órdenes: se quitó el filtro `tipo='campo'` en `OrdenTrabajoUnifiedService` (lectura y edición) pero **no** en el pago, que sigue contando solo `campo`. **Verificado** en el diff del commit y en `LiquidationService.php:287`.
- `Task::boot` (`app/Models/Task.php:42-48`) exige que `tipo` coincida con la `category` del tipo de Talento: una restricción útil que la agenda unificada debe respetar.

## 8. Comparación de motores (pregunta 6)

| Criterio | Motor A: Tareas/Scheduling (`tasks`) | Motor B: Órdenes Talento legado (`talento_work_orders`) |
|---|---|---|
| Modelo de datos | Varios asignados, proyecto, cliente, ticket, adjuntos, notas, checklist; fechas **texto** | Columnas tipadas (`scheduled_at`, `started_at`, `completed_at`, `validated_at`, coordenadas); 1 solo colaborador; sin proyecto/ticket/tarea |
| Zonas horarias | Texto sin zona; funciona en una zona | `timestamp` de Laravel (zona de la app) |
| Rendimiento | Calendario sin ventana de fechas (carga todo lo no archivado) | Consultas por fecha/colaborador sencillas |
| Pruebas | Ninguna | Ninguna (solo `PayWeekTest`) |
| Quién escribe hoy | **Todo lo nuevo**: *Asignar trabajo*, calendario, tickets→tarea, WarRoom | Nadie nuevo (última fila 24-oct 06:00, 208 cerradas) |
| Choques / recordatorios / historial | No / no / no | No / no / no |

**Veredicto:** ninguno es «fiable» como agenda hoy. **Motor A es la base por adopción y por estructura** (varios participantes, es donde se escribe, ya alimenta el pago); su debilidad es el modelo de fechas, corregible en una migración acotada (Fase 0). Motor B tiene mejor tipado pero es legado cerrado.

**Componentes Vue a reutilizar:**

| Componente | Reutilizar | Nota |
|---|---|---|
| `shared/QCalendar.vue` (FullCalendar: mes/semana/día/lista, arrastrar, redimensionar) | **Sí**, como calendario base | Corregir `events[0]`, ventana de fechas y reprogramación con motivo |
| `scheduling/calendar/CalendarIndex.vue` + `FiltersTaskCalendar` | Sí, filtros ya persistentes por usuario | Agregar «mi agenda / mi equipo» |
| `scheduling/CrearTareaModal.vue` (ticket → tarea) | Sí como patrón de origen trazable | Hoy solo 2 usos |
| `talento/AsignarTrabajo.vue` + `SelectRolUsuario.vue` | Sí: es el flujo más cercano al «crear orden rápida» | Solo 1 asignado; sin franja ni validación de choque |
| `TaskCrud.vue`/`TaskEdit.vue`/`TaskListar.vue` | Se conservan; no son agenda | |
| `client/billing/components/Minifullcalend.vue` | No: calendario de facturación del cliente | |

## 9. Recomendación para la agenda unificada (pregunta 7)

### Opción 1 — Índice/vista de solo lectura sobre las fuentes (**recomendada**)

Un servicio (`AgendaService`) que normaliza cada fuente a `{fuente, id_origen, título, inicio, fin, responsables[], estado_origen, estado_unificado, enlace}` y lo expone por API paginada y por ventana de fechas. Cada módulo sigue siendo dueño de su registro. Solo `tasks` se reprograma desde el calendario, y lo hace a través de su servicio.

| Pros | Contras |
|---|---|
| Cumple «no duplicar registros de origen» y «reflejar cambios del original» (se lee en vivo) | Cada fuente necesita un adaptador y su consulta eficiente (ventana de fechas + índices) |
| Cero migración de datos; no toca el pago de técnicos | Los choques de horario se calculan al vuelo entre fuentes distintas (más costo) |
| Reversible: se apaga la vista y las fuentes siguen igual | Recordatorios y reprogramaciones con historial necesitan una tabla propia pequeña de todos modos |
| Sirve de API única para la futura app móvil | Fuentes con fecha mal tipada (varchar) requieren normalización en el adaptador |

### Opción 2 — Tabla central de agenda (`agenda_eventos`)

| Pros | Contras |
|---|---|
| Consultas, choques y recordatorios simples y rápidos | Es una **copia**: riesgo de desincronización (lo que el plan prohíbe) y doble escritura |
| Historial de reprogramaciones natural | Exige sincronizar `tasks`, ticket, CRM y Talento en ambos sentidos |
| | Migración y conciliación de ~1,900 registros históricos; mayor riesgo con el pago |

### Opción 3 — Híbrida (variante de la 1)

Índice de lectura **más** una tabla pequeña solo para lo que no pertenece a ninguna fuente: recordatorios configurables, bitácora de reprogramaciones (motivo) y participantes adicionales. Es lo que la Opción 1 necesitará de todos modos y evita crear una tabla central de eventos.

**Recomendación: Opción 3 (índice + tablas auxiliares), precedida de una Fase 0 sobre `tasks`:**

0. Normalizar el modelo de fechas de `tasks` (una sola fuente de verdad: `start_time`/`end_time` datetime; `start_date` derivado), corrigiendo el flujo de *Asignar trabajo* y *Mi día* para que lean lo mismo que el calendario. Sin esto el índice heredaría el desorden.
1. Adaptadores de lectura: `tasks`, `talento_work_orders` (legado), instalación de prospecto, reuniones. Tickets entran por la tarea que generan, no como fuente propia (no tienen fecha).
2. Tablas auxiliares: recordatorios, reprogramaciones con motivo, participantes.
3. Detección de choques en el servicio al asignar.

Esta etapa es solo recomendación: **no se construyó nada**.

## 10. Huecos sin verificar

- Causa de la reescritura masiva del 2026-10-14 (no hay bitácora en `activity_log`; no se sabe si fue una importación, un arreglo de zona horaria o una corrección de formato).
- ~~Si el calendario filtra por usuario en el servidor (§2.3)~~: resuelto por #692/#698 (filtro y pruebas por perfil).
- Comportamiento real del calendario con la orden de *Asignar trabajo* (hora sin fecha, sin fin): se infiere del código, no se probó en navegador.
- Proyectos de planta externa y rutas: 0 filas, no hay datos con qué medir; solo se inventarió el modelo.
- Producción: no se consultó (solo dev, solo lectura).

## 11. Preguntas para Irving

**P1 — ¿Qué se hace con `talento_work_orders` (legado, 208 cerradas)?**

| Opción | Pros | Contras |
|---|---|---|
| A. Dejarla como histórico de solo lectura y que la agenda la lea | Cero riesgo, conserva todo | Sigue habiendo dos orígenes de órdenes en lecturas |
| B. Migrar sus 208 filas a `tasks` | Un solo origen | Toca registros que ya se pagaron; requiere conciliación completa |

**Recomendación: A.** Es coherente con «no perder información» y con no tocar lo ya liquidado.

**P2 — ¿Autorizas una Fase 0 sobre el modelo de fechas de `tasks`?** (convertir `start_time`/`end_time` a datetime real, normalizar los formatos mixtos y reconciliar `start_date`).

| Opción | Pros | Contras |
|---|---|---|
| A. Sí, migración acotada con respaldo y reversa | Base confiable para agenda, choques y recordatorios | Cambio de esquema sobre una tabla compartida con pago, Tickets, WarRoom y MegaFamilia |
| B. No; el índice normaliza al leer | No se toca el esquema | Mantiene el defecto; errores de fecha seguirán saliendo en cada pantalla |

**Recomendación: A**, con migración incremental y verificación previa en copia (el cambio toca la tabla que cuenta para el pago).

**P3 — ¿Qué pasa con las 67 fechas de instalación en el CRM (`instalation_date`)?**

| Opción | Pros | Contras |
|---|---|---|
| A. La agenda las lee tal cual (solo lectura) | Sin migración | Son texto, solo 15 tienen técnico |
| B. Al convertir el prospecto, crear la orden (tarea) automáticamente | Un solo flujo, sin captura doble | Cambia el flujo comercial actual |

**Recomendación: A** ahora, B como mejora posterior a validar con el equipo comercial.

**P4 — ¿Cuándo se arregla la posible exposición en el calendario (§2.3)?** Es lectura de datos ajenos, no dinero.

| Opción | Pros | Contras |
|---|---|---|
| A. Verificarlo y corregirlo antes de la agenda unificada | Cierra el hueco ya | Es un cambio de código fuera de esta etapa documental |
| B. Dejarlo hasta la Fase 0 | Se resuelve junto con el rediseño | Mientras tanto sigue abierto si se confirma |

**Recomendación: A**, como ítem aparte tras confirmar con una prueba.

**P5 — ¿Reglas de choque: bloquean o avisan?** Para mostrador y supervisor.

| Opción | Pros | Contras |
|---|---|---|
| A. Avisar y permitir continuar | No frena la operación | Se pueden empalmar técnicos |
| B. Bloquear salvo permiso de supervisión | Evita dobles asignaciones | Puede frenar urgencias |

**Recomendación: A al inicio, con motivo obligatorio al forzar** y revisar con datos reales a las pocas semanas.
