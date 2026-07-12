# Módulo War Room

> Dashboard ejecutivo para juntas operativas con control de tiempo, KPIs por área, insights IA y planes de ataque en vivo. `app/Modules/Addons/WarRoom/` · slug `addon-warroom` · addon activo.

## 0. En simple
Es la sala de juntas digital de la dirección: mientras se reúnen, la pantalla muestra en vivo cómo va el negocio (ventas, cobros, red, tickets), controla el tiempo de cada tema y convierte lo acordado en tareas asignadas a alguien, con recordatorio automático por WhatsApp.

## 1. Qué es
Módulo que combina un dashboard de KPIs multi-área (resumen, finanzas, operaciones, ventas, red, marketing, talento) con un moderador de juntas: agenda por secciones cronometradas, insights generados por IA (o por reglas si la IA falla), desempeño de colaboradores y una bitácora de "planes de ataque" (action items) que se acuerdan durante la junta.

## 2. Para qué sirve
Le sirve a la dirección/gerencia para llevar la junta operativa semanal sin perder el hilo: ver de un vistazo el estado del negocio por área, no pasarse de tiempo en ningún punto de la agenda, recibir sugerencias automáticas de qué decir/priorizar y salir de la junta con tareas ya asignadas, con deadline y notificadas por WhatsApp a cada responsable — sin depender de que alguien tome notas manuales y luego reparta pendientes.

## 3. Cómo funciona

**Piezas clave — 8 tablas propias `warroom_*`:**
- `warroom_meetings` — la junta: tipo (`ordinaria`/`acta`), estado (`in_progress`/`paused`/`ended`), moderador, sección actual, duración planeada vs. real, `settings` (JSON: sugerencias IA, sonido, envío de minutas por WhatsApp).
- `warroom_meeting_sections` — puntos de agenda de una junta (orden, tiempo planeado/real en segundos, presentador, estado `pending`/`in_progress`/`completed`/`skipped`).
- `warroom_section_templates` — plantillas reutilizables de agenda (clave + minutos por defecto) que arman las secciones de una junta nueva si no se especifican a mano.
- `warroom_meeting_attendees` — asistentes convocados, con `present`/`confirmed`.
- `warroom_meeting_notes` — notas libres capturadas durante una sección (con autor).
- `warroom_action_items` — "planes de ataque" acordados: descripción, responsable, prioridad (`critico`/`alto`/`medio`/`oportunidad`/`estrategico`), deadline, estado, sección de origen, y `linked_task_id` (la tarea que generó en el sistema de tickets).
- `warroom_insights_cache` — insights de IA/reglas cacheados por vista+período.
- `warroom_kpi_snapshots` — snapshot diario de todos los KPIs por período (histórico, no se recalcula en vivo).

**Controllers / Servicios:**
- `KpiController` — motor de los 7 tableros (`resumen`, `finanzas`, `operaciones`, `ventas`, `red`, `marketing`, `talento`). Cada vista hace queries directas (`DB::table`) contra tablas de OTROS módulos (`client_invoices`, `tasks`, `clients`, `referral_commissions`, `referrals`, `olt_onus`, `olts`, `olt_pon_ports`, `mikrotik_client_ppoes`, `marketing_publications/campaigns/leads/channels`, `talento_*`), comparando período actual vs. anterior y armando series diarias/semanales para gráficas. El método `raw()` (sin envolver en JSON) lo reusa el comando de snapshot.
- `InsightsController` / `InsightsService` — arma un resumen de KPIs por vista+período, se lo manda a la API de Claude pidiendo 3 insights (`positivo`/`atencion`/`oportunidad`) en JSON; si la IA falla (sin API key, timeout, respuesta inválida) cae a un generador de insights **basado en reglas fijas** por vista. Resultado cacheado en `warroom_insights_cache`.
- `MeetingController` — ciclo de vida de la junta: `start` (crea junta + secciones desde plantillas o request + asistentes), `nextSection`/`previousSection` (avanza/retrocede agenda, mide tiempo real por sección), `pause`/`resume`, `end` (cierra secciones pendientes como `skipped`, dispara `SendMeetingMinutesJob` si está habilitado, arma resumen de la junta), `getSuggestion` (delega a `ModeratorAi`).
- `ModeratorAi` — cuando una sección lleva ≥80% de su tiempo planeado, sugiere al moderador una acción concreta (vía Claude, cacheada 60s por bucket de 5% de avance; si la IA falla usa un mensaje de respaldo fijo).
- `MeetingHistoryController` / `MeetingNoteController` — listado/detalle de juntas ya cerradas (con secciones, notas, action items, asistentes) y CRUD de notas de una junta activa.
- `ActionItemController` + `ActionItemObserver` — al crear un action item, el observer automáticamente (a) crea una `Task` (ticket del sistema, prefijo "War Room:", `source='warroom'`) enlazada vía `linked_task_id`, y (b) si el responsable tiene teléfono, le manda un WhatsApp con la tarea, prioridad y deadline.
- `DesempenoController` / `DesempenoSerieController` — panel de desempeño de colaboradores de Talento dentro del War Room; **no calcula su propio score**, reusa `App\Modules\Addons\Talento\Services\CompositeScoreService` sobre métricas agregadas de OTs, asistencias, inspecciones de caja, bonos de salud de red, penalizaciones, desvíos de ruta, incidencias, activaciones OLT, extensiones de turno y evaluaciones prácticas (todas de tablas `talento_*`).
- `RefreshWarRoomCommand` (`warroom:refresh`) — job de mantenimiento: regenera insights de todas las vistas y/o escribe el snapshot diario de KPIs. Programado en `Kernel.php` a las 23:55 diario con `--skip-insights` (solo snapshot, sin gastar llamadas a IA).

**Flujo principal:** el moderador abre `/warroom`, ve el dashboard con KPIs en vivo (o el snapshot más reciente) e insights IA por área; al iniciar una junta se arma la agenda (secciones con tiempo asignado), el sistema cronometra cada punto y sugiere avanzar cuando se acerca al límite; durante la junta se capturan notas y "planes de ataque" (action items) que quedan asignados a un responsable con prioridad y fecha límite; cada action item genera automáticamente un ticket (`Task`) y una notificación por WhatsApp; al cerrar la junta se generan las minutas y se reparten por WhatsApp a cada asistente con sus tareas.

**Rutas:** `routes.php` del módulo, todo bajo `/warroom` con `middleware(['web','auth'])` + `permission:warroom.view` para el grueso de endpoints (algunas acciones de escritura exigen además `$this->authorize()` con un permiso más específico dentro del controller).

**Permisos:** `warroom.view`, `warroom.meeting.create`, `warroom.meeting.moderate`, `warroom.meeting.delete`, `warroom.action_items.assign`, `warroom.insights.regenerate`, `warroom.snapshots.regenerate` (Spatie, declarados en `module.json`).

## 4. Qué EXPONE / qué CONSUME

**Expone**
- Vista `/warroom` (Blade `views/warroom/index.blade.php` → componente Vue `warroom-dashboard`, registrado en `resources/js/app.js`).
- API interna bajo `/warroom/api/*`: KPIs por vista/período, insights (lectura + regenerar), junta activa, historial de juntas + detalle, notas de junta, ciclo de vida de junta (start/next-section/previous-section/pause/resume/end/suggestion), desempeño + serie de desempeño, CRUD de action items. Lista formal con `scope`/`permission` (subset) en `module.json` → `api_endpoints`.
- Menú "War Room" en el sidebar primario (`sidebar.location=primary`) y tarjeta en `admin_cards`, ambos gateados por `warroom.view`.
- Bloque de conocimiento/intents para el asistente IA (`module.json` → `ai`), con `warroom.snapshots.regenerate` e `warroom.insights.regenerate` marcadas como acciones sensibles.
- Comando de consola `warroom:refresh` (snapshot + insights), programado diario en `Kernel.php`.
- Como efecto lateral de un action item: una `Task` nueva en el módulo de tickets del sistema (`source='warroom'`).

**Consume**
- Datos de negocio de otros módulos, en **lectura directa vía `DB::table`** (no a través de sus repositorios): `client_invoices`, `clients`, `tasks`, `activity_log`, `referral_commissions`, `referrals` (Finanzas/CRM/Embajadores), `olt_onus`, `olts`, `olt_pon_ports`, `mikrotik_client_ppoes` (Red/MikroTik/OLT), `marketing_publications`, `marketing_campaigns`, `marketing_leads`, `marketing_channels` (Marketing), y numerosas tablas `talento_*` (Talento) — con `Schema::hasTable()` como guarda para instancias donde Talento no esté instalado.
- `App\Modules\Addons\Talento\Services\CompositeScoreService` — score de desempeño reusado tal cual, sin duplicar lógica.
- `App\Modules\Addons\Marketing\Services\EvolutionApiService` — envío de WhatsApp (minutas de junta y notificación de action items) llamando directo al servicio de Marketing, **no** a través de la capa `WhatsAppGateway`/eventos que `CLAUDE.md` documenta como el gateway único recomendado.
- API de Claude (`https://api.anthropic.com/v1/messages`), directo por HTTP con `CLAUDE_API_KEY`/`CLAUDE_MODEL` de `.env` — no pasa por el módulo IA / Integration Hub central; si falla, ambos puntos de uso (insights de KPIs y sugerencias del moderador) caen a lógica de respaldo (reglas fijas / mensaje genérico) en vez de romper.
- Sistema de permisos Spatie (`middleware('permission:...')` + `$this->authorize()`) — no implementa autorización propia.

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
