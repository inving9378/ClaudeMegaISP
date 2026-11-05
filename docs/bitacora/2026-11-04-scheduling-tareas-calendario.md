## 2026-11-04 — Scheduling: Tareas con el estilo de la Torre y Calendario como Google Calendar

Pedido de David: «arregla la parte de tareas y el calendario… usa los estilos de la torre y de google
para el calendario». Rama `feat/scheduling-tareas-calendario-estilo`.

**Antes**
- Tareas: filtros sueltos con «None selected» en inglés, cajas de distinto tamaño, la nota completa
  dentro de la fila (renglones enormes), estado como bloque de color plano, fecha vencida en rojo
  aunque la tarea ya estuviera terminada, prioridad como texto.
- Calendario: cuatro filtros grandes arriba, una imagen PNG como leyenda, barra de botones oscura.
  Las tareas de varios días ocupaban columnas enteras en la vista de semana y tapaban las demás.
  El título se pintaba con `innerHTML` sin escapar (un título con HTML se habría ejecutado).

**Ahora**
- Tareas (`TaskListar.vue`): encabezado con «Calendario» y «Nueva tarea», filtros en tarjeta y en
  rejilla, tabla en tarjeta con encabezados en versalitas. Chips de estado, prioridad y fecha
  («atrasada» solo si sigue abierta); la nota se ve en 2 líneas y completa en su ventana.
  Celdas: `status`/`start_time`/`last_note` y `priority` (nuevo) en `meganet/shared/table/module/task/`.
- Calendario (`QCalendar.vue` + `CalendarIndex.vue`): barra única tipo Google (Hoy, flechas,
  título, Mes/Semana/Día/Lista, Filtros con contador, Tareas, Crear). Filtros plegables con «Más
  filtros». Leyenda en una línea. Cada tarea: franja del color de su prioridad, estado (círculo,
  medio, palomita), «!» si está atrasada e iniciales de quién la tiene. Las de 24 h o más van en
  «Todo el día» (no se arrastran: se cambian desde la tarea). Las órdenes de Talento no se
  arrastran (se reprograman con motivo, #1158). Texto con `textContent`.
- `FullcalendarController` manda además `priority` y `es_orden_talento` (aditivo).
- Textos del selector múltiple en español («Seleccionar…», «Buscar…», «Seleccionar todos»).
- Modo oscuro en las dos pantallas.

**Arreglo de paso (#1158):** en `CargaCalendario.vue` y `TalentoAgenda.vue` el selector
`:global(body[data-layout-mode="dark"]) .cc …` se compilaba como solo `body[…]`: en modo oscuro dos
reglas le ponían color de texto al `<body>` de toda la app. Ahora el selector va completo dentro
de `:global(...)`.

**Verificado** en la copia de trabajo (127.0.0.1:8095, cuenta `prueba_admin`), claro y oscuro:
lista con chips, buscador dentro de la tarjeta, notas en 2 líneas; calendario en Semana, Mes y
Lista con tareas reales de dev; clic en una tarea abre «Editar tarea».

**Visto y no tocado:** al abrir «Editar tarea» desde el calendario, el título y la descripción
salen vacíos y CKEditor lanza un error de proxy de Vue; pasa igual en la versión actual.

Quién: sesión de David (Claude Code).

## 2026-11-04 — Calendario: el nombre de quien tiene la tarea salía cortado

Al pasar el cursor sobre el círculo de iniciales, BRANDON salía como «BRAND» y Prueba como «Prue».
`Task::getUserOrTeamNameAttribute` le quitaba las 2 últimas letras al nombre (`substr(…, 0, -2)`, del
primer commit del sistema); ese dato solo lo usa este calendario. Ahora manda nombre y apellido, así
que el círculo lleva las iniciales de nombre y apellido y se distinguen las cuentas que comparten nombre («Prueba Admin», «Prueba
Mostrador»). Rama `fix/calendario-nombre-completo`.

**Verificado:** `NombreAsignadoCalendarioTest` (4 pruebas) y en navegador con `prueba_admin` sobre la
copia de trabajo: un técnico sale con sus iniciales y su nombre completo, y «PM · Prueba Mostrador», sin errores.

Quién: sesión de David (Claude Code).
