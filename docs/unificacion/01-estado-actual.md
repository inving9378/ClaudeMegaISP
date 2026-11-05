# UNI-01 · Documento 1 — Estado actual consolidado

Item #685 · épica UNI · 2026-10-24 · **solo documental** (sin cambios de código, migraciones, permisos ni config).
Une las cinco auditorías y **no repite su detalle**: enlaza a la sección donde está la evidencia.
Contrato: `plan-maestro-unificacion.md` (adjunto del item). Conteos = BD de dev; producción no se consultó.
Sin nombres, contactos ni montos por persona: solo conteos y nombres técnicos.

> **Actualizado tras #691–#694 y #697–#699 (2026-10-26):** ya corregidos y salen de la lista de riesgos: fecha de orden con una sola fuente oficial (#691), calendario y endpoints por id sin tareas ajenas (#692, #698), vigencia del token y límite de intentos de la app de Talento (#693), notificación inválida que daba 500 (#694); #697–#699 agregaron las pruebas. Siguen abiertos: validación de las 208 OT históricas, evasión de evidencias por rutas de estado del admin, idempotencia del ledger y bono de proyecto manual.

| Auditoría | Archivo | Cubre |
|---|---|---|
| 01a | [`01a-talento.md`](01a-talento.md) | Talento: identidad, ficha, campo, incentivos, app |
| 01b | [`01b-agenda.md`](01b-agenda.md) | Tareas y Scheduling: motor de agenda |
| 01c | [`01c-vendedores.md`](01c-vendedores.md) | Vendedores: ventas, atribución, comisiones |
| 01d | [`01d-ordenes.md`](01d-ordenes.md) | Tickets, órdenes, instalaciones, garantías |
| 01e | [`01e-base-transversal.md`](01e-base-transversal.md) | Identidad, accesos, notificaciones, auditoría, API |

---

## 1. Resumen ejecutivo

**Qué hay.** MegaISP ya tiene casi todas las piezas que pide el contrato, construidas por separado: un motor de pago semanal en Talento (63 tablas, 356 rutas), una ficha propia de colaborador de 21 pestañas, un calendario en Scheduling, un flujo de «Asignar trabajo» que ya crea órdenes con avisos, evidencias con candado de calidad, dos motores de ventas, una campana de notificaciones y una API móvil (Sanctum) en uso. La identidad ya es correcta: **39 colaboradores = 39 cuentas, 0 duplicados** (01a §2).

**Qué no funciona junto, y por qué.**

1. **No hay una orden: hay cuatro objetos** (`tickets`, `tasks`, `talento_work_orders`, `cut_installations`) sin llave común. Solo `tasks.ticket_id` los une y se usó 2 veces en 899 tickets (01d §2).
2. **No hay una agenda: hay cuatro fuentes con fecha** que ninguna pantalla ve juntas, y la tabla donde se escribe todo lo nuevo (`tasks`) guarda las fechas como texto en dos campos que se contradicen (01b §1, §6).
3. **Nada de lo que decide el pago deja historia.** Asignar, reprogramar, validar, cerrar: no hay bitácora (`activity_log` tiene 0 filas de tareas, órdenes y tickets) ni idempotencia en el libro de incentivos (01d §4, 01e §6, 01a §5).
4. **Ninguna comisión por venta se calcula hoy.** Los listeners legacy están comentados y el motor nuevo `ventas_*` es un esqueleto vacío; lo que se paga son 72 pagos semanales de sueldo fijo y adicional (01c §1).
5. **La garantía de 6 meses está construida pero no opera, y su diseño contradice el contrato**: marca al técnico como responsable y deja la orden sin puntos de forma automática (01d §7).
6. **El puente de identidad comercial está apagado**: `colaborador_id` en 0 de 4,439 clientes con vendedor; flags OFF (01e §2.2).
7. **La seguridad es sólida por URL y débil por fila** en Scheduling y cortes de caja; y la API móvil tiene tokens sin vencimiento y login sin `throttle` (01e §4.2, §7.1).

**Lo que sí es base reutilizable.** `Actor` (identidad por request, agnóstica de guard), `OrdenTrabajoUnifiedService`, `LiquidationService::countBillableUnits` (punto único del conteo), `AsignarTrabajo.vue`, `QCalendar.vue`, la compuerta de evidencias/dBm, la campana `notifications`, el dispatcher/canales de Flotas y el esqueleto `ventas_esquemas_comision`.

**Consecuencia para el plan.** La etapa siguiente es de **conexión y de cierre de huecos de trazabilidad**, no de construcción desde cero. El orden natural que sugieren las auditorías: (a) historial y compuerta de cierre en un solo lugar, (b) fechas de `tasks`, (c) agenda como vista sobre las fuentes, (d) política de ventas y libro de incentivos, (e) Mi Espacio sobre la ficha propia.

---

## 2. Estado por módulo de la misión

Leyenda: **F** funciona · **I** incompleto · **D** duplicado · **R** roto · **N** no auditado/no verificado. «Cobertura» dice qué tan a fondo lo vieron las cinco auditorías: los cinco primeros son de misión directa; los últimos cuatro solo se tocaron donde se cruzan con el contrato.

| Módulo | Estado | Resumen | Detalle |
|---|---|---|---|
| Talento | **F / I** | Identidad, OT, evidencias, asistencia, pago semanal, penalizaciones funcionan. Faltan metas, evaluaciones, agenda, notificaciones propias, estado «pagado», cierre con aprobación | 01a §0, §8 |
| Tareas (`tasks`) | **F / D / I** | Es donde se escribe todo lo nuevo. Fechas `varchar` y dos campos de fecha; 82 % de tareas con varios asignados; sin historial; 50 sin asignado | 01b §2, §6 |
| Scheduling (calendario) | **I / R** | Calendario FullCalendar usable; carga todo sin ventana de fechas; sin recurrencia, recordatorios ni choques; defectos en `getStartAndEndTime` y `initialView` | 01b §3 |
| Vendedores | **F / R / I** | Cortes de caja, pagos semanales, reportes funcionan. Comisión por evento apagada; `commissions_details` 100 % huérfana; sin metas; rutas `/sellers/*` 404 | 01c §9 |
| Órdenes de trabajo | **F / I / R** | Alta, evidencias, validación funcionan. Sin plantillas por solicitud, franja, conflictos, pausa, aceptación, reprogramación con motivo; historial roto; cierre de admin evade evidencias | 01d §9 |
| Instalaciones | **R / I** | `cut_installations` (175) es registro comercial sin llave a la orden; garantía sin ventanas; sin fecha de aceptación | 01d §7 |
| Garantías | **R** | 0 ventanas, tipos `Garantía pagable/no pagable` inexistentes, `inicia_garantia` sin consumidor, sin causa | 01d §7 |
| Tickets | **F / I** | Mesa de ayuda funciona; sin fecha de atención ni vínculo con órdenes | 01d §2, §8 |
| CRM | **I / N** | Prospectos con 67 fechas de instalación que ninguna agenda ve; duplicado con `ventas_prospectos`. El módulo en sí no se auditó entero | 01b §4, 01c §3 |
| Clientes | **I / N** | `seller_id` guarda `users.id` y se sobrescribe; `colaborador_id` vacío. Ficha de cliente no auditada más allá de estos campos | 01c §3, 01e §2.2 |
| Facturación (lo que toca a ventas) | **I / N** | Sin estado de venta validada ni cancelada; `payments_sellers` y `discounts_sales` vacías. Cobranza no auditada | 01c §6 |
| Inventario | **F (lectura) / N** | Custodia por usuario vía `inventory_item_stocks`, sin vínculo a adeudo; materiales por orden solo en la OT histórica | 01a §4, 01d §5 |
| Mapas y Planta Externa | **N con datos** | Código completo de proyectos, rutas, cajas y reparto (`quantity_share`), pero **0 filas** en todas las tablas; sin enlace a la creación de órdenes. Mapa de red no auditado | 01a §4, 01d §6 |
| Mantenimiento | **N** | No hay objeto propio de orden de mantenimiento ni flujo preventivo localizado | 01d §2 |

Transversales (no son módulos de la misión, pero condicionan todo): identidad **F**, roles y permisos **F / I** (01e §3), acceso por fila **I** (01e §4), notificaciones **D / I** (01e §5), auditoría **I** (01e §6), API móvil **F / I** (01e §7).

---

## 3. Las 10 duplicidades más costosas

Ordenadas por costo: cuánto confunde, cuánto dinero toca y cuánto bloquea la unificación.

| # | Mismo dato o proceso en dos lugares | Evidencia | Por qué cuesta |
|---|---|---|---|
| 1 | **«Orden»: `tickets` / `tasks` / `talento_work_orders` / `cut_installations`** | Sin llave común; `ticket_id` en 2 de 1,666 tareas; OT histórica sin `ticket_id`, 207 de 208 sin `client_id` (01d §2) | Imposible seguir una incidencia hasta su instalación original: bloquea garantía y calidad |
| 2 | **Fecha de la misma actividad: `start_date` frente a `start_time`/`end_time`** (ambos texto, en `tasks`) | El calendario mueve `start_time`; *Mi día* lee `start_date`; el técnico ve la fecha vieja (01b §1 punto 4, §6) | Mismo registro, dos verdades. Además toca la tabla que cuenta el pago |
| 3 | **Motor de comisión: legacy (`commissions_rules`, `payment_by_rule*`) frente a nuevo (`ventas_*`)** | Legacy paga sueldo fijo y adicional (72 filas) con comisión por evento apagada; nuevo con 0 filas (01c §1) | No hay fuente de verdad para modelos A–D; reactivar uno u otro cambia reglas económicas |
| 4 | **Vendedor y colaborador: `sellers` (32) y `talento_colaboradores` (39)** | 25 coinciden, 7 vendedores sin colaborador, 9 cuentas dueñas de ventas sin colaborador (01e §2.1, §2.2) | «Mis ventas» por colaborador cubriría como máximo 47 % de las filas |
| 5 | **Atribución de venta: `seller_id` (= `users.id`) y `colaborador_id` en paralelo** | 4,439 con `seller_id`, 0 con `colaborador_id`; flags OFF; `seller_id` colisiona numéricamente con `sellers.id` (01c §1 punto 2) | Base de comisiones ambigua y sobrescribible |
| 6 | **Comisión de embajador y de vendedor sobre un mismo cliente** | `referrals` (101) independiente de `seller_id`; sin regla de exclusión (01c §3) | Posible doble comisión por una venta |
| 7 | **Dos fuentes de «orden de campo»: `talento_work_orders` y `tasks` con `talento_type_id`** | Capa de traducción `OrdenTrabajoUnifiedService`; la vieja es solo histórico de 208 cerradas (01a §4, 01d §1) | Cada fuente conserva su estado; el pago suma ambas |
| 8 | **Cuatro almacenes de notificación y tres de token push** | `notifications` 14,943; `task_notifications` 3,661; `general_notifications`; `fleet_notification_*`; `push_tokens`, `talento_device_tokens`, `fleet_driver_push_tokens` (01e §5.1) | Ninguna sirve hoy para push real; el contrato pide una infraestructura reutilizable |
| 9 | **Dos motores de prospectos: CRM (2,262) y `ventas_prospectos` (58)** | 0 de 67 fechas de instalación ligadas a una tarea; comando de consolidación existente (01b §4, 01c §9) | La captura comercial se hace dos veces |
| 10 | **Dos familias de rutas de la app/portal con los mismos datos** (`TalentoMobileEquipoController` Sanctum y `PortalTecnicoController` sesión) | Misma lógica de servicios, dos contratos (01a §7) | Duplica contratos de API y su endurecimiento |

Menciones menores, no incluidas arriba: roles `Super Administrador` frente a `super-administrator` (01e §8 fila 7); `seller_types` repetido (01c §8); tres almacenes de bitácora por dominio (01e §6.2).

---

## 4. Las 17 preguntas de la sección 17 del contrato

Cada una: ¿puede responderla hoy el sistema?, ¿desde dónde?, ¿qué falta? **Sí** = respuesta directa y confiable; **Parcial** = hay datos pero incompletos o dispersos; **No** = no hay fuente.

| # | Pregunta | ¿Responde? | Desde dónde | Qué falta |
|---|---|---|---|---|
| 1 | ¿Qué debe hacer cada empleado hoy? | **Parcial** | *Mi día* (`/talento/portal/ots/hoy`, filtra por `start_date`) y listado de tareas por usuario | Agenda única; tickets, citas y reuniones no entran (01b §5) |
| 2 | ¿Qué actividades tiene atrasadas? | **Parcial** | Medible por consulta: 150 tareas `ToDo`/`InProgress` con fecha vencida | No hay vista de atrasadas; fechas texto; historial de fecha original perdido en 1,662 tareas (01b §1 punto 5, 01d §8) |
| 3 | ¿Quién le asignó una orden? | **No** | `created_by` por `BaseModel` | No se distingue quién creó de quién asignó ni de quién aprueba (01b §2.2) |
| 4 | ¿Quién está trabajando en ella? | **Parcial** | Asignados en `task_user`; estado `InProgress` | Sin «aceptada», «en camino» ni «pausada»; con varios asignados no hay ejecutor principal (01d §3-§4) |
| 5 | ¿Qué técnicos están disponibles? | **No** | — | Sin disponibilidad, sin choques, sin cuadrillas; 38 de 39 colaboradores sin supervisor (01a §2, §6) |
| 6 | ¿Qué órdenes siguen sin atenderse? | **Parcial** | `status` ToDo (126), tickets `Nuevo` (118, 48 sin responsable), tareas sin usuario (50) | «Pendiente de asignación» no es un estado; sin vista (01d §4, §8) |
| 7 | ¿Cuántas instalaciones realizó un técnico? | **Parcial** | `talento_work_orders` tipo 1 (184) y `countBillableUnits`; `cut_installations.technical_id` (175) | Tres fuentes sin llave común; la OT histórica solo con 5 colaboradores |
| 8 | ¿Cuántas fueron aprobadas? | **Parcial** | `validated` (206 de 208) | La aceptación técnica (`accepted_at`) está vacía: «aprobada» = validada por admin (01d §7) |
| 9 | ¿Qué garantías recibió? | **No** | — | 0 ventanas; la incidencia no se liga a la instalación original; solo ~1.7 % de instalaciones con ticket posterior, como piso (01d §7) |
| 10 | ¿Cuántas se atribuyeron a defectos de instalación? | **No** | — | No existe causa (las 8 del contrato); `warranty_overrides` con texto libre y 0 filas |
| 11 | ¿Qué ventas registró cada empleado? | **Parcial** | `client_main_information.seller_id` | 9 cuentas sin colaborador; sin historial de reasignación; ventas históricas implícitas en clientes (01c §6) |
| 12 | ¿Cuáles generan comisiones? | **No** | — | Sin estado de venta validada/elegible; comisión por evento apagada; `commissions` vacía (01c §1) |
| 13 | ¿Qué objetivos cumplió un departamento? | **No** | — | No existen metas ni objetivos; departamento es texto libre derivado del rol (01a §6, 01c §1 punto 6) |
| 14 | ¿Qué incentivos corresponden a cada persona? | **Parcial** | Ledger (12 asientos), bono de salud y de proyecto, liquidación en borrador | Sin libro con estados ni fuentes de venta/instalación/cuota (01a §5) |
| 15 | ¿Qué conceptos están pendientes de autorizar? | **Parcial** | Penalizaciones pendientes; liquidaciones `draft` | Sin cola unificada de autorizaciones ni «pendiente de revisión» en el ledger |
| 16 | ¿Cuánto lleva acumulado en el período? | **Sí (Talento)** | *Mi dinero* (`/portal/dinero/*`) y `avance`, mismo cálculo que el pago | No incluye comisión de venta (vacía) ni bonos por cuota; ventana `PayWeek` ya correcta (01a §5) |
| 17 | ¿Qué información se necesita para preparar el pago semanal? | **Parcial** | `simular`, liquidación, cierre | Falta observar/aprobar/bloquear/exportar/reabrir y estado «pagado» (01a §8) |

Lectura: solo **1 de 17** se responde sin reservas, **10** de forma parcial y **6** no tienen fuente. Las que fallan son las que dependen de historia (3, 9, 10) o de entidades que no existen (5, 12, 13); las parciales suelen faltar de estados (6, 15) o de una vista única.

«Resultados trazables a su fuente original» (cierre de la sección 17): hoy solo se cumple en el motor de pago de Talento; en garantías, ventas e incentivos no hay cadena de evidencia.

---

## 5. Riesgos para la integración

### 5.1 Datos sin dueño

| Riesgo | Evidencia |
|---|---|
| 9 cuentas dueñas de ventas sin colaborador (una concentra cerca de la mitad de las filas con `seller_id`) | 01e §2.2 |
| 38 de 39 colaboradores sin supervisor: «Mi equipo», asignación y confirmación de penalizaciones dependen de un dato casi vacío | 01a §2 |
| `tasks.assigned_to` legado (24 filas) frente a `task_user` | 01b §2.1 |
| Departamento como texto libre con valores de prueba; sin catálogo de capacidades | 01e §3.2 |
| Metas vigentes: no existen, nada que migrar pero todo por crear | 01c §6 |

### 5.2 Filas huérfanas o sin enlace

| Riesgo | Evidencia |
|---|---|
| `commissions_details`: 566,780 filas, 100 % sin padre; `commissions` en 0 | 01c §1 punto 7 |
| 207 de 208 OT históricas sin `client_id`, 0 con `accepted_at` | 01d §1 punto 5 |
| 50 tareas sin asignado; 67 instalaciones del CRM sin tarea | 01b §2.1, §4 |
| 1,662 de 1,666 tareas con la fecha original de programación perdida (reescritura del 2026-10-14, causa no determinada) | 01b §1 punto 5, §10 |
| ~14 asignaciones huérfanas del rol `client` y 5 colaboradores con rol `client` | CLAUDE.md (Fase 1), 01e §2.3 |

### 5.3 Cálculos desactivados o que contradicen el contrato

| Riesgo | Evidencia |
|---|---|
| Listeners de comisión con cuerpo comentado; espejo `talento_comisiones_espejo` con flag OFF | 01c §2.2, §7 |
| Doble escritura `colaborador_id` y corte de lectura apagados | 01e §2.2 |
| Garantía: «no pagable / 0 puntos» automático contradice §9 (no responsabilizar ni descontar de forma automática) | 01d §7 |
| Ledger sin estado ni clave de idempotencia: «append-only por convención»; liquidación sin «pagado» | 01a §5 |
| Dos reglas de desempeño DEMO activas (2 de 7) | 01a §5 |
| Conteo de pago de tareas: con varios asignados cada uno recibiría la unidad completa (no probado, solo 5 tareas de campo) | 01d §6 |
| Cualquier cambio a `tasks` (tipos, estados, `validated_at`, `task_user`) toca el cálculo de pago | 01b §7 |

### 5.4 Rutas rotas y acceso

| Riesgo | Evidencia |
|---|---|
| `/sellers/prospectos`, `/sellers/statistics` (404, item #585); `/message/reminder` GET inexistente | 01c §8 |
| `/read-notification/{id}` con id ajeno o ya leído → 500 | 01e §4.2 |
| Cierre por admin (`PUT /ordenes/{id}/status`) evade evidencias y dBm | 01d §5 |
| Acceso horizontal débil: Scheduling (19 rutas con id), cortes de caja, ficha básica de colaborador, media de OT, evidencia práctica. El calendario podría no filtrar por usuario (por lectura de código, sin probar) | 01e §4, 01b §2.3 |
| API móvil: 31 tokens vigentes sin expiración, sin *abilities*, login sin `throttle`, no valida `users.estado`; tres formatos de respuesta, sin paginación ni `/v1` | 01e §7, 01a §7 |

### 5.5 Riesgos propios de la transición

- **La tabla `tasks` es compartida** con pago, tickets, WarRoom y MegaFamilia: cualquier corrección de fechas se hace con respaldo, prueba en copia y migración incremental (01b P2).
- **Sin pruebas automáticas** de `Task`, Scheduling, calendario ni `OrdenTrabajoUnifiedService` (solo `PayWeekTest`): la regresión de §18 del contrato parte de cero (01b §3).
- **Producción no auditada**: todo conteo es de dev y los ids divergen de prod (regla de ids dev≠prod).
- **Datos sin ejercitar**: planta externa, rutas, cajas, exámenes, préstamos y finiquitos tienen 0 filas; que estén «construidos» no equivale a «probados».

---

## 6. Preguntas para Irving (consolidadas, sin repetir)

Las cinco auditorías hicieron 32 preguntas; aquí quedan 20 tras fusionar las que se superponen. Cada una dice qué auditoría la origina. Recomendación en negrita; las marcadas ⚠ tocan una frontera dura (dinero, permisos, autenticación o dato histórico) y requieren su decisión.

### Identidad y accesos

**Q1 · Cuentas dueñas de ventas sin colaborador (9).** (01e P1; ver también 01c P3)
- A. Crear expediente ligero para las que sean personas y marcar como «cuenta de sistema» las demás, excluidas del puente. Pro: el backfill cubre lo real. Con: usted confirma cuáles son personas.
- B. Dejarlas fuera. Pro: cero trabajo. Con: sus ventas históricas no aparecen en ningún Mi Espacio.
- **Recomiendo A**, con la lista entregada por conteo, sin nombres.

**Q2 · Encender `IDENTIDAD_DOBLE_ESCRITURA_COLABORADOR` en dev ya.** (01e P2, 01c P4)
- A. Ahora: las altas nuevas nacen con `colaborador_id`, conciliar con `identidad:verificar-consistencia`, luego prod. Pro: la deuda deja de crecer; reversible. Con: las huérfanas siguen sin puente hasta Q1.
- B. Esperar a Q1. Pro: orden. Con: el hueco crece.
- **Recomiendo A.**

**Q3 · ⚠ Dónde vive el permiso base de Mi Espacio (`portal.colaborador`).** (01e P3)
- A. En cada rol de campo y oficina, no por usuario. Pro: toda alta futura lo hereda. Con: toca permisos de roles.
- B. Mantener la asignación directa por observer (20 cuentas). Pro: ya funciona. Con: es el patrón de directos que la Fase 3b quiere eliminar.
- **Recomiendo A**, en migración aditiva aparte.

**Q4 · Acceso horizontal débil (Scheduling, cortes de caja, ficha básica, media de OT): ¿antes o durante Mi Espacio?** (01e P4, 01b P4)
- A. Antes: pasada corta de «asignado a mí / mi equipo / permiso de supervisión», empezando por la prueba del calendario. Con: toca controladores con consumidores.
- B. Durante, módulo por módulo. Con: Mi Espacio expondría rutas con el hueco abierto.
- **Recomiendo A** para Scheduling y cortes de caja (dinero); B para el resto.

**Q5 · Supervisores y estructura.** (01a P4, 01e P8, 01a P5)
- Dos decisiones juntas: (1) capturar `supervisor_id` de los 38 sin supervisor antes de construir supervisión (A) o derivarlo de puesto/departamento (B); (2) catálogos propios `departamentos` y `capacidades` separados del rol (A) o seguir con rol = puesto y departamento libre (B).
- **Recomiendo A en ambas**: dato mínimo y claro; las tablas residuales de niveles/puestos se dejan hasta la fase de retiro (01a P5, coherente con §13).

### Datos de campo, agenda y órdenes

**Q6 · ⚠ Fase 0 sobre las fechas de `tasks`.** (01b P2)
- A. Migración acotada: `start_time`/`end_time` a datetime real, `start_date` derivado, con respaldo, prueba en copia y reversa. Pro: base confiable para agenda y choques. Con: cambio de esquema en tabla compartida con pago.
- B. No tocar el esquema; el índice normaliza al leer. Con: el defecto persiste.
- **Recomiendo A.**

**Q7 · `talento_work_orders` histórica (208 cerradas, casi todas sin cliente).** (01b P1, 01d P5)
- A. Histórico de solo lectura que la agenda lee. Pro: conserva todo y no toca lo ya pagado. Con: sigue habiendo dos orígenes en lectura.
- B. Migrar a `tasks` o completar `client_id`. Con: toca registros liquidados.
- **Recomiendo A**; B solo si quiere medir calidad de esas instalaciones.

**Q8 · Choques de horario: ¿avisan o bloquean?** (01b P5)
- A. Avisar y permitir, con motivo obligatorio al forzar. B. Bloquear salvo permiso de supervisión.
- **Recomiendo A** al inicio y revisar con datos reales a las pocas semanas.

**Q9 · Instalaciones agendadas en el CRM (67 con fecha).** (01b P3)
- A. La agenda las lee tal cual. B. Al convertir el prospecto, crear la orden automáticamente (cambia el flujo comercial).
- **Recomiendo A** ahora, B como mejora posterior validada con ventas.

**Q10 · ⚠ Cierre del admin que evade evidencias.** (01d P3)
- A. Bloquear salvo permiso explícito y motivo. B. Dejarlo.
- **Recomiendo A**: hoy el candado de calidad no es real por esa ruta.

**Q11 · Unión ticket–orden al crear desde un ticket.** (01d P6)
- A. Obligatoria con `ticket_id` para tickets de tipo «Problema» o con visita. B. Opcional.
- **Recomiendo A.**

**Q12 · Crédito con varios técnicos en una orden.** (01d P2)
- A. Partes iguales por defecto, editables por el supervisor (reusa `quantity_share`). B. El responsable cobra y reparte fuera del sistema. C. Porcentaje fijo por rol.
- **Recomiendo A.** Toca el conteo de pago.

### Garantías

**Q13 · ⚠ Inicio de los 6 meses y lógica de garantía.** (01d P1 y P4)
- Inicio: A. aceptación técnica (`accepted_at`, hoy vacío), con respaldo a la fecha de validación; B. validación (lo actual).
- Lógica «no pagable / 0 puntos»: A. apagarla y pasar a evento de garantía + causa + revisión sin efecto económico automático; B. mantenerla y solo agregar causas (contradice §9).
- **Recomiendo A en ambas.**

### Ventas, incentivos y pago

**Q14 · ⚠ Fuente de verdad de la comisión por venta y política de elegibilidad.** (01c P1, P3, P7)
- Fuente: A. reactivar los listeners legacy; B. terminar `ventas_*` y dejar el legacy como histórico sin tocar `payment_by_rule*`; C. híbrido.
- Elegibilidad: A. cualquier colaborador activo con permiso; B. solo quien tenga política asignada.
- Pendiente de fondo: la regla de compensación de roles no técnicos (item #121) condiciona los modelos A–D y conviene cerrarla antes de construir políticas.
- **Recomiendo B y B**, sin comisión si no hay política.

**Q15 · Comisión de embajador y de vendedor sobre un mismo cliente, y metas.** (01c P2, P5)
- Embajador y vendedor: A. se acumulan; B. excluyentes; C. por campaña. **Recomiendo B** con excepción por campaña (modelo D).
- Metas: parte de la política o entidad propia versionada. **Recomiendo entidad propia**, reutilizable en los modelos B y C.

**Q16 · ⚠ Estado «pagado» y cierre semanal.** (01a P2, P1)
- A. Agregar estado y quién lo confirma (hoy solo `draft`/`closed`). B. Dejarlo fuera: Talento solo prepara.
- **Recomiendo A**, con confirmación manual; cumple §10.
- Y la forma de Mi Espacio: A. vista que concentre solo lo propio sobre la ficha; B. dejar la ficha y añadir pestañas. **Recomiendo A**: separa lo propio de lo administrativo.

**Q17 · Histórico huérfano y reglas demo.** (01c P6, 01a P6)
- 566,780 filas de `commissions_details`: A. conservarlas y marcarlas legado; B. archivarlas fuera de la tabla activa. **Recomiendo A** (nada se borra, §13).
- Reglas DEMO activas (2 de 7): A. desactivar tras su revisión. **Recomiendo A.**

### Notificaciones y API

**Q18 · Notificaciones.** (01e P5)
- A. Consolidar sobre `notifications` + `StandardNotification` con canales del patrón Flotas, tokens en `push_tokens`, `task_notifications` como compatibilidad. B. Infraestructura nueva.
- **Recomiendo A.**

**Q19 · ⚠ API móvil: tokens con vigencia, `throttle`, usuario activo y revocación.** (01a P3, 01e P6)
- A. Ahora, con plazo largo al principio y fecha de corte comunicada. Con: obliga a re-iniciar sesión en la app actual. B. Esperar a la app nueva (sigue habiendo tokens permanentes, contra §12).
- **Recomiendo A.**

**Q20 · Contrato de API.** (01e P7)
- A. Sobre único y `/v1` solo para lo nuevo; lo actual queda congelado. B. Migrar todo con compatibilidad dual.
- **Recomiendo A.**

Nota: varias preguntas agrupan decisiones relacionadas. Ninguna se ejecutó: el documento se cierra igual sin respuestas, y las que no se contesten toman la recomendación como supuesto de diseño, registrada en el Documento 2.

---

## 7. Cómo se verificó

Consolidación de las cinco auditorías (solo lectura). Las cifras citadas se toman de ellas sin recalcular; donde una auditoría marcó «no verificado» se conserva así (p. ej. exposición del calendario por usuario, revocación de tokens, personas duplicadas entre tablas, producción). Este documento no añade hallazgos nuevos ni modifica código, datos ni configuración.
