# UNI-04 · Experiencia de usuario por perfil y «Mi Espacio»

Épica UNI (plan maestro de unificación) · Documento 4 · Item #687 · 2026-10-24.
Etapa **solo documental**: es una propuesta; no se cambió código, migraciones, permisos, config ni `.env`.
Base: auditorías `01a-talento.md`, `01b-agenda.md`, `01c-vendedores.md`, `01d-ordenes.md`, `01e-base-transversal.md` (ya en `main`) y lectura puntual de `Support/Actor.php`, `TalentoColaboradorFicha.vue`, `module.json` de Talento/Vendedores/Scheduling/Tickets y `Talento/routes.php`.
Sin nombres de personas, contactos, montos por persona ni datos de clientes. Lo que no se pudo medir se dice así.

## 0. Resumen (lo que cambia el plan)

1. **«Mi Espacio» no es una pantalla nueva: es la ficha propia de Talento reordenada.** La ficha ya tiene 5 pestañas realmente propias (Mi día, Mi dinero, Mis prospectos, Mi equipo, Documentos) y ~15 pestañas que son la pantalla de administración embebida (01a §3). El trabajo de UX es **separar lo propio de lo administrativo**, no construir otro portal.
2. **De los 6 bloques de Mi Espacio, 2 tienen base sólida (Inicio ≈ Mi día, Mi Trabajo ≈ Mi día + Órdenes), 1 es parcial (Mis Ventas) y 3 dependen de entidades que no existen** (Mi Agenda → sin agenda; Mis Resultados → sin metas/evaluaciones; Mis Incentivos → sin libro de incentivos con estados). El documento los diseña pero marca desde qué fase pueden mostrar datos reales.
3. **Hoy cada perfil entra a una pantalla distinta según un solo «track» por rol** (`Actor::trackPrincipal()` elige uno entre técnico PI, técnico PE, mostrador, vendedor, genérico). 6 colaboradores tienen 2 o más roles de puesto: con un solo track, la mitad de su trabajo queda oculta. Mi Espacio debe componerse por **secciones que aplican** (como ya hace `Actor::sections()`), no por track único.
4. **El flujo «crear orden desde mostrador» ya es un solo diálogo de 4 campos** (`AsignarTrabajo.vue`); lo que falta no es quitar clics sino **plantillas por tipo de solicitud, precarga del cliente, franja horaria y aviso de choque** (01d §3). El conteo de clics propuesto es una estimación por lectura de código, no una medición (§3.4).
5. **A 360 px casi nada de Talento está probado**: solo 1 de 43 componentes Vue de Talento declara `@media` (`AsignarTrabajo.vue`). Hay que decidir qué 6 pantallas son «móvil primero» y probarlas en navegador antes de prometerlas (§5).
6. **Lo económico se decide por pestaña y por endpoint, no por «tener acceso a Talento»**: hoy Vendedor, TECNICO y Mostrador tienen `talento.view` y pueden leer la ficha básica de otro colaborador (01e §4.1). La matriz de §7 propone el criterio de visibilidad por perfil.

---

## 1. Mi Espacio sobre la ficha propia de Talento

### 1.1 Qué existe y qué se propone

`TalentoColaboradorFicha.vue` (518 líneas) es la ficha. Reemplazó al Portal de colaborador el 23-oct (CLAUDE.md). Sus pestañas propias se alimentan de `/talento/portal/*` (`Talento/routes.php:606`, gate `can:portal.colaborador`, datos filtrados por `Actor`). Sus secciones salen de `Actor::sections()` (`Support/Actor.php:198-225`): Mi día, Mi dinero, Mi material, Mi progreso (tres variantes mutuamente excluyentes: calidad/ventas/mostrador), Mis prospectos, Mi equipo, Penalizaciones del equipo, Mis documentos, Perfil.

**Propuesta de forma** (decisión de Irving en P1): la ficha, cuando la abre su dueño, muestra un grupo **«Mi Espacio»** con los 6 bloques del contrato; las pestañas administrativas (Órdenes, Compensación, Liquidaciones, Cajas, Rutas, Penalizaciones, Finiquito/Préstamos, Dispositivos, Academia…) pasan a un grupo **«Administración»** que solo aparece con el permiso correspondiente. Cuando un supervisor abre la ficha de otra persona ve «Administración» y la parte de Mi Espacio que su permiso de supervisión permite (§7). No hay ruta, login ni portal nuevo.

### 1.2 Los seis bloques

Leyenda de **estado**: ✅ hay datos y endpoint hoy · 🟡 parcial · ⛔ falta la entidad (el bloque se diseña, pero queda vacío hasta su fase).

| Bloque | Se reutiliza | Se agrega | Endpoint de origen | Permiso que lo muestra | Estado |
|---|---|---|---|---|---|
| **Inicio** | Pestaña «Mi día» (`TalentoMiDia.vue`): asistencia, órdenes de hoy, reaperturas de documentos | Saludo y fecha; «próxima actividad»; pendientes urgentes y vencidas; avisos importantes; avance semanal (hoy en «Mi progreso» y «Mi dinero») | `GET /talento/portal/asistencia/hoy`, `/portal/ots/hoy`, `/portal/reaperturas` (existen); agregar `GET /talento/portal/inicio` que **componga** los anteriores sin duplicar lógica | `portal.colaborador` | 🟡 |
| **Mi Agenda** | `shared/QCalendar.vue` (FullCalendar mes/semana/día/lista) y filtros persistentes de `FiltersTaskCalendar` (01b §8) | Vista «mi agenda / mi equipo»; fuentes: tareas, órdenes, citas, reuniones; choques y recordatorios (tablas auxiliares) | **No existe.** Propuesto: `AgendaService` de lectura (01b §9, opción 3), `GET /talento/portal/agenda?desde=&hasta=` con ventana de fechas | `portal.colaborador` (propia); supervisión para equipo | ⛔ (requiere Fase 0 de fechas en `tasks`) |
| **Mi Trabajo** | «Mi día» (órdenes de hoy) + pestaña «Órdenes» filtrada a lo propio + `TalentoCampo.vue` (flujo de campo: evidencias, firma, aceptar) | Estados programada / en ejecución / completada / pendiente de aprobación / historial en una sola lista; evidencias por orden | `/talento/portal/ots/hoy` (existe), `/talento/api/ordenes` (admin; hoy **no** está acotado a lo propio sin permiso), móvil `/talento/api/ots/*` (self-scoped) | `portal.colaborador` | 🟡 |
| **Mis Resultados** | «Mi progreso» (calidad dBm, inspecciones, ranking de ventas, mostrador) y `avance` | Indicadores individuales, objetivos y cumplimiento, comparación de períodos, evaluaciones | `/talento/portal/dinero/desglose` y `/colaboradores/{id}/avance` cubren producción; **metas y evaluaciones no existen** (01a §5, 01c §1.6) | `portal.colaborador` | 🟡 producción · ⛔ metas/evaluaciones |
| **Mis Ventas** | Pestaña «Mis prospectos» (`TalentoMisProspectos.vue`) y pantalla `Mis ventas` (`TalentoVentas.vue`, `/talento/mis-ventas`, `routes.php:88`); puente `sellerData` | Ventas registradas / validadas / rechazadas, activaciones, comisiones por estado (generada, pendiente, autorizada, pagada), metas | `/talento/mis-ventas` y `/talento/portal/prospectos` (existen). La atribución real es `client_main_information.seller_id` (= `users.id`); `colaborador_id` está en 0 filas con la doble escritura apagada (01c §3, 01e §2.2). Comisión por evento: **apagada** (listeners comentados) | Visible si la persona tiene política de venta o `Actor::seller()`; ver P3 | 🟡 |
| **Mis Incentivos** | «Mi dinero» (cuenta, desglose, fondo, préstamos) | Bonificaciones por estado (estimada → pendiente → autorizada → ajuste → historial) | `/talento/portal/dinero/*` (existe); el **libro de incentivos con estados no existe** (ledger sin estado ni idempotencia, 01a §5) | `talento.mi-dinero` propuesto o `portal.colaborador` + bandera económica (§7) | 🟡 cuenta · ⛔ estados |

Notas de implementación (propuestas, no hechas):
- Los bloques ⛔ muestran un **estado vacío explicativo** («Se activa con la agenda unificada»), no se ocultan: así el equipo ve el plan y no se promete lo que no hay.
- Cada bloque llama a **un** endpoint de composición en `/talento/portal/*`; el mismo endpoint debe servir a la app futura (01e §7.3). El frontend no calcula reglas de negocio.
- Las tres etiquetas «Mi progreso» (calidad/ventas/mostrador) se renombran por contenido («Mi calidad», «Mis ventas», «Mi mostrador») para no confundir al usuario con varios roles; hoy son mutuamente excluyentes (`Actor.php:208-210`).

### 1.3 Navegación dentro de la ficha

```
Ficha propia (dueño)                       Ficha de otra persona (supervisor)
┌────────────────────────────────┐         ┌────────────────────────────────┐
│ MI ESPACIO                     │         │ Datos de <colaborador>         │
│  Inicio · Mi Agenda · Mi Trabajo│        │  Información · Mi día* · Equipo*│
│  Mis Resultados · Mis Ventas*   │        │ ADMINISTRACIÓN (por permiso)   │
│  Mis Incentivos                 │        │  Órdenes · Compensación ·      │
│ MI CUENTA                       │         │  Liquidaciones · Penalizaciones│
│  Documentos · Material · Perfil │        │  Expediente (si expediente.view)│
│ ADMINISTRACIÓN (si hay permiso) │        └────────────────────────────────┘
│  …pestañas actuales de gestión  │         * solo lo que su permiso de supervisión permite
└────────────────────────────────┘
```

---

## 2. Pantallas por perfil

Entrada común: tras el login, **un solo destino** por cuenta (la redirección post-login por tipo está diferida a propósito, CLAUDE.md «Estrategia: migración gradual»). Propuesta: mientras no se migre un perfil, entra como hoy y el botón «Mi Espacio» del menú de usuario abre la ficha propia (`/talento/mi-ficha`, `routes.php:53`). Cuando un perfil se «muda», la redirección cambia para ese perfil y no para otros.

### 2.1 Mostrador

| Aspecto | Propuesta |
|---|---|
| **Al entrar** | Hoy: dashboard admin con el menú completo. Propuesto: buscador de cliente al frente + Inicio de Mi Espacio (próxima cita, pendientes, tickets nuevos sin responsable) |
| **Accesos rápidos** | Buscar cliente · Crear orden (desde la ficha del cliente) · Abrir ticket · Registrar pago · Registrar venta · Seguimiento de órdenes (§4) |
| **Qué queda del menú actual** | Todo lo que tenga autorizado (CLAUDE.md §2 del contrato: «conservar funciones actuales»). Clientes, Tickets, Finanzas/Captura de pago y CRM no se tocan. Se **agrega** Mi Espacio; no se quita nada |
| **Mi Espacio** | Inicio, Mi Agenda, Mi Trabajo (tareas/citas propias), Mis Ventas, Mis Incentivos, notificaciones. Track actual: `mostrador` (sección «Mi progreso» de mostrador) |
| **Datos** | 5 usuarios con rol `Mostrador`, 259 permisos (01e §3.2); rol `SUPERVISOR_MOSTRADOR` sin usuarios (decidir si se conserva) |

### 2.2 Técnico

| Aspecto | Propuesta |
|---|---|
| **Al entrar** | Pantalla única «Hoy»: órdenes del día ordenadas por hora, con cliente, domicilio, tipo, evidencias requeridas y estado. Hoy el equivalente es «Mi día» + `TalentoCampo.vue` + APK |
| **Acciones por orden** (según permisos) | Confirmar recepción · Iniciar · Pausar · Avance · Materiales · Fotos · Incidencia · Pruebas (dBm) · Finalizar · Solicitar validación. **Faltan hoy**: confirmar recepción, en camino, pausa, materiales en el flujo de tarea (01d §3-5) |
| **Accesos rápidos** | Cómo llegar (mapa), llamar al cliente, escanear serie (pendiente en la APK), material en custodia |
| **Qué queda del menú actual** | Lo autorizado: Campo, Cajas, Rutas, Calidad, Custodia. Se agrupa bajo «Mi Trabajo» sin quitar rutas |
| **Mi Espacio** | Inicio, Mi Agenda, Mi Trabajo, Mis Resultados (calidad dBm, instalaciones aprobadas), Mis Ventas (ventas propias), Mis Incentivos |
| **Dos tracks** | `TECNICO_PLANTA` (interna) no viaja a clientes; `TECNICO`/`TECNICO_INSTALADOR` (externa) sí y ve el mapa «a dónde debo ir hoy» (`Actor.php:104-139`). 7 usuarios con rol `TECNICO` en dev |

### 2.3 Planta externa

| Aspecto | Propuesta |
|---|---|
| **Al entrar** | «Semana»: agenda semanal por día y hora, con cuadrilla, ruta y obra. Las tablas `talento_projects`, `…_activities`, `…_routes`, `…_route_stops` están construidas pero con **0 filas** en dev (01a §1.1): no hay datos para validar el diseño, solo estructura |
| **Contenido** | Obras, rutas, tendido (metros programados y ejecutados), fusiones, cierres, materiales, incidencias, evidencias, supervisión |
| **Qué queda del menú** | Proyectos, Rutas, Cajas, Calidad de caja. Sin cambios de ruta |
| **Brecha principal** | No hay entidad de cuadrilla (solo `supervisor_id`, 38 de 39 vacío) ni planeación por hora; el reparto de crédito por participante existe solo en reportes de actividad (`quantity_share`) |
| **Reglas** | La pertenencia del reportante al proyecto no se validó (01a §4): no exponer escritura nueva hasta confirmarlo |

### 2.4 Supervisor

| Aspecto | Propuesta |
|---|---|
| **Al entrar** | «Mi equipo hoy»: quién trabaja en qué, órdenes atrasadas, sin asignar, por validar, incidencias abiertas |
| **Acciones** | Asignar / reasignar, reprogramar con motivo, validar, observar, confirmar penalizaciones, ver disponibilidad |
| **Qué ya existe** | Pestañas Mi equipo (progreso) y Penalizaciones del equipo (`Actor.php`, secciones `equipo` y `penalizaciones`); validación `POST /ordenes/{id}/validate`; lista de asignables acotada al equipo (`asignables()`) |
| **Brecha crítica** | **38 de 39 colaboradores no tienen supervisor** (01a §2): la pantalla nace casi vacía hasta capturar el dato (P4). Tampoco hay filtro de atrasadas / sin asignar (01d §8) |
| **Separación** | Supervisar/aprobar es permiso distinto de ejecutar (`talento.work_orders.validate` frente a `.manage`); un supervisor **no aprueba lo propio** (guard hoy ausente en `validarAdmin`, 01a §4) |

### 2.5 Administración y Dirección

| Aspecto | Propuesta |
|---|---|
| **Al entrar** | `TalentoDashboard.vue` actual con tarjetas: plantilla, productividad, garantías, cierre semanal pendiente |
| **Contenido** | Departamentos, plantilla, puestos (= roles), metas, productividad, bonos, comisiones, garantías, reportes, costos, pre-nómina |
| **Qué queda** | Todo el menú actual de Talento (23 entradas bajo «Talento», `module.json`) reorganizado en 4 grupos: Personas (Colaboradores, Documentos pendientes, Paquetes, Credenciales, Academia), Operación (Órdenes, Proyectos, Calidad, Custodia, Dispositivos, Penalizaciones), Desempeño (Compensación, Reglas semanales, Escalafón, Ranking), Dinero (Liquidaciones, Préstamos, Finiquito, Comisiones, Caja de vendedor) |
| **Sin cambio de permisos** | Reagrupar el menú no cambia quién entra; el 403 por URL de `CheckRoutePermission` se mantiene (CLAUDE.md #538) |
| **Pendiente de entidad** | Pre-nómina con observar/aprobar/bloquear/reabrir: hoy solo `draft`→`closed` (01a §5); diseño de pantalla en §2.6 |

### 2.6 Cierre semanal (pantalla de Administración)

Lista de trabajadores del período con las columnas del contrato §11; estados: **Simulado → Revisado → Observado → Aprobado → Bloqueado**, con «Reabrir» solo con permiso y motivo. Hoy existe `simular` y `calcular/cerrar` (`routes.php:182-184`), sin los estados intermedios ni «pagado».

```
CIERRE SEMANAL  ·  Período Sáb 18:00 → Sáb 18:00            [Simular] [Bloquear período]
┌────────────┬──────────┬────────┬─────────┬───────┬────────┬──────────┬──────────┐
│Trabajador  │Depto     │Activ.  │Cumplim. │Ventas │Instal. │Incentivo │Estado    │
│            │          │valid.  │         │elegib.│aprob.  │autorizado│          │
├────────────┼──────────┼────────┼─────────┼───────┼────────┼──────────┼──────────┤
│ …          │ Planta E.│ 12/14  │  86 %   │   1   │   4    │  $ ····  │Observado │
└────────────┴──────────┴────────┴─────────┴───────┴────────┴──────────┴──────────┘
 Detalle por fila → eventos de origen (OT, venta, garantía verificada) · [Observar] [Aprobar]
```

---

## 3. Flujo rápido de orden desde la ficha del cliente

### 3.1 Cómo es hoy (verificado por lectura de código)

Botón **«Asignar trabajo»** en la ficha de cliente y de prospecto (`InformationClientCrud.vue`, `InformationCrmCrud.vue`) → diálogo `AsignarTrabajo.vue` → `POST /talento/api/ordenes` (`TalentoWorkOrderController::store`, `:113`). Una orden es de cliente **o** de prospecto, nunca ambos (`:139-142`). Crea una fila en `tasks` con `talento_type_id`, la asigna en `task_user`, y avisa por app, push y WhatsApp (freno `WHATSAPP_SENDER_ENABLED`). La dirección, teléfono y plan **no se copian**; se leen al consultar. Hoy 5 tipos fijos, 1 técnico, fecha+hora sin franja ni duración, **sin validación de choque**, **sin ticket de origen** (01d §3).

### 3.2 Flujo propuesto, pantalla por pantalla

Se amplía `AsignarTrabajo.vue` y `store()`; no se crea otro sistema (01d §10).

**Pantalla 1 — Ficha del cliente** (ya existe)
```
┌ Cliente: <nombre>  ·  Plan <x>  ·  Estado: activo  ·  Saldo ····        ┐
│ [Pagos] [Servicios] [Tickets] [Instalación]     [ + Crear orden ]  ◄ clic 1│
└────────────────────────────────────────────────────────────────────────┘
```

**Pantalla 2 — Tipo de solicitud** (tarjetas, no lista desplegable)
```
 ¿Qué necesita el cliente?                                            clic 2
 [Sin servicio] [Internet lento] [Cambio de domicilio] [Cambio de equipo]
 [Revisión de fibra/potencia] [Reubicar módem] [Reparar acometida]
 [Revisión de garantía] [Mant. preventivo] [Visita técnica] [Otro]
 Origen: ○ Directo   ○ Desde ticket #…  ◄ (si se llegó desde un ticket: preseleccionado)
```
Las plantillas por tipo fijan: tipo de trabajo base (de los 5 `talento_work_order_types`), duración estimada, evidencias requeridas y texto guía. Quedan **como datos configurables**, no como código.

**Pantalla 3 — Datos precargados + observaciones**
```
 Domicilio: <calle, colonia>  [mapa]        Equipo: <ONT/serie>   Caja/puerto: <…>
 Servicio: <plan>   Contacto: <teléfono del cliente>          (solo lectura, ya existe)
 Síntoma / observaciones: [_______________________]  ◄ clic 3 (escribe)
```

**Pantalla 4 — Fecha, franja y técnico**
```
 Fecha: [ <fecha> ]  Franja: (•) 09-11  ( ) 11-13  ( ) 13-15  ( ) 15-17     clic 4-5
 Asignar a: [ Técnico disponible ▾ ]  → muestra «libre» / «ocupado 10:00-11:30»  clic 6
 ⚠ Aviso de choque: «ya tiene una orden 10:00-11:30» [Continuar con motivo] [Cambiar]
```
Solo se muestra «Asignar a» si el usuario tiene permiso de asignar (hoy: permiso o supervisor directo, `asignables()`); si no, la orden queda **«Pendiente de asignación»** para el supervisor (estado que hoy no existe).

**Pantalla 5 — Confirmar**
```
 Resumen: <tipo> · <cliente> · <fecha/franja> · <técnico>        [ Crear orden ]  clic 7
 Resultado: «Orden #… creada. Aparece en la agenda de <técnico>.»  [Ver seguimiento]
```

### 3.3 Qué se reutiliza y qué se agrega

| Elemento | Reutiliza | Agrega |
|---|---|---|
| Diálogo | `AsignarTrabajo.vue` (+ `SelectRolUsuario.vue`) | tarjetas de tipo, precarga, franja, aviso de choque |
| Backend | `TalentoWorkOrderController::store/asignables` | plantillas por tipo (datos), `ticket_id`, duración, validador de traslape |
| Aviso al técnico | push FCM + app + WhatsApp (ya existen) | notificación «cambio de horario» (hoy no existe, 01e §5.3) |
| Recepción | — | estado «Aceptada por técnico» (paso 11, hoy **falta**) |

### 3.4 Conteo de clics frente al flujo actual

**Estimación por lectura de código, no medición** (01d §3: «contar clics no fue posible sin sesión de navegador»). Se cuenta desde la ficha del cliente abierta.

| Paso | Hoy | Propuesto |
|---|---|---|
| Abrir el diálogo | 1 | 1 |
| Elegir tipo | 1-2 (lista) | 1 (tarjeta) |
| Datos del cliente / domicilio | 0 (no se precargan; el técnico los consulta aparte) | 0 (precargados) |
| Observaciones | 1 (foco) | 1 |
| Fecha y hora | 2-3 | 2 (fecha + franja) |
| Persona (rol → persona) | 2 | 1-2 (lista de disponibles) |
| Confirmar | 1 | 1 |
| **Total aproximado** | **8-10** | **7-8** |

Lectura honesta: el ahorro en clics es **pequeño**. La ganancia real es quitar capturas fuera del flujo (domicilio, equipo, síntoma), evitar dobles asignaciones y dejar trazabilidad ticket→orden. Si el objetivo de Irving es «casi predeterminado», la palanca es la plantilla que autocompleta, no el número de clics (se menciona en P2).

---

## 4. Seguimiento de órdenes para mostrador y supervisor

### 4.1 Qué hay hoy

`/talento/ordenes` (`TalentoOrdenes.vue`, `TalentoWorkOrderController::data`): filtros por colaborador, estado, tipo, rango y prospecto; sin «atrasadas» ni «sin asignar». Tickets (`/tickets`) y Scheduling (`/scheduling/task`) son pantallas separadas que no conocen las órdenes. Medido en dev: 150 tareas `ToDo`/`InProgress` con fecha vencida y 118 tickets `Nuevo` (48 sin responsable) — nadie los ve en un solo lugar (01d §8).

### 4.2 Pantalla propuesta: «Seguimiento»

Vista de lectura sobre las fuentes (misma idea que la agenda: **índice, no copia**). Los estados unificados son una **etiqueta de vista** calculada; **no sobrescriben** los estados propios de cada módulo (contrato §7). Tabla de correspondencia (01d §4):

| Estado de la vista | Cómo se calcula hoy | Disponible |
|---|---|---|
| Recibida / Pendiente de asignación | tarea `ToDo` sin `task_user` | derivable |
| Programada | tiene fecha/franja | derivable |
| Asignada | tiene `task_user` | derivable |
| Aceptada, En camino, Pausada | — | **requieren nuevos estados** (fase de órdenes) |
| En ejecución | `in_progress`/`InProgress` | ✅ |
| Terminada por técnico | `completed`/`Done` | ✅ |
| Pendiente de validación | `completed` + tipo con `requires_validation` | derivable |
| Observada | `incidencia` | 🟡 |
| Cerrada / Cancelada | `validated` / `cancelled`·`Archivado` | ✅ (cancelar sin motivo hoy) |

```
SEGUIMIENTO DE ÓRDENES            [Hoy][Semana]  Equipo: [Todos ▾]  Tipo: [Todos ▾]  🔍
 ┌─Sin asignar 6─┬─Atrasadas 4─┬─En ejecución 9─┬─Por validar 7─┬─Observadas 2─┐   ← tarjetas-filtro
 └───────────────┴─────────────┴────────────────┴───────────────┴──────────────┘
 #   Cliente       Tipo         Programada     Responsable   Estado        ⏱   Origen
 …   <cliente>     Sin servicio  hoy 10-12     <técnico>     En ejecución  –   Ticket #
 …   <cliente>     Instalación   ayer 15-17    (sin asignar) Atrasada      1d  Directo
 Fila → panel lateral: línea de tiempo (quién/cuándo/motivo), evidencias, [Reprogramar][Reasignar][Validar]
```

Reglas de la pantalla:
- **Mostrador**: ve lo que él creó o lo de su cliente actual; puede reprogramar con motivo. **Supervisor**: ve el árbol de su equipo (`idsColaboradoresVisibles`, ya existe) y valida.
- **Reprogramar con motivo y cancelar con motivo** son campos obligatorios nuevos; hoy no existen y `actualizarAdmin` solo cambia fecha/notas (01d §4).
- **Línea de tiempo**: requiere el historial que hoy está **roto** (`activity_log` con 0 filas de `Task`/`TalentoWorkOrder`; la transición admin usa `DB::table` y no dispara observers). Sin eso, el panel lateral solo podría mostrar fechas actuales, no cambios. Es prerrequisito, no detalle.
- **No cerrar sin evidencias**: la ruta del técnico ya devuelve 422 `EVIDENCIAS_FALTANTES`, pero `PUT /ordenes/{id}/status` permite `in_progress→completed` sin pasar por ahí (01d §5). El botón «Cerrar» del supervisor debe usar el mismo servicio que el técnico (P5).

---

## 5. Uso desde el celular (360 px)

### 5.1 Punto de partida

- La plantilla base declara `viewport` (`Core/Layout/views/title-meta.blade.php:3`), pero **solo 1 de 43 componentes de Talento declara `@media`** (`AsignarTrabajo.vue:270`, a 420 px). Otros usan utilidades Quasar/Bootstrap (56 coincidencias de `xs`/`sm:`/`$q.screen` en `talento/*.vue`, conteo de `grep`, sin verificar caso por caso).
- Hay una APK de Talento en uso (React Native, Sanctum) y el portal PWA de Talento está retirado (queda un `sw.js` de baja, 01a §7). **No se probó ninguna pantalla en navegador a 360 px** en esta pasada: lo siguiente es una lista de requisitos, no un diagnóstico.
- Peligro conocido: `.row > *` de Bootstrap 5 estira tabs/botones/selects de Quasar en el admin (memoria del proyecto); aplica a cualquier pantalla de Mi Espacio nueva.

### 5.2 Pantallas que deben funcionar bien a 360 px

| # | Pantalla | Quién | Requisito mínimo |
|---|---|---|---|
| 1 | Inicio / Mi día | todos | Lista vertical, sin tablas anchas; tarjetas apiladas |
| 2 | Orden del técnico (detalle + evidencias + firma) | técnico | Botones ≥ 44 px; cámara; sin scroll horizontal |
| 3 | Mi Agenda (vista día/lista) | todos | Vista «lista» por defecto en móvil; semana/mes opcional |
| 4 | Crear orden (diálogo) | mostrador/supervisor | Tarjetas de tipo en 2 columnas; selector de franja tipo chips |
| 5 | Seguimiento (lista de tarjetas) | supervisor | Tabla → tarjetas apiladas; filtros en cajón |
| 6 | Mis Incentivos / Mi dinero | todos | Totales arriba, detalle desplegable |
| 7 | Notificaciones | todos | Campana existente (`/notifications/count`) |

Pantallas **de escritorio aceptable** (no se promete móvil): Cierre semanal, Compensación, Reglas, Colaboradores, Paquetes de documentos, reportes.

### 5.3 Criterios y pruebas

- Probar en navegador a 360×640 (flujo `guardar→editar→convertir`, memoria «revisar cada pantalla»): sin desbordes horizontales, texto ≥ 14 px, sin dependencias de hover.
- **Fuera de alcance**: sincronización offline (el contrato la excluye hasta un diseño específico); app nativa nueva.
- La APK vigente ya cubre parte de las pantallas 1-2 y 6 contra los endpoints `/talento/api/*`; la decisión de «web responsiva vs. APK» por pantalla va en P6.

---

## 6. Bocetos (ASCII) de las pantallas principales

Anchos de ~72 caracteres (escritorio). Los datos son marcadores, no personas ni montos.

### 6.1 Mi Espacio · Inicio

```
┌ Mi Espacio ─ Hola, <nombre> · <día y fecha> ──────────── 🔔 3 ┐
│ Inicio │ Mi Agenda │ Mi Trabajo │ Mis Resultados │ Mis Ventas │ Mis Incentivos│
├────────────────────────────────────────────────────────────────────┤
│ PRÓXIMA ACTIVIDAD   10:00 · Instalación nueva · <colonia>    [Abrir]│
│ HOY (4)             ▢ 10:00 Instalación  ▢ 12:00 Revisión  ▢ 15:30 … │
│ URGENTE / VENCIDO   ⚠ 1 orden vencida · 2 documentos por firmar      │
│ AVISOS              Orden #… reprogramada a 11:00                    │
│ AVANCE SEMANAL      Unidades ██████░░░░ 6/10   Calidad ✔ en rango    │
└────────────────────────────────────────────────────────────────────┘
```

### 6.2 Mi Espacio · Mi Agenda

```
 Mi Agenda   [Día][Semana][Mes][Lista]   Ver: (•) Mi agenda ( ) Mi equipo
 ┌ lun ┬ mar ┬ mié ┬ jue ┬ vie ┬ sáb ┐
 │ 09 ▇ Orden #1     │          │ ▇ Cita │       │ ▇ Instal.│
 │ 11      ▇ Tarea   │ ▇ Reunión│        │ ▇ …   │          │
 └──────────────────────────────────────────────────────────┘
 Leyenda: ▇ orden  ▇ tarea  ▇ cita  ▇ reunión  ▇ garantía    ⚠ = choque de horario
 Clic en evento → abre el registro ORIGINAL (no una copia): ticket, tarea u orden
```

### 6.3 Mi Espacio · Mi Trabajo

```
 Mi Trabajo     [Programadas 5] [En ejecución 1] [Por aprobar 2] [Completadas] [Historial]
 ┌ #  Cliente   Tipo          Cuándo        Evidencias   Estado ───────────┐
 │ …  <cl.>     Instalación   hoy 10-12     6/8          En ejecución  ▶    │
 │ …  <cl.>     Garantía      hoy 15-17     0/4          Asignada     [Aceptar]│
 └───────────────────────────────────────────────────────────────────────┘
```

### 6.4 Mi Espacio · Mis Resultados

```
 Mis Resultados   Período: [Esta semana ▾]  Comparar con: [Semana anterior ▾]
 Producción 6 uds (+1)  │ Calidad dBm en rango 92 %  │ Garantías atribuibles verificadas 0
 Objetivo semanal ████████░░ 80 %   ← (⛔ requiere metas)     Evaluaciones: (sin registros)
```

### 6.5 Mi Espacio · Mis Ventas

```
 Mis Ventas   Registradas 3 │ Validadas 2 │ Rechazadas 0 │ Activaciones 1
 Comisiones: generadas · pendientes · autorizadas · pagadas   (cada una = conteo + total propio)
 ┌ Venta   Cliente   Fecha   Estado de venta   Comisión (estado) ┐
 │ …       <cl.>     …       Validada          Pendiente         │
 └───────────────────────────────────────────────────────────────┘
 [ + Registrar venta ]   Meta de la semana: ░░░░ (⛔ sin metas hoy)
```

### 6.6 Mi Espacio · Mis Incentivos

```
 Mis Incentivos  Semana en curso
 Estimado ····  │ Pendiente de validación ····  │ Autorizado ····  │ Ajustes ····
 Historial de períodos: sem 41 [Autorizado] · sem 40 [Pagado] · …
 Aviso: «Estimado» es una proyección; solo «Autorizado» entra a la pre-nómina.
```

### 6.7 Técnico · Orden del día (móvil, 360 px)

```
┌──────────────────────┐
│ ← Orden #… · En curso│
│ Instalación nueva    │
│ 10:00–12:00 · <col.> │
│ 📍 Cómo llegar        │
│ 📞 Llamar cliente     │
│ ──────────────────── │
│ Evidencias 6/8       │
│ ✔ Fachada ✔ NAP      │
│ ○ Potencia (dBm)     │
│ ○ Firma cliente      │
│ [ + Foto ] [Incidencia]│
│ [   Solicitar cierre  ]│
└──────────────────────┘
```

### 6.8 Supervisor · Mi equipo hoy

```
 MI EQUIPO HOY      Sin asignar 3 │ Atrasadas 2 │ Por validar 5 │ Incidencias 1
 ┌ Persona   Hoy               Carga   Disponibilidad ──────────────────────┐
 │ <t1>      3 órdenes         ███░    libre 14-17                          │
 │ <t2>      1 orden · atrasada ██░░    ocupado hasta 12                     │
 └──────────────────────────────────────────────────────────────────────┘
 [Asignar] [Reprogramar…motivo] [Validar]  ← mismo servicio que usa el técnico para cerrar
```

---

## 7. Información económica por perfil

Principio (contrato §14): **supervisar tareas no concede ver sueldos**. Hoy `talento.view` lo tienen Vendedor, TECNICO y Mostrador y permite leer la ficha básica de otro colaborador, aunque el expediente (sueldo, CURP, NSS, etc.) queda oculto sin `talento.expediente.view` (01e §4.1). La matriz propone el criterio; **no cambia permisos** (frontera dura).

Leyenda: ✔ ve · ◐ ve solo lo propio · ✖ no ve · ⛔ requiere dato/entidad que hoy no existe

| Información | Mostrador | Técnico | Planta externa | Supervisor | Administración / Dirección |
|---|---|---|---|---|---|
| Mi cuenta y desglose semanal (Mi dinero) | ◐ | ◐ | ◐ | ◐ | ◐ propia · ✔ ajena con `talento.liquidation.view` |
| Mis incentivos (estimado/autorizado) | ◐ ⛔ | ◐ ⛔ | ◐ ⛔ | ◐ ⛔ | ✔ con permiso de pre-nómina |
| Mis comisiones por venta | ◐ | ◐ si vende | ◐ si vende | ◐ | ✔ con `talento.comisiones.view` |
| Incentivos / comisiones del equipo | ✖ | ✖ | ✖ | ✖ por defecto · ✔ solo con permiso económico explícito | ✔ |
| Sueldo, retenciones, conceptos legales | ✖ (propios: ver en nómina) | ✖ | ✖ | ✖ | ✔ **solo desde la fuente de nómina**, con `talento.expediente.view`/permiso específico |
| Producción y cumplimiento (unidades, calidad) | ◐ | ◐ | ◐ | ✔ equipo | ✔ |
| Garantías atribuibles verificadas | ◐ propias | ◐ propias | ◐ propias | ✔ equipo | ✔ |
| Penalizaciones | ◐ propias | ◐ propias | ◐ propias | ✔ equipo (confirma) | ✔ |
| Préstamos, fondo, finiquito | ◐ | ◐ | ◐ | ✖ | ✔ con `talento.loans.view`/`settlement.view` |
| Costos de operación, margen, precios | ✖ | ✖ | ✖ | ✖ | ✔ |
| Aprobar el propio bono/orden | ✖ | ✖ | ✖ | ✖ | ✖ (revisor distinto; guard hoy ausente en órdenes, ver P5) |

Reglas de diseño:
1. **Propio vs. ajeno se decide en el backend** por `Actor`/`puedeVer()`, nunca ocultando una pestaña en Vue. Los cuatro endpoints débiles de 01e §4.1 (ficha básica `GET /talento/api/colaboradores/{id}`, `GET /talento/media/{id}`, `practical-evidence/{id}`, `proyectos/{id}`) y los de Scheduling/cortes de caja (§4.2 de 01e) deben cerrarse **antes** de abrir Mi Espacio a todos.
2. La pestaña económica propia se esconde tras una bandeja de permiso (`mi-dinero`, `mis-incentivos`) para poder **apagar lo económico por perfil** sin tocar lo operativo. El permiso base `portal.colaborador` hoy está como directo en 20 cuentas y en solo 2 roles (01e §3.3): moverlo a los roles de campo/oficina es la decisión P3 de 01e.
3. En el Cierre semanal, un supervisor ve **cumplimiento y producción** del equipo, **no** montos, salvo permiso económico explícito.
4. Convención de nombres: seguir `{modulo}_view_{block|card|info}` (#538) para los permisos nuevos, con `depend` hacia el padre en el editor de roles.

---

## 8. Preguntas para Irving

Cada pregunta con opciones, pros/contras y recomendación. El documento queda completo con cualquier respuesta.

**P1. ¿Mi Espacio reordena la ficha (propio vs. administración) o solo se le agregan pestañas?** *(retoma la P1 de 01a)*
- A) Reordenar: grupo «Mi Espacio» (6 bloques) + grupo «Administración» por permiso. *Pros:* separa lo propio de lo ajeno, reduce el riesgo de ver datos de otros en la misma pantalla. *Contras:* hay que mover pestañas y reentrenar al equipo.
- B) Dejar la ficha igual y agregar pestañas. *Pros:* menos trabajo. *Contras:* la mezcla persiste y la ficha pasa de 21 a ~27 pestañas.
- **Recomendación: A**, en dos pasos (primero solo agrupar, después mover contenido).

**P2. ¿Qué cuenta como «orden rápida»: menos clics o menos captura?**
- A) Plantillas por tipo que autocompletan y dejan 7-8 clics (§3.4). *Pros:* la plantilla es dato configurable; elimina capturas externas. *Contras:* el ahorro de clics es pequeño.
- B) Un botón por tipo en la ficha («Sin servicio», «Internet lento») que crea la orden en 2-3 clics con valores por defecto. *Pros:* es lo más «casi predeterminado». *Contras:* mayor riesgo de órdenes mal asignadas si no hay revisión.
- **Recomendación: A**, con B solo para 3-4 tipos de alta frecuencia una vez medida su frecuencia real.

**P3. ¿Las secciones Mis Ventas y Mis Incentivos se muestran a todos o solo a quienes tengan política?** *(ligada a P3 de 01c)*
- A) Solo con política de venta/incentivo asignada. *Pros:* evita mostrar una pantalla vacía y evita comisiones accidentales. *Contras:* un técnico que vende ocasionalmente no la ve hasta que alguien le asigne política.
- B) A todos, con estado vacío. *Pros:* contrato «todos pueden registrar ventas». *Contras:* ruido y preguntas de soporte.
- **Recomendación: A** para Mis Incentivos; **B** para Mis Ventas (registrar es posible sin política; comisionar no).

**P4. ¿Dónde se captura el supervisor y desde cuándo?** *(01a P4)*
- A) Captura mínima previa (38 de 39 vacíos) antes de construir la pantalla de supervisión. *Pros:* la pantalla nace con datos. *Contras:* trabajo administrativo previo.
- B) Derivar por departamento/puesto. *Pros:* sin captura. *Contras:* hoy el departamento es texto libre.
- **Recomendación: A.**

**P5. El botón «Cerrar» del supervisor: ¿usa la compuerta de evidencias del técnico o conserva la vía actual?** *(01d P3)*
- A) Misma compuerta, con excepción por permiso explícito + motivo. *Pros:* cumple §7 del contrato. *Contras:* fricción puntual.
- B) Mantener la vía actual. *Pros:* ninguna. *Contras:* el candado de calidad no es real.
- **Recomendación: A.**

**P6. ¿Qué pantallas se resuelven en la web responsiva y cuáles quedan para la app móvil?**
- A) Las 7 de §5.2 en web responsiva y la APK actual convive hasta la app nueva. *Pros:* un solo camino de negocio; sin duplicar. *Contras:* hay que probar y ajustar cada una a 360 px.
- B) Solo escritorio hasta que exista la app. *Pros:* sin esfuerzo de UI. *Contras:* contradice «la experiencia web debe ser utilizable desde celular».
- **Recomendación: A**, empezando por Inicio y orden del técnico.

**P7. ¿Qué hace la redirección post-login mientras convive lo viejo y lo nuevo?**
- A) Sin cambio: cada perfil entra como hoy y «Mi Espacio» es un botón del menú de usuario, hasta que Irving autorice mudar un perfil. *Pros:* no interrumpe operación. *Contras:* adopción más lenta.
- B) Mudar primero a técnicos (los más aislados del admin). *Pros:* validan el patrón con quien menos depende del menú. *Contras:* requiere que Mi Trabajo y Mi Agenda estén completos.
- **Recomendación: A** (coincide con la estrategia gradual vigente); B como primer candidato.

**P8. ¿Los usuarios con varios roles de puesto ven un solo track o secciones combinadas?** *(6 casos hoy)*
- A) Secciones combinadas por permiso/rol (cada una aplica o no). *Pros:* no oculta trabajo. *Contras:* pantalla más larga.
- B) Selector de «modo» (técnico/vendedor) arriba. *Pros:* limpio. *Contras:* una persona deja de ver sus pendientes del otro modo.
- **Recomendación: A.**
