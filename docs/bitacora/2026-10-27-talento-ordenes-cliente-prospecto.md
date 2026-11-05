## 2026-10-27 — Talento: asignar trabajo desde Clientes y Clientes potenciales (revisión + correcciones)

Rama `fix/talento-ordenes-cliente-prospecto` (sin mezclar a main).

### Lo que se verificó (en navegador, DEV, cuentas de prueba `prueba_admin` y `prueba_mostrador`)

- Ficha del cliente (menú «Tareas» → «Asignar trabajo») y ficha del prospecto (botón «Asignar trabajo»):
  el botón sale para administrador y Mostrador (permiso `talento.work_orders.manage`).
- «Asignar a» va en dos pasos (`SelectRolUsuario` con `/talento/api/ordenes/asignables`): la persona
  queda deshabilitada hasta elegir rol, se vacía al cambiar de rol y solo lista fichas de Talento
  activas (ficha `active` + usuario `activo`): 17 de 35 en DEV. Roles ofrecidos: Administrador,
  Desarrollador, Mostrador, Técnico, Vendedor (los que tienen al menos una persona activa).
- Como Mostrador se crearon dos órdenes reales (una desde el cliente 6810, otra desde el prospecto
  crm 3594) para una cuenta demo sin teléfono ni app; quedaron ligadas al cliente/prospecto, asignadas
  a la persona elegida y con `created_by` = quien la creó. Se borraron al terminar (tareas,
  `task_user`, `task_notifications`, `notifications`).

### Huecos encontrados y corregidos

1. **El técnico no veía a quién atender en órdenes de prospecto.** Mi día, el detalle y la app leen
   `cliente`/`direccion`/`telefono`, que solo se llenaban con datos de cliente. Ahora, sin cliente y
   con prospecto, se llenan con los del prospecto (dirección compuesta de calle, números, colonia y
   municipio, porque casi ningún prospecto trae `address`) + `es_prospecto` para la marca
   «· prospecto» en Mi día. La app no necesita actualización (mismas llaves).
2. **Talento › Órdenes no decía de qué cliente era la orden.** La columna «Prospecto» pasa a
   «Cliente / prospecto»; el cliente sale con enlace a su ficha (si se tiene `client_edit_client`) y
   también en el detalle. Las órdenes de la tabla vieja (`talento_work_orders`) resuelven el nombre
   solo para la página mostrada.
3. **Una orden sin fecha caía al final de la lista** (se ordenaba con 0). Ahora, sin fecha, se ordena
   por cuándo se creó.
4. **El servidor aceptaba asignar a alguien inactivo** si llegaba su id (la lista solo muestra
   activos). `store` responde 422 «Esta persona ya no está activa…». Probado con una ficha activa cuyo
   usuario está desactivado: 422 y ninguna tarea creada.
5. `store` ya no truena si no llega `notes`.

### Pendiente de decidir (no se tocó)

- Una orden creada **sin fecha** no aparece en «Mi día» del técnico (solo lista las de hoy); la ve en
  su historial. Opciones: pedir fecha obligatoria al asignar, o que Mi día muestre también las
  pendientes sin fecha.
- Fuera de Talento: con Mostrador, cada página pide `/administracion/documentation/documentation_menu/tree`
  y recibe 403 (la ruta no está en `config/route_permission.php`); no se revisó qué ve en el menú del manual.

## 2026-10-27 — «Asignar trabajo» exige el día

Decidido el pendiente de arriba: en el modal «Asignar trabajo» (fichas de cliente y de prospecto) el
día es obligatorio; la hora sigue opcional. «Crear orden» queda deshabilitado sin día, con la nota «El
día es obligatorio: ese día le aparece en «Mi día»». Se quitó «Quitar fecha» del calendario. Solo es
del modal: el servidor y «Nueva orden» de Talento › Órdenes siguen aceptando una orden sin fecha.
Rama `fix/talento-asignar-fecha-obligatoria`.

## 2026-10-27 — «Nueva orden» (Talento › Órdenes) también exige el día

Misma regla que «Asignar trabajo»: día obligatorio («Elige el día de la orden.» si falta), hora
opcional. El `datetime-local` del navegador (salía en inglés y obligaba a poner hora) se cambió por el
mismo selector que ya usaba «Asignar trabajo», ahora componente compartido `FechaHoraOrden.vue`
(calendario en español + listas de hora y minutos). Su calendario se abre siempre como menú y con
z-index 10050, porque el modal de Nueva orden va en 9999 y el menú de Quasar (6000) quedaba detrás.
Probado en navegador con el POST interceptado (no se creó ninguna orden): Nueva orden como Mostrador
manda `2026-10-28 10:30`; Asignar trabajo como admin en modo oscuro manda `2026-10-28`.
Sigue aceptando sin fecha: el servidor y el modal del flujo de campo (`TalentoFlujoCrearModal.vue`).
Rama `fix/talento-nueva-orden-fecha-obligatoria`.

## 2026-10-27 — El día es obligatorio también en «Flujo de campo» y en el servidor

- «Nuevo flujo» (pestaña Flujo de campo de la ficha del colaborador, `TalentoFlujoCrearModal.vue`):
  mismo selector `FechaHoraOrden`, «Agendada *», «Elige el día de la orden.» si falta.
- `TalentoWorkOrderController::store`: `scheduled_at` pasa de `nullable|date` a `required|date`
  («Elige el día de la orden.» / «El día de la orden no es una fecha válida.»). Antes de cambiarlo se
  revisó quién crea órdenes por ese endpoint: solo las tres ventanas; la app móvil y JARVIS no crean
  órdenes, y la única prueba automática que lo usa (`ReprogramacionOrdenesTest`) ya manda fecha.
- Probado: servidor dentro de una transacción deshecha (sin día / vacío → 422, texto → 422, solo día y
  día+hora → 201; ninguna tarea quedó); Flujo de campo en navegador con el POST interceptado.
Rama `fix/talento-orden-fecha-servidor-y-flujo`.

## 2026-10-27 — Una orden sin hora muestra solo el día

Una orden con día pero sin hora salía como «28/10/26, 12:00 a.m.» en Talento › Órdenes (lista y
detalle) y en Flujo de campo (lista y «Agendada»). No hay una marca de «sin hora»: la orden se guarda
a las 00:00. Ahora esas pantallas usan `fechaAgenda.js`, que muestra solo el día cuando la hora es
00:00 y deja día y hora en los demás casos. El aviso al técnico al asignarle la orden dice «para el
28/10» en vez de «para el 28/10 00:00». Efecto aceptado: una orden puesta a propósito a las 12:00 a.m.
también se verá solo con el día.
Probado en navegador con la lista real y la primera orden puesta a 00:00 en la respuesta (no se tocó
la BD): Órdenes y su detalle muestran «28/10/26»; las demás siguen «24/10/26, 7:00 a.m.»; Flujo de
campo igual. Rama `fix/talento-orden-solo-dia`.
