# 12 · «Mi espacio»: vistas por rol sobre la ficha propia (UNI-T N1, #910)

Documento de diseño. Solo conteos y nombres técnicos, sin personas. No cambia código ni permisos.
Complementa `04-experiencia-usuario.md` (forma de la ficha), `01a-talento.md` §3 (pestañas actuales) y
`10-matriz-permisos.md` (matriz B2). Etiquetas: [código] leído en el código, [DEV] observado en la BD de
desarrollo, [por verificar] sin comprobar. Regla del proyecto: primero el portal web; la app después, sobre
los mismos endpoints `/talento/portal/*`.

## 1. Base [código]

- La ficha propia (`TalentoColaboradorFicha.vue`, ruta `/talento/mi-ficha/{id?}`) tiene 21 pestañas.
  `Actor::sections()` calcula 12 secciones posibles; la ficha usa 5 (`mi_dia`, `mi_dinero`, `prospectos`,
  `equipo`, `ventas`). Las demás pestañas son pantallas de administración embebidas.
- Gate común de lo propio: `portal.colaborador` (solo el shell). Los datos se acotan por `Actor`
  (self-scoped), no por el permiso.
- «Mi espacio» = grupo de pestañas propias; cada vista se compone **por secciones que aplican** (una persona
  con dos puestos ve la unión), no por un track único.

## 2. Vistas (fuente · pantalla reutilizada · permiso que la habilita)

| Vista | Fuente (endpoint) | Pantalla existente que se reutiliza | Permiso / condición | Estado |
|---|---|---|---|---|
| Mi día | `/talento/portal/asistencia/hoy`, `/portal/ots/hoy`, `/portal/reaperturas` | `TalentoMiDia.vue` | `portal.colaborador` + colaborador activo (sección `mi_dia`) | ✅ |
| Mis tareas | `tasks` asignadas al usuario (`task_user`); lectura vía servicio de tareas/`OrdenTrabajoUnifiedService` | Listado de Tareas filtrado a lo propio | `task_edit_task` (propias); `task_view_full_task` **no** para mostrador/vendedor (B2 §5) | 🟡 falta endpoint propio en `/portal` |
| Mis órdenes | `/talento/portal/ots/hoy` (propias); historial vía móvil `/talento/api/ots/*` (self-scoped) | «Mi día» + `TalentoCampo.vue`; **no** la pestaña admin Órdenes | `portal.colaborador`; la pestaña admin exige `talento.work_orders.view` | 🟡 historial propio sin endpoint web |
| Mis tickets | tickets asignados/creados por el usuario | Listado de Tickets filtrado a lo propio | permiso de Tickets del rol (mostrador/dirección) | ⛔ sin bloque propio en la ficha [por verificar permiso] |
| Mi dinero | `/talento/portal/dinero/{cuenta,desglose,fondo,prestamos}` (`countBillableUnits` = lo que se paga) | Pestaña «Mi dinero» | `portal.colaborador`, solo lectura; sección `mi_dinero` | ✅ |
| Mis ventas y prospectos | `/talento/portal/prospectos`, `/talento/mis-ventas`, `sellerData` | `TalentoMisProspectos.vue`, `TalentoVentas.vue` | `Actor::seller()` (sección `prospectos`/`ventas`); `talento.comisiones.view` para comisiones | 🟡 atribución real = `seller_id`; `colaborador_id` en doble escritura (#9990878) |
| Mi caja | caja diaria de efectivo del vendedor (`sellers`/`cuts`) | `/talento/caja-vendedor` | `talento.caja-vendedor.view` | 🟡 pantalla de menú, sin pestaña propia |
| Mis documentos | `/talento/portal/documentos` (+firma, PDF) | Sección `documentos` (#9990814) | `talento.documentos.ver-propios` | ✅ en `Actor`; la ficha aún no la usa |
| Mi material | `/talento/portal/material` (`inventory_item_stocks`) | Sección `material` (solo lectura) | `Actor::custodia()` | ✅ en `Actor`; la ficha no la usa |
| Mi equipo / validaciones | `/portal/mi-equipo-progreso`, `/portal/equipo/penalizaciones`; validar: `POST /ordenes/{id}/validate` | Pestañas Mi equipo y Penalizaciones | `tieneEquipo()` o super admin; validar exige `talento.work_orders.validate` | ✅ (dato casi vacío: 38 de 39 sin supervisor [DEV]) |
| Tablero | KPIs agregados (plantilla, productividad, cierre semanal) | `TalentoDashboard.vue` | `talento.view` + tablero económico solo dirección (B2 §5) | ✅ administración |

## 3. Vista por rol

Cada rol ve la **unión** de las filas que le aplican; lo no listado no se muestra (no se deshabilita).

| Rol | Mi día | Tareas | Órdenes | Tickets | Dinero | Ventas/prospectos | Caja | Documentos | Material | Equipo/validaciones | Tablero |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Dirección | ✓ | todas | todas (admin) | todos | ✓ + económicos completos | ✓ | ✓ | ✓ | ✓ | ✓ (todo) | completo |
| Supervisión | ✓ | equipo | equipo + validar | equipo | ✓ | — | — | ✓ | ✓ | ✓ (su equipo) | de su equipo |
| Técnico | ✓ | propias | propias (campo, evidencias, cierre) | — | ✓ | — (salvo doble rol) | — | ✓ | ✓ | — | «Mi progreso» (calidad dBm) |
| Mostrador | ✓ | **solo propias** | seguimiento | los de su mesa | ✓ | ✓ (ventas propias) | — | ✓ | — | — | «Mi mostrador» |
| Vendedor | ✓ | propias | — | — | ✓ | ✓ (prospectos, ventas, comisiones) | ✓ | ✓ | ✓ (si custodia) | — | «Mis ventas» |
| Almacén | ✓ | propias | — | — | ✓ | — | — | ✓ | ✓ + inventario | — | — |

Notas:
- Técnico PI (planta interna) no viaja; PE (externa) añade el mapa de «a dónde debo ir hoy» [código `Actor.php`].
- «Dirección» no hereda por defecto la vista de otros: ve lo propio en Mi espacio y lo ajeno en Administración.
- Mostrador y vendedor: `task_view_full_task` queda fuera de la vista propia (propuesta B2, pregunta 1 abierta).

## 4. Revisión contra la matriz de B2 (`10-matriz-permisos.md`)

- Perfiles de B2 (dirección, administrador, supervisión, mostrador, vendedor, técnico, almacén) cubiertos; el
  administrador se trata como dirección acotada al área.
- Mostrador «solo sus tareas» ↔ fila «Mis tareas» sin `task_view_full_task`: coherente.
- `talento.work_orders.manage` (Mostrador) y `.validate` (supervisión) se mantienen separados: ejecutar ≠ aprobar.
- Lectura que abre escritura (B2 §3): ninguna vista propia depende de esos permisos; las propias van por
  `portal.colaborador` + `Actor`, que filtra por dueño.
- SUPERVISOR_MOSTRADOR tiene 0 cuentas [DEV]: la vista «Supervisión» no tendrá usuarios hasta que Irving decida
  el perfil real (B2 pregunta 3).

## 5. Pendiente (no se decide aquí)

1. Endpoints web propios para Mis tareas, Mis órdenes (historial) y Mis tickets (hoy solo existen admin o móvil).
2. Que la ficha use las secciones `material` y `documentos` que `Actor` ya expone.
3. Renombrar las tres etiquetas «Mi progreso» (calidad/ventas/mostrador) por contenido.
4. Todo cambio de comportamiento va detrás de un interruptor de `config/unificacion.php`, por defecto = hoy.
5. Nada económico (comisiones, incentivos por estado) se define aquí: depende de las decisiones de Irving.
