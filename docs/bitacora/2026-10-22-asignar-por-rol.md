## 2026-10-22 — Asignar trabajo por rol y persona activa (Agenda, tickets, fichas de cliente y prospecto)

Pedido: "si voy a clientes o prospectos de clientes y quiero asignar el trabajo a alguien no puedo
… debería aparecer asignar a con primeramente el rol, y después los activos de ese rol … lo mismo
pasaría con los tickets". Decisiones del mismo día: desde la ficha del cliente y del prospecto se
crea una **orden de trabajo de Talento**; "Asignado a" con rol → persona activa (una sola) se usa
**también en Agenda**.

### Qué había

- Ficha del cliente: "Tareas › Agregar Tarea" con "Asignado a" = lista de 51 equipos y personas
  mezclados, sin rol ni filtro de activos.
- Ficha del prospecto: no había cómo asignarle trabajo.
- Tickets: "Asignado a" = 42 personas, sin rol y con inactivos.

### Qué cambia

Rama `feat/asignado-por-rol`:
- "Asignado a" en dos pasos (`SelectRolUsuario.vue`, tipo de campo `select-rol-usuario`): rol y
  después una persona activa de ese rol (cuenta activa, sin clientes ni cuentas de sistema; los
  dos roles de super administrador van juntos). Aplica a tarea nueva, editar tarea, plantilla de
  tarea, "Agregar Tarea" de la ficha del cliente, ticket nuevo y detalle del ticket.
- Se asigna a una sola persona. Una tarea vieja con varias, o con alguien inactivo, no cambia
  hasta que se elige a otra. Deja de poder asignarse a un equipo completo.
- `POST /get-options-rol-usuario` y la migración `2026_10_22_160000_asignado_a_por_rol` (cambia el
  tipo del campo `assigned_to` en Task, TaskRight, TemplateTask, Ticket y TicketDetails; módulos por
  nombre; `down()` lo regresa).

Rama `feat/asignar-trabajo-fichas` (encima de la anterior):
- Botón "Asignar trabajo" en la ficha del cliente (menú Tareas) y del prospecto, para quien tiene
  `talento.work_orders.manage`: tipo de trabajo, rol → colaborador activo, fecha y notas. Crea la
  orden con el mismo `POST /talento/api/ordenes` de Talento (aviso al técnico incluido).
- `POST /talento/api/ordenes/asignables`: puestos con sus colaboradores activos (un supervisor sin
  el permiso general ve solo a su equipo).

### Pruebas (copia de la base de dev, ya borrada)

Con la cuenta demo de Mostrador: "Agregar Tarea" y ticket nuevo envían a la persona elegida; la
tarea y el ticket se guardan con ella y cambiar el asignado de una tarea funciona; editar una tarea
muestra su rol y persona; órdenes creadas desde el cliente (con el cliente) y desde el prospecto
(con el prospecto), asignadas al técnico elegido. Revisado en el navegador.

Sin cambio: "Agregar Tarea" desde la ficha del cliente sigue sin prellenar el campo Cliente.
