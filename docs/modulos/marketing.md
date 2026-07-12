# Módulo Marketing

> Campañas de marketing con IA: leads, generación de contenido, video MVM, publicación multicanal y agente WhatsApp. `app/Modules/Addons/Marketing/` (+ `app/Models/Marketing/`, `app/Http/Resources/Marketing/`, `app/Observers/Marketing/`) · slug `addon-marketing` (id 129) · addon activo.

## 0. En simple
Es el departamento de mercadotecnia automatizado del sistema: captura clientes potenciales (leads) desde anuncios o formularios, un robot con IA les contesta por WhatsApp, genera videos y textos publicitarios, y publica todo en Facebook/Instagram/WhatsApp/correo.

## 1. Qué es
Módulo de marketing digital con IA integrada: captura y calificación de leads (Meta Ads + formularios propios), un agente conversacional de WhatsApp que atiende prospectos, un motor de generación de video (MVM) con Director Creativo IA, y un publicador multicanal (Facebook, Instagram, WhatsApp Status, Email) con enrutamiento inteligente y métricas.

## 2. Para qué sirve
Le sirve al equipo comercial/marketing de Meganet para no perder leads que llegan por anuncios de Meta o por formularios embebidos en la web: el sistema los captura, los califica con IA (score), y un agente de WhatsApp puede conversar con el prospecto (consultar cobertura, planes, agendar cita) antes de escalar a un humano. Además genera contenido (copy, imágenes, video corto por nicho) y lo publica directamente en los canales del negocio, midiendo resultados (métricas de la publicación) sin que un humano tenga que hacerlo a mano canal por canal.

## 3. Cómo funciona
El módulo tiene una **arquitectura híbrida por fases** (conviven sin unificar):
- **Fases 1-2** (captura/leads): modelos en `app/Models/Marketing/` (`Lead`, `LeadSource`, `LeadForm`, `LeadActivity`, `Pipeline`, `PipelineStage`, `Campaign`, `ContentTemplate`, `GeneratedContent`, `Attribution`…), 18 tablas `marketing_*`.
- **Fases 3-4.5b+** (agente IA, video, integraciones, publicación): `app/Modules/Addons/Marketing/` con `Controllers/`, `Services/`, `Jobs/`, `views/`.

**Flujo de captura de leads:** un anuncio de Meta Ads dispara el webhook público `/webhooks/marketing/meta-ads` (verificación HMAC) o un formulario embebible (`PublicLeadFormController`, `embed.js`) → se crea un `Lead` → `ProcessMetaLeadJob`/`LeadObserver` disparan `LeadScoringService` (usa Claude vía `ClaudeApiClient`) para calificar al prospecto.

**Flujo de conversación WhatsApp (agente IA):** mensajes entrantes llegan al webhook `/webhooks/marketing/evolution` (`EvolutionWebhookController`, instancia Evolution API v2) → `ProcessIncomingMessageJob` resuelve la conversación (`ConversationResolverService`) y, si el modo IA está activo, `AiAgentService` genera la respuesta usando **tools** propias (`AgentTools/`: `CheckCoverageTool`, `QueryPlansTool`, `ScheduleAppointmentTool`, `UpdateLeadTool`, `AssignToHumanTool` — este último escala a un humano) → `SendOutboundMessageJob` envía la respuesta por `EvolutionApiService`. Imágenes/documentos entrantes (p. ej. comprobantes de pago) se descargan aparte con `DownloadWhatsAppMediaJob` para consumo de otros módulos (ver Cobranza/Pagos).

**Flujo de generación de contenido y video (MVM):** `ContentGeneratorController`/`AIContentService`/`ImageGeneratorService` generan copy e imágenes vía IA; el motor de video (`Services/Video/`: `FFmpegService`, `VideoTemplateRenderer`, `KineticTextRenderer`, `AssetLibraryService`, `BrollLibraryService`) arma videos cortos a partir de `VideoTemplate` + `Brand Kit` (logo/colores/tipografías propios del negocio) + banco de b-roll (Pexels) + voz (`Services/Tts/`: `OpenAiTtsDriver`/`PiperTtsDriver`), renderizados async por `RenderVideoJob` en la cola `video-render`. El **Director Creativo IA** (`MultivariantCampaignController`/`GenerateMultivariantCampaignJob`) genera variantes de una misma campaña por **nicho** (`MarketingNiche`) con voz y estilo propios de cada nicho.

**Flujo de publicación multicanal:** `PublishingController` + `Services/Publishing/` (`ChannelManager`, `SmartRouter`, `PostPublisherService`, `TokenRefresher`) enrutan una campaña al canal adecuado según su contenido y publican vía drivers dedicados (`Services/Publishing/Drivers/`: `FacebookPageDriver`, `InstagramFeedDriver`, `InstagramReelsDriver`, `InstagramStoriesDriver`, `WhatsAppStatusDriver`, `EmailBlastDriver`), cada uno implementando `PublishDriverInterface`. `PublishPostJob` ejecuta la publicación async; `FetchMetricsJob`/`FetchAllMetricsJob` recuperan métricas después. `MetaOAuthController` gestiona la conexión de la cuenta de Meta Ads/Facebook/Instagram; `RefreshMetaTokensJob` renueva tokens.

**Rutas:** públicas (webhooks + formulario embebible) sin auth; API JSON bajo `/api/marketing/*` con `auth`; panel admin (vistas Blade) bajo `/marketing/*` con `check_route_permission`.

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Webhooks públicos:** `POST/GET /webhooks/marketing/meta-ads` (verificación + recepción de leads de Meta Ads), `POST /webhooks/marketing/evolution` (mensajería WhatsApp entrante).
- **Formulario embebible público:** `GET public/marketing/lead-form/{slug}`, `POST .../submit`, `GET public/marketing/embed.js` (script para incrustar el formulario en sitios externos).
- **API JSON** (`/api/marketing/*`, auth): leads CRUD + scoring + actividades, lead-forms CRUD, conversaciones (listar/enviar mensaje/asignar/cerrar/toggle-IA), brand-kit, video-templates, generated-content (render/descarga/progreso), multivariant-campaigns, niches, voice-comparator.
- **API de publicación** (`/api/marketing/publishing/*`, auth + permisos): canales, smart routing, publicar/reintentar/cancelar publicación, métricas, dashboard.
- **Panel admin** (`/marketing/*`, Blade + Vue): leads, lead-forms, conversaciones, video-templates/generator/queue, brand-kit, campaign-generator, voice-comparator, publishing (dashboard/setup/queue/campaign), Meta OAuth.
- **14 permisos** declarados en `module.json` (`connect-meta-account`, `create/delete-marketing-campaigns`, `generate/delete/download-video-content`, `manage-brand-assets`, `manage-integrations`, `manage-marketing-niches`, `manage-publication-queue`, `manage-publishing-channels`, `manage-video-settings`, …).
- Entrada de sidebar "Marketing" (top-level, `module-sidebar/marketing.blade.php`, bloque agregado a mano — el sidebar no lee `module.json` dinámicamente).
- **Canal WhatsApp propio** (`EvolutionApiService`, instancia `meganet-ventas`) — lo reusa el módulo **Flotas** para sus notificaciones de geocerca (ver `docs/modulos/flotas.md`), y **Cobranza/Pagos (Conciliación WhatsApp)** para recibir comprobantes de pago por WhatsApp (`DownloadWhatsAppMediaJob`/`ProcessIncomingMessageJob`).

**Consume**
- **Integration Hub** (`app/Services/Core/UsesApiIntegration`) — resuelve credenciales de `anthropic` (Claude, para IA/scoring/contenido), `evolution` (WhatsApp), `openai` (TTS/imágenes) y `pexels` (b-roll de video), con fallback Hub → `.env` → `marketing_settings`; **no** monta clientes HTTP ni keys propias fuera de ese mecanismo.
- **Cola de jobs** (`QUEUE_CONNECTION=database`) — colas `default` (mensajería/leads/publicación) y `video-render` (FFmpeg, timeout largo); requiere sus workers de Supervisor activos.
- **FFmpeg** (binario del sistema) para renderizado de video.
- **Meta Graph API** (`MetaGraphApiService`, `MetaOAuthController`) — API externa de Facebook/Instagram para OAuth, publicación y métricas.
- **Módulo Clientes/Planes** — las tools del agente IA (`CheckCoverageTool`, `QueryPlansTool`) consultan cobertura y catálogo de planes del sistema; `ScheduleAppointmentTool` agenda citas contra el módulo de Scheduling.

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
