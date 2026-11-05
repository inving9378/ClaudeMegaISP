# UNI-01e · Base transversal: identidad, roles y accesos, notificaciones, auditoría y API

Épica UNI (plan maestro de unificación) · Documento 1, pieza E · Item #684 · 2026-10-24.
Etapa solo documental: no se cambió código, migraciones, permisos, config ni `.env`.
Base de dev consultada en **solo lectura** (conteos agregados). Sin nombres, correos, ids de personas ni montos.

> **Actualizado tras #691–#694 y #697–#699 (2026-10-26):** §3 (acceso horizontal): calendario y tareas por id ya restringen por asignado (#692/#698); §4 (notificaciones): marcar como leída una inválida/ajena ya responde 404 sin error (#694, pruebas en #699); §6 (API): el token de la app de Talento vence y el login limita intentos (#693). Cierre parcial: el resto de P6 (usuario activo, endurecimiento de otros endpoints) sigue como pregunta para Irving.

Leyenda de estado: **funciona** · **incompleto** · **duplicado** · **roto** · **no verificado** (se dice por qué).

---

## 1. Resumen ejecutivo (lo que cambia el plan)

1. **La identidad "una cuenta por colaborador" ya está en la base**: `talento_colaboradores.user_id` es UNIQUE (39 colaboradores = 39 cuentas distintas, 0 duplicados). El problema no es la duplicación de cuentas sino que **el puente comercial está sin encender**: `client_main_information.colaborador_id` tiene **0 de 4,439** filas pobladas y los dos kill-switches de identidad están apagados.
2. **El vendedor y el colaborador son dos tablas con un solo `users.id` en común**: 32 `sellers` y 39 `talento_colaboradores`; 25 coinciden, 7 vendedores no tienen colaborador. Nueve cuentas dueñas de ventas (`seller_id`) no tienen colaborador (incluida la cuenta de administración que concentra la mayoría de las filas): son huérfanas **estructurales**; el backfill no las arregla.
3. **«Puesto» = rol de Spatie, y eso ya es decisión tomada** (talento_puestos y `job_title` se retiraron el 2026-10-21; `puesto_id` es NULL en los 39). No existe hoy una capa propia de departamento/capacidad: `department` es texto libre con 9 valores (incluye valores de prueba).
4. **La autorización por ruta es sólida pero es a nivel de URL, no de fila**. Talento y su app móvil sí filtran por el colaborador autenticado o por supervisión (sección 4). Fuera de Talento, **Scheduling (tareas)** y **Vendedores (cortes de caja)** reciben un id y confían solo en el permiso de la ruta (sección 4.2). Es el hueco horizontal más grande para la etapa de Mi Espacio.
5. **Notificaciones: hay cuatro infraestructuras paralelas y ninguna sirve hoy para push real.** La campana usa `notifications` (Laravel, 14,943 filas, 10,283 sin leer); existen además `task_notifications`, `fleet_notification_*` y `push_tokens` (0 filas, sin ningún emisor FCM que lo consuma). El correo de dev se intercepta; WhatsApp se frena con un interruptor propio (`WHATSAPP_SENDER_ENABLED`).
6. **Auditoría: `activity_log` cubre clientes, saldos, pagos y facturas; Talento casi no deja rastro ahí** (0 filas con modelo de Talento; solo 2 de 60 modelos usan `LogsActivity`). Los eventos de la sección 15 del plan (asignaciones, reprogramaciones, validaciones, metas, comisiones, cierres) **no están cubiertos de forma uniforme**.
7. **API: ya hay una API móvil estable en Talento (Sanctum), pero sin expiración de token, sin throttle en el login, sin versionado y con tres formatos de respuesta distintos.** Reutilizable como base; hay que unificar contrato antes de abrir un segundo cliente.

---

## 2. Identidad

### 2.1 Inventario (dev, conteos agregados)

| Entidad | Tabla | Filas | Evidencia |
|---|---|---|---|
| Cuentas | `users` | 4,981 (4,963 activas, 18 inactivas; 1 de sistema `is_system`) | consulta `group by estado` / `is_system` |
| Cuentas espejo de cliente | `users` con `login_user` `Meganet[0-9a-f]{8}` | 1,321 | patrón de CLAUDE.md (Fase 2.5) |
| Colaboradores | `talento_colaboradores` | 39 (20 activos, 19 inactivos) | `user_id` UNIQUE → 39 cuentas distintas |
| Vendedores | `sellers` | 32 (32 `user_id` distintos) | |
| Colaboradores que además son vendedor | join por `user_id` | 25 | |
| Vendedores sin colaborador | | 7 | |
| Colaboradores con rol `client` | | 5 | cuenta de cliente reutilizada como colaborador |
| Colaboradores sin ningún rol | | 1 | |
| Correos repetidos en `users` | `group by email having count>1` | 9 grupos | posible duplicado persona↔cuenta; **no verificado** si son personas reales (no se cruzó contra datos personales por la regla de repo público) |

**¿Colaboradores con más de una cuenta?** No por `user_id` (UNIQUE). **¿Personas duplicadas entre tablas?** No se puede afirmar sin cruzar nombres/CURP, y eso expondría datos personales; queda como pregunta (ver Preguntas, P1).

### 2.2 Estado de la identidad unificada (`colaborador_id` en paralelo a `seller_id`)

| Pieza | Estado | Evidencia |
|---|---|---|
| Columna `client_main_information.colaborador_id` | existe, **0 de 4,439** filas con `seller_id` la tienen poblada | `php artisan identidad:verificar-consistencia` |
| Doble escritura | **apagada** | `config/identidad.php` `doble_escritura_colaborador_id` (env `IDENTIDAD_DOBLE_ESCRITURA_COLABORADOR`, ausente en `.env`) |
| Corte de lectura módulo a módulo | **apagado** | `config/identidad.php` `lectura_colaborador_id` (env `IDENTIDAD_LECTURA_COLABORADOR_ID`, ausente en `.env`) |
| Observer del puente | registrado (se dispara solo con el flag) | `app/Providers/EventServiceProvider.php:117-118` → `Observers/Identidad/ColaboradorIdBridgeObserver` |
| Comando de consistencia | **funciona**, solo lectura | `app/Console/Commands/Active/VerificarConsistenciaIdentidadCommand.php` (`identidad:verificar-consistencia`) |
| Cobertura resoluble | 2,078 de 4,439 filas tienen colaborador real disponible hoy (pendientes de backfill); 2,361 no | salida del comando |
| Huérfanas estructurales | 9 cuentas con `seller_id` sin colaborador (la mayor concentra ~2,137 filas) | salida del comando |
| `whatsapp_conversations` | 0 filas, nada que migrar | salida del comando |

**Lectura:** el trabajo técnico (observer, comando, flags) está hecho y es seguro (default apagado). Lo que falta es una **decisión** sobre las 9 cuentas huérfanas (crear expediente ligero de colaborador, o dejarlas como "cuentas de sistema/administración" excluidas del puente) y luego el backfill. Mientras no se decida, "Mis ventas" por colaborador cubriría como máximo 47% de las filas.

### 2.3 Cuentas espejo de clientes

Son 1,321 `users` con rol `client` (1,337 asignaciones del rol) para que el cliente entre al portal/MegaFamilia. Dos riesgos vigentes, ya documentados en CLAUDE.md y confirmados hoy:
- 5 colaboradores tienen además el rol `client` (la cuenta de cliente fue reutilizada). Un colaborador con rol `client` hereda las rutas de portal de cliente; no hay conflicto técnico, pero mezcla dos identidades en una cuenta.
- La fuente que copia permisos rol→directo al crear usuarios (`UserController::store`, Fase 3b) sigue pendiente; hoy hay 5,243 filas de permisos **directos** en 40 usuarios (ver 3.3).

---

## 3. Autorización

### 3.1 Mecanismos (todos reutilizables, ninguno duplicado)

| Mecanismo | Dónde | Estado |
|---|---|---|
| Permiso por URL | `app/Modules/Core/Auth/Middleware/CheckRoutePermission.php` + `config/route_permission.php` (2,931 líneas, ~511 entradas) | funciona; honra permisos de **rol ∪ directos** desde el flip de la Fase 3a (`PermissionTrait::getPermissionForUserAuthenticated`) |
| Permiso Spatie directo en ruta (`can:`, `permission:`, `role:`) | 347 rutas | funciona |
| Sanctum (apps) | 138 rutas bajo `auth:sanctum` | funciona (ver sección 7) |
| Rutas sin autenticación | 84 de 2,998 | revisar: 17 sin módulo claro (webhooks/health esperables), 16 PortalCliente (login/registro), 13 Roadmap (API externa con token propio), 7 Marketing, 6 Talento (health, branding, login, app/latest, credencial firmada, service worker), resto portales públicos |
| Bypass por nombre de rol | `CheckRoutePermission` + `isAdmin()` | funciona; es deliberado (super-administrator, DESARROLLADOR, etc.) |
| Puerta propia de Talento | `Support/Actor.php` (identidad inmutable por request), `can:portal.colaborador`, `puedeVer*()` por controlador | funciona |
| Convención jerárquica `{modulo}_view_{dashboard|block|card|info}` | item #538 (CLAUDE.md) | **incompleta**: solo Dashboard la usa; los demás módulos no |

Totales de rutas (dev, `route:list`): 2,998; con `check_route_permission` 1,858; con `auth:sanctum` 138; con `can/permission/role` 347; solo `auth` 571; sin auth 84.

### 3.2 Roles frente a puestos, departamentos y capacidades

| Concepto del plan | Qué existe hoy | Estado |
|---|---|---|
| Rol de seguridad | Spatie `roles` (11) | funciona |
| Puesto | **es el rol** (decisión 2026-10-21; `talento_puestos` retirado, `puesto_id` NULL en 39/39) | funciona, pero mezcla rol de seguridad con puesto |
| Departamento | `talento_colaboradores.department` texto libre (9 valores distintos, 3 NULL, varios de prueba) | **incompleto** |
| Nivel/escalafón | `level_id` (0 de 39 con nivel; los niveles se retiraron) | retirado |
| Capacidad profesional (técnico PI/PE, instalador, etc.) | `categoria_externo` solo para externos; no hay catálogo de capacidades | **no existe** |
| Supervisor | `supervisor_id` + `subordinados()` | funciona (base de la supervisión por árbol) |

Roles y tamaño (usuarios · permisos): `client` 1,337·7; `Vendedor` 28·105; `TECNICO` 7·117; `Mostrador` 5·259; `super-administrator` 5·824; `DESARROLLADOR` 4·859; `Administrador` 2·163; `Super Administrador` 2·448 (duplicado ES/EN del super-admin); `Almacen` 1·75; `ADMINISTRADOR_COMPLETO` 0·567; `SUPERVISOR_MOSTRADOR` 0·267 (sin usuarios). 823 permisos en total. 12 usuarios tienen más de un rol de staff.

### 3.3 Rol frente a directos

- 40 usuarios tienen permisos directos (5,243 filas): el residuo de la Fase 3b. El flip de la Fase 3a ya hace que el rol sume, pero mientras los directos sigan ahí, **cambiar un permiso en el rol no cambia lo que realmente puede hacer esa persona**.
- `portal.colaborador` está en solo 2 roles (super-administrator y DESARROLLADOR) y como **directo en 20 cuentas**: se asigna cuenta por cuenta (observer de Talento), no por rol. Para "Mi Espacio" para todos conviene el permiso base en el rol, no por usuario.
- Los permisos de **supervisión/aprobación** existen separados de los de ejecución en Talento (`talento.work_orders.validate` frente a `.manage`; `talento.liquidation.view`; `talento.employees.view` solo en super-administrator y DESARROLLADOR). Cumple el principio de la sección 14. **Falta** hacer lo mismo para "aprobar el propio bono/comisión": no hay regla de "no autoaprobarse" verificable fuera de Talento.

---

## 4. Acceso horizontal (lectura de código, sin explotar nada)

Criterio: un endpoint con id en la URL es **seguro** si filtra por el colaborador autenticado (o por su árbol de supervisión) o exige un permiso de supervisión específico; **débil** si solo depende del permiso de ruta y el permiso lo tienen muchos roles de ejecución.

### 4.1 Talento

| Endpoint (grupo) | Control encontrado | Evidencia | Veredicto |
|---|---|---|---|
| App móvil `/talento/api/ots/{id}` y sus acciones (ver, evidencia, iniciar, completar, incidencia, nota) | todas pasan `colaborador->id` y la consulta filtra `where colaborador_id` | `Services/OrdenTrabajoUnifiedService.php` `detail` (316-320), `findLight` (339-342), `iniciar` (361-364), `completar` (589-592) | seguro |
| App móvil `/talento/api/portal/*?colaborador_id=` | `puedeVer()`: staff por permiso o él mismo o su subordinado | `TalentoMobileEquipoController.php:60-74` | seguro |
| Portal web `/talento/portal/*` | `can:portal.colaborador` + `Actor` self-scoped; documentos con `can:talento.documentos.*-propios` | `Talento/routes.php:606-660` | seguro |
| Fichas y órdenes web (`/ordenes/{id}`, `/campo/{id}/*`) | permiso + `puedeVerFlujoDe`/`puedeGestionarFlujoDe` | `TalentoFieldFlowController.php:110-122, 161-164, 252-260, 289-292, 300-307`; `TalentoWorkOrderController::show` (249-262) | seguro (fila + permiso) |
| Asistencia, liquidación, rutas, inspecciones, penalizaciones, credenciales | filtro por árbol de supervisión | `TalentoAttendanceController::show` (68), `TalentoLiquidacionController::show` (207-212), `TalentoRouteController::show` (115), `TalentoQualityController::runIaAnalysis` (215), `TalentoPenaltyController::serveEvidencePhoto` (188), `TalentoCredentialController::serveDocument` (161) | seguro |
| `GET /talento/api/colaboradores/{id}` | solo `authorize('talento.view')`; **Vendedor, TECNICO y Mostrador tienen `talento.view`**; oculta los campos de expediente (sueldo, CURP, NSS, etc.) si no hay `talento.expediente.view` | `TalentoColaboradorController.php:687-704`, `795-808`, `Models/TalentoColaborador.php:37-41` | **débil (lectura limitada)**: cualquier técnico puede leer la ficha básica de otro colaborador. *(Actualizado tras auditoría UNI-T A5 (2026-10-26): la app del técnico no llama a esta ruta; el riesgo es de acceso directo por URL/API, no de uso normal.)* Los datos económicos y personales sí quedan ocultos |
| `GET /talento/media/{id}` (evidencia de OT) | solo permiso (`talento.media.view`: Mostrador, TECNICO, admins); sin comprobar que la OT sea suya | `TalentoFieldFlowController.php:195-207` | **débil**: un técnico con el id de otra evidencia la descarga |
| `GET /talento/practical-evidence/{id}` | solo `talento.academy.view` (Mostrador, TECNICO) | `TalentoAcademyController.php:342-346` | **débil** |
| `GET /api/proyectos/{id}` (móvil: `proyectoDetalle`) | cualquier colaborador real; incluye participantes | `TalentoProjectController.php:87-97` | **débil por diseño** (documentado en el controlador) |
| `/talento/api/colaboradores/{id}/avance` | solo `talento.liquidation.view` (admins) | `TalentoLiquidacionController.php:275-277` | seguro (permiso de supervisión) |

### 4.2 Fuera de Talento

| Módulo | Endpoints con id | Control encontrado | Veredicto |
|---|---|---|---|
| Scheduling (tareas) | 19 rutas `scheduling/task/*/{id}` (editar, update, destroy, notas, archivos, `get-data-task`) | permiso por URL (`check_route_permission`); `getData`/`addNote`/`destroy` usan `find/findOrFail($id)` sin verificar asignado ni equipo | **débil**: acceso horizontal posible a cualquier tarea con el permiso de la ruta (`TaskController.php:232-238, 348-350, 437-451`) |
| Vendedores (cortes) | `sellers/cuts/get-user-current-box/{id}`, `close-user-current-box/{id}`, `cuts/{id}` | `User::find($id)` / `CutBox::find($id)`; el `id` es de otro usuario o caja y no se compara con el autenticado | **débil** (`BoxController.php:18-45, 60-63`); además `find()` sin null-check → 500 con id inexistente |
| Vendedores | `sellers/seller/editar|update|destroy/{id}` y `sellers/cuts/*` | permiso por URL | **no verificado** a nivel de fila |
| Tickets | `tickets/abiertos/{client_id}` | permiso por URL; por cliente, no por usuario | **no verificado** (el alcance por cliente es lo normal en mostrador) |
| Bitácora | `/get-activities-by-user/{id}`, `/get-all-activities` | permiso por URL | **no verificado** a nivel de fila |
| Notificaciones | `/read-notification/{id}` | busca solo entre las `unreadNotifications` del autenticado → seguro, pero con id ajeno/ya leída hace `markAsRead()` sobre null → **roto (500)** | `NotificationController.php:44-50` |

---

## 5. Notificaciones

### 5.1 Infraestructura existente

| Pieza | Qué es | Datos (dev) | Estado |
|---|---|---|---|
| `notifications` (Laravel, canal database) vía `App\Notifications\StandardNotification` | campana del topbar | 14,943 (10,283 sin leer); tipos: StandardNotification 13,642, TicketOpen 1,301 | **funciona**, con leído/no leído (`read_at`) |
| Rutas de campana | `/read-all-notifications`, `/read-notification/{id}`, `/notifications/count` (poller del badge, item #9990615) | | funciona (ver bug de 4.2) |
| `task_notifications` | avisos de tareas del Scheduling | 3,661 | **duplicado** respecto a la campana; propio de Scheduling (`task/read-notification`, `unread-notification`) |
| `general_notifications` | | 0 | sin uso |
| `app/Modules/Core/Notifications` | solo registro/baja de tokens FCM: `POST /api/push-tokens`, `DELETE /api/push-tokens/{token}`, tabla `push_tokens` (item #627), comando de purga | `push_tokens`: 0 filas | **incompleto**: guarda tokens pero **no hay emisor FCM**; cero consumidores del envío |
| Tokens duplicados | `talento_device_tokens` (+ `POST /talento/api/devices/token`) y `fleet_driver_push_tokens` | 0 filas ambos | **duplicado** (tres tablas de token push) |
| Flotas | `FleetNotificationDispatcher` con canales email/WhatsApp (y `PushChannel` esbozado), log `fleet_notification_log`, preferencias por usuario/vehículo/geocerca | | funciona para geocercas; patrón de dispatcher + canales **reutilizable** |
| Facturación | `billing_notifications`, `config_finance_notifications` | | propio de cobranza |
| Otros | `referral_notification_*`, `mikrotik_notifications`, `MonitoreoOficial` | | propios de cada módulo |
| Gateway WhatsApp | `WhatsAppAgent/Services/WhatsAppGateway.php` (`sendText` → `EvolutionApiService::sendAndLog`, cola) | | funciona; gateway único designado en CLAUDE.md |

### 5.2 Cómo se bloquea la salida real fuera de producción

| Canal | Mecanismo | Evidencia | Estado |
|---|---|---|---|
| Correo | intercepta y reescribe a **un** destinatario, fallo cerrado si falta destino; no usa `APP_ENV` (dev y prod declaran `local`) | `config/mail.php:102-124` (`MAIL_INTERCEPTAR`, `MAIL_INTERCEPTAR_DESTINO`), `App\Listeners\Correo\InterceptarCorreoDev`, `EventServiceProvider.php:54-56` | funciona |
| WhatsApp | interruptor de salida `WHATSAPP_SENDER_ENABLED` (default false) más `WHATSAPP_FAKE` y `WHATSAPP_AUTO_REPLY` (default false); gatea el POST `sendText` en `EvolutionApiService` | `config/whatsapp.php:28-54` | funciona, pero **es un on/off global, no una intercepción a un destino de prueba** como el correo |
| Push | no existe emisor, nada que bloquear | | n/a |

### 5.3 Brechas frente a la sección 16 del plan

- No hay un **servicio único de eventos de notificación** (tipo + destinatario + canal + leído): cada módulo escribe en su propia tabla. De los 13 eventos de la sección 16 solo "nueva tarea" (task_notifications) y los tickets tienen notificación hoy; **nueva orden, cambio de horario, reprogramación, solicitud de aprobación, trabajo observado, garantía asignada, meta alcanzada, comisión validada e incentivo autorizado no tienen notificación**.
- La recomendación para la etapa de diseño es **extender `notifications` + `StandardNotification` (ya con leído/no leído y campana)** y colgar de ahí los canales, reutilizando el patrón dispatcher/canal de Flotas, y consolidar los tres almacenes de token push en `push_tokens`.

---

## 6. Auditoría

### 6.1 `log_name` y volumen (`activity_log`, 130,790 filas; 34,613 en los últimos 30 días)

| log_name | Filas | Notas |
|---|---|---|
| `default` | 130,571 | modelos con `LogsActivity` (vía `BaseModel`) |
| `devtools-terminal` | 199 | consola de DevTools |
| `facturacion-correcciones` | 10 | |
| `prefactura` | 9 | |
| `facturacion-envios` | 1 | |
| `talento`, `jarvis`, `portal_cliente_apariencia` | 0 en dev | el código escribe esos nombres (`FieldFlowService.php:354`, Asistente, Portal) pero en dev no hay filas |

Modelos que más registran: `Client` 21,613, `Balance` 14,255, `ClientInvoice` 5,912, `Payment` 5,898, `User` 364. Eventos: `updated` 35,713, `created` 12,325, `deleted` 4; 82,739 filas sin evento (log manual).

### 6.2 Tablas de historial por dominio (no unificadas)

`talento_compensation_rule_history`, `history_sellers_rules`, `history_general_configuration_rule`, `client_historial_cambios`, `mapared_historial`, `user_role_change_audits` (cambios de rol de usuario), `talento_health_bonus_log`, `talento_reconciliacion_log`, `log_activities` (3,991, bitácora de CRM/cliente), `fleet_client_audit_log`, `api_mobile_logs`.

### 6.3 Cobertura de eventos de la sección 15

| Evento | Cobertura hoy | Estado |
|---|---|---|
| Creación de tareas / notas | `log_activities` y `ObservationTask` | incompleto |
| Asignaciones y cambios de responsable | no hay registro propio (la OT/tarea cambia en sitio) | **no cubierto** |
| Reprogramaciones | no hay registro propio | **no cubierto** |
| Inicio/cierre de órdenes, evidencias, validaciones, rechazos | columnas de estado y media en `talento_work_order*`; no hay `status_history` (la tabla `talento_work_order_status_history` no existe) y solo 2 de 60 modelos de Talento usan `LogsActivity` | incompleto |
| Modificación de metas | no existe aún el concepto de meta | pendiente del diseño |
| Registro de ventas / comisiones | `history_sellers_rules` (reglas), `TransactionSeller` | incompleto |
| Cálculo de comisiones, aprobación de incentivos | `talento_health_bonus_log`, `talento_compensation_rule_history` | parcial |
| Cambios de reglas | `talento_compensation_rule_history`, `history_sellers_rules` | funciona (por dominio) |
| Cierre de períodos | `talento_liquidations` con UNIQUE (colaborador, period_start, period_end) | funciona como dato; sin evento de auditoría uniforme |
| Valor anterior/nuevo y motivo | solo en `activity_log` (`properties`) y en tablas de historial; no hay campo de "motivo" común | incompleto |
| "Una edición ordinaria no borra historia de responsabilidad" | las tablas de historial son append; no se verificó que los modelos operativos usen soft deletes en todos los casos | **no verificado** |

---

## 7. API

### 7.1 Autenticación de las apps actuales

| Aspecto | Talento (`/talento/api`) | MegaFamilia (`/api` del módulo) | Evidencia |
|---|---|---|---|
| Esquema | Sanctum Bearer | Sanctum Bearer | `Talento/routes.php:514`; `MegaFamilia/Controllers/ApiController.php:243, 1085` |
| Emisión | `createToken('talento-mobile')` en `POST /talento/api/auth/login`; borra los anteriores (un dispositivo activo) | `createToken($deviceName)`; token de hijo con `abilities` `['hijo','device:N']` | `TalentoMobileApiController.php:75-77` |
| Verificación de contraseña | `PasswordService::check` (acepta bcrypt y base64 legacy) con rehash al entrar | propio del módulo | `TalentoMobileApiController.php:53-62` |
| Vigencia | **sin expiración**: `config/sanctum.php:33` `'expiration' => null`; los 31 tokens vigentes en dev no expiran (31 de 31 sin `expires_at`) | igual | consulta a `personal_access_tokens` |
| Abilities | ninguna (token con `*`) | sí, solo en el token de hijo | |
| Limitación de intentos | **ninguna** en el login de Talento (la ruta se registra con `->middleware([])`; las rutas de módulo no reciben el grupo `api`) | no verificado | `Talento/routes.php:491-493` |
| Estado del usuario | el login valida que exista colaborador activo, **no** que `users.estado` sea `activo` (el web sí lo exige) | n/a | `TalentoMobileApiController.php:64-72` |
| Emisión genérica de tokens | `CatalogoApiController` permite crear tokens con abilities y expiración opcional (`expires_in_days` nullable → sin expiración por defecto) | | `CatalogoApiController.php:55-75` |

### 7.2 Contrato

| Aspecto | Hallazgo | Estado |
|---|---|---|
| Formato de respuesta | tres formas coexisten: `{message}` / `{error}` (Talento), `{success, ...}` (MegaFamilia, Catálogo), y respuestas crudas del modelo | **incompleto** (sin sobre estándar) |
| Errores | Talento devuelve 401/403/422 con texto en español; sin código de error máquina | incompleto |
| Paginación | `paginate(` aparece **0 veces** en los dos controladores móviles de Talento; el historial usa filtros/páginas propias | **incompleto** |
| Versionado | no hay prefijo `/v1` en ninguna ruta de API | **incompleto** |
| Cargas de archivo | evidencia: `foto` requerida, `mimes:jpg,jpeg`, `max:10240`, `client_uuid` (idempotencia de reenvío), watermark y GPS en servidor | **funciona** y es reutilizable |
| Descargas protegidas | enlace firmado de 10 min para el PDF de credencial (`signed`) | funciona |
| Documentación | solo comentarios en `routes.php`; no hay OpenAPI. Existe `docs/contratos/` para otros contratos | **incompleto** |
| Servicios de negocio reutilizables | `OrdenTrabajoUnifiedService`, `Actor`, `AttendanceService`, `LiquidationService::countBillableUnits` (punto único del conteo) son compartidos por web y móvil | **funciona** (cumple "mismos servicios que la web") |
| Seguridad por usuario | resuelta con `resolveColaborador()` + `Actor` (ver sección 4) | funciona en Talento |

### 7.3 Qué se puede reutilizar para la app futura y qué falta

Reutilizable: Sanctum + `Actor`/`resolveColaborador`, `OrdenTrabajoUnifiedService`, la carga de evidencia con `client_uuid`, el enlace firmado, `PushToken` y su comando de purga, el patrón dispatcher/canal de Flotas.
Falta: expiración y renovación de tokens (hoy permanentes), throttle del login, validación de `estado` activo, sobre de respuesta y código de error únicos, paginación estándar, prefijo de versión, documentación de contrato, emisor FCM, y revocar tokens al cambiar contraseña o dar de baja (**no verificado** si hoy se revocan).

---

## 8. Tabla funciona / incompleto / duplicado / roto

| # | Tema | Estado | Nota |
|---|---|---|---|
| 1 | Una cuenta por colaborador (`user_id` UNIQUE) | funciona | 39/39 |
| 2 | Puente `colaborador_id` en clientes | incompleto | 0 filas pobladas; flags apagados; 9 cuentas huérfanas estructurales |
| 3 | `sellers` frente a `talento_colaboradores` | duplicado | dos tablas con el mismo `users.id`; 7 vendedores sin colaborador |
| 4 | Comando `identidad:verificar-consistencia` | funciona | solo lectura |
| 5 | Roles de Spatie + permiso por URL | funciona | rol ∪ directos |
| 6 | Puesto, departamento, capacidad | incompleto | puesto = rol; departamento texto libre; sin capacidades |
| 7 | Rol `Super Administrador` frente a `super-administrator` | duplicado | pendiente de Fase 4 |
| 8 | Roles `ADMINISTRADOR_COMPLETO` y `SUPERVISOR_MOSTRADOR` sin usuarios | incompleto | decidir si se conservan |
| 9 | Permisos directos residuales | incompleto | 5,243 filas en 40 usuarios (Fase 3b) |
| 10 | `portal.colaborador` solo directo (20) | incompleto | falta en el rol base |
| 11 | Acceso horizontal en Talento | funciona (con 4 excepciones débiles) | ficha básica, media de OT, evidencia práctica, proyectos |
| 12 | Acceso horizontal en Scheduling y Vendedores (cortes) | **incompleto** | solo permiso de ruta |
| 13 | `/read-notification/{id}` con id inválido | roto | 500 por `markAsRead()` sobre null |
| 14 | Campana de notificaciones (`notifications`) | funciona | con leído/no leído |
| 15 | `task_notifications`, `general_notifications`, tablas de aviso por módulo | duplicado | cuatro almacenes |
| 16 | Tokens push | duplicado / incompleto | tres tablas, 0 filas, sin emisor FCM |
| 17 | Bloqueo de salida real: correo | funciona | intercepta a un destino |
| 18 | Bloqueo de salida real: WhatsApp | funciona (on/off) | sin intercepción a destino de prueba |
| 19 | Auditoría de Clientes/Pagos/Saldos | funciona | `activity_log` |
| 20 | Auditoría de Talento operativo | incompleto | 0 filas de modelos de Talento; sin historial de estados de OT |
| 21 | Eventos de la sección 15 (asignación, reprogramación, metas) | incompleto | sin registro uniforme |
| 22 | Autenticación móvil (Sanctum) | funciona | tokens sin expiración, login sin throttle |
| 23 | Contrato de API (formato, errores, paginación, versión) | incompleto | tres formatos, sin `/v1`, sin paginación |
| 24 | Carga segura de evidencia | funciona | mime, tamaño, idempotencia, GPS, watermark |
| 25 | Servicios de negocio compartidos web/móvil | funciona | `OrdenTrabajoUnifiedService`, `Actor` |
| 26 | Convención jerárquica de permisos (#538) | incompleto | solo Dashboard |

---

## 9. Qué NO se pudo verificar (dicho explícitamente)

- Personas duplicadas entre `users`, `sellers` y `talento_colaboradores` por nombre/CURP/correo: requiere cruzar datos personales; se dejó como pregunta.
- Si los 9 correos repetidos de `users` son personas reales o cuentas de prueba/espejo.
- Control a nivel de fila en `sellers/seller/*`, `tickets/*`, `get-activities-by-user/{id}`: solo se confirmó el permiso por URL.
- Si los tokens de Sanctum se revocan al cambiar contraseña o desactivar al usuario.
- Throttle del login de MegaFamilia.
- Estado en **producción**: todo lo anterior son conteos de dev (los ids y volúmenes divergen de prod; CLAUDE.md, regla de ids dev≠prod).

---

## Preguntas para Irving

**P1. ¿Qué se hace con las 9 cuentas dueñas de ventas que no tienen colaborador (la mayor concentra ~48% de las filas con `seller_id`)?**
- A) Crear expediente "ligero" de colaborador para las que sean personas reales y marcar como "cuenta de sistema/administración" las demás (excluidas del puente).
  - Pros: el backfill cubre todo lo real; reutiliza el patrón `expedienteVendedor` de #9990360. Contras: requiere que Irving confirme cuáles son personas.
- B) Dejarlas fuera y mostrar "sin colaborador" en Mi Espacio.
  - Pros: cero trabajo. Contras: las ventas históricas de esas cuentas no aparecen en ningún Mi Espacio.
- **Recomendación: A**, con lista entregada por conteo (sin nombres) para que Irving la confirme.

**P2. ¿Se enciende `IDENTIDAD_DOBLE_ESCRITURA_COLABORADOR` en dev ya, antes de resolver P1?**
- A) Sí, ahora: las altas nuevas ya nacen con `colaborador_id` y el backfill se hace después.
  - Pros: deja de crecer la deuda; reversible (flag). Contras: hasta resolver P1 las huérfanas siguen sin puente.
- B) Esperar a P1.
  - Pros: un solo paso ordenado. Contras: el hueco sigue creciendo.
- **Recomendación: A** (el flag es aditivo y reversible).

**P3. ¿Dónde vive el permiso base de Mi Espacio (`portal.colaborador`)?**
- A) En cada rol de campo/oficina (Vendedor, TECNICO, Mostrador, Almacen, Administrador) y no por usuario.
  - Pros: cualquier alta futura lo hereda; coincide con "una cuenta por colaborador". Contras: toca permisos de roles (frontera dura: lo aplica Irving o con su aprobación).
- B) Mantener la asignación directa por observer.
  - Pros: ya funciona. Contras: es el mismo patrón de directos que la Fase 3b quiere eliminar.
- **Recomendación: A**, en una migración aditiva aparte.

**P4. Acceso horizontal débil (Scheduling, cortes de caja, ficha básica de colaborador, media de OT): ¿se cierra antes o durante Mi Espacio?**
- A) Antes: una pasada corta que agregue "asignado a mí o en mi equipo o permiso de supervisión" a esos endpoints.
  - Pros: Mi Espacio nace sin fugas. Contras: toca controladores con consumidores existentes (se avisa y se prueba).
- B) Durante, módulo por módulo.
  - Pros: menor riesgo por entrega. Contras: Mi Espacio expondría rutas con el hueco abierto durante un tiempo.
- **Recomendación: A** para Scheduling y cortes de caja (dinero); B para el resto.

**P5. Notificaciones: ¿se consolida sobre `notifications` + `StandardNotification` (campana actual) con canales del patrón Flotas?**
- A) Sí; unificar los tres almacenes de token push en `push_tokens` y dejar `task_notifications` como vista de compatibilidad.
  - Pros: una sola bandeja leído/no leído; push futuro sin rehacer. Contras: migrar el consumo de Scheduling.
- B) Crear una infraestructura nueva de eventos.
  - Pros: diseño limpio. Contras: es exactamente la duplicación que la épica quiere evitar.
- **Recomendación: A.**

**P6. Seguridad de la API móvil: ¿se aprueba ponerle vigencia a los tokens, throttle al login y exigir usuario activo, antes de abrir la app futura?**
- A) Sí: expiración con renovación, `throttle` en login, validar `estado` activo y revocar tokens al cambiar contraseña o dar de baja.
  - Pros: cierra tokens permanentes (el plan los prohíbe). Contras: obliga a re-iniciar sesión en la app actual al entrar en vigor; toca autenticación (frontera dura).
- B) Posponer a la etapa de la app.
  - Pros: no molesta a la app de hoy. Contras: sigue habiendo 31 tokens sin expiración.
- **Recomendación: A**, con fecha de corte comunicada.

**P7. Contrato de API: ¿se define un sobre único y versión `/v1` solo para lo nuevo, o también se migra lo existente?**
- A) Solo lo nuevo (Mi Espacio y la app futura); lo actual de Talento queda congelado.
  - Pros: no rompe la APK en uso. Contras: dos formatos conviviendo un tiempo.
- B) Migrar todo con compatibilidad dual.
  - Pros: un solo contrato. Contras: más trabajo y riesgo para la APK actual.
- **Recomendación: A.**

**P8. ¿Qué es "departamento" y "capacidad profesional" en el modelo?**
- A) Catálogos propios (`departamentos`, `capacidades`) ligados al colaborador, separados del rol de seguridad.
  - Pros: cumple la separación del plan (puesto/departamento/capacidad distintos del rol). Contras: dato nuevo que capturar.
- B) Seguir con rol = puesto y texto libre de departamento.
  - Pros: cero trabajo. Contras: no se pueden hacer reportes ni reglas de asignación por capacidad.
- **Recomendación: A**, en la etapa de diseño del Documento correspondiente.
