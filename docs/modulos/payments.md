# Módulo Payments

> `app/Modules/Addons/Payments/` · slug `addon-payments` · módulo **addon** (activo).

## 0. En simple
Es la parte del sistema que recibe el dinero que los clientes transfieren por banco o mandan por WhatsApp, entiende quién pagó y cuánto, y lo aplica al saldo del cliente correcto — a veces sola, a veces con ayuda de una persona del equipo que confirma.

## 1. Qué es
Módulo addon que centraliza el **cobro por transferencia bancaria (SPEI/OpenPay) y la conciliación de pagos reportados por WhatsApp con ayuda de IA**: CLABE virtual por cliente, webhook de confirmación, extracción de datos de comprobantes, identificación conversacional del cliente que pagó, y aplicación del pago a su saldo — con distintos niveles de automatización según qué tan segura esté la identificación.

## 2. Para qué sirve
Le resuelve dos problemas al equipo de cobranza (Diana, Ariana, Tere y roles con permiso `payments_*`/`conciliacion.manage`):
- **Cobro automático por transferencia**: cada cliente tiene una CLABE única; al recibir la transferencia, OpenPay avisa por webhook y el sistema abona el saldo y activa el servicio sin intervención manual.
- **Conciliación de comprobantes por WhatsApp**: hoy la mayoría de los clientes mandan la foto/PDF de su comprobante por WhatsApp en vez de usar la CLABE virtual. Este módulo lee el comprobante con IA, intenta averiguar de qué cliente es (por referencia MEG, nombre o número de cliente) y aplica el pago — automático cuando la certeza es alta, o mandándolo a una cola para que Tere lo confirme cuando no lo es. Así se evita que Diana/Ariana tengan que buscar cliente por cliente a mano cada comprobante que llega.

## 3. Cómo funciona

### 3.1 Cobro directo (CLABE virtual / OpenPay)
- `PaymentReferenceService::ensureFor(Client)` — genera (o recupera) la referencia de pago **MEG-{id8}-{mod97}** de cada cliente (tabla `client_payment_references`), visible en el header de su ficha con botón "Copiar". Se usa como ancla de identificación exacta en la conciliación por WhatsApp.
- `ClabeAssignmentController` + `OpenPayService::createClabe()` — asignan una CLABE virtual por cliente contra la API de OpenPay (`payment_clabes`, ligada a un `PaymentProvider` configurado en `/finanzas/payment-providers`, tabla `payment_providers`).
- `SpeiWebhookController::handle()` — endpoint público (sin sesión, sin CSRF) que recibe la notificación de OpenPay cuando llega una transferencia; valida la firma vía HTTP Basic Auth contra `payment_providers.config.webhook_secret` (`OpenPayService::verifyWebhookAuth`), registra el intento en `payment_webhooks_log` y, si es válida, delega en `PaymentApplicationService`.
- `PaymentApplicationService::applyPayment()` — punto único de aplicación de un pago: crea el registro en `payments` (modelo global `App\Models\Payment`), abona el saldo del cliente, activa el servicio si estaba suspendido (`activateClientService`), guarda el comprobante (`saveReceipt` → `payment_receipts`) y notifica al cliente. Admite overrides (método de pago, autor, fecha, comentario) para que otros flujos (captura de mostrador, aplicación por IA) lo reusen sin duplicar lógica.

### 3.2 Captura manual de mostrador
- `ManualPaymentController` (`/finanzas/captura-pago`) — pantalla para que Diana/Ariana busquen al cliente (nombre o referencia MEG) y capturen a mano un pago reportado (monto, fecha, clave de rastreo, titular, banco, método, comprobante). Aplica el pago vía `PaymentApplicationService::applyPayment()` y además registra el reporte en `reported_payments` (modelo `ReportedPayment`) con `conciliation_status='pendiente_verificar'`, ligado al `payment_id`.

### 3.3 Conciliación por WhatsApp con IA (flujo F1→F4)
Encadena varias piezas, cada una un "ladrillo" separado y reversible:
1. **Recepción** — el módulo **NO** monta su propia integración de WhatsApp; escucha los eventos del gateway único (`WhatsAppAgent`) vía `ConciliationListener` (media entrante) y `ConciliationTextListener` (texto entrante, para respuestas dentro de una sesión de identificación en curso). Registrados en `ModuleServiceProvider::boot()`.
2. **Extracción** — `Services/Extraction/PaymentReceiptExtractor` (perfil `SpeiTransferProfile`) le pide a la IA (Claude, vía el adaptador único del módulo IA) que lea la imagen/PDF del comprobante y devuelva campos estructurados (monto, clave de rastreo, fecha, titular, concepto/referencia, banco) con nivel de confianza por campo; nunca inventa datos. Se guarda en `whatsapp_payment_extractions`.
3. **Identificación** (`Services/Identification/`) — `IdentificationFsm` conversa con el cliente para saber a quién pertenece el comprobante: `MegReferenceResolver` reconoce la referencia MEG si el cliente la puso en el concepto (identificación **exacta**), `SubscriberSearchService` busca por nombre si no (identificación **propuesta**, puede haber homónimos). El estado de la conversación vive en `whatsapp_identification_sessions` (`certainty` = `exact`/`proposed`).
4. **Aplicación** (`Services/Conciliation/PaymentFromSessionService`, jobs `ApplyIdentifiedPaymentJob`/comando `payments:apply-identified`) — con identificación `exact` y el freno maestro `config('payments.auto_apply_enabled')` en `true`, aplica el pago solo (reusa `PaymentApplicationService::applyPayment`, autor = usuario de sistema `MEGAISP`). Con identificación `proposed`, múltiples servicios, o el freno en `false`, encola el caso en `reconciliation_tickets` para que un humano (Tere) confirme desde `/finanzas/conciliacion-cola` (`ReconciliationQueueController`).
- **3 candados anti-duplicado de dinero**: clave de rastreo única, claim atómico (`UPDATE ... WHERE applied_at IS NULL`), y sesión de identificación única por mensaje.
- `ReconciliationService` — genera el `ReconciliationTicket` cuando hay una discrepancia real (p. ej. clave de rastreo duplicada), no ante cualquier duda.
- Interruptores de seguridad, todos en `false`/`off` por defecto (`config/payments.php`): `wa_conciliation` (maestro de ruteo), `wa_autorespond` (si la IA contesta por WhatsApp), `auto_apply_enabled` (si F4 aplica sola), `id_cliente_auto_apply` (si identificar por número de cliente cuenta como exacto).
- Pantallas de apoyo (gateadas por rol `super-administrator|DESARROLLADOR` o el permiso propio `conciliacion.manage`): `/finanzas/extraccion-comprobante` (prueba aislada de IA), `/finanzas/whatsapp-comprobantes` (revisión + extracción manual), `/finanzas/conciliacion-sim` (simulador de la conversación, sin WhatsApp real), `/finanzas/conciliacion-config` (interruptores, solo `super-administrator`), `/finanzas/conciliacion-cola` (cola de Tere).

### 3.4 Piezas transversales
- **Modelos propios** (`Models/`): `PaymentProvider`, `PaymentClabe`, `PaymentReceipt`, `PaymentWebhookLog`, `ClientPaymentReference`, `PaymentInstrument`, `ReconciliationTicket`, `ReportedPayment`, `WhatsappIdentificationSession`, `WhatsappPaymentExtraction`, `ConciliationSetting`.
- **App móvil MegaFamilia** — `MobilePaymentController` expone `GET /api/megafamilia/payments/clabe` y `POST /api/megafamilia/payments/notify-transfer` (auth Sanctum) para que la app Flutter consulte la CLABE del cliente y avise de una transferencia.

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Endpoint público** `POST /payments/spei/webhook` — recepción de notificaciones OpenPay (sin sesión; validado por firma HTTP Basic contra un secreto por proveedor).
- **Vistas admin** (`/finanzas/*`, permiso `check_route_permission`): `metodos-pago`, `conciliacion`, `captura-pago`, `payment-providers`, `clients/{id}/clabe`, `payments/{id}/receipt`.
- **Vistas de IA/conciliación** (rol `super-administrator|DESARROLLADOR` o permiso `conciliacion.manage`): `extraccion-comprobante`, `whatsapp-comprobantes`, `conciliacion-sim`, `conciliacion-config`, `conciliacion-cola`.
- **API mobile** (Sanctum): `GET /api/megafamilia/payments/clabe`, `POST /api/megafamilia/payments/notify-transfer`.
- **Comandos artisan**: `payments:apply-identified` (aplica pagos con identificación resuelta, uso en cron/manual), `conciliacion:demo` (datos de demostración).
- **Servicio reusable por otros módulos**: `PaymentApplicationService::applyPayment()` — es el punto único de aplicación de pagos del sistema; lo reusan la captura de mostrador propia del módulo, `ClientCrudPayment.vue` (ficha del cliente, "Crear Gasto") y el flujo de conciliación por IA, todos con el mismo abono de saldo + historial.
- **Permisos** (`module.json`): `payments_view_providers`, `payments_manage_providers`, `payments_view_receipts`, `payments_view_webhooks_log`, `payments_assign_clabe`, `payments_capture_manage` + `conciliacion.manage` (migración propia, otorgado a Mostrador/Tere).
- **Eventos escuchados** (no propios, consumidos): registra sus listeners como consumidor del gateway único de WhatsApp (ver "Consume").

**Consume**
- **WhatsApp** — gateway único `WhatsAppAgent`: escucha `WhatsAppMediaReceived` (comprobantes entrantes) y `WhatsAppTextReceived` (respuestas dentro de una identificación en curso) vía `ConciliationListener`/`ConciliationTextListener`. No monta integración propia (convención de servicios compartidos únicos).
- **IA** — adaptador de Claude del módulo IA (`ClaudeApiClient`) para leer comprobantes (`PaymentReceiptExtractor`); no tiene cliente HTTP ni API key propios.
- **Clientes** — modelo `Client`/`ClientMainInformation` para buscar al cliente por CLABE, referencia MEG o nombre, y abonar su saldo.
- **Finanzas** — modelo global `App\Models\Payment`/`PaymentDetail`/`MethodOfPayment`; `PaymentReferenceService::ensureFor()` es consumido por el módulo Finanzas al crear proformas para asegurar que todo cliente tenga su referencia MEG.
- **Usuario de sistema** — `User::systemBot()` (usuario `MEGAISP`, sin login posible) como autor (`add_by`) de los pagos que la IA aplica automáticamente, para que queden trazables en el historial igual que un pago capturado por una persona.
- **Configuración** — `config/payments.php` (interruptores de automatización, todos apagados por defecto) y `PaymentProvider.config` (credenciales OpenPay por proveedor, gestionadas desde `/finanzas/payment-providers`, nunca hardcodeadas).
- **Colas** — `ApplyIdentifiedPaymentJob`, `ConciliationIntakeJob`, `GatewayConciliationIntakeJob` corren en el worker estándar (`QUEUE_CONNECTION=database`).

> _Servicios compartidos únicos: este módulo NO monta su propia integración de WhatsApp ni su propio cliente de IA — se conecta a `WhatsAppAgent` (gateway) y al adaptador Claude del módulo IA, como manda la convención del proyecto._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
