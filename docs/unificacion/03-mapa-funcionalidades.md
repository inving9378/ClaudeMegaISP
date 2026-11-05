# UNI-03 · Documento 3 del plan maestro: mapa de funcionalidades

Item #688 · épica UNI · 2026-10-24 · **solo documental**: sin cambios de código, migraciones, seeders, permisos, config ni `.env`.
Base: auditorías `01a`–`01e`, consolidado `01-estado-actual.md` y arquitectura `02-arquitectura.md` (esta carpeta). El detalle y la evidencia `archivo:línea` viven ahí; aquí se cita por documento y sección.
Conteos y rutas = dev, solo lectura (`route:list` y `COUNT`). Producción no se consultó. Sin nombres de personas, contactos, montos por persona ni datos de clientes.

## 1. Cómo leer este mapa

| Decisión | Significa | Regla |
|---|---|---|
| **Conservar** | Funciona y se queda tal cual | No se toca salvo para cerrar un hueco de seguridad |
| **Adaptar** | Funciona, pero se le conecta algo o se le corrige un defecto | Cambio aditivo y reversible; la tabla/ruta sigue siendo la oficial |
| **Fusionar** | Dos copias hacen lo mismo; sobrevive una | La otra pasa a «retirar después» o a vista de compatibilidad |
| **Retirar después** | Dejará de usarse, **no ahora** | Lleva condición de retiro y consumidores a mantener (§4). Nunca se borran tablas históricas |

«Dónde vive al final» sigue el mapa de dueños de `02` §2. «Riesgo» = riesgo de la **decisión** (no del estado actual): **B** = bajo (aditivo), **M** = medio (toca tabla o pantalla con consumidores), **A** = alto (dinero, permisos, autenticación o dato histórico: requiere aprobación de Irving, nivel B/C del circuito). El «item» es el item existente del roadmap, si lo hay; «—» = no existe item y se creará en el plan de fases (Documento 6).

Estados de la auditoría: **F** funciona · **I** incompleto · **D** duplicado · **R** roto · **N** no verificado.

---

## 2. Mapa por módulo

### 2.1 Talento (`app/Modules/Addons/Talento`, 356 rutas, 63 tablas `talento_*`)

| Funcionalidad | Estado | Decisión | Dónde vive al final | Rutas / pantallas | Riesgo | Item |
|---|---|---|---|---|---|---|
| Identidad colaborador↔usuario (`talento_colaboradores.user_id`, 39/39) | F | Conservar | Talento (`02` §2) | `Actor` (`Support/Actor.php`) | B | — |
| `Actor` (identidad por request, agnóstico de guard) | F | Adaptar: añadir `capacidades()` y `departamento()` | Talento | todas las rutas `portal.colaborador` y Sanctum | B | — |
| Ficha propia de 21 pestañas (`TalentoColaboradorFicha.vue`) | F, mezcla propio/administrativo | Adaptar: base de **Mi Espacio** (vista que concentra solo lo propio) | Talento | `/talento/colaboradores/{id}`, `/talento/portal/*` (28) | M | #687 (UX) |
| Pestañas propias: Mi día, Mi dinero, Mis prospectos, Mi equipo, Documentos | F | Conservar y reordenar bajo Mi Espacio | Talento | `/talento/portal/{asistencia/hoy,ots/hoy,dinero/*,prospectos,mi-equipo-progreso,documentos}` | B | — |
| Pestañas de administración embebidas (Compensación, Liquidaciones, Campo, Cajas, Rutas, Penalizaciones, Finiquito…) | F | Conservar; **separar visualmente** de lo propio | Talento (admin) | mismas rutas, permisos `talento.*` | M | — |
| Departamento (texto libre + `role_departments`, 4 filas) | I | Adaptar: catálogo `talento_departamentos` | Talento | sin pantalla hoy | M | — |
| Capacidad profesional («puede vender», «puede validar») | no existe | Crear sobre `Actor` | Talento (`talento_capacidades`) | sin pantalla hoy | M | — |
| Cuadrilla / equipo | no existe | Crear; `supervisor_id` sigue para el árbol | Talento | sin pantalla hoy | M | — |
| Niveles / puestos (`talento_levels`, `puestos`, `level_assignments`) | residual, sin UI desde 21-oct | Retirar después | Puesto = rol Spatie | sin rutas activas | B | — |
| Orden de campo: OT + evidencias + dBm + firma + incidencia | F | Conservar | Scheduling (`tasks` + `talento_type_id`) | `/talento/ordenes`, `/talento/campo`, `/talento/api/ots/*` | B | — |
| Validación de OT (`validarAdmin`) | F, sin guard de auto-validación | Adaptar: no-autoaprobación en el servicio | Talento | `POST /talento/api/ordenes/{id}/validate` | A (aprobación/pago) | — |
| `talento_work_orders` histórica (208 cerradas) | histórico | Conservar como solo lectura | Talento | lectura en `OrdenTrabajoUnifiedService` | B | — |
| Garantía a 6 meses (`WarrantyWindowService`, 0 ventanas) | R | Adaptar: «evento + causa + revisión», sin efecto económico | Talento (`talento_garantia_eventos`) | `/talento/api/garantias*` | A (contradice §9; toca puntos) | — |
| Asistencia y mapa en vivo | F (4 registros) | Conservar | Talento | `/talento/asistencia`, `TalentoMapaVivo.vue` | B | — |
| Custodia de material (lectura) | F | Conservar | Inventario (`inventory_item_stocks`) | `/talento/portal/material` | B | #218 |
| Planta externa (proyectos, rutas, cajas, reparto `quantity_share`) | N con datos (0 filas) | Conservar y **probar con datos** antes de decidir | Talento | `/talento/proyectos`, `/rutas`, `/cajas` | M | — |
| Motor de pago: `PayWeek`, `countBillableUnits`, bonos de salud/proyecto, ledger | F | Conservar (punto único del conteo) | Talento | `/talento/liquidaciones`, `/portal/dinero/*` | A | — |
| Ledger `talento_ledger_entries` (12) sin estado ni idempotencia | I | Adaptar: se mantiene para lo ya pagado; el libro nuevo corre en paralelo | Talento (`talento_incentivo_movimientos`) | sin pantalla nueva aún | A | — |
| Liquidación `draft`/`closed` | I | Adaptar: estados de cierre (`talento_cierres`) | Talento | `/talento/liquidaciones`, `calcular`, `cerrar` | A | — |
| Penalizaciones y apelaciones | F | Conservar | Talento | `/talento/penalizaciones` | B | — |
| Reglas semanales de desempeño (7; 2 DEMO activas) | F | Conservar; desactivar DEMO tras revisión de Irving | Talento | `talento:evaluar-reglas-semanales` (dom 04:00) | M | — |
| Fondos, préstamos, finiquitos | F (0–1 filas) | Conservar | Talento | `/talento/fondos`, `/loans`, `/settlements` | A | — |
| Metas, objetivos, evaluaciones | no existe | Crear | Talento (`talento_metas`) | sin pantalla hoy | M | #121 condiciona |
| Expediente y documentos con firma | F | Conservar | Talento | `/talento/portal/documentos` | B | — |
| Academia y certificaciones | F (0 exámenes) | Conservar | Talento | `/talento/courses`, `/exams` | B | — |
| API de la app: login Sanctum, `/talento/api/*` (58) | F, tokens sin vencimiento | Adaptar: vigencia, `throttle`, `estado=activo`; sobre `/v1` solo para lo nuevo | Core/Auth + Talento | `POST /talento/api/auth/login` | A (autenticación) | — |
| Portal PWA de técnico | retirado; queda `sw.js` de baja | Retirar después (ya retirado) | Ficha propia | `PortalTecnicoController` (sesión) | B | — |
| `TalentoMobileEquipoController` (Sanctum) y `PortalTecnicoController` (sesión) | D | Fusionar contratos | Servicios compartidos | `/talento/api/portal/*` y `/talento/portal/*` | M | — |

### 2.2 Tareas (`tasks`, 1,666 filas)

| Funcionalidad | Estado | Decisión | Dónde vive al final | Rutas / pantallas | Riesgo | Item |
|---|---|---|---|---|---|---|
| Tabla `tasks` + `task_user` (donde se escribe todo lo nuevo) | F/D | Conservar como orden de campo vigente | Scheduling | `/scheduling/task` | A (la lee el pago) | — |
| Fechas `start_date`/`start_time`/`end_time` como `varchar` (dos verdades) | R | Adaptar (Fase 0): `datetime` real con respaldo y reversa, o normalizar al leer | Scheduling | `/scheduling/task/calendar`, Mi día | A (tabla compartida con pago) | — |
| `tasks.assigned_to` (legado, 24 filas) | D | Retirar después (`task_user` manda) | Scheduling | formulario de tarea | B | — |
| Notas de seguimiento (`observation_tasks`, 2,249) | F | Conservar | Scheduling | ficha de tarea | B | — |
| Estados `ToDo/InProgress/Done/Archivado` | F | Conservar; los 13 del contrato son **vista**, no columna | Scheduling | listado de tareas | B | — |
| Crear tarea desde solicitud (`CrearTareaModal.vue`) | F (2 usos) | Adaptar: `ticket_id` obligatorio en «Problema» | Scheduling | `POST /scheduling/task/add` | M | — |
| Tareas sin asignado (50) | I | Adaptar: estado derivado «pendiente de asignación» | Scheduling | listado | B | — |

### 2.3 Scheduling (calendario)

| Funcionalidad | Estado | Decisión | Dónde vive al final | Rutas / pantallas | Riesgo | Item |
|---|---|---|---|---|---|---|
| Calendario `QCalendar.vue` (FullCalendar), 30 rutas | I/R | Fusionar con **Agenda unificada**: la UI se conserva, la fuente de datos cambia a `AgendaService` | Agenda (servicio) sobre Scheduling | `/scheduling/task/calendar` | M | — |
| Carga sin ventana de fechas (`TaskService.php:16-20`) | I | Adaptar: consulta por ventana | Scheduling | mismo | B | — |
| Defectos de `getStartAndEndTime` e `initialView` | R | Adaptar (corregir) | Scheduling | mismo | B | — |
| Recurrencia, recordatorios, choques | no existe | Crear (`agenda_recordatorios`, choques en `AgendaService`) | Agenda | sin pantalla hoy | M | — |
| Filtros del calendario (`calendar_filter_*`) | F | Conservar | Scheduling | calendario | B | — |
| Acceso por id (19 rutas) sin control por fila | I | Adaptar: `Actor::puedeVer` | Scheduling | `/scheduling/*/{id}` | A (acceso) | — |
| Plantillas de tareas | F | Conservar | Scheduling | `/scheduling/task-templates` | B | — |

### 2.4 Vendedores / Ventas (`Vendedores` 70 rutas, `Ventas` 15)

| Funcionalidad | Estado | Decisión | Dónde vive al final | Rutas / pantallas | Riesgo | Item |
|---|---|---|---|---|---|---|
| `sellers` (32) y tipos/estatus | F/D | Adaptar: «vendedor» pasa a **capacidad** del colaborador | Talento (`talento_capacidades`) + Vendedores | `/vendedores/dashboard`, `/vendedores/ventas*` | M | — |
| Atribución `client_main_information.seller_id` (= `users.id`, 4,439) | F/R | Adaptar: doble escritura `colaborador_id`; luego `ventas_atribuciones` versionada | Vendedores/Clientes → Talento | ficha de cliente, altas | A | #9990878 (doble escritura) |
| `colaborador_id` en paralelo (0 filas, flags OFF) | I | Conservar el puente; encender doble escritura | Talento | `identidad:verificar-consistencia` | M | #9990878 |
| Cortes de caja y pagos semanales (sueldo fijo + adicional, 72 filas) | F | Conservar | Vendedores | `/vendedores/cortes*`, pagos | A | — |
| Reglas de comisión legacy (`commissions_rules`, 8; `payment_by_rule*`) | F, no alcanza A–D | Adaptar: se conservan como origen y como histórico | Talento (política con alcance y vigencia) | `/configuracion/reglas-comisiones*` | A | #121 |
| Listeners de comisión por evento (cuerpo comentado) | R (apagado) | Retirar después (no reactivar) | — | `EventServiceProvider:65-69` | A | #121 |
| `commissions` (0) y `commissions_details` (566,780 huérfanas) | histórico | Conservar como **legado de solo lectura** | Vendedores (archivo) | `ComissionController::getCommissionsBySeller` (vacío hoy) | A | #9990471 |
| Motor nuevo `ventas_*` (`ventas_ventas`, `…esquemas_comision`, `…maduraciones`, `…ledger_movimientos`: 0 filas) | I | Fusionar hacia la política de Talento: el esqueleto se aprovecha, no se construye otro | Talento | sin pantalla | A | — |
| Prospectos: CRM (2,262) frente a `ventas_prospectos` (58) | D | Fusionar: sobrevive CRM (§3) | CRM | `/vendedores/prospectos`, `/crm` | M | — |
| Custodia de prospectos (`/ventas/custodias`, 0 filas) | I | Conservar; darle permiso Spatie propio | Ventas | `/ventas/custodias` | M | — |
| Rutas `/sellers/prospectos` y `/sellers/statistics` (404) | R | Retirar después: quitar del menú viejo (las reales son `/vendedores/*`) | Vendedores | menú legado | B | #585 |
| Embajadores: `referrals` (101), comisiones por factura pagada | F | Conservar; regla de exclusión con vendedor por decidir (P4) | Embajadores | `/embajadores/*` | A | — |
| Metas | no existe | Crear | Talento (`talento_metas`) | sin pantalla | M | — |
| `payments_sellers`, `discounts_sales`, `sales`, `prospects` (0 filas, sin consumidores) | muertas | Retirar después | — | sin rutas | B | #9990471 |

### 2.5 CRM (`/crm`, `CrmEmpresarial`)

| Funcionalidad | Estado | Decisión | Dónde vive al final | Rutas / pantallas | Riesgo | Item |
|---|---|---|---|---|---|---|
| Prospectos `crm_main_information` (2,247) / `crm_lead_information` (2,262) | F | Conservar: **sobrevive** como motor único de prospectos | CRM | `/crm`, `/crm/listar` | B | — |
| Fecha y técnico de instalación del prospecto (`instalation_date` 67, `crm_techical_user_id` 15, 0 ligados a tarea) | I | Adaptar: la agenda las **lee** como cuarta fuente (decisión por defecto) | CRM → Agenda | ficha de prospecto | M | — |
| Botón «Asignar trabajo» en prospecto | F | Conservar | Talento (`store`) | `InformationCrmCrud.vue:18` | B | — |
| CRM empresarial (expediente, propuestas, contratos) | N | Conservar; fuera del alcance de esta épica | CrmEmpresarial | `/crm-empresarial/*` | B | #554 |
| Conversión prospecto→cliente | N | Conservar; crear orden automática queda como mejora posterior (P9) | CRM/Clientes | flujo de conversión | M | — |

### 2.6 Clientes (`/cliente/*`, 182 rutas, `clients` 5,857)

| Funcionalidad | Estado | Decisión | Dónde vive al final | Rutas / pantallas | Riesgo | Item |
|---|---|---|---|---|---|---|
| Ficha de cliente y listado | F/N | Conservar; **no se auditó entera** | Clientes | `/cliente/listar`, `ClientCrud.vue` | B | — |
| Botón «Asignar trabajo» en ficha | F | Adaptar: pasa a «Crear orden» con plantilla | Talento (`OrdenService`) | `InformationClientCrud.vue:52` | M | — |
| `seller_id` y `colaborador_id` | ver 2.4 | ver 2.4 | ver 2.4 | ficha | A | #9990878 |
| Pestaña de módulo en ficha (`client_tab`) | F | Conservar; infraestructura para Mi Espacio del cliente | Clientes | `ClientCrud.vue` | B | — |
| Cuentas espejo de clientes (`client` + `portal`) | F, saneadas en dev | Conservar; replicar saneo en prod (pendiente) | Core/Auth | login | A | (CLAUDE.md, Fase 2.5) |
| Mostrador (buscar cliente, servicios, pagos) | F | Conservar | Clientes/Finanzas | `/cliente/listar`, `/finanzas/captura-pago` | B | — |

### 2.7 Tickets (`/tickets`, 44 rutas, 899 tickets)

| Funcionalidad | Estado | Decisión | Dónde vive al final | Rutas / pantallas | Riesgo | Item |
|---|---|---|---|---|---|---|
| Mesa de ayuda: bandejas, hilos (1,470 mensajes), KPIs | F | Conservar | Tickets | `/tickets` | B | — |
| Ticket sin fecha de atención ni llave a orden | I | Adaptar: relación `ticket_id`↔tarea (obligatoria en «Problema»); el ticket **no** entra a la agenda como fila propia | Tickets + Scheduling | `CrearTareaModal.vue` | M | — |
| Estados `Nuevo/Cerrado/Reciclado` | F | Conservar; no se mapea a los 13 estados | Tickets | listado | B | — |
| Campo `customer_lead` (cliente) frente a la documentación («responsable») | D (doc vs modelo) | Adaptar: corregir `docs/modulos/tickets.md` | Tickets | — | B | — |
| Tickets «Nuevo» sin responsable (48 de 118) | I | Adaptar: vista de pendientes de asignación | Tickets/Seguimiento | listado | B | — |

### 2.8 Órdenes de trabajo

| Funcionalidad | Estado | Decisión | Dónde vive al final | Rutas / pantallas | Riesgo | Item |
|---|---|---|---|---|---|---|
| «Asignar trabajo» (`AsignarTrabajo.vue` → `POST /talento/api/ordenes`) | F | Adaptar: amplía con plantillas por solicitud, precarga, franja, choques | Talento → `OrdenService` | ficha de cliente/prospecto, `/talento/ordenes` | M | — |
| Plantillas por tipo de solicitud (hoy 5 tipos fijos; el contrato pide 13) | I | Crear `orden_plantillas` sobre `talento_work_order_types` | Talento | sin pantalla | M | — |
| Seguimiento: estados aceptada / en camino / pausada / observada | I | Crear `orden_estado_historial` (los 13 estados son **vista** sobre el estado de origen) | Talento | `/talento/ordenes` | M | — |
| Reprogramación y cancelación con motivo | no existe | Crear (`agenda_reprogramaciones`) | Agenda | sin pantalla | M | — |
| Cierre por admin (`PUT /ordenes/{id}/status`) evade evidencias | R | Adaptar: pasa por el único servicio de cierre, con permiso y motivo | Talento | `cambiarEstadoAdmin` | A (calidad y pago) | — |
| Evidencias obligatorias + dBm + firma (ruta del técnico) | F | Conservar (gate único) | Talento | `/talento/api/ots/*` | B | — |
| Historial de cambios | R (`activity_log` sin filas de tarea/orden/ticket; `DB::table()->update`) | Adaptar: `AuditoriaService`, sin updates masivos de estado | Core | todas | M | — |
| Varios técnicos / crédito compartido | I | Adaptar: `quantity_share` (partes iguales editables) | Talento | `countBillableUnits` | A (toca el conteo del pago) | — |
| OT histórica `talento_work_orders` | D (doble fuente) | Fusionar: lectura unificada; la vieja es **solo histórico** | Talento (solo lectura) | `OrdenTrabajoUnifiedService` | B | — |
| Vista de atrasadas / sin asignar | no existe | Crear sobre el seguimiento | Talento | sin pantalla | B | — |

### 2.9 Instalaciones

| Funcionalidad | Estado | Decisión | Dónde vive al final | Rutas / pantallas | Riesgo | Item |
|---|---|---|---|---|---|---|
| `cut_installations` (175, alta comercial) | F, sin llave a la orden | Conservar; añadir referencia a la orden (aditiva) | Cobranza/Instalaciones | cortes de instalación | M | — |
| Instalación nueva (OT tipo 1, 9 pts, `inicia_garantia`) | F | Conservar | Talento | `/talento/ordenes` | B | — |
| Fecha de aceptación técnica (`accepted_at`, 0 filas) | R (no se usa) | Adaptar: usar `accept` de verdad; respaldo a la validación | Talento | `FieldFlowService.php:83-112` | A | — |
| Encuestas de instalación (`installation_surveys`, 0) | F, sin uso | Conservar | Talento | `talento:cerrar-encuestas` | B | — |
| Garantías (ver 2.1) | R | Adaptar | Talento | `/talento/garantias` | A | — |
| Activación de ONU (`work_order_activations`, 0) | N con datos | Conservar | Talento/GestionRed | flujo del técnico | B | — |

### 2.10 Mantenimiento

| Funcionalidad | Estado | Decisión | Dónde vive al final | Rutas / pantallas | Riesgo | Item |
|---|---|---|---|---|---|---|
| Mantenimiento de red / preventivo programado | **N**: no hay objeto propio ni flujo localizado | No decidir: se modela como **tipo de plantilla de orden** («Mantenimiento preventivo», «Visita técnica») | Talento (`orden_plantillas`) | — | B | — |
| Mantenimiento de vehículos (`fleet_maintenances`, 2; `/flotas/api/mantenimientos`) | F | Conservar: es de Flotas, otro dominio | Flotas | `/flotas/mantenimientos` | B | #60 |
| Mantenimiento de planta externa | N | Conservar dentro de planta externa | Talento | proyectos y rutas | M | — |

### 2.11 Facturación

| Funcionalidad | Estado | Decisión | Dónde vive al final | Rutas / pantallas | Riesgo | Item |
|---|---|---|---|---|---|---|
| Facturas y pagos (`invoices` 101,519; `payments` 139,015), captura manual, conciliación | F/N | Conservar; **no se tocan en esta épica** | Finanzas/Clientes | `/finanzas/*` (75), `/facturacion/cfdi` | A | — |
| Estado de venta validada/cancelada/no elegible | no existe | Crear en `ventas_atribuciones`, **sin** tocar las facturas | Talento/Vendedores | sin pantalla | A | — |
| Descuentos y pagos al vendedor (`discounts_sales`, `payments_sellers`: 0) | muertas | Retirar después | — | — | B | — |
| Pre-nómina y cierre semanal | I | Crear sobre `PayWeek`; sueldos solo desde nómina externa | Talento | sin pantalla | A | — |
| Atribución de cobros `payments.add_by` (bot MEGAISP) | F | Conservar | Payments | `PaymentApplicationService` | A | #272 |

### 2.12 Inventario (`/inventory/*`, módulo Inventario)

| Funcionalidad | Estado | Decisión | Dónde vive al final | Rutas / pantallas | Riesgo | Item |
|---|---|---|---|---|---|---|
| Ítems, tipos (402), stock (1,567), movimientos, almacenes | F | Conservar | Inventario | `/inventory/inventory_item`, `_type`, `_stock`, `_movement`, `_store` | B | #169–#171, #471 |
| Categorías herramienta/material/equipo_cliente/equipo_red | F | Conservar | Inventario | tipos de artículo | B | #218, #436, #583 |
| Custodia por usuario (`inventory_item_stocks` polimórfico a `users`) | F (lectura) | Conservar | Inventario | `/talento/portal/material` | B | — |
| Materiales usados por orden | I (solo en la OT histórica) | Adaptar: captura en el flujo de tarea, vía `inventory_movements` | Inventario + Talento | flujo del técnico | M | — |
| Vínculo custodia→adeudo→nómina | no existe | **Diferir**: es decisión de dinero | — | — | A | (Fase 0 portal) |
| «Inventario» de DocumentaciónCorporativa (`/documentacion-corporativa/api/inventario/{recurso}`) | F | Conservar: es otro dominio (activos de la empresa), no confundir | DocumentacionCorporativa | esa ruta | B | #664 |

### 2.13 Mapas y Planta Externa (`Mapas` 131 rutas, `MapaRed` 123, `GestionRed`)

| Funcionalidad | Estado | Decisión | Dónde vive al final | Rutas / pantallas | Riesgo | Item |
|---|---|---|---|---|---|---|
| Módulo `Mapas` (red, postes, clientes en el mapa) | F | Conservar | Mapas | `/mapas` | B | — |
| Módulo `MapaRed` (cables 250, puertos 10,987, hilos 12,320; capas 7,041) | F/I | Conservar; es el mapa compartido | MapaRed | `/mapa-red/*` | B | #936 (épica MR) |
| Enlaces de servicio (`mapared_enlaces_servicio`) | **0 filas** hoy | Conservar; bloquea cualquier cruce con la orden | MapaRed | `EnlacesServicioController` | M | #9990496, #9990742 |
| Duplicidad `Mapas` frente a `MapaRed` | D | Fusionar a largo plazo en `MapaRed` (regla «un solo módulo provee mapas») | MapaRed | `/mapas` y `/mapa-red` | M | — |
| Planta externa de Talento (proyectos, rutas, reportes) | N con datos (0 filas) | Conservar; prueba con datos reales antes de exponer | Talento | `/talento/proyectos`, `/rutas` | M | — |
| Enlace orden de trabajo ↔ caja/NAP (`tasks.caja_id`, `olt_onu_id`) | I | Adaptar: la orden referencia el elemento del mapa, no lo duplica | Talento ↔ MapaRed | formulario de orden | M | — |
| Mapa de flota y geocercas | F | Conservar: otro dominio | Flotas | `/flotas/mapa` | B | #62 |

### 2.14 Transversales que condicionan el mapa

| Funcionalidad | Estado | Decisión | Dónde vive al final | Rutas / pantallas | Riesgo | Item |
|---|---|---|---|---|---|---|
| `notifications` + `StandardNotification` (14,943) | F | Conservar: **sobrevive** | Core/Notifications | campana | B | — |
| `task_notifications` (3,661), `general_notifications`, `fleet_notification_*` | D | Fusionar hacia `notifications`; `task_notifications` queda como vista de compatibilidad | Core/Notifications | avisos de tarea | M | — |
| Tokens push: `push_tokens` (0), `talento_device_tokens`, `fleet_driver_push_tokens` | D | Fusionar en `push_tokens` | Core/Notifications | `POST /talento/api/devices/token` | M | — |
| Auditoría: `activity_log` + 3 historiales por dominio | I | Fusionar el acceso en `AuditoriaService`; los historiales se conservan | Core | — | M | — |
| Roles `Super Administrador` frente a `super-administrator` | D | Fusionar (Fase 4 de permisos) | Core/Auth | administración de roles | A (permisos) | (CLAUDE.md) |
| `portal.colaborador` como permiso directo en 20 cuentas | I | Adaptar: moverlo al rol (migración aditiva) | Core/Auth | middleware `can:` | A (permisos) | (CLAUDE.md, Fase 3b) |

---

## 3. Duplicidades y qué copia sobrevive

| # | Duplicidad | Sobrevive | Razón | La otra pasa a |
|---|---|---|---|---|
| 1 | Orden: `tickets` / `tasks` / `talento_work_orders` / `cut_installations` | `tasks` con `talento_type_id` | Es donde `store()` ya escribe; las demás son otro objeto (ticket = solicitud, instalación = alta comercial) o histórico | Relaciones por `ticket_id`/referencia; OT histórica = solo lectura |
| 2 | Fecha de una actividad: `start_date` frente a `start_time`/`end_time` | `datetime` real derivado de `start_time`/`end_time` | El calendario ya escribe ahí; Mi día debe leer lo mismo | `start_date` derivado |
| 3 | Fuente de agenda: tareas / OT histórica / CRM / tickets | Índice de lectura (`AgendaService`) | No hay copias que desincronizar (`02` §4.1) | Cada fuente sigue escribiendo su dato |
| 4 | Motor de comisión: legacy frente a `ventas_*` | Política en Talento, con el legacy como origen e histórico | Cumple modelos A–D con vigencia y versión; el legacy no los soporta | `payment_by_rule*` y listeners: histórico; `ventas_*` se aprovecha como esqueleto |
| 5 | Vendedor frente a colaborador (`sellers` / `talento_colaboradores`) | `talento_colaboradores` | Una cuenta por colaborador; 25 de 32 vendedores ya coinciden | `sellers` pasa a capacidad |
| 6 | Atribución: `seller_id` frente a `colaborador_id` | `colaborador_id` (vía `ventas_atribuciones`) | Persona estable, no `users.id` ambiguo con colisión numérica | `seller_id` en doble escritura hasta cortar lectura |
| 7 | Prospectos: CRM frente a `ventas_prospectos` | CRM | 2,262 frente a 58; ya integra al cliente y a la conversión | `ventas_prospectos`: consolidar con el comando existente y retirar después |
| 8 | Comisión de embajador frente a la de vendedor sobre el mismo cliente | Decisión pendiente (P4) | Es regla de dinero, no técnica | — |
| 9 | Notificaciones: 4 almacenes y 3 de tokens | `notifications` + `push_tokens` | Ya tiene campana y lectura; el resto son casos particulares | Vistas de compatibilidad |
| 10 | App/Portal: `TalentoMobileEquipoController` (Sanctum) frente a `PortalTecnicoController` (sesión) | Servicios compartidos con un contrato por cliente (web y móvil) | La lógica ya es la misma; solo cambia el envoltorio | El de sesión sigue sirviendo a la ficha |
| 11 | Mapas: `Mapas` frente a `MapaRed` | `MapaRed` | Regla de servicio compartido «un solo mapa»; es el módulo vivo de la épica MR | `Mapas` conservado hasta que `MapaRed` cubra sus funciones |
| 12 | Roles ES/EN de super administrador | `super-administrator` | Es el que usa el bypass por nombre | `Super Administrador` |

---

## 4. Lo que se marca «retirar después»: condición y consumidores a mantener

**Regla común:** retirar **nunca borra tablas históricas**. Retirar = quitar la pantalla o ruta, dejar de escribir y marcar la tabla como legado de solo lectura. Cada retiro necesita (1) estar migrado y conciliado, (2) cero consumidores activos medidos, (3) aprobación de Irving si toca dinero, permisos o autenticación.

| Qué se retira | Condición para retirarlo | Consumidores que hay que mantener | Qué queda |
|---|---|---|---|
| `tasks.assigned_to` (legado) | `task_user` cubre las 24 filas y ningún formulario ni reporte lo lee | Formulario de tarea (`TaskController`), selector rol→persona de 2026-10-22 | Columna sin escribir |
| `start_date` como fuente | Mi día, calendario y pago leen el `datetime` derivado y conciliado | `PortalTecnicoController` (Mi día), `LiquidationService::countBillableUnits` (pago), WarRoom, MegaFamilia (la tabla es compartida) | Columna derivada |
| Listeners `CalculateClient/ProspectCommission` | La política de Talento calcula modelos A–D y fue conciliada contra el histórico | `EventServiceProvider:65-69` (el evento se sigue disparando) | Código comentado eliminado; eventos intactos |
| `commissions_details` (566,780) | **No se retira**: legado de solo lectura | `ComissionController` (hoy devuelve vacío), reportes de comisiones | Tabla intacta |
| `/sellers/prospectos`, `/sellers/statistics` | Sin enlaces en el menú legado | Menú antiguo de vendedores | 404 desaparece del menú |
| `ventas_prospectos` | Prospectos de CRM consolidados con el comando existente | `/ventas/custodias`, `TalentoVentasController` | Tabla como legado |
| `payments_sellers`, `discounts_sales`, `sales`, `prospects` (0 filas) | Cero consumidores confirmados por grep en `app/`, `resources/`, `routes/` y `config/` | Ninguno (item #9990471) | Tabla vacía, sin modelo |
| `talento_levels`, `puestos`, `level_assignments` (+ `level_id`/`puesto_id`) | Fase de retiro posterior a Mi Espacio; ningún reporte las lee | `TalentoColaborador::level()` (relación añadida en item #222), `DashboardService` | Tablas intactas |
| `task_notifications` como almacén | Toda notificación nueva nace en `notifications`; la vista de compatibilidad cubre lo viejo | Pantalla de avisos de tarea, `Task::sendNotifications` | Vista de lectura |
| Tokens push duplicados (`talento_device_tokens`, `fleet_driver_push_tokens`) | Emisor push existente y migración de tokens verificada | App de Talento (`/talento/api/devices/token`), app del conductor de Flotas | `push_tokens` |
| `PortalTecnicoController` (ruta de sesión) | La ficha propia consume los servicios directamente | `/talento/portal/*` (28 rutas) usadas por la ficha | Servicios compartidos |
| `talento_work_orders` | **No se retira**: queda de solo lectura; se reevalúa solo si Irving decide migrar a `tasks` (P7 de `01-estado-actual`) | `OrdenTrabajoUnifiedService`, `countBillableUnits` (suma ambas fuentes), pantalla `/talento/ordenes` | Tabla intacta |
| Módulo `Mapas` | `MapaRed` cubre postes, equipos activos y clientes en el mapa | `/mapas/*` (131 rutas), permisos `maps_*` | Se conserva hasta cobertura total |
| Rol `Super Administrador` (EN/ES duplicado) | Reasignado y conciliado con Irving (permiso) | Usuarios con ese rol; bypass por nombre en `CheckRoutePermission` | Rol único |
| Apps/APK actual (`/talento/api/*` sin `/v1`) | **No se retira**: queda congelada | APK de Talento en campo | Contrato congelado |

---

## 5. Funcionalidades que el contrato pide y hoy no existen

No son retiros ni fusiones; son huecos. Cada una se construye **sobre** lo existente (columna «sobre»), no como sistema aparte.

| Funcionalidad | Sobre qué se construye | Dueño | Dependencia |
|---|---|---|---|
| Agenda unificada (`AgendaService`, adaptadores) | `tasks`, OT histórica, CRM | Agenda | Fase 0 de fechas (P1 de `02`) |
| Recordatorios, reprogramaciones, participantes extra | Tablas auxiliares con `fuente + id_origen` | Agenda | Agenda |
| Plantillas de orden (13 tipos) | `talento_work_order_types` + matriz de evidencias | Talento | — |
| Historial de estado de orden | `orden_estado_historial` | Talento | — |
| Catálogos de departamento y capacidad, cuadrillas | `role_departments`, `supervisor_id` | Talento | Captura de supervisores (38 de 39 vacíos) |
| Metas y evaluaciones | — | Talento | #121 |
| Atribución versionada de ventas | `colaborador_id` + `seller_id` | Talento | Doble escritura encendida |
| Libro de incentivos con estados e idempotencia | Ledger vigente | Talento | #121; aprobación de Irving |
| Cierre semanal con aprobación y bloqueo | `PayWeek`, `simular`, liquidación | Talento | Libro de incentivos |
| Evento de garantía con causa | `WarrantyWindowService` | Talento | Decisión de Irving (P5) |
| Notificaciones por tipo de evento (13) | `notifications` | Core/Notifications | — |
| Auditoría uniforme | `activity_log` | Core | — |
| Contrato de API `/v1` solo para lo nuevo | Sanctum + `PasswordService` | Core/Auth | Endurecer tokens (P7) |

---

## 6. Qué no se pudo verificar

- **Producción**: ningún conteo ni ruta; ids y volúmenes divergen de dev.
- **Clientes, CRM y Facturación** no se auditaron enteros: solo se mapean los campos y pantallas que se cruzan con el contrato. Su decisión «conservar» significa «no se tocan en esta épica», no «están sanos».
- **Mantenimiento**: no hay objeto de orden ni flujo preventivo localizado en el código; no se descarta que exista en producción o fuera de los módulos revisados.
- **Mapas y planta externa**: `talento_projects`, rutas y reportes en 0 filas; `mapared_enlaces_servicio` en 0 filas (medido hoy, mientras que puertos e hilos ya tienen datos). Lo «construido» no equivale a «probado».
- Si el calendario de Scheduling filtra por usuario en servidor y el control por fila de cortes de caja (`01e` §9).
- Consumidores externos de las rutas a retirar (reportes fuera de `app/`, integraciones en producción): la condición «cero consumidores» exige medirlos antes de cada retiro.
- Número de clics del flujo de órdenes (`01d` §3): requiere sesión de navegador.

---

## 7. Preguntas para Irving

Las marcadas ⚠ tocan dinero, permisos, autenticación o dato histórico. Las que no se contesten toman la recomendación como supuesto de diseño. Se evita repetir las 20 de `01-estado-actual` §6 y las 10 de `02` §9; aquí van solo las que **este mapa** necesita para decidir conservar/adaptar/fusionar/retirar.

**Q1 · ¿`Mapas` se fusiona con `MapaRed` o conviven?**
- A. Fusionar a largo plazo en `MapaRed` y mantener `Mapas` hasta cubrir sus funciones. *Pro:* cumple la regla de un solo mapa. *Contra:* trabajo de migración de postes y equipos activos.
- B. Convivir: `Mapas` para postes y equipos, `MapaRed` para fibra y enlaces. *Pro:* cero trabajo. *Contra:* dos mapas y dos permisos.
- **Recomiendo A**, sin fecha: no es parte de la unificación de personas y órdenes.

**Q2 · ⚠ Comisiones legacy: ¿se retiran los listeners comentados o se conservan comentados?**
- A. Retirar el código comentado una vez conciliada la política nueva. *Pro:* menos confusión. *Contra:* se pierde la referencia de lo que calculaba.
- B. Dejarlos comentados indefinidamente. *Pro:* referencia. *Contra:* cualquiera puede reactivarlos por accidente y duplicar el cálculo.
- **Recomiendo A**, después de conciliar y con el histórico respaldado.

**Q3 · ¿Mantenimiento tiene un flujo propio o es un tipo de plantilla de orden?**
- A. Plantilla de orden («Mantenimiento preventivo», «Visita técnica»), sin objeto nuevo. *Pro:* no crea otro sistema de órdenes (contrato §6). *Contra:* sin programación recurrente hasta que la agenda tenga recurrencia.
- B. Objeto propio con calendario de mantenimiento. *Pro:* preventivo planificado. *Contra:* segundo sistema de órdenes.
- **Recomiendo A**; si se necesita preventivo recurrente, se agrega como recurrencia de la agenda.

**Q4 · ⚠ ¿Embajador y vendedor sobre el mismo cliente se acumulan o son excluyentes?** (El mapa lo necesita para decidir si Embajadores se queda separado.)
- A. Excluyentes, con excepción por campaña. *Pro:* evita doble comisión. *Contra:* regla nueva sobre dinero.
- B. Se acumulan. *Pro:* cero cambio. *Contra:* posible doble pago.
- **Recomiendo A.**

**Q5 · ¿Se retiran `ventas_prospectos` y el módulo `Ventas` o se conserva su custodia?**
- A. Consolidar en CRM con el comando existente y conservar `/ventas/custodias` hasta tener uso real. *Pro:* un solo motor de prospectos. *Contra:* la custodia queda a medias.
- B. Retirar `Ventas` completo. *Pro:* limpio. *Contra:* se pierde un diseño de custodia aún sin ejercitar.
- **Recomiendo A.**

**Q6 · ⚠ ¿Se mantienen congeladas las rutas de la APK actual hasta la app nueva?**
- A. Sí, congelar `/talento/api/*` y abrir `/v1` solo para lo nuevo. *Pro:* no rompe la APK en campo. *Contra:* conviven dos formatos.
- B. Migrar todo y forzar actualización. *Pro:* un contrato. *Contra:* riesgo en operación de campo.
- **Recomiendo A.** Coincide con `02` P7.

**Q7 · ¿Se cumple la regla «retirar nunca borra» también para las tablas vacías sin consumidores (`sales`, `prospects`, `payments_sellers`, `discounts_sales`)?**
- A. Sí: se dejan en el esquema, solo sin pantalla ni modelo. *Pro:* cero riesgo. *Contra:* ruido en el esquema.
- B. Borrarlas en una migración aditiva con respaldo. *Pro:* esquema limpio. *Contra:* contradice «no se borra» y exige aprobación.
- **Recomiendo A.**
