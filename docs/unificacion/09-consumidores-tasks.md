# 09 · Consumidores de la tabla `tasks` y lista de regresión (UNI-T A4)

Inventario de quién lee o escribe `tasks`, qué supuestos hace y qué se rompería al cambiar estado, fecha, borrado o asignación. Solo conteos y nombres técnicos.
Leyenda: **[código]** leído en el repo · **[DEV]** observado en la BD de DEV (no producción) · **[por verificar]**.
Verificado el 2026-10-26 con grep de `Task::`, `DB::table('tasks')`, `->tasks()` y `tasks` en `app/`, `routes/` y `resources/js`.

## 1. Hechos del esquema y del modelo

- **Estados reales** [DEV]: `ToDo`, `InProgress`, `Done`, `Archivado` (ToDo 126, InProgress 24, Done 851, Archivado 665; no hay `To Do` ni `Cancelado`).
- **Asignación**: pivote `task_user` (y `assigned_to` legado). [código]
- **Fechas**: `start_date`, `start_time`, `finish_at` (se fija sola al pasar a `Done`, se anula al salir de `Done`), `finish_at_first_time`, `validated_at`. Lo hace `Task::boot()` en el evento `updating`. [código]
- **Borrado**: `Task` **no usa SoftDeletes** aunque `tasks.deleted_at` existe [DEV]; `TaskController::destroy` hace `->delete()` en duro. [código]
- **Cascada**: 13 tablas hijas con FK `ON DELETE CASCADE` hacia `tasks` [DEV]: 11 de Talento (`talento_health_bonus_log`, `_installation_surveys`, `_responsibility_windows`, `_route_stops`, `_warranty_overrides`, `_work_order_activations`, `_activities`, `_ia_validations`, `_incidents`, `_media`, `_signatures`) más `task_notifications` y `task_user`.
- **Columnas de vínculo**: `ticket_id`, `crm_lead_id`, `client_main_information_id`, `talento_type_id`. **No existe** `source` ni `linked_task_id` en `tasks` [DEV]. `saving()` exige `tipo == category` del `talento_type_id`.

## 2. Consumidores

### 2.1 WarRoom
| Ruta | Uso | Supuesto / riesgo |
|---|---|---|
| `WarRoom/Observers/ActionItemObserver.php` `linkToTask()` | **Escribe**: `Task::create` con `source => 'warroom'` y `status => 'To Do'` | La columna `source` no existe y `To Do` no es un estado válido → el `create` falla siempre y el `catch` lo deja en un `Log::warning`. Resultado [DEV]: 0 `warroom_action_items` con `linked_task_id`. Si se agrega `source` o se corrige el estado, empezará a crear tareas reales (efecto visible en tablero, KPIs y notificaciones). |
| `WarRoom/Models/ActionItem.php` `linkedTask()` | Lee por `linked_task_id` | Hoy siempre vacío. |
| `WarRoom/Controllers/KpiController.php` (≈15 consultas) | Lee con `DB::table('tasks')` | Trata **toda** tarea como "ticket": `ToDo`/`InProgress` = pendiente, `Done` = cerrado; cerrados del mes por `updated_at`; `whereNull('deleted_at')`; promedio de resolución con fechas. Las OT de Talento y tareas internas inflan estos KPI. |
| `WarRoom/Services/InsightsService.php` L48-51 | Lee conteos por `created_at` y `updated_at` | Igual: cualquier cambio de estado o fecha mueve los números. Un borrado en duro baja el histórico; un SoftDelete con `deleted_at` ya lo respeta. |

### 2.2 MegaFamilia (app del técnico)
| Ruta | Uso | Supuesto / riesgo |
|---|---|---|
| `MegaFamilia/Controllers/ApiController.php` `tecnicoOrdenes()` | Lee tareas del usuario por `task_user` | Excluye `Archivado`, **sin filtro de fecha**, `orderByDesc('id')`, **límite 50**: un técnico con más de 50 abiertas/cerradas no ve las más viejas. |
| `ApiController::updateOrden` (PUT técnico) | **Escribe** `status` (`mapOrdenStatusToTask`) + `ObservationTask` + `task_closures` + archivos | Puede poner `Done` **sin compuerta** (sin evidencias, checklist ni validación Talento); `Task::updating` fija `finish_at`. Si D/E/F añaden compuerta de cierre, esta ruta la salta. |
| `taskToOrden()` / `mapTaskStatusToOrden()` | Traduce `ToDo/InProgress/Done` ↔ `pendiente/en_proceso/completada` | Un estado nuevo cae a `pendiente`/`ToDo`. Usa `start_date`+`start_time` como fecha programada. |

### 2.3 Tickets
| Ruta | Uso | Supuesto / riesgo |
|---|---|---|
| `Scheduling/Controllers/TaskController.php` (campos `ticket_id`, `ticket_topic`) y `Models/Ticket.php` | Relación ticket ↔ tarea por `tasks.ticket_id` | Borrar la tarea (duro) deja el ticket sin su tarea; la UI no lo avisa. |
| `resources/js/components/module/scheduling/CrearTareaModal.vue` y `tickets/VerTicket.vue` | Crean/listan tareas desde un ticket vía `TaskCreateRequest` | Dependen de los nombres de estado y de `ticket_id`. |
| Tickets del portal / WhatsApp / MegaFamilia soporte | **No** tocan `tasks` (solo `ticket_id` de hilos) [código] | Sin impacto directo. |

### 2.4 CRM
| Ruta | Uso | Supuesto / riesgo |
|---|---|---|
| `Talento/Controllers/TalentoWorkOrderController.php` (validación `crm_lead_id`, L120-185) | **Escribe** `crm_lead_id` en la OT-tarea | Si hay `client_id` y `crm_lead_id` a la vez se rechaza (L142); una OT es de cliente **o** de prospecto. |
| `Talento/Services/OrdenTrabajoUnifiedService.php` (L783, 798-844) | Lee `crm_lead_id` y filtra por prospecto | `TalentoWorkOrder` no tiene `crm_lead_id`: el filtro de prospecto solo aplica a tareas. Si se unifica, hay que migrar la columna. |
| Migración `2026_09_17_130000_add_crm_lead_id_to_tasks` | Crea la columna | Sin FK declarada [código]; borrar el lead no limpia la tarea. |

### 2.5 Talento y otros (impactan estado/fechas/asignación)
| Ruta | Uso | Supuesto / riesgo |
|---|---|---|
| `Talento/Services/LiquidationService.php` `countBillableUnits()` (L284-295) | Lee tareas pagables | Filtro exacto: `talento_type_id` no nulo, `is_billable`, `points>0`, `status='Done'`, `validated_at` dentro de la semana, asignado por `task_user`. **Mueve dinero**: cambiar `status`, `validated_at`, `points` o el pivote cambia pagos. Es el punto único (regla 10). |
| `Talento/Services/OrdenTrabajoUnifiedService.php` (`TASK_STATUS_MAP`, L23, L134, L273, L376) | Lee y escribe estado | Mapea `Task.status` → estado de OT; `iniciar` exige `ToDo`. Un estado nuevo debe entrar al mapa. |
| `Talento/Services/FieldFlowService.php`, `Support/FieldFlowEntity.php`, `TalentoFieldFlowController.php` | Flujo de campo sobre tareas | Dependen de estados y de las tablas hijas con cascada. |
| `Talento/Services/HealthBonusService.php` L203 | Referencia polimórfica `Task::class` en el ledger | Borrar la tarea deja `reference_id` colgado en `talento_ledger_entries`. |
| `Http/Repository/TaskRepository.php`, `Services/TaskService.php`, `Traits/Models/Task/ScopeTask.php`, `HelpersModule/.../TaskDatatableHelper.php` | CRUD y filtros (`status`, `priority`, `assigned*` por `task_user`) del módulo Scheduling | Filtros por `whereIn` de los valores literales de estado. |
| `Http/Controllers/Utils/FullcalendarController.php` | Colores por `Task::TASK_COLOR` | Estado nuevo sin color. |
| `SmartImportExport/Services/SmartImportService.php` L415, L760 | Importa/exporta `tasks` | Conserva ids; no valida estados. |
| `Console/Commands/Active/ConciliarFechasTasksCommand.php`, `Scripts/UpdateTaskStartAndFinishDate.php` | Reescriben fechas | Cambian `start_date`/`finish_at` masivamente. |
| `Http/Controllers/TestScriptController.php`, `Clientes/.../ComponentSearchServiceController.php` | Lectura puntual | Sin supuestos relevantes. |

### 2.6 Frontend
`TalentoMiDia.vue`, `TalentoDashboard.vue`, `TalentoOrdenes.vue`, `AsignarTrabajo.vue`, `MegaFamiliaTareas.vue` (son `parental_tasks`, **no** `tasks`), `warroom/meeting/MeetingSummary.vue`, `warroom/views/ViewDesempeno.vue`, `CrearTareaModal.vue`, `VerTicket.vue`: leen estados por texto literal.

## 3. Qué se rompe según el cambio

| Si cambia… | Se rompe / hay que revisar |
|---|---|
| **Estado** (nuevo valor, renombre) | Mapas de MegaFamilia y `OrdenTrabajoUnifiedService`, colores del calendario, filtros de Scheduling, KPIs de WarRoom (`ToDo`/`InProgress`/`Done`), compuerta de pago (`Done`+`validated_at`). |
| **Fecha** (`start_date`, `finish_at`, `validated_at`) | Ventana de pago semanal, "cerrados del mes" de WarRoom (`updated_at`), `scheduled_at` de la app del técnico, comandos de conciliación de fechas. |
| **Borrado** | Hoy es duro y cae en cascada a 13 tablas (evidencias, firmas, incidencias, actividades); ledger y tickets quedan con referencias colgadas. Pasar a SoftDeletes obliga a que todas las consultas respeten `deleted_at` (KPIs ya lo hacen con `DB::table`; los modelos Eloquent no sin el trait). |
| **Asignación** (`task_user`/`assigned_to`) | Lista del técnico (límite 50), cálculo de unidades pagables, filtros `assigned*`, notificaciones. |

## 4. Lista de regresión (checklist para D, E y F)

Marcar antes de mergear cualquier item que toque `tasks`:

1. [ ] Crear tarea de Scheduling (con y sin `ticket_id`) y verificar estado `ToDo`, creador y asignados.
2. [ ] Crear OT de Talento de cliente y de prospecto (`crm_lead_id`); rechazo cuando vienen ambos.
3. [ ] Recorrer `ToDo → InProgress → Done`: `finish_at` y `finish_at_first_time` se fijan; al salir de `Done` `finish_at` se anula.
4. [ ] App del técnico (MegaFamilia): lista (excluye `Archivado`, límite 50), actualizar estado, nota, foto, firma y GPS; confirmar si la compuerta nueva (si existe) también aplica aquí.
5. [ ] Pago semanal: `LiquidationService::countBillableUnits` y `breakdown()` devuelven lo mismo antes y después con un conjunto fijo de OT de prueba (`UNI-PRUEBA`).
6. [ ] `OrdenTrabajoUnifiedService`: listado unificado, filtro por estado, filtro por prospecto, `iniciar` y `completar`.
7. [ ] WarRoom: KPIs y Insights (tickets abiertos/cerrados/pendientes) sin cambios inesperados; crear un `ActionItem` y comprobar si aparece o no la tarea vinculada (hoy no aparece: ver 2.1).
8. [ ] Calendario (Fullcalendar) y listado datatable de Scheduling con filtros de estado/prioridad/asignado.
9. [ ] Borrado: eliminar una tarea de prueba y contar filas en las 13 tablas hijas antes/después; ledger sin `reference_id` colgado.
10. [ ] SmartImport: exportar/importar `tasks` conservando ids.
11. [ ] Portal/WhatsApp/MegaFamilia soporte: sin cambios (no tocan `tasks`).
12. [ ] Pruebas automáticas solo contra `megaisp_test`, por nombre de archivo.

## 5. Hallazgos para decidir (no resueltos aquí)

- **WarRoom → tarea**: crea tareas con `source` inexistente y estado `To Do` (falla siempre en silencio). Corregirlo cambia comportamiento visible; va tras interruptor de `config/unificacion.php`.
- **KPI de WarRoom** cuenta toda tarea como ticket; separar por tipo es decisión de producto.
- **Borrado en duro** con cascada a evidencias de Talento: candidato a SoftDeletes (item aparte).
