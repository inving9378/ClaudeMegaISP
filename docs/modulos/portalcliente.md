# Módulo PortalCliente

> `app/Modules/Addons/PortalCliente/` · slug `addon-portal-cliente` · módulo **addon** (activo).

## 0. En simple
Es la oficina virtual donde el cliente entra con su usuario y contraseña para ver sus facturas, pagar, revisar su consumo de internet, abrir tickets de soporte y activar servicios extra — sin tener que llamar o ir a las oficinas de Meganet.

## 1. Qué es
Módulo addon que expone un **portal web de autoatención para clientes finales del ISP** bajo `/portal/*`, con su propio sistema de sesión (guard `cliente`, independiente del guard `web` del admin) y aislamiento estricto por `client_id` en cada consulta.

## 2. Para qué sirve
Le resuelve a los clientes finales de Meganet el poder autoatenderse sin depender de un agente humano:
- Ver su **plan de internet**, **facturas**, **historial de pagos** y **CLABE** asignada.
- **Pagar facturas con tarjeta** (OpenPay, cargo síncrono tokenizado en el navegador).
- Consultar su **consumo de internet**.
- Abrir y responder **tickets de soporte**.
- **Registrarse y recuperar su contraseña** por sí mismos (verificando teléfono, sin depender de un admin).
- Activar servicios adicionales del **marketplace** (hoy: MegaFamilia gratuito) y ver paneles de solo lectura de otros módulos que ya lo soportan (Embajadores, Flotas).

## 3. Cómo funciona

### 3.1 Autenticación (guard propio `cliente`)
- `ModuleServiceProvider::register()` registra en caliente el guard `cliente` (driver `session`) y el provider `portal_clients`, apuntando al modelo `PortalClient` (tabla `client_main_information`, la misma ficha que usa el admin). Es un guard **independiente** del guard `web` de los usuarios internos.
- `PortalClient` implementa `Authenticatable` a mano: el identificador de auth es `password` (la "Contraseña WEB" de la ficha del admin, **texto plano**, comparada con `hash_equals` para evitar timing attacks) — **no** `portal_password` (columna legacy en desuso, quedó de un diseño bcrypt anterior).
- `AuthController::login` — acepta como identificador **email**, **Usuario WEB** (columna `user`) o **número de cliente** (`client_id`, tolerante a ceros a la izquierda). Rate-limit por IP (5/min) **y** por cuenta (10/15min, `RateLimiter` keyed por id resuelto o identificador crudo — protege contra fuerza bruta distribuida).
- `AuthController::registro` / `recuperar` — autoservicio: el cliente se identifica con **número de cliente + teléfono** (contra `phone`/`phone2`/`phone3`); si coincide, escribe una contraseña nueva directo en la columna `password`. Mensajes de error genéricos (anti-enumeración).
- Middleware `auth.portal` (`Middleware/AuthPortal.php`, alias registrado en `ModuleServiceProvider::boot()`) protege todas las rutas privadas del portal.

### 3.2 Aislamiento multi-tenant (`PortalClientScope`)
- Clase estática con la **regla dura del módulo**: toda consulta que devuelva datos de cliente debe pasar por `clientId()` (deriva el `client_id` de la sesión activa del guard `cliente`), `assertOwns()` (aborta 404 si el registro no es del cliente) o `assertPolymorphic()` (para tablas polimórficas como `payments`). Si una query no se puede filtrar así, no se expone.
- Cada controller de lectura (`FacturasController`, `PagosController`, `TicketsController`, `ConsumoController`, `PlanController`, `DashboardController`) resuelve el `client_id` del cliente autenticado y lo aplica como `WHERE` explícito antes de devolver datos.
- Verificación: comando `php artisan portal:test-idor` (`AntiIdorTestCommand`) confirma en caliente que el cliente A no puede leer datos del cliente B.

### 3.3 Pagos con tarjeta (OpenPay)
- `PortalPagoController::cobrar()` — el navegador tokeniza la tarjeta con `openpay.js` (el PAN nunca toca el backend) y envía solo `token` + `device_session_id`. El controller re-valida que la factura pertenezca al cliente autenticado, toma el **monto de `client_invoices.total`** (nunca del request), registra el intento en `portal_payment_attempts` (idempotencia: bloquea reintentos si hay uno `pending` reciente) y delega en `OpenpayService::cobrarTarjeta()`.
- `OpenpayService` — wrapper del SDK Openpay; exige `OPENPAY_SANDBOX=true` en dev (aborta si no), guarda la `private_key` solo en config/backend.
- Si el cargo queda `completed`, se escribe en `payments` + se actualiza `client_invoices`; `PortalPaymentReceiptService::enviar()` manda el recibo por email (best-effort, fuera de la transacción de dinero — un fallo de envío nunca revierte el pago).
- `OpenpayWebhookController` — endpoint **fuera** del guard `cliente` (lo llama OpenPay, autenticado con Basic Auth donde el password es la `private_key`); actúa como capa de conciliación si el cargo se completó en OpenPay pero no se registró localmente (crash/timeout). Pendiente activar su URL en el dashboard de OpenPay cuando el subdominio `portal.meganet.mx` tenga SSL.

### 3.4 Marketplace de servicios activables
- `MarketplaceController` — catálogo de servicios activables desde el portal. Hoy: **MegaFamilia** (tier gratuito, delega la creación/reactivación en `ParentalAccountActivationService`, único punto de escritura). **Flotas** y **VoIP** están gateados ("en preparación").
- El vínculo cliente↔usuario para MegaFamilia se resuelve por `login_user = client_main_information.user`; ~878 CMI sin fila en `users` reciben aviso de "contacta soporte" en vez del botón Activar.

### 3.5 Paneles de solo lectura de otros módulos
- `EmbajadoresController` y `FlotasController` — paneles read-only dentro del portal que reusan `CurrentClientResolver::resolve()` (rama guard `cliente`) y escopan **toda** consulta con `->forClient($clientId)` sobre los modelos de Referrals/Flotas ya existentes. No duplican lógica de negocio, solo consumen.

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Rutas públicas** (`/portal/login`, `/portal/registro`, `/portal/recuperar`) y **rutas protegidas** por `auth.portal` bajo `/portal/*`: dashboard, `mi-plan`, `facturas` (+ pago), `pagos`, `consumo`, `tickets` (listar/crear/responder), `perfil` (+ cambiar contraseña), `servicios` (marketplace), `embajadores`, `flotas`, y el árbol completo de `megafamilia/*` (perfiles, dispositivos, bloqueos, horarios, geocercas, tareas, recompensas, solicitudes de permiso).
- **Webhook público** `POST /portal/openpay/webhook` — fuera del guard `cliente`, autenticado por Basic Auth con la `private_key` de OpenPay.
- **Comando artisan** `portal:test-idor` — verificación de aislamiento, solo lectura.
- **Modelo/guard de autenticación propio**: guard `cliente` + provider `portal_clients` + modelo `PortalClient`, reusables por cualquier otro código que necesite operar "como el cliente autenticado del portal" (p. ej. `CurrentClientResolver`).

**Consume**
- **Clientes** — tabla/ficha `client_main_information` (misma que edita el admin) y modelo `App\Models\Client`; el portal no duplica el maestro de clientes, solo lo lee/actualiza (contraseña, perfil).
- **Facturación** — `client_invoices`, `payments`, `payment_clabes` (tablas del módulo Finanzas/Payments, leídas directo, sin capa propia).
- **MegaFamilia** — `ParentalAccountActivationService` (fuente única de creación/reactivación de `parental_accounts`); las rutas `megafamilia/*` del portal son la superficie de escritura completa del control parental para el cliente final.
- **Embajadores (Referrals)** — modelos `ClientReferralProfile`, `Referral`, `ReferralCommission`, `ReferralProspect`, `ReferralReward`, `ReferralSetting`, vía `CurrentClientResolver` + `->forClient()`.
- **Flotas** — modelos `FleetVehicle`, `FleetMaintenance`, `FleetGeofence` + `FleetSubscriptionService`, también vía `CurrentClientResolver` + `->forClient()`.
- **Tickets** — tabla `tickets`, el mismo modelo que usa el admin (filtrado por `customer_lead = client_id`).
- **OpenPay** — SDK oficial (`Openpay` PHP SDK) directo desde `OpenpayService`, credenciales en `config('openpay.*')`/`.env` (`OPENPAY_SANDBOX`).
- **Correo** — `Mail::queue()` para el recibo de pago (`PortalPaymentReceiptMail`), vía el mailer estándar de Laravel.

> _Nota de arquitectura: el guard/provider `cliente`/`portal_clients` es una convención propia del portal (sesión separada de los usuarios internos), no un servicio compartido único listado en las reglas del proyecto — pero cualquier módulo que necesite saber "qué cliente está autenticado en el portal" debe resolverlo vía `Auth::guard('cliente')` o `CurrentClientResolver`, no inventar su propio mecanismo._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
