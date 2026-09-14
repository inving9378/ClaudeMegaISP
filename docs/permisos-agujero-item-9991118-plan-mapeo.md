# Plan de mapeo — Item #9991118: cerrar los 38 agujeros MEDIO (auditoría #9990745)

SOLO LECTURA / PLAN — no se tocó ninguna ruta, middleware ni permiso. Este documento es la
tabla ruta→permiso propuesto que el item #9991118 (según lo que Irving ya decidió en `q1`)
debe entregar y poner a su aprobación **antes** de que cualquier sub-item toque código.

Fuente: `docs/permisos-agujero-item-9990745-auditoria-final.md`, secciones 1, 2 y 3 (38 rutas
MEDIO: 20 en `routes/web.php`, 6 en MegaFamilia, 12 en Marketing).

Decisiones ya aprobadas por Irving en #9991118 (para que el ejecutor de cada sub-item no las
vuelva a preguntar):
- **q2** — criterio de asignación: reutilizar el permiso existente del módulo dueño de la ruta
  cuando exista un mapeo claro; solo proponer permiso nuevo si de verdad no hay ninguno.
- **q3** — antes de integrar cada lote: regresión + smoke manual de los roles principales
  (admin, soporte, ventas, cliente) tocados por ese módulo.
- **q4** — un sub-item por módulo afectado (3 sub-items: `routes/web.php`, MegaFamilia,
  Marketing), cada uno con su propia rama/commit.

Metodología de esta tabla: para cada ruta se buscó (1) si ya existe una entrada en
`config/route_permission.php` que la liste (en cuyo caso el único fix es envolverla en el
middleware `check_route_permission`, sin tocar el config), y si no, (2) si existe un permiso ya
usado por el mismo controller/módulo que encaje por significado. Cuando ninguna de las dos
aplica limpio, se marca **AMBIGUO** en vez de forzar un mapeo dudoso — decisión explícita de
Irving en `q2` ("puede quedar granularidad gruesa... pero reutilizar, no inflar").

---

## 1. `routes/web.php` (20 rutas MEDIO)

Todas viven hoy dentro de `Route::group(['middleware' => ['auth']], ...)` (líneas 60-214),
**fuera** del sub-grupo `check_route_permission` (líneas 73-125, que además hoy solo contiene
comentarios de rutas ya migradas a otros módulos — está vacío de rutas reales). El fix de forma
para todas: mover cada línea al grupo `check_route_permission` (o envolverla con
`->middleware('check_route_permission')`), que consume `config/route_permission.php`.

| # | Ruta | Controller@método | Permiso propuesto | Tipo de fix |
|---|---|---|---|---|
| 1 | `POST /crm/send-notification/{id}` | `Utils\NotificationController@sendNotificationCrm` | `crm_document_view_crm` **(ya existe la entrada en config línea 62, incluye este path)** | Solo mover al grupo `check_route_permission` — sin tocar config |
| 2 | `GET /log-client` | `TestScriptController@logClient` | `admin_view_information` (ya cubre `/administracion/activity_log` y `/activity_log/table`, mismo dominio: activity log) | Reusar existente |
| 3 | `POST /get-log-activities/{id}` | `Utils\LogActivityController@getLogActivities` | `admin_view_information` | Reusar existente |
| 4 | `GET /get-all-activities` | `@getAllActivities` | `admin_view_information` | Reusar existente |
| 5 | `GET /get-activities-by-user/{id}` | `@getActivitiesByUser` | `admin_view_information` | Reusar existente |
| 6 | `GET /admin/consumo` | `InternetConsumptionRadiusController@index` | `client_view_client` (consumo RADIUS es dato de cliente, mismo permiso que el resto de la ficha/estadísticas) | Reusar existente |
| 7 | `GET /admin/consumo/{username}` | `@show` | `client_view_client` | Reusar existente |
| 8 | `POST /get-options-client` | `SearchModelController@longOptionsClient` | `client_view_client` | Reusar existente |
| 9 | `POST /get-options-client-task` | `@longOptionsClientTask` | `client_view_client` | Reusar existente |
| 10 | `POST /get-options-client-prospect` | `@longOptionsClientProspect` | `client_view_client` (o `crm_view_crm` si el criterio real es "ver prospecto"; **AMBIGUO — decidir cuál pesa más** entre cliente y CRM) | Reusar existente, confirmar cuál |
| 11 | `POST /get-crm-client-if-exist` | `HelperController@getCrmClientIfExist` | `crm_view_crm` | Reusar existente |
| 12 | `POST /fullcalendar/get-task-events` | `Utils\FullcalendarController@getTaskEvents` | `scheduling_view_calendar` (mismo dominio que `/scheduling/task/calendar`) | Reusar existente |
| 13 | `POST /get-data/{module}` | `HelperController@getData` | `router_view_router` **AMBIGUO** — el audit dice "datos de infraestructura de red (router) por id arbitrario"; confirmar que `{module}` siempre resuelve a router antes de gatear con este permiso (si puede apuntar a otros módulos, el permiso fijo sería incorrecto) | Confirmar alcance antes de mapear |
| 14 | `GET /perfil/{id}` | `UserController@show` | **AMBIGUO** — shell de vista de otro usuario sin ownership; candidato natural es un permiso de administración de usuarios (`user_view_user` o equivalente) — verificar nombre exacto en el módulo `Core/Usuarios` antes de aplicar | Requiere confirmar permiso exacto de Usuarios |
| 15 | `GET /perfil/editar/{id}` | `@edit` | Igual que #14 | Igual |
| 16 | `POST /get-options-select` | `SearchModelController@search` | **AMBIGUO — no mapear a un solo permiso.** Es un buscador genérico sobre modelos allowlisted (usado por decenas de pantallas de módulos distintos); gatearlo con un permiso fijo sería demasiado amplio o demasiado estrecho según el modelo consultado. Requiere diseño propio (permiso por-modelo vía la allowlist de `QueryGuard`), fuera del patrón 1-ruta-1-permiso de este item | Diseño propio, no mapeo simple |
| 17 | `POST /get-options-select/{id}` | `@searchWithoutId` | Igual que #16 | Igual |
| 18 | `POST /get-long-options-select` | `@longOptions` | Igual que #16 | Igual |
| 19 | `POST /save-or-delete-default-value` | `SharedDefaultValues@saveOrDeleteDefaultValue` | **AMBIGUO** — escribe/borra un `DefaultValue` de alcance no declarado; no hay permiso existente obvio | Requiere definir alcance primero |
| 20 | `GET /notification-email` | closure anónimo | **Candidato a remover, no a gatear.** Envía un mail hardcodeado a `TaskNotification::find(1)` — huele a ruta de debug/prueba manual que quedó viva, no a una pantalla real en uso. Antes de proponerle permiso, confirmar con `grep` que nada la enlaza desde el frontend; si es así, se borra (código muerto) en vez de protegerse | Verificar uso real primero |

## 2. MegaFamilia (6 rutas MEDIO)

**Distinto tipo de remediación** — no es "falta un permiso Spatie", es "falta aplicar el mismo
guard de ownership que el propio controller ya usa en sus otros métodos". Las rutas viven a
propósito fuera de los dos sub-grupos con `permission:megafamilia_admin`/`megafamilia_support`
(comentario del código: *"Cliente final (solo auth, sin permission extra)"* — es el portal del
cliente, scoped por su propia `ParentalAccount`, no por un rol de staff). Gatearlas con
`megafamilia_admin` sería un downgrade de producto (le quitaría el acceso a los clientes reales
del portal), no el fix correcto.

| # | Ruta | Controller@método | Fix propuesto |
|---|---|---|---|
| 1 | `GET /megafamilia/perfiles/data` | `PerfilesController@data` | Aplicar el mismo criterio que `guardOwnership()` (ya existe en el mismo archivo, líneas 96-106): si el usuario no tiene `ParentalAccount` propia, NO debe caer al `else` que devuelve TODOS los perfiles — debe exigir `megafamilia_admin` (igual que hace `guardOwnership` en el caso "sin cuenta") en vez de devolver todo sin filtro |
| 2 | `GET /megafamilia/perfiles/{id}` | `@show` | Aplicar `guardOwnership($profile)` (ya existe, solo falta la llamada en este método — mismo patrón que `update`/`destroy` en el mismo archivo) |
| 3 | `GET /megafamilia/tareas/data` | `TareasController@data` | Mismo patrón: filtrar por `account_id` del usuario (vía `accountForCurrentUser()`, ya existe en el archivo) cuando `profile_id` no viene, en vez de devolver todas las tareas de todos los perfiles |
| 4 | `GET /megafamilia/reportes/data` | `ReportesController@data` | `ReportesController` **no tiene ningún método de scoping propio hoy** (a diferencia de Perfiles/Tareas) — hay que sumarle su propio `accountForCurrentUser()`/`guardOwnership` (mismo patrón que los otros 2 controllers) y aplicarlo aquí |
| 5 | `GET /megafamilia/reportes/profiles` | `@profiles` | Igual que #4 |
| 6 | `GET /megafamilia/reportes/export` | `@export` | Igual que #4 |

## 3. Marketing (12 rutas MEDIO)

Viven en `Route::middleware(['web','auth'])->prefix('api/marketing')` (líneas 51-145 de
`app/Modules/Addons/Marketing/routes.php`), **sin** `check_route_permission` — mientras que las
vistas Blade hermanas (`/marketing/conversations`, `/marketing/pilot-campaigns`, líneas 147+)
**sí** están envueltas en `check_route_permission`. El agujero real es que la API detrás de esas
pantallas no exige el mismo candado que la pantalla.

| # | Ruta | Controller@método | Permiso propuesto | Tipo de fix |
|---|---|---|---|---|
| 1 | `GET /api/marketing/generated-content/{id}/download` | `MarketingGeneratedContentController@download` | `view-video-content` **(ya existe y ya se aplica a `index`/`show`/`progress` del mismo controller vía `$this->middleware('permission:view-video-content')->only([...])`, línea 20 — solo falta sumar `'download'` a ese `->only()`)** | Fix de una línea, permiso ya existente |
| 2 | `GET /api/marketing/conversations` | `MarketingConversationController@index` | **Sin permiso existente que calce.** No hay ninguna entrada en `config/route_permission.php` para `/marketing/*` (ni la vista Blade `/marketing/conversations` tiene una — su `check_route_permission` hoy resuelve fail-closed a solo admin/DESARROLLADOR mientras no se registre). `module.json` del addon tampoco declara un permiso de "ver conversaciones". **Requiere permiso nuevo** (p.ej. `view-marketing-conversations`) — decisión de Irving: a qué roles se lo asigna (soporte/ventas que atienden WhatsApp real) | Permiso nuevo |
| 3 | `GET /api/marketing/conversations/{id}` | `@show` | Igual que #2 | Igual |
| 4 | `GET /api/marketing/conversations/{id}/messages` | `@messages` | Igual que #2 | Igual |
| 5 | `POST /api/marketing/conversations/{id}/mark-as-read` | `@markAsRead` | Igual que #2 (mismo permiso de lectura basta, es de bajo impacto) | Igual |
| 6 | `GET /api/marketing/pilot-campaigns` | `PilotCampaignController@index` | **Sin permiso existente.** Mitigado (opera sobre lista dummy documentada, nunca clientes reales) pero igual sin gate. Candidato: reusar `create-marketing-campaigns` (ya declarado en `module.json`, cubre la vista Blade hermana `/marketing/pilot-campaigns`) — es el permiso más cercano por dominio | Reusar `create-marketing-campaigns` (module.json) |
| 7 | `POST /api/marketing/pilot-campaigns` | `@store` | Igual que #6 | Igual |
| 8 | `GET /api/marketing/pilot-campaigns/{id}` | `@show` | Igual que #6 | Igual |
| 9 | `DELETE /api/marketing/pilot-campaigns/{id}` | `@destroy` | Igual que #6 | Igual |
| 10 | `POST /api/marketing/pilot-campaigns/{id}/dry-run` | `@dryRun` | Igual que #6 | Igual |
| 11 | `POST /api/marketing/pilot-campaigns/{id}/send` | `@send` | Igual que #6 | Igual |
| 12 | `POST /api/marketing/pilot-campaigns/{id}/sends/{sendId}/mark-converted` | `@markConverted` | Igual que #6 | Igual |

---

## Resumen de confianza (para la revisión de Irving)

| Confianza | Rutas | Motivo |
|---|---|---|
| **Alta** (permiso ya existe, solo falta envolver/registrar) | web.php #1, #2-5, #6-7, #8-9, #11-12; Marketing #1 | Mapeo directo a un permiso que ya protege exactamente ese dominio |
| **Media** (permiso existente por analogía, criterio razonable) | web.php #10; Marketing #6-12 (`create-marketing-campaigns`) | El permiso existe y encaja por dominio, pero no fue diseñado literalmente para esa ruta — pedir confirmación |
| **Baja / requiere decisión previa** | web.php #13-15, #19-20 | Necesitan una decisión de alcance o verificación de uso antes de poder proponer permiso |
| **Fuera de este patrón (diseño propio)** | web.php #16-18 | Buscador genérico multi-modelo; un solo permiso fijo sería el mapeo incorrecto |
| **Permiso nuevo necesario** | Marketing #2-5 | No existe ningún permiso de "ver conversaciones de WhatsApp" en el sistema hoy |
| **No es mapeo de permiso, es scoping** | MegaFamilia #1-6 | El fix correcto es aplicar el guard de ownership que el propio controller ya tiene, no asignar un rol/permiso Spatie |

## Próximo paso

Por cada módulo (routes/web.php, MegaFamilia, Marketing) se abre su propio sub-item de
ejecución, con esta tabla como spec. Ninguno toca código hasta que Irving apruebe (o corrija)
el mapeo de arriba — así lo decidió él mismo en `q1` de este item.
