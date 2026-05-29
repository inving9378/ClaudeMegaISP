═══════════════════════════════════════════════════════
REPORTE DE AUDITORÍA — MEGAFAMILIA vs ALCANCE FUNCIONAL
Fecha: 2026-05-26
═══════════════════════════════════════════════════════

── RESUMEN EJECUTIVO ───────────────────────────────────
Total funciones del alcance (agrupadas):   55
Implementadas completamente:               10  (18%)
Implementadas parcialmente:                22  (40%)
No implementadas:                          23  (42%)
════════════════════════════════════════════════════════

Nota de metodología: se auditaron los siguientes recursos:
  Flutter:  mobile/megafamilia/lib/**/*.dart  (41 archivos)
  API PHP:  app/Modules/Addons/MegaFamilia/   (31 endpoints)
  Admin:    resources/js/components/module/megafamilia/*.vue  (20 componentes)
  BD:       migraciones (24 archivos, ejecutadas)

════════════════════════════════════════════════════════

── SECCIÓN 1: APP PADRE ────────────────────────────────

1.1 LOGIN / AUTENTICACIÓN
⚠️  PARCIAL  — Login con email + contraseña
               | Implementado: POST /api/megafamilia/auth/login (email+password)
               | Falta: login por número de cliente, por teléfono, OTP SMS/WhatsApp/email
               | Archivo: ApiController.php:101, login_screen.dart
               | ALERTA SEGURIDAD: La comparación de contraseña es plain-text
               |   ($data['password'] !== $cmi->password) — no usa Hash::check()

❌  FALTANTE  — OTP por SMS / WhatsApp / email
               | Impacto: alto — requerido por alcance para 2FA
               | Complejidad: media — requiere integración Twilio/WhatsApp Business API

1.2 DASHBOARD PRINCIPAL
⚠️  PARCIAL  — Dashboard cliente
               | Implementado: nombre, contrato, plan, estado servicio, dirección,
               |   saldo total a pagar, fecha próximo pago, botón pago rápido,
               |   tickets abiertos, acceso a control parental
               | Archivo: cliente_dashboard.dart, ApiController.php:149 (servicio())
               | Falta: consumo de datos (% del plan), alerta de suspensión destacada,
               |   saldo expresado como campo separado (actualmente fusionado con facturas)

1.3 PAGOS
⚠️  PARCIAL  — Módulo de pagos
               | Implementado: saldo pendiente, historial de pagos, subir comprobante SPEI
               |   (image_picker), CLABE para transferencia, notificar transferencia
               | Archivos: pagos_screen.dart, ApiController.php:322–399,
               |   GET /payments/clabe, POST /payments/notify-transfer
               | Falta: descargar comprobantes (PDF), recordatorios automáticos de pago,
               |   suspensión programada visible, pago en línea con tarjeta/Mercado Pago

1.4 FACTURAS
⚠️  PARCIAL  — Módulo de facturas
               | Implementado: ver listado, filtrar por estado (todas/pagadas/pendientes),
               |   detalle de factura (factura_detail_screen.dart)
               | Archivos: facturas_screen.dart, factura_detail_screen.dart,
               |   ApiController.php:263 (facturas())
               | Falta: descargar PDF, descargar XML (CFDI), solicitar factura fiscal,
               |   actualizar RFC / datos fiscales

1.5 CONSUMOS Y VELOCIDAD
❌  FALTANTE  — Pantalla de consumos
               | Impacto: alto — prometida en alcance
               | Complejidad: alta — requiere integración con Mikrotik/RADIUS para
               |   consumo real por cliente; fl_chart ya está instalado en pubspec
               | Tablas: no existe tabla parental_reports ni endpoints de consumo

1.6 TICKETS DE SOPORTE
⚠️  PARCIAL  — Sistema de tickets
               | Implementado: crear ticket con categoría y descripción (7 categorías),
               |   ver listado con estado, detalle de ticket
               | Archivos: tickets_screen.dart, new_ticket_screen.dart,
               |   ApiController.php:191–261
               | Falta: adjuntar fotos al ticket (UI tiene campo _hasPhoto pero no
               |   implementa image_picker en tickets), ver respuestas del técnico
               |   (la API no retorna threads), calificar/puntuar ticket

1.7 NOTIFICACIONES PUSH
❌  FALTANTE  — Push notifications
               | firebase_messaging y firebase_core explícitamente comentados en pubspec.yaml
               |   (comentario: "requieren google-services.json — se añaden en iteración posterior")
               | FcmService.php existe en backend (Services/FcmService.php) pero sin app token
               | Impacto: crítico — recordatorios de pago, alertas seguridad familiar,
               |   notificaciones de solicitud hijo→padre son inoperables sin FCM
               | Complejidad: media — backend listo, falta google-services.json y
               |   configurar firebase_messaging en Flutter

1.8 CONTROL PARENTAL — ACTIVACIÓN Y PLANES
⚠️  PARCIAL  — Activación de control parental
               | Implementado: pantalla control_parental_screen.dart muestra lista de
               |   perfiles hijos con resumen (nombre, edad, dispositivos)
               | Falta: flujo de activación/contratación de plan (Básico/Plus/Premium),
               |   aceptación de términos y condiciones (TerminosController.php existe
               |   pero no está conectado a la app), verificación de licencia activa

1.9 PERFILES DE HIJOS
⚠️  PARCIAL  — Gestión de perfiles
               | Implementado: crear perfil (nombre, edad) vía POST /profiles,
               |   listar perfiles con devicesCount
               | Archivos: control_parental_screen.dart ("Añadir perfil" → SnackBar "próximamente"),
               |   ApiController.php:533 (storeProfile)
               | Falta: foto de perfil, nivel escolar, tipo de perfil,
               |   horarios (tab Horarios en child_detail es placeholder),
               |   reglas personalizadas, editar/eliminar perfil desde la app

1.10 VINCULACIÓN DE DISPOSITIVOS
⚠️  PARCIAL  — Vinculación QR / código numérico
               | Implementado: mobile_scanner instalado (pubspec), API POST /devices/link
               | Falta: pantalla dedicada de vinculación; no hay ruta ni screen para
               |   escanear QR o ingresar código — el router.dart no incluye esta pantalla

1.11 DASHBOARD FAMILIAR
⚠️  PARCIAL  — Vista familiar del padre
               | Implementado: lista de hijos, conteo de dispositivos, tiempo usado hoy,
               |   lista de apps con minutos de uso, acceso al detalle del hijo
               | Archivos: child_detail_screen.dart (tabs: Resumen, Apps, Horarios, Tareas, Ubicación)
               | Falta: estado de conexión en tiempo real, alertas del día (tab sin datos),
               |   badge de solicitudes pendientes del hijo, resumen de tareas cumplidas,
               |   datos reales de apps (el endpoint /profiles/{id} devuelve `apps: []` por defecto)

1.12 CONTROL DE TIEMPO
⚠️  PARCIAL  — Límites de tiempo
               | Implementado: GET/PUT /devices/{id}/rules (limit_minutes diario),
               |   visualización circular de tiempo usado/restante en child_detail,
               |   botón "Dar tiempo extra" (SnackBar placeholder)
               | Falta: límites por app individual, límites por categoría, horario escolar
               |   (lun-vie), horario fin de semana diferenciado, tiempo extra con
               |   opciones 15/30/60 min o personalizado (implementación real, no placeholder)

1.13 BLOQUEO DE APLICACIONES
❌  FALTANTE  — Control de apps desde la app padre
               | Tab "Apps" en child_detail solo muestra historial de uso (minutos)
               | No hay UI para bloquear/permitir apps individuales
               | parental_app_blocks tabla existe, AppBlock modelo existe
               | Impacto: crítico para el diferenciador del producto
               | Complejidad: muy alta (ver Riesgos Técnicos §1)

1.14 CATEGORÍAS DE APLICACIONES
❌  FALTANTE  — Clasificación por categoría
               | Juegos, redes sociales, video, mensajería, educación, navegadores,
               |   compras, productividad, apps desconocidas
               | No hay modelo ni endpoint de categorías en la API móvil

1.15 SOLICITUDES DE DESBLOQUEO (flujo padre)
⚠️  PARCIAL  — Padre ve y responde solicitudes
               | Implementado: GET /requests/pending, POST /requests/{id}/respond,
               |   ApiController.php:626–657
               | Falta: pantalla dedicada en la app padre para ver/aprobar/rechazar
               |   solicitudes (no hay pantalla "solicitudes" en screens/cliente/)
               |   — el padre actualmente no puede responder desde la app móvil

1.16 CONTROL WEB / FILTROS
❌  FALTANTE  — Filtro de contenido web
               | parental_web_blocks tabla y modelo existen
               | No hay pantalla ni endpoints API móvil para gestionar filtros web
               | Impacto: alto — diferenciador del plan Plus
               | Complejidad: muy alta (ver Riesgos Técnicos §3)

1.17 LISTAS BLANCA / NEGRA DE SITIOS
❌  FALTANTE  — Dominios permitidos/bloqueados
               | Depende del control web (1.16)

1.18 REPORTES DE NAVEGACIÓN
❌  FALTANTE  — Historial de sitios visitados/bloqueados
               | No hay tabla parental_reports (requerida por alcance)
               | No hay endpoints API para reportes de navegación

1.19 UBICACIÓN DEL HIJO
⚠️  PARCIAL  — Localización
               | Implementado: POST /locations (reporte de ubicación), GET /profiles/{id}/location,
               |   parental_locations tabla y modelo existen
               | Falta: mapa visual (google_maps_flutter comentado en pubspec),
               |   tab Ubicación en child_detail muestra placeholder "Requiere google_maps_flutter"
               |   No hay permisos de ubicación en AndroidManifest.xml

1.20 ZONAS SEGURAS (GEOCERCAS)
❌  FALTANTE  — Geocercas desde app padre
               | Admin tiene MegaFamiliaGeofences.vue (531 líneas) y parental_geofences tabla
               | La app padre no tiene pantalla de geocercas
               | Impacto: solo disponible en plan Premium

1.21 BOTÓN DE AYUDA DEL HIJO
❌  FALTANTE  — SOS / botón de emergencia
               | No implementado en ninguna pantalla ni endpoint

1.22 SISTEMA DE TAREAS (flujo padre)
⚠️  PARCIAL  — Creación y validación de tareas
               | Implementado: padre puede crear tareas vía panel admin web (MegaFamiliaTareas.vue),
               |   GET /profiles/{id}/tasks, POST /tasks/{id}/complete (lado hijo),
               |   POST /megafamilia/tareas/{id}/approve y reject (admin web)
               | Falta: crear tareas desde la app móvil padre (no hay pantalla),
               |   foto/comentario al completar tarea, validación padre→aprueba/rechaza
               |   desde la app móvil (solo funciona desde el panel web admin)

1.23 GAMIFICACIÓN
⚠️  PARCIAL  — Puntos, medallas, racha
               | Implementado: HijoProvider tiene points y streakWeeks (cargados desde API),
               |   logros_screen.dart muestra puntos, racha y grid de medallas
               | Falta: medallas dinámicas (actualmente hardcodeadas en logros_screen.dart:
               |   `static const _badges = [...]`), niveles progresivos, ranking familiar,
               |   premios canjeables, parental_rewards tabla existe pero no se usa en la app

════════════════════════════════════════════════════════

── SECCIÓN 2: APP HIJO ─────────────────────────────────

2.1 VINCULACIÓN POR QR O CÓDIGO
⚠️  PARCIAL  — Registro del dispositivo hijo
               | Implementado: mobile_scanner instalado, API /devices/link existe
               | Falta: pantalla de onboarding/vinculación; el router.dart no define
               |   ruta para esta pantalla; la app inicia directamente en login

2.2 PANTALLA PRINCIPAL DEL HIJO
⚠️  PARCIAL  — Dashboard hijo
               | Implementado: tiempo restante (circular, desde HijoProvider),
               |   grid de apps permitidas (hardcoded 8 apps estáticas),
               |   tareas pendientes (3 primeras, desde API),
               |   navegación a Tareas/Logros/Solicitar
               | Archivo: hijo_dashboard.dart
               | Falta: apps permitidas dinámicas desde la API (actualmente YouTube/
               |   Juegos/etc. hardcodeados), mensajes del padre, botón de ayuda/SOS

2.3 PANTALLA DE BLOQUEO
⚠️  PARCIAL  — Pantalla de bloqueo de app
               | Implementado: UI completa con motivo, timer countdown, botón solicitar permiso
               | Archivo: blocked_screen.dart
               | Falta: timer real desde la API (actualmente hardcoded en 47:21),
               |   motivo dinámico desde API (actualmente "Horario familiar" fijo),
               |   mensaje educativo configurable
               | Nota crítica: la pantalla de bloqueo en Flutter solo se puede mostrar
               |   si la app está activa. El bloqueo real de apps del sistema requiere
               |   Accessibility Service o Device Admin (ver Riesgos §1)

2.4 SOLICITUDES (HIJO → PADRE)
⚠️  PARCIAL  — Flujo de solicitudes
               | Implementado: SolicitarScreen con 3 tipos (time_extra, app_unlock, web_unlock),
               |   mensaje opcional, POST /requests, notificación SnackBar de confirmación
               | Archivo: solicitar_screen.dart, ApiController.php:626
               | Falta: tipo "cambiar horario", confirmar tarea completada con foto/comentario
               |   (TareasScreen solo tiene botón "Hecha" sin imagen ni descripción adicional)

2.5 PROTECCIÓN DE DESINSTALACIÓN
❌  FALTANTE  — Device Admin / PIN de desinstalación
               | No hay nada implementado: sin AndroidManifest receiver, sin DeviceAdmin,
               |   sin PIN padre para desactivar
               | Impacto: crítico para la utilidad del control parental
               | Complejidad: alta (ver Riesgos §2)

════════════════════════════════════════════════════════

── SECCIÓN 3: MÓDULO ADMIN MEGAISP ─────────────────────

3.1 DASHBOARD ADMIN
✅  COMPLETO  — Dashboard con KPIs
               | Implementado: clientes totales/activos, licencias activas/por vencer,
               |   dispositivos online, alertas sin leer, ingresos del mes, distribución
               |   por plan, alertas recientes
               | Archivos: DashboardController.php, MegaFamiliaDashboard.vue (189 líneas)

3.2 GESTIÓN DE CLIENTES
✅  COMPLETO  — CRUD clientes con control parental
               | Implementado: buscar (por nombre/email/plan/estado), ver detalle,
               |   activar/suspender/cancelar control parental, cambiar plan,
               |   ver dispositivos y perfiles del cliente
               | Archivos: ClientesController.php, MegaFamiliaClientes.vue (483 líneas)

3.3 GESTIÓN DE PLANES
✅  COMPLETO  — Planes Básico / Plus / Premium
               | Implementado: CRUD completo, toggle activo/inactivo, precios,
               |   límites (padres, hijos, dispositivos), features por plan
               | Archivos: PlanesController.php, MegaFamiliaPlanes.vue (400 líneas)
               | Seeder: ParentalPlansSeeder.php crea los 3 planes del alcance

3.4 GESTIÓN DE LICENCIAS
✅  COMPLETO  — Licencias por cliente
               | Implementado: crear, asignar a cliente, renovar, suspender/reactivar,
               |   KPIs (total/activas/por vencer/vencidas), filtros por plan/estado/fecha
               | Archivos: LicenciasController.php, MegaFamiliaLicencias.vue (531 líneas)

3.5 REPORTES ADMIN
✅  COMPLETO  — Reportes exportables
               | Implementado: reportes de uso por perfil, exportar a PDF
               | Archivos: ReportesController.php, MegaFamiliaReportes.vue (322 líneas)

3.6 MÓDULOS ADICIONALES ADMIN (extras al alcance)
✅  EXTRA     — Auditoría de eventos (AuditoriaController + MegaFamiliaAuditoria.vue)
✅  EXTRA     — Gestión de alertas (AlertasController + MegaFamiliaAlertas.vue)
✅  EXTRA     — Geofences (GeofencesController + MegaFamiliaGeofences.vue)
✅  EXTRA     — Ubicaciones en tiempo real (UbicacionesController + MegaFamiliaUbicaciones.vue)
✅  EXTRA     — Solicitudes de hijos (SolicitudesController + MegaFamiliaSolicitudes.vue)
✅  EXTRA     — Tareas (TareasController + MegaFamiliaTareas.vue)
✅  EXTRA     — Notificaciones push (NotificacionesController + MegaFamiliaNotificaciones.vue)
✅  EXTRA     — Mikrotik (MikrotikController — integración de red)
✅  EXTRA     — Ingresos (IngresosController + MegaFamiliaIngresos.vue, PDF export)
✅  EXTRA     — Soporte técnico (SoporteController)
✅  EXTRA     — OTA App updates (AppVersionController + MegaFamiliaConfiguracion.vue)

Nota: el módulo admin supera el alcance — tiene más funcionalidad que la spec requiere.

════════════════════════════════════════════════════════

── SECCIÓN 4: API CENTRAL ──────────────────────────────

IMPLEMENTADOS (31 endpoints):
✅  POST   /api/megafamilia/auth/login              — autenticación email+password → token
✅  GET    /api/megafamilia/app-version             — OTA check (público)
✅  GET    /api/megafamilia/mobile/check-update     — OTA check alternativo (público)
✅  GET    /api/megafamilia/mobile/download/{id}    — descarga APK (público)
✅  GET    /api/megafamilia/servicio                — info ISP del cliente
✅  GET    /api/megafamilia/account                 — datos de cuenta completos
✅  GET    /api/megafamilia/profile                 — perfil del usuario auth
✅  GET    /api/megafamilia/tickets                 — listado de tickets
✅  POST   /api/megafamilia/tickets                 — crear ticket
✅  GET    /api/megafamilia/facturas                — historial de facturas
✅  GET    /api/megafamilia/pagos                   — historial de pagos
✅  POST   /api/megafamilia/pagos                   — subir comprobante SPEI
✅  GET    /api/megafamilia/payments/clabe          — CLABE para transferencia
✅  POST   /api/megafamilia/payments/notify-transfer — notificar transferencia
✅  GET    /api/megafamilia/profiles                — listar perfiles hijos
✅  POST   /api/megafamilia/profiles               — crear perfil hijo
✅  GET    /api/megafamilia/profiles/{id}           — detalle de perfil + apps + tiempo
✅  GET    /api/megafamilia/profiles/{id}/devices   — dispositivos del perfil
✅  GET    /api/megafamilia/profiles/{id}/tasks     — tareas del perfil
✅  GET    /api/megafamilia/profiles/{id}/location  — última ubicación
✅  POST   /api/megafamilia/devices/link            — vincular dispositivo hijo
✅  GET    /api/megafamilia/devices/{id}/rules      — reglas del dispositivo
✅  PUT    /api/megafamilia/devices/{id}/rules      — actualizar reglas (tiempo límite)
✅  POST   /api/megafamilia/tasks/{id}/complete     — completar tarea (hijo)
✅  POST   /api/megafamilia/requests               — enviar solicitud (hijo)
✅  GET    /api/megafamilia/requests/pending       — solicitudes pendientes (padre)
✅  POST   /api/megafamilia/requests/{id}/respond  — responder solicitud (padre)
✅  POST   /api/megafamilia/locations              — reportar ubicación (hijo)
✅  GET    /api/megafamilia/tecnico/ordenes        — órdenes del técnico
✅  PUT    /api/megafamilia/tecnico/ordenes/{id}   — actualizar orden técnico
✅  GET    /api/megafamilia/hijo/tareas            — tareas (vista hijo)

FALTANTES (endpoints requeridos por el alcance):
❌  POST   /auth/login  (por número cliente / por teléfono / OTP)
❌  POST   /auth/otp/send     — enviar código OTP
❌  POST   /auth/otp/verify   — verificar código OTP
❌  GET    /facturas/{id}/pdf  — descargar factura PDF
❌  GET    /facturas/{id}/xml  — descargar factura CFDI/XML
❌  POST   /facturas/request   — solicitar factura fiscal
❌  PUT    /account/fiscal     — actualizar datos fiscales
❌  GET    /consumo/stats      — consumo mensual/diario/semanal
❌  GET    /consumo/devices    — dispositivos conectados al router
❌  GET    /consumo/speed      — historial de velocidad
❌  GET    /profiles/{id}/apps       — apps instaladas en dispositivo hijo
❌  POST   /profiles/{id}/apps/{pkg} — bloquear/permitir app específica
❌  GET    /profiles/{id}/web-rules  — reglas web activas
❌  POST   /profiles/{id}/web-rules  — crear/actualizar regla web
❌  GET    /profiles/{id}/categories — reglas por categoría de app
❌  POST   /profiles/{id}/schedule   — crear/editar horario escolar/fin de semana
❌  POST   /profiles/{id}/time-extra — dar tiempo extra al hijo
❌  POST   /tickets/{id}/attachment  — adjuntar foto a ticket
❌  POST   /tickets/{id}/rate        — calificar ticket
❌  GET    /geofences               — zonas seguras del padre
❌  POST   /geofences               — crear zona segura
❌  POST   /profiles/{id}/sos       — botón de ayuda hijo
❌  POST   /profiles/{id}/tasks     — crear tarea desde app padre
❌  PUT    /tasks/{id}/validate      — validar tarea (foto+comentario padre)
❌  GET    /notifications            — listado de notificaciones
❌  POST   /notifications/token      — registrar FCM token

════════════════════════════════════════════════════════

── SECCIÓN 5: BASE DE DATOS ────────────────────────────

Tablas requeridas por el alcance:
✅  EXISTE    — parental_accounts     | cuenta padre vinculada a user ISP
✅  EXISTE    — parental_profiles     | perfiles de hijos (nombre, edad, nivel, tipo)
✅  EXISTE    — parental_devices      | dispositivos vinculados por perfil
✅  EXISTE    — parental_rules        | reglas de tiempo por dispositivo
✅  EXISTE    — parental_app_blocks   | bloqueos de apps por perfil/dispositivo
✅  EXISTE    — parental_web_blocks   | filtros web (dominios, categorías)
✅  EXISTE    — parental_schedules    — horarios (escolar, fin de semana, personalizado)
✅  EXISTE    — parental_requests     | solicitudes hijo → padre (tipo, mensaje, estado)
✅  EXISTE    — parental_tasks        | tareas asignadas al hijo
✅  EXISTE    — parental_rewards      | recompensas (tiempo extra, app, puntos, medalla)
✅  EXISTE    — parental_locations    | historial GPS del hijo
✅  EXISTE    — parental_alerts       | alertas generadas (geofence, inusual, etc.)
✅  EXISTE    — parental_licenses     | licencias por cliente y plan
✅  EXISTE    — parental_events       | log de eventos del sistema
❌  FALTANTE  — parental_reports      | requerida por alcance (historial navegación,
               |   sitios visitados, bloqueados, intentos, hora, dispositivo)
               |   Nota: la tabla de reportes de navegación NO está en las migraciones

TABLAS EXTRAS (no en alcance original, implementadas):
✅  EXTRA     — parental_plans        | planes Básico/Plus/Premium con precios y límites
✅  EXTRA     — parental_geofences    | geocercas con coordenadas, radio y alertas
✅  EXTRA     — parental_consents     | registro de aceptación de T&C con firma
✅  EXTRA     — app_versions          | versiones de APK para distribución OTA

════════════════════════════════════════════════════════

── SECCIÓN 6: INFRAESTRUCTURA Y PERMISOS ───────────────

Plugin/Permiso               Estado     Notas
─────────────────────────────────────────────────────────
http / shared_prefs / provider  ✅     Base HTTP y estado funcional
go_router                       ✅     Navegación completa implementada
fl_chart                        ✅     Instalado, pero ninguna pantalla de consumo lo usa aún
mobile_scanner (QR)             ✅     Instalado, falta pantalla que lo invoque
image_picker                    ✅     Usado en pagos (comprobante); falta en tickets
local_auth (biometría)          ✅     Instalado, no activado aún en login
tutorial_coach_mark             ✅     Tour guiado del dashboard implementado
firebase_messaging (FCM)        ❌     Comentado en pubspec — falta google-services.json
firebase_core                   ❌     Comentado en pubspec
google_maps_flutter             ❌     Comentado en pubspec — falta Maps API key
geolocator                      ❌     No instalado — necesario para ubicación GPS hijo
flutter_local_notifications     ❌     No instalado — necesario para alertas en primer plano
─────────────────────────────────────────────────────────
Permisos AndroidManifest:
ACCESS_FINE_LOCATION            ❌     No declarado — GPS no funcionará
CAMERA                          ❌     No declarado — QR scanner y fotos no funcionarán
READ_MEDIA_IMAGES               ❌     No declarado — image_picker fallará en Android 13+
RECEIVE_BOOT_COMPLETED          ❌     No declarado — no hay servicio de reinicio
PACKAGE_USAGE_STATS             ❌     No declarado — imprescindible para saber qué apps usa el hijo
BIND_ACCESSIBILITY_SERVICE      ❌     No declarado — requerido para bloqueo real de apps
BIND_DEVICE_ADMIN               ❌     No declarado — requerido para protección de desinstalación
─────────────────────────────────────────────────────────
OTP SMS                         ❌     Sin integración (Twilio, AWS SNS, etc.)
OTP WhatsApp                    ❌     Sin integración (Meta Cloud API / Twilio)
OTP email                       ❌     Sin implementación de mailer OTP
Push notifications (FCM)        ❌     FcmService.php existe pero sin token de app
VPN/DNS local (filtro web)      ❌     No implementado — alternativa a Accessibility Service

════════════════════════════════════════════════════════

── PLAN DE TRABAJO RECOMENDADO ─────────────────────────

ETAPA 1 (1–2 semanas) — Infraestructura crítica que desbloquea todo lo demás:
  1. Configurar Firebase (google-services.json) e integrar firebase_messaging en Flutter
  2. Agregar permisos faltantes en AndroidManifest: CAMERA, READ_MEDIA_IMAGES,
     ACCESS_FINE_LOCATION, PACKAGE_USAGE_STATS
  3. Agregar google_maps_flutter y geolocator al pubspec
  4. Crear pantalla de vinculación de dispositivo hijo (QR + código numérico)
  5. Crear tabla parental_reports (migración) con campos: profile_id, device_id,
     domain, blocked, category, timestamp, request_type

ETAPA 2 (2–3 semanas) — Control parental funcional (funciones core):
  1. Pantalla de solicitudes pendientes en la app padre
     (padre recibe alerta push y puede aprobar/rechazar)
  2. Pantalla de bloqueo de apps (listar apps instaladas, toggle bloquear/permitir)
     — requiere PACKAGE_USAGE_STATS para leer apps del hijo
  3. Editor de horarios (horario escolar / fin de semana / personalizado)
  4. Implementar tiempo extra real (opciones 15/30/60 min o personalizado)
  5. Pantalla de ubicación del hijo con google_maps_flutter

ETAPA 3 (2–3 semanas) — Features avanzados y completar flujos parciales:
  1. Pantalla de consumo ISP (gráficas fl_chart — diario/semanal/mensual)
     con integración a Mikrotik (MikrotikController ya existe)
  2. Pantalla de control web (filtros por categoría + listas blanca/negra)
  3. Pantalla de zonas seguras (geocercas) en la app padre
  4. Botón SOS del hijo (pantalla + endpoint + push al padre)
  5. Validación de tareas con foto/comentario (padre aprueba desde app móvil)
  6. Medallas y niveles dinámicos (consumir parental_rewards, no hardcode)
  7. Descarga de facturas PDF/XML + solicitud de factura fiscal

ETAPA 4 (2–4 semanas) — Seguridad, pulido y protección:
  1. OTP por SMS/WhatsApp para login (integrar Twilio o equivalente)
  2. Protección de desinstalación (DeviceAdmin receiver)
     — o implementar la alternativa VPN local (ver Riesgo §2)
  3. Adjuntar fotos en tickets de soporte
  4. Calificación de tickets resueltos
  5. Recordatorios de pago automáticos (push + correo)
  6. Ranking familiar y premios canjeables (gamificación completa)
  7. Corregir comparación de contraseña plain-text → Hash::check()

════════════════════════════════════════════════════════

── RIESGOS TÉCNICOS ────────────────────────────────────

⚠️  RIESGO 1 — Bloqueo real de aplicaciones en Android
   El alcance pide "ver apps instaladas, bloquear/permitir por app, reglas por horario".
   Esto es técnicamente lo más complejo del proyecto y tiene implicaciones de distribución:
   — OPCIÓN A (Accessibility Service): requiere BIND_ACCESSIBILITY_SERVICE + que el usuario
     active el servicio manualmente. Google Play puede rechazar apps con este permiso
     a menos que la app sea "herramienta de accesibilidad". Proceso de aprobación extra.
   — OPCIÓN B (Device Policy / MDM): requiere que el padre active "Device Admin". Solo
     posible si el hijo usa el dispositivo como perfil dedicado. Muy intrusivo.
   — OPCIÓN C (VPN local): la app crea una VPN loopback que intercepta y bloquea tráfico
     por app. Técnicamente viable, pero bloquea tráfico de red, no ejecución de la app.
   — OPCIÓN D (Usage Stats + overlay): con PACKAGE_USAGE_STATS se puede detectar qué
     app está en primer plano y mostrar un overlay de bloqueo. Workaround pero muy usado
     en apps de control parental (Qustodio, Google Family Link).
   RECOMENDACIÓN: implementar Opción D (overlay) como MVP, y evaluar Opción C para
   filtrado web avanzado. Documentar en Play Store para revisión.

⚠️  RIESGO 2 — Protección de desinstalación
   Sin Device Admin o Accessibility Service, el hijo puede desinstalar la app sin restricción.
   Android 12+ restringe aún más los Device Admin para apps de terceros.
   — ALTERNATIVA: configurar el dispositivo del hijo como "perfil de trabajo" (Android for Work)
     o usar MDM como headless. Muy complejo para el segmento de mercado objetivo (hogares).
   — MÍNIMO VIABLE: al detectar intento de desinstalación via Accessibility, enviar
     push al padre con registro en parental_events. No previene pero alerta.

⚠️  RIESGO 3 — Filtrado web (control parental de navegación)
   Filtrar contenido web sin VPN o DNS requiere que el hijo use solo el navegador de la app.
   — OPCIÓN A (VPN local DNS): la app levanta un servidor DNS local que resuelve/bloquea
     dominios. Funciona para todo el tráfico del dispositivo pero consume batería.
   — OPCIÓN B (Safe Browsing API): solo aplica si se construye un navegador propio.
   — OPCIÓN C (integración Mikrotik): como ya existe MikrotikController, se puede aplicar
     el filtrado a nivel de router del ISP (DNS/firewall). Es la opción más poderosa
     para clientes MegaISP — el filtro aplica a todos los dispositivos del hogar.
   RECOMENDACIÓN: explotar la ventaja de ser ISP — implementar Opción C (Mikrotik)
   es un diferenciador único que no tienen Qustodio ni Google Family Link.

⚠️  RIESGO 4 — Sincronización en tiempo real hijo↔padre
   El sistema actual es polling: el hijo carga tiempo restante al abrir la app.
   Si el padre cambia el límite, el hijo no se entera hasta la próxima apertura.
   — Requiere WebSockets (Laravel Reverb/Pusher) o polling corto (~30 seg) para
     que cambios del padre se reflejen en el dispositivo del hijo en tiempo real.
   — Sin esto, la pantalla de bloqueo del hijo muestra tiempo hardcoded.

⚠️  RIESGO 5 — Seguridad: contraseñas en texto plano
   ApiController.php:116: `$data['password'] !== $cmi->password`
   Comparación directa sin Hash::check() — las contraseñas se almacenan en plain text
   en client_main_information.password. Si la BD es comprometida, todas las cuentas
   quedan expuestas. Corregir en Etapa 4 o antes si hay auditoría de seguridad.

════════════════════════════════════════════════════════

── NOTAS FINALES ───────────────────────────────────────

1. EL ADMIN WEB SUPERA AL ALCANCE
   El módulo administrativo está sorprendentemente bien desarrollado — tiene más de
   lo que el alcance especificaba (auditoría, Mikrotik, OTA, soporte, ingresos PDF).
   Esto es una ventaja operativa importante. La brecha está en el lado móvil.

2. LA BASE DE DATOS ESTÁ CASI COMPLETA
   Solo falta parental_reports. Todas las demás tablas del alcance existen y tienen
   modelos Eloquent. El esquema es sólido y bien normalizado.

3. LA APP FLUTTER ESTÁ EN v0.3.2 — SCAFFOLDING VISIBLE
   Varios flujos tienen SnackBars "próximamente" que indican trabajo planeado pero
   no iniciado: "Añadir perfil", "Pausar internet", "Dar tiempo extra", etc.
   El código está bien estructurado (Provider + GoRouter) y es fácil de extender.

4. LA VENTAJA COMPETITIVA CLAVE AÚN NO ESTÁ IMPLEMENTADA
   La integración con Mikrotik (filtrado web a nivel ISP) es el diferenciador más
   poderoso — ninguna app de control parental del mercado tiene acceso al router.
   MikrotikController.php ya existe. Conectar esto con la app padre sería el
   feature que justifica pagar un plan Premium.

5. PRIORIDAD INMEDIATA: NOTIFICACIONES PUSH
   Sin FCM, el flujo solicitud-hijo → notificación-padre → respuesta es imposible.
   Es el núcleo del producto. Configurar Firebase debería ser el primer paso.

════════════════════════════════════════════════════════
Generado automáticamente por auditoría de código — 2026-05-26
Archivo fuente: /var/www/megaisp/docs/megafamilia-auditoria-2026-05-26.md
