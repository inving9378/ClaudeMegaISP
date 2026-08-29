# Barrido de servicios con métodos de ciclo en módulo Marketing (item #792, sub-item de #657)

**Alcance de esta pasada:** servicios con métodos de ciclo (tick/ciclo/drain/barrido/procesar y
equivalentes: sync/run/dispatch/process, y por extensión cualquier método con semántica de
"barrido periódico" aunque su nombre literal no calce con esas palabras — mismo criterio amplio
que el propio texto del item con "y equivalentes") dentro de `app/Modules/Addons/Marketing/` +
`app/Models/Marketing/` + `app/Http/Controllers/Marketing/`.

**Corrección de ruta declarada en el item:** `app/Http/Controllers/Marketing/` **no existe** en
este repo — los controllers de Marketing viven en `app/Modules/Addons/Marketing/Controllers/`
(arquitectura modular, confirmado con `find`). Se barrió esa ruta real en su lugar; no hay
controllers de Marketing fuera del módulo.

**SOLO INVENTARIO** (mismo mandato que los precedentes #647/#789): esta pasada NO borra, desconecta
ni ejecuta nada. No se disparó ningún job real de WhatsApp/FFmpeg ni se llamó ninguna API externa —
todo el barrido fue lectura de código (`grep`/`find`/lectura de archivos) + `crontab -l` +
`ls /etc/supervisor/conf.d/`. Cada veredicto es lectura para que Irving decida en una vuelta futura.

## Método (sitios consultados)

1. `crontab -l` del usuario `meganet` — confirmado: **sin línea `schedule:run`** en este box (mismo
   hallazgo que el precedente #789; el comentario del propio crontab lo dice explícito).
2. `app/Console/Kernel.php::schedule()` — 3 líneas Marketing: `marketing:publish-due` (`everyMinute`),
   `RefreshMetaTokensJob` (`dailyAt('03:45')`), `FetchAllMetricsJob` (`everyFourHours`).
3. `command_configs` (schedule dinámico en BD) — **0 filas** con `command LIKE '%marketing%'` o
   `%campaign%` (verificado por tinker).
4. Listado completo de métodos públicos de cada archivo en `Services/` (incluidas subcarpetas
   `Publishing/`, `Publishing/Drivers/`, `Video/`, `Tts/`, `Tts/Support/`, `Personalization/`,
   `AgentTools/`, `Pilot/`) — no solo grep de keywords, para no perder métodos con semántica de
   ciclo mal nombrados (p. ej. `rescheduleFailedSlots`, `validateAllChannels` no contienen
   literalmente sync/run/dispatch/process pero son barridos por lote).
5. `grep -rn` de cada método/servicio candidato sobre `app/`, `routes/`, `resources/js/` para
   distinguir invocación real de mención en comentario.
6. `Models/Marketing/*.php` — revisados todos los métodos públicos no-relación/no-accessor: **cero
   métodos de acción** (todo es `belongsTo`/`hasMany`/scopes triviales), así que ese árbol no aporta
   candidatos propios — confirma que toda la lógica de ciclo vive en `Modules/Addons/Marketing/Services/`.
7. `/etc/supervisor/conf.d/*.conf` — solo existen `megaisp-deploy-worker.conf` y `megaisp-queue.conf`
   (cola `cobranza,referrals,database,default`). **`megaisp-video-render.conf` no existe** — ni en
   disco ni en `git log --all` ni como stub en `deploy/`/`stubs/` (ver Hallazgo H1 abajo).
8. Jobs del módulo (13 en `Jobs/`): se verificó el dispatch site de cada uno.

---

## A. CONFIRMADOS — arranque real verificado (11 servicios/comandos)

| Servicio/método | Arranque |
|---|---|
| `MarketingPublishDueCommand` (`marketing:publish-due`) | `Kernel.php` `everyMinute()` — despacha `PublishPostJob` por cada `Publication` `queued` con `scheduled_for<=now()` |
| `TokenRefresher::refreshMetaTokens()` | `Kernel.php` `$schedule->job(new RefreshMetaTokensJob())->dailyAt('03:45')` |
| `PostPublisherService::fetchMetricsForPublication()` | `Kernel.php` `$schedule->job(new FetchAllMetricsJob())->everyFourHours()` (lote, hasta 50 publicaciones con métricas vencidas) **+** `FetchMetricsJob` de-a-uno, `PostPublisherService::queueCampaign()` lo agenda con `delay(1h)`/`delay(1día)` tras publicar |
| `PostPublisherService::queueCampaign()` | `PublishingController::queueCampaign` (ruta HTTP real, UI `PublishCampaignView.vue`) |
| `PostPublisherService::publishNow()` | `PublishPostJob::handle()` (worker, cola `default`, consumida por `megaisp-queue.conf`) |
| `PublicationSchedulerService::scheduleForCampaign()` | `CampaignController::activate()` — botón real "Activar campaña" en `MarketingCampaigns.vue`/`MarketingCampaignShow.vue`. **Ver Hallazgo G1 abajo: lo que produce nunca se consume.** |
| `AiAgentService::respondTo()` | `ProcessIncomingMessageJob::handle()`, dispatcheado desde `EvolutionWebhookController` (webhook real de Evolution API) |
| `ScoreLeadJob`/`LeadScoringService::scoreLead()` | 4 sitios de dispatch confirmados: `MarketingLeadController` (×2), `PublicLeadFormController`, `ProcessMetaLeadJob` |
| `CreativeDirectorService::generateMultivariantBriefs()` | `GenerateMultivariantCampaignJob::handle()`, dispatcheado desde `MarketingMultivariantCampaignController` |
| `MonitorCampaignJob` (auto-poll) | Dispatcheado desde `GenerateMultivariantCampaignJob` (`delay(5 min)`) y se **auto-re-dispatcha** cada 3 min mientras el render no termina — patrón de poll-loop, confirmado, no huérfano |
| `PilotCampaignService` (`send`/`markOpened`/`markClicked`/`markConverted`) | Rutas reales (`dry-run`/`send`/`mark-converted`) + `PilotCampaignTrackingController` (pixel/link de tracking) para `markOpened`/`markClicked` |

Nota igual que el hallazgo de contexto del precedente #789: los 3 renglones de `Kernel.php` son
"arranque real verificado en código", condicionado a que el cron de `schedule:run` sí corra (en
este DEV no corre — confirmado en el método, punto 1 — pero ya está documentado en CLAUDE.md como
comportamiento esperado en desarrollo).

## B. FALSO POSITIVO A EVITAR

Ninguno con el patrón estricto de #647 (servicio de ciclo llamado directo dentro del `handle()` de
OTRO comando, invisible para su comando gemelo). Lo más cercano es `GatewayMessageListener` (ver
sección C) pero es un patrón distinto (feature-flag, no comando gemelo).

## C. WIRED PERO DORMIDO A PROPÓSITO (feature flag) — 1, NO huérfano

| Componente | Estado |
|---|---|
| `GatewayMessageListener` (`handleText`/`handleMedia`) | **Registrado y disparándose de verdad** (`Event::listen` en `ModuleServiceProvider::boot()`, eventos reales `WhatsAppTextReceived`/`WhatsAppMediaReceived` del gateway único `WhatsAppAgent`) pero su propio docblock declara 3 modos por `config('marketing_gateway.mode')`: `legacy` (default, no-op total), `shadow` (solo logging, pendiente de aprobación del item #203-B) y `unified` (cutover, item #203-C, **intencionalmente no implementado**). Es el patrón "wired pero dormido por diseño hasta que Irving apruebe la siguiente fase" — no es un huérfano, es una preparación técnica ya declarada como tal en su propio comentario. |

## D. MANUAL POR DISEÑO — sin invocador automático, y no debería tenerlo (2)

| Comando/acción | Por qué es manual a propósito |
|---|---|
| `PilotCampaignService::create()`/`dryRun()` | Piloto de campaña — creación y simulación son acciones deliberadas de un operador antes de enviar de verdad (`dry-run` es literalmente el botón de "probar sin enviar"). |
| `BrollLibraryService::downloadInitialLibrary()`/`downloadForNiche()` | Ya documentado en CLAUDE.md como bootstrap manual por nicho/empresa nueva (mismo criterio que `marketing:download-broll` en el barrido #789, categoría D). |

## E. BACKFILLS DE UNA SOLA CORRIDA

No aplica en este universo — no se encontró ningún comando/servicio de backfill dentro del alcance
de Marketing (a diferencia de `Active/`, que sí tenía 7).

---

## F. HUÉRFANOS — sin arranque, con veredicto (2)

### F1. `PublicationSchedulerService::rescheduleFailedSlots()` — FALTA CONECTAR (y su precondición tampoco existe — ver G1)

- **Sitios buscados:** `grep -rn "rescheduleFailedSlots"` sobre `app/`, `routes/`, `resources/js/` —
  **cero resultados fuera de su propia definición**. No hay ruta, no hay botón, no hay cron, no hay
  `Artisan::call`.
- El método reagenda (máx. 3 reintentos) los `CampaignSchedule` con `status='failed'` — pero nada en
  todo el repo pone jamás una fila de `campaign_schedules` en `status='failed'` (ver Hallazgo G1:
  la tabla completa que alimenta este método nunca se consume, así que tampoco hay quien la marque
  como fallida). Doble huérfano: sin invocador Y sin dato que procesar aunque se invocara.
- **Veredicto: FALTA CONECTAR** (si se retoma el pipeline de `CampaignSchedule`, ver G1) **o SOBRA**
  (si ese pipeline entero fue superado por `Publication`/`marketing:publish-due` y nunca se retiró).
  Misma familia que F5 del precedente #647 (`circuito:retriar-bandeja` vs `circuito:destrabar-bandeja`)
  — dos lecturas igual de válidas sin que el código decida por sí solo. Irving decide.

### F2. `ChannelManager::validateAllChannels()` — FALTA CONECTAR

- **Sitios buscados:** `grep -rn "validateAllChannels"` sobre `app/`, `routes/`, `resources/js/` —
  **cero resultados fuera de su propia definición**. El único endpoint de validación registrado
  (`POST channels/{id}/validate` → `PublishingController::validateChannel`) usa
  `getDriver($channel)->validateCredentials()` **directo**, canal por canal — nunca pasa por el
  método de barrido completo.
- El propio método es exactamente el tipo de barrido que "debería tener un invocador periódico"
  que pide el item: recorre TODOS los canales activos de una empresa y refresca
  `credentials_ready`/`credentials_status_message`/`credentials_validated_at` de cada uno — sin él,
  un canal con credenciales vencidas solo se detecta si un humano entra a la UI y hace clic canal
  por canal.
- **Veredicto: FALTA CONECTAR** (candidato natural a cron de baja frecuencia, p. ej. diario —
  es de solo lectura+actualización de estado, no publica ni gasta cuota de mensajería). Irving decide.

---

## Hallazgos adicionales (fuera de la clasificación A-F, mismo espíritu que los hallazgos D1/H1 de los precedentes #647/#789)

### G1. El pipeline `Campaign` → `CampaignSchedule` está wireado en un extremo y muerto en el otro

`PublicationSchedulerService::scheduleForCampaign()` SÍ tiene un invocador real (botón "Activar
campaña" → `CampaignController::activate()`, confirmado en categoría A) — pero lo que produce
(filas `campaign_schedules` con `status='pending'`) **nunca se lee de vuelta**. Verificado:

- `CampaignSchedule::scopeDueNow()` (el scope explícitamente diseñado para "está pendiente y ya se
  cumplió su hora", `where('status','pending')->where('scheduled_at','<=',now())`) tiene **cero
  consumidores** en todo `app/` fuera de su propia definición.
- `CampaignSchedule::scopePending()` — mismo resultado, cero consumidores.
- Ningún comando, job ni controller hace `CampaignSchedule::where(...)->get()` para efectivamente
  enviar nada.

Esto es distinto al pipeline que sí funciona de punta a punta: `MultivariantCampaign` →
`GeneratedContent` → `PostPublisherService::queueCampaign()` → `Publication` (`status='queued'`) →
`marketing:publish-due` (cron `everyMinute`) → `PublishPostJob` → `PostPublisherService::publishNow()`
— ese SÍ está confirmado A de punta a punta. `Campaign`+`CampaignSchedule` es un **segundo modelo de
campaña, con su propia UI (`MarketingCampaigns.vue`), que crea trabajo (los `campaign_schedules`)
que ningún proceso jamás recoge**. Un usuario puede crear una campaña, aprobarla y "activarla" en la
UI, ver el mensaje "Campaña activada. N envíos programados." — y esos N envíos nunca ocurren.

**No se investigó** (fuera de alcance de un inventario) si esto es: (a) una Fase anterior de
"Publicador Multicanal" que se abandonó a medio construir cuando se adoptó el modelo
`MultivariantCampaign`/`Publication`, o (b) un pipeline paralelo pensado para otro caso de uso
(campañas simples de un solo canal vs. multivariantes) que sigue pendiente de que alguien construya
el consumidor. Cualquiera de las dos lecturas explica también F1 (`rescheduleFailedSlots` sin datos
que procesar). **Irving decide** si se conecta (un job/comando que lea `CampaignSchedule::dueNow()`
y despache el envío real) o se retira la UI de "Activar campaña" por engañosa mientras tanto.

### H1. La cola `video-render` no tiene worker en este DEV — `RenderVideoJob` se queda pending para siempre aquí

CLAUDE.md ya documenta la existencia esperada de
`/etc/supervisor/conf.d/megaisp-video-render.conf` ("video-render queue (FFmpeg jobs, timeout=660)"
— "Sin el worker `video-render`, los renders quedan en `pending` para siempre"). Verificado en este
box: **ese archivo no existe** (`ls /etc/supervisor/conf.d/` solo lista
`megaisp-deploy-worker.conf` y `megaisp-queue.conf`), tampoco existe en el historial de git
(`git log --all -- "*video-render*"` vacío) ni como stub versionado en `deploy/`/`stubs/` (a
diferencia de CobranzaBlaster, que sí tiene `stubs/supervisor-ami-listener.conf` para su daemon).
`megaisp-queue.conf` consume `--queue=cobranza,referrals,database,default` — **`video-render` no
está en esa lista**.

`RenderVideoJob` tiene 3 sitios de dispatch reales y confirmados (`GenerateMultivariantCampaignJob`,
`MarketingGeneratedContentController`, `MarketingMultivariantCampaignController`, los 3 con
`->onQueue('video-render')`) — el despacho SÍ es real (categoría A por invocación), pero en **este
DEV concreto** cualquier video encolado se queda en `pending` indefinidamente porque no hay proceso
que la drene. Esto confirma, con evidencia directa de infraestructura, la advertencia que CLAUDE.md
ya traía escrita — no es un hallazgo nuevo de comportamiento, pero sí la primera vez que se verifica
que el archivo de configuración en sí nunca llegó a crearse/commitearse. Corrección propuesta para
un item aparte (fuera de alcance de este inventario, que es solo lectura): crear
`stubs/supervisor-video-render.conf` (o agregar `video-render` a la lista de colas de
`megaisp-queue.conf`) y documentar el paso de instalación, mismo patrón que
`stubs/supervisor-ami-listener.conf` de CobranzaBlaster.

---

## Resumen

| Categoría | Cantidad | Servicios/comandos |
|---|---|---|
| A. Confirmados (arranque real) | 11 | ver tabla A |
| B. Falso positivo descartado | 0 | ninguno con el patrón estricto |
| C. Wired pero dormido por feature-flag (no huérfano) | 1 | `GatewayMessageListener` (item #203, fases B/C pendientes de aprobación) |
| D. Manual por diseño (intencional, correcto así) | 2 | `PilotCampaignService::create/dryRun`, `BrollLibraryService::download*` |
| E. Backfill de una sola corrida | 0 | no aplica en este universo |
| F. Huérfanos con veredicto | 2 | `PublicationSchedulerService::rescheduleFailedSlots()` (falta conectar/sobra), `ChannelManager::validateAllChannels()` (falta conectar) |
| **Hallazgos adicionales** | 2 | **G1** pipeline `Campaign`→`CampaignSchedule` wireado en un extremo, muerto en el otro (relacionado con F1); **H1** cola `video-render` sin worker en este DEV (archivo de supervisor nunca existió) |

FUERA DE ALCANCE DE ESTA PASADA (mismo mandato que #647/#789): no se borró, desconectó, corrigió ni
ejecutó nada — tampoco se disparó ningún mensaje de WhatsApp ni render de video real. Los 2
huérfanos con veredicto y los 2 hallazgos (G1, H1) quedan para que Irving decida item por item
(conectar, retirar la UI engañosa, crear el worker faltante, o dejar así documentado) en una vuelta
futura.
