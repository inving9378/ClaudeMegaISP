## 2026-11-06 — Lo atrasado se ve siempre en los calendarios, web y app

**Pedido:** «recuerda que siempre se debe mostrar las atrasadas tanto en web como en la app». En la app, con hoy elegido, la lista no mostraba lo atrasado; solo salía en su día original con un «!».

**Antes:**
- Scheduling › Calendario (web): lo atrasado salía solo en su día, con «!». Si ese día no estaba en pantalla, no se veía. Con `UNIFICACION_CALENDARIO_F4` encendido ni siquiera llegaba desde el servidor.
- Calendario de la app: igual, y el servidor solo mandaba lo del rango.
- Mi día y la agenda de órdenes ya lo mostraban en hoy; no se tocaron.

**Ahora:**
- Si hoy está en el rango, el servidor también entrega lo abierto (`ToDo`, `InProgress`) de días anteriores, en los dos calendarios. Lo terminado, cancelado o de otra persona no llega. Si hoy no está en el rango, nada cambia.
- Web: además de en su día, sale una copia en la franja «Todo el día» de hoy, con «3 días · título». No se arrastra y al abrirla edita la tarea real. Aplica a Mes, Semana, Día y Lista.
- La app (rama `fix/calendario-atrasadas-hoy` de su repositorio, misma versión 1.26): sale arriba en la lista de hoy, con «Atrasada · 3 días · era para el martes 3 de noviembre», y el día de hoy lleva el «!».

**Comprobado:**
- `CalendarioAppTest` (9), `CalendarioAtrasadasTest` (2) y `ReprogramacionOrdenesTest` (3) pasan.
- Navegador con la rama y `demo_tecnico_bajo`: copias en hoy en las cuatro vistas, ninguna la semana siguiente, abrir la copia abre la tarea real, modo claro y oscuro.
- Con `prueba_admin` en dev hay 148 atrasadas: la franja muestra dos y «+146 más». Son tareas generales que aquí no mueve la tarea programada, porque en dev no corre el cron.

**Visto, sin tocar:** al abrir una tarea con un técnico, una consulta falla en la consola (`AxiosError`); también pasa sin este cambio.

**Datos de prueba en dev:** tareas 1769, 1770 y 1771 de `demo_tecnico_bajo`, sin puntos ni cobro.
