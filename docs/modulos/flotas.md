# Módulo Flotas

> Gestión de flota vehicular. `app/Modules/Addons/Flotas/` · slug `addon-flotas` (id 200) · addon activo. Uso interno Meganet + producto SaaS vendible a clientes ISP.

## 0. En simple
Es el sistema donde se controlan los vehículos de la empresa (y de clientes que lo contraten): quién los trae, cuándo les toca servicio, si sus papeles están vigentes, cuánto gastan en gasolina y, si tienen GPS, dónde están en el mapa en este momento.

## 1. Qué es
Módulo de administración de flota vehicular: alta de vehículos, asignación de operadores, mantenimientos, documentos (con semáforo de vencimientos), bitácora de combustible, fotos, tracking GPS en vivo, geocercas con alertas de entrada/salida y un modelo de suscripción SaaS (planes por vehículo/mes) para vender el mismo módulo a clientes ISP.

## 2. Para qué sirve
A Meganet le sirve para llevar el control de sus propios vehículos (camionetas de instalación, unidades de reparto, etc.): quién los tiene asignados, cuándo vence la verificación/tenencia/seguro, cuánto rinden en combustible y, si llevan GPS, dónde están y si salieron de su zona de trabajo. Como producto SaaS, el mismo módulo se ofrece a clientes ISP bajo 3 planes (`Gestión Plus` sin GPS, `Gestión Plus + Tracking` y `Empresa`, ver `Support/FleetPlans.php`), modelo **BYOD** (el cliente compra su propio GPS de la marca que quiera; el software es agnóstico de marca).

## 3. Cómo funciona
- **Modelos/tablas principales** (prefijo `fleet_*`):
  - `FleetVehicle` (`fleet_vehicles`) — ficha del vehículo (marca/modelo/año/placas/estado), `client_id` nullable (null = flota interna Meganet; con valor = flota de un cliente SaaS).
  - `FleetAssignment` (`fleet_assignments`) — historial de qué operador (`users`) trae cada vehículo, con fecha de inicio/fin.
  - `FleetMaintenance` / `FleetMaintenanceFile` — mantenimientos y sus archivos adjuntos.
  - `FleetDocument` — documentos del vehículo (tarjeta de circulación, seguro, verificación…) con fecha de vencimiento.
  - `FleetFuelLog` — cargas de combustible (litros/costo, calcula km/L).
  - `FleetProvider` — talleres/proveedores de servicio.
  - `FleetPhoto` — fotografías del vehículo.
  - `FleetDevice` / `FleetPosition` / `FleetDeviceEvent` — dispositivo GPS vinculado, histórico de posiciones (pings) y eventos crudos del dispositivo.
  - `FleetGeofence` / `FleetGeofenceEvent` / `FleetGeofenceRule` — zonas geográficas (polígono), eventos de entrada/salida detectados y reglas opcionales (horario/día) que filtran cuándo notificar.
  - `FleetNotificationPreference` / `FleetNotificationLog` — a quién avisar por evento de geocerca y bitácora de cada envío.
  - `FleetSubscription` / `FleetSubscriptionEvent` — suscripción SaaS de un cliente (plan, estado, trial, vehículos contratados) y su historial de cambios.
- **Flujo principal (interno/gestión):** alta de vehículo (`FleetVehicleController`) → asignación de operador (`FleetAssignmentController`) → registro de mantenimientos/documentos/combustible/fotos por sus respectivos controllers. `flotas:check-document-expirations` (cron diario 08:00) revisa vencimientos y dispara alertas por correo (`FleetDocumentAlertMail`).
- **Flujo GPS:** un dispositivo (Ruptela u otra marca compatible) envía posiciones vía TCP al listener `flotas:gps-listen`, que las guarda con `FleetPositionService`. Cada posición nueva se evalúa contra las geocercas asignadas al vehículo (`GeofenceDetectionService`, algoritmo punto-en-polígono) y, si hay entrada/salida, se genera un `FleetGeofenceEvent`. El job `SendGeofenceNotificationsJob` despacha las notificaciones a quienes tengan preferencia activa para ese vehículo/geocerca, filtradas primero por `FleetGeofenceRule` (horario/día, opt-in) si el usuario definió alguna.
- **Flujo SaaS:** `FleetSubscriptionService` administra el ciclo de vida de la suscripción de un cliente (prueba gratuita, cambio de plan, cancelación) sobre el catálogo fijo de `Support/FleetPlans.php`. El cron `flotas:check-subscriptions` (diario 07:00) mantiene el estado (expira trials, cuenta vencidos).
- **Rutas:** todas bajo `/flotas/*`, autorizadas por `check_route_permission` (rutas generales) o `permission:` de Spatie directo en las de suscripciones SaaS.
- **Frontend:** pantallas Vue 3 con Bootstrap 5 + Leaflet (mapa/geocercas), NO Quasar — consistente con la pantalla original del módulo.

## 4. Qué EXPONE / qué CONSUME
**Expone**
- **Rutas web** bajo `/flotas/*`: dashboard, listado/alta/ficha de vehículo, mapa (`/flotas/mapa`), geocercas, log de notificaciones, reglas de alertas, dashboard de documentos y suscripciones (`/flotas/suscripciones`).
- **API REST** bajo `/flotas/api/*`: `vehiculos`, `gps` (estado/historial/activar dispositivo/eventos de geocerca), `notificaciones-log`, `reglas`, `geocercas`, `mantenimientos`, `documentos`, `proveedores`, `combustible`, `fotos` y `suscripciones` (dashboard, catálogo, alta de trial, cambio de plan, cancelación por cliente).
- **17 permisos** `fleet.*` (view/manage/assign/maintenance/documents/fuel/providers/gps/geofences/notifications/rules/subscriptions).
- **Comandos artisan**: `flotas:gps-listen` (listener TCP de dispositivos GPS), `flotas:simulate-gps`/`flotas:simulate-ruptela` (datos de prueba sin hardware), `flotas:reprocess-geofences`, `flotas:test-notification`, `flotas:test-rule`, `flotas:check-document-expirations` (cron diario), `flotas:check-subscriptions` (cron diario).
- Menú "Flotas" en el sidebar (bloque agregado a mano en `sidebar.blade.php` — el sidebar no lee `module.json` dinámicamente).

**Consume**
- **Módulo Marketing** — el canal WhatsApp de las notificaciones de geocerca reusa `App\Modules\Addons\Marketing\Services\EvolutionApiService` (instancia Evolution de la empresa del cliente), no monta su propia integración.
- **Correo del sistema** (`Mail`) — para alertas de documentos por vencer y eventos de geocerca (`FleetDocumentAlertMail`, `FleetGeofenceEventMail`).
- **Módulo Clientes** — `client_id` en `fleet_vehicles`/`fleet_geofences` liga la flota SaaS a un cliente existente; las suscripciones se administran por `client_id`.
- **Disco `local` privado** — almacena documentos, fotos y archivos de mantenimiento (sin symlink público; se sirven vía endpoint controlado).
- Dispositivos GPS físicos (protocolo Ruptela u otras marcas vía `Services/Gps/Drivers/*`) por conexión TCP directa al listener — no depende de una API externa de terceros.

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
