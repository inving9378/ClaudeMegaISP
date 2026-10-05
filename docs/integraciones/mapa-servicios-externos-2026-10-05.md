# Mapa de servicios externos y plan "proveedor por módulo" — 2026-10-05

Levantado para pasar los módulos de **servicios** al mismo esquema que ya tiene la IA (Integraciones →
"Módulos IA": cada módulo elige integración del Hub, cambiar de proveedor no rompe nada). Ejemplo guía:
cambiar una línea de WhatsApp de **Evolution** a la **API oficial de Meta (Cloud API)**.

Barrido completo de solo-lectura (tres revisiones en paralelo + barrido de hosts/llaves en `app/`,
`config/`, `routes/`). Rutas relativas a `/var/www/megaisp`. Abreviaturas: `WA` =
`app/Modules/Addons/WhatsAppAgent`, `MK` = `app/Modules/Addons/Marketing`, `PY` = `app/Modules/Addons/Payments`,
`AD` = `app/Modules/Addons`. **Ningún valor de llave aparece aquí**, solo nombres.

El Circuito CC (vuelta.sh, Revisor, Válvula, Jarvis, API roadmap-externo, MCP) **no se toca**.

---

## 1. Resumen ejecutivo

- El Hub resuelve llaves **por proveedor fijo** (`getKey('pexels')`, `getIntegration('meta')`). Solo la IA
  tiene "proveedor por módulo" (`ia_asignaciones`). Ese patrón es la plantilla a copiar.
- **WhatsApp** es lo más grande: **dos pilas Evolution independientes** (gateway de líneas en WhatsAppAgent y
  la de Marketing), **27 caminos de envío** y **~28 tipos de mensaje proactivo** (los que en la API oficial
  necesitan plantilla aprobada fuera de la ventana de 24 h). El número real de ventas entra por el webhook de
  **Marketing**; las líneas creadas desde el panel entran por el gateway. Conciliación existe dos veces.
- **Correo**: dos fuentes de SMTP (`.env` en ~15 envíos directos, `email_settings` solo en el camino de
  StandardNotification). Sin resolvedor por módulo.
- **Push (FCM)**: dos clientes con la API *legacy* de Google (apagada en 2024, verificar) → los avisos al
  celular están muertos. 4 tablas de tokens distintas.
- **Mapas**: la llave de Google que se usa vive en `system_map_credentials` (texto plano); la del Hub
  `google_maps` está huérfana; tiles OSM y Nominatim repetidos en ~30 componentes.
- **Dinero, red, telefonía, infraestructura**: **nada pasa por el Hub**. Solo tienen sentido como
  "proveedor por módulo": **PAC de CFDI** (interfaz ya existe, sin PAC) y **pasarela de pagos** (OpenPay
  duplicado: SDK + Guzzle, dos almacenes de llaves, dos webhooks). MikroTik, OLTs, RADIUS, Asterisk,
  troncales SIP, GPS son configuración **por equipo**, no van al Hub.
- **IA que faltaba**: generación de imágenes de Marketing con **Replicate** (`MK/Services/ImageGeneratorService.php`,
  llave en `.env` `REPLICATEAPITOKEN`, modelo fijo en código) no pasa por Módulos IA.

---

## 2. WhatsApp (detalle)

### 2.1 Transporte

**Pila gateway** — `WA/Services/EvolutionApiService.php`. Credenciales SOLO de `config/whatsapp.php`
(`WHATSAPP_API_URL`, `WHATSAPP_API_KEY`); nunca Hub ni `whatsapp_instances.api_url/api_key` (columnas muertas).
Nombre de instancia = `whatsapp_instances.slug`.

| Método | Endpoint Evolution |
|---|---|
| `sendAndLog` :54 | (sin HTTP) busca línea por slug o `default_instance` con `firstOrFail`, crea `whatsapp_messages` out/pending, encola `SendWhatsAppMessageJob` |
| `sendTextViaApi` :84 | `POST /message/sendText/{name}` — respeta `whatsapp.fake` y `whatsapp.sender_enabled` (default OFF → solo log) |
| `getQrCode` :144, `ensureInstanceExists` :171, `getConnectionStatus` :204, `getInstanceProfile` :221, `disconnect` :258, `createInstance` :299 | `/instance/connect`, `/instance/fetchInstances`, `/instance/connectionState`, `/instance/logout`, `/instance/create` (registra webhook por instancia `/whatsapp/webhook/{slug}`, `WHATSAPP-BAILEYS`, `base64:true`) |
| `getBase64FromMediaMessage` :276 | `POST /chat/getBase64FromMediaMessage/{name}` |

Fachada `WA/Services/WhatsAppGateway.php` (`sendText` :35, `downloadMedia` :47). **Solo texto**: no media,
plantillas, typing ni leído.

**Pila Marketing** — `MK/Services/EvolutionApiService.php`. URL: Hub `evolution` `config.endpoint` →
`marketing_settings.evolution_api_url`. Llave: Hub → `config('marketing.whatsapp_status_api_key')` →
`marketing_settings.evolution_api_key`. Instancia: **solo** `marketing_settings.evolution_instance_name`
(el `config.instance` del Hub se ignora). Envío **síncrono**, **sin kill-switch** (siempre manda de verdad).
Métodos: `sendText` :27 (usado), `sendTyping` :107 (usado), `getBase64FromMediaMessage` :67 (usado),
`sendMedia`/`fetchInstanceStatus`/`setWebhook`/`markAsRead` (muertos). Helpers `phoneToJid`/`jidToPhone`.

**HTTP directo** — `MK/Services/Publishing/Drivers/WhatsAppStatusDriver.php` (estados/historias a
`status@broadcast`). Bug: :22 puede usar la **llave** del Hub como URL; `publish()` ignora el Hub.

### 2.2 Entrada

| Ruta | Controlador | Autenticación | Parser |
|---|---|---|---|
| `POST /whatsapp/webhook/{slug}` | `WA/Controllers/WhatsAppWebhookController.php:19` | `X-Webhook-Secret`/`apikey` vs `whatsapp_instances.webhook_secret` | `WA/Services/WhatsAppRouterService.php` (dedup por `evolution_message_id`; no ignora grupos; no lee captions; audio/video/sticker/ubicación llegan como evento de texto vacío) |
| `POST /webhooks/marketing/evolution` | `MK/Controllers/EvolutionWebhookController.php:15` | token de `marketing_settings.evolution_webhook_token` | `MK/Jobs/ProcessIncomingMessageJob.php` (ignora grupos y fromMe; lee captions; **sin dedup**) |

Eventos del gateway: `WhatsAppMessageReceived` / `WhatsAppTextReceived` / `WhatsAppMediaReceived` (llevan
`lineFunctions` y `rawMessage` con forma Evolution). Suscriptores:

| Evento | Listener | Función de la línea |
|---|---|---|
| Texto | `WA/Listeners/IaAutoReplyListener.php` | `ventas` (IA `whatsapp.soporte`) |
| Texto | `WA/Listeners/AgenteVentasTextListener.php` | `agente_ventas` (IA `whatsapp.ventas`) |
| Media | `PY/Listeners/ConciliationListener.php` → `GatewayConciliationIntakeJob` | `conciliacion` |
| Texto | `PY/Listeners/ConciliationTextListener.php` | `conciliacion` + sesión activa |
| Texto/Media | `MK/Listeners/GatewayMessageListener.php` | `ventas` (modo `legacy` = no hace nada) |

Funciones `cobranza`, `soporte`, `atencion`: sembradas, **sin consumidor**. `WhatsAppLineResolver`: sin uso.

**Dependencias de la forma del payload Evolution** (a reescribir con un parser neutro):
`WhatsAppRouterService` (22, 37, 44, 55-63, 151, 163, 175-219), `WhatsAppInstanceController` (69-72, 93),
`WA EvolutionApiService` (132, 183, 237-240, 289-290), `WhatsAppGateway` (63-91),
`MK EvolutionWebhookController`, `ProcessIncomingMessageJob` (32-73, 137-155), `UpdateMessageStatusJob`
(forma v1, probablemente nunca coincide), `SendOutboundMessageJob` (37, 56), `DownloadWhatsAppMediaJob`,
`ConversationResolverService` (17-22, 58), `WhatsAppStatusDriver`, y en Pagos:
`ConciliationIntakeJob:229`, `ConciliationRouter:145`, `IdentificationSessionStarter:100`,
`ReconciliationQueueController:204,274` (sacan el teléfono del `remoteJid`). Tests en
`tests/Feature/Marketing/*`, `Core/ApiIntegrationServiceTest.php`, `Referrals/Phase6Test.php`,
`Portal/DomiciliacionTest.php`, `Payments/AutoApplyFlagContractTest.php`.

### 2.3 Datos

| Tabla | Columnas atadas al proveedor | Falta para la API oficial |
|---|---|---|
| `whatsapp_instances` | `slug` (= instancia Evolution), `webhook_secret`, `status`/`qr_code`/`qr_expires_at`, `phone_number`, `default_instance` | columna de **proveedor**, `phone_number_id`, WABA, referencia a token, app secret, verify token |
| `whatsapp_conversations` | `contact_number` (`521…`), `contact_name` (pushName) | `last_inbound_at` (ventana 24 h) |
| `whatsapp_messages` | `evolution_message_id` (único; ahí iría el `wamid`), `status`, `message_type` | nombre/parámetros de plantilla |
| `marketing_settings` | `evolution_api_url`, `evolution_api_key`, `evolution_instance_name`, `evolution_webhook_token` | — |
| `marketing_conversations` | `external_thread_id` = JID completo (es el **destino** de envío) | ya tiene `last_inbound_at` |
| `marketing_messages` | `external_message_id`, `metadata` = nodo `data` crudo | — |
| `marketing_publication_channels` | `platform_config` `{evolution_url, evolution_api_key, evolution_instance}` (texto plano) | — |
| `whatsapp_payment_extractions`, `whatsapp_identification_sessions` | `source` = `marketing` \| `gateway` | — |

### 2.4 Quién manda WhatsApp (27 caminos)

Pila **G** = gateway (encolado, respeta kill-switch). **M** = Marketing (síncrono, sin kill-switch). **D** = HTTP directo.
"Proactivo" = lo inicia la empresa (en la API oficial requiere plantilla fuera de 24 h).

| # | Dónde | Pila | Qué | Línea | Tipo |
|---|---|---|---|---|---|
| 1 | `WA/Controllers/WhatsAppPanelController.php:85` | G | respuesta de agente en el panel | la de la conversación | respuesta (puede caer fuera de 24 h) |
| 2 | `WA/Controllers/WhatsAppSendController.php:25` (`POST /whatsapp/api/send`) | G | texto libre por API | slug o principal | normalmente proactivo |
| 3 | `WA/Services/WhatsAppAutoReplyService.php:108` | G | respuesta IA | conversación | respuesta |
| 4 | `WA/Services/AgenteVentas/AgenteVentasReplyService.php:115` | G | respuesta agente de ventas | conversación | respuesta |
| 5 | `WA/Services/WhatsAppCrmService.php:336` | G | "Tu instalación está agendada…" | conversación | transaccional (desde panel puede caer fuera de 24 h) |
| 6 | `WA/Listeners/SendReferralShareListener.php:34` | G | invitación masiva de embajadores | principal | **proactivo a terceros sin opt-in** |
| 7 | `PY/Listeners/ConciliationTextListener.php:79` | G | flujo de identificación de pago | evento | respuesta |
| 8 | `PY/Jobs/GatewayConciliationIntakeJob.php:240` | G | aviso duplicado / siguiente paso | mensaje | respuesta |
| 9 | `PY/Services/Conciliation/ConciliationResponder.php:98` | G | "Tu pago quedó aplicado" | conversación | proactivo (utilidad) |
| 10 | `PY/Services/PaymentApplicationService.php:216` | G | "¡Pago recibido!" (webhook SPEI) | principal | **proactivo** |
| 11 | `AD/Domiciliacion/Controllers/EnrollmentLinkController.php:146` | G | liga para domiciliar tarjeta | principal | **proactivo** |
| 12 | `AD/Domiciliacion/Commands/DomiciliacionCobrarCommand.php:360` | G | 3 avisos de cobro (éxito, intento fallido, fallo final) | principal | **proactivo ×3** |
| 13 | `app/Modules/Core/Clientes/Services/BillingPauseService.php:370` | G | 3 avisos de pausa (programada, inicia, termina) — teléfono sin normalizar | principal | **proactivo ×3** |
| 14 | `app/Console/Commands/Active/SendBillingNotificationsCommand.php:167` | G | recordatorio de facturación si no hay correo | principal | **proactivo** (`BILLING_WHATSAPP_FALLBACK_ENABLED`) |
| 15 | `AD/Talento/Controllers/TalentoEmployeeDocumentController.php:271` | G | recordatorio de documentos a personal | principal | proactivo (interno) |
| 16 | `AD/Talento/Controllers/TalentoWorkOrderController.php:241` | G | OT asignada a técnico | principal | proactivo (interno) |
| 17 | `MK/Jobs/SendOutboundMessageJob.php:55` (desde agente IA, conversación humana de Marketing y `ConciliationResponder:121`) | M | respuestas y "pago aplicado" | instancia de `marketing_settings` | respuesta / puede caer fuera de 24 h |
| 18 | `MK/Jobs/ProcessIncomingMessageJob.php:121` | M | "escribiendo…" | Marketing | no es mensaje |
| 19 | `app/Services/Referrals/ReferralWhatsAppNotifier.php:130,177` | M | 6 avisos de referidos | empresa 1 | **proactivo ×6** |
| 20 | `AD/WarRoom/Observers/ActionItemObserver.php:52` | M | acción asignada | empresa 1 | proactivo (interno) |
| 21 | `AD/WarRoom/Jobs/SendMeetingMinutesJob.php:45` | M | minuta de junta | empresa 1 | proactivo (interno) |
| 22 | `AD/PortalPago/Commands/EnviarRecurrentesCommand.php:151` | M | resumen diario | empresa 1 | proactivo (interno) |
| 23 | `AD/Roadmap/Support/AlertaExterna.php:83` (Jarvis) | M | alarma — **Circuito: no se toca** | `CIRCUITO_JARVIS_ALERTA_COMPANY_ID` | proactivo (puede ir a un grupo) |
| 24 | `AD/Flotas/Services/Notifications/Drivers/WhatsappChannel.php:37` | M | evento de geocerca | empresa = `client_id` del vehículo | proactivo |
| 25 | `AD/Flotas/Services/Notifications/DocumentAlertDispatcher.php:112` | M | vencimiento de documentos | empresa del vehículo | proactivo |
| 26 | `AD/Flotas/Services/Notifications/SubscriptionAlertDispatcher.php:82` | M | vencimiento de suscripción | `$companyId` | proactivo |
| 27 | `MK/Services/Publishing/Drivers/WhatsAppStatusDriver.php:75` | D | **video a estados** (`status@broadcast`) | `platform_config` o env | difusión — **no existe en la API oficial** |

No vivos: recordatorios de la FSM de identificación (solo el simulador), `DocumentacionCorporativa/PendientesRecordatorioCommand` (no cableado), `CampaignSchedule` con canal whatsapp (nadie los envía).

### 2.5 Lo que solo existe en Evolution

| Función | Dónde | Con la API oficial |
|---|---|---|
| QR / crear / conectar / desconectar instancia | `WA EvolutionApiService:144-346`, `WhatsAppInstanceController:34-157`, `WhatsAppInstanceManager.vue` | alta en Meta: `phone_number_id` + WABA + token |
| Webhook por instancia con `X-Webhook-Secret` | `createInstance` | **un** webhook de app (GET `hub.challenge` + POST firmado `X-Hub-Signature-256`), ruteo por `phone_number_id` |
| Estados/historias | `WhatsAppStatusDriver` | **no existe** |
| "Escribiendo…" y `delay` | `MK sendTyping`, ambos `sendText` | mecanismo distinto / no existe |
| Descarga de media `getBase64FromMediaMessage` | ambos clientes | `GET /{media-id}` → URL → descarga con token |
| Grupos | gateway no los filtra; alerta Jarvis a grupo | no equivalente |
| Mensajes a contactos sin opt-in (embajadores masivo) | #6 | plantilla de marketing + opt-in |

### 2.6 Configuración

`WHATSAPP_API_URL`, `WHATSAPP_API_KEY` (lo leen **ambas** pilas), `WHATSAPP_DEFAULT_INSTANCE` (sin uso en
runtime), `WHATSAPP_WEBHOOK_BASE_URL`, `WHATSAPP_FAKE`, `WHATSAPP_SENDER_ENABLED`, `WHATSAPP_AUTO_REPLY*`,
`WHATSAPP_AGENTE_VENTAS_*`, `WHATSAPP_MARKETING_GATEWAY_MODE`, `WHATSAPP_API_BASE`, `WHATSAPP_INSTANCE`,
`WHATSAPP_TOKEN` (solo informativo), `PAYMENTS_WA_CONCILIATION`, `PAYMENTS_WA_AUTORESPOND`,
`BILLING_WHATSAPP_FALLBACK_ENABLED`, `PAGOS_RECURRENTES_RESUMEN_WHATSAPP`, `CIRCUITO_JARVIS_ALERTA_*`,
`SOPORTE_WHATSAPP` (solo liga wa.me). En BD: `marketing_settings.evolution_*`, Hub `evolution`,
`marketing_publication_channels.platform_config`, `whatsapp_instances.webhook_secret`, `conciliation_settings`.

Pantallas: `WhatsAppPanel.vue`, `WhatsAppInstanceManager.vue`, `WhatsAppFunctionManager.vue`,
`MarketingConversationsView.vue`, `publishing/ChannelsSetupView.vue`, `hub/IntegrationsHubView.vue`, y las de
Pagos (`conciliacion-config`, `whatsapp-comprobantes`, `conciliacion-cola`, `conciliacion-sim`).

### 2.7 Fallas y riesgos de WhatsApp encontrados

1. **Los avisos proactivos del gateway dependen de una línea "principal"** (`default_instance`). En dev no
   hay ninguna (la única línea, `pruebas`, está desconectada y no es principal) → los avisos #2, #6, #10-#16
   fallan y el error se traga. Verificar en producción qué línea es principal.
2. La pila Marketing no tiene interruptor de envío ni modo prueba: referidos, WarRoom, Flotas, resumen de
   PortalPago mandan siempre de verdad.
3. `WhatsAppStatusDriver.php:22` puede usar la llave del Hub como URL.
4. `UpdateMessageStatusJob` usa la forma v1 (estados de entrega probablemente nunca se registran).
5. `EmbajadorExtApiController.php:283` escribe `channel='whatsapp_masivo'`, que no está en el enum (falla callado).
6. Normalización de teléfono `521` duplicada en 6 lugares e inconsistente (pausas pasan el teléfono crudo;
   Flotas sin lada). En la API oficial el `wa_id` de México va como `52…` → hay que centralizarla.
7. Flotas usa `new EvolutionApiService($vehicle->client_id)`: para empresas ≠ 1 probablemente no hay credenciales.
8. Con `base64:true` la media cruda viaja dentro de `rawMessage` por 5+ listeners encolados y a los logs.
9. Los JID `@lid` no se manejan.

---

## 3. Correo, push, mensajería, publicación, mapas y medios

### 3.1 Correo

| Pieza | Detalle |
|---|---|
| Transporte | `config/mail.php` (SMTP por `.env`: `MAIL_*`). Paquetes Mailgun y Postmark instalados; SES requiere `aws/aws-sdk-php` (no está). |
| SMTP en BD | `email_settings` (contraseña en **texto plano**) aplicado solo por `app/Modules/Core/Configuracion/Services/EmailConfigService.php::setMailConfig`, llamado únicamente desde `StandardNotification::toMail`. |
| Pantalla de SMTP | `Core/Configuracion/Controllers/EmailSetting/EmailSettingController.php:16` apunta a un modelo que no existe → **probablemente rota**. |
| Mailer `ventas` | lo pide `EvaluacionEmpresarialMail` pero no existe; se ignora. |
| Bug | `StandardMail.php:23` lee `title` pero se manda `subject` → todos los correos del camino BD salen con asunto "Notificación". |

Quién manda correo: CRM (`Utils/NotificationController.php:30`), Tickets (`SendTicketNotificationJob`,
`TicketOpen`), facturación cada 15 min (`SendBillingNotificationsCommand.php:111`, con PDF), proforma
(`Finanzas/InvoiceController.php:131`), reenvíos de Mensajes (4 controladores), envíos masivos
(`SendAllEmailsCommand`, `SendEmailsProformaInvoiceCommand`), Domiciliación (2), PortalPago (resumen),
PortalCliente (recibo de pago), EvaluadorEmpresarial, Marketing (`EmailBlastDriver`, `PilotCampaignService`),
Flotas (3), Talento (vencimiento de licencias). Sin webhooks de rebote/queja.

### 3.2 Push, SMS, otros

| Servicio | Estado |
|---|---|
| Push MegaFamilia | `AD/MegaFamilia/Services/FcmService.php` — FCM **legacy** (`fcm.googleapis.com/fcm/send`), llave en `megafamilia_settings.firebase_server_key` → `FCM_SERVER_KEY`. **Muerto** si Google ya apagó la API legacy. |
| Push Talento | `AD/Talento/Controllers/TalentoMobileApiController.php:826-859` — segundo cliente FCM legacy, solo `FCM_SERVER_KEY`. |
| Push Flotas | `Flotas/Services/Notifications/Drivers/PushChannel.php` — stub que siempre falla (item #72). |
| Tokens | 4 tablas: `push_tokens` (nadie la lee), `fleet_driver_push_tokens`, `talento_device_tokens`, `parental_devices.fcm_token`. |
| SMS | No existe (solo marcador en Flotas). |
| Telegram / Slack / Pusher | Solo configuración, sin uso. |
| Abstracción reutilizable | `Flotas/Services/Notifications/NotificationChannelInterface.php` + `FleetNotificationDispatcher` (los dispatchers de documentos y suscripciones la ignoran). |

### 3.3 Meta (Facebook/Instagram)

- Publicación: `MK/Services/Publishing/ChannelManager.php` + drivers `FacebookPageDriver`, `InstagramReels/Feed/StoriesDriver` (Graph **v18.0** fijo en 8 lugares). Tokens de página en `marketing_publication_channels.platform_config` (texto plano).
- OAuth: `MK/Controllers/MetaOAuthController.php` — Hub `meta` (`app_id`, `app_secret`) y escribe de vuelta tokens de usuario y páginas en el `config` del Hub. Empresa fija en 1.
- Renovación: `TokenRefresher` (solo canales Facebook; Instagram nunca se renueva).
- Leads de anuncios: `MK/Controllers/MetaAdsWebhookController.php` (HMAC) — credenciales en `marketing_settings.meta_*`, **sin pantalla**.
- El secreto de la app vive en 2 lugares y los tokens de página en 3.

### 3.4 Mapas

- Google Maps JS: llave real en `system_map_credentials.api_key` (texto plano), servida por
  `GET /configuracion/credenciales-google-maps/render-config`. La integración `google_maps` del Hub **no la lee nadie**.
  MegaFamilia usa `google.maps.*` directo (atado a Google). `MIX_VUE_APP_GOOGLEMAPS_KEY` y `GOOGLE_MAPS_API_KEY`: sin uso en runtime.
- Tiles OSM (`tile.openstreetmap.org`) fijos en ~22 componentes + vistas del portal; geocodificación Nominatim en ~8.
  Políticas de uso de OSM/Nominatim limitan uso comercial intenso. Sin fábrica de mapa compartida.

### 3.5 Medios

- Pexels: `MK/Services/Video/BrollLibraryService.php` (solo Hub `pexels`) y un segundo cliente muerto
  `AssetLibraryService` (lee `marketing_settings.pexels_api_key`). El Brand Kit escribe y muestra la llave de
  `marketing_settings`, no la del Hub → "split brain".
- Replicate (IA de imágenes): ver resumen; pendiente de pasar a Módulos IA.
- reCAPTCHA en formularios de leads: guardado pero **sin verificación en servidor** (no protege).
- `ClientClabeCard.vue:131` manda la CLABE del cliente a `api.qrserver.com` para hacer el QR (se puede generar local con `chillerlan/php-qrcode`).

---

## 4. Dinero, red, telefonía e infraestructura

### 4.1 Pagos

| Pieza | Detalle |
|---|---|
| OpenPay tarjeta (SDK) | `AD/PortalCliente/Services/OpenpayService.php` — portal, domiciliación; credenciales `.env` (`OPENPAY_*`); webhook `POST /portal/openpay/webhook` (Basic con la llave privada). |
| OpenPay SPEI/CLABE (Guzzle) | `PY/Services/OpenPayService.php` — credenciales en `payment_providers` (cifrado); webhook `POST /payments/spei/webhook`. |
| Registro de proveedores | `payment_providers` ya es un registro con config cifrada (`openpay\|stripe\|paypal\|conekta\|spei_manual`), pero todos los consumidores fijan `openpay`. Stripe/PayPal/Conekta/MercadoPago: **no implementados** (solo nombres). |
| CEP Banxico | `AD/PortalPago/Services/Cep/CepValidatorDriver.php` — única interfaz de driver real en dinero; sin credenciales. |
| Riesgos | Pago con tarjeta **oculto en producción** (`PortalCliente/views/factura_show.blade.php:63` y `pagos.blade.php:68` lo muestran solo con `sandbox=true`); 6 caminos distintos que registran pagos (uno inserta directo y salta el observer); IP antifraude toma `X-Forwarded-For` crudo; tarjetas ya guardadas y CLABEs emitidas no se pueden mover de proveedor. |
| Facturación CFDI | `app/Services/Finance/Timbrado/TimbradoServiceInterface.php` + `NullTimbradoService` (sin PAC). **Mejor candidato**: un driver por PAC (Facturama, SW, Finkok…) vía Hub. Los CSD van en datos fiscales de la empresa. |

### 4.2 Red, telefonía, GPS, infraestructura — NO van al Hub (configuración por equipo)

| Integración | Dónde viven las credenciales | Nota |
|---|---|---|
| MikroTik (RouterOS) | `mikrotiks`, `routers` (**texto plano**) | ~50 consumidores; el selector correcto sería un driver por router (`type_of_nas`). Credenciales de respaldo de dev **fijas en código** (`app/Http/Traits/RouterConnection.php:211-217`). |
| SmartOLT | `olt_smartolt_config` (cifrado) → `.env` | 14 de 17 consumidores saltan la capa de drivers `OltDriverManager`. El token podría guardarse en el Hub. |
| OLT Huawei (telnet) | `.env` (un solo juego global) | no permite dos OLT Huawei con credenciales distintas. |
| FreeRADIUS | `.env` (`DB_RADIUS_*`) | `app/Console/Commands/Active/RadiusListener.php`: servidor UDP casero que acepta toda autenticación con secreto fijo — **no está corriendo**, borrar. |
| Asterisk AMI/ARI, BD realtime | `.env` | 3 clientes AMI + 4 copias ARI. |
| Troncales SIP | `voip_configuracion` + `voip_troncales` | ya eligen troncal **por propósito** (`proposito`) y por campaña: es su propio "proveedor por módulo", se queda en VoIP. |
| GPS (Ruptela) | registro de IMEI | driver por protocolo del equipo. |
| GitHub, deploy remoto, respaldos | `.env` | infraestructura. Deploy webhook compara el secreto con `!==` y sin límite de intentos. Respaldos solo en disco local. |
| Inversiones (Alpaca) | `.env` | configuración sin código. |

---

## 5. Hallazgos de seguridad (fuera del plan de servicios, para atender aparte)

1. **Token de COPOMEX escrito en el código** desde el primer commit (`app/Console/Commands/Disabled/GetColonyStateMunicipalityCommand.php`) → rotarlo en COPOMEX y quitarlo.
2. **El `config` de las integraciones del Hub no está cifrado y se manda completo a la pantalla**: el `app_secret` de Meta y los tokens de páginas los ve cualquiera con permiso de ver integraciones.
3. MikroTik: credenciales de respaldo fijas en código; contraseñas API y secreto RADIUS en texto plano; rutas `libera-ip/{id}`, `data-client-mikrotik/{id}`, `admin/consumo` sin permiso específico (solo sesión).
4. Contraseña SMTP en texto plano (`email_settings`); llave de Google Maps en texto plano (por diseño va al navegador: restringir por dominio).
5. Deploy webhook: comparación no segura del secreto y sin límite de intentos.
6. CLABE del cliente enviada a un servicio externo de QR.
7. `RadiusListener` (código muerto peligroso).

---

## 6. Plan propuesto — servicios por módulo

**Patrón**: el mismo de la IA. Catálogo de módulos de servicio, tabla de asignaciones (módulo → integración
del Hub + opciones), pestaña "Módulos de servicios" con botón Probar, y una puerta única por tipo de servicio
con conectores ("drivers") por proveedor. Cada fase en su rama, probada, y merge con aprobación.

| Fase | Contenido | Cambia comportamiento |
|---|---|---|
| **S0 — Base y seguridad del Hub** | Cifrar los secretos del `config` y dejar de mandarlos a la pantalla; catálogo y asignaciones de servicios; pestaña "Módulos de servicios" con Probar; registro de uso. | No |
| **S1 — WhatsApp: una sola puerta de salida** | Interfaz de conector; conector Evolution único (fusiona los dos clientes); los 27 envíos pasan por `WhatsApp::enviar(clave…)`; cada módulo elige **línea**; las líneas tienen proveedor; un solo interruptor de envío/modo prueba; una sola normalización de teléfono; corrige "línea principal" (cada aviso proactivo elige su línea). | No (salvo arreglar los avisos que hoy fallan) |
| **S2 — WhatsApp: una sola puerta de entrada** | Parser neutro por conector → eventos actuales; fusionar la entrada de Marketing (número real de ventas) al gateway, primero en modo sombra; Pagos lee campos neutros, no el `remoteJid`. | Sí, con modo sombra previo |
| **S3 — Conector API oficial de Meta** | Credenciales (token, `phone_number_id`, WABA, secreto de app, token de verificación) en el Hub; webhook firmado; media; ventana de 24 h (`last_inbound_at`); catálogo de plantillas y su mapeo a los ~28 avisos proactivos; en líneas oficiales: sin QR, sin estados, sin grupos. Prueba con número de prueba de Meta. | Solo en líneas que se pasen a oficial |
| **S4 — Correo** | Remitente por módulo vía Hub; conectores SMTP, Mailgun, Postmark (SES opcional); unificar `.env` y `email_settings`; arreglar asunto "Notificación" y pantalla de SMTP. | No |
| **S5 — Push** | Conector FCM HTTP v1 (cuenta de servicio en el Hub); unificar tokens; revive los avisos al celular. | Sí (hoy no funcionan) |
| **S6 — Mapas y medios** | Configuración central de mapa (tiles, geocodificador, llave) para el frontend; unificar Pexels; Replicate → Módulos IA ("generar imágenes"). | No |
| **S7 — Meta publicación** | Credenciales de Meta consolidadas y cifradas; versión de Graph configurable; renovación de tokens de Instagram. | No |
| **Carril aparte (dinero, con decisión de Irving)** | Unificar OpenPay (dos clientes, dos llaves, dos webhooks) y un solo aplicador de pagos; PAC de CFDI cuando se elija proveedor. | Frontera dura |

Decisiones que necesita el plan:

1. **Sin asignación**: en IA se dejó "apagado hasta configurar". En servicios hay envíos vivos en producción a
   clientes (facturación, pagos). Recomendación: la migración **pre-asigna lo que funciona hoy** para que el
   deploy no apague nada.
2. **Granularidad WhatsApp**: recomendación — el módulo elige **línea**, la línea define el proveedor.
3. **Fuera de 24 h sin plantilla** (API oficial): recomendación — no enviar y avisar a la campana.
4. Plantillas de Meta: alguien con acceso al administrador de WhatsApp Business debe crearlas y aprobarlas.
