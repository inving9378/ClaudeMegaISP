# Fase 2 — Constructor de campañas 360 con IA (Reels y Videos listos para producir) · PAQUETES

Supervisor: `ingeniero-supervisor` · Dueño: **Henry** · Fecha: 2026-11-13
Worktree: `/var/www/megaisp-henry-marketing` · Rama: `feat/marketing-360-equipo`.
Nadie commitea. Nadie migra ni escribe en la base `megaisp`. Nadie trabaja en `/var/www/megaisp`.

> **Estado: PLAN — espera las decisiones de Henry (§9).** Precondiciones para arrancar:
> 1. La Fase 1 y la restricción automática del equipo (§2.6.2 de `fase-1-paquetes.md`, en construcción al escribir esto:
>    `Support/EquipoMarketing.php` y la migración `2026_11_13_100200` aún sin commit) **commiteadas con el SÍ de Henry**.
>    La base de la Fase 2 es ese commit.
> 2. La sesión principal corre la suite completa sobre esa base y guarda la línea base en
>    `docs/marketing/fase-2-linea-base-fallas.txt` (mismo formato que la de Fase 1).
> 3. F2-1 y F2-2 decididas. F2-3 y F2-4 hacen falta para **cerrar** la fase (no para construir).

> Reglas de Henry que mandan sobre todo lo de abajo (siguen del 13-nov): **solo Meganet** (empresa 1, sin selector);
> **solo Henry e Irving** entran (regla B de la Fase 1: fila `admin` activa en la empresa 1, negado por defecto); los
> mensajes de WhatsApp **solo se generan como texto y nunca se envían**; **Sofía no tiene nada que ver con Marketing**.


> **DECISIONES DE HENRY (2026-11-13) — mandan sobre el resto:** F2-1 SÍ (2 plantillas nuevas en Video MVM, sin cambiar lo
> que existe). F2-2 SÍ. F2-3 SÍ: se le pide a Irving (pasos en `docs/marketing/pasos-irving-produccion.md`). F2-4: lo
> hace Henry (super-administrador en prod) en Integraciones → Módulos IA. F2-5 a F2-15 con su valor por defecto.
> **AGREGADO por Henry — menú lateral de Marketing (§2.11):** se reorganiza SOLO el submenú de Marketing
> (`resources/views/module-sidebar/marketing.blade.php`, autorizado por Henry solo para ese bloque) y el `menu[]` de
> `module.json` / barra del módulo, con la propuesta que Henry apruebe. **No se programa hasta que Henry apruebe la
> propuesta final.**
---

## 0. Hechos encontrados en el código (condicionan el plan)

### 0.1 El motor de Video MVM: qué recibe hoy

1. **Entrada única: `RenderVideoJob`** (`app/Modules/Addons/Marketing/Jobs/RenderVideoJob.php:27-33`):
   `(generatedContentId, templateId, variables[], options[], companyId = 1)`. `tries 2`, `timeout 600` (`:19-20`).
   El formato sale de `options['aspect_ratio']` (default `9:16`, `:59`). Lo despachan 3 lugares, siempre con
   `->onQueue('video-render')`: `MarketingGeneratedContentController.php:153-154`, `GenerateMultivariantCampaignJob.php:105-111`
   y `MarketingMultivariantCampaignController.php:144-145`.
2. **Las escenas salen SOLO de la plantilla**, no de la petición: `VideoTemplateRenderer::render()` lee
   `$template->schema['scenes']` (`VideoTemplateRenderer.php:133-147`). Lo único que entra desde fuera son **variables**
   que se sustituyen con `{{clave}}` (`resolveVariables`, `:919-925`) y, para el generador por nicho, un
   `_creative_brief` (hook, cta, `kinetic_text_segments`) (`:71-75`, `:833-882`). **No hay forma hoy de mandar un guion
   por escenas**: hay que copiar textos a las variables fijas de una plantilla (titular, subtítulo, cuerpo, CTA, narración).
3. **Tipos de escena que sabe dibujar** (`:274-287`): `logo_reveal`, `feature_highlight(_modern)`, `call_to_action(_modern)`,
   `text_quote`, `coverage_map`, `broll_overlay`, `kinetic_text`, `split_screen`, `info_banner` (por defecto). Cada escena
   lleva su `duration` (`:138`).
4. **Duración total = `schema.duration_seconds` fija** (`:111-112`); si la voz dura más, se corta con desvanecido
   (`:114-129`). Las escenas se unen con transición `xfade` (`_transition_type`/`_transition_duration`, default `fade`/0.5 s,
   `:150-166`; `FFmpegService.php:208-255`), que **acorta** el total (n−1)×transición.
5. **Voz en off: UNA sola pista** para todo el video, de `schema.voiceover_script` (con `voiceover_fallback`) (`:84-108`).
   TTS por `TtsService` → `OpenAiTtsDriver` con `IA::credencialesPara('marketing.voz_videos')`
   (`OpenAiTtsDriver.php:26`; POST `/audio/speech` `:37-45`). **Si falla, el video sale sin voz y no se avisa**
   (`VideoTemplateRenderer.php:104-107` solo lo anota en el log interno). La voz se elige **solo por nicho**
   (`preferred_voice_id`, `:95-97`, `:792-804`); no se puede pedir una voz por video. Voces: `alloy, echo, fable, onyx,
   nova, shimmer` (`VoiceComparatorController.php:22-28`); modelos `tts-1`, `tts-1-hd` (`:31-32`).
6. **Música de fondo nunca suena** (defecto existente): el render busca `getRandom('audio', categoría)`
   (`VideoTemplateRenderer.php:190-196`) pero la biblioteca guarda `type = 'music'` (6 filas: `corporate_clean`,
   `corporate_confident`, `epic_electronic`, `modern_creative`, `uplifting_family`, `warm_acoustic`).
7. **B-roll** por categoría = slug de nicho (`findBrollClip`, `:820-831`): 111 clips en BD (familia, gamer, home_office,
   streamer, pequeno_negocio, adulto_mayor). **Los archivos no están en dev**: `storage/app/marketing/` del checkout
   principal no tiene `assets/` (BD = copia de prod). Sin archivo, la escena cae a fondo de color (`:589-593`). En el
   worktree tampoco está el logo (`marketing/brand/1/logo.png` solo existe en `/var/www/megaisp/storage`).
8. **Texto en pantalla sin salto de línea**: `FFmpegService::drawText` (`FFmpegService.php:166-200`) no parte líneas;
   `feature_highlight` usa fuente al 10 % del ancho (`VideoTemplateRenderer.php:349`) → en 9:16 caben ~16 caracteres;
   `call_to_action` al 6.5 % (`:396`) → ~26. Solo `info_banner.body` y `text_quote` envuelven (`wrapText`, `:1001-1018`).
   `fontcolor` entra sin escapar al filtro (`FFmpegService.php:183-188`) → **ningún color puede venir del usuario ni de la IA**.
   Los argumentos van con `escapeshellarg` (`:24-28`); el texto escapa `\ : ' %` (`:181`).
9. **Resultado y estado**: `RenderVideoJob` pone `status` `generating` → `ready`/`failed`, `render_stage`, `output_path`,
   `thumbnail_path` (`RenderVideoJob.php:52-79`). Defectos: guarda `tts_cost_usd` (`:75`) pero el render devuelve
   `cost_usd` (`VideoTemplateRenderer.php:251`) → siempre 0; en éxito **no guarda el log** (no se sabe si salió sin voz);
   al fallar **no llena `failure_reason`** (`RenderVideoJob.php:113-123`). `render_progress` solo pasa de 0 a 100.
10. **Cola sin procesador en dev**: Supervisor (`/etc/supervisor/conf.d/megaisp-queue.conf`) solo atiende
    `cobranza,referrals,database,default` (2 procesos, `--timeout=90`). **Nadie atiende `video-render`**; en la BD hay 3
    videos `pending` y 1 `generating` de junio que nunca terminaron. `config/queue.php:48` `retry_after = 300` < `timeout 600`
    del job → con dos procesos en la misma cola un render largo podría correr dos veces.
11. **Plantillas en dev** (22, todas de la empresa 1): 7 familias × 3 formatos (genérica, cobertura, testimonio, recordatorio
    de pago, bienvenida, promo relámpago, mantenimiento), cada una de **un solo formato**, 4–5 escenas fijas y variables
    fijas; más `multivariant-plan-promo-gamer` (3 formatos, usa brief). **Las bases de prueba (`megaisp_test`,
    `megaisp_fresh_test`) tienen 0 plantillas, 0 nichos, 0 assets y sin Brand Kit** (vienen de seeders, no de migraciones).
12. **Permisos del motor** (`generate-video-content`, `view-video-content`, `view-video-templates`, …) existen en `megaisp`
    pero **no en `megaisp_test`**: se crean en `database/seeders/Marketing/Phase4PermissionsSeeder.php`, no en una migración.
    Por eso la Fase 1 no pudo probar el doble clic al renderizar.
13. **Multivariante** (`GenerateMultivariantCampaignJob.php:49-124`): pide briefs por nicho a la IA
    (`CreativeDirectorService.php:53`, clave `marketing.director_creativo`, `json => true`) y renderiza una variante por nicho
    × formato con la plantilla `multivariant-plan-promo-<nicho>` (cae a la de gamer, `:79-80`). Es de promoción de plan; no
    sirve para un guion libre, pero su forma de pedir JSON a la IA y limpiarlo (`CreativeDirectorService.php:130-158`) es el
    modelo a seguir.
14. **Ffmpeg 5.1.9** instalado; **ImageMagick (`convert`) no** → el logo se usa sin escalar (`VideoTemplateRenderer.php:991-999`).
    Ajustes leídos con otro nombre que el guardado (`video_quality_crf`/`video_preset` vs `video_crf`/`ffmpeg_preset`, `:217-218`)
    → usa los valores por defecto. No se arregla en esta fase.

### 0.2 Lo demás

15. `marketing_campaigns`: **0 filas**; `type` enum (`meta_ads`,`google_ads`,`organic`,`email_blast`,`sms_blast`,`voice_blast`,
    `mixed`,`manual`), `status` enum (`draft`,`active`,`paused`,`finished`,`canceled`), `budget`/`spent` `decimal(12,2)`,
    `currency` default MXN, `slug` único por `(company_id, slug)`, `config`/`goals` json. Modelo `App\Models\Marketing\Campaign`
    (`:18-31`) con `PerteneceAEmpresa` y relación `generatedContent()` por `source_campaign_id` (`:48-51`).
16. `marketing_generated_content`: 204 filas; `type` enum (`copy`,`image`,`video`,`audio_voiceover`,`ai_response`), `status`
    enum (`pending`,`generating`,`ready`,`failed`,`used`,`completed`), `generation_engine` NOT NULL, **FK `source_campaign_id` →
    `marketing_campaigns` ON DELETE SET NULL** (ya sirve para ligar piezas y videos a la campaña).
17. **IA**: `IA::enviar($clave, $mensaje, [], $system, [], ['json'=>true,'max_tokens'=>…])` e `IA::json()`
    (`IA/Services/IA.php:345-396`). Las claves `marketing.*` están en `IA/config/modulos.php:41-45` y `:78`. **En dev no hay
    ninguna IA asignada a `marketing.*` ni a `grupo:marketing`** (solo `grupo:voip`): hoy `IA::enviar('marketing.…')` lanza
    `IANoConfigurada` y la voz de los videos no funciona. Las pruebas simulan la IA sembrando la asignación dentro de la
    transacción + `Http::fake` (patrón de `tests/Feature/Roadmap/IaSupervisorEconomicoTest.php`).
18. Pantallas nuevas se registran en `resources/js/components/module/marketing/registrar.js` (`PANTALLAS`) → **no se toca
    `app.js`**. Hoy «Crear campaña con IA» / «Nueva campaña» llevan al generador por nicho:
    `dashboard/PanelCampanasActivas.vue:23`, `MarketingDashboard.vue:32` y `:80`, `publishing/PublishCampaignView.vue:38`.
19. Rutas: 134 (Fase 1). `GET /marketing/campaigns` sirve el Panel y `GET /marketing/campaigns/{id}` redirige (retirada F1-3)
    → las pantallas nuevas van en **otra URL** (`/marketing/campanas-360`). Patrón de acceso: grupo `['web','auth']` +
    `$M` (`routes.php:35-37`, `:71`), ids numéricos con `->where($NUM)` (`:42`).
20. Disco del servidor 70 % (43 GB libres); un video de 30 s pesa 0.2–2 MB. Servidor de 4 núcleos con CPU robada por el
    hipervisor → los renders se corren con `nice`.

---

## 1. Resumen de lo que se construye

- **Asistente por pasos** `/marketing/campanas-360/nueva`: objetivo → público → oferta → canales → presupuesto MXN y fechas →
  revisar y **Generar con IA**. Se puede guardar como borrador en cualquier paso.
- **La IA genera** (asíncrono, por la cola `default`, con estado visible por pieza): **estrategia** (`marketing.campana_360`) y
  las **piezas** (`marketing.piezas_campana`): guiones de **Reel 9:16** y **Video 16:9** por escenas, copies de Facebook e
  Instagram con variantes A/B, mensajes de WhatsApp (solo texto), variantes de anuncio. **Todo editable y regenerable por pieza.**
- **Producir en Video MVM**: un botón por guion crea el trabajo en el motor existente (plantilla «Guion por escenas», escenas,
  voz y música ya cargadas, sin copiar y pegar), ligado a la campaña; la ficha muestra **En cola / Produciendo / Listo / Error**,
  reproduce el video terminado y avisa si se queda en cola.
- **El motor MVM aprende a recibir escenas** (F2-1): bandera `escenas_desde_variables` en 2 plantillas nuevas; voz y música por
  video; arreglos de estado del job (log, `failure_reason`, idempotencia). Lo existente no cambia.
- **Ficha de campaña** con estrategia, piezas por canal, videos, reparto del presupuesto en MXN y activar/pausar/terminar.

---

## 2. CONTRATO COMPARTIDO

### 2.1 Reglas de trabajo (las cumplen los tres agentes)

- **Archivos autorizados** (Henry): `app/Modules/Addons/Marketing/**`, `app/Models/Marketing/**`,
  `resources/js/components/module/marketing/**`, **solo** las 2 claves nuevas `marketing.*` en
  `app/Modules/Addons/IA/config/modulos.php`, `tests/Feature/Marketing/Fase2/**` (más los ajustes de pruebas de Fase 0/1 que
  pida el contrato, de QA), `docs/marketing/**`, `docs/bitacora/**`. **Nada** de layout, menú global, CSS global, `config/`,
  otros módulos, ni **ninguna línea nueva en `resources/js/app.js`**. **Ninguna librería nueva** (Quasar `q-stepper`,
  ApexCharts y `<video>` nativo bastan).
- **IA solo por `IA::enviar('marketing.campana_360'|'marketing.piezas_campana', …)`**; voz solo por el `TtsService` existente.
  Sin `Http::` hacia proveedores de IA, sin llaves, sin conexiones nuevas. **Nunca se mandan datos de leads ni clientes a la
  IA** (solo los datos de la campaña y del Brand Kit).
- **WhatsApp: solo texto.** Ningún código nuevo referencia `WhatsAppGateway`, `EvolutionApiService`, `SendOutboundMessageJob`,
  `PublishPostJob` ni Sofía. La UI solo permite **copiar**.
- **Dinero en MXN**: `budget` `decimal(12,2)`; en JSON siempre **cadena con 2 decimales** (`"15000.00"`); cálculos con `bcmath`,
  nunca `float`. Los costos de IA (USD) quedan en el Hub; **no se muestran**.
- **Bases**: `megaisp_test`, `megaisp_fresh_test`, `megaisp_dryrun`. **Prohibido escribir en `megaisp`**; leerla sin volcar
  `marketing_settings` con llaves, `.env`, `api_integrations` ni `ia_asignaciones` con valores.
- **Pruebas por nombre de archivo**, nunca por directorio, sobre `Tests\Feature\Marketing\MarketingTestCase`. IA, voz, Meta,
  Evolution y correo **siempre simulados**. No se siembran permisos que deban venir de una migración.
- **Nada de commits. Nada de `config:cache`.** Migraciones solo con `--path=app/Modules/Addons/Marketing/migrations`.
- **Ninguna forma JSON existente cambia.** Única excepción aditiva: `GET /api/marketing/generated-content` agrega
  `source_campaign_id` y `pieza` a cada fila (§3).
- Textos visibles en español. Ningún login, id real ni dato de cliente en código, pruebas o docs.

### 2.2 Regla contra atorones (instrucción de Henry)

1. Cada agente escribe **su** `docs/marketing/fase-2-avance-<agente>.md` y agrega **una línea cada 15 minutos como máximo**:
   `HH:MM — <lo que hice> · sigue: <lo siguiente>`. Primera línea al arrancar.
2. **Nada esperando en segundo plano.** Todo comando largo va en primer plano, con tope y salida a archivo:
   ```bash
   S=/tmp/claude-1000/-var-www-megaisp/78fb3bd1-90ba-47f4-819f-1aca73a1fd54/scratchpad/fase2/<agente>; mkdir -p $S
   timeout 900 env NODE_OPTIONS=--max-old-space-size=4096 npm run dev > $S/npm-dev.log 2>&1; echo "exit $?" >> $S/npm-dev.log
   timeout 900 php vendor/bin/phpunit tests/Feature/Marketing/Fase2/Qa/XTest.php > $S/XTest.log 2>&1; echo "exit $?" >> $S/XTest.log
   ```
   Migraciones **sin** `timeout`, con `flock -w 600 /tmp/megaisp-suite.lock …`. Siempre se lee `tail -40` y el `exit`.
3. Procesos que sí deben quedar vivos (`artisan serve`, el procesador de cola de la vista previa §2.10) se comprueban
   (`curl`/`ps`) antes de usarlos y se matan al terminar. **Nunca** se espera a que «avise» algo que no se comprobó vivo.
4. **Si un agente pasa 20 minutos sin una línea nueva**, la sesión principal lo detiene y retoma su paquete desde su avance.
5. QA no corre la suite completa (la corre la sesión principal al cierre, §7.16).

### 2.3 Modelo de datos (migraciones aditivas, con guarda `hasColumn`, sin tocar datos existentes)

**`marketing_campaigns` + columnas** (todas nulas; migración `2026_11_13_200000_agrega_campos_360_a_marketing_campaigns.php`):

| Columna | Tipo | Contenido |
|---|---|---|
| `objetivo` | varchar(40) | clave de §2.5.1 |
| `publico` | json | §2.4 «Campana360» |
| `oferta` | json | descripción, plan, `precio_mxn` (cadena), condiciones, vigencia (el plan decía texto: F2-2) |
| `canales` | json | lista de claves de §2.5.2 |
| `estrategia` | json | §2.5.4 |
| `estrategia_estado` | varchar(20) | `sin_generar` · `generando` · `lista` · `error` |
| `estrategia_error` | varchar(500) | mensaje en español |
| `estrategia_generada_at` | timestamp | |
| `creado_por_user_id` | bigint unsigned | `auth()->id()` (el modelo no extiende `BaseModel`) |

Presupuesto = `budget` (MXN, `currency` siempre `'MXN'`), fechas = `start_date`/`end_date`, `type = 'mixed'` (F2-11),
`status` inicial `draft`, `slug` = `Str::slug(nombre)` + sufijo único en la empresa.

**`marketing_generated_content` + columnas** (todas nulas salvo `regeneraciones`; migración
`2026_11_13_200100_agrega_pieza_a_marketing_generated_content.php`; índices `(source_campaign_id, pieza)` y `(pieza_origen_id)`):

| Columna | Tipo | Contenido |
|---|---|---|
| `pieza` | varchar(30) | `reel_guion` · `video_guion` · `copy_facebook` · `copy_instagram` · `mensaje_whatsapp` · `variante_anuncio` · `reel` · `video` |
| `variante` | varchar(2) | `A`, `B`, `C` |
| `contenido` | json | forma por pieza (§2.5.5) |
| `pieza_origen_id` | bigint unsigned | en un video producido: el id de su guion |
| `editado_at` | timestamp | última edición manual |
| `regeneraciones` | smallint unsigned default 0 | |

- Piezas de texto: `type = 'copy'`, `status` `pending`/`generating`/`ready`/`failed`, `source_campaign_id`,
  `generation_engine = 'ia:' . <modelo devuelto por IA::enviar>` (≤ 50), `output_text` = versión en texto plano,
  `generation_cost_usd = 0`, `failure_reason` en español, versiones previas en `generation_metadata.versiones` (máx. 5).
- Videos producidos: `type = 'video'`, `pieza` `reel`|`video`, `pieza_origen_id`, `source_campaign_id`, `template_id` de la
  plantilla de guion, `generation_engine = 'mvm_ffmpeg'`.
- Fase 3 agregará `estado_editorial`, `aprobado_*`, `programado_para`, `publication_id` (no en esta fase).
- Estado de la generación de la campaña **no se guarda**: se calcula de `estrategia_estado` + estados de las filas de piezas
  (sin carreras entre procesos).

**Plantillas nuevas del motor** (catálogo de Meganet, `company_id = 1`; migración
`2026_11_13_200200_crea_plantillas_guion_por_escenas.php`, `updateOrInsert` por `(company_id, slug)`; `down()` las borra
solo si ningún `marketing_generated_content` las usa):

| slug | name | category | aspect_ratios | duration_seconds |
|---|---|---|---|---|
| `guion-escenas-9-16` | Guion por escenas — Reel (9:16) | `guion_escenas` | `["9:16"]` | 30 (nominal) |
| `guion-escenas-16-9` | Guion por escenas — Video (16:9) | `guion_escenas` | `["16:9"]` | 60 (nominal) |

`schema`: `{"engine_version":"1.1","escenas_desde_variables":true,"scenes":[],"voiceover_script":"{{voiceover_text}}",
"audio":{"voiceover":{"enabled":true,"volume":1},"background_music":{"volume":0.18}},"variables":[{"key":"voiceover_text","label":"Narración","required":false}],
"supported_aspect_ratios":[<el suyo>]}`; `engine_version` `1.1`, `requires_voiceover` 1, `active` 1.

**Permisos del motor** `[F2-6]` (migración `2026_11_13_200300_asegura_permisos_motor_video.php`): `Permission::firstOrCreate`
de `view-video-templates`, `view-video-content`, `generate-video-content`, `delete-video-content`, `download-video`; **solo si
la crea**, `givePermissionTo` a `super-administrator` y `DESARROLLADOR`. En prod/dev ya existen → no cambia nada. `down()` vacío.

### 2.4 Rutas NUEVAS (18) — total de Marketing **134 → 152**

API: al **final** del grupo `['web','auth']` `api/marketing` (después de `navegacion`), con `->where($NUM)` del grupo y
`$M`. Parámetros `{id}` (campaña), `{piezaId}`, `{videoId}` numéricos — **agregar `piezaId` y `videoId` a `$NUM`**.
Blade: dentro del grupo `marketing` con `$M`, **antes** de la sección «Legado». Nombres `api.marketing.campanas-360.*` y
`marketing.campanas-360.*`. Las marcadas ⏱ llevan además `throttle:6,1` (F2-15).

| # | Método | URL | Nombre | Acción |
|---|---|---|---|---|
| 1 | GET | `/api/marketing/campanas-360` | `api.marketing.campanas-360.index` | `Campana360Controller@index` |
| 2 | POST | `/api/marketing/campanas-360` | `….store` | `Campana360Controller@store` |
| 3 | GET | `/api/marketing/campanas-360/{id}` | `….show` | `Campana360Controller@show` |
| 4 | PUT | `/api/marketing/campanas-360/{id}` | `….update` | `Campana360Controller@update` |
| 5 | DELETE | `/api/marketing/campanas-360/{id}` | `….destroy` | `Campana360Controller@destroy` |
| 6 | POST | `/api/marketing/campanas-360/{id}/estado` | `….estado.cambiar` | `Campana360Controller@cambiarEstado` |
| 7 | POST ⏱ | `/api/marketing/campanas-360/{id}/generar` | `….generar` | `Campana360GeneracionController@generar` |
| 8 | GET | `/api/marketing/campanas-360/{id}/progreso` | `….progreso` | `Campana360GeneracionController@progreso` |
| 9 | PUT | `/api/marketing/campanas-360/{id}/estrategia` | `….estrategia.update` | `Campana360GeneracionController@actualizarEstrategia` |
| 10 | PUT | `/api/marketing/campanas-360/{id}/piezas/{piezaId}` | `….piezas.update` | `Campana360PiezaController@actualizar` |
| 11 | POST ⏱ | `/api/marketing/campanas-360/{id}/piezas/{piezaId}/regenerar` | `….piezas.regenerar` | `Campana360PiezaController@regenerar` |
| 12 | POST ⏱ | `/api/marketing/campanas-360/{id}/piezas/{piezaId}/producir` | `….piezas.producir` | `Campana360PiezaController@producir` |
| 13 | GET | `/api/marketing/campanas-360/{id}/videos/{videoId}/ver` | `….videos.ver` | `Campana360VideoController@ver` |
| 14 | GET | `/api/marketing/campanas-360/{id}/videos/{videoId}/miniatura` | `….videos.miniatura` | `Campana360VideoController@miniatura` |
| 15 | GET | `/marketing/campanas-360` | `marketing.campanas-360.lista` | vista `addon-marketing::campanas-360.lista` |
| 16 | GET | `/marketing/campanas-360/nueva` | `marketing.campanas-360.nueva` | vista `…asistente` (`campanaId = null`) |
| 17 | GET | `/marketing/campanas-360/{id}` | `marketing.campanas-360.ficha` | vista `…ficha` (`campanaId`) |
| 18 | GET | `/marketing/campanas-360/{id}/editar` | `marketing.campanas-360.editar` | vista `…asistente` (`campanaId`) |

Reglas comunes: la campaña se busca con el scope de empresa (`findOrFail` → **404** `{"ok":false,"error":"no_encontrado","mensaje":"No encontramos esa campaña."}`
en API); `{piezaId}`/`{videoId}` deben pertenecer a `{id}` (`source_campaign_id`) → si no, 404. Errores de validación: 422 formato
Laravel (`message`, `errors.campo`). Errores de negocio: `{"ok":false,"error":"<código>","mensaje":"<español>"}`. Fechas ISO-8601
`America/Mexico_City`; fechas de calendario `YYYY-MM-DD`. Las vistas Blade reciben el id solo como atributo del tag
(`:campana-id="{{ (int) $campanaId }}"`).

#### Formas JSON

**Campana360** (lo devuelven 2, 3, 4, 6; el índice usa el resumen):
```json
{
  "id": 12, "nombre": "Fibra 500 diciembre", "estado": "draft", "estado_etiqueta": "Borrador",
  "objetivo": "nuevos_clientes", "objetivo_etiqueta": "Conseguir clientes nuevos",
  "publico": { "descripcion": "Familias en colonias con fibra nueva", "zonas": ["Santa Inés", "Centro"],
               "edad_min": 25, "edad_max": 55, "nichos": ["familia", "home_office"], "intereses": ["streaming"] },
  "oferta": { "descripcion": "500 Megas con instalación gratis", "plan": "Fibra 500", "precio_mxn": "599.00",
              "condiciones": "Primeros 100 clientes", "vigencia_hasta": "2026-12-31" },
  "canales": ["facebook", "instagram", "reels", "video", "anuncios_meta", "whatsapp"],
  "presupuesto_mxn": "15000.00", "moneda": "MXN", "fecha_inicio": "2026-12-01", "fecha_fin": "2026-12-31",
  "estrategia": null, "estrategia_estado": "sin_generar", "estrategia_error": null, "estrategia_generada_at": null,
  "generacion": { "estado": "sin_generar", "piezas_total": 0, "piezas_listas": 0, "piezas_generando": 0, "piezas_error": 0 },
  "videos_resumen": { "total": 0, "listos": 0, "en_proceso": 0, "error": 0 },
  "creada_at": "2026-11-13T18:00:00-06:00", "actualizada_at": "2026-11-13T18:00:00-06:00"
}
```
`generacion.estado`: `sin_generar` (nada pedido) · `generando` (estrategia o alguna pieza `pending`/`generating`) · `lista` ·
`con_errores` (alguna `failed` y ninguna en proceso). Etiquetas de `estado`: `draft` Borrador · `active` Activa · `paused`
Pausada · `finished` Terminada · `canceled` Cancelada.

**1) GET índice** `?estado=draft&pagina=1` (`estado` opcional en la lista de 5; `pagina` ≥ 1) → 200
`{"ok":true,"data":[<resumen>],"meta":{"pagina":1,"por_pagina":20,"total":3}}`. Resumen = `id, nombre, estado, estado_etiqueta,
objetivo_etiqueta, canales, presupuesto_mxn, fecha_inicio, fecha_fin, generacion, videos_resumen`. Orden `created_at` desc.

**2) POST** — cuerpo = los campos editables de Campana360 (`nombre, objetivo, publico, oferta, canales, presupuesto_mxn,
fecha_inicio, fecha_fin`) → **201** `{"ok":true,"data":<Campana360>}`. Validación (FormRequest, mensajes en español):
- `nombre` 3–150; `objetivo` en §2.5.1; `canales` arreglo 1–6, distintos, en §2.5.2.
- `publico.descripcion` 3–500; `zonas` ≤ 20 × ≤ 80; `edad_min`/`edad_max` enteros 13–99, min ≤ max (opcionales);
  `nichos` ⊆ slugs de `marketing_niches` activos de la empresa 1 (opcional); `intereses` ≤ 10 × ≤ 40.
- `oferta.descripcion` 3–500 (obligatoria); `plan` ≤ 100; `precio_mxn` opcional `^\d{1,8}(\.\d{1,2})?$` (se guarda con 2
  decimales); `condiciones` ≤ 500; `vigencia_hasta` fecha opcional.
- `presupuesto_mxn` obligatorio `^\d{1,10}(\.\d{1,2})?$` (0 permitido: orgánica); `fecha_inicio` ≥ hoy (México) al crear;
  `fecha_fin` ≥ inicio; vigencia ≤ 366 días.
- Todo texto: `strip_tags`, sin `{{`/`}}`, sin caracteres de control. Arreglos o tipos raros → 422.

**3) GET ficha** → 200 `{"ok":true,"data": <Campana360> + "piezas": {…}, "videos": [Video…]}` con
`"piezas": {"reel_guion":[Pieza], "video_guion":[…], "copy_facebook":[…], "copy_instagram":[…], "mensaje_whatsapp":[…], "variante_anuncio":[…]}`
(claves siempre presentes, arreglos ordenados por `variante`).

**Pieza**:
```json
{ "id": 301, "pieza": "reel_guion", "pieza_etiqueta": "Guion de Reel", "variante": "A",
  "estado": "lista", "error": null, "contenido": { "…": "§2.5.5" }, "texto_plano": "…",
  "editada": false, "editada_at": null, "regeneraciones": 0, "actualizada_at": "…", "video_ids": [410] }
```
`estado`: `pendiente` (pending) · `generando` · `lista` (ready) · `error` (failed).

**Video**:
```json
{ "id": 410, "pieza": "reel", "guion_id": 301, "formato": "9:16", "estado": "listo", "estado_etiqueta": "Listo",
  "error": null, "sin_voz": false, "duracion_s": "27.6",
  "url_ver": "/api/marketing/campanas-360/12/videos/410/ver",
  "url_miniatura": "/api/marketing/campanas-360/12/videos/410/miniatura",
  "en_cola_desde": "2026-11-13T18:05:00-06:00", "demorado": false, "creado_at": "…" }
```
`estado` desde `status`: `pending` → `en_cola` «En cola» · `generating` → `produciendo` «Produciendo» · `ready`/`completed`/`used`
→ `listo` «Listo» · `failed` → `error` «Error». `demorado = true` si `en_cola` hace > 10 min o `produciendo` sin cambio hace > 15 min.
`sin_voz = true` si el render terminó sin pista de voz (§2.6). `error` = `failure_reason` traducido a un mensaje corto en español
(nunca la traza). `url_*` solo cuando `estado = listo`.

**4) PUT** (mismos campos que 2, todos `sometimes`) → 200; solo con `estado` `draft` o `paused`, si no **409** `campana_activa`
«Pausa la campaña para cambiar sus datos.». `fecha_inicio` ≥ hoy solo si cambia.

**5) DELETE** → 200 `{"ok":true}`; solo `draft` (si no **409** `solo_borrador`); borrado lógico de la campaña y de sus piezas de
texto; los videos se conservan (archivos incluidos).

**6) POST estado** `{"estado":"active"}` → 200 Campana360. Transiciones válidas: `draft→active` (exige `estrategia_estado = lista`,
si no 422 `sin_estrategia`), `active→paused`, `paused→active`, `active|paused→finished`, `draft|paused→canceled`. Otra →
422 `transicion_invalida` «No se puede pasar de Borrador a Terminada.».

**7) POST generar** `{"alcance":"todo"|"estrategia"|"piezas","reemplazar":false}` → **202** `{"ok":true,"generacion":{…}}`.
- Antes de despachar: `IA::configurada('marketing.campana_360')` (alcance todo/estrategia) y `IA::configurada('marketing.piezas_campana')`
  (todo/piezas); si falta → **422** `ia_no_configurada` «Falta asignar una IA a «<nombre de la función>» en Integraciones →
  Módulos IA. Pídeselo a Irving.».
- `generacion.estado = generando` → **409** `generando` «Ya se está generando esta campaña.».
- `todo`/`piezas` con piezas existentes y `reemplazar = false` → **409** `piezas_existentes` «Ya hay piezas. Confirma que quieres
  reemplazarlas.». Con `reemplazar = true` se borran lógicamente las piezas de texto (los videos quedan).
- `piezas` sin estrategia lista → 422 `sin_estrategia`. Campaña `finished`/`canceled` → 409 `campana_cerrada`.
- `todo`: despacha `GenerarEstrategiaCampanaJob`; al terminar bien, ese job crea las filas `pending` de §2.5.3 y despacha un
  `GenerarPiezasCampanaJob` por **tipo** de pieza. `estrategia`: solo la estrategia. `piezas`: crea filas y despacha.

**8) GET progreso** (ligero, para sondeo) → 200
`{"ok":true,"estrategia_estado":"lista","estrategia_error":null,"generacion":{…},"piezas":[{"id":301,"pieza":"reel_guion","variante":"A","estado":"lista","error":null}],"videos":[Video…]}`.

**9) PUT estrategia** `{"estrategia":{…§2.5.4}}` → 200 Campana360; se valida igual que la salida de la IA; `reparto_presupuesto`
se recalcula (§2.5.4). 409 si `estrategia_estado = generando`.

**10) PUT pieza** `{"contenido":{…}}` → 200 `{"ok":true,"data":<Pieza>}`. Mismo validador que la salida de la IA para su `pieza`
(§2.5.5); el guion se renumera y se recalcula `duracion_total_s`. Guarda la versión previa en `versiones`, pone `editado_at`.
409 si la pieza está `generando`. Pieza `reel`/`video` → 422 `no_editable`.

**11) POST regenerar** `{"indicaciones":"Más corto y con humor"}` (opcional, ≤ 500, saneado) → **202** `{"ok":true,"data":<Pieza estado generando>}`;
pone `status = generating`, `regeneraciones + 1`, despacha `RegenerarPiezaCampanaJob`. Solo cambia **esa** pieza. 409 si ya está
`generando`; 422 `ia_no_configurada` como en 7.

**12) POST producir** `{"voz":"nova","musica":"uplifting_family"}` (ambos opcionales; `voz` en §2.5.6, `musica` en §2.5.6 o
`sin_musica`; si faltan se usan los del guion) → **202** `{"ok":true,"data":<Video estado en_cola>}`.
- Solo `reel_guion`/`video_guion` en estado `lista` → si no 422 `no_es_guion` / `pieza_no_lista`.
- El guion debe pasar el validador (límites §2.5.5) → si no 422 `guion_invalido` con `errores` por escena.
- Ya hay un video de ese guion `en_cola`/`produciendo` → **409** `produccion_en_curso` «Ese guion ya se está produciendo.».
- Plantilla `guion-escenas-*` ausente o inactiva → 422 `plantilla_no_disponible`.
- Qué crea y despacha: §2.6.

**13) GET ver** → 200 `video/mp4` en línea (`response()->file`, admite `Range`), **14) GET miniatura** → 200 `image/jpeg`.
404 si el video no está `listo` o el archivo no existe; la ruta del archivo se resuelve con `realpath` y debe quedar dentro de
`storage_path('app/marketing/videos')`.

### 2.5 Contenido que genera la IA

#### 2.5.1 Objetivos (clave → etiqueta)
`nuevos_clientes` Conseguir clientes nuevos · `reconocimiento` Dar a conocer la marca · `cobertura_nueva` Anunciar cobertura
nueva · `promocion` Impulsar una promoción · `retencion` Retener clientes · `mejora_plan` Subir de plan a clientes.

#### 2.5.2 Canales
`facebook` Publicaciones de Facebook · `instagram` Publicaciones de Instagram · `reels` Reels (9:16) · `video` Video (16:9) ·
`anuncios_meta` Anuncios de Meta · `whatsapp` Mensajes de WhatsApp (solo texto, no se envían).

#### 2.5.3 Piezas por canal (fijas en esta fase, F2-9) — una llamada a la IA por tipo
| Canal | Pieza | Variantes |
|---|---|---|
| `reels` | `reel_guion` | A, B |
| `video` | `video_guion` | A |
| `facebook` | `copy_facebook` | A, B |
| `instagram` | `copy_instagram` | A, B |
| `anuncios_meta` | `variante_anuncio` | A, B, C |
| `whatsapp` | `mensaje_whatsapp` | A (difusión), B (seguimiento), C (respuesta a quien pregunta) |

Etiquetas: Guion de Reel · Guion de Video · Copy de Facebook · Copy de Instagram · Variante de anuncio · Mensaje de WhatsApp ·
Reel producido · Video producido.

#### 2.5.4 Estrategia (`marketing.campana_360`, `json => true`, `max_tokens` 4000)
```json
{ "resumen": "≤ 600", "propuesta_valor": "≤ 300", "mensajes_clave": ["≤ 120", "… 3 a 5"],
  "publico_detalle": "≤ 600",
  "por_canal": [ { "canal": "reels", "rol": "Atraer", "formato": "Reel 9:16 de 15–30 s", "frecuencia": "3 por semana" } ],
  "calendario": [ { "semana": 1, "acciones": ["≤ 160"] } ],
  "reparto_presupuesto": [ { "canal": "anuncios_meta", "porcentaje": "60.00", "monto_mxn": "9000.00" } ],
  "kpis": [ { "nombre": "Leads", "meta": "150" } ], "riesgos": ["≤ 200"] }
```
- `por_canal` y `reparto_presupuesto` solo con canales de la campaña. **El backend recalcula `monto_mxn`** desde `porcentaje`
  con `bcmath` (half-up a 2 decimales) y el último renglón absorbe la diferencia: **la suma es exactamente `presupuesto_mxn`**.
  Porcentajes que no suman 100.00 se normalizan; presupuesto 0 → montos `"0.00"`.
- Prompt: datos de la campaña + Brand Kit (nombre, lema, teléfono, sitio) + nichos elegidos (motivadores, dolores,
  vocabulario de `marketing_niches`). Reglas: español de México, **no inventar precios, velocidades ni promociones fuera de la
  oferta**, dinero en pesos, sin promesas de «ilimitado» si la oferta no lo dice, solo JSON.

#### 2.5.5 Piezas (`marketing.piezas_campana`, `json => true`, `max_tokens` 3500) — forma de `contenido`
Respuesta de la IA por tipo: `{"piezas":[{"variante":"A","contenido":{…}}, …]}`. El backend valida cada `contenido` con
`ValidadorPiezas`; si algo no cumple, **un** reintento con «Tu respuesta no cumplió: <errores>. Responde solo el JSON
corregido.»; si vuelve a fallar → la fila queda `failed` con mensaje en español. Saneado de todo texto: `strip_tags`, sin `{{ }}`,
sin control; recorte al límite **solo** en edición manual no: en edición manual se responde 422 (el usuario decide).

- **`reel_guion`** (9:16) y **`video_guion`** (16:9):
```json
{ "titulo": "¿Se traba tu serie?", "formato": "9:16", "gancho": "¿Otra vez se trabó la serie?",
  "escenas": [
    { "numero": 1, "duracion_s": 3, "toma": "Mamá frente a la tele con el círculo de carga",
      "visual": { "tipo": "broll", "categoria_broll": "familia" },
      "texto_pantalla": "¿Se traba tu serie?", "voz_en_off": "¿Otra vez se trabó la serie?", "musica_sonido": "Golpe de bajo" },
    { "numero": 2, "duracion_s": 4, "toma": "Texto grande sobre el color de la marca",
      "visual": { "tipo": "texto", "categoria_broll": null },
      "texto_pantalla": "$599 al mes", "voz_en_off": "Fibra de 500 megas por quinientos noventa y nueve pesos.", "musica_sonido": "" },
    { "numero": 3, "duracion_s": 4, "toma": "Cierre con logo y teléfono", "visual": { "tipo": "cta", "categoria_broll": null },
      "texto_pantalla": "Contrata hoy", "voz_en_off": "Contrata hoy, la instalación es gratis.", "musica_sonido": "Remate" } ],
  "musica": { "ambiente": "uplifting_family" }, "voz": { "id": "nova" },
  "cta_final": { "texto": "Contrata hoy" }, "duracion_total_s": 11 }
```
  Límites `[F2-10]`: Reel 3–8 escenas y 10–45 s; Video 3–8 escenas y 15–75 s; cada escena 2–15 s (entero); `numero` lo pone el
  backend (1..n); `duracion_total_s` la calcula el backend (suma). `toma` ≤ 200 (texto para quien graba; MVM no filma, F2-14);
  `visual.tipo` ∈ `logo`, `broll`, `texto`, `cta`; `categoria_broll` ∈ slugs de nicho o null (obligatorio si `broll`);
  `texto_pantalla` sin emojis y con tope por tipo: `broll` ≤ 60, `texto` ≤ 18, `cta` ≤ 24, `logo` se ignora;
  `voz_en_off` ≤ 300 (≤ 2.5 palabras por segundo de la escena, regla para la IA, aviso —no error— en edición);
  `musica_sonido` ≤ 100 (indicación para quien edita); **la última escena es `cta`**; `musica.ambiente` ∈ §2.5.6 o
  `sin_musica`; `voz.id` ∈ §2.5.6; `cta_final.texto` ≤ 24. Teléfono: siempre el del Brand Kit, nunca de la IA.
- **`copy_facebook`**: `{"texto":"≤ 1500","hashtags":["≤ 30 c/u, máx. 5"],"cta":"≤ 40"}`.
- **`copy_instagram`**: `{"texto":"≤ 2000","hashtags":["máx. 15"],"cta":"≤ 40"}`.
- **`variante_anuncio`**: `{"titular":"≤ 40","texto_principal":"≤ 125","descripcion":"≤ 30","boton":"<clave>","formato":"imagen|video|carrusel","idea_visual":"≤ 200"}`;
  `boton` ∈ `MAS_INFORMACION`, `ENVIAR_MENSAJE`, `LLAMAR`, `REGISTRARTE`, `OBTENER_OFERTA`, `CONTACTANOS`.
- **`mensaje_whatsapp`**: `{"uso":"difusion|seguimiento|respuesta","texto":"≤ 700"}` — **solo texto**.
- `texto_plano`/`output_text`: versión legible para buscar y copiar (guion: título + una línea por escena).

#### 2.5.6 Catálogos cerrados
Voces: `alloy` (neutra), `echo` (masculina joven), `fable` (masculina narrativa), `onyx` (masculina grave), `nova` (femenina
cálida), `shimmer` (femenina suave). Música (`ambiente`): `corporate_clean`, `corporate_confident`, `epic_electronic`,
`modern_creative`, `uplifting_family`, `warm_acoustic`, `sin_musica` (etiquetas en español en `CatalogosCampana360`).

#### 2.5.7 Claves de IA (las escribe backend en `IA/config/modulos.php`, después de `marketing.director_creativo`, `:45`)
```php
'marketing.campana_360' => ['nombre' => 'Estrategia de campañas 360', 'grupo' => 'Marketing', 'requiere' => [], 'listo' => true],
'marketing.piezas_campana' => ['nombre' => 'Piezas de campañas 360 (guiones, copies, anuncios, WhatsApp)', 'grupo' => 'Marketing', 'requiere' => [], 'listo' => true],
```

### 2.6 Producir en Video MVM — cómo se conecta al motor `[F2-1]`

**`ProductorVideoCampana::producir(Pieza $guion, ?string $voz, ?string $musica): GeneratedContent`** (backend):
1. Plantilla `guion-escenas-9-16` (reel) o `guion-escenas-16-9` (video), `company_id = Empresa::PRINCIPAL_ID`.
2. `GuionAMvm` traduce cada escena a una escena del motor (los colores **nunca** vienen del guion; los pone el motor desde el
   Brand Kit):

| `visual.tipo` | Escena MVM |
|---|---|
| `logo` | `{type: logo_reveal, duration}` |
| `broll` | `{type: broll_overlay, duration, broll_source: "niche:<categoria_broll>", bg_darken: 0.35, layers: [{type: kinetic_text, text: texto_pantalla, duration_sec: duracion_s, style: huge_centered, position: center}]}` (sin texto → sin capa) |
| `texto` | `{type: feature_highlight, duration, main_text: texto_pantalla, sub_text: ""}` |
| `cta` | `{type: call_to_action, duration, cta_text: texto_pantalla ?: cta_final.texto, phone: "{{brand.phone}}"}` |

3. Variables del job: `_escenas_mvm` (lo de arriba), `voiceover_text` (las `voz_en_off` no vacías unidas con espacio),
   `_voz`, `_musica` (o null si `sin_musica`), `_niche_slug` (primera `categoria_broll` o primer nicho de la campaña),
   `_transition_type: fade`, `_transition_duration: 0.3`. `options = {aspect_ratio}`.
4. Crea `GeneratedContent` (`company_id` = empresa activa, `type video`, `pieza reel|video`, `pieza_origen_id`,
   `source_campaign_id`, `template_id`, `template_type = VideoTemplate::class`, `status pending`, `render_stage queued`,
   `render_progress 0`, `render_log []`, `generation_engine mvm_ffmpeg`, `input_variables` = variables,
   `generation_metadata = {template_slug, aspect_ratio, guion_hash: sha1(contenido)}`).
5. `RenderVideoJob::dispatch($id, $templateId, $variables, $options, Empresa::PRINCIPAL_ID)->onQueue('video-render')`.
   El video aparece también en **Video → Cola** (pantalla existente).

**Cambios al motor** (archivos compartidos con el generador actual; todo detrás de la bandera o sin efecto en lo existente):
- `VideoTemplateRenderer`:
  a) si `schema.escenas_desde_variables === true`: las escenas salen de `variables['_escenas_mvm']` pasadas por una **lista
     blanca** (tipos `logo_reveal`, `feature_highlight`, `broll_overlay`, `call_to_action`; claves permitidas por tipo; se
     descarta cualquier `*color*`; `duration` 2–15; máx. 8 escenas; `broll_source` solo `niche:<slug [a-z_]+>`); vacías →
     `['success'=>false,'error'=>'El guion no trae escenas.']`; la **duración total = suma de escenas** (tope 75 s) en lugar de
     `duration_seconds`.
  b) `variables['_voz']` en la lista de §2.5.6 → voz del TTS (antes que la del nicho).
  c) `variables['_musica']` en la lista → categoría de música (antes que la del esquema); **y la búsqueda usa `type = 'music'`**
     `[F2-7]`.
  d) el resultado agrega `voz_generada` (bool) y conserva `cost_usd`.
- `RenderVideoJob`:
  a) al empezar, si el registro ya está `ready` → termina sin hacer nada (idempotente);
  b) `middleware()`: `(new WithoutOverlapping("mkt-render-{id}"))->releaseAfter(60)->expireAfter(900)`;
  c) en éxito guarda `render_log = $result['log']`, `generation_metadata.voz_generada` y `tts_cost_usd = $result['cost_usd']`;
  d) al fallar llena `failure_reason` (≤ 500).
  Sin cambiar firma, cola, `tries` ni `timeout`.
- `Video.sin_voz` = `generation_metadata.voz_generada === false`.

### 2.7 Trabajos en cola (todos en `default`, que ya tiene procesador en dev — F2-8)

| Job | Qué hace | `tries` / `timeout` |
|---|---|---|
| `GenerarEstrategiaCampanaJob(campanaId)` | `estrategia_estado` generando → llama `marketing.campana_360` → valida → guarda; si `alcance = todo` crea filas `pending` y despacha piezas | 1 / 170 |
| `GenerarPiezasCampanaJob(campanaId, pieza)` | filas de ese tipo → `generating` → una llamada `marketing.piezas_campana` (con la estrategia en el prompt) → `ready`/`failed` por fila | 1 / 170 |
| `RegenerarPiezaCampanaJob(piezaId, indicaciones)` | una llamada para **esa** variante (con las otras variantes como contexto para no repetirlas) | 1 / 170 |

`failed()` de cada job deja la fila/campaña en `error`/`failed` con mensaje en español; `IANoConfigurada` → «Falta asignar
la IA…». Nunca se reintenta solo (cuesta dinero).

### 2.8 `module.json` (dueño: backend; **sin subir `version`**)

- `api_endpoints[]`: + las 14 API de §2.4.
- `menu[0].children`: nuevo item **2.º** `{label: "Campañas 360", url: "/marketing/campanas-360", icono_material: "rocket_launch",
  roles_empresa: ["admin","marketing"]}` (sin `permission`); el item «Campañas IA» se renombra **«Video por nicho»** (misma URL)
  `[F2-11]`. Validar el nombre del icono Material antes de usarlo (si no existe, `campaign`).
- Sin permisos nuevos `[F2-12]` (los de §2.3 ya están declarados).

### 2.9 Frontend: lo que comparten

- **Tags de pantalla** (registrados en `registrar.js` → `PANTALLAS`): `marketing-campanas-360-lista` (sin props),
  `marketing-campana-360-asistente` (prop `campana-id`, número o null), `marketing-campana-360-ficha` (prop `campana-id`).
  Las vistas Blade (backend) los montan dentro de `@extends('core-layout::master')` como las demás.
- **Sondeo**: la ficha llama `GET …/progreso` cada **5 s** solo mientras haya estrategia/piezas `generando`/`pendiente` o videos
  `en_cola`/`produciendo`; se detiene al terminar, al ocultar la pestaña (`visibilitychange`) y a los 30 min.
- **Estados obligatorios** (guía §5) en las 3 pantallas y en cada tarjeta de pieza: cargando, vacío, error (con `mensaje`),
  sin permiso, no encontrado (404), **IA no configurada** (422 `ia_no_configurada` → `mkt-estado-vacio` con el mensaje, sin
  reintentar en bucle), **generando** (esqueleto por pieza), **demorado** (aviso «Sigue en cola: el procesador de videos no
  está corriendo. Avísale a Irving.»).
- Dinero: `formatoMXN(cadena)`. Duraciones: agregar `formatoDuracion(seg)` a `kit/formato.js` (solo agregar).
- Botones que escriben con `:loading` y deshabilitados durante la petición (doble clic = una sola fila). Ningún `v-html`;
  `<style scoped>`; clases `mkt-*`; 375 px sin scroll horizontal; claro/oscuro; Material Icons en Quasar.
- WhatsApp: aviso fijo «Solo texto: estos mensajes no se envían desde LM. Cópialos y úsalos donde corresponda.» y **solo**
  botón «Copiar».

### 2.10 Base de pruebas, migraciones, cola y vista previa

- **Migrar** (solo backend):
  `cd /var/www/megaisp-henry-marketing && flock -w 600 /tmp/megaisp-suite.lock env APP_ENV=testing DB_DATABASE=megaisp_test php artisan migrate --path=app/Modules/Addons/Marketing/migrations --force`
  y `env APP_ENV=testing DB_DATABASE=megaisp_test php artisan permission:cache-reset`.
- **Compilar** (solo frontend), como en Fase 1 (`timeout 900 … npm run dev`). QA no compila.
- **Servidor**: frontend 8096, QA 8097, **con `QUEUE_CONNECTION=database`** (ya no `null`) y `--no-reload`:
  ```bash
  cd /var/www/megaisp-henry-marketing && env APP_ENV=local APP_URL=http://127.0.0.1:8096 DB_DATABASE=megaisp_test \
    QUEUE_CONNECTION=database MAIL_MAILER=log php artisan serve --host=127.0.0.1 --port=8096 --no-reload
  ```
- **Procesador de cola de la vista previa** (los trabajos quedan en `megaisp_test.jobs`; los procesadores de dev leen `megaisp`,
  no se cruzan). Uno a la vez, con candado, tope y en primer plano:
  ```bash
  flock -w 60 /tmp/megaisp-mkt-worker.lock timeout 900 nice -n 10 env APP_ENV=local DB_DATABASE=megaisp_test \
    php artisan queue:work database --queue=default,video-render --max-time=840 --timeout=600 --tries=1 > $S/worker.log 2>&1
  ```
- **Usuarios de vista previa**: los `mkt_prev_*` de Fase 0/1 (mismo script, contraseña con `read -s`), con la marca
  `equipo_fijado_at` en `megaisp_test`.
- **Datos de vista previa** (solo `megaisp_test`, script `tinker` en el scratchpad, nunca seeder): 2 nichos (`familia`,
  `home_office`), una campaña `PREV-F2 Fibra 500` con estrategia y **todas** las piezas tomadas de los ejemplos de §2.5 /
  fixtures de QA, y una campaña `PREV-F2 Vacía` en borrador. La IA real no está en `megaisp_test`: «Generar» mostrará
  «IA no configurada» (estado a validar). QA borra todo lo `PREV-F2%` al cierre (con sus piezas, videos y archivos).

---

## 3. PAQUETE BACKEND (`agente-backend`) — tamaño **XL** (~37 archivos, ~4 h)

**Crear:**
- `app/Modules/Addons/Marketing/migrations/2026_11_13_200000_agrega_campos_360_a_marketing_campaigns.php`
- `…/migrations/2026_11_13_200100_agrega_pieza_a_marketing_generated_content.php`
- `…/migrations/2026_11_13_200200_crea_plantillas_guion_por_escenas.php`
- `…/migrations/2026_11_13_200300_asegura_permisos_motor_video.php` `[F2-6]`
- `Controllers/Campana360Controller.php`, `Controllers/Campana360GeneracionController.php`,
  `Controllers/Campana360PiezaController.php`, `Controllers/Campana360VideoController.php`
- `Requests/GuardarCampana360Request.php`, `Requests/CambiarEstadoCampanaRequest.php`, `Requests/GenerarCampana360Request.php`,
  `Requests/ActualizarEstrategiaRequest.php`, `Requests/ActualizarPiezaRequest.php`, `Requests/RegenerarPiezaRequest.php`,
  `Requests/ProducirVideoRequest.php`
- `Services/Campanas360/CatalogosCampana360.php` (objetivos, canales, piezas por canal, voces, músicas, botones, límites,
  etiquetas), `PromptsCampana360.php`, `GeneradorEstrategia.php`, `GeneradorPiezas.php`, `ValidadorPiezas.php` (incluye
  saneado), `RepartoPresupuesto.php` (bcmath), `GuionAMvm.php`, `ProductorVideoCampana.php`, `Campana360Presenter.php`
  (formas JSON de §2.4 y mapeo de estados)
- `Jobs/GenerarEstrategiaCampanaJob.php`, `Jobs/GenerarPiezasCampanaJob.php`, `Jobs/RegenerarPiezaCampanaJob.php`
- `views/campanas-360/lista.blade.php`, `views/campanas-360/asistente.blade.php`, `views/campanas-360/ficha.blade.php`
- `tests/Feature/Marketing/Fase2/Backend/**` — mínimo: `Campana360CrudBackendTest`, `GeneracionBackendTest`,
  `ValidadorPiezasBackendTest`, `RepartoPresupuestoBackendTest`, `GuionAMvmBackendTest`, `ProducirBackendTest`,
  `RendererEscenasBackendTest`, `MigracionesFase2BackendTest`

**Modificar:**
- `routes.php` — 18 rutas (§2.4) + `piezaId`/`videoId` en `$NUM`. Nada más.
- `module.json` — §2.8.
- `app/Models/Marketing/Campaign.php` — `fillable`/`casts` de las columnas nuevas; relación `piezas()`.
- `app/Models/Marketing/GeneratedContent.php` — `fillable`/`casts` de las columnas nuevas; relaciones `guion()` / `videos()`.
- `Jobs/RenderVideoJob.php` y `Services/Video/VideoTemplateRenderer.php` — solo lo de §2.6 `[F2-1][F2-7]`.
- `Controllers/MarketingGeneratedContentController.php` — **solo** agregar `source_campaign_id` y `pieza` al `select` de `index`.
- `app/Modules/Addons/IA/config/modulos.php` — las 2 claves de §2.5.7. Ninguna otra línea.

**Orden:** (1) migraciones + aplicarlas en `megaisp_test` (§2.10) — avisar en el avance para que frontend siembre ;
(2) catálogos, validador, reparto, presenter ; (3) CRUD + estado + rutas + Blade + `module.json` ; (4) IA: prompts, generadores,
jobs, generar/progreso/estrategia/regenerar ; (5) motor: `GuionAMvm`, `ProductorVideoCampana`, cambios al renderer y al job,
producir/ver/miniatura ; (6) pruebas propias ; (7) `php artisan route:list --path=marketing | wc -l` (152) y
`grep -rnE "WhatsAppGateway|EvolutionApi|SendOutboundMessage|PublishPostJob|sofia|Http::(post|withToken)" ` sobre los archivos
nuevos → vacío ; (8) correr por nombre las pruebas viejas del motor: `RenderVideoJobTest`, `VideoTemplateRendererTest`,
`MultivariantCampaignControllerTest`, `KineticTextRendererTest`, `FFmpegServiceTest`, `TtsServiceTest` → igual que en la base.

**Pruebas propias mínimas:** validación de cada campo del asistente (normal/borde/ataque); transiciones de estado; generar con
IA simulada (estrategia válida, inválida → reintento → error, `IANoConfigurada` → 422); reparto exacto con centavos y con 0;
cada validador de pieza; `GuionAMvm` (cada tipo, colores descartados, `{{ }}` y emojis fuera); renderer con bandera (escenas de
variables, duración = suma, `_voz`, `_musica`, música `type music`) y **sin** bandera (plantilla genérica igual que antes);
producir (variables exactas, cola `video-render`, 409 doble, 404 cruzado); migraciones idempotentes y `rollback`.

**Fuera de alcance:** cualquier archivo no listado; los Vue; `app.js`; `config/**`; Supervisor; `tests/Feature/Marketing/Fase0/**`,
`Fase1/**` y las pruebas viejas (son de QA); el generador por nicho (`GenerateMultivariantCampaignJob`, su controlador).

---

## 4. PAQUETE FRONTEND (`agente-frontend`) — tamaño **XL** (~26 archivos, ~4.5 h, en 2 olas)

**Crear** (en `resources/js/components/module/marketing/campanas360/`):
- Pantallas: `MarketingCampanas360Lista.vue`, `MarketingCampana360Asistente.vue`, `MarketingCampana360Ficha.vue`
- Asistente: `PasoObjetivo.vue`, `PasoPublico.vue`, `PasoOferta.vue`, `PasoCanales.vue`, `PasoPresupuestoFechas.vue`,
  `PasoRevisar.vue`
- Ficha: `PanelEstrategia.vue` (con reparto MXN en barra horizontal ApexCharts + `kit/tokens.js`), `TarjetaGuion.vue`
  (tabla de escenas: número, duración, toma, visual, texto en pantalla, voz en off, música; duración total; botones Editar,
  Regenerar, **Producir en Video MVM**; estado del video, reproductor `<video controls>` y miniatura), `EditorGuionEscenas.vue`
  (agregar/quitar/reordenar escenas, contadores de caracteres por tipo, total en vivo contra el límite), `TarjetaCopy.vue`
  (FB/IG, A/B lado a lado), `TarjetaAnuncio.vue` (contadores contra los topes de §2.5.5), `TarjetaWhatsApp.vue` (solo Copiar),
  `DialogoProducirVideo.vue` (voz y música precargadas del guion, sobre `mkt-dialogo`), `DialogoRegenerarPieza.vue`
  (indicaciones opcionales), `catalogos360.js` (etiquetas y límites espejo de §2.5), `sondeo.js` (§2.9)
- `tests/Feature/Marketing/Fase2/Frontend/PantallasCampanas360Test.php` — las 4 vistas responden 200 al admin con su tag y
  prop; 403 a quien no es admin de Meganet; sin sesión → login.

**Modificar:**
- `registrar.js` — los 3 tags de §2.9 en `PANTALLAS`.
- `kit/formato.js` — agregar `formatoDuracion` (sin cambiar lo existente); `kit/index.js` solo si hace falta.
- Enlaces «Crear campaña con IA» / «Nueva campaña» → `/marketing/campanas-360/nueva`: `dashboard/PanelCampanasActivas.vue:23`,
  `MarketingDashboard.vue:32` (y el acceso rápido `:80` → «Campañas 360» `/marketing/campanas-360`),
  `publishing/PublishCampaignView.vue:38`.
- `MarketingVideoGeneratorView.vue` — excluir del selector las plantillas `category = guion_escenas`.
- `MarketingVideoTemplatesView.vue` — insignia «Solo desde Campañas 360» en esas plantillas, sin botón de generar.
- `MarketingVideoQueueView.vue` — si la fila trae `source_campaign_id`, enlace «Ver campaña» a `/marketing/campanas-360/{id}`.
- `docs/marketing/guia-visual.md` — agregar: asistente por pasos, tarjeta de guion/editor de escenas, estado de producción de
  video (en cola / produciendo / listo / error / demorado), aviso de WhatsApp solo texto.
- `docs/marketing/fase-2-avance-frontend.md`.

| Ola | Pantallas | Cierre |
|---|---|---|
| 1 | Lista + Asistente (5 pasos + revisar, guardar borrador, editar) + ficha con estrategia y estados de generación | avisar a QA en el avance |
| 2 | Ficha completa: guiones, editor de escenas, producir y estado de video, copies, anuncios, WhatsApp + enlaces y ajustes de Video | avisar a QA |

**Cierre de cada ola:** `npm run dev` sin errores ni advertencias nuevas; Playwright (8096) a 1440 y 375 px, claro y oscuro, con
datos `PREV-F2`, vacío y con error (incluido «IA no configurada»); en la ola 2, producir el guion de Reel y el de Video con el
procesador de la vista previa (§2.10) y capturar En cola → Produciendo → Listo con el video reproduciéndose; consola sin errores;
capturas en `…/scratchpad/fase2/frontend/`; una línea en el avance con la lista.

**Fuera de alcance:** `resources/js/app.js`; los 4 componentes viejos sin uso (F2-13); cualquier `.php` salvo su prueba; Blade;
CSS global; `public/` (lo genera `npm run dev` y no entra al commit).

---

## 5. PAQUETE QA (`agente-qa`) — tamaño **L** (~16 archivos de prueba + navegador, ~3 h)

Empieza desde el contrato, sin esperar código. **Primera tarea (≤ 15 min):** fixtures y escenario, y avisarlo en el avance
(frontend los usa para sembrar la vista previa).

**Crear** (todas extienden `MarketingTestCase`; no siembran permisos que deban venir de migración):
- `Fase2/Qa/fixtures/ia/estrategia-valida.json`, `piezas-<tipo>-validas.json` (los 6 tipos), `piezas-invalidas.json`
  (escena de 40 s, emoji, `{{brand.phone}}` en texto, color en escena, sin CTA final, botón fuera de lista, JSON roto).
- `Fase2/Qa/Concerns/EscenarioFase2.php` — admin de Meganet (fila `admin` activa + marca), usuario de LM sin equipo, inactivo;
  2 nichos; Brand Kit mínimo; asignación de IA simulada a `marketing.campana_360`, `marketing.piezas_campana` y
  `marketing.voz_videos` (integración ficticia sin llave real, como `IaSupervisorEconomicoTest`) + `Http::fake` de la IA y de
  `/audio/speech` (devuelve un mp3 corto generado con ffmpeg en el `setUp`).
- `AccesoCampanas360Test.php` — barrido desde el router de las 18 rutas: sin sesión nunca 200; sin equipo / fila no admin /
  super-administrador sin fila → 403; inactivo → 401; admin → 2xx; id de otra campaña o pieza ajena → 404.
- `AsistenteCampanaTest.php` — cada regla de §2.4.2 (normal, vacío, borde, arrays, `<script>`, SQL, `{{ }}`, montos con 3
  decimales, fechas al revés, 367 días, nicho inexistente).
- `GeneracionEstrategiaTest.php` — 202/409/422 de §2.4.7; estrategia guardada; reparto **suma exacta** (centavos, 0, porcentajes
  que no suman 100); JSON inválido → 1 reintento → error con mensaje; IA no configurada → 422 sin despachar nada.
- `GeneracionPiezasTest.php` — piezas y variantes exactas por canal (§2.5.3); una llamada por tipo; `failed` por tipo no tumba
  los demás; progreso calculado bien.
- `EditarRegenerarPiezaTest.php` — editar valida y versiona; regenerar cambia **solo** esa pieza (hash del resto igual); 409
  si ya genera; indicaciones saneadas.
- `ProducirVideoTest.php` — `Queue::fake`: `RenderVideoJob` en `video-render` con `_escenas_mvm`, `voiceover_text`, `_voz`,
  `_musica` exactos; fila con `pieza`, `pieza_origen_id`, `source_campaign_id`, plantilla correcta; doble POST → una fila + 409;
  pieza no guion → 422; guion inválido → 422 con errores; ver/miniatura 404 hasta `listo`; ruta de archivo manipulada → 404.
- `MotorEscenasTest.php` — renderer: sin bandera la plantilla genérica produce las mismas escenas que antes; con bandera,
  lista blanca (colores, tipos, `broll_source` raro descartados), duración = suma, voz elegida, música `type music`;
  `RenderVideoJob` idempotente, guarda log/`voz_generada`/`failure_reason`.
- **`ProduccionPuntaAPuntaTest.php`** (criterio de Henry) — con IA y voz simuladas y **ffmpeg real**: crear campaña con
  `reels` y `video` → generar → producir 1 Reel y 1 Video → correr `RenderVideoJob` en el proceso → ambos `ready`, MP4 existe,
  `ffprobe` da **1080×1920** y **1920×1080**, duración dentro de suma ± ((n−1)×0.3 + 1) s, con pista de audio, ligados a la
  campaña y visibles en `progreso` como `listo`. Borra los archivos al terminar. `timeout 900`.
- `WhatsAppSoloTextoTest.php` — generar, editar y regenerar mensajes de WhatsApp sin ningún envío: `Http::assertNotSent` a
  Evolution/Graph, `Queue::assertNotPushed(SendOutboundMessageJob|PublishPostJob)`, sin filas en `marketing_messages` ni
  `marketing_publications`.
- `EstadoCampanaTest.php` — transiciones; activar sin estrategia → 422; la campaña activa aparece en
  `GET /api/marketing/dashboard/campanas-activas` con su presupuesto en MXN.
- `MigracionesFase2Test.php` — columnas, plantillas y permisos tras migrar; `up()` dos veces igual; `down()` de plantillas no
  borra si hay videos que las usan.
- `docs/marketing/fase-2-qa-reporte.md`, `docs/marketing/fase-2-avance-qa.md`.

**Modificar (solo por el contrato, sin bajar exigencias, cada cambio con su razón en el reporte):**
- `Fase0/Qa/RutasMarketingProtegidasTest.php` — total **134 → 152**, las 18 en `nuevas()`.
- `Fase1/Qa/NavegacionTest.php` y `Fase1/Backend/NavegacionBackendTest.php` si comparan el menú (nuevo item y renombre).
- Cualquier prueba de Fase 0/1 o vieja de `tests/Feature/Marketing/*.php` que dependa del renderer o del job (§2.6): correrla
  primero por nombre y anotar qué falla y por qué.

**Navegador (8097, al cierre de cada ola de frontend)** — en el reporte con capturas:
1. Asistente completo a 1440 y 375 px; guardar borrador a mitad y volver; recargar a mitad sin duplicar.
2. Doble clic en Guardar, Generar, Regenerar y **Producir** → una sola fila / un solo trabajo (contar en `megaisp_test`).
3. **Punta a punta en pantalla**: con la campaña `PREV-F2`, producir el Reel A y el Video A con el procesador de vista previa →
   ver En cola → Produciendo → Listo y reproducir ambos; un render forzado a fallar (plantilla desactivada) → Error con mensaje;
   procesador apagado 10 min → aviso «demorado».
4. WhatsApp: solo «Copiar» y el aviso visible.
5. Otro usuario de LM escribe las 4 URL → «No tienes acceso a Marketing»; `/marketing/campanas-360/999999` → «No encontrado».

**Al cierre:** skill `pruebas-antes-de-avanzar` pasos 2–7 (N = 4 migraciones; dry-run con
`--with-data=marketing_campaigns,marketing_generated_content,marketing_video_templates,permissions,roles,role_has_permissions`),
sin el paso 1 (suite completa: sesión principal); limpieza de §2.10; `git diff --stat` contra la base.

---

## 6. Regla de no-pisarse

| Archivo / carpeta | Dueño único |
|---|---|
| `app/Modules/Addons/Marketing/routes.php`, `module.json`, `views/campanas-360/**` | backend |
| `app/Modules/Addons/Marketing/{migrations,Controllers,Requests,Services,Jobs}/**` (los de §3) | backend |
| `app/Models/Marketing/Campaign.php`, `GeneratedContent.php`; `app/Modules/Addons/IA/config/modulos.php` (2 claves) | backend |
| `tests/Feature/Marketing/Fase2/Backend/**`, `docs/marketing/fase-2-avance-backend.md` | backend |
| `resources/js/components/module/marketing/**` (los de §4, incl. `registrar.js`) | frontend |
| `docs/marketing/guia-visual.md`, `docs/marketing/fase-2-avance-frontend.md`, `tests/Feature/Marketing/Fase2/Frontend/**` | frontend |
| `tests/Feature/Marketing/Fase2/Qa/**` (incl. fixtures), `Fase0/**`, `Fase1/**`, `tests/Feature/Marketing/*.php` | QA |
| `docs/marketing/fase-2-qa-reporte.md`, `docs/marketing/fase-2-avance-qa.md` | QA |
| `docs/marketing/fase-2-paquetes.md`, `fase-2-linea-base-fallas.txt`, `docs/bitacora/2026-11-1x-marketing-fase-2.md` | supervisor |
| `resources/js/app.js`, `config/**`, Supervisor, layout/menú global, otros módulos | **nadie** |

`megaisp_test`: migrar = backend; usuarios y datos `PREV-F2` = frontend (QA re-corre y limpia); procesador de cola = quien lo
necesite, uno a la vez con `/tmp/megaisp-mkt-worker.lock`. Compilar = frontend. Puertos: frontend 8096, QA 8097. Quien necesite
un archivo ajeno lo pide al supervisor en su avance con el texto exacto; no lo toca.

---

## 7. Criterios de aceptación (verificables)

1. `/marketing/campanas-360/nueva`: 5 pasos + revisar, validación en línea en español, guardar borrador en cualquier paso; la
   campaña aparece en `/marketing/campanas-360` como **Borrador** con presupuesto en MXN.
2. «Generar con IA» (con IA asignada) produce la estrategia y **exactamente** las piezas de §2.5.3 según los canales; la ficha
   muestra el avance por pieza mientras se genera. Sin IA asignada: mensaje «Falta asignar una IA…», nunca un 500.
3. Cada pieza se **edita** (con validación) y se **regenera** sola; regenerar una no cambia ninguna otra (QA lo prueba por hash).
4. Guiones de Reel (9:16) y Video (16:9) con número, duración (s), toma/visual, texto en pantalla, voz en off, música/sonido,
   CTA final y duración total = suma; última escena CTA; límites de §2.5.5.
5. «Producir en Video MVM» crea **un** trabajo en el motor existente con plantilla, escenas, voz y música cargadas (sin copiar
   y pegar), ligado a la campaña (`source_campaign_id`, `pieza_origen_id`), en la cola `video-render`, y visible también en
   Video → Cola.
6. La ficha muestra el estado de cada video (**En cola / Produciendo / Listo / Error**, «sin voz» y «demorado»), reproduce el
   video listo y muestra su miniatura.
7. **Punta a punta (criterio de Henry):** una campaña de prueba produce **≥ 1 Reel (MP4 1080×1920) y ≥ 1 Video (MP4 1920×1080)**:
   (a) `ProduccionPuntaAPuntaTest` en verde; (b) en navegador con la vista previa (§5, punto 3); (c) en dev con IA real, al
   integrar, lo corre la sesión principal con Henry (requiere F2-3 y F2-4).
8. WhatsApp: solo texto, solo «Copiar»; ningún envío (`WhatsAppSoloTextoTest`); grep sin referencias nuevas a gateway,
   Evolution, envío de mensajes ni Sofía.
9. Dinero: cadenas MXN con 2 decimales; el reparto de la estrategia suma exactamente el presupuesto; sin `float` en cálculos de
   dinero (grep).
10. Acceso: las 18 rutas nuevas niegan a todos salvo al admin de Meganet; sin sesión nunca 200 (`AccesoCampanas360Test`).
11. IA solo con `IA::enviar('marketing.campana_360'|'marketing.piezas_campana')`; en `modulos.php` solo esas 2 líneas nuevas;
    ningún dato de leads o clientes en los prompts (revisión del diff).
12. El motor existente no cambia: `RenderVideoJobTest`, `VideoTemplateRendererTest`, `MultivariantCampaignControllerTest`,
    `KineticTextRendererTest`, `FFmpegServiceTest`, `TtsServiceTest` igual que en la base; la plantilla genérica produce las mismas
    escenas.
13. `megaisp_dryrun`: las 4 migraciones corren en limpio, `rollback --step=4` y re-`migrate` pasan; idempotentes.
14. Rutas de Marketing: **152**; `RutasMarketingProtegidasTest` en verde.
15. Las 3 pantallas con el kit, navegación del módulo («Campañas 360» 2.º), estados completos, 1440/375 px, claro/oscuro, sin
    errores de consola.
16. **Suite completa (sesión principal): 0 fallas nuevas** contra `docs/marketing/fase-2-linea-base-fallas.txt` (mismo comando
    `php -r` de la Fase 1, §7.15).
17. `git diff --stat` solo con archivos de §6 (más `public/js/` sin trackear); `app.js` intacto.

---

## 8. Riesgos y cómo se cubren

| # | Riesgo | Cobertura |
|---|---|---|
| R1 | Nadie procesa `video-render` → «Producir» se queda en cola para siempre (ya pasa con 4 videos de junio) | Aviso «demorado» en la ficha; F2-3 a Irving; la vista previa usa su propio procesador acotado |
| R2 | Un render largo pasa `retry_after` (300 s) y corre dos veces | Límites de duración (F2-10), `WithoutOverlapping`, idempotencia en el job, un solo proceso dedicado (F2-3); QA mide el tiempo de render y lo reporta |
| R3 | La IA devuelve JSON roto o inventa precios/promesas | Validador por pieza + 1 reintento; reglas del prompt; precio solo de la oferta; teléfono solo del Brand Kit |
| R4 | Gasto de IA descontrolado (clics repetidos) | Una generación a la vez por campaña (409), `throttle:6,1`, sin reintentos automáticos, regenerar es por pieza |
| R5 | Cambiar el renderer rompe el generador actual | Todo detrás de `escenas_desde_variables`; pruebas viejas del motor por nombre; QA compara la plantilla genérica |
| R6 | Texto que se sale del cuadro (drawtext no parte líneas) | Topes por tipo de escena (§2.5.5); QA extrae un cuadro por escena con ffmpeg y lo revisa |
| R7 | Inyección en el filtro de ffmpeg o en `{{ }}` | Colores nunca del guion; lista blanca de claves; saneado de `{{ }}`, emojis y control; `escapeshellarg` existente |
| R8 | B-roll, música y logo no existen en dev → videos con fondo de color | Aceptado (F2-14); en prod la biblioteca está en BD (archivos sin verificar: Irving) |
| R9 | Voz no asignada → video mudo sin aviso | `voz_generada` en el job + «sin voz» en la ficha; F2-4 |
| R10 | Frontend es el paquete más grande | 2 olas con cierre propio; avance cada 15 min; la sesión principal lo retoma a los 20 min |
| R11 | Generar en la cola `default` compite con cobranza | Jobs cortos (≤ 170 s, una llamada c/u); `video-render` va aparte |
| R12 | Datos de vista previa y archivos de video ensucian `megaisp_test` y el disco | Prefijo `PREV-F2`, limpieza de QA con archivos, `flock` |
| R13 | `megaisp_test` sin plantillas/nichos/permisos del motor | Las plantillas de guion y los permisos llegan por migración (§2.3); nichos y Brand Kit los siembra cada prueba |

---

## 9. Decisiones que necesitan a Henry

### Bloqueantes

| # | Pregunta | Recomendación |
|---|---|---|
| **F2-1** | El motor MVM hoy no recibe escenas (solo variables fijas de cada plantilla). ¿Se le enseña a recibir el guion por escenas con 2 plantillas nuevas «Guion por escenas» (Reel 9:16 y Video 16:9) insertadas por migración, tocando `VideoTemplateRenderer` y `RenderVideoJob` detrás de una bandera? | **Sí** (lo actual no cambia) |
| **F2-2** | ¿Se adelantan a la Fase 2 las columnas `pieza` y `variante` (el plan las tenía en Fase 3), más `contenido`, `pieza_origen_id`, `editado_at`, `regeneraciones` en contenido generado, y en campañas `oferta` como JSON (el plan decía texto) con `estrategia_estado/error` y `creado_por_user_id`? | **Sí** |
| **F2-3** | *(para cerrar, no para arrancar)* No hay procesador de la cola `video-render` en dev (y en prod no se ha verificado): sin él «Producir» se queda «En cola». ¿Se le pide a Irving un proceso de Supervisor dedicado (1 proceso, `--queue=video-render --timeout=600`, con `nice`)? | **Sí, pedirlo ya** |
| **F2-4** | *(para cerrar, no para arrancar)* En dev no hay IA asignada a nada de Marketing. ¿Se le pide a Irving asignar en Integraciones → Módulos IA las 2 claves nuevas (o la IA general de Marketing) y la voz `marketing.voz_videos`, con crédito? Sin eso no hay prueba con IA real ni voz. | **Sí** |

### No bloqueantes (arrancan con la recomendación)

| # | Pregunta | Recomendación |
|---|---|---|
| F2-5 | Voz en off: una sola pista continua (las voces de cada escena unidas) o una por escena, sincronizada. | Una pista por ahora |
| F2-6 | Crear por migración los permisos del motor de video si faltan (en prod ya existen: no cambia nada; arregla la base de pruebas). | Sí |
| F2-7 | Arreglar que la música de fondo nunca suena (busca tipo `audio`, la biblioteca guarda `music`). También cambia los videos del generador por nicho. | Sí |
| F2-8 | La IA genera en segundo plano (cola `default`, que ya funciona) y la ficha se actualiza sola cada 5 s. | Sí |
| F2-9 | Piezas fijas por canal (tabla §2.5.3) en esta fase; elegir cuántas, después. | Sí |
| F2-10 | Límites de guion: Reel 10–45 s, Video 15–75 s, 3–8 escenas de 2–15 s; textos en pantalla cortos por tipo. | Sí; ampliar tras medir |
| F2-11 | Campañas nuevas como tipo «Mixta», en `/marketing/campanas-360`, menú «Campañas 360» en 2.º lugar y «Campañas IA» renombrado «Video por nicho». | Sí |
| F2-12 | Sin permisos nuevos: el acceso es la regla de Fase 1 (solo admin de Meganet), como el Panel. | Sí |
| F2-13 | Los 4 componentes viejos sin uso siguen sin borrar (tocaría `app.js`). | Dejarlos |
| F2-14 | «Toma» es texto para quien graba; el motor no filma: usa el B-roll de la biblioteca por categoría (en dev no hay archivos → fondo de color). | Aceptar |
| F2-15 | Tope de 6 por minuto a Generar, Regenerar y Producir (cuidar el gasto de IA y del servidor). | Sí |


## 2.11 Menú de Marketing (propuesta para Henry — NO programar hasta su aprobación)
Solo lo ven quienes pasan la regla de acceso de la Fase 1 (Henry e Irving). Orden de trabajo:
1. **Panel** → `/marketing`.
2. **Campañas 360** → `/marketing/campanas-360` (lista + «Nueva campaña» con IA). Fase 2.
3. **Contenido** → biblioteca y calendario. Llega en la Fase 3 (no se muestra antes).
4. **Reels y videos** → `/marketing/video-templates` con pestañas: Plantillas · Generar · Video por nicho (antes «Campaña IA», `/marketing/campaigns/generate`) · Fila de producción (`/marketing/video-queue`).
5. **Publicación** → `/marketing/publishing` con pestañas: Resumen · Publicar (antes «Publicar», `/publishing/campaign`) · Fila de publicación (`/publishing/queue`). Une «Publicador» y «Publicar».
6. **Leads** → `/marketing/leads` con pestañas: Leads (Kanban en Fase 4) · Conversaciones · Formularios de captura.
7. **Pruebas A/B** → `/marketing/pilot-campaigns`.
8. **Analítica** → llega en la Fase 5 (no se muestra antes).
9. **Configuración** → `/marketing/brand-kit` con pestañas: Brand Kit · Voces (`/voice-comparator`) · Canales de publicación (`/publishing/setup`) · enlace a Integraciones.
Se quitan del menú: «Campañas» y «Plantillas» viejas (retiradas en Fase 1), los dos «Publicar/Publicador» (unidos), «Cola» suelta (va dentro de su sección).

## 2.12 AGREGADO por Henry al cierre de la Fase 2 (lo hace la sesión principal)
- **(A) Prueba real en dev:** 1 Reel y 1 Video de punta a punta (campaña → guion por escenas → voz → música → archivo final),
  con IA y voz reales en dev y un procesador temporal de `video-render` (sin tocar Supervisor). Se entregan las rutas para
  que Henry los vea antes que Irving. Requiere IA de Marketing y voz asignadas en el Integraciones de dev.
- **(B) Costos en MXN por pieza y mensual (8 reels + 4 videos):** texto IA (Claude/Anthropic), voz (OpenAI TTS), clips
  (Pexels, gratis), música (biblioteca local, gratis), render (ffmpeg en el servidor de LM, gratis). Va en el reporte y en
  `entrega-irving.md`.

## 2.13 AGREGADO por Irving (vía Henry, 2026-11-13): diseño unificado con Clientes
Referencia obligatoria (solo lectura, no se toca): módulo de Clientes (`/cliente`, `/cliente/listar`). En TODAS las
pantallas de Marketing: breadcrumb «Página > Marketing > …»; tarjeta blanca con título y filtros en la misma fila; tablas
como Listado de Clientes; submenú con iconos como Clientes; botones y colores del sistema; todo en español (nada de
«No data available»). Antes de cerrar: captura de cada pantalla junto a Clientes para comparar. Todo en beta.

**Precisión de Irving (audio, 2026-11-13), se suma a §2.13:** (1) «Marketing» en el menú lateral entra DIRECTO al
Panel de Marketing; (2) dentro del módulo, UN SOLO grupo de pestañas compacto, sin flechas de desplazamiento ni
pestañas que ocupen media pantalla; (3) referencia: cómo se entra a un CLIENTE (la ficha con sus pestañas, dividida
y organizada) además del listado — solo lectura; (4) cada sección organizada y combinable con el resto del sistema.
Lo califica `agente-calidad-visual` (puntos 13–15).
