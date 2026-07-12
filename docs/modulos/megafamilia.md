# Módulo MegaFamilia

> Plataforma de control parental vendida como servicio adicional a clientes de internet: licencias por plan, perfiles de hijos, dispositivos, reglas/horarios/geofences, alertas, tareas familiares y app móvil. `app/Modules/Addons/MegaFamilia/` · slug `addon-megafamilia` · módulo **addon**, activo.

## 0. En simple
Es el control parental que Meganet vende junto con el internet: los papás activan un plan, registran a sus hijos y sus dispositivos, ponen horarios y zonas seguras, y reciben alertas — todo desde una app móvil o desde el portal del cliente.

## 1. Qué es
Addon de control parental (`module.json` describe: *"licencias por plan, perfiles de hijos, dispositivos, reglas, geofences, alertas, tareas y app móvil"*). El ISP (Meganet) lo administra desde un panel web dentro del admin (`/megafamilia/*`), lo activa/vende por cliente, y la familia lo usa desde una app móvil (Flutter) y/o desde una sección dedicada del Portal Cliente (`/portal/*`).

## 2. Para qué sirve
Resuelve para el cliente final (padre/madre) el problema de supervisar el uso de internet de sus hijos: qué apps y sitios usan, cuánto tiempo, dónde están (geocercas/ubicación), y permite asignarles tareas con recompensas (gamificación). Para Meganet es un **producto adicional monetizable** (planes Básico/Plus/Premium) sobre la base de clientes de internet ya existente, con su propio panel de ventas/soporte/licenciamiento y filtrado real a nivel de red vía MikroTik.

## 3. Cómo funciona

**Modelos y tablas** (`app/Modules/Addons/MegaFamilia/Models/`), todas con prefijo `parental_` salvo `app_versions` y `megafamilia_settings`:

| Modelo | Tabla | Para qué sirve |
|---|---|---|
| `ParentalPlan` | `parental_plans` | Catálogo de planes comerciales (Básico/Plus/Premium): precio, límites, features. |
| `ParentalAccount` | `parental_accounts` | Cuenta "padre" — raíz del árbol de datos: liga `user_id` + `client_isp_id` (tenant) + plan. |
| `ParentalProfile` | `parental_profiles` | Perfil de un hijo dentro de una cuenta. |
| `ParentalDevice` | `parental_devices` | Dispositivo vinculado a un perfil (por MAC/QR). |
| `ParentalRule` | `parental_rules` | Reglas de tiempo de uso (1:1 con perfil). |
| `ParentalAppBlock` | `parental_app_blocks` | Bloqueo/permiso de apps específicas por perfil. |
| `ParentalWebBlock` | `parental_web_blocks` | Filtro de dominios/categorías web por perfil. |
| `ParentalSchedule` | `parental_schedules` | Horarios (escolar, fin de semana, personalizado) por perfil. |
| `ParentalRequest` | `parental_requests` | Solicitudes hijo→padre (tiempo extra, desbloqueo app/web, canje de recompensa). |
| `ParentalTask` | `parental_tasks` | Tareas familiares definidas a nivel de cuenta, con puntos. |
| `ParentalTaskAssignment` | `parental_task_assignments` | Asignación de una tarea a un perfil, con estado (p. ej. `completed`). |
| `ParentalReward` | `parental_rewards` | Recompensas: catálogo (creadas por el padre) u otorgadas (canjeadas). |
| `ParentalLocation` | `parental_locations` | Historial GPS reportado por el dispositivo del hijo. |
| `ParentalGeofence` | `parental_geofences` | Geocercas (zonas seguras) con coordenadas/radio. |
| `ParentalAlert` | `parental_alerts` | Alertas generadas (geocerca, evento inusual, etc.). |
| `ParentalLicense` | `parental_licenses` | Licencia activa/histórica de una cuenta ligada a un plan (vencimiento, estado). |
| `ParentalEvent` | `parental_events` | Log de auditoría/eventos del sistema. |
| `ParentalConsent` | `parental_consents` | Aceptación de términos y condiciones (firma/IP). |
| `AppVersion` | `app_versions` | Versiones de APK para actualización OTA. |
| — | `megafamilia_settings` | Configuración global clave/valor (soporta cifrado), vía `MegaFamiliaSettingsService`. |

`ParentalAccount` usa el trait `BelongsToClientTenant` (`tenantColumn = client_isp_id`, `allowNullTenant = false`) — fail-closed: sin cliente resuelto, cero filas.

**Flujo principal:** el ISP crea/activa una cuenta MegaFamilia para un cliente (desde `ClientesController`) con un plan y licencia. El padre, desde la app móvil o el Portal Cliente, crea perfiles de hijos, vincula dispositivos, define reglas/horarios/bloqueos/geocercas y tareas con recompensas. El hijo (desde la app o el "portal del hijo") completa tareas, hace solicitudes y acumula puntos canjeables. El panel admin de Meganet (18 controllers) da soporte, monitorea alertas/ubicaciones/auditoría, y gestiona planes/licencias/ingresos/notificaciones push/versiones de la app.

**Servicios clave** (`Services/` del módulo):
- `MegaFamiliaSettingsService` — key-value store en `megafamilia_settings` con cache de 60 min como acelerador (no fuente de verdad); soporta valores cifrados (p. ej. `firebase_server_key`).
- `FcmService` — cliente del API legacy HTTP de Firebase Cloud Messaging para notificaciones push, en lotes de hasta 1000 tokens.
- `ApkDeployService` — copia el `.apk` a `public/downloads` y hace upsert en `app_versions` (usado por `DeployApkCommand`/`megafamilia:deploy-apk`); convive con `PublishMegaFamiliaApkCommand` (`megafamilia:publish-apk`), que compila la app Flutter real (`mobile/megafamilia`) y actualiza `ApiMobileConfig` — este último es el flujo vigente (la app es Flutter, no React Native).

**Integración MikroTik:** `Controllers/MikrotikController.php` usa el `App\Services\MikrotikService` central (no propio del módulo) para conectar routers, gestionar address-lists y sincronizar/pausar-reanudar el filtrado por perfil — es el mecanismo de bloqueo real a nivel de red del ISP, pensado como diferenciador frente a apps de control parental genéricas.

**Integración con Portal Cliente** (`app/Modules/Addons/PortalCliente/Controllers/MegaFamilia*.php`): el cliente activa/usa el módulo desde `/portal/servicios` vía `MegaFamiliaController`. Si no tiene cuenta activa, se muestra estado vacío con CTA (nunca error). El aislamiento multi-tenant se hace por `CurrentClientResolver` (resuelve `client_id` del guard `cliente`, fail-closed) + `ParentalAccount::forClient()`; **`MegaFamiliaBaseController`** es la clase base de todos los controllers de escritura del portal (perfiles, dispositivos, geofences, horarios, bloqueos, tareas, solicitudes, gamificación) y centraliza la verificación de propiedad **siempre contra BD** (nunca confía en el id recibido). `MegaFamiliaBalance` (Support, no modelo) calcula el balance de puntos de un perfil: puntos de tareas completadas menos valor de recompensas otorgadas.

**App móvil:** app Flutter (`mobile/megafamilia`) consumida vía la API `/api/megafamilia/*` (Sanctum); se publica con `php artisan megafamilia:publish-apk`, que compila, calcula SHA-256, copia a `public/apk/` y actualiza el alias estable `megafamilia.apk` + metadata OTA.

**Configuración** (`config/megafamilia.php`): un único flag `financial_demo_enabled` (default `false`) — kill-switch de datos demo financieros (facturas/pagos simulados) en la API móvil cuando el usuario no tiene cliente ISP ligado (evita mostrar montos ficticios indistinguibles de reales).

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Rutas web admin** `/megafamilia/*` (`web`+`auth`), segmentadas por permiso: `megafamilia_admin` (Dashboard, Clientes, Licencias, Planes, Ingresos, Notificaciones push, Configuración, App-versions/OTA, Términos, Auditoría, Mikrotik), `megafamilia_support` o `admin` (Alertas, Solicitudes, Dispositivos, Ubicaciones, Geofences, Soporte técnico), y `auth` simple para el cliente final (Perfiles, Tareas, Reportes).
- **API móvil** `/api/megafamilia/*` (Sanctum + middleware `log_api_mobile`/`force_json`): login, chequeo de versión OTA, cuenta/servicio/facturas/pagos, perfiles/dispositivos/reglas/tareas/ubicación de hijos, solicitudes hijo↔padre, órdenes de técnico — más bloques de Embajadores y Flotas que comparten el mismo `ApiController`/infra de auth móvil aunque no son control parental.
- **Rutas públicas sin auth**: `/megafamilia/descargar` (página de descarga del APK) y `/api/megafamilia/mobile/check-update` / `.../download/{id}`.
- **Modelos compartidos** (`Parental*`) — en principio de uso interno del módulo; no se detectó consumo directo desde otros módulos del sistema.
- **3 permisos Spatie propios**: `megafamilia_view`, `megafamilia_support`, `megafamilia_admin`.

**Consume**
- **`MikrotikService`** (servicio central del sistema) — filtrado/bloqueo real a nivel de router del ISP.
- **Firebase Cloud Messaging** (API legacy HTTP) — notificaciones push a la app móvil, vía `FcmService` propio (no pasa por un hub de IA/mensajería central).
- **`CurrentClientResolver`** y el guard `cliente` del Portal Cliente — para resolver el tenant del cliente autenticado.
- **Sistema de permisos Spatie** — gating de rutas admin y de las tarjetas/secciones del `module.json`.
- **Firma de flujos financieros del Portal Cliente** (facturas/pagos) para la sección "cuenta" de la app móvil, cuando el cliente tiene datos ISP reales ligados.

No hay entrada en el registro de contratos inter-módulos (`docs/contratos/`) para MegaFamilia al momento de esta doc: el módulo no monta cliente propio de WhatsApp/IA/Mapas — su único servicio compartido central consumido es `MikrotikService`; FCM/push corre por un cliente HTTP legacy propio (`FcmService`), fuera de cualquier gateway único designado.

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
