# Módulo WhatsApp Agent

> Mensajería WhatsApp conversacional con Evolution API + asistente IA. `app/Modules/Addons/WhatsAppAgent/` · slug `addon-whatsapp-agent` · addon activo. Es el **gateway único** de WhatsApp del sistema (ver `CLAUDE.md` — servicios compartidos únicos).

## 0. En simple
Es el WhatsApp de negocio de la empresa dentro del sistema: recibe los mensajes de los clientes, un robot con inteligencia artificial puede contestar solo, y el equipo de ventas/soporte puede ver y responder los chats desde un panel web.

## 1. Qué es
Módulo que centraliza la mensajería de WhatsApp de todos los números (líneas) del ISP: recibe mensajes vía webhook de Evolution API, los guarda en el sistema, y permite responder desde un panel web o de forma automática con IA.

## 2. Para qué sirve
A vendedores y soporte les da un panel único para ver y responder conversaciones de WhatsApp de cualquier línea, con contexto del cliente (si el número ya es cliente, se vincula automáticamente). A la operación le permite automatizar respuestas frecuentes (agendar instalación, resolver dudas) con un asistente IA que puede escalar a un humano, y le da a **otros módulos** (Conciliación de pagos, notificaciones de Flotas, etc.) un solo canal de envío/recepción de WhatsApp sin que cada uno tenga que integrarse con Evolution por su cuenta.

## 3. Cómo funciona
- **Instancias (líneas):** `WhatsAppInstance` (`whatsapp_instances`) representa un número de WhatsApp vinculado vía QR a Evolution API (URL, api_key, `webhook_secret`, estado de conexión). `WhatsAppInstanceController` crea/conecta/desconecta instancias y expone el QR. Cada instancia puede tener una o más **funciones** asignadas (Ventas, Cobranza, Soporte…) vía el catálogo `WhatsAppFunction` (`whatsapp_functions`) y el pivote `WhatsAppInstanceFunction` (`whatsapp_instance_functions`); `WhatsAppFunctionService` es el punto único que asigna/mueve/reasigna funciones (una función `exclusive` solo puede vivir en una línea a la vez).
- **Entrada (webhook):** Evolution llama a `POST /whatsapp/webhook/{slug}` (ruta pública, autenticada por header `X-Webhook-Secret`/`apikey` contra `whatsapp_instances.webhook_secret`, comparación `hash_equals`). `WhatsAppWebhookController` valida y delega en `WhatsAppRouterService::route()`, que distingue eventos de mensaje/conexión/QR. Para mensajes entrantes: dedup por `evolution_message_id`, resuelve el número de contacto, intenta matchear con un cliente existente (`WhatsAppContactMatcherService`, compara últimos 10 dígitos contra `client_main_information.phone/phone2/phone3`), crea/actualiza la `WhatsAppConversation` (`whatsapp_conversations`) y persiste el `WhatsAppMessage` (`whatsapp_messages`, con soporte de texto/imagen/documento/audio/video/ubicación/sticker).
- **Publicación de eventos (gateway → consumidores):** tras persistir un mensaje entrante, el router dispara `WhatsAppMessageReceived` (genérico) más uno especializado por tipo: `WhatsAppTextReceived` (texto) o `WhatsAppMediaReceived` (imagen/documento). Los eventos viajan con las funciones asignadas a la línea (`lineFunctions`) para que cada listener se autogatee. El módulo mismo se suscribe a `WhatsAppTextReceived` vía `IaAutoReplyListener` (cola `default`) para la auto-respuesta de ventas; otros módulos (p. ej. Conciliación de pagos en `Payments`) se suscriben igual, sin tocar el router.
- **Asistente IA:** `WhatsAppIAService` arma el prompt (mensaje + historial + contexto del cliente + datos ya recopilados) y llama al proveedor Claude activo en `ia_proveedores` (driver `claude`). `WhatsAppAutoReplyService` (modo automático, gateado por `config('whatsapp.auto_reply')` + función de línea `ventas`) decide si envía el borrador solo (confianza ≥ umbral) o lo deja guardado en `whatsapp_messages.context` para que un agente humano lo revise/envíe desde el panel (`WhatsAppPanelController::iaAssist`, endpoint manual equivalente).
- **Puente a CRM:** `WhatsAppCrmService` acumula datos extraídos por la IA en `whatsapp_conversations.collected_data`, crea un lead (`Crm`+`CrmMainInformation`+`CrmLeadInformation`) cuando hay intención de instalación con datos mínimos, resuelve dirección contra los catálogos geo (`states`/`municipalities`/`colonies`), y agenda instalación creando un `Ticket` vinculado al lead.
- **Salida (envío):** `EvolutionApiService::sendAndLog()` es el punto que llama a la API real de Evolution y registra el `WhatsAppMessage` saliente (dirección `out`); el envío efectivo corre por cola vía `Jobs/SendWhatsAppMessageJob`. `WhatsAppGateway` es la **fachada única** de transporte que deben usar los módulos consumidores (envío de texto + descarga/almacenamiento de media entrante en disco privado `private/whatsapp/media`, con validación de tamaño/mime) — ningún otro módulo debe montar su propia integración con Evolution.
- **Panel staff:** `WhatsAppPanelController` sirve la vista de conversaciones (`/whatsapp`) y su API (listar, mensajes, enviar, marcar leído, cerrar, sugerencia IA, agendar instalación, listado de técnicos); los vendedores se filtran por `seller_id` dentro del controller, no a nivel de ruta.
- **Rutas:** todas bajo middleware `web` + `auth` + `check_route_permission`, prefijo `/whatsapp` y `whatsapp.*` como nombre de ruta, salvo el webhook (`web` únicamente, público).

## 4. Qué EXPONE / qué CONSUME
**Expone**
- **Vistas:** `/whatsapp` (panel de conversaciones), `/whatsapp/instances` (gestión de líneas), `/whatsapp/funciones` (catálogo de funciones por línea).
- **API JSON** bajo `/whatsapp/api/*`: conversaciones (listar/mensajes/enviar/marcar leído/cerrar/`ia-assist`/agendar instalación), técnicos disponibles, instancias (CRUD, QR, estado de conexión, desconectar), funciones por línea (asignar/desasignar/reasignar) y su catálogo (CRUD + toggle exclusiva), y `POST /whatsapp/api/send` — envío programático genérico para otros módulos.
- **Webhook público** `POST /whatsapp/webhook/{slug}` — el único punto de entrada de Evolution API hacia el sistema.
- **Eventos de dominio** (el contrato real para integrarse sin tocar este módulo): `WhatsAppMessageReceived`, `WhatsAppTextReceived`, `WhatsAppMediaReceived`.
- **`WhatsAppGateway`** (servicio) — fachada de envío de texto + descarga de media, pensada para ser inyectada desde cualquier otro módulo.
- **6 permisos** `whatsapp_view_conversations` / `whatsapp_send_messages` / `whatsapp_manage_instances` / `whatsapp_manage_functions` / `whatsapp_view_logs` / `whatsapp_use_ia`.
- **Pestaña de cliente** `WhatsAppClientTab` (declarada en `module.json` vía `client_tab`), visible en la ficha de cliente.

**Consume**
- **Evolution API** (externo, self-hosted en Docker) — único cliente HTTP de WhatsApp del sistema (`EvolutionApiService`); URL/API key configurables en `.env` (`WHATSAPP_API_URL`, `WHATSAPP_API_KEY`) o por instancia.
- **Módulo IA** — proveedor Claude activo (`ia_proveedores`, driver `claude`) para el asistente conversacional (`WhatsAppIAService`).
- **Módulo Clientes** (`ClientMainInformation`/`Client`) — matching de contacto por teléfono.
- **Módulo CRM** (`Crm`/`CrmMainInformation`/`CrmLeadInformation`) — creación de leads desde conversaciones.
- **Tickets** — creación de solicitud de instalación al agendar desde el chat.
- **Catálogos geográficos** (`states`/`municipalities`/`colonies`) — resolución de dirección para el lead.
- **Módulos consumidores del gateway (conocidos):** el addon `Payments` (conciliación de pagos por WhatsApp) se suscribe a `WhatsAppTextReceived`/`WhatsAppMediaReceived` para leer comprobantes reportados por chat, sin integración propia a Evolution.

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
