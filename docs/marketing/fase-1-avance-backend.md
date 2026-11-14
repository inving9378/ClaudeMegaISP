# Fase 1 — avance backend

10:02 — arranque: leí agente-backend.md y fase-1-paquetes.md · sigue: inventario de routes.php, middleware y migración de permisos
10:02 — (1) migración 2026_11_13_100000 creada (13 permisos, reparto directo SOLO a super-administrator y DESARROLLADOR); aplicada en megaisp_test, rollback --step=1 y re-migrate OK; verificado megaisp sin cambios · sigue: (2) quitar company_id=1
10:04 — (2) company_id=1 quitado: LeadController, LeadFormController (+slug global +pipeline acotado), Multivariant, GeneratedContent, Publishing, VideoTemplate, BrandKit, Pilot (servicio sin default), job multivariante → PRINCIPAL_ID; grep del paso 7 vacío · sigue: (3) retiradas
10:05 — (3) retiradas: trait Concerns/RespondeRetirada + MarketingController, CampaignController, ContentGeneratorController, LeadController (410; validación/findOrFail antes; show→302 /marketing) · sigue: (4) Dashboard + navegación
10:06 — inventario para Dashboard/navegación (esquema campaigns/leads/attributions; module.json menu no lo pinta ningún blade) · sigue: escribir DashboardMarketingService + Requests + controladores
10:09 — (4)(5) DashboardMarketingService + 2 Requests + DashboardController + NavegacionController; routes.php (3 nuevas al final del grupo API, sin check_route_permission en Blade y Publicación; total 134); module.json (13 permisos, menú reordenado con icono_material/roles_empresa/sub, 3 endpoints; version sin tocar); middleware: usuario inactivo → 401 usuario_inactivo / login · sigue: (6) pruebas propias
10:10 — CAMBIO DE ALCANCE (Henry, A–E) recibido: (D) REVERTIDO todo el paso (2) company_id=1 (git checkout de LeadController, LeadFormController [incl. slug global y pipeline acotado], Multivariant, GeneratedContent, Publishing, VideoTemplate, BrandKit, Pilot+servicio, job multivariante, VoiceComparator) → queda como deuda · sigue: middleware regla B + empresa activa = 1, comando marketing:equipo-fijar
10:11 — (B) middleware: solo admin activo de empresa 1 (sin filas → sin_empresa; con filas pero sin admin en 1 → 403 sin_acceso); (A) empresa activa siempre 1; «Empresas y equipo» fuera de module.json menu; (C) comando marketing:equipo-fijar en Console/EquipoFijarCommand.php, registrado en ModuleServiceProvider · sigue: pruebas
10:13 — pruebas: Concerns/EscenarioBackendFase1 + PermisosFaltantesBackendTest (6/6 OK) · sigue: DashboardResumenBackendTest
10:14 — DashboardResumenBackendTest 13/13 OK · sigue: CampanasActivas, Navegacion
10:14 — CampanasActivasBackendTest y NavegacionBackendTest OK (WAF global da 403 a 'limite=5 OR 1=1': se acepta 403/422) · sigue: Retiradas, Acceso, EquipoFijar
10:15 — RetiradasBackendTest 7/7 OK · sigue: AccesoSinRutaPermiso (regla B + barrido de rutas) y EquipoFijar
10:16 — AccesoSinRutaPermisoBackendTest 9/9 OK (barrido de las 134 rutas sin sesión + regla B) · sigue: EquipoFijarBackendTest
10:19 — EquipoFijarBackendTest 4/4 OK. Paquete backend TERMINADO (ver resumen abajo).

---

## Resumen de cierre (backend)

### Qué cambió por el CAMBIO DE ALCANCE de Henry (A–E)
- **D — revertido**: el arreglo de `company_id = 1` fijo (paso 2 del §3) se había hecho y se deshizo con
  `git checkout` de los 11 archivos (LeadController, LeadFormController —incluía slug único global y pipeline
  acotado—, Multivariant, GeneratedContent, Publishing, VideoTemplate, BrandKit, Pilot + PilotCampaignService,
  GenerateMultivariantCampaignJob, VoiceComparator). **Queda como DEUDA** (si vuelve multimarca): 35 líneas
  `where('company_id', 1)`, 5 `forCompany(1)`, 2 `RenderVideoJob::dispatch(..., 1)`, nichos del job multivariante
  por `$campaign->company_id`, `PilotCampaignService::create` con `company_id => 1`, slug de formulario único solo
  por empresa. El grep del paso 7 del §3 **NO** sale vacío a propósito.
- **B — middleware** `ResolverEmpresaActiva`: entra solo quien tiene sesión + usuario activo + fila activa
  `rol='admin'` en la empresa 1. Sin ninguna asignación → 403 `sin_empresa` (igual que Fase 0, misma pantalla);
  con asignaciones pero sin admin activo en la 1 (filas marketing/ventas, admin de otra empresa, admin inactivo) →
  403 **`sin_acceso`** (código nuevo, mensaje en `MENSAJES['sin_acceso']`; en navegación usa `meganet.pages.403`).
  Super-administrador/DESARROLLADOR sin fila → 403 `sin_empresa`.
- **A — empresa activa siempre = 1** con rol `admin` (se ignora la elegida en sesión; se guarda 1 en sesión). No se
  borró nada de Fase 0 (tablas, scope, trait, `asignacionesActivas`, ContextoEmpresaController siguen). «Empresas y
  equipo» se quitó de `module.json → menu[0].children` (la barra de navegación ya no la muestra; sus rutas siguen).
- **C — comando** `php artisan marketing:equipo-fijar <login_user>... [--aplicar]`
  (`app/Modules/Addons/Marketing/Console/EquipoFijarCommand.php`, registrado en `ModuleServiceProvider`). Sin
  `--aplicar` solo imprime la tabla de cambios. Con `--aplicar` (transacción): borra las demás filas de la empresa 1,
  deja a esas personas como `admin` activas, `givePermissionTo` (aditivo) de los permisos existentes `marketing.%`
  (los 13 de F1-2 + `marketing.empresas.gestionar` + los 4 `marketing.conversation.*`), y escribe `activity_log`
  (`log_name=marketing`, `event=equipo_fijado`, properties: admins, quitados, permisos_otorgados). Login inexistente
  → exit 1 sin cambiar nada. Nunca ids. **No se corrió contra `megaisp`.**

### Migración F1-2
`2026_11_13_100000_crea_permisos_faltantes_de_marketing.php`: 13 permisos, reparto directo con `givePermissionTo`
SOLO a super-administrator y DESARROLLADOR (no usa `PermissionSyncService::syncPermissionToBaseRoles` porque ese
reparte los `*.view` a todos los roles si `PERMISSION_SYNC_AUTO_GRANT_VIEW=true`). `down()` vacío (criterio de
catálogo). En `megaisp_test`: migrate OK, `rollback --step=1` OK, re-migrate OK; up() dos veces sin duplicar
(prueba). Verificado: `megaisp` sin esos permisos.

### Rutas: 134 (131 + 3). Sin `check_route_permission` en Blade `marketing/**` ni en `api/marketing/publishing/**`.

### F1-3 — funciones/rutas RETIRADAS (siguen registradas; responden 410 tras su validación/findOrFail)
| Método | URL | Nombre | Acción | Antes del 410 |
|---|---|---|---|---|
| GET | /marketing/stats | marketing.stats | MarketingController@stats | — |
| GET | /marketing/templates | marketing.templates.index | MarketingController@templates | — |
| POST | /marketing/templates/store | marketing.templates.store | MarketingController@storeTemplate | validación (422) |
| PUT | /marketing/templates/{id} | marketing.templates.update | MarketingController@updateTemplate | — (tabla no existe) |
| DELETE | /marketing/templates/{id} | marketing.templates.destroy | MarketingController@destroyTemplate | — (tabla no existe) |
| POST | /marketing/campaigns/table | marketing.campaigns.table | CampaignController@table | — |
| POST | /marketing/campaigns/store | marketing.campaigns.store | CampaignController@store | validación (422) |
| GET | /marketing/campaigns/{id} | marketing.campaigns.show | CampaignController@show | findOrFail (404) → **302 a /marketing** |
| PUT | /marketing/campaigns/{id} | marketing.campaigns.update | CampaignController@update | findOrFail (404) |
| DELETE | /marketing/campaigns/{id} | marketing.campaigns.destroy | CampaignController@destroy | findOrFail (404) |
| POST | /marketing/campaigns/{id}/approve | marketing.campaigns.approve | CampaignController@approve | findOrFail (404) |
| POST | /marketing/campaigns/{id}/pause | marketing.campaigns.pause | CampaignController@pause | findOrFail (404) |
| POST | /marketing/campaigns/{id}/activate | marketing.campaigns.activate | CampaignController@activate | findOrFail (404) |
| POST | /marketing/content/generate-copy | marketing.content.generate-copy | ContentGeneratorController@generateCopy | validación (422; campaña acotada a la empresa) |
| POST | /marketing/content/generate-image | marketing.content.generate-image | ContentGeneratorController@generateImage | validación (422) |
| POST | /marketing/content/approve/{id} | marketing.content.approve | ContentGeneratorController@approve | — |
| POST | /marketing/content/reject/{id} | marketing.content.reject | ContentGeneratorController@reject | — |
| GET | /marketing/content/campaign/{campaignId} | marketing.content.by-campaign | ContentGeneratorController@byCampaign | findOrFail (404) |
| POST | /marketing/leads-legacy/table | marketing.leads-legacy.table | LeadController@table | — |
| GET | /marketing/leads-legacy/{id} | marketing.leads-legacy.show | LeadController@show | findOrFail (404) |
| POST | /marketing/leads-legacy/{id}/qualify | marketing.leads-legacy.qualify | LeadController@qualify | findOrFail (404) |
| PUT | /marketing/leads-legacy/{id}/status | marketing.leads-legacy.update-status | LeadController@updateStatus | findOrFail (404) |
| POST | /marketing/leads-legacy/{id}/assign | marketing.leads-legacy.assign | LeadController@assign | findOrFail (404) |

Siguen sirviendo vista: `GET /marketing` (`marketing.index`) y `GET /marketing/campaigns` (`marketing.campaigns.index`).
Ya no se inyectan `AIContentService`, `ImageGeneratorService`, `LeadQualifierService`, `CampaignRepository` ni
`PublicationSchedulerService` en esos controladores (cero IA/HTTP).

### Notas para QA / frontend
- `generate-copy`: se quitó la regla `exists:marketing_templates,id` (la tabla no existe y la regla misma daba 500);
  queda `required|integer`.
- `mes=` vacío y `limite=` vacío → 422 (no se toman como «sin parámetro»). `limite=5 OR 1=1` lo corta el WAF global
  `SqlInjectionProtection` con 403 antes de validar (nunca 200).
- Gasto: si todas las campañas que califican son de otra moneda, `gasto_mxn.valor = null` (no «0.00») y se cuentan en
  `campanas_otra_moneda`. `leads` de campañas activas no cuenta leads con borrado lógico.
- Fase 0 que ahora fallan POR CONTRATO (dueño QA): `CapaEmpresaMiddlewareTest` (6: sesión elige empresa, ventas/
  marketing/config compartida — regla A/B), `RutasMarketingProtegidasTest` (2: total 131→134 y middleware previo
  `check_route_permission`), `MatrizRolesTest` (41: ventas/marketing ya no entran, empresa B activa ya no existe).
10:53 — correcciones de QA recibidas (1–5) · sigue: ContextoEmpresa cambiar, comando, migración preferred_voice_model, whereNumber, texto sin_empresa
10:56 — correcciones QA 1–5 aplicadas (cambiar no escribe otra empresa en sesión; comando da marketing.% + module.json permissions[] sin manage-integrations; +5 permisos declarados en module.json; migración preferred_voice_model; where numérico por grupo; texto sin_empresa); ajustada 1 prueba de QA (ContextoEmpresaApiTest texto_numerico, choca con regla A) · sigue: correr pruebas
11:00 — Correcciones de QA (docs/marketing/fase-1-qa-reporte.md) HECHAS:
  1) ContextoEmpresaController@cambiar: valida igual (422 / 404 si no es suya) y, si es suya, responde 200 con la
     empresa 1 y guarda SIEMPRE 1 en sesión (nunca otra). Ajustada QA Fase0/Qa/ContextoEmpresaApiTest::
     test_cambiar_acepta_company_id_como_texto_numerico (esperaba la B: choca con la regla A).
  2) marketing:equipo-fijar da marketing.% + todos los de module.json → permissions[] que existan, EXCEPTO
     manage-integrations (permiso del Hub de Integraciones de todo el sistema). Se declararon en module.json los 5
     que el módulo exigía sin declarar: view-marketing-campaigns, regenerate-marketing-variants, view-video-templates,
     view-video-content, test-voices. Pruebas: EquipoFijarBackendTest (+1) y QA EquipoFijarCommandTest (+1).
  3) Migración 2026_11_13_100100_agrega_preferred_voice_model_a_marketing_niches: varchar(30) NOT NULL DEFAULT 'tts-1'
     after preferred_voice_id, con guarda hasColumn (leído de megaisp con SHOW COLUMNS). down() vacío a propósito
     (en dev/prod la columna ya existía con datos). megaisp_test: migrate OK (la agregó), rollback OK, re-migrate
     no-op. Prueba MigracionVozNichoBackendTest.
  4) routes.php: $NUM = id/campaignId/sendId/userId → [0-9]{1,18}, con ->where($NUM) en los 4 grupos autenticados
     (no Route::pattern global). Solo quedan sin restricción {slug} y {niche} (cadenas). Prueba: barrido de todas las
     rutas con id malo (abc, 1abc, -1, 1.5, 20 dígitos) → 404, nunca 500.
  5) Texto sin_empresa → «No tienes acceso a Marketing. Pide acceso a un administrador de Marketing.» (middleware y
     sin-empresa.blade.php). Ajustadas por ese texto: Fase0/Backend/CapaEmpresaMiddlewareTest (2 aserciones),
     Fase0/Qa/MatrizRolesTest y Fase1/Qa/AccesoNegadoPorDefectoTest (constante MSG_SIN_EMPRESA).
  Pruebas por nombre, todas en verde: backend (8 archivos), QA Fase1 EquipoFijarCommand/RutasRetiradas/
  AccesoSinRutaPermiso/Navegacion/AccesoNegadoPorDefecto, Fase0 ContextoEmpresaApi/ContextoEmpresaBackend/
  RutasMarketingProtegidas/CapaEmpresaMiddleware/MatrizRoles.
12:14 — NUEVA TAREA §2.6.2 (restricción automática al aplicar la versión) sobre 98978ff71 · sigue: leer contrato e inventario marketing_settings
12:16 — §2.6.2: Support/EquipoMarketing (marca, permisos, lista, restringirALista), migración 100200, candado en ResolverEmpresaActiva, equipo-fijar escribe la marca, MarketingTestCase crea la marca · sigue: migrar en megaisp_test y pruebas nuevas
12:17 — megaisp_test: 100200 migrate/rollback/re-migrate OK (lista vacía, marca escrita); config:auditar-env 0; EquipoRestringidoBackendTest escrito · sigue: correr todo lo de acceso
12:20 — §2.6.2 TERMINADO (encima de 98978ff71, sin commit):
  - Nuevo Support/EquipoMarketing.php: marca (hayMarca/marcar/quitarMarca sobre marketing_settings company 1,
    key equipo_fijado_at, group acceso), permisos de Marketing (marketing.% + module.json sin manage-integrations,
    antes vivía en el comando), lista MARKETING_EQUIPO_LOGINS (Env::get; si la config estuviera cacheada, se lee el
    archivo .env con Dotenv::parse) y restringirALista() (transacción: desactiva —no borra— las demás filas de la
    empresa 1, admin activo + permisos a cada login existente, ignora inexistentes, marca, activity_log
    equipo_restringido con solo conteos).
  - Migración 2026_11_13_100200_restringe_equipo_marketing_a_lista_env.php: llama a restringirALista(); avisos en
    consola (lista vacía, logins ignorados); down() borra la marca.
  - ResolverEmpresaActiva: sin marca → 403 sin_acceso a todos (después de sesión/inactivo, antes de la regla B).
  - marketing:equipo-fijar --aplicar escribe/actualiza la marca.
  - tests/Feature/Marketing/MarketingTestCase.php: setUp crea la marca dentro de la transacción si no existe.
  - megaisp_test: migrate (MARKETING_EQUIPO_LOGINS vacía), rollback --step=1, re-migrate: OK; marca presente;
    config:auditar-env → 0. Ningún .env editado.
  - Pruebas por nombre en verde: Fase1/Backend (9 archivos, incl. nuevo EquipoRestringidoBackendTest 11/11),
    Fase0 MatrizRoles, CapaEmpresaMiddleware, MigracionAsignacion(Backend), ContextoEmpresaApi; Fase1/Qa
    AccesoNegadoPorDefecto, AccesoSinRutaPermiso, EquipoFijarCommand, RestriccionEquipoEnvTest (20/20).
