# Módulo Scheduling

> Proyectos, tareas y calendario para el equipo operativo del ISP (instalaciones, mantenimientos, soporte). `app/Modules/Addons/Scheduling/` · slug `addon-scheduling` · módulo **addon**, activo, sin dependencias declaradas. Modelos (`Task`/`Project`) viven en `app/Models/`, no en el namespace del módulo — son compartidos por otros addons.

## 0. En simple
Es la lista de pendientes y el calendario donde el equipo (técnicos, soporte, oficina) organiza lo que hay que hacer, quién lo hace y para cuándo.

## 1. Qué es
Módulo **addon** bajo el prefijo de rutas `/scheduling/*` que implementa un gestor de **proyectos → tareas → calendario**, con plantillas de tareas reutilizables, checklists de verificación, notas/observaciones, archivos adjuntos (imágenes/video/documentos), notificaciones a los usuarios asignados, filtros avanzados (por técnico, proyecto, estado, partner) y archivado. No es exclusivo de campo: convive tareas internas de oficina (`tipo='interna'`) y tareas de campo (`tipo='campo'`, las que consume el addon Talento como órdenes de trabajo).

## 2. Para qué sirve
Le da al equipo de instalaciones/soporte (y a oficina en general) un solo lugar para crear proyectos de trabajo, desglosarlos en tareas asignables a técnicos o equipos, verlas en un calendario arrastrable, y darles seguimiento (notas, adjuntos, checklist de verificación, archivado) hasta cerrarlas. También sirve de "pizarrón" compartido: otros módulos (Tickets, Talento, MegaFamilia, WarRoom) crean o leen filas de `tasks` directamente para sus propios flujos.

## 3. Cómo funciona

**Flujo principal:** crear plantillas de tareas (`TemplateTask`, gestionadas desde Configuración) → crear un `Project` (opcionalmente con equipos asignados) → crear `Task`s dentro del proyecto, asignadas a uno o varios usuarios (`assigned_to` → tabla pivote `task_user`) → dar seguimiento desde el calendario (arrastrar reprograma `start_time`/`end_time`) → archivar al terminar.

**Modelos y tablas** (`app/Models/`, fuera del namespace del addon):
- **`Task`** (tabla `tasks`, `$guarded=[]`): `project_id`, `title`, `status` (ToDo/InProgress/Done/Archivado/PostponedByClient), `priority`, `description`, `start_time`/`end_time`, `finish_at`/`finish_at_first_time`, `archived`/`archived_at`/`archived_by`, `client_main_information_id`, `ticket_id`, `talento_type_id`, `tipo` (campo/interna), `points`, `is_billable`, `validated_at`. Relaciones: `project()`, `users()` (belongsToMany vía `task_user`), `files()` (morphMany `File`), `notes()` (hasMany `ObservationTask`), `closure()` (hasOne `TaskClosure`), `ticket()` (belongsTo `Ticket`), `talentoType()` (belongsTo `TalentoWorkOrderType`, addon Talento). En `Task::boot()` (`saving`) valida que si hay `talento_type_id`, el `tipo` de la tarea coincida con la `category` de ese tipo de Talento — lanza excepción si no. Al cambiar `status` a `Done` fija `finish_at`; revertir desde `Done` lo limpia.
- **`Project`** (tabla `projects`, extiende `BaseModel`): `title`, `description`, `type`, `partner`, `project_lead`, `category`, `workflow`. Relaciones: `teams()` (belongsToMany `Team` vía `project_team`) y `partners()` (morphToMany `Partner`).
- Satélites: `TemplateTask` (tabla `template_tasks`, plantillas), `ObservationTask` (tabla `observation_tasks`, notas), `TaskClosure` (tabla `task_closures`), `TaskNotification`, `ListTemplateVerificationTask` (checklist marcado por tarea).

**Controllers** (`Controllers/`):
- **`TaskController`** — CRUD de tareas (`index`/`create`/`store`/`edit`/`update`/`destroy`/`table`), `store()`/`update()` validan plantilla de descripción (`DocumentTemplateService`), calculan `start_time`/`end_time` a partir de `FrequencyEstimatedDedicatedTime`, sincronizan usuarios asignados y disparan `Task::sendNotifications()`. Además: `showCalendar()`/`updatetaskToCalenddar()` (drag&drop del calendario), `archive()`/`unArchive()` (fuerza `status=Done` al archivar + log), `addNote()`/`getNotesByTask()`, `uploadFile()`/`download()`/`removeFile()` (adjuntos, con streaming para video), `getListTemplateVerification()` (checklist), `readNotification()`/`unreadNotification()`.
- **`ProjectController`** — CRUD de proyectos, más `usersForProject()` (usuarios filtrados por los equipos del proyecto, con fallback a todos los `notClientRole` — alimenta el modal "Crear Tarea") y `syncTeams()`/`getTeams()` (mapping proyecto↔equipos).
- **`TaskDatatableHelper`** filtra qué tareas ve cada usuario en las tablas: sin `task_view_full_task` (ni admin/superadmin), solo ve las tareas donde está asignado.

**Vistas:** Blade legacy en `resources/views/meganet/module/scheduling/` (`task/{index,add,edit}`, `task_archived/*`, `project/index`, `calendar/index`) que montan componentes Vue: `TaskListar.vue`/`TaskCrud.vue`/`TaskEdit.vue`/`TaskTableGrid.vue`, `ProjectListar.vue`/`ProjectCrud.vue`, `CalendarIndex.vue` (usa `QCalendar.vue` compartido), `CrearTareaModal.vue`.

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Rutas web** bajo `['web','auth','check_route_permission']`, prefijo `/scheduling/*` (declaradas en `routes.php` propio del addon, "Project + Task aplanados" desde 2026-05-20): CRUD de `project/*` (incluye `{project}/users`, `{project}/teams` GET/POST para el mapping de equipos) y de `task/*` (calendario, notas, adjuntos, archivado, notificaciones — listado completo en el código).
- **Permisos Spatie** (`module.json`): `scheduling_view_scheduling`, `scheduling_view_calendar`, `scheduling_project_{view_project,create,update,delete}`, `scheduling_task_{view_task,create,update,delete}`, más un segundo set "general" reusado por otros consumidores de `tasks`: `task_{view_task,add_task,edit_task,delete_task,archive_task,view_archived_task,view_full_task,export_task}` y `task_filter_*`/`calendar_filter_*`/`templatetask_*`.
- **`admin_cards`** ("Scheduling" → `/scheduling/task`) y **`config_sections`** (`scheduling_general`, parámetros documentados en `/scheduling/task`).
- **`client_tab`** declarado en `module.json` (`component: "SchedulingClientTab"`, permiso `scheduling_task_view_task`) para mostrar las tareas del cliente en su ficha — **sin componente Vue registrado hoy** (no existe `SchedulingClientTab` en `app.js`; el infra de tabs de `ClientCrud.vue` solo monta las declaraciones cuyo componente está registrado globalmente, así que esta pestaña no aparece todavía).
- **Notificaciones** a los usuarios asignados vía `Task::sendNotifications()` (`StandardNotification` + registro en `TaskNotification`) cuando se crea/edita una tarea.
- El modelo `Task` en sí, como **tabla compartida**: otros módulos crean filas de `tasks` directamente (no vía las rutas de este addon) — Talento (órdenes de trabajo de campo, `talento_type_id`/`points`/`is_billable`), Tickets (vínculo `ticket_id`), WarRoom (`ActionItemObserver`) y MegaFamilia (asignación de tareas a hijos).

**Consume**
- **Tickets/Helpdesk** — `Task.ticket_id` (belongsTo `Ticket`), opcional, vincula una tarea a un ticket de soporte.
- **Talento** (addon) — `Task.talento_type_id` (belongsTo `TalentoWorkOrderType`), con validación cruzada `tipo`↔`category` en `Task::boot()`; las tareas de campo (`tipo='campo'`) son las OTs (junto con `talento_work_orders`) que cuentan para compensación de técnicos.
- **Core/Configuración** — `DocumentTemplateService` (variables en plantillas de descripción), `DefaultValueRepository` (filtros persistentes por usuario/módulo, vía módulo `FiltersTaskCalendar`), `FrequencyEstimatedDedicatedTime` (catálogo de tiempos estimados), `ListTemplateVerificationRepository`/`ListTemplateVerificationTask` (checklists configurables), `TemplateTaskController` (plantillas de tareas, vive en `Core/Configuracion`, no en este addon).
- Servicios genéricos: `FileUploadService`, `FormatDateService`, `LogService`, `UnreadNotificationService`.
- Sistema de permisos Spatie (`check_route_permission`) — gating estándar de todas las rutas. Migración `2026_07_07_120000_revoke_scheduling_view_calendar_from_client_role` revoca explícitamente el acceso al calendario para el rol `client`.
- No se encontró integración con los servicios compartidos únicos de WhatsApp/IA/Mapas — el módulo es autocontenido salvo los vínculos con Ticket y Talento arriba descritos.

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
