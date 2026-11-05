# 11 · Correcciones de acceso integradas (B1, item #868)

Integra en una sola rama sobre `main` las correcciones pendientes de #693, #698 y #700. [código]

| Origen | Qué hace | Pruebas |
|---|---|---|
| #693 | Tokens `talento-mobile` con vencimiento configurable (`config/talento.php`), límite de intentos de login (429), revocación al dar de baja al colaborador | `tests/Feature/Talento/AuthAppTalentoTest.php` |
| #698 | 13 endpoints de Scheduling por id (`TaskService::tareaAutorizada`) y la evidencia por id (`TalentoFieldFlowController`) acotados a asignado/creador/supervisor/admin/`task_view_full_task`; ajeno o inexistente = 404 | `AccesoTareasPorIdTest`, `AccesoTalentoMediaPorIdTest` |
| #700 | Cortes de caja: sin alcance de cortes solo se ve la caja propia; ids inexistentes = 404 | `tests/Feature/Vendedores/CortesCajaAccesoTest.php` |

## Compatibilidad con #691 / #697
- Sin conflictos de texto al aplicar los commits sobre `main`.
- Choque de comportamiento: `update-task-to-calendar` ya devolvía 403 (#697) al mover una tarea ajena; #698 lo habría pasado a 404. Se conserva el 403 de #697 (misma regla) y se retira la llamada redundante de #698 en ese único endpoint; su prueba lo refleja.
- Se descartó el commit `1a21910f` (variante alterna de #698 solo en `TaskController`): redundante con `TaskService::tareaAutorizada`.

## Interruptores
Son endurecimientos de acceso; no llevan interruptor de `config/unificacion.php` porque apagados por defecto no cumplirían los criterios de aceptación. Decisión registrada en el item para revisión de Irving al mergear.

## Verificación
- [código] Pruebas por archivo en `megaisp_test` (schema de referencia + catálogos copiados de DEV, solo lectura en DEV): Auth app 8/8, Media por id 1/1, Tareas por id 7/7, Cortes de caja 5/5, Reprogramación 3/3, TaskScheduleService 4/4.
- [por verificar] Matriz manual en DEV por perfil (técnico, mostrador, vendedor, dirección) con usuarios temporales: las pruebas automáticas cubren esos perfiles; la prueba en navegador queda pendiente.
