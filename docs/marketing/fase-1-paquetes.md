# Fase 1 — Rediseño ejecutivo de las pantallas de Marketing · PAQUETES

Supervisor: `ingeniero-supervisor` · Dueño: **Henry** · Fecha: 2026-11-12
Worktree: `/var/www/megaisp-henry-marketing` · Rama: `feat/marketing-360-equipo` (base de la fase = su HEAD actual, `a0af9d95b`, ya juntada con main; Fase 0 publicada como V1.87).
Nadie commitea. Nadie migra ni escribe en la base `megaisp`. Nadie trabaja en `/var/www/megaisp`.

> **Estado: EN CONSTRUCCIÓN — Henry decidió el 2026-11-13: F1-1 SÍ (con condiciones, §2.6.1), F1-2 SÍ (solo los 13
> permisos, solo super-administrator y DESARROLLADOR), F1-3 SÍ (solo funciones de Marketing; el reporte lista cuáles se
> quitaron).** F1-4 a F1-9 con su valor por defecto. Al cierre: pruebas de ataque en navegador pendientes de la Fase 0.


> ## ⚠️ CAMBIO DE ALCANCE — Henry, 2026-11-13 (manda sobre el resto de este documento)
> **A. Solo Meganet.** Sin selector de empresa ni alta de marcas en pantalla: el selector y la pantalla «Empresas y
> equipo» se OCULTAN (no se borran tablas ni lógica de Fase 0; la empresa activa siempre resuelve a 1). No se
> rediseña nada de multimarca.
> **B. Solo Henry e Irving entran, como admin con todas las facultades.** Regla de acceso (backend, en el middleware
> del módulo): entra quien tiene sesión de LM **y** fila activa en `marketing_empresa_usuario` con `rol = 'admin'` en la
> empresa 1 **y** usuario activo. Cualquier otro → 403 (también super-administrador sin esa fila, y filas `marketing` o
> `ventas`). Públicas por diseño (formulario por slug, webhooks con firma) siguen igual.
> **C. Fijar el equipo:** comando `php artisan marketing:equipo-fijar <login_user> <login_user> [--aplicar]` (por
> `login_user`, nunca ids; sin logins escritos en código, pruebas ni docs): sin `--aplicar` muestra qué quedaría; con
> `--aplicar` deja SOLO esos usuarios como `admin` de la empresa 1 (borra las demás filas de la empresa 1 y lo anota en
> `activity_log`) y les da con `givePermissionTo` (aditivo) los permisos `marketing.*` del módulo. En dev lo corre la
> sesión principal al integrar; en prod lo corre Irving con bitácora previa.
> **D. Se tira / se simplifica:** `MarcaNuevaVeSusRegistrosTest`, `SlugFormularioGlobalTest`, el arreglo de
> `company_id = 1` fijo en los 6 controladores (con una sola empresa no afecta; queda anotado como deuda), cualquier
> trabajo de UI de selector/empresas, y la prueba de navegador «URL de otra marca» (no habrá otra marca; se sustituye
> por «otro usuario de LM escribe la URL → 403»). La matriz de roles de Fase 0 (`ventas`/`marketing`) se ajusta a la
> regla B; cada prueba cambiada se lista con su razón.
> **E. Se queda:** F1-1 (sin `check_route_permission`, con `auth` + negado por defecto), F1-2 (13 permisos), F1-3
> (retiradas), Dashboard/KPIs, campañas activas, barra de navegación, Brand Kit sin llaves, rediseño ejecutivo.

---

## 0. Hechos encontrados en el código (condicionan el plan)

1. **El `company_id = 1` fijo** está en 6 controladores + 1 servicio (35 líneas), más **`forCompany(1)`** (5 líneas) y
   el `RenderVideoJob::dispatch(..., 1)` (2 líneas). Las **altas ya caen en la marca correcta** (el trait
   `PerteneceAEmpresa` de la Fase 0 pisa el `company_id`), pero las **lecturas** filtran `company_id = 1` **y** el scope
   → una marca nueva no ve lo que crea. Además `GenerateMultivariantCampaignJob` busca nichos por
   `$campaign->company_id` → para otra marca no encuentra nichos (catálogo de Meganet) y sale vacío.
2. **Permisos que NO existen en `permissions` (dev = copia de prod del 10-nov)** y que los controladores exigen con
   `permission:`: `marketing.leads.{view,create,update,delete,assign,score}`, `marketing.forms.{view,create,update,delete}`
   y `marketing.brand_kit.configure`. Spatie no tiene atajo para admins (no hay `Gate::before` propio) → **la API de
   Leads, la de Formularios y la del Brand Kit responden 403 a TODOS**, también a super-administrador. Las pruebas de la
   Fase 0 no lo vieron porque **siembran esos permisos dentro de la prueba** (`Fase0/Qa/Concerns/EscenarioEmpresas.php:75-82`).
   El menú lateral de Marketing también pregunta por permisos inexistentes (`marketing.leads.view`,
   `marketing.conversations.view`, `marketing.forms.*`, `marketing.campaigns.view`, `marketing.templates.manage`,
   `marketing.brand_kit.configure`) → esas entradas no se ven para nadie.
3. **Lo «legado» no tiene base real**: el modelo `Modules\Addons\Marketing\Models\Campaign` escribe `title`,
   `target_zone`, `channel`, `daily_limit`, `approved_*` en `marketing_campaigns`, que tiene `name`, `type`, `budget`,
   `spent`…; usa estados (`pending_approval`, `approved`) que el enum no tiene; y `campaign_contents`,
   `campaign_schedules` y `marketing_templates` **no existen**. Todo el flujo «Campañas legado / Plantillas / Generar
   copy-imagen / leads-legacy / stats» da 500 o no puede funcionar. El Dashboard actual (`MarketingDashboard.vue`) depende
   de él en 3 de sus 4 pestañas (Campañas, Leads legado, Plantillas) y su botón «Nueva campaña» llama a
   `/marketing/campaigns/store` (500).
4. **El menú lateral NO sale de `module.json`**: `addon-marketing` está en `$sidebarHardcoded`
   (`app/Modules/Core/Layout/views/sidebar.blade.php:203`) y se pinta desde
   `resources/views/module-sidebar/marketing.blade.php` (fuera de lo autorizado). Reordenar `menu[]` no cambia nada
   visible. → Fase 1 agrega una **barra de navegación dentro del módulo** que lee el orden de `module.json` (F1-4).
5. **D5 (`check_route_permission`)**: `CheckRoutePermission` deja pasar sin mapeo solo a `super-administrator`,
   `DESARROLLADOR`, `Super Administrador` y `Administrador`; para cualquier otro busca la URL en
   `config/route_permission.php`, que no tiene ninguna entrada `/marketing/**`. Hay una salida **sin tocar archivos fuera
   de lo autorizado**: quitar ese middleware de los 2 grupos que lo llevan en `routes.php` del módulo (vistas Blade y API de
   Publicación) y dejar el acceso a la capa de la Fase 0 (`marketing.empresa` + `marketing.rol`) más los permisos Spatie
   de cada acción. Precedente en el repo: `MonitoreoOficial/routes.php:6-7` («No se añade a config/route_permission.php»).
   Cambia **quién** entra → es decisión de Henry (F1-1). Descartado: inyectar entradas a `config('route_permission')` en
   tiempo de ejecución desde el ServiceProvider (no toca el archivo, pero cambia la configuración global de autorización
   a escondidas).
6. **Slug de formularios**: el índice único es `(company_id, slug)` pero el formulario público
   (`PublicLeadFormController`) busca **solo por slug** → con dos marcas, un mismo slug mandaría los leads a la marca
   equivocada. Se corrige con validación de slug único **entre todas las marcas** (sin tocar el índice).
7. **Lo que entra sin sesión** (webhook de WhatsApp/Evolution, webhook de Meta Ads, `ProcessMetaLeadJob`, bot) crea con
   `company_id` = 1 por defecto. En Fase 1 **no se cambia** (una sola línea de WhatsApp y una cuenta Meta, de Meganet). Una
   marca nueva recibe leads solo por sus formularios o captura manual (F1-8).
8. Componentes Vue viejos que el Dashboard nuevo deja sin uso: `MarketingCampaigns.vue`, `MarketingLeads.vue`,
   `MarketingContentGenerator.vue`, `MarketingCampaignShow.vue`. Están registrados en `app.js`; borrarlos exige tocar
   `app.js` más allá de la línea autorizada (ya usada en Fase 0) → se quedan intactos (F1-9).
9. Fase 1 **no usa IA** (cero claves nuevas en `IA/config/modulos.php`) y **no crea ninguna marca** (ni seeders ni
   migraciones que inserten en `marketing_empresas`).
10. Línea base de fallas de la suite completa: **141 nombres** (`Clase::prueba`), copiada a
    `docs/marketing/fase-1-linea-base-fallas.txt` (original en el scratchpad de la sesión, `…/scratchpad/suite/fase2.fallas`).

---

## 1. Resumen de lo que se construye

- **Dashboard `/marketing` nuevo**: 4 KPIs (leads del mes, conversión, gasto MXN, costo por lead) con variación contra el
  mes anterior, embudo de leads del mes y campañas activas. 2 endpoints nuevos filtrados por la empresa activa.
- **Rediseño ejecutivo** (kit `mkt-*`) de las 17 pantallas restantes, mismo funcionamiento, + barra de navegación del
  módulo (orden según `module.json`) en todas.
- **Quitar el `company_id = 1` fijo**: datos de negocio por empresa activa; catálogos compartidos de Meganet por
  `Empresa::PRINCIPAL_ID` (criterio §2.3). Pruebas A/B.
- **Defectos que ya existían**: permisos faltantes (Leads/Formularios/Brand Kit dejan de dar 403) [F1-2]; rutas legado
  sin tablas → respuesta controlada 410 y fuera de la UI [F1-3]; slug global de formularios.
- **Acceso a las pantallas viejas sin `config/route_permission.php`** [F1-1].
- **Pruebas de ataque en navegador** pendientes de la Fase 0 (doble clic, recargar a mitad, URL de otra marca).

---

## 2. CONTRATO COMPARTIDO

### 2.1 Reglas de trabajo (las cumplen los tres agentes)

- **Lugares autorizados** fuera de `app/Modules/Addons/Marketing/**`: `resources/js/components/module/marketing/**`,
  una línea en `resources/js/app.js` (ya usada en Fase 0: **en Fase 1 nadie toca `app.js`**), `app/Models/Marketing/**`
  y las claves `marketing.*` de `app/Modules/Addons/IA/config/modulos.php` (Fase 1 no las necesita). Más
  `tests/Feature/Marketing/**`, `docs/marketing/**`, `docs/bitacora/**`. **Nada más**: ni sidebar, ni layout, ni CSS
  global, ni `config/`, ni `app/Http/Kernel.php`, ni otros módulos.
- **Henry prohibió dar de alta una segunda marca en producción hasta cerrar la Fase 1**: ningún seeder, migración ni
  comando que inserte en `marketing_empresas`. La empresa de prueba B solo existe **dentro de las pruebas** (transacción)
  y en `megaisp_test` para la vista previa (§2.9), y se borra al cierre.
- **Bases permitidas**: `megaisp_test`, `megaisp_fresh_test`, `megaisp_dryrun`. **Prohibido escribir en `megaisp`**
  (solo lectura, y sin volcar `marketing_settings`, `.env` ni `api_integrations`: no se imprimen secretos).
- **Pruebas por nombre de archivo**, nunca por directorio: `php vendor/bin/phpunit tests/Feature/Marketing/Fase1/…/XTest.php`.
  Base `Tests\Feature\Marketing\MarketingTestCase` (DatabaseTransactions). Nunca `Tests\TestCase` ni `RefreshDatabase`.
  IA, Meta, Evolution, correo: siempre simulados (`Http::fake`, `Queue::fake`, `Bus::fake`, `Mail::fake`).
  **Las pruebas de Fase 1 NO siembran permisos** que deban existir por migración (eso tapó el defecto de §0.2): los
  usuarios de prueba reciben roles (`super-administrator` / `DESARROLLADOR`) o se les da (`givePermissionTo`) un permiso
  **que ya existe**; si no existe, la prueba debe fallar.
- **Nada de commits.** Nada de `migrate` sin `--path` del módulo. Nada de `config:cache`.
- **Ninguna forma JSON existente cambia** (salvo las rutas retiradas de §2.5). El frontend consume los endpoints viejos
  tal como están hoy; si necesita otra forma, lo pide al supervisor, no lo inventa.
- Textos visibles en español. Dinero `decimal(12,2)` en MXN, nunca `float` (ni en PHP ni al sumar en SQL→PHP).

### 2.2 Regla de avance contra atorones (instrucción de Henry: ningún agente más de 20 minutos sin avanzar)

1. Cada agente escribe **su** archivo `docs/marketing/fase-1-avance-<agente>.md` (`backend`, `frontend`, `qa`) y agrega
   **una línea cada ~10 minutos**: `HH:MM — <lo que hice> · sigue: <lo siguiente>`. Al arrancar, primera línea con la hora.
2. **Ningún trabajo en segundo plano sin límite.** Todo comando largo va en primer plano, con salida a archivo y tope:
   ```bash
   S=/tmp/claude-1000/-var-www-megaisp/78fb3bd1-90ba-47f4-819f-1aca73a1fd54/scratchpad/fase1/<agente>; mkdir -p $S
   timeout 900 env NODE_OPTIONS=--max-old-space-size=4096 npm run dev > $S/npm-dev.log 2>&1; echo "exit $?" >> $S/npm-dev.log
   timeout 600 php vendor/bin/phpunit tests/Feature/Marketing/Fase1/Qa/XTest.php > $S/XTest.log 2>&1; echo "exit $?" >> $S/XTest.log
   ```
   Las **migraciones nunca llevan `timeout`** (cortarlas deja la base a medias): van con `flock -w 600 /tmp/megaisp-suite.lock …`;
   si no obtiene el candado en 10 min, se anota en el avance y se sigue con otra tarea. Se revisa siempre la cola del log
   (`tail -40`) y el `exit`.
3. Si algo se lanzó en segundo plano (p. ej. `artisan serve`), se verifica que responde (`curl -s -o /dev/null -w '%{http_code}'`)
   antes de usarlo y se mata al terminar (`kill`). **Nunca** se espera «a que avise» un proceso que no se comprobó vivo.
4. Si el último renglón del avance de un agente tiene **más de 20 minutos**, la sesión principal lo detiene y retoma su
   paquete desde el archivo de avance.
5. **QA no corre la suite completa**: la corre la sesión principal al cierre (§7, criterio 15). QA corre solo sus archivos
   y los de Fase 0 que tenga que ajustar, uno por uno.

### 2.3 Datos por marca vs. catálogo compartido de Meganet (criterio D3, por escrito)

| Tipo | Modelos / tablas | Cómo se lee | Cómo se escribe |
|---|---|---|---|
| **Datos de negocio de cada marca** (llevan el scope de Fase 0) | `Lead`, `Campaign` (ambos), `Conversation`, `GeneratedContent`, `LeadForm`, `MultivariantCampaign`, `PilotCampaign`, `Pipeline`, `Publication`, `PublicationChannel`, `ContentTemplate` | **sin** `where('company_id', …)` ni `forCompany(…)`: el scope filtra por la empresa activa | `company_id = app(EmpresaActiva::class)->id()` explícito en el `create` (el trait lo garantiza igual) |
| **Catálogo / configuración compartida de Meganet** (sin scope) | `VideoTemplate`, `MarketingNiche`, `Asset` (B-roll, música, voz cacheada), `Channel`, `LeadSource`, `Setting` (Brand Kit y llaves), `AiAgentConfig` | `where('company_id', Empresa::PRINCIPAL_ID)` — **nunca el literal `1`** | solo con Meganet activa (`marketing.principal`, ya en las rutas MP) |
| **Hijos sin `company_id`** | `Message`, `LeadActivity`, `Attribution`, `LeadPipeline`, `PipelineStage`, `PublicationLog`, `PilotCampaignSend` | siempre a través del padre con scope | igual que hoy |

Reglas derivadas:
- El parámetro `$companyId` de los servicios del **motor de video** (`RenderVideoJob`, `VideoTemplateRenderer`,
  `CreativeDirectorService`, `BrollLibraryService`, `AssetLibraryService`, `TtsService`, `FFmpegService`) significa
  «dueño del catálogo» → se le pasa **`Empresa::PRINCIPAL_ID`**. El `company_id` del `GeneratedContent` resultante es el
  de la marca que lo pidió. Consecuencia aceptada (F1-7): un video de otra marca sale con el Brand Kit de Meganet.
- Servicios de **publicación** (`ChannelManager`, `SmartRouter`, drivers): `$companyId` = empresa activa (los canales y sus
  tokens son de cada marca).
- Los caminos sin sesión (webhooks, bot, jobs de entrada) **no cambian** (§0.7).

### 2.4 Rutas NUEVAS (3)

Se agregan **al final** del grupo API `['web','auth']` con prefijo `api/marketing` en `routes.php` (después de
`pilot-campaigns`), para no mover el orden de las 121 existentes (R8 de Fase 0). Total de rutas de Marketing:
**131 → 134**.

| # | Método | URL | Nombre | Middleware adicional | Acción |
|---|---|---|---|---|---|
| 1 | GET | `/api/marketing/dashboard/resumen` | `api.marketing.dashboard.resumen` | `$M` (`marketing.empresa`, `marketing.rol:admin,marketing`) | `DashboardController@resumen` |
| 2 | GET | `/api/marketing/dashboard/campanas-activas` | `api.marketing.dashboard.campanas-activas` | `$M` | `DashboardController@campanasActivas` |
| 3 | GET | `/api/marketing/navegacion` | `api.marketing.navegacion` | `marketing.empresa` (cualquier rol, incluido ventas) | `NavegacionController@index` |

Fechas ISO-8601 con zona `America/Mexico_City`. Dinero como **cadena con 2 decimales** (`"12500.00"`), nunca número
flotante; el frontend solo lo convierte para formatear con `formatoMXN`.

**1) `GET /api/marketing/dashboard/resumen?mes=2026-11`** → 200
- `mes` opcional, `^\d{4}-(0[1-9]|1[0-2])$`, entre `2020-01` y el mes actual; si no, **422** formato Laravel
  (`errors.mes`). Sin `mes` = mes actual en `America/Mexico_City`. Arrays, `<script>`, SQL → 422.
```json
{
  "ok": true,
  "empresa_id": 1,
  "periodo": { "mes": "2026-11", "desde": "2026-11-01T00:00:00-06:00", "hasta": "2026-11-30T23:59:59-06:00",
               "mes_anterior": "2026-10" },
  "kpis": {
    "leads_mes":          { "valor": 42,        "anterior": 35,       "variacion": 20.0 },
    "conversion":         { "valor": 11.9,      "anterior": 8.6,      "variacion": 38.4, "ganados": 5 },
    "gasto_mxn":          { "valor": "12500.00", "anterior": null,    "variacion": null,
                            "fuente": "campanas", "campanas_otra_moneda": 0 },
    "costo_por_lead_mxn": { "valor": "297.62",  "anterior": null,     "variacion": null }
  },
  "embudo": {
    "etapas": [
      { "clave": "new",         "etiqueta": "Nuevo",          "total": 20 },
      { "clave": "contacted",   "etiqueta": "Contactado",     "total": 9 },
      { "clave": "qualified",   "etiqueta": "Calificado",     "total": 6 },
      { "clave": "negotiating", "etiqueta": "En negociación", "total": 2 },
      { "clave": "won",         "etiqueta": "Ganado",         "total": 5 }
    ],
    "perdidos": 0, "archivados": 0, "total": 42
  },
  "generado_at": "2026-11-12T19:00:00-06:00"
}
```
Definiciones (todas sobre la empresa activa, sin filas borradas):
- `leads_mes` = leads con `captured_at` dentro del periodo (todos los estados).
- `conversion` = `ganados` / `leads_mes` × 100, 1 decimal; `ganados` = leads del periodo con `status = won`.
  `leads_mes = 0` → `valor: null`.
- `gasto_mxn` = suma de `spent` de las `marketing_campaigns` con `currency = 'MXN'`, `status` ∈ {`active`,`paused`,`finished`}
  y vigencia que toca el periodo (`start_date <= hasta` y (`end_date` nula o `>= desde`)). Ninguna campaña que califique →
  `valor: null` (en pantalla «—», nunca un 0 inventado). Las que califican pero con otra moneda **no se suman** y se
  cuentan en `campanas_otra_moneda`. Mientras no exista la lectura de Meta (Fase 5) `fuente = "campanas"` (F1-5).
- `costo_por_lead_mxn` = `gasto_mxn` / `leads_mes`, redondeo half-up a 2 decimales; null si cualquiera de los dos es null/0.
  **Aritmética sin float**: centavos enteros o `bcmath`.
- `anterior` = el mismo indicador del mes anterior; `variacion` = (actual − anterior) / anterior × 100, 1 decimal; null si
  `anterior` es null o 0.
- `embudo` = leads del periodo agrupados por `status`; `lost` → `perdidos`, `archived` → `archivados`. Siempre las 5 etapas,
  aunque estén en 0.

**2) `GET /api/marketing/dashboard/campanas-activas?limite=5`** → 200
- `limite` opcional entero 1–20 (default 5); fuera de rango o no entero → 422.
```json
{
  "ok": true,
  "total": 1,
  "data": [
    { "id": 7, "nombre": "Fibra 500 Megas noviembre", "tipo": "meta_ads", "tipo_etiqueta": "Meta Ads",
      "estado": "active", "moneda": "MXN", "moneda_distinta": false,
      "presupuesto": "5000.00", "gastado": "1200.50", "avance_presupuesto": 24.0,
      "inicio": "2026-11-01", "fin": "2026-11-30", "leads": 12 }
  ]
}
```
- Campañas de la empresa activa con `status = active`, ordenadas por `start_date` desc. `total` = todas las activas.
- `leads` = leads distintos en `marketing_attributions` de esa campaña. `avance_presupuesto` = `spent`/`budget`×100,
  1 decimal; null si `budget = 0`. `moneda_distinta = true` si `currency ≠ MXN` (el monto se muestra con su moneda, no se
  convierte).
- Etiquetas de `tipo`: `meta_ads` Meta Ads · `google_ads` Google Ads · `organic` Orgánica · `email_blast` Correo masivo ·
  `sms_blast` SMS masivo · `voice_blast` Llamadas · `mixed` Mixta · `manual` Manual.
- Hoy no hay ninguna fila en `marketing_campaigns` (las crea el constructor de la Fase 2) → `{"ok":true,"total":0,"data":[]}`.

**3) `GET /api/marketing/navegacion`** → 200
```json
{ "ok": true, "rol": "marketing",
  "items": [
    { "clave": "panel", "etiqueta": "Panel", "url": "/marketing", "icono": "insights", "sub": [] },
    { "clave": "video", "etiqueta": "Video", "url": "/marketing/video-templates", "icono": "movie",
      "sub": [ { "clave": "video-plantillas", "etiqueta": "Plantillas", "url": "/marketing/video-templates" },
               { "clave": "video-generar", "etiqueta": "Generar", "url": "/marketing/video-generator" } ] }
  ] }
```
- Sale de `module.json` → `menu[0].children` **en su orden** (así reordenar el menú = editar `module.json`).
- Un item (y cada `sub`) se incluye si el rol de la empresa activa está en su `roles_empresa` **y** (no trae `permission`
  **o** `auth()->user()->can(permission)`). Sin empresa → 403 `sin_empresa` (lo da el middleware).
- Solo devuelve `clave, etiqueta, url, icono, sub`. Nada de permisos ni datos del usuario.

### 2.5 Rutas RETIRADAS (defectos con tablas inexistentes) — `[F1-3]`

**No se borra ninguna ruta** (método, URI, nombre y acción quedan iguales; el snapshot de Fase 0 sigue valiendo). Cambia
solo el **cuerpo** del método:

1. Se conserva lo que el método hacía **antes** de tocar tablas inexistentes: la validación (`validate`, reglas `exists`
   acotadas de Fase 0) y el `findOrFail` sobre modelos con scope (id de otra marca → **404**, como hoy).
2. Después responde **410**:
   `{"ok":false,"error":"retirada","mensaje":"Esta función se retiró porque dependía de tablas que ya no existen. Las campañas y plantillas se rehacen en la Fase 2."}`
3. Si la tabla no existe ni para buscar (`marketing_templates`), responde 410 directo.

| Ruta(s) | Qué queda |
|---|---|
| `GET marketing/stats` | 410 |
| `GET marketing/templates`, `POST marketing/templates/store` (validación → 410), `PUT/DELETE marketing/templates/{id}` | 410 |
| `POST marketing/campaigns/table` | 410 |
| `POST marketing/campaigns/store` | validación → 410 |
| `PUT/DELETE marketing/campaigns/{id}`, `POST …/{id}/approve|pause|activate` | `findOrFail` (404 si es de otra marca) → 410 |
| `GET marketing/campaigns/{id}` (vista) | `findOrFail` (404) → **redirect 302 a `/marketing`** (no 410: es navegación) |
| `POST marketing/content/generate-copy|generate-image` | validación (422 si la campaña es de otra marca) → 410. **Ninguna llamada a IA ni HTTP.** |
| `POST marketing/content/approve|reject/{id}`, `GET marketing/content/campaign/{campaignId}` | 410 (`campaign_contents` no existe); `campaign/{id}`: `findOrFail` de la campaña → 410 |
| `POST/GET/PUT marketing/leads-legacy/**` | `table` → 410; con `{id}`: `findOrFail` (404) → 410 |

`GET marketing` y `GET marketing/campaigns` siguen sirviendo la vista del Dashboard. La UI deja de llamar a todas las
retiradas.

### 2.6 Acceso a las pantallas viejas sin `config/route_permission.php` — `[F1-1]`

En `routes.php` se **quita `check_route_permission`** de los 2 grupos que lo llevan (vistas Blade `marketing/**` y API
`api/marketing/publishing/**`). No se toca ninguna otra cosa de esas rutas (orden, middleware de empresa/rol, nombres).
Queda así:
- Entra a una pantalla quien tiene **empresa de Marketing asignada con el rol de la matriz** (V/M/MP de Fase 0).
- Cada acción sigue exigiendo su permiso Spatie donde ya lo exige el controlador (Leads, Formularios, Brand Kit,
  video, multivariante, voces, conversaciones). Los permisos a personas no-admin los da Irving/Henry en el editor de roles.
- `marketing.empresa` (`ResolverEmpresaActiva`) agrega la revisión que hacía `check_route_permission`: usuario con
  `estado ≠ 'activo'` → cierra sesión y responde **401** JSON `{"ok":false,"error":"usuario_inactivo","mensaje":"Tu usuario está inactivo."}`
  / redirect a login en navegación.
- Si Henry dice **NO**: no se toca nada de esto (las pantallas siguen abriendo solo a roles de administración del sistema) y
  queda pendiente pedirle a Irving las entradas en `config/route_permission.php`.

#### 2.6.1 Condiciones de Henry para F1-1 (obligatorias, 2026-11-13)
1. **Se sigue exigiendo sesión iniciada en LM**: toda ruta del módulo conserva `auth` (sin sesión → redirect a login en
   navegación / 401 en API). Quitar `check_route_permission` NO quita `auth`.
2. **Acceso negado por defecto**: toda ruta del módulo pasa por `marketing.empresa` (+ `marketing.rol` donde aplique). Las
   únicas excepciones son las públicas que ya existían por diseño (formulario público por slug y webhooks con su firma);
   se listan con nombre en una lista cerrada dentro de la prueba, y cualquier ruta nueva fuera de esa lista sin esos
   middleware hace fallar la prueba.
3. **QA prueba, ruta por ruta (todas las del módulo, recorridas desde el router, no una lista a mano)**:
   a) usuario con sesión, **sin rol de Marketing y sin equipo** (sin empresa asignada, sin roles de sistema) → **403** en
      cada ruta (API: JSON `sin_empresa`; Blade: la pantalla «Aún no tienes empresa» con status 403);
   b) **sin sesión** (alguien de fuera escribiendo la URL) → nunca 200: redirect a login o 401/419, en cada ruta;
   c) usuario inactivo → `usuario_inactivo`.

#### 2.6.2 Restricción automática al aplicar la versión (Henry, 2026-11-13, condición para aprobar)
Objetivo: al aplicar la versión en prod **en ningún momento** entra nadie fuera de la lista, sin comando posterior.
- **Lista en `.env`** (no en el repo): `MARKETING_EQUIPO_LOGINS=<login_user>,<login_user>` (separados por coma, sin
  espacios obligatorios; se recortan). Vacía o ausente → nadie entra.
- **Migración nueva** `2026_11_13_100200_restringe_equipo_marketing_a_lista_env.php` (env() en migración está
  permitido; NO crear archivos de config con env(): `config:auditar-env` los marca y rompe el candado de `config:cache`):
  en transacción, sobre la empresa 1: pone `activo = false` a toda fila de `marketing_empresa_usuario` cuyo usuario no
  esté en la lista (no borra: reversible); para cada login de la lista que exista → fila `admin` activa (updateOrCreate)
  + `givePermissionTo` (aditivo) de los permisos de Marketing (misma lista que `marketing:equipo-fijar`, sin
  `manage-integrations`); login inexistente → se ignora y se avisa en la salida; escribe la **marca**
  `marketing_settings` (company_id 1, key `equipo_fijado_at`, group `acceso`, value = fecha) y `activity_log`
  (`log_name=marketing`, `event=equipo_restringido`, solo conteos, sin logins). Idempotente. `down()`: borra la marca
  (con eso nadie entra; no reactiva filas). Nunca ids; ningún login real en código, pruebas ni docs.
- **Candado en tiempo de ejecución** (`ResolverEmpresaActiva`): si NO existe la marca `equipo_fijado_at` en la empresa
  1 → 403 `sin_acceso` para todos (cubre los milisegundos entre la migración de Fase 0 que asigna por rol y esta, y
  cualquier base donde esta migración no haya corrido). Con marca → regla B de siempre (fila admin activa en empresa 1).
- `marketing:equipo-fijar --aplicar` también escribe/actualiza la marca.
- **Pruebas:** `MarketingTestCase` (o el escenario base) crea la marca para que las pruebas existentes sigan igual; pruebas
  nuevas: sin marca → 403 en todas las rutas aun con fila admin; migración con lista vacía → nadie activo + marca;
  con lista de 2 logins ficticios → solo ellos activos/admin y con permisos; login inexistente → ignorado; `up()` dos
  veces igual; `down()` → nadie entra; los 12 «admins por rol» que deja la migración 100300 quedan inactivos.

### 2.7 Permisos que faltan — `[F1-2]`

Migración `app/Modules/Addons/Marketing/migrations/2026_11_13_100000_crea_permisos_faltantes_de_marketing.php`, mismo
patrón que la de Fase 0 (`Permission::firstOrCreate` + `PermissionSyncService::syncPermissionToBaseRoles`, solo
`super-administrator` y `DESARROLLADOR`, aditivo; `down()` vacío por criterio de catálogo). Crea **13**:

| Permiso | Quién lo exige hoy |
|---|---|
| `marketing.leads.view`, `.create`, `.update`, `.delete`, `.assign`, `.score` | `MarketingLeadController` + menú |
| `marketing.forms.view`, `.create`, `.update`, `.delete` | `MarketingLeadFormController` + menú |
| `marketing.brand_kit.configure` | `MarketingBrandKitController` + menú |
| `marketing.conversations.view` | solo el menú lateral (entrada «Conversaciones») |
| `marketing.campaigns.view` | solo el menú lateral (entrada «Campañas» → `/marketing`) |

**No** se crea `marketing.templates.manage` (su entrada del menú lleva a Plantillas legado, retiradas). Efecto visible:
para super-administrador y DESARROLLADOR aparecen en el menú lateral Leads, Conversaciones, Formularios, Campañas y Brand
Kit, y esas pantallas dejan de dar 403. Se declaran también en `module.json → permissions[]`. **No se sube `version`.**

### 2.8 `module.json`

- `permissions[]`: + los 13 de §2.7.
- `api_endpoints[]`: + las 3 rutas nuevas.
- `menu[0].children` **reordenado** y con dos claves nuevas por item: `icono_material` y `roles_empresa`
  (`ModuleRegistry` ignora claves que no conoce; el sidebar no pinta Marketing desde aquí):

| # | label | url | permission | roles_empresa | sub |
|---|---|---|---|---|---|
| 1 | Panel | `/marketing` | — | admin, marketing | — |
| 2 | Leads | `/marketing/leads` | `marketing.leads.view` | admin, marketing, ventas | — |
| 3 | Conversaciones | `/marketing/conversations` | — | admin, marketing, ventas | — |
| 4 | Formularios | `/marketing/lead-forms` | `marketing.forms.view` | admin, marketing | — |
| 5 | Campañas IA | `/marketing/campaigns/generate` | `view-marketing-campaigns` | admin, marketing | — |
| 6 | Video | `/marketing/video-templates` | `view-video-templates` | admin, marketing | Plantillas `/marketing/video-templates` · Generar `/marketing/video-generator` (`generate-video-content`) · Cola `/marketing/video-queue` (`view-video-content`) · Voces `/marketing/voice-comparator` (`test-voices`) |
| 7 | Publicación | `/marketing/publishing` | — | admin, marketing | Tablero `/marketing/publishing` · Publicar `/marketing/publishing/campaign` · Cola `/marketing/publishing/queue` · Canales `/marketing/publishing/setup` |
| 8 | Piloto A/B | `/marketing/pilot-campaigns` | — | admin, marketing | — |
| 9 | Brand Kit | `/marketing/brand-kit` | `marketing.brand_kit.configure` | admin, marketing | — |
| 10 | Empresas y equipo | `/marketing/empresas` | `marketing.empresas.gestionar` | admin | — |
| 11 | Integraciones | `/integraciones` | `manage-integrations` | admin, marketing | — |

Iconos Material: insights, people, forum, dynamic_form, auto_awesome, movie, campaign, science, palette, domain, hub.
`menu[0]` (url `/marketing`, `permission` `create-marketing-campaigns`) no cambia.

### 2.9 Frontend: lo que comparten

- **Kit nuevo** (lo escribe frontend, en `kit/` y en `COMPONENTES_KIT` de `kit/index.js` → se registra solo vía
  `registrar.js`; **no se toca `app.js`**):
  - `mkt-navegacion-modulo` (`MktNavegacionModulo.vue`): sin props. `GET /api/marketing/navegacion`; barra horizontal con
    los items (marca el activo comparando `window.location.pathname`), y fila de pestañas `sub` si el item activo trae
    `sub`. A 375 px: desplazable **dentro** de sí misma. Errores → no pinta nada (la pantalla sigue). Va dentro de
    `<mkt-raiz>`, debajo del encabezado, en **todas** las pantallas de Marketing.
  - `mkt-dialogo` (`MktDialogo.vue`): `q-dialog` con `<mkt-raiz>` adentro, props `titulo`, `modelValue`, `guardando`
    (pone `:loading` y bloquea cerrar), `ancho` (default 560 px; móvil `calc(100vw - 32px)`), slots `default` y `acciones`.
    Para que ningún diálogo nuevo repita el arreglo de teletransporte/Bootstrap.
- **Formato**: `formatoMXN` recibe la cadena del backend (`Number(v)` solo para formatear). Fechas con `formatoFecha`.
- **Estados obligatorios en cada pantalla** (guía §5): cargando, vacío, error (con `mensaje` del backend), sin permiso
  (403 → `mkt-estado-vacio` con candado, sin botones), **no encontrado** (404 de una ficha por id → «No encontrado», nunca
  datos ajenos ni pantalla en blanco), **retirada** (410 → no debería ocurrir porque la UI ya no llama esas rutas).
- **Doble clic**: todo botón que escribe lleva `:loading` mientras la petición está en curso y se deshabilita.
- Ningún `v-html`; todo `<style scoped>`; layout con clases `mkt-*` (no `row/col-*`); 375 px sin scroll horizontal de
  página; claro y oscuro; Material Icons en Quasar, FA5 validado con `grep` solo fuera de Quasar.

### 2.10 Base de pruebas, migración y vista previa

- **Migración** (solo backend, en cuanto la escriba):
  `cd /var/www/megaisp-henry-marketing && flock -w 600 /tmp/megaisp-suite.lock env APP_ENV=testing DB_DATABASE=megaisp_test php artisan migrate --path=app/Modules/Addons/Marketing/migrations --force`
  y después `env APP_ENV=testing DB_DATABASE=megaisp_test php artisan permission:cache-reset`.
- **Compilar** (solo frontend): `cd /var/www/megaisp-henry-marketing && timeout 900 env NODE_OPTIONS=--max-old-space-size=4096 npm run dev > $S/npm-dev.log 2>&1`.
  Sin `public/js/app.js` compilado **en el worktree** el layout no pinta en la vista previa. QA **no compila**: usa la
  última compilación de frontend (frontend anota en su avance la hora de cada compilación).
- **Usuarios de vista previa** (`mkt_prev_admin`, `mkt_prev_marketing`, `mkt_prev_ventas`, `mkt_prev_sinempresa`): el
  script de Fase 0 §2.10.2 (`fase-0-paquetes.md`), idéntico, contraseña con `read -s` y nunca escrita en archivos. Lo
  corre frontend; QA puede re-correrlo (idempotente). Si F1-1 = SÍ, agregar `mkt_prev_marketing_sinrol`: **sin** roles de
  sistema, asignado a la empresa 1 con rol `marketing` y con `givePermissionTo` de `marketing.leads.view` y
  `marketing.forms.view` (para ver que entra sin `check_route_permission`).
- **Datos de vista previa** (solo `megaisp_test`, con `tinker` en un script del scratchpad, **nunca** un seeder ni
  migración): empresa id 2 «Empresa Prueba B» (como en Fase 0) y, con nombre que empiece por `PREV-F1`: ≥15 leads de la
  empresa 1 repartidos en estados y en este mes y el anterior, 3 leads de la 2, 1 campaña `active` MXN con
  `budget`/`spent` y 1 en USD en la empresa 1, atribuciones, 1 formulario por empresa (slugs distintos), 2 conversaciones
  de la 1. Lo corre frontend; **QA lo borra al cierre** (`DELETE … WHERE name/full_name LIKE 'PREV-F1%'`, empresa 2,
  usuarios `mkt_prev_*` con sus roles y asignaciones), con el mismo `flock`/`env`.
- **Servidor** (frontend en **8096**, QA en **8097**):
  ```bash
  cd /var/www/megaisp-henry-marketing && env APP_ENV=local APP_URL=http://127.0.0.1:8096 DB_DATABASE=megaisp_test \
    QUEUE_CONNECTION=null MAIL_MAILER=log php artisan serve --host=127.0.0.1 --port=8096 --no-reload
  ```
  **Sin `--no-reload` el proceso hijo lee el `.env` y cae en la base `megaisp`.** Se comprueba con `curl` que responde y se
  mata al terminar. Playwright desde `node_modules/playwright`; capturas en el scratchpad, nunca en `public/`.

---

## 3. PAQUETE BACKEND (`agente-backend`) — tamaño M (~22 archivos, ~1.5–2 h)

**Crear:**
- `app/Modules/Addons/Marketing/migrations/2026_11_13_100000_crea_permisos_faltantes_de_marketing.php` `[F1-2]`
- `app/Modules/Addons/Marketing/Controllers/DashboardController.php`
- `app/Modules/Addons/Marketing/Controllers/NavegacionController.php`
- `app/Modules/Addons/Marketing/Services/Dashboard/DashboardMarketingService.php` (cálculos de §2.4; consultas con
  bindings; sin float)
- `app/Modules/Addons/Marketing/Requests/ResumenDashboardRequest.php`, `.../CampanasActivasRequest.php`
- `app/Modules/Addons/Marketing/Controllers/Concerns/RespondeRetirada.php` (trait con la respuesta 410 de §2.5) `[F1-3]`
- `tests/Feature/Marketing/Fase1/Backend/**` — mínimo: `DashboardResumenBackendTest.php`,
  `CampanasActivasBackendTest.php`, `NavegacionBackendTest.php`, `MarcaVeSusRegistrosBackendTest.php`,
  `RetiradasBackendTest.php`, `PermisosFaltantesBackendTest.php`, `AccesoSinRutaPermisoBackendTest.php` `[F1-1]`

**Modificar:**
- `app/Modules/Addons/Marketing/routes.php` — 3 rutas nuevas al final del grupo API (§2.4); quitar `check_route_permission`
  de los 2 grupos `[F1-1]`. Nada más.
- `app/Modules/Addons/Marketing/module.json` — §2.8. Sin subir `version`.
- `app/Modules/Addons/Marketing/Middleware/ResolverEmpresaActiva.php` — revisión de usuario inactivo `[F1-1]`.
- Quitar `company_id = 1` según §2.3:
  - `Controllers/MarketingLeadController.php` (líneas 28, 83, 103, 115, 128, 136, 143, 150)
  - `Controllers/MarketingLeadFormController.php` (22, 31, 40, 53, 61, 64, 80, 87) + **slug único entre todas las marcas**
    (`Rule::unique('marketing_lead_forms','slug')->whereNull('deleted_at')`, ignorando el propio id al editar; mensaje
    «Ese identificador ya lo usa otro formulario.») + `assign_to_pipeline_id` con `Rule::exists` acotado a la empresa activa.
  - `Controllers/MarketingMultivariantCampaignController.php` (`forCompany(1)` 30, 39, 52, 121, 157; altas 99, 132; nichos
    175, 184 → `PRINCIPAL_ID`; `RenderVideoJob::dispatch(..., 1)` 144 → `PRINCIPAL_ID`)
  - `Controllers/MarketingGeneratedContentController.php` (27, 52, 59, 136, 165, 182 → scope; plantilla 90 →
    `PRINCIPAL_ID`; `RenderVideoJob::dispatch(..., 1)` 153 → `PRINCIPAL_ID`)
  - `Controllers/PublishingController.php` (`forCompany(1)` 32, 194 y `where('company_id',1)` 131, 184, 209 → scope; pasar
    `app(EmpresaActiva::class)->id()` a `ChannelManager`/`SmartRouter`/drivers donde hoy usan el default 1)
  - `Controllers/MarketingVideoTemplateController.php` (19, 52, 59 → `PRINCIPAL_ID`)
  - `Controllers/PilotCampaignController.php` + `Services/Pilot/PilotCampaignService.php` (28): `create(array $data, int $companyId)`
    sin default; el controlador pasa la empresa activa.
  - `Jobs/GenerateMultivariantCampaignJob.php`: nichos (67, 128) y `CreativeDirectorService` (56-57) y `RenderVideoJob` (110)
    con `PRINCIPAL_ID`; los `GeneratedContent` siguen con `$campaign->company_id` (89).
  - `Controllers/MarketingBrandKitController.php`: `$cid = 1` → `Empresa::PRINCIPAL_ID` (sin otro cambio).
- Retiradas `[F1-3]` (§2.5): `Controllers/MarketingController.php` (`stats`, `templates`, `storeTemplate`, `updateTemplate`,
  `destroyTemplate`), `Controllers/CampaignController.php` (todo menos `index`), `Controllers/ContentGeneratorController.php`,
  `Controllers/LeadController.php`.

**Tareas en orden:** (1) migración de permisos + aplicarla en `megaisp_test` (§2.10) ; (2) `company_id` (§2.3) ;
(3) retiradas ; (4) servicio y controladores del Dashboard + navegación ; (5) `routes.php` + `module.json` +
middleware ; (6) pruebas propias ; (7) `grep -rnE "company_id['\"]?\s*(=>|,)\s*1\b|forCompany\(1\)|, 1\)\s*$" app/Modules/Addons/Marketing/Controllers app/Modules/Addons/Marketing/Services/Pilot app/Modules/Addons/Marketing/Jobs/GenerateMultivariantCampaignJob.php`
debe salir vacío ; (8) `php artisan route:list --path=marketing | wc -l` y anotar el total.

**Pruebas propias mínimas** (normal / error / vacío / borde): cada KPI con números exactos (incluye centavos, redondeo
half-up, 0 leads, sin campañas → null, campaña USD excluida, mes anterior en 0 → variación null, `mes` inválido/futuro → 422);
campañas activas (límite, orden, `budget = 0`, USD); navegación por rol (ventas solo Leads y Conversaciones, admin todo, orden
igual a `module.json`); una marca B crea y ve sus registros en los 6 controladores + piloto, A no los ve, id cruzado → 404;
B lee plantillas de video y nichos de Meganet; el job multivariante de una campaña de B encuentra nichos; retiradas → 410
(y 404/422 antes cuando aplica); migración idempotente; usuario inactivo `[F1-1]`.

**Fuera de alcance:** cualquier archivo no listado; en particular `config/**`, `app/Http/Kernel.php`, el sidebar,
`app/Modules/Addons/IA/config/modulos.php`, las vistas Blade (Fase 1 no las cambia), los Vue, y **todas** las pruebas de
`tests/Feature/Marketing/Fase0/**` y las pruebas viejas de Marketing (son de QA en esta fase).

---

## 4. PAQUETE FRONTEND (`agente-frontend`) — tamaño XL (~30 archivos, ~5,000 líneas a rediseñar, ~4 h, en 2 olas)

**Crear:**
- `resources/js/components/module/marketing/kit/MktNavegacionModulo.vue`, `kit/MktDialogo.vue`
- `resources/js/components/module/marketing/dashboard/PanelKpis.vue`, `dashboard/PanelEmbudo.vue` (ApexCharts barra
  horizontal con `kit/tokens.js`), `dashboard/PanelCampanasActivas.vue`
- `tests/Feature/Marketing/Fase1/Frontend/PantallasMarketingTest.php` — cada vista Blade de Marketing responde 200 al admin
  con su tag kebab-case; las vistas M responden 403 a `ventas`; `/marketing/campaigns/{id}` redirige a `/marketing`.

**Modificar (rediseño con el kit, mismo funcionamiento):**

| Ola | Pantalla (URL) | Archivos |
|---|---|---|
| 1 | Dashboard `/marketing` — **rehacer**: encabezado con selector · KPIs · embudo · campañas activas (vacío: «Aún no hay campañas activas» + botón «Crear campaña con IA» → `/marketing/campaigns/generate`) · accesos rápidos a Leads y Conversaciones. Se quitan las pestañas Campañas/Leads legado/Plantillas/Conversaciones, el diálogo «Nueva campaña» legado y el de plantillas. «Nueva campaña» → `/marketing/campaigns/generate`. Selector de mes (mes actual y 11 anteriores). | `MarketingDashboard.vue` |
| 1 | Leads lista y ficha (`/marketing/leads`, `/marketing/leads/{id}`) — tabla ejecutiva con filtros, ficha con timeline; 404 → «No encontrado» | `MarketingLeadsView.vue`, `MarketingLeadDetail.vue`, `LeadActivityTimeline.vue`, `LeadScoreBadge.vue`, `LeadStatusBadge.vue`, `LeadSourceChip.vue` |
| 1 | Formularios y editor (`/marketing/lead-forms`, `…/create`, `…/{id}/edit`) — muestra el error de slug repetido en su campo | `MarketingLeadFormsView.vue`, `MarketingLeadFormEditor.vue` |
| 1 | Conversaciones (`/marketing/conversations[/{id}]`) — bandeja tipo panel; **sin cambiar la lógica del bot** (mismos endpoints y botones) | `MarketingConversationsView.vue`, `ConversationListItem.vue`, `MessageBubble.vue`, `MessageDayDivider.vue`, `AgentToggleButton.vue` |
| 2 | Video: plantillas, generador, cola, voces | `MarketingVideoTemplatesView.vue`, `MarketingVideoGeneratorView.vue`, `MarketingVideoQueueView.vue`, `VoiceComparatorView.vue` |
| 2 | Generador multivariante (`/marketing/campaigns/generate`) | `MarketingCampaignGeneratorView.vue` |
| 2 | Piloto A/B (`/marketing/pilot-campaigns`) | `PilotCampaignsView.vue` |
| 2 | Publicación: tablero, publicar, cola, canales | `publishing/PublishingDashboardView.vue`, `publishing/PublishCampaignView.vue`, `publishing/PublicationQueueView.vue`, `publishing/ChannelsSetupView.vue` |
| 2 | Brand Kit — sin el bloque de llaves de API: muestra «Configurada / Falta» de OpenAI y Pexels y un enlace «Configurar en Integraciones» (`/integraciones`) [F1-6] | `MarketingBrandKitView.vue` |
| 2 | Empresas y equipo — solo agregar `<mkt-navegacion-modulo>` | `empresas/MarketingEmpresasEquipo.vue` |

También: `kit/index.js` (agregar los 2 componentes), `kit/formato.js` y `kit/tokens.js` (solo agregar, sin cambiar lo
existente), `registrar.js` (solo si hace falta), `docs/marketing/guia-visual.md` (agregar: navegación del módulo,
gráfica de embudo, `mkt-dialogo`, estado «no encontrado»), `docs/marketing/fase-1-avance-frontend.md`.

**Cierre de cada ola:** `npm run dev` sin errores ni advertencias nuevas; Playwright (8096) a 1440 y 375 px, claro y
oscuro, de cada pantalla de la ola con datos, vacía y con error; consola sin errores; capturas en
`…/scratchpad/fase1/frontend/`; una línea en el avance con la lista de capturas. Al cerrar la ola 1 se avisa en el avance
para que QA pueda empezar sus pruebas de navegador de esas pantallas.

**Fuera de alcance:** `resources/js/app.js`; `MarketingCampaigns.vue`, `MarketingLeads.vue`, `MarketingContentGenerator.vue`,
`MarketingCampaignShow.vue`, `MarketingLeadForm.vue` (sin tocar, F1-9); cualquier `.php` salvo su prueba; Blade; sidebar;
CSS global; `public/` (salvo lo que genera `npm run dev`, que no entra al commit).

---

## 5. PAQUETE QA (`agente-qa`) — tamaño L (~10 archivos de prueba + navegador al cierre, ~2.5 h)

**Crear** (todas extienden `MarketingTestCase`; **no siembran permisos que deban venir de la migración**):
- `tests/Feature/Marketing/Fase1/Qa/Concerns/EscenarioFase1.php` — empresas A (=1) y B, usuarios admin/marketing/ventas de A,
  admin de B, sin empresa, inactivo; datos en A y B de leads (fechas en este mes, el anterior y otro año; todos los
  estados), campañas MXN y USD con `budget`/`spent` con centavos, atribuciones, formularios, contenido, multivariante,
  piloto, canales, publicaciones.
- `Fase1/Qa/DashboardKpisTest.php` — cada definición de §2.4 con números exactos; aislamiento A/B (B nunca suma datos de A);
  ventas → 403 `sin_permiso_rol`; ataque en `mes` (vacío, `2026-13`, `2019-12`, futuro, array, `<script>`, SQL).
- `Fase1/Qa/CampanasActivasTest.php` — forma, orden, límite (0, 21, `abc`), USD, `budget = 0`, A/B.
- `Fase1/Qa/NavegacionTest.php` — orden igual a `module.json`, filtro por rol y permiso, sin empresa → 403, sin campos sensibles.
- `Fase1/Qa/MarcaNuevaVeSusRegistrosTest.php` — **pruebas A/B de la condición 1 de Henry**: por cada uno de los 6 controladores
  y el piloto, B crea → B lo lista y lo abre por id; A no lo ve y por id → 404; B ve las plantillas de video y los nichos de
  Meganet; B no puede editar nichos ni Brand Kit (`config_compartida`).
- `Fase1/Qa/RutasRetiradasTest.php` — cada ruta de §2.5: forma 410 exacta; 404 / 422 antes cuando aplica; **ninguna**
  respuesta 500; sin llamadas HTTP (`Http::assertNothingSent()`); y un barrido: toda ruta GET autenticada de Marketing
  sin parámetros responde ≠ 500 al admin de A.
- `Fase1/Qa/PermisosFaltantesTest.php` `[F1-2]` — tras la migración existen los 13, los tienen super-administrator y
  DESARROLLADOR, nadie más los recibió por la fase; un super-administrador (sin sembrar nada) obtiene 200 en
  `GET /api/marketing/leads`, `GET /api/marketing/lead-forms`, `GET /api/marketing/brand-kit`; `up()` dos veces no duplica.
- `Fase1/Qa/AccesoSinRutaPermisoTest.php` `[F1-1]` — usuario sin roles de sistema con empresa `marketing` + permisos → 200 en
  sus pantallas y APIs; sin empresa → `sin_empresa`; inactivo → `usuario_inactivo`; ventas sigue sin campañas/video/publicación.
- `Fase1/Qa/SlugFormularioGlobalTest.php` — slug repetido entre marcas → 422; el formulario público de cada slug crea el lead
  con el `company_id` de **su** marca.
- `docs/marketing/fase-1-qa-reporte.md` y `docs/marketing/fase-1-avance-qa.md`.

**Modificar (solo por cambios del contrato, sin bajar ninguna exigencia; cada cambio listado en el reporte con su razón):**
- `tests/Feature/Marketing/Fase0/Qa/RutasMarketingProtegidasTest.php` — total 131 → **134**, las 3 nuevas en `nuevas()`; si
  F1-1 = SÍ, la 6.ª columna de las rutas Blade y de Publicación pasa de `['check_route_permission']` a `[]` y se agrega la
  aserción de que **ya no** lo llevan.
- Cualquier otro archivo de `tests/Feature/Marketing/Fase0/**` o de las pruebas viejas de `tests/Feature/Marketing/*.php`
  **solo** si una aserción dependía de `check_route_permission` `[F1-1]`, del 500 de una ruta retirada `[F1-3]` o del
  `company_id = 1` fijo. Antes de tocarlo, correrlo por nombre y anotar qué falla y por qué.

**Pruebas de ataque en el navegador (al cierre, puerto 8097, pendientes de la Fase 0)** — se reportan en
`fase-1-qa-reporte.md` con capturas:
1. **Doble clic en guardar**: crear lead, guardar formulario, crear piloto, «Agregar persona» en Empresas y equipo, renderizar
   video (con `QUEUE_CONNECTION=null`) → una sola fila nueva (contar en `megaisp_test` antes/después).
2. **Recargar a mitad**: recargar mientras se guarda un lead, mientras carga el Dashboard y justo después de cambiar de empresa
   con el selector → sin filas duplicadas ni a medias, la pantalla vuelve a un estado válido y la empresa activa es la elegida.
3. **URL con id de otra marca**: con `mkt_prev_admin` en la empresa 1 abrir `/marketing/leads/{idB}`,
   `/marketing/lead-forms/{idB}/edit`, `/marketing/conversations/{idB}` y `/marketing/campaigns/{idB}` → «No encontrado» o
   redirect, nunca datos de B; y al revés.
4. Usuario `ventas` en `/marketing` y `/marketing/video-templates` → pantalla de sin permiso; usuario sin empresa → «Aún no
   tienes empresa». `[F1-1]` `mkt_prev_marketing_sinrol` entra a Leads.
5. Las 18 pantallas a 375 px: sin scroll horizontal de página; consola sin errores.

**Al cierre:** skill `pruebas-antes-de-avanzar` pasos 2–7 (N = 1 migración; dry-run con
`--with-data=permissions,roles,role_has_permissions,model_has_permissions,model_has_roles`), **sin** el paso 1 (la suite
completa la corre la sesión principal), limpieza de §2.10, `git diff --stat` contra la base.

---

## 6. Regla de no-pisarse

| Archivo / carpeta | Dueño único |
|---|---|
| `app/Modules/Addons/Marketing/routes.php`, `module.json`, `Middleware/ResolverEmpresaActiva.php` | backend |
| `app/Modules/Addons/Marketing/{migrations,Controllers,Services,Requests,Jobs}/**` (los listados en §3) | backend |
| `tests/Feature/Marketing/Fase1/Backend/**`, `docs/marketing/fase-1-avance-backend.md` | backend |
| `resources/js/components/module/marketing/**` (los listados en §4) | frontend |
| `docs/marketing/guia-visual.md`, `docs/marketing/fase-1-avance-frontend.md` | frontend |
| `tests/Feature/Marketing/Fase1/Frontend/**` | frontend |
| `tests/Feature/Marketing/Fase1/Qa/**`, `tests/Feature/Marketing/Fase0/**`, `tests/Feature/Marketing/*.php` | QA |
| `docs/marketing/fase-1-qa-reporte.md`, `docs/marketing/fase-1-avance-qa.md` | QA |
| `docs/marketing/fase-1-paquetes.md`, `docs/marketing/fase-1-linea-base-fallas.txt`, `docs/bitacora/2026-11-1x-marketing-fase-1.md` | supervisor |
| `resources/js/app.js`, `app/Models/Marketing/**`, `IA/config/modulos.php`, Blade de Marketing | **nadie** en Fase 1 |

`megaisp_test`: migrar = solo backend; usuarios y datos de vista previa = frontend (QA re-corre y limpia). Compilar = solo
frontend. Puertos: frontend 8096, QA 8097. Si un agente necesita un archivo que no es suyo, lo anota en su avance y lo pide
al supervisor con el texto exacto; no lo toca.

---

## 7. Criterios de aceptación (verificables)

1. `/marketing` muestra, con Meganet activa, los 4 KPIs del mes (con «—» donde no hay dato, nunca un 0 inventado), su
   variación contra el mes anterior, el embudo y las campañas activas (vacío con acción si no hay). Cambiar el mes recalcula.
2. Los números del Dashboard coinciden con un conteo manual en `megaisp_test` (QA lo demuestra en `DashboardKpisTest`).
3. Con la empresa B activa, el Dashboard, Leads, Formularios, Multivariante, Video, Publicación y Piloto muestran **solo**
   lo de B; lo que B crea, B lo ve; A no lo ve y por id recibe 404 / «No encontrado».
4. B ve el catálogo compartido de Meganet (plantillas de video, nichos) y no puede editarlo (`config_compartida`).
5. Cero `company_id = 1`, `forCompany(1)` o `dispatch(…, 1)` literales en los archivos de §3 (grep vacío).
6. `[F1-2]` Super-administrador abre Leads, Formularios y Brand Kit sin 403; en el menú lateral aparecen Leads,
   Conversaciones, Formularios, Campañas y Brand Kit.
7. `[F1-3]` Ninguna ruta de Marketing responde 500 en el barrido de QA; las retiradas responden 410 con el mensaje en español
   y la UI ya no las llama.
8. `[F1-1]` Una persona sin roles de administración del sistema, con empresa y permisos, abre Leads; una sin empresa ve «Aún
   no tienes empresa»; un usuario inactivo es rechazado.
9. Las 18 pantallas usan el kit (encabezado, navegación del módulo en el orden de `module.json`, tarjetas, tablas, estados
   cargando/vacío/error/sin permiso/no encontrado), se ven bien a 1440 y 375 px, claro y oscuro, sin errores de consola.
10. Doble clic, recarga a mitad y URL de otra marca: resultados del §5 sin una sola falla, con capturas.
11. Slug de formulario repetido entre marcas → 422; el formulario público crea el lead en su marca.
12. `megaisp_dryrun`: la migración corre en limpio, `rollback --step=1` y re-`migrate` pasan; con datos reales da los 13
    permisos solo a los 2 roles base. Ninguna migración inserta en `marketing_empresas`.
13. Rutas de Marketing: **134**; las autenticadas llevan `marketing.empresa` (`RutasMarketingProtegidasTest` en verde).
14. Grep de seguridad y de reglas del sistema sin coincidencias nuevas (sin IA, sin WhatsApp, sin Meta, sin `float`/`USD` en
    cálculos de dinero, sin `v-html`, sin llaves).
15. **Suite completa (la corre la sesión principal): 0 fallas nuevas** contra `docs/marketing/fase-1-linea-base-fallas.txt`:
    ```bash
    php -r '$b=array_flip(file($argv[1],FILE_IGNORE_NEW_LINES));$n=[];foreach((new SimpleXMLElement(file_get_contents($argv[2])))->xpath("//testcase[failure or error]") as $t){$k=$t["class"]."::".$t["name"];if(!isset($b[$k]))$n[]=$k;}echo count($n)," fallas nuevas\n",implode("\n",$n),"\n";exit($n?1:0);' docs/marketing/fase-1-linea-base-fallas.txt <junit-de-la-fase>.xml
    ```
16. `git diff --stat` solo muestra archivos de la sección 6 (más `public/js/` sin trackear, que no entra al commit).

---

## 8. Riesgos y cómo se cubren

| # | Riesgo | Cobertura |
|---|---|---|
| R1 | Frontend es el paquete más grande (18 pantallas) y puede atorarse | 2 olas con cierre propio; avance cada 10 min; la sesión principal lo retoma a los 20 min sin avance |
| R2 | Quitar el `company_id` fijo cambia algo de bot/jobs/webhooks | Solo se tocan lecturas con contexto y el catálogo del job multivariante; QA re-corre `Fase0/Qa/CaminosSinSesionTest.php` |
| R3 | Las retiradas rompen pruebas de Fase 0 que esperan 404/422 | Validación y `findOrFail` se conservan antes del 410 (§2.5) |
| R4 | `[F1-1]` abre Marketing a más personas | Solo quien tiene empresa asignada por un admin con `marketing.empresas.gestionar`; acciones con su permiso Spatie; inactivos fuera; matriz de QA |
| R5 | `[F1-2]` cambia el menú lateral de los roles base | Esperado y deseado; solo super-administrador y DESARROLLADOR; `PermisosFaltantesTest` |
| R6 | Las pruebas vuelven a tapar un permiso inexistente | Regla §2.1: Fase 1 no siembra permisos que deben venir de la migración |
| R7 | Slug global choca con datos actuales | Hoy solo existe la empresa 1 → no hay choques; es solo validación, no cambia el índice |
| R8 | `npm run dev` se queda sin memoria | `NODE_OPTIONS=--max-old-space-size=4096` + `timeout 900` + log |
| R9 | Datos de vista previa ensucian `megaisp_test` (compartida) | Prefijo `PREV-F1`, limpieza obligatoria de QA, todo con `flock` |
| R10 | «Gasto» confunde (es acumulado de la campaña, no del mes) | Etiqueta «Gasto registrado» + ayuda «suma de lo gastado en campañas vigentes este mes»; Meta lo reemplaza en Fase 5 (F1-5) |
| R11 | Se cuela una segunda marca a producción | Prohibido por contrato; QA hace `grep -rn "marketing_empresas" app/Modules/Addons/Marketing/migrations` y revisa que solo la de Fase 0 inserte (id 1) |
| R12 | Componentes viejos quedan como código muerto | Documentado (F1-9); se limpian en Fase 2 |
| R13 | Un video de otra marca sale con el logo de Meganet | Aceptado por D3/F1-7 hasta Brand Kit por marca |

---

## 9. Decisiones que necesitan a Henry

### Bloqueantes (sin ellas no arranca la tarea marcada)

| # | Pregunta | Recomendación |
|---|---|---|
| **F1-1** | Pantallas viejas tras `check_route_permission` sin mapeo (D5): ¿se quita ese candado de las rutas de Marketing (solo `routes.php` del módulo) y el acceso lo dan la empresa + rol de Marketing y los permisos de cada acción? Sin esto, solo los roles de administración del sistema abren Marketing. | **Sí** |
| **F1-2** | 13 permisos de Marketing no existen y por eso Leads, Formularios y Brand Kit dan 403 a todos (también en prod). ¿Se crean por migración para super-administrador y DESARROLLADOR? | **Sí** |
| **F1-3** | Campañas legado, Plantillas legado, generar copy/imagen, leads-legacy y stats usan tablas que no existen (500). ¿Se retiran (respuesta 410 con mensaje, fuera de la UI) y «Nueva campaña» lleva al generador IA hasta la Fase 2? | **Sí** |

### No bloqueantes (arrancan con la recomendación)

| # | Pregunta | Recomendación |
|---|---|---|
| F1-4 | El menú lateral de Marketing es fijo (archivo fuera de lo autorizado). ¿Basta con el orden en `module.json` + barra de navegación dentro del módulo? | Sí; pedir a Irving el lateral más adelante |
| F1-5 | Gasto MXN antes de Meta: suma de lo gastado en campañas de la marca vigentes en el mes; hoy no hay → «—». | Sí; Meta lo reemplaza en Fase 5 |
| F1-6 | Brand Kit: quitar de la pantalla el bloque para capturar llaves de OpenAI/Pexels y mandar al Hub `/integraciones` (regla de IA central). | Sí |
| F1-7 | Videos de otra marca usan Brand Kit, nichos y plantillas de Meganet (catálogo compartido) hasta tener Brand Kit por marca. | Aceptar |
| F1-8 | Leads de WhatsApp y Meta (webhooks) siguen entrando a Meganet; otra marca solo recibe por sus formularios o captura manual. | Aceptar |
| F1-9 | Los 4 componentes viejos sin uso se quedan sin tocar (borrarlos exige otra línea en `app.js`). | Limpiarlos en Fase 2 |
