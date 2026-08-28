# Item #143 — Webhook OpenPay en dashboard (RESUELTO — código ya completo, pendiente paso externo)

## Qué pedía el item

Título: "Activar URL `https://portal.meganet.mx/portal/openpay/webhook` en dashboard OpenPay al
publicar subdominio/SSL." Migrado de la sección "IMPORTANTE — Para pasar a producción" de
`CLAUDE.md` (2026-06-19).

## Investigación (acumulada de wt-5 26-ago, wt-4 28-ago, re-verificada por wt-1 28-ago)

El webhook **ya está 100% implementado en código**, no hay nada que programar:

- **Endpoint:** `POST /portal/openpay/webhook` →
  `App\Modules\Addons\PortalCliente\Controllers\OpenpayWebhookController::handle`
  (registrado en `app/Modules/Addons/PortalCliente/routes.php:45`, **fuera** del guard `cliente`
  a propósito — lo llama OpenPay, no un cliente logueado).
- **Autenticación:** Basic Auth donde la contraseña es la `private_key` de OpenPay, comparada con
  `hash_equals()` (`OpenpayWebhookController::validarBasicAuth`, línea 186-197). Este **es** el
  mecanismo real de verificación de OpenPay para webhooks (no HMAC genérico de header, que fue lo
  que asumió sin verificar el "FIX + BRIEF DE RIESGO" del 2026-08-28 06:21 en el historial del
  item — ese brief se escribió sin leer el controller existente).
- **Idempotencia:** antes de aplicar cualquier pago, `conciliarCargo()` revisa
  `portal_payment_attempts.status === 'completed'` y sale sin hacer nada si ya se procesó
  (líneas 93-96). No hace falta una tabla de eventos aparte — el estado del intento ya es la
  fuente de idempotencia.
- **Conciliación:** busca el intento por `order_id` o `openpay_charge_id`, valida que la factura
  no esté ya pagada, y si no, aplica el pago (`payments` + `client_invoices.estado='Pagado'`)
  dentro de una transacción, con envío de recibo best-effort.

## Por qué no se puede cerrar por completo

Lo único pendiente es **externo**: dar de alta la URL del webhook en el dashboard de OpenPay
(`dashboard.openpay.mx`), lo cual requiere que `https://portal.meganet.mx` esté publicado con
SSL — prerequisito de infraestructura que no existe todavía (nginx + certbot, acción manual de
Irving, ya trackeada en `CLAUDE.md` §"IMPORTANTE — Para pasar a producción" y §"Portal Cliente —
estado", ítem "Portal: subdominio + SSL"). No hay ninguna acción de código posible desde dev para
completar ese paso — es configuración en un panel de un proveedor externo, bloqueada por un
prerequisito que tampoco es código.

## Resolución

Sin cambio de código (nada que arreglar ni que construir). Este documento deja registrada la
verificación para que, cuando `portal.meganet.mx` tenga SSL, el único paso que falte sea entrar a
`dashboard.openpay.mx` y pegar la URL — ya documentado en `CLAUDE.md`.
