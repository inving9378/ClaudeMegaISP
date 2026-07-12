# Módulo Portal de Pago (PortalPago)

> `app/Modules/Addons/PortalPago/` · slug `addon-portal-pago` · módulo **addon** (activo, id=220).

## 0. En simple
Es la liga de pago que se le manda al cliente para que transfiera por banco; el sistema checa solo con Banxico si el dinero llegó y, si cuadra, marca la factura como pagada sin que nadie tenga que revisarlo a mano.

## 1. Qué es
Addon de **cobro por transferencia SPEI con conciliación automática vía CEP de Banxico**: genera una liga única por factura, el cliente transfiere a una CLABE propia de Meganet y reporta su clave de rastreo, y el sistema valida ese comprobante contra el servicio público de Banxico (Comprobante Electrónico de Pago). Cero comisión a terceros — no depende de un proveedor de cobro como OpenPay. Lo que no cuadra cae en una bandeja de revisión manual.

## 2. Para qué sirve
Le resuelve a cobranza el mismo problema que Payments/Domiciliación pero por otra vía: transferencias SPEI directas a una cuenta de Meganet, sin intermediario ni comisión.
- **Al cliente**: recibe una liga (`/f/{token}`) con el monto exacto de su factura, ve a qué CLABE transferir, y reporta su clave de rastreo (18 caracteres) + comprobante opcional. Puede volver a `/f/{token}/estado` a ver si ya se concilió.
- **Al equipo de cobranza** (permisos `pagos.*`): evita revisar transferencia por transferencia a mano — el sistema intenta conciliar solo contra Banxico; solo lo que no cuadra (monto distinto, CLABE distinta, o Banxico no concluyente) llega a la bandeja de conciliación (`/pagos/conciliacion`) para aprobar/rechazar.
- También soporta **recurrencia asistida**: para clientes con cobro mensual fijo, genera automáticamente la liga del mes en su día de corte (sin auto-débito — el cliente sigue transfiriendo él mismo).

## 3. Cómo funciona

### 3.1 Piezas de datos
- **`portal_pago_accounts`** (`PortalPagoAccount`) — cuentas propias de Meganet a las que el cliente transfiere (CLABE, banco, titular, activa/inactiva).
- **`portal_pago_payment_links`** (`PortalPagoPaymentLink`) — una liga = una factura legacy (`client_invoices`, vía `document_id`) expuesta con token UUID, monto esperado, referencia única `PP{10}` y expiración (`link_ttl_days`, default 7 días). Estados: `pendiente → reportado → validado/conciliado`, o `expirado`/`rechazado`.
- **`portal_pago_payment_reports`** (`PortalPagoPaymentReport`) — cada vez que el cliente reporta una transferencia (clave de rastreo, banco emisor, fecha, monto, comprobante en disco privado). Estados: `pendiente_validacion → validado/discrepancia/rechazado`, guarda el `cep_resultado` (JSON crudo de Banxico).
- **`portal_pago_recurrences`** (`PortalPagoRecurrence`) — cliente + cuenta + día de corte + monto fijo, para generación mensual automática de ligas.

### 3.2 Flujo de pago (cliente, sin login)
`PublicPagoController` (rutas `/f/{token}`, prefijo `web` + `throttle:10,1`, **sin auth**):
- `show` — pinta la pantalla de pago (monto, CLABE, días restantes) resolviendo el token vía `PortalPagoLinkService::findByTokenForPayment` (falla-cerrado: token inexistente, expirado o no-pagable → siempre la misma respuesta 404 neutra `no_disponible.blade.php`, para que no se pueda enumerar tokens).
- `reportar` (`ReportarPagoRequest`) — guarda el `PortalPagoPaymentReport` (comprobante siempre en `storage/app/private/portal-pago/comprobantes/`, nunca público) y dispara `CepValidatorService::validar()`.
- `estado` — pantalla de confirmación (pendiente/conciliado/rechazado) con botón de soporte por WhatsApp.

### 3.3 Validación CEP y conciliación
- **`CepValidatorService`** orquesta: corre *guards* de negocio (liga ya conciliada, factura ya pagada, misma clave de rastreo ya validada → lanza `CepConciliationException`, fail-closed), selecciona el driver según `PAGOS_CEP_MODE` (`banxico`|`manual`|`hybrid`, default `hybrid`) y aplica un **match antifraude de 3 condiciones**: monto exacto (comparación decimal, no float), CLABE beneficiaria = la de la cuenta de la liga (y que esté activa), y estado Banxico = `LIQUIDADO`. Solo si las 3 cuadran pasa a `VALIDADO`; si Banxico encontró el CEP pero algo no cuadra, `DISCREPANCIA`; si Banxico no respondió o no fue concluyente, se deja `pendiente_validacion` para revisión humana (nunca lanza excepción que tumbe el flujo).
  - `BanxicoCepDriver` — POST al formulario público (no documentado/no-API oficial) `banxico.org.mx/cep/valida.do`; defensivo al extremo (timeouts cortos, 1 reintento, cualquier fallo de red/parseo → `inconclusive()`, jamás excepción).
  - `ManualCepDriver` — nunca llama a Banxico; todo reporte queda para revisión humana (modo `manual`, o respaldo si Banxico está caído).
- **`ConciliacionService::conciliar()`** — el paso que efectivamente mueve dinero: crea un `Payment` polimórfico a `Client` (autor = `User::systemBot()` = MEGAISP) dentro de una transacción, lo que dispara la cadena de observers existente (`PaymentObserver` → `PaymentClientJob` → reactivación/desbloqueo MikroTik). Marca la factura legacy como pagada, dispara el evento `InvoicePaid` (comisiones de Embajadores) y cierra la liga como `conciliado`. Se invoca tanto automáticamente (tras un CEP validado) como manualmente (aprobación desde la bandeja).

### 3.4 Bandeja de conciliación y administración (auth, permisos por URL)
`Controllers/Admin/`:
- **`ConciliacionController`** (`/pagos/conciliacion`, permiso `pagos.conciliar`) — lista reportes `pendiente_validacion`/`discrepancia`; `aprobar` fuerza la conciliación manual (mismo `ConciliacionService`); `rechazar` cierra el reporte sin mover dinero; `comprobante` sirve el archivo privado solo autenticado (nunca expone `storage/app/private` directo).
- **`CuentasController`** (`/pagos/cuentas`, permiso `pagos.cuentas.manage`) — CRUD de `portal_pago_accounts`.
- **`LinksController`** (`/pagos/links`, permiso `pagos.links.manage`) — genera ligas manualmente (busca cliente, lista sus facturas pendientes, `PortalPagoLinkService::generate`) e historial.
- **`DashboardController`** (`/pagos`, permiso `pagos.view`) — KPIs del mes (ligas generadas, % auto-conciliadas sin revisión manual, excluye ligas expiradas).

### 3.5 Recurrencia asistida
`pagos:enviar-recurrentes {--dry-run}` (`EnviarRecurrentesCommand`) — **NO registrado en el schedule** (línea comentada en `Kernel.php`, corre solo manual por ahora). Por cada `PortalPagoRecurrence` activa cuyo día de corte ya llegó este mes (con catch-up si el cron falló un día) y no se le generó liga aún este mes: toma la factura pendiente más antigua del cliente, genera la liga y **solo loguea** token+teléfono en `laravel.log` — el envío real por WhatsApp/SMS queda fuera de este comando (lo hace el operador o un canal que se conecte después).

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Rutas públicas sin auth** (`/f/{token}`, `/f/{token}/reportar`, `/f/{token}/estado`; `throttle:10,1`): la liga de pago para el cliente.
- **Rutas admin** (`/pagos`, `/pagos/conciliacion`, `/pagos/cuentas`, `/pagos/links`, `/pagos/comprobante/{report}`) gateadas por permiso vía `can:`.
- **API JSON** bajo `/api/pagos/*` (kpis, conciliacion, cuentas, links, búsqueda de clientes/facturas) consumida por las pantallas Vue/Quasar de cada Blade admin.
- **Permisos** `pagos.view`, `pagos.conciliar`, `pagos.cuentas.manage`, `pagos.links.manage`.
- **Comando artisan** `pagos:enviar-recurrentes {--dry-run}` (sin cron activo).
- **Evento** `InvoicePaid` (del núcleo, no propio del módulo) — se dispara al conciliar, consumido por Embajadores para comisiones.

**Consume**
- **`ClientInvoice`** (`client_invoices`, núcleo/legacy) — factura que cubre cada liga; se marca `Pagado` al conciliar.
- **`Payment`** (núcleo) — se crea polimórfico a `App\Models\Client` para reusar la cadena de observers existente (reactivación de servicio, desbloqueo MikroTik); es la ÚNICA vía de reactivación que usa este módulo, nunca toca `client_internet_services` directamente.
- **`User::systemBot()`** (MEGAISP) — autor (`add_by`) de los pagos conciliados automática o manualmente.
- **Servicio público de Banxico** (`banxico.org.mx/cep/valida.do`) — único cliente HTTP externo del módulo; no es un servicio compartido designado del sistema (es específico de este módulo, formulario no documentado, sujeto a cambios sin aviso por parte de Banxico).
- **Disco `local` (privado)** — comprobantes de pago en `storage/app/private/portal-pago/comprobantes/`.

> _Servicios compartidos únicos respetados: no usa WhatsApp/IA (el envío de la liga recurrente queda fuera del comando, sin canal propio de WhatsApp montado). No hay entrada en el registro de contratos inter-módulos (`docs/contratos/`) para Portal de Pago al momento de esta doc. Nota de nombres: existe otro controlador `PortalPagoController` bajo `App\Modules\Addons\PortalCliente\Controllers` — es el cobro con tarjeta OpenPay del Portal Cliente, un módulo distinto que coincide solo en el nombre; no forma parte de este addon._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
