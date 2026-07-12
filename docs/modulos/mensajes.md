# Módulo Mensajes

> Bandeja interna, recordatorios de cobro y correos transaccionales (factura, proforma, pago). `app/Modules/Addons/Mensajes/` · slug `addon-mensajes` · módulo **addon**, activo, sin dependencias declaradas.

## 0. En simple
Es la pantalla donde el equipo revisa y reenvía los correos automáticos que le llegan a un cliente: recordatorios de pago, avisos de factura, de proforma y de pago recibido.

## 1. Qué es
Módulo **addon** que agrupa, bajo el prefijo de rutas `/message/*`, cuatro listados tipo bandeja (`Inbox` como shell visual) para los correos de cobranza y facturación que el sistema genera automáticamente: recordatorios de pago, tickets de pago, facturas y proformas. No es una mensajería interna entre usuarios (no hay redacción libre) — es la consola de **revisión y reenvío manual** de esos correos ya generados por otros módulos.

## 2. Para qué sirve
Le da a Cobranza/Finanzas un solo lugar para ver el estado de cada correo transaccional (enviado, pendiente, con qué destinatario) y reenviarlo a mano con un clic si hizo falta (p. ej. un recordatorio que no llegó). También aloja las plantillas de esos correos vía `config_sections` en `/message/invoice_email` (editables desde Configuración → Sistema).

## 3. Cómo funciona

**UI (`Inbox.vue`, componente único `message.inbox`):** un shell con pestañas principales — *Recordatorios*, *Pagos*, *Facturas*, *Facturas Proformas* — cada una con un sub-tab que monta un datatable genérico (`ContentConfig.vue`) apuntando a un `url_base` distinto (`message/reminder`, `message/payment_email`, `message/invoice_email`, `message/proforma_invoice_email`). Es decir: una sola vista Vue reutiliza el mismo patrón de tabla para los cuatro tipos de correo, cambiando solo el endpoint.

**Controllers** (`Controllers/`), todos con la misma forma (constructor fija `model`/`url`/`module`, `index()` para la vista Blade legacy, `table()` para el datatable, `sendMessage()` para reenviar):
- `InboxController` — sirve la vista shell (`index`) y `getDataTabs()` (lee `ConfigFinanceNotificationRepository::getAll()`, catálogo de tipos de notificación financiera usado para poblar las pestañas).
- `ReminderController` — CRUD de lectura + reenvío sobre `App\Models\Reminder` (tabla `reminders`). `sendMessage()` llama `EmailConfigService::sendEmail('reminder', $reminder)`.
- `InvoiceEmailController` — sobre `App\Models\InvoiceEmail` (tabla `invoice_emails`); `sendEmail('invoice', …)`.
- `ProformaInvoiceEmailController` — sobre `App\Models\ProformaInvoiceEmail` (tabla `proforma_invoice_emails`); `sendEmail('proforma_invoice', …)`.
- `PaymentEmailController` — sobre `App\Models\PaymentEmail` (tabla `payment_emails`); `sendEmail('payment', …)`. **Sus rutas están comentadas** en `routes.php` (preservado así desde el legacy; el controller existe pero hoy no es alcanzable por HTTP salvo por el tab "Pagos" del Inbox, que apunta a `message/payment_email` — ver nota de rutas abajo).

**Quién llena las tablas que este módulo solo lee/reenvía:** el módulo Mensajes no genera esos registros, los consume. `App\Console\Commands\Active\ReminderPaymentCommand` (`app:reminder-payment-command`, programado dinámicamente vía `command_configs`) evalúa la configuración de recordatorios (`BillingReminder`) y escribe filas en `reminders` para los clientes próximos a vencer. Las tablas `invoice_emails`/`proforma_invoice_emails`/`payment_emails` se pueblan desde el ciclo de facturación (fuera de este módulo).

**Envío real de correo:** los cuatro controllers delegan en `App\Modules\Core\Configuracion\Services\EmailConfigService::sendEmail()` (servicio **compartido**, no propio de Mensajes), que arma una `StandardNotification` y la despacha por el canal `mail` de Laravel usando la config SMTP guardada en `EmailSetting`.

**Nota de rutas:** el módulo declara sus rutas en `routes.php` propio (patrón moderno de addon), con `web` explícito en el `Route::middleware()` porque `loadRoutesFrom()` no aplica el grupo `web` automáticamente. `PaymentEmailController` no tiene rutas activas (`table`/`send_message` comentadas) — el tab "Pagos" del Inbox quedaría sin backend si se navega a él tal cual está hoy.

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Rutas web** bajo `['web','auth','check_route_permission']`, prefijo `/message/*`: `inbox/` (+ `get-data-tabs`), `reminder/{table,send_message}`, `invoice_email/table`, `proforma_invoice_email/{table,send_message}`. `payment_email/*` declarado en el código pero comentado (sin ruta activa).
- **Permisos Spatie**: `inbox_view_inbox` (ver bandeja), `inbox_send_message` (gatea el botón "Reenviar" en las 4 tablas de acciones), `inbox_add_inbox`/`inbox_edit_inbox`/`inbox_delete_inbox` (declarados en `module.json` y en el catálogo de roles del frontend, sin consumidor de código encontrado hoy — probable remanente de un diseño de mensajería interna más amplio) y `labels_view_labels` (declarado, sin consumidor encontrado).
- **`admin_cards`** ("Mensajes" → `/message/inbox`) y **`config_sections`** (`mensajes_notificaciones`, plantillas de correo de cobranza en `/message/invoice_email`), ambos consumidos por las pantallas dinámicas de Administración/Configuración del Core.
- ⚠️ El `menu` declarado en `module.json` (Inbox/Recordatorios) **no aparece en el sidebar izquierdo**: el sidebar de MegaISP es Blade estático (`sidebar.blade.php`) y no itera el `menu` de los addons salvo que se agregue el bloque a mano (patrón ya documentado para otros addons en `CLAUDE.md`) — hoy no existe ese bloque para Mensajes. La única entrada a `/message/inbox` es la `admin_card` o la URL directa.

**Consume**
- **`EmailConfigService`** (Core Configuración) — servicio compartido para el envío real de correo; Mensajes no tiene su propio cliente SMTP.
- **`ConfigFinanceNotificationRepository` / `ConfigFinanceNotification`** (Core Configuración) — catálogo de tipos de notificación financiera que arma las pestañas del Inbox.
- **`App\Models\Reminder`, `InvoiceEmail`, `ProformaInvoiceEmail`, `PaymentEmail`** — modelos en `app/Models` (fuera del namespace del módulo), poblados por el ciclo de facturación/cobranza (`ReminderPaymentCommand` para recordatorios; el resto por el flujo de emisión de documentos), no por Mensajes.
- **Sistema de permisos Spatie** (`check_route_permission`) — gating estándar de todas las rutas.

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
