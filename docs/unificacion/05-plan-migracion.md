# UNI-05 · Documento 5 del plan maestro: plan de integración sin pérdida de datos

Item #689 · épica UNI · 2026-10-24 · **solo documental**: no se crean ni se corren scripts, migraciones, seeders, permisos, config ni `.env`.
Base: `01a`–`01e`, `01-estado-actual`, `02-arquitectura` y `03-mapa-funcionalidades` (esta carpeta). La evidencia `archivo:línea` vive ahí; aquí se cita por documento y sección.
Conteos = BD de **dev**, solo lectura (`COUNT`/`GROUP BY` del 2026-10-24). **Producción no se consultó**; ids y volúmenes divergen. Sin nombres de personas, contactos, montos por persona ni datos de clientes.

## 1. Principios y frontera de este plan

1. **Nada se borra ni se reescribe.** Todo lo nuevo es aditivo (tablas, columnas nulas, flags); lo histórico pasa a solo lectura, jamás a `DROP`.
2. **Una sola escritura por registro.** Cada fase fija quién es el dueño que escribe (`02` §2). La capa nueva lee y orquesta; no hay dos pantallas que editen la misma fila sin pasar por el mismo servicio.
3. **Cada fase es reversible por interruptor** (flag o ruta) antes de ser reversible por restauración.
4. **Dinero, permisos y autenticación = nivel B/C del circuito.** Las fases que los tocan quedan marcadas ⚠ y no se ejecutan sin decisión de Irving. Ninguna regla económica vigente se modifica aquí.
5. **Producción:** todo comando propuesto se ensaya primero en una copia de dev. Aplicarlo en producción exige una entrada en `docs/bitacora/` **antes** (regla permanente de CLAUDE.md: comando, base de datos, respaldo, responsable).
6. **Los ids originales se conservan.** Las tablas nuevas referencian por `fuente + id_origen` o por la llave original; no se renumera nada.

Pasos comunes a toda migración de datos propuesta:

| Requisito | Cómo se cumple |
|---|---|
| Repetible / idempotente | Llave natural única (`fuente + id_origen`, `evento_uid`); un segundo corrido no inserta |
| `--dry-run` | Reporta conteos y discrepancias sin escribir; es el valor por defecto recomendado |
| Aditiva | Solo `INSERT` en tablas nuevas o `UPDATE` de columnas **nuevas** nulas; nunca `UPDATE`/`DELETE` de columnas existentes |
| Respaldo previo | `backup_db:process` (+ volcado de las tablas tocadas) antes de cualquier corrida real |
| Auditable | Cada corrida escribe un resumen en `docs/bitacora/` y en el log del comando |
| Guardrail de migraciones | Respeta el guardrail #534 (migración commiteada y en rama ligada a main) |
| Sin `migrate:fresh` | Solo migración incremental |

## 2. Fase A — Inventario de lo que entra a la integración

### 2.1 Tablas y conteos (dev)

| Dominio | Tabla / objeto | Filas | Papel en la integración |
|---|---|---|---|
| Identidad | `users` | 4,981 | Cuenta única; no se toca |
| Identidad | `talento_colaboradores` | 39 | Perfil laboral 1:1 por `user_id`; 0 huérfanos hacia `users` |
| Identidad | `sellers` | 32 | Pasa a **capacidad**; 7 sin colaborador (ver §3.3) |
| Seguridad | `roles` / `permissions` / `model_has_roles` / `model_has_permissions` | 11 / 823 / 1,391 / 5,243 | Fuera de datos migrables; solo migraciones aditivas de permisos (⚠) |
| Órdenes | `tasks` / `task_user` / `observation_tasks` | 1,666 / 4,905 / 2,249 | Orden vigente; 0 `task_user` huérfanos |
| Órdenes | `tasks` con `talento_type_id` | 5 | Órdenes de campo del flujo nuevo |
| Órdenes | `talento_work_orders` (histórica) | 208 | Solo lectura; `validated` 206 · `completed` 1 · `incidencia` 1 |
| Órdenes | `talento_work_order_types` | 5 | Base de las plantillas |
| Solicitudes | `tickets` / hilos `ticket_threads` | 899 / n.v. | `Cerrado` 777 · `Nuevo` 118 · `Reciclado` 4; sin fecha ni llave a orden |
| Solicitudes | `tasks.ticket_id` | 2 (0 huérfanas) | Única relación orden↔ticket hoy |
| Instalación | `cut_installations` | 175 | Alta comercial; sin llave a la orden |
| Prospectos | `crm_main_information` / `crm_lead_information` / `ventas_prospectos` | 2,247 / 2,262 / 58 | CRM sobrevive; `ventas_prospectos` se consolida |
| Ventas | `client_main_information` con `seller_id` / con `colaborador_id` | 4,439 / **0** | Atribución actual frente al puente nuevo |
| Ventas | `commissions_rules` / `commissions` / `commissions_details` | 8 / 0 / 566,780 | Legado: las 566,780 filas de detalle son 100 % huérfanas |
| Ventas | `ventas_ventas` / `ventas_esquemas_comision` / `ventas_ledger_movimientos` | 0 / 0 / 0 | Esqueleto vacío que se aprovecha |
| Ventas | `payments_sellers` / `discounts_sales` | 0 / 0 | Muertas |
| Incentivos | `talento_ledger_entries` | 12 (suma agregada 13,850) | Ledger vigente; sin estado ni idempotencia |
| Incentivos | `talento_liquidations` | 5 (todas `draft`) | Cierre actual |
| Incentivos | `talento_penalties` | 2 | Se conserva |
| Garantía | `talento_responsibility_windows` / `talento_warranty_overrides` | existen (conteos de 02: 0) | Base del evento de garantía |
| Notificaciones | `notifications` / `task_notifications` | 14,943 / 3,661 | Sobrevive la primera; la segunda pasa a vista |
| Notificaciones | `push_tokens` / `talento_device_tokens` / `fleet_driver_push_tokens` | 0 / 0 / n.v. | Consolidación futura |
| Auditoría | `activity_log` | 130,801 | Sin filas de tarea/orden/ticket (01e §6) |
| Auth móvil | `personal_access_tokens` | 31 | Sin vencimiento (⚠ autenticación) |
| Material | `inventory_item_stocks` | 1,567 | Custodia; solo lectura en Mi Espacio |
| Mapa | `mapared_enlaces_servicio` | **0** | Bloquea cualquier cruce orden↔elemento del mapa |
| Pago | `payments` / `invoices` | 139,015 / 101,519 | **No se tocan** |

(n.v. = no verificado en esta pasada; el detalle de esquema y rutas está en `01a`–`01e`. Los conteos de tablas de garantía provienen de `01d`/`02`; no se re-midieron.)

### 2.2 Rutas, vistas, permisos, jobs y consumidores a no romper

| Consumidor | Qué lee o escribe | Fuente de inventario |
|---|---|---|
| Ficha propia de Talento (`/talento/portal/*`, 28 rutas) y `PortalTecnicoController` | `tasks`, `talento_work_orders`, liquidación | `01a` §3; `03` §2.1 |
| APK de Talento (`/talento/api/*`, 58 rutas, Sanctum) | `tasks`, evidencias, tokens | `01a`, `01e` §7 |
| `LiquidationService::countBillableUnits` | `tasks` + `talento_work_orders` + proyectos | `02` §1 |
| Calendario Scheduling (`/scheduling/task/calendar`, 30 rutas) | `tasks.start_date/start_time/end_time` (`varchar`) | `01b` |
| WarRoom, MegaFamilia, Tickets | Comparten `tasks` | `03` §4 |
| Ficha de cliente / CRM (`InformationClientCrud.vue`, `InformationCrmCrud.vue`) | Botón «Asignar trabajo» → `POST /talento/api/ordenes` | `03` §2.5–2.6 |
| `EventServiceProvider:65-69` | Evento de comisión (listeners con cuerpo comentado) | `03` §4 |
| Cron `talento:evaluar-reglas-semanales` (dom 04:00), `schedule:run` | Reglas de desempeño, Kernel | `01a` |
| Middleware `check_route_permission` / `can:portal.colaborador` | Permisos de rutas | `02` §5 |

## 3. Fase B — Mapeo entidad vieja → entidad nueva

### 3.1 Tabla de mapeo

| Entidad vieja | Entidad nueva | Tabla de correspondencia | Regla |
|---|---|---|---|
| `sellers` (32) | Capacidad «puede vender» del colaborador | `talento_colaborador_capacidad` (pivote) | 1 fila por vendedor que ya tiene colaborador; los 7 sin colaborador quedan en reporte, no se crean fichas solas |
| `client_main_information.seller_id` (`users.id`, 4,439) | `client_main_information.colaborador_id` (existente, 0 filas) y luego `ventas_atribuciones` | La propia columna; `ventas_atribuciones` guarda `seller_id_origen` | Doble escritura primero; backfill; versionado después |
| `tasks` + `task_user` | Orden unificada (sin copia) | No hay: la orden **es** `tasks` | Se añaden relaciones (`ticket_id`, instalación) en tablas auxiliares |
| `tasks.assigned_to` (24) | `task_user` | Ninguna | Solo se comprueba que `task_user` cubra las 24; no se vacía |
| `talento_work_orders` (208) | Se queda como histórico | Ninguna: lectura en `OrdenTrabajoUnifiedService` | No se migra a `tasks` (decisión P7 de `01-estado-actual`) |
| `tickets` (899) | Se queda | `orden_ticket_relacion` (auxiliar, `ticket_id` ↔ `task_id`) | Se siembra desde `tasks.ticket_id` (2 filas) |
| `cut_installations` (175) | Se queda | `orden_instalacion_relacion` (auxiliar) | Sin sembrar: no hay llave confiable (§3.2) |
| Fechas `varchar` de `tasks` | `datetime` normalizado | `tasks_fechas_normalizadas` o vista (según decisión P1 de `02`) | Opción A: columnas nuevas derivadas; opción B: normalizar al leer |
| `commissions_rules` (8) | Política de comisión con alcance y vigencia | `talento_politicas_comision.origen_regla_id` | Cada regla vieja origina una política versión 1 |
| `talento_ledger_entries` (12) | Se queda | `talento_incentivo_movimientos.ledger_origen_id` (opcional) | Lo ya registrado se copia como movimiento `pagado` solo si Irving lo decide (P1 §9) |
| `talento_liquidations` (5, `draft`) | `talento_cierres` | `talento_cierres.liquidacion_id` | Los borradores no se promueven solos |
| `task_notifications` (3,661) | `notifications` | Vista de compatibilidad | No se mueven filas; se lee en vista |
| Tokens push (3 almacenes) | `push_tokens` | `push_tokens.origen` | Solo cuando exista emisor |
| `ventas_prospectos` (58) | CRM | Comando existente `ventas:consolidar-prospectos-crm --dry-run` | Ya existe; se reutiliza |

### 3.2 Duplicados, huérfanos y datos sin equivalente

| Caso | Medido en dev | Regla |
|---|---|---|
| Ventas cuyo `seller_id` no existe en `users` | **19 filas** de `client_main_information` | Quedan sin `colaborador_id`; se listan en el reporte de conciliación como «dueño desconocido». No se inventa un usuario ni se reasignan |
| Ventas cuyo `seller_id` existe pero el usuario no tiene colaborador | **2,360 filas / 8 cuentas distintas** (de 27 `seller_id` distintos) | Ver §3.3: decisión de Irving cuenta por cuenta. La doble escritura las deja con `colaborador_id` nulo, no las bloquea |
| `commissions_details` sin `commissions` | **566,780 de 566,780** (100 %) | Legado de solo lectura. No se migra, no se borra, no entra a conciliación de importes (no tiene padre). Se conserva como evidencia |
| Tareas sin `task_user` | **50** | Estado derivado «pendiente de asignación». No se asignan automáticamente |
| `tasks.assigned_to` poblado | 24 | Se verifica cobertura en `task_user`; las no cubiertas se reportan |
| `tasks.start_date` vacío o nulo | **1,663 de 1,666** | La fecha de verdad vive en `start_time`/`end_time` (`varchar`). Regla: se deriva de ahí; si no se puede interpretar, queda `null` y se reporta (no se estima) |
| `tasks.ticket_id` sin ticket | 0 | Sin acción |
| Orden con ticket **e** instalación | no medible (sin llave a instalación) | La agenda muestra una fila por `fuente + id_origen`; ticket e instalación son relaciones de esa fila |
| `colaborador.user_id` sin usuario | 0 | Sin acción |
| Colaboradores sin supervisor | 38 de 39 (`01-estado-actual`) | No bloquea la migración; bloquea las vistas de equipo (captura manual) |
| Estados sin equivalente | Aceptada, en camino, pausada, observada: no existen en `tasks` | Se registran desde ahora en `orden_estado_historial`; **no se inventa historia** para lo ya cerrado |
| Estados de ticket | `Nuevo` / `Cerrado` / `Reciclado` | No se mapean a los 13 estados de la vista; se muestran como estado del ticket |
| `mapared_enlaces_servicio` en 0 | 0 | La referencia orden↔elemento del mapa no se migra: se deja el campo nulo |

### 3.3 Cuentas con ventas y sin colaborador

Hay 8 cuentas de `users` con ventas atribuidas y sin ficha de colaborador (cifra medida hoy; `02` P10 y `01e` hablaban de 9: la diferencia es temporal, no se asume). Regla propuesta, sin datos personales: cada cuenta se clasifica en (a) **persona** → se le crea ficha de colaborador por el flujo normal de Talento (decisión de Irving, ⚠ alta de personas), (b) **cuenta de sistema o exempleado** → se marca sin colaborador y sus ventas conservan `seller_id` como única verdad. Hasta que se decida, esas ventas **no generan incentivo nuevo** (pero tampoco se tocan).

## 4. Fase C — Compatibilidad: que lo viejo y lo nuevo convivan

### 4.1 Adaptadores y capas

| Adaptador | Qué hace | Interruptor |
|---|---|---|
| `AgendaService` + un adaptador por fuente (`tasks`, OT histórica, CRM) | Lee las fuentes por ventana de fechas y devuelve el contrato `{fuente, id_origen, …}`; no copia filas | Ruta nueva; el calendario actual sigue en pie |
| Traductor de estados (solo lectura) | Deriva `estado_unificado` desde el estado de origen; nunca lo escribe en el origen | Código; sin datos |
| `ColaboradorIdResolver` (ya existe) | Resuelve identidad `colaborador_id → user_id` con respaldo a `seller_id` | `IDENTIDAD_LECTURA_COLABORADOR_ID` (hoy `false`) |
| Doble escritura de ventas (observer + parches raw, ya existe) | Escribe `colaborador_id` junto a `seller_id` | `IDENTIDAD_DOBLE_ESCRITURA_COLABORADOR` (hoy `false`) |
| Vista de compatibilidad de `task_notifications` | Presenta lo viejo como si fuera `notifications` | Vista SQL / consulta; sin tocar filas |
| Servicio de cierre único | Todo cierre de orden (técnico o admin) pasa por el mismo gate de evidencias | Permiso nuevo para cierre admin con motivo (⚠ permisos) |
| Generadores de incentivos → libro | `LiquidationService`, `HealthBonusService`, `ProjectBonusService` escriben **también** al libro nuevo | Flag nuevo, apagado por defecto (⚠ dinero) |

### 4.2 Cómo se evita que dos pantallas modifiquen el mismo registro sin sincronizarse

| Registro | Riesgo de doble edición | Mecanismo |
|---|---|---|
| Fecha/estado de una orden | Calendario viejo y agenda nueva | La agenda **no escribe** salvo a través del servicio de Scheduling; ese servicio es el único camino. El calendario viejo ya usa ese servicio |
| Responsable de una orden | `assigned_to` frente a `task_user` | Se escribe solo `task_user`; `assigned_to` queda de lectura y se registra su divergencia en conciliación |
| Atribución de una venta | `seller_id` frente a `colaborador_id` | Doble escritura en la misma transacción desde un observer; ninguna pantalla escribe una sola de las dos. Con el flag apagado `seller_id` es la única verdad |
| Comisión | Listeners viejos frente a política nueva | Los listeners siguen **sin actuar** (cuerpo comentado) y no se reactivan; la política nueva escribe al libro, no a `commissions` |
| Incentivo ya pagado | Ledger vigente frente a libro | El libro nuevo nunca reescribe una fila del ledger; un movimiento `pagado` solo se corrige con ajuste |
| Notificación | Cuatro almacenes | Cada evento nuevo nace solo en `notifications`; los almacenes viejos no reciben tipos nuevos |

Ventana de convivencia máxima por dominio: **una fase** (§8). Una fase no se cierra si queda un par de pantallas editando el mismo dato sin el mecanismo de la tabla.

## 5. Fase D — Migraciones de datos propuestas (comandos repetibles)

Ninguno existe todavía salvo donde se indica. Todos: `--dry-run` por defecto recomendado, idempotentes, aditivos, ids originales conservados, **solo dev en esta etapa**.

| # | Comando propuesto | Qué hace | Tabla/columna que escribe | Idempotencia | Existe | Riesgo |
|---|---|---|---|---|---|---|
| D1 | `identidad:backfill-colaborador-id` (+ `identidad:backfill-colaborador-id-sellers`) | Completa `colaborador_id` en `client_main_information` desde `seller_id` ↔ `talento_colaboradores.user_id`; ambos con `--dry-run`; se verifica con `identidad:verificar-consistencia` | `client_main_information.colaborador_id` (nuevo, nulo) | Solo donde es nulo (por confirmar al leer el código) | **Sí** (3 comandos en `app/Console/Commands/Active/`) | Alto ⚠ (dato comercial) |
| D2 | `agenda:sembrar-relaciones` | Siembra `orden_ticket_relacion` desde `tasks.ticket_id` (2 filas) | tabla auxiliar nueva | `task_id` único | No | Bajo |
| D3 | `agenda:normalizar-fechas` | Interpreta `start_time`/`end_time`/`start_date` (`varchar`) y llena columnas `datetime` nuevas | columnas nuevas en `tasks` o tabla `tasks_fechas_normalizadas` | Recalcula igual cada vez | No | Alto ⚠ (tabla compartida con el pago) |
| D4 | `talento:sembrar-capacidades` | Crea la capacidad «puede vender» desde `sellers` con colaborador | `talento_capacidades` + pivote | Par único colaborador/capacidad | No | Medio |
| D5 | `talento:sembrar-departamentos` | Propone catálogo desde los textos libres de `department` (⚠ incluye valores de prueba) | `talento_departamentos` | Por nombre normalizado | No | Medio |
| D6 | `comisiones:sembrar-politicas` | Crea una política v1 por cada regla de `commissions_rules` (8) | `talento_politicas_comision` | `origen_regla_id` único | No | Alto ⚠ (dinero) |
| D7 | `incentivos:sembrar-libro` | Opcional: copia los 12 movimientos del ledger como `pagado` con `ledger_origen_id` | `talento_incentivo_movimientos` | `evento_uid` determinista | No | Alto ⚠ (dinero); solo si Irving lo decide |
| D8 | `ventas:consolidar-prospectos-crm` | Consolida `ventas_prospectos` (58) en CRM | CRM | Por `--dry-run` y llave de prospecto | **Sí** | Medio |
| D9 | `notificaciones:vista-compat` | Crea la vista de lectura de `task_notifications` | Vista SQL | `CREATE OR REPLACE` | No | Bajo |
| D10 | `orden:sembrar-plantillas` | Crea las plantillas de orden por tipo desde `talento_work_order_types` (5) y las 13 solicitudes del contrato | `orden_plantillas` | Por clave de plantilla | No | Bajo |

Orden de ejecución dentro de una fase: respaldo → `--dry-run` y revisión del reporte → corrida real en copia de dev → conciliación (§6) → solo entonces dev real → bitácora. Producción: otra decisión aparte (regla de bitácora previa).

## 6. Fase E — Conciliación

### 6.1 Qué se compara en cada fase

| Fase de integración | Conteos | Relaciones | Sumas monetarias | Comisiones pagadas | Tareas | Estados | Fechas | Adjuntos | Responsables |
|---|---|---|---|---|---|---|---|---|---|
| 1 Identidad y capacidades | `sellers` con colaborador = filas de pivote; cuentas sin colaborador listadas | `colaborador.user_id` ↔ `users` (0 huérfanos) | — | — | — | — | — | — | Cada vendedor ↔ un colaborador |
| 2 Atribución de ventas (⚠) | `seller_id` no nulos (4,439) = `colaborador_id` no nulos + sin colaborador conocidos | `colaborador_id` ↔ `seller_id` por `user_id` (0 divergencias) | — | — | — | — | — | — | Mismo dueño por venta |
| 3 Agenda | Filas de agenda por fuente = filas de origen en la ventana | Una fila por `fuente + id_origen` (0 duplicados) | — | — | Tareas del origen = tareas en agenda | Estado unificado derivable de todo origen | Fecha normalizada = fecha original interpretable (o `null` explicado) | — | `task_user` = participantes |
| 4 Órdenes y seguimiento | `tasks` antes = `tasks` después | `task_id` ↔ `ticket_id` (2) | — | — | Cierres con evidencias requeridas cumplidas | Cada orden con estado derivable | — | Evidencias antes = evidencias después | Responsable y asignador conservados |
| 5 Incentivos y cierre (⚠) | Movimientos = eventos candidatos | `ref_movimiento` válido | Suma del libro por periodo = suma del ledger vigente por periodo (13,850 de referencia agregada en dev) | Movimientos `pagado` = filas pagadas del ledger | — | Transiciones válidas | `periodo` ⊂ ventana `PayWeek` | — | Beneficiario = colaborador del evento |
| 6 Notificaciones | Vista = `task_notifications` (3,661) | — | — | — | — | Leído/no leído conservado | Fecha conservada | — | Destinatario conservado |
| 7 Garantías | Eventos = ventanas migradas | Instalación original válida | — | — | — | Causa registrada | Aceptación técnica ≥ inicio de ventana | Evidencias referenciadas | Participantes de la instalación |

### 6.2 Cómo se explica una diferencia

1. **Toda diferencia se registra** con su causa en una tabla del reporte (`conciliacion_discrepancias`: fase, métrica, esperado, obtenido, causa, decisión).
2. Las causas aceptables son una lista cerrada: dato histórico sin dueño (p. ej. los 19 `seller_id` sin usuario), dato legado huérfano (p. ej. los 566,780 detalles), fila excluida por regla (venta cancelada), cambio en vivo de la fuente entre dos mediciones.
3. Una diferencia **sin causa en la lista** detiene la fase: no se avanza hasta explicarla o corregir el comando.
4. La conciliación se corre **antes y después** de cada corrida real, en dev, y se anexa a la bitácora. Una fase se da por buena solo con «0 diferencias sin explicar».
5. Las sumas monetarias son agregadas (por periodo), nunca por persona en el repositorio.

## 7. Fase F — Sustitución visual

| Condición para mover un acceso | Detalle |
|---|---|
| Nueva pantalla funcional y validada | Prueba visual por Irving (o quien designe) en dev |
| Conciliación de la fase en cero diferencias sin explicar | §6 |
| Rutas viejas siguen respondiendo | Ninguna ruta de `03` §4 se elimina en esta fase |
| Aprobación explícita | Sin ella no se retira ningún menú |

Qué se hace: el menú/sidebar apunta a la pantalla nueva; la ruta antigua **sigue activa** (redirige o responde igual). Rutas que se mantienen sí o sí en esta etapa: `/talento/api/*` (APK congelada), `/talento/portal/*` (la ficha las consume), `/scheduling/*` (WarRoom, MegaFamilia, Tickets), `/talento/ordenes`, `ComissionController` (hoy vacío), `/mapas/*`.

Reversión de esta fase: restaurar el enlace del menú (cambio de configuración/Blade; no hay datos que deshacer).

## 8. Fase G — Retiro controlado

Se aplica **solo** a lo marcado «retirar después» en `03` §4, con sus tres condiciones (migrado y conciliado; cero consumidores medidos; aprobación de Irving si toca dinero, permisos o autenticación). Retirar significa quitar la pantalla o ruta, dejar de escribir y declarar la tabla legado de solo lectura. **No se borra ninguna tabla.** Quedan fuera de retiro: `commissions_details`, `talento_work_orders`, APK actual, `Mapas` (hasta que `MapaRed` cubra todo).

## 9. Reversión por fase

Cada fase se plantea como un paquete con interruptor. La fila indica qué se apaga primero y qué se restaura solo si algo más falla.

| Fase | Contenido | Interruptor (primer paso) | Restauración (segundo paso) | Qué NO se puede deshacer |
|---|---|---|---|---|
| 0 | Fechas normalizadas (D3), ⚠ | Dejar de leer las columnas nuevas; el calendario vuelve al `varchar` | Borrar columnas nuevas (aún vacías de uso) o restaurar respaldo | Nada: las columnas originales no se tocaron |
| 1 | Capacidades y catálogos (D4, D5) | Quitar la capacidad de la vista; los permisos no cambian | Vaciar las tablas nuevas | Nada |
| 2 | Doble escritura y backfill de `colaborador_id` (D1), ⚠ | `IDENTIDAD_DOBLE_ESCRITURA_COLABORADOR=false` | `UPDATE … SET colaborador_id = NULL` solo sobre filas sembradas por el comando (marca de corrida) | Nada: `seller_id` nunca se modificó |
| 3 | Agenda (D2, adaptadores) | Ocultar la ruta nueva; el calendario viejo sigue | Borrar tablas auxiliares | Nada |
| 4 | Plantillas y seguimiento (D10, `orden_estado_historial`) | Flag de plantillas apagado; «Asignar trabajo» actual sigue | Borrar tablas auxiliares | El historial de estado escrito entre tanto (es nuevo; se conserva en respaldo) |
| 5 | Libro de incentivos y cierre (D6, D7), ⚠ | Flag del libro apagado; ledger vigente y `PayWeek` siguen | Restaurar respaldo de las tablas nuevas | **Cualquier movimiento ya marcado `pagado` fuera de MegaISP**: no se revierte con datos; solo con ajuste auditable |
| 6 | Notificaciones (D9) | Quitar la vista | Nada que restaurar | Nada |
| 7 | Garantías | Apagar el flag del evento de garantía | Vaciar eventos nuevos; las ventanas viejas no se tocaron | Nada |
| 8 | Sustitución visual | Restaurar el enlace del menú | — | Nada |
| 9 | Retiro | Reponer ruta/pantalla desde git | — | Nada (las tablas siguen) |

Regla general: si una fase deja de ser reversible por interruptor, **no se ejecuta** sin decisión de Irving.

## 10. Orden de las fases y condiciones de arranque

| # | Fase | Depende de | Arranca cuando |
|---|---|---|---|
| 0 | Cerrar huecos de acceso (Scheduling por id, cortes de caja, ficha, media de OT) y decisión de fechas | Decisión P1/P4 de `02` | Irving aprueba; sin esto Mi Espacio expone fugas |
| 1 | Capacidades, departamentos, cuadrillas | Fase 0 | Catálogos aprobados; captura de supervisores iniciada |
| 2 | Identidad de ventas (doble escritura + backfill) | Fase 1; decisión sobre 8 cuentas sin colaborador | Conciliación de fase 2 en cero |
| 3 | Agenda unificada | Fase 0 (fechas) | Adaptadores probados contra datos reales |
| 4 | Plantillas de orden y seguimiento | Fase 3 | Gate de evidencias único en servicio |
| 5 | Libro de incentivos y cierre ⚠ | Fases 2 y 4; decisión P5 de `02` | Irving aprueba reglas económicas |
| 6 | Notificaciones | Fase 4 | — |
| 7 | Garantías | Fase 4 | Decisión de causa y efecto económico (sin automático) |
| 8 | Sustitución visual | Fases validadas | Aprobación explícita |
| 9 | Retiro | Fase 8 + consumidores medidos | Aprobación explícita |

El desglose con objetivo, archivos, riesgos y criterios de aceptación pertenece al Documento 6.

## 11. Pruebas de integridad que respaldan el plan

Aplican a cada fase (diseño; no se escriben ni corren aquí): conservación de filas migradas (§6), compatibilidad de rutas anteriores (§7), regresión del conteo de unidades y de la liquidación (`PayWeek`, `countBillableUnits` sin cambio de resultado), prevención de pago duplicado (`evento_uid` único), imposibilidad de modificar un movimiento `pagado`, ausencia de duplicados en agenda, comisiones históricas intactas (`commissions_details` sin variación de conteo), y que ninguna acción de dev envíe correo o WhatsApp reales.

## 12. Qué no se pudo verificar

- Producción: conteos, huérfanos y cuentas sin colaborador pueden ser distintos; cada conciliación debe repetirse allá antes de actuar.
- La idempotencia real de los comandos `identidad:backfill-colaborador-id*` (existen con `--dry-run`; no se leyó su código de escritura).
- Conteos de `ticket_threads`, `talento_responsibility_windows`, `talento_warranty_overrides` y `fleet_driver_push_tokens` en esta pasada (se toman de `01d`/`02`).
- Número de instalaciones ligables a su orden (no hay llave); se asume 0 hasta medir.
- La causa de la reescritura masiva de fechas de `tasks` del 2026-10-14 (`01b` §1.5): condiciona D3.
- Que los tokens móviles se revoquen al cambiar contraseña.

## 13. Preguntas para Irving

**P1 ⚠ — ¿Se copian los 12 movimientos del ledger vigente al libro nuevo como `pagado`?**
- A. Sí, con `ledger_origen_id`. *Pro:* historia única. *Contra:* toca datos de dinero; riesgo de doble lectura.
- B. No; el ledger vigente queda como histórico y el libro nuevo empieza vacío. *Pro:* cero riesgo. *Contra:* dos fuentes en el reporte del periodo.
- **Recomiendo B**, con la conciliación sumando ambas fuentes.

**P2 ⚠ — ¿Qué se hace con las 8 cuentas que tienen ventas y no tienen colaborador?**
- A. Clasificarlas una por una (persona → ficha; sistema/exempleado → marcar). *Pro:* identidad limpia. *Contra:* requiere tu confirmación y datos personales fuera del repo.
- B. Dejarlas sin colaborador y excluirlas de incentivos nuevos. *Pro:* sin trabajo. *Contra:* deuda que crece.
- **Recomiendo A**; mientras tanto B como estado temporal.

**P3 — ¿Normalizar las fechas de `tasks` con columnas nuevas (D3-A) o al leer (D3-B)?**
- A. Columnas `datetime` nuevas derivadas, sin tocar las originales. *Pro:* base confiable de agenda y choques. *Contra:* cambio de esquema en tabla compartida con el pago.
- B. Normalizar al leer en cada adaptador. *Pro:* sin esquema. *Contra:* el defecto sigue en las otras pantallas.
- **Recomiendo A**, ensayada primero en copia (coincide con `02` P1).

**P4 ⚠ — ¿Cuánto tiempo conviven doble escritura y lectura antigua antes de cortar la lectura?**
- A. Una fase completa (≈ un periodo de pago) con conciliación en cero. *Pro:* prueba con una semana real. *Contra:* más tiempo con dos columnas.
- B. Corte inmediato tras el backfill. *Pro:* más rápido. *Contra:* sin prueba con operación real.
- **Recomiendo A.**

**P5 — ¿Las plantillas de orden empiezan con las 5 existentes o con las 13 del contrato?**
- A. Las 13 (D10), marcando cuáles se mapean a un tipo existente. *Pro:* cubre el contrato. *Contra:* ocho plantillas necesitan reglas de evidencia nuevas.
- B. Solo las 5, ampliando con uso. *Pro:* menos riesgo. *Contra:* el mostrador no ve todos los casos.
- **Recomiendo A**, con las ocho nuevas heredando la matriz del tipo más parecido hasta que se ajuste.

**P6 — ¿Se ejecuta algo de esto en producción, y cuándo?**
- A. Nada hasta cerrar y conciliar todo en dev; luego una fase a la vez con bitácora previa. *Pro:* sin sorpresas. *Contra:* más lento.
- B. Fases independientes (p. ej. notificaciones) pueden adelantarse. *Pro:* valor temprano. *Contra:* producción divergente de dev.
- **Recomiendo A.**
