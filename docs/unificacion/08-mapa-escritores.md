# 08 — Mapa de escritores y lectores por proceso (UNI-T A3)

Proyecto UNI-T (paraguas #862), item #866. Solo conteos y nombres técnicos.
Leyenda: **[código]** leído en el código actual (grep 2026-10-26) · **[DEV]** observado en la BD de DEV ·
**[por verificar]** no comprobado. Todas las filas de este documento son **[código]** salvo que se indique.

Dos almacenes conviven para una "orden": `tasks` (Scheduling, con `talento_type_id` cuando es orden de
Talento) y `talento_work_orders` (WO). `OrdenTrabajoUnifiedService` (OTU) ya unifica la vista de ambos para
el técnico y el admin de Talento; Scheduling y MegaFamilia escriben `tasks` sin pasar por OTU.

Archivos: `S` = `app/Modules/Addons/Talento/Services/`, `SCH` = `app/Modules/Addons/Scheduling/Controllers/TaskController.php`.

## 1. Procesos sobre la orden / tarea

| Proceso | Escritores (archivo:línea) | Lectores | Dueño propuesto | Debe dejar de escribir |
|---|---|---|---|---|
| Crear orden | `SCH:120` `store` (+`users()->sync` :142); `TalentoWorkOrderController.php:178` `Task::create` (+sync :198); `WarRoom/Observers/ActionItemObserver.php:22` | listas de la fila 8 | OTU (método de alta único) | Scheduling y Talento crean cada uno por su cuenta; WarRoom crea con `status 'To Do'` (distinto de `ToDo`) |
| Asignar | `SCH:142` y `:293` (`users()->sync`); `TalentoWorkOrderController.php:198`; OTU `:950` (`actualizarAdmin`) | `OTU` (`task_user`), `LiquidationService:284`, `HealthBonusService:95`, `MegaFamilia/ApiController.php:1280` | OTU | `SCH` sync directo |
| Reprogramar | `SCH:408` (`updatetaskToCalenddar`); `SCH:293-300` (`update`); OTU `:935` (vía `TaskScheduleService::fields`); `UpdateTasksOldCommand.php:66` | OTU `:200`/`:742` (`TaskScheduleService::start`), calendario `FullcalendarController::getTaskEvents` | OTU + `TaskScheduleService` | los 3 caminos que escriben `start_date/start_time/end_time` a mano |
| Iniciar | OTU `:370`/`:380` (`iniciar`), `:571-575` (`subirEvidencia` pasa a InProgress); `MegaFamilia/ApiController.php:1319` (`mapOrdenStatusToTask`) | OTU, WarRoom `InsightsService:50` | OTU | MegaFamilia (guarda estado propio) |
| Evidencia | OTU `:473` `subirEvidencia`; `SCH:454` (`ObservationTask`); `MegaFamilia/ApiController.php:1357,1372` (`fileable_type Task`) | OTU `:1147` `validarEvidencias`, `:1208` gate dBm | OTU | `SCH`/MegaFamilia suben por su lado |
| Completar (cierre técnico) | OTU `:588` `completar` (`:604` WO `completed`; `:630` task `Done`+`validated_at`); `cambiarEstadoAdmin` `:977`; `SCH:426` (`archive` pone `Done`+`finish_at`); `MegaFamilia/ApiController.php:1319`; `FieldFlowService:493` | OTU, WarRoom `InsightsService:51`, `KpiController:63,151` | OTU (`completar`) | `SCH` archive y MegaFamilia (`cambiarEstadoAdmin` ya vive en OTU) |
| Validar | OTU `:1031` `validarAdmin` (WO `validated` :1089; task `Done` :1047); cierre del técnico (`:630` pone `validated_at`); `FieldFlowService:436,453` (`talento:cerrar-encuestas`, lee `tasks.validated_at`) | `LiquidationService:290` (solo tasks con `validated_at`), `HealthBonusService` | OTU `validarAdmin` | `completar` con auto-validación (:630) y el cierre de encuestas |
| Cancelar | OTU `:977` `cambiarEstadoAdmin` (`:1003/:1024`); `SCH` `update` con status | listas | OTU | `SCH` update de status |
| Borrar | `SCH:348` `destroy` (`delete`, soft); MegaFamilia `TareasController:153` (tareas parentales, otra tabla) | todas las listas filtran `deleted_at` | OTU (baja lógica con motivo) | `SCH` destroy sin motivo |
| Incidencia | OTU `:416` `reportarIncidencia` (WO `incidencia` :439) | OTU | OTU | — |
| Nota técnica | OTU `:390` `guardarNota` | OTU | OTU | — |

**Estados:** hay dos vocabularios: `tasks` (`ToDo`, `InProgress`, `Done`, `Archivado`, `To Do` de WarRoom) y WO
(`pending`, `in_progress`, `completed`, `validated`, `incidencia`). OTU traduce con `TASK_STATUS_MAP`. [código]

## 2. Fecha, unidades, incentivos

| Proceso | Escritores | Lectores | Dueño propuesto | Debe dejar de escribir |
|---|---|---|---|---|
| Fecha de la orden (5) | `TaskScheduleService` (`app/Services/TaskScheduleService.php:13`); `SCH:151` `getStartAndEndTime`; `resources/js/.../CrearTareaModal.vue`; `UpdateTasksOldCommand.php:66`; edición de Scheduling `SCH:293`. `ConciliarFechasTasksCommand` repara (usa `TaskScheduleService`) | OTU `:200,:742`, calendario | `TaskScheduleService` | `getStartAndEndTime`, `UpdateTasksOldCommand` (por script que reescribe fechas pasadas a hoy) |
| Unidades pagables | puntos en `tasks.points` al crear (`TalentoWorkOrderController:178`); WO `points` | `LiquidationService::countBillableUnits` (tasks `:290`, WO, proyectos) | `countBillableUnits` (ya único) | cálculos paralelos |
| Pago semanal | `LiquidationService:197,212`; `TalentoWeeklyRuleEvaluationService:248` | `LiquidationService`, `SettlementService` | `PayWeek` + `LiquidationService` | — |
| Incentivos / bonos al ledger | `HealthBonusService:72,198`; `ProjectBonusService:106`; `TalentoWeeklyRuleEvaluationService:248` (los tres `credit/bonus`) | `LiquidationService:132` | Talento (ledger) | — (verificar duplicidad semanal vs bono de salud: [por verificar]) |
| Descuentos / fondos / préstamos | `PenaltyService:88,165`; `FundService:67`; `LoanService:62`; `SettlementService:164` | `LiquidationService:139`, `SettlementService:44` | Talento (ledger) | — |

Tabla única de dinero de Talento: `talento_ledger_entries` (11 llamadas `TalentoLedgerEntry::create` en 8 servicios). [código]

## 3. Venta, comisión, ticket, asistencia

| Proceso | Escritores | Lectores | Dueño propuesto | Debe dejar de escribir |
|---|---|---|---|---|
| Atribución de venta (`client_main_information.seller_id`, es `users.id`) | alta de cliente `ClientController.php:201` (`DB::table insertGetId`); conversión CRM `CrmController.php:172`; `Asistente/Herramientas/CrearCliente.php:138`; import `MigrateSeedController.php:582`; puente `colaborador_id`: `ColaboradorIdResolver.php:91`, `BackfillColaboradorIdBridgeCommand` | `TalentoVentasController:98`, `TalentoMobileEquipoController:510`, `DashboardService:433`, `Seller.php:68,178`, `SaleController:167`, `PaymentClientController:215`, `TalentoCajaVendedorController:247`, `InstallationController:29`, `ClientDatatableHelper:233,849`, `WhatsAppContactMatcherService:48` | Identidad unificada (colaborador) con `seller_id` como espejo | los 4 sitios que escriben `seller_id` a mano |
| Comisión de vendedor | `PaymentSellerController:324` (`new PaymentByRule`), `:392` (`PaymentByRuleDetails`), `:193,:233` (`PaymentSeller`); `CommissionRule.php:83`; `CommissionRuleController:63`; cálculo `CalculateBalanceSellerService` (sin escritura directa) | `Seller.php:38`, estrategias `PeriodStrategies/*`, `TalentoComisionEspejoObserver` (escribe `talento_comisiones_espejo`), `ReconciliarComisionesEspejoCommand` (solo lectura) | Vendedores (`payment_by_rule`) hasta decisión económica de Irving; Talento solo espejo | Talento no debe pagar estas comisiones por ledger (decisión pendiente, ver item #123) |
| Embajadores | `TalentoEmbajadoresController.php:171` | `TalentoEmbajadoresController::sellerData` | Embajadores | — |
| Ticket (tareas WarRoom/Scheduling) | `ActionItemObserver.php:22` crea `tasks` con `source 'warroom'` | KPI WarRoom: `InsightsService:48-51`, `KpiController:63,151,283` (cuentan **toda** `tasks`, incluidas órdenes de campo, como "tickets") | Scheduling | KPI no distingue ticket de orden de campo [por verificar] |
| Asistencia | `AttendanceService.php:105` (`checkIn`), `:130` (`checkOut`), `:154` `storePing`; **segundo escritor** `TalentoMobileApiController.php:197` (`DB::table insertGetId` en `talento_attendances`) | WarRoom (`MeetingController`, `KpiController`, `Desempeno*`), `FieldFlowService` | `AttendanceService` | `TalentoMobileApiController:197` (insert directo) |

## 4. Listados de tareas con consulta propia (lectores)

Lista y calendario de Scheduling (`SCH` `table`, `FullcalendarController::getTaskEvents`); Órdenes de Talento
(OTU `listForAdmin` :788, `historial` :248); Mi día / app (`summaryForHoy` :219); MegaFamilia técnico
(`MegaFamilia/ApiController.php:1280`); KPI WarRoom (`KpiController`, `InsightsService`). Seis consultas
distintas sobre las mismas tablas, con filtros de alcance y estado propios.

## 5. Resumen por dueño propuesto

- **Orden / estado / asignación / evidencia / validar:** OTU. Dejan de escribir `status`/`task_user` directo: `SCH` (`update`, `store`, `archive`, `updatetaskToCalenddar`), MegaFamilia (`ApiController:1319`), `UpdateTasksOldCommand`, WarRoom observer (estado `To Do`).
- **Fecha:** `TaskScheduleService` (5 escritores → 1).
- **Dinero Talento:** ledger vía `LiquidationService`/`PayWeek` (ya único).
- **Venta/comisión:** Vendedores (`payment_by_rule`) sin cambios hasta decisión económica; Talento solo lee/espeja.
- **Asistencia:** `AttendanceService`.

## 6. Verificación cruzada hecha

Grep sobre `app/` y `routes/` de: `Task::create`, `->users()->sync`, `DB::table('tasks')`, `TalentoLedgerEntry::create`,
`PaymentByRule*`, `'seller_id'`, `talento_attendances`. Cada fila del mapa se localizó en el código actual;
la lista de la auditoría tenía 4 escritores de cierre, 3 de validación y 5 de fecha: coinciden con las filas
Completar / Validar / Fecha. Escritores no listados en la auditoría y encontrados aquí: WarRoom observer (crea
`tasks`), segundo insert de asistencia, `SCH:archive` (pone `Done`), `FieldFlowService:453`.
Lo marcado [por verificar] requiere observar datos en DEV (no se consultó la BD).
