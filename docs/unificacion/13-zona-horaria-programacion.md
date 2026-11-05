# Zona horaria y programación única de tareas (UNI-T F1)

- **Zona de negocio:** `app.timezone = America/Mexico_City` (`TaskScheduleService::zona()`); la hora del servidor no manda.
- **Forma oficial** de una task: `start_date` (Y-m-d) + `start_time`/`end_time` como datetime completo (Y-m-d H:i:s). Los lectores toleran el formato viejo (`start_time` solo-hora).
- **Escritores** (todos detrás del interruptor `UNIFICACION_PROGRAMACION_UNICA`, `config/unificacion.php`):
  `TaskController::getStartAndEndTime` (alta/edición, con o sin duración; cubre `CrearTareaModal` desde ticket con fecha + HH:MM), arrastre del calendario, reprogramación de Talento y `update_task_old_command:process` (ahora mueve también `start_date`).
- **Lectores:** `FullcalendarController` (`ventana`) y `OrdenTrabajoUnifiedService` (Mi día / Órdenes / APIs de Talento, `start`).
- **Pruebas:** `tests/Unit/TaskScheduleServiceTest.php`, `tests/Feature/Scheduling/ReprogramacionOrdenesTest.php`.
