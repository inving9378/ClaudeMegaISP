## 2026-11-06 — La tarea automática de fechas pasadas ya no mueve lo terminado

**Qué pasaba.** `update_task_old_command:process` pasa a hoy las tareas con fecha pasada. No miraba el
estado, así que una tarea terminada o archivada también cambiaba de fecha cada vez que corría, y se
perdía cuándo se hizo. En dev hay 852 terminadas y 665 archivadas que entrarían en esa consulta.

**Arreglo.** `TaskRepository::getTasksToUpdate()` solo toma las de estado `ToDo` e `InProgress`. Las
tareas sueltas abiertas se siguen moviendo a hoy como antes; apagar eso es el interruptor
`UNIFICACION_FECHAS_ATRASADAS_INTACTAS_F2` y no se tocó.

**Probado.** `OrdenesAtrasadasTest`: 3 pruebas pasan. La nueva corre el comando con una tarea de cada
estado: las abiertas quedan en hoy y `Done` y `Archivado` conservan su día. Sin el arreglo falla con
`Done` movida a hoy.

**Límites.**
- En dev la tarea no está programada (`command_configs` no tiene su fila), así que aquí no ha movido
  nada. La fila viene de una migración archivada; en producción no se pudo revisar desde dev.
- Las fechas que ya se hayan movido no se recuperan con esto.

Quién: sesión de David (Claude Code).
