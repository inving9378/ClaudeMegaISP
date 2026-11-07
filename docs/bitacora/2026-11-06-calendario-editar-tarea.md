## 2026-11-06 — «Editar tarea» desde el calendario ya trae el título y la descripción

**Qué pasaba.** Al tocar una tarea en el calendario, el formulario abría con el título y la descripción
vacíos, aunque el servidor sí los mandaba. Si se abría otra tarea, salían los de la anterior. Guardar
sin darse cuenta podía dejar la tarea mal.

**Causa.** El formulario ya estaba en pantalla, listo para «crear», y sus campos de texto no toman los
datos que llegan después. Los selectores sí, por eso estado y prioridad salían bien.

**Arreglo.** `TaskCrud` vacía el formulario (`resetCrudForm`) antes de cargar, así los campos se arman
con los datos nuevos. Un primer intento, volver a montar el formulario desde el calendario, no bastó
porque los datos anteriores siguen en memoria.

**Cambio de paso.** «Crear» en el calendario ahora trae los mismos valores iniciales que «Nueva tarea»
en la lista; antes los dejaba vacíos por este mismo defecto. La lista no cambia.

**Probado en el navegador**, con la cuenta de prueba de administrador, sin guardar:
- 7 aperturas por modo (claro y oscuro), con 6 tareas distintas y volviendo a la primera: título,
  descripción, estado y prioridad coinciden con lo que manda el servidor.
- «Crear» después de editar abre limpio, sin datos de la tarea anterior.
- Abrir una tarea justo al cargar la página también trae sus datos.
- El formulario de la lista es igual antes y después.

Quién: sesión de David (Claude Code).
