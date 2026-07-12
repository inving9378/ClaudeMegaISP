# Módulo Auditoría

> Bitácora de auditoría de cambios del sistema (Activity Log, sobre `spatie/laravel-activitylog`). `app/Modules/Core/Auditoria/` · slug `core-auditoria` · módulo core, activo.

**En simple:** es el historial que registra automáticamente quién cambió qué y cuándo en el sistema, para poder revisarlo después.

## 1. Qué es
Módulo de **auditoría de cambios**: cada vez que se crea, edita o borra un registro en los modelos auditados, queda una fila en la bitácora con qué cambió, quién lo hizo y cuándo, consultable desde una pantalla de administración.

## 2. Para qué sirve
Le da al equipo de **administración/soporte** (permiso `admin_view_information`) una forma de investigar "¿quién cambió esto y qué valor tenía antes?" — útil para resolver disputas, detectar errores de captura o revisar acciones sensibles, sin tener que buscar en la base de datos a mano.

## 3. Cómo funciona
- **Producción de los registros (quién escribe en la bitácora):** `App\Models\BaseModel` (la clase base de la que heredan casi todos los modelos del sistema, ver `CLAUDE.md`) usa el trait `Spatie\Activitylog\Traits\LogsActivity` con opciones por defecto (`logOnlyDirty()` + `dontSubmitEmptyLogs()`) — es decir, **cualquier modelo que extienda `BaseModel` audita automáticamente sus cambios (solo campos que sí cambiaron)**, sin código adicional. Algunos modelos que NO extienden `BaseModel` (p.ej. `Payment`, `User`, `Discount`, `DistributionCommission`) declaran el trait aparte y sobreescriben `getActivitylogOptions()` para acotar qué columnas auditar (`logOnly([...])`).
- **Almacenamiento:** tabla `activity_log` (estándar de spatie: `log_name`, `description`, `subject_type`/`subject_id`, `event`, `causer_type`/`causer_id`, `properties` JSON con los valores viejo/nuevo, `batch_uuid`, `client_id`).
- **Modelo de lectura:** `App\Models\ActivityLog` (Eloquent sobre la misma tabla `activity_log`), con relación `user()` (`causer_id` → `User`) y accessor `user_name` ("Sistema" si no hay causante).
- **Controller** (`app/Modules/Core/Auditoria/Controllers/ActivityLogController.php`):
  - `index()` — pantalla `/administracion/activity_log`.
  - `table()` — endpoint del DataTable server-side, delega en `ActivityLogDatatableHelper` (`app/Http/HelpersModule/module/administration/activity_log/`), que arma la consulta (paginado/orden/búsqueda) y renderiza cada celda con la vista `meganet.shared.table.column_activity_log`.
- **UI:** tabla server-side con un modal (`ShowActivity.vue`) que abre el detalle (`properties` antes/después) al hacer click en una fila.
- **Retención/archivado:** el comando `activitylog:archive` (`app/Console/Commands/Active/ArchiveActivityLogsCommand.php`, corre diario 02:00 vía `Kernel.php`) mueve por lotes los registros más antiguos que N días (default 90) de `activity_log` a una **base de datos separada** (`meganet_logs`, conexión `logs`), para no inflar el backup principal; conserva la misma estructura de tabla.
- **No confundir con:** `App\Models\LogActivity` (tabla `log_activities`) es un log **distinto y no relacionado**, específico del hilo de actividad del CRM (trait `LogActivityCrm`) — no pertenece a este módulo ni comparte tabla/UI con la bitácora de auditoría.

## 4. Qué EXPONE / qué CONSUME
**Expone**
- **Rutas web** `GET /administracion/activity_log` (pantalla) y `POST /administracion/activity_log/table` (datos del DataTable), bajo `['web','auth','check_route_permission']`.
- **Tarjeta de administración** ("Bitácora de Actividad", `module.json` → `admin_cards`) con permiso `admin_view_information`.
- **Comando de mantenimiento** `php artisan activitylog:archive {--days=90} {--batch=200} {--dry-run}`, programado diario a las 02:00.
- No define eventos ni servicios propios para que otros módulos los consuman: es un módulo de **lectura/mantenimiento** sobre una tabla que otros módulos alimentan indirectamente.

**Consume**
- **Todos los modelos que heredan de `BaseModel`** (la mayoría del sistema) — son quienes generan las filas de `activity_log` vía el trait `LogsActivity` de spatie; el módulo no los referencia directamente, solo lee la tabla resultante.
- **Usuarios** (`causer_id` → `User`) — para mostrar el nombre de quien hizo el cambio.
- **Conexión de BD `logs`** (`config/database.php`, BD `meganet_logs`) — destino del archivado en frío.
- **Catálogo `modules`** — `ActivityLogDatatableHelper` resuelve las columnas del DataTable desde `Module::where('name','ActivityLog')->columnsDatatable` (catálogo DB-driven, ver nota de data-drift en `CLAUDE.md`).

> _Servicios compartidos únicos: este módulo no monta WhatsApp/IA/Mapas propios y no aplica aquí._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
