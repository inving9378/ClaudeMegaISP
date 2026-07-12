# Módulo Domiciliación

> Cobro recurrente automático mensual con tarjeta de crédito/débito vía OpenPay. `app/Modules/Addons/Domiciliacion/` · slug `addon-domiciliacion` · addon, depende de `addon-portal-cliente`, **arranque apagado** (se activa por piloto).

**En simple:** es el sistema que le cobra la mensualidad al cliente automáticamente de su tarjeta cada mes, sin que tenga que pagar a mano.

## 1. Qué es
Addon de **domiciliación de pagos**: permite registrar la tarjeta de un cliente (tokenizada, nunca el número real llega al servidor) y cobrar automáticamente su factura mensual vía OpenPay.

## 2. Para qué sirve
Le ahorra al cliente tener que pagar manualmente cada mes y le ahorra a cobranza el seguimiento de esa factura: una vez que el cliente registra su tarjeta (desde el Portal Cliente, desde la ficha del cliente en el admin, o desde una liga enviada por WhatsApp/email), el comando mensual de cobro detecta su factura vencida y la cobra solo, con reintentos y notificación al cliente en cada paso (éxito, fallo de intento, fallo final).

## 3. Cómo funciona
- **Modelos** (`Models/`):
  - `ClientRecurringCard` (tabla `client_recurring_cards`, soft deletes) — la tarjeta tokenizada por cliente (`openpay_customer_id`/`openpay_card_id`, marca, últimos 4, vencimiento, titular, `status` active/cancelled/failed, canal y fecha de consentimiento). Scopes `active()` y `forClient()`. Solo puede haber **una tarjeta activa por cliente**: al enrolar una nueva se cancelan las anteriores.
  - `EnrollmentLink` (tabla `enrollment_links`) — liga pública de un solo uso para que el cliente registre su tarjeta sin loguearse; token aleatorio de 64 chars, expira en 48 h (`isExpired`/`isUsed`/`markUsed`).
  - `RecurringChargeAttempt` (tabla `recurring_charge_attempts`, sin `updated_at`, inmutable) — un registro por intento de cobro (pending/completed/failed), con `attempt_no`, código/mensaje de error de OpenPay y `notified_at`.
- **Servicio** `Services/DomiciliacionEnrollmentService`:
  - `enrolar()` — recibe el token de openpay.js (el PAN nunca toca el servidor), crea/usa el cliente en OpenPay (`OpenpayService::crearClienteOpenpay`), guarda la tarjeta (`guardarTarjeta`) y cancela cualquier tarjeta activa previa del cliente antes de persistir la nueva.
  - `cancelar()` — valida que la tarjeta pertenezca al `client_id` (anti-IDOR), intenta eliminarla en OpenPay (best-effort, no bloquea si falla) y la marca `cancelled` + soft-delete localmente.
- **Comando** `domiciliacion:cobrar` (`Commands/DomiciliacionCobrarCommand`, corrido por cron mensual):
  - **Compuertas de seguridad:** requiere `config('domiciliacion.cobro_live_enabled')` (env `DOMICILIACION_COBRO_LIVE_ENABLED`, kill-switch independiente de `OPENPAY_SANDBOX`) **y** el `Setting` `domiciliacion_habilitada=true`; sin ellas solo corre en `--dry-run`.
  - Candidatos: clientes con tarjeta activa que tengan **exactamente una** factura `Atrasado`, sin intento `completed` previo para esa factura y con menos de 3 intentos fallidos.
  - Por cada candidato: registra el intento `pending`, cobra vía `OpenpayService::cobrarTarjetaGuardada`, y si es exitoso crea el `Payment` (autor = `User::systemBot()`, método OpenPay id 9), marca la factura `Pagado` y el intento `completed`. Si falla, registra el error y notifica; al 3er intento fallido marca la tarjeta `failed` y desactiva la domiciliación.
  - Notifica cada resultado (éxito/intento fallido/fallo final) por **WhatsApp** (`EvolutionApiService`), **email** (`StandardMail`) y, en éxito/fallo final, una **llamada AMI** best-effort (`AmiConnectionService`, si el módulo VoIP está presente).
- **Controllers:**
  - `DomiciliacionController` — API JSON para ver/registrar/cancelar la tarjeta, tanto desde el **Portal Cliente** (guard `cliente`, scoped a su propio `client_id`) como desde el **admin** (ficha del cliente, cualquier `clientId`).
  - `PortalDomiciliacionController` — versión Blade (no-API) de esas mismas acciones para la pantalla `/portal/domiciliacion/tarjeta`.
  - `EnrollmentLinkController` — genera la liga de enrolamiento (admin), y sirve/procesa el formulario público de tokenización (`/d/{token}`) sin autenticación.
- **Frontend:** `DomiciliacionClientTab.vue` (pestaña en la ficha de cliente del admin, permiso `domiciliacion.manage`) — muestra la tarjeta activa y permite captura directa o generar/enviar liga.

## 4. Qué EXPONE / qué CONSUME
**Expone**
- **Rutas Portal Cliente** (guard `cliente`, `/portal/domiciliacion/*`): vista Blade `tarjeta`, `POST tarjeta/enrolar`, `DELETE tarjeta/{card}`, y su equivalente JSON `GET/POST/DELETE /portal/domiciliacion/*`.
- **Rutas admin** (`/domiciliacion/*`, permisos por URL): `GET/POST /clientes/{clientId}`, `DELETE /clientes/{clientId}/{cardId}`, `POST /clientes/{clientId}/liga` (generar liga).
- **Rutas públicas sin auth** (`/d/{token}`, `throttle:30,1` en GET y `throttle:5,1` en POST): formulario de enrolamiento vía liga.
- **Permisos** `domiciliacion.view`, `domiciliacion.manage`, `domiciliacion.cobrar`, `domiciliacion.links`.
- **Comando artisan** `domiciliacion:cobrar {--dry-run}`.
- **Pestaña de cliente** `DomiciliacionClientTab` (client_tab del module.json, consumida por la ficha de cliente del admin).

**Consume**
- **`OpenpayService`** (`App\Modules\Addons\PortalCliente\Services`) — crear cliente OpenPay, guardar/eliminar tarjeta y cobrar cargo recurrente; único cliente HTTP a OpenPay del sistema.
- **`User::systemBot()`** — autor (`add_by`) de los pagos automáticos, para que el historial de pagos los atribuya a MEGAISP y no a un admin.
- **`Payment` / tabla `client_invoices`** (núcleo de Facturación) — registra el pago y marca la factura como pagada.
- **`EvolutionApiService`** (Marketing) — envío de WhatsApp para notificaciones y ligas de enrolamiento (gateway único, no monta línea propia).
- **`StandardMail`** — envío de email de notificaciones y ligas.
- **`AmiConnectionService`** (VoIP, opcional/best-effort) — llamada telefónica en éxito/fallo final del cobro.
- **`Setting`** (Marketing Settings) — interruptor operativo `domiciliacion_habilitada`.

> _Servicios compartidos únicos respetados: WhatsApp vía `EvolutionApiService` (no webhook propio), IA no aplica a este módulo. No hay entrada en el registro de contratos inter-módulos (`docs/contratos/`) para Domiciliación al momento de esta doc._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
