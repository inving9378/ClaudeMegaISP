# OpenPay — dos servicios y dos fuentes de credenciales (item Roadmap #227)

FASE 1 (mapa documental) del item #227. Solo documenta el estado actual — **no cambia
comportamiento**. Fases 2-4 (elegir fuente canónica y consolidar) requieren decisión de
Irving por tocar dinero/credenciales/switch sandbox↔prod — quedan escaladas.

## Los dos servicios

### 1. `App\Modules\Addons\Payments\Services\OpenPayService`
- **Fuente de credenciales:** modelo `PaymentProvider` (`Payments/Models/PaymentProvider.php`),
  campo `config` — **JSON cifrado en BD**. Claves esperadas: `merchant_id`, `api_key`,
  `webhook_secret`, `sandbox` (bool, **por provider/registro**, no global).
- **SDK:** Guzzle directo (HTTP crudo a `api.openpay.mx` / `sandbox-api.openpay.mx`), sin el
  SDK oficial `openpay-php`.
- **Qué hace:** `createClabe()` (CLABE virtual para SPEI, gateada por
  `config('openpay.clabe_emission_enabled')` + el flag `sandbox` del provider individual),
  `getTransaction()`, `verifyWebhookAuth()` (Basic Auth contra `webhook_secret` del provider).
- **Consumidores:** `Payments/Controllers/MobilePaymentController.php`,
  `Payments/Controllers/ClabeAssignmentController.php`,
  `Payments/Controllers/SpeiWebhookController.php`.
- **Único toque a `config('openpay.*')` (.env) en este servicio:**
  `clabe_emission_enabled` (kill-switch global aparte del provider).

### 2. `App\Modules\Addons\PortalCliente\Services\OpenpayService`
- **Fuente de credenciales:** `config('openpay.*')` → `config/openpay.php` → **`.env`**
  (`OPENPAY_ID`, `OPENPAY_PUBLIC_KEY`, `OPENPAY_PRIVATE_KEY`, `OPENPAY_SANDBOX`). Un solo
  juego de credenciales **global** para todo el sistema (no por provider/registro).
- **SDK:** paquete oficial `openpay/openpay` (`Openpay\Data\*`), vía `OpenpayApi::getInstance()`.
- **Qué hace:** `cobrarTarjeta()` (cargo síncrono con token del navegador),
  `consultarCargo()`, `crearClienteOpenpay()`, `guardarTarjeta()`, `cobrarTarjetaGuardada()`,
  `eliminarTarjeta()`.
- **Guard propio:** el constructor lanza `RuntimeException` si `config('openpay.sandbox')`
  es falsy — hoy este servicio **rechaza correr fuera de sandbox** (candado explícito, ver
  `OpenpayService.php:27-31`).
- **Consumidores:**
  - `PortalCliente/Controllers/PortalPagoController.php` — cobro de tarjeta en el portal cliente.
  - `PortalCliente/Controllers/OpenpayWebhookController.php` — conciliación webhook (también
    lee `config('openpay.private_key')` directo para verificar firma, línea 196).
  - `Domiciliacion/Services/DomiciliacionEnrollmentService.php` — enrolamiento de domiciliación.
  - `Domiciliacion/Commands/DomiciliacionCobrarCommand.php` — cobro recurrente vía cron
    (también lee `config('openpay.sandbox')` directo, línea 52).
- **Otros lectores directos de `config('openpay.*')`** (no instancian el servicio, pero
  comparten la misma fuente .env — relevante para Fase 3):
  `Domiciliacion/Controllers/EnrollmentLinkController.php`,
  `Domiciliacion/Controllers/PortalDomiciliacionController.php`,
  `PortalCliente/views/{factura_show,facturas,pagos,partials/openpay_modal}.blade.php`
  (exponen `id`/`public_key`/`sandbox` al navegador — correcto, la `private_key` nunca sale).

## La divergencia real

| | `Payments\OpenPayService` | `PortalCliente\OpenpayService` |
|---|---|---|
| Credenciales | `PaymentProvider.config` (BD, cifrado, **por registro**) | `.env` (`config/openpay.php`, **global**) |
| Switch sandbox/prod | Campo `sandbox` del provider individual | `OPENPAY_SANDBOX` único, global |
| SDK | Guzzle manual | SDK oficial `openpay-php` |
| Alcance | CLABE SPEI (`payment_clabes`) | Cobro con tarjeta + domiciliación recurrente |

Son dos verdades de sandbox/llaves independientes: **nada impide** que el `PaymentProvider`
usado para CLABE esté en modo producción mientras `OPENPAY_SANDBOX` (que gobierna tarjeta +
domiciliación) siga en sandbox, o viceversa. Rotar credenciales de OpenPay hoy exige tocar
dos lugares (fila cifrada en BD + variables `.env`) que no se sincronizan entre sí.

## Estado de Fase 2 (decisión de fuente canónica)

Pendiente — ver brief ya generado en `comentarios_claude` del item #227 (3 opciones con
pros/contras, recomendación: opción 1, `PaymentProvider` cifrado como canónico + `.env` solo
para `public_key` del navegador). Requiere que Irving elija antes de tocar código de
credenciales/cobro real.
