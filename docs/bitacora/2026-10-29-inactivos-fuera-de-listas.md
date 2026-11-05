## 2026-10-29 — Inactivos fuera de las listas para asignar

Pedido de Irving (video del 28-oct): quien ya no trabaja (se marca inactivo en Administración ›
Usuarios, no se borra) no debe salir en ninguna lista del sistema; se conserva para saber, por
ejemplo, quién hizo una venta hace dos años.

Lo que ya estaba (22-oct, `7c09d7e00`, todavía no publicado en producción): «Asignado a» de tickets
(nuevo y detalle) y de tareas de Agenda se elige por rol y después la persona activa
(`SelectRolUsuario.vue`). De las 40 cuentas de personal en dev, 16 están inactivas.

Qué se hizo (rama `fix/inactivos-fuera-de-listas`):
- `User::activo()`: cuenta activa y no la del sistema (las mismas condiciones que «Asignado a»).
- Listas del catálogo de formularios: la búsqueda acepta `solo_activos`; con eso
  `/get-options-select` da solo personal activo y agrega a quien ya está guardado en el registro,
  con «(inactivo)», para que no se pierda al guardar (`SearchModelController::personalActivo`).
  `SelectComponent` y `SelectComponentWithCheckbox` mandan el valor guardado (`conGuardado`), y
  `SelectComponent` vuelve a pedir la lista si el registro cambia a alguien que no está.
- Migración `2026_10_29_120000_listas_de_personal_solo_activos`: marca `solo_activos` en vendedor
  del cliente (`ClientMainInformation.seller_id`), vendedor y técnico del prospecto (`Crm.owner_id`,
  `CrmLeadInformation.owner_id`, `crm_techical_user_id`), líder de proyecto, miembros de equipo y
  responsable de almacén. Módulos por nombre; `down()` lo quita. `Module::getfields()` pasa la marca.
- Listas armadas en código, ahora solo activos: «Pago a técnico» (cobro en la ficha), técnicos de la
  caja de vendedor (Talento y Vendedores), personas de un proyecto y «Asignar a» al crear una tarea
  desde un ticket. Movimientos de inventario: el destino solo activos; el origen deja a todos, por si
  quien se fue todavía tiene material.

Se dejaron con todos, a propósito: los filtros de búsqueda (vendedor en la lista de clientes,
«Asignado a» en Agenda y tareas), porque sirven para buscar lo que hizo alguien que ya se fue.

Probado en el servidor (sin escribir): vendedores 28 → 13 activos; uno inactivo guardado sale como
«Nombre (inactivo)»; sin la marca la lista no cambia; lista múltiple con valores guardados; técnicos
para pago y caja 6 de 7 (0 inactivos); personas de un proyecto sin inactivos.

Verificado en dev después de mezclar, compilar y correr la migración (como administrador de prueba,
sin guardar nada):
- Ficha de un cliente con vendedor inactivo, pestaña Información: «Vendedor» muestra
  «Nombre (inactivo)», conserva el valor y la lista ofrece los 13 vendedores activos más él.
- Prospecto con vendedor inactivo: igual. Prospecto nuevo: solo activos, ninguno «(inactivo)».
- La pestaña Estadísticas de la ficha marca dos errores de la página («Element not found»,
  «t.put is not a function»); no tiene listas de personal, no viene de este cambio.
