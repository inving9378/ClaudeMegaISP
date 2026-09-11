# Inventario de rutas sin protección de permiso — Lote 1/3 (Core + routes/web.php)

Item #9990769 (sub-item de #9990764 → #9990745). **SOLO LECTURA**: este documento no modifica
rutas, middleware, config ni permisos — es un inventario para que Irving decida qué cerrar y en
qué orden.

## Cómo leer este documento

- **Clasificación 1 — PROTEGIDA**: dentro de un grupo `check_route_permission`. El middleware
  exige que el usuario tenga (directo o por rol) el permiso cuyo patrón matchea la URI en
  `config/route_permission.php`; si nadie declaró un patrón para esa URI, el acceso se **deniega**
  (no pasa gratis) — salvo admin/DESARROLLADOR/super-admin, que siempre pasan.
- **Clasificación 2 — GATEADA POR ROL/GUARD**: `role:...` de Spatie, `can:permiso` inline en la
  ruta, o un guard dedicado (`auth:sanctum`, etc.). No es agujero, se documenta el candado.
- **Clasificación 3 — AUTHORIZE/SCOPING INLINE**: sin `check_route_permission`, pero el método del
  controller aplica su propio candado (literal `->authorize()`/`Gate::`, o auto-scoping fuerte por
  `auth()->id()`/`Auth::user()` que hace que el parámetro de la ruta sea irrelevante). Se documenta
  el mecanismo — no es agujero, aunque no sea el patrón central del sistema.
- **Clasificación 4 — AGUJERO REAL**: solo `auth` (o nada), sin 1/2/3. Riesgo:
  **CRÍTICO** (modifica datos/dinero/credenciales), **MEDIO** (lectura sensible/cruza usuarios),
  **BAJO** (lectura pública-adyacente o auto-scoping débil sin literal authorize).
- **Clasificación 5 — PUBLIC_ROUTES explícita**: matchea `CheckRoutePermission::PUBLIC_ROUTES` — a
  propósito, no se reporta como hallazgo.

`check_route_permission` = `App\Modules\Core\Auth\Middleware\CheckRoutePermission`. Antes de
evaluar nada, esa clase compara la URI contra su lista `PUBLIC_ROUTES` (~19 patrones) y la deja
pasar sin sesión si matchea — **incluso si la ruta está declarada dentro de un grupo
`check_route_permission`** (ver nota en Documentos, más abajo: 3 rutas están "protegidas" en el
routes.php pero el propio middleware las trata como públicas).

---

## Resumen ejecutivo (solo este lote — Core + web.php)

| Riesgo | Cantidad | Dónde |
|---|---|---|
| 🔴 CRÍTICO | **7** | Todas en `routes/web.php`, todas alcanzables con solo `auth` (cualquier rol, incluido `client`) |
| 🟠 MEDIO | ~28 | Mayormente `routes/web.php` (HelperController/SearchModelController/Utils/*) |
| 🟡 BAJO | ~20 | `routes/web.php` (helpers triviales, auto-scoping débil, endpoints rotos) |
| 🔵 Nota / anomalía (no es de permisos) | 3 | 2 rutas rotas (método inexistente), 1 ruta pública sin documentar |
| ✅ PROTEGIDA / GATEADA (1-2-3) | ~230 | Los otros 14 archivos del lote están, con 4 excepciones puntuales documentadas, 100% envueltos en `check_route_permission`, `role:`, `permission:`/`can:`, `auth:sanctum` o auto-scoping fuerte |

**Hallazgo central del lote:** los 14 archivos de módulo (`Usuarios`, `Clientes`, `Configuracion`,
`Localizacion`, `CRM`, `Auth`, `Documentos`, `Documentacion`, `Release`, `Dashboard`,
`ModuleManager`, `Auditoria`, `Layout`, `Notifications`) están, con pocas excepciones ya
documentadas y justificadas en el propio código, correctamente envueltos. **Todo el riesgo real
del lote está concentrado en el bloque de `routes/web.php:60-227`** — el remanente de utilería
legacy (`HelperController`, `SearchModelController`, `Utils\*`, `SharedDefaultValues`, y sobre
todo el `App\Http\Controllers\UserController` legacy de `/perfil/*`) que quedó fuera del
sub-grupo `check_route_permission` cuando los módulos de negocio se migraron y ese sub-grupo se
vació (líneas 73-125, hoy solo tiene comentarios).

### 🔴 Los 7 CRÍTICOS (orden de severidad)

1. **`POST /perfil/update/{id}`** → `App\Http\Controllers\UserController@update`
   (`routes/web.php:177`) — **el más grave del lote**. Actualiza CUALQUIER usuario por `id`
   arbitrario, sin ownership check. `PerfilUpdateRequest::authorize()` devuelve `true` a secas
   (`app/Http/Requests/module/perfil/PerfilUpdateRequest.php:18`) y sus `rules()` marcan
   `password` como **`required`** — es decir, la ruta espera y hashea una password nueva en
   *cada* llamada (`PasswordService::make`, línea 96 del controller) y la guarda en el usuario
   objetivo. Cualquier usuario autenticado (incluidas cuentas espejo rol `client`) que conozca o
   adivine un `id` de `users` puede tomar control de esa cuenta. Solo middleware `auth`.
2. **`GET /data-client-mikrotik/{id}`** → `TestScriptController@getDataToMikrotikByClientId`
   (`routes/web.php:64`, método en `app/Http/Controllers/TestScriptController.php:2149`) — expone
   el **password PPP en texto plano** del secret de Mikrotik del cliente (`$ipData['password']`).
   Solo `auth`.
3. **`POST /data-client-mikrotik-by-ip`** → `TestScriptController@getDataToMikrotikByIp`
   (`routes/web.php:65`, método línea 2198) — mismo leak de password PPP, buscando por IP en vez
   de `client_id` (además revela a qué cliente pertenece esa IP). Solo `auth`.
4. **`GET /script`** → `TestScriptController@script` (`routes/web.php:61`, método línea 2034) —
   ejecuta `DB::unprepared()` que hace `DROP TRIGGER IF EXISTS` + `CREATE TRIGGER` sobre la tabla
   `payments` **en cada request**, y llama `dd()` (debug leftover, nunca debería estar en una ruta
   viva). Cualquier usuario autenticado puede disparar DDL contra la base de producción/dev. Solo
   `auth`.
5. **`GET /libera-ip/{id}`** → `TestScriptController@liberaIp` (`routes/web.php:66`, método línea
   2252) — hace `UPDATE` sobre `NetworkIp` (libera cualquier IP: `used=false, client_id=null`) para
   el `id` que se pida, sin validar que exista relación con el usuario. Solo `auth`.
6. **`POST /save-random-password`** → `HelperController@saveRandomPassword`
   (`routes/web.php:159`, método línea 229) — recibe `module` (nombre), `id`, `field`, `val` del
   request y llama `$module->saveRandomPasswordByIdAndField($id, $field, $val)`: escritura de un
   campo arbitrario (declarado como password) en un registro arbitrario de un módulo arbitrario,
   sin ninguna validación de que ese módulo/campo/id le pertenezcan al usuario. Solo `auth`.
7. **`POST /perfil/get-perfil-by-id/{id}`** → `UserController@getPerfilById`
   (`routes/web.php:180`, método línea 144) — `return $model` del `User::find($id)` completo
   (IDOR: cualquier usuario autenticado puede leer el registro `users` de cualquier otro por id;
   el JSON solo se filtra por `$hidden` del modelo `User`, no por autorización). Riesgo depende de
   qué tan sensible sea lo que `$hidden` NO cubra — se marca CRÍTICO por precaución (mismo
   controller que el #1).

---

## 1. `routes/web.php` (265 líneas)

### 1.1 — Rutas sueltas (fuera de cualquier grupo `auth`)

| Método | URI | Controller@método | Línea | Clasificación | Nota / riesgo |
|---|---|---|---|---|---|
| GET | `/csrf-refresh` | closure | 56-58 | 5-equivalente | Pública **a propósito** (comentario explícito en el código): el token CSRF no es secreto, sirve a invitado y autenticado. Middleware `web,throttle:60,1`. No está en el const `PUBLIC_ROUTES` de la clase (no lo necesita, no pasa por `check_route_permission`). |
| GET | `/notification-email` | closure (`TaskNotification::find(1)` + `StandardNotification::toMail`) | 223-227 | **4 — AGUJERO REAL** | **MEDIO.** Sin NINGÚN middleware (ni `auth`, ni `web` explícito aparte del grupo global de `RouteServiceProvider`). Envía/renderiza un `MailMessage` con datos de la notificación/tarea/usuario id=1, alcanzable sin login. Huele a código de debug olvidado — no tiene `name()`, no se referencia desde ningún frontend. |

### 1.2 — `Route::group(['middleware' => ['auth']])` (líneas 60-214)

Sub-grupo interno `check_route_permission` (líneas 73-125): **vacío** — solo comentarios que
documentan a qué módulo se migró cada bloque (Configuracion, Localizacion, Auditoria, Documentos,
Documentacion, Inventario, Vendedores, Mensajes, Planes, Tickets, CRM, Mapas, OLTs, Finanzas,
Release, DevTools, Scheduling, GestionRed). **0 rutas activas ahí** — no hay nada que clasificar
dentro de ese sub-grupo hoy.

Todo lo que sigue está SOLO bajo `auth` (fuera del sub-grupo `check_route_permission`):

| Método | URI | Controller@método | Línea | Clasificación | Riesgo |
|---|---|---|---|---|---|
| GET | `/script` | `TestScriptController@script` | 61 | **4** | **CRÍTICO** — ver hallazgo #4 arriba |
| GET | `/log-client` | `TestScriptController@logClient` | 62 | **4** | MEDIO — lee `Activity` (audit log) filtrado por rango de fechas + `client_id` opcional, sin scoping ni permiso |
| GET | `/information-dev` | `TestScriptController@getInformationDevelopment` | 63 | **4** | CRÍTICO — vuelca HTML con id/nombre/email/balance/billing de TODOS los clientes (`InformationService::getInformation()`), sin paginar |
| GET | `/data-client-mikrotik/{id}` | `TestScriptController@getDataToMikrotikByClientId` | 64 | **4** | **CRÍTICO** — leak de password PPP, ver hallazgo #2 |
| POST | `/data-client-mikrotik-by-ip` | `TestScriptController@getDataToMikrotikByIp` | 65 | **4** | **CRÍTICO** — ver hallazgo #3 |
| GET | `/libera-ip/{id}` | `TestScriptController@liberaIp` | 66 | **4** | **CRÍTICO** — ver hallazgo #5 |
| GET | `/admin/consumo` | `InternetConsumptionRadiusController@index` | 68-69 | **4** | MEDIO — consumo RADIUS (IP, upload/download, sesiones) de TODOS los clientes, sin permiso |
| GET | `/admin/consumo/{username}` | `InternetConsumptionRadiusController@show` | 70-71 | **4** | MEDIO — detalle de sesiones RADIUS por `username` arbitrario |
| POST | `/cliente/get-receipt-for-client` | `Utils\ReceiptController@getReceiptForClient` | 127 | **4** | BAJO — solo devuelve un contador/folio (`fecha+count`), sin datos de cliente |
| POST | `/get-payment-period` | `Utils\UtilController@getPaymentPeriod` | 128 | **4** | BAJO — texto fijo ("mes actual - mes siguiente"), sin datos |
| GET | `/setting-table/get/{table_id}` | `SettingTableController@get` | 131 | **4** | BAJO — auto-scoping por `Auth::user()->id` dentro del método (el `{table_id}` no permite leer ajeno) |
| POST | `/setting-table/post/{table_id}` | `SettingTableController@store` | 132 | **4** | BAJO — mismo auto-scoping (`updateOrCreate(['user_id'=>...])`) |
| POST | `/user/get-next-user` | `HelperController@getNextUserId` | 138 | **4** | BAJO — devuelve solo `User::count()` |
| POST | `/get-data/{module}` | `HelperController@getData` | 140 | **4** | MEDIO — limitado a 2 módulos hardcodeados (`Partner`,`Location`), expone datos de infraestructura de red (router) por `id` arbitrario |
| POST | `/fields-by-module` | `HelperController@getFieldsByModule` | 141 | **4** | BAJO — metadata de formulario (`Module::getfields()`), no datos de negocio |
| POST | `/fields-by-module-and-relation` | `HelperController@getFieldsByModuleRelation` | 143 | **4** | BAJO — igual, metadata |
| POST | `/fields-by-module-with-module-requested` | `HelperController@getFieldsByModuleWithModuleRequested` | 144 | **4** | BAJO — igual |
| POST | `/fields-by-module/{id}` | `HelperController@getFieldsEditedById` | 146 | **4** | BAJO — metadata de un registro editado (config de formulario, no el registro) |
| POST | `/fields-by-module/general/edited` | `HelperController@requestGeneralEditedFields` | 147 | **4** | BAJO |
| POST | `/columns-by-module` | `HelperController@getColumnsByModule` | 148 | **4** | BAJO — definición de columnas del datatable de cualquier módulo por nombre (metadata, no filas) |
| POST | `/column-expand-by-module` | `HelperController@getColumnDtExpandByModule` | 149 | **4** | BAJO — auto-scoping por `auth()->user()->id` |
| POST | `/set-column-expand-by-module` | `HelperController@setColumnDtExpandByModule` | 150 | **4** | BAJO — mismo auto-scoping (write) |
| POST | `/columns-by-module-except-columns` | `HelperController@getColumnsByModuleExceptColumns` | 151 | **4** | BAJO — metadata |
| POST | `/all-columns-by-module` | `HelperController@getAllColumnsByModule` | 154 | **4** | BAJO — metadata, cacheada 24h |
| POST | `/update-column-by-user` | `HelperController@updateColumnsByUser` | 155 | **4** | BAJO — auto-scoping por `getUserAuthenticated()` |
| POST | `/request-random-password` | `HelperController@getRandomPassword` | 158 | **4** | BAJO — genera y devuelve una password candidata, no la persiste ni la asocia a nadie |
| POST | `/save-random-password` | `HelperController@saveRandomPassword` | 159 | **4** | **CRÍTICO** — ver hallazgo #6 |
| POST | `/request-generate-user/{username}` | `HelperController@getGenerateUser` | 160 | **4** | BAJO — genera candidato de `login_user`, no crea la cuenta |
| POST | `/request-generate-user-exist` | `HelperController@getGenerateUserExist` | 161 | **4** | BAJO — igual |
| POST | `/get-options-team` | `HelperController@getOptionsTeam` | 163 | **4** | BAJO-MEDIO — lista equipos/usuarios agrupados (org interna, no PII de clientes) |
| POST | `/get-options-select` | `SearchModelController@search` | 165 | **4** | MEDIO — búsqueda genérica sobre modelos allowlisted (protegida contra SQLi por `QueryGuard`, ver memoria `project_sqli_searchmodel`, pero SIN gating de permiso: cualquier rol autenticado puede consultar cualquier modelo/columna de la allowlist) |
| POST | `/get-options-select/{id}` | `SearchModelController@searchWithoutId` | 166 | **4** | MEDIO — igual |
| POST | `/get-long-options-select` | `SearchModelController@longOptions` | 167 | **4** | MEDIO — igual |
| POST | `/get-options-client` | `SearchModelController@longOptionsClient` | 168 | **4** | MEDIO — búsqueda de clientes sin permiso `client_view` |
| POST | `/get-options-client-task` | `SearchModelController@longOptionsClientTask` | 169 | **4** | MEDIO |
| POST | `/get-options-client-prospect` | `SearchModelController@longOptionsClientProspect` | 170 | **4** | MEDIO |
| GET | `/perfil/{id}` | `App\Http\Controllers\UserController@show` | 175 | **4** | MEDIO — renderiza shell de vista para `id` arbitrario, sin ownership |
| GET | `/perfil/editar/{id}` | `UserController@edit` | 176 | **4** | MEDIO — igual |
| POST | `/perfil/update/{id}` | `UserController@update` | 177 | **4** | **CRÍTICO** — ver hallazgo #1 |
| POST | `/perfil/update-image/{id}` | `UserController@updateImage` | 178 | **4** | BAJO — cuerpo del método 100% comentado (no-op) |
| POST | `/perfil/get-perfil-by-id/{id}` | `UserController@getPerfilById` | 179 | **4** | **CRÍTICO** — ver hallazgo #7 |
| GET | `/profile/password` | `Usuarios\UserController@showPasswordForm` | 185 | **3** | Self-service documentado: solo renderiza el form propio, sin parámetro de id |
| POST | `/profile/change-password` | `Usuarios\UserController@changePassword` | 186 | **3** | `Auth::user()` + verifica `current_password` con `PasswordService::check` antes de escribir — auto-scoping real, no toma `id` de la request |
| POST | `/get-user-authenticated` | `HelperController@getUserAuthenticated` | 188 | **4** | BAJO — devuelve el propio id (`Auth::user()->id`) |
| POST | `/get-default-value/{date}` | `Utils\DefaultValueController@getDefaultValue` | 190 | **4** | BAJO — solo fecha/hora del servidor |
| POST | `/get-default-billing-date-for-client` | `Utils\DefaultValueController@getDefaultBillingDateForClient` | 191 | **4** | BAJO — deriva el `client_id` de la URL previa en sesión, no de un parámetro controlable |
| POST | `/cliente/get-user-for-client` | `Utils\DefaultValueController@getDefaultValueForUserClient` | 193 | **4** | BAJO — mismo patrón (deriva de `_previous` de sesión) |
| POST | `/crm/send-notification/{id}` | `Utils\NotificationController@sendNotificationCrm` | 195 | **4** | MEDIO — envía correo a CUALQUIER dirección de email que venga en el body, adjuntando datos del CRM `{id}`; abuso posible como relay de spam/phishing usando el remitente del sistema |
| POST | `/get-log-activities/{id}` | `Utils\LogActivityController@getLogActivities` | 196 | **4** | MEDIO — activity log por `{id}` sin permiso |
| GET | `/get-all-activities` | `Utils\LogActivityController@getAllActivities` | 197 | **4** | MEDIO — **vuelca el activity log COMPLETO del sistema** (todos los usuarios), sin paginar ni permiso |
| GET | `/get-activities-by-user/{id}` | `Utils\LogActivityController@getActivitiesByUser` | 198 | **4** | MEDIO — IDOR: actividad de cualquier `{id}` de usuario |
| POST | `/fullcalendar/get-billing-configuration` | `Utils\FullcalendarController@getBillingConfiguration` | 199 | **4** | BAJO |
| POST | `/fullcalendar/get-task-events` | `Utils\FullcalendarController@getTaskEvents` | 200 | **4** | MEDIO — eventos de tareas/calendario, alcance no verificado (posible cruce entre usuarios) |
| POST | `/get-crm-client-if-exist` | `HelperController@getCrmClientIfExist` | 202 | **4** | MEDIO — oráculo de existencia por email/teléfono/nombre (permite enumerar prospectos/clientes) |
| POST | `/save-or-delete-default-value` | `SharedDefaultValues@saveOrDeleteDefaultValue` | 203 | **4** | MEDIO — escribe/borra un `DefaultValue`; alcance (¿global o por usuario/módulo?) no queda claro del propio método, delega en `DefaultValueService` |
| POST | `/get-default-fields-value` | `SharedDefaultValues@getDefaultFieldsValue` | 204 | **4** | BAJO |
| POST | `/getDataTable` | `Controller@getDataToTable` | 207 | **4 (ruta rota)** | El método `getDataToTable` **no existe** en `App\Http\Controllers\Controller` ni en ningún trait del repo (`grep` sin resultados) — la ruta produce error si se invoca. Anomalía, no agujero de permisos activo. |
| GET | `/read-all-notifications` | `Utils\NotificationController@readAll` | 208 | **3** | Auto-scoping real: `$this->userAutenticated()->unreadNotifications` |
| GET | `/read-notification/{id}` | `Utils\NotificationController@readNotification` | 209 | **3** | Auto-scoping real: busca `{id}` SOLO dentro de `$user->unreadNotifications` — un id ajeno no hace nada |
| GET | `/notifications/count` | `Utils\NotificationController@count` | 211 | **3** | Auto-scoping real |

### 1.3 — Fuera de cualquier grupo `auth` (después de la línea 214)

| Método | URI | Controller@método | Línea | Clasificación | Riesgo |
|---|---|---|---|---|---|
| GET | `/register-vendor` | `RegisterVendorController@index` | 218-219 | **Sin middleware — pública de facto** | BAJO. Sin `auth` ni `web` explícito (hereda el grupo `web` global de `RouteServiceProvider`). No está en `PUBLIC_ROUTES` ni documentada como intencional en este archivo — parece un formulario de auto-registro de vendedor, pero no hay comentario que lo confirme. Solo renderiza una vista. |
| POST | `/register-vendor/register` | `RegisterVendorController@register` | 220 | **Ruta rota** | El método `register()` **no existe** en `RegisterVendorController` (solo tiene `index()`) — produce error si se invoca. Anomalía, no agujero de permisos activo. |
| POST | `/ia/chat` | `IAChatController@chat` | 234-236 | **2** | Gateada: `middleware(['auth','can:usar-ia-chat'])` |
| GET | `/ia/suggestions` | `IAChatController@suggestions` | 240-242 | **2** | Gateada: mismo `can:usar-ia-chat` |
| GET | `/red/ipv6-config` | `Ipv6ConfigController@index` | 255 | **2** | `role:super-administrator\|DESARROLLADOR` |
| GET | `/red/ipv6-config/routers` | `Ipv6ConfigController@routers` | 256 | **2** | Mismo rol |
| POST | `/red/ipv6-config/detectar-version` | `Ipv6ConfigController@detectarVersion` | 257 | **2** | Mismo rol |
| POST | `/red/ipv6-config/mapear-zonas` | `Ipv6ConfigController@mapearZonas` | 258 | **2** | Mismo rol |
| POST | `/red/ipv6-config/vista-previa` | `Ipv6ConfigController@vistaPrevia` | 259 | **2** | Mismo rol |
| GET | `/red/ipv6-config/simulador` | `Ipv6ConfigController@simulador` | 264 | **2** | Mismo rol |

También sueltas dentro del bloque `auth`-only inicial (antes del grupo grande, líneas 44-58):

| Método | URI | Controller@método | Línea | Clasificación | Riesgo |
|---|---|---|---|---|---|
| GET | `/sin-modulos` | closure (vista estática) | 44-46 | **4** | BAJO — página informativa fija, sin datos, para cualquier autenticado |

---

## 2. `app/Modules/Core/Usuarios/routes.php` (89 líneas)

Grupo principal `web,auth,check_route_permission` bajo prefix `administracion` (líneas 22-80):
**Clasificación 1 (PROTEGIDA)** para las 26 rutas de `/administracion/{user,addresses,rol,permisos}/*`:

| Método | URI | Controller@método | Línea |
|---|---|---|---|
| GET | `/administracion/` | `AdministracionController@index` | 25 |
| GET | `/administracion/clean-all-client-service` | `@clearAllClientServices` | 26 |
| GET | `/administracion/add-clients-imported-to-mikrotik` | `@addClientsImportedToMikrotik` | 27 |
| GET | `/administracion/set-state-municipality-and-colony` | `@setStateMunicipalitiesAndColonies` | 28 |
| GET | `/administracion/suspend_clients` | `@suspendProcess` | 29 |
| GET | `/administracion/billing_services` | `@billigProcess` | 30 |
| POST | `/administracion/active-schedule-process` | `@activeCommands` | 31 |
| GET | `/administracion/check-schedule-process` | `@checkProcess` | 32 |
| GET | `/administracion/show_scripts` | `@showScripts` | 33 |
| GET | `/administracion/rectify_address_list` | `@rectifyAddressList` | 34 |
| GET | `/administracion/billing_services_to_client_active_promise_payment` | `@billingServiceToClientActivePromise` | 35 |
| GET | `/administracion/user/` | `UserController@index` | 38 |
| GET | `/administracion/user/getRoles` | `@getRoles` | 39 |
| GET | `/administracion/user/get-all-users` | `@getAllUsers` | 40 |
| GET | `/administracion/user/crear` | `@create` | 41 |
| POST | `/administracion/user/create` | `@store` | 42 |
| GET | `/administracion/user/{id}/editar` | `@edit` | 43 |
| POST | `/administracion/user/get-data-user/{id}` | `@getData` | 44 |
| POST | `/administracion/user/{id}/update` | `@update` | 45 |
| DELETE | `/administracion/user/{id}/destroy` | `@destroy` | 46 |
| POST | `/administracion/user/{id}/inactive-or-active` | `@inactiveOrActive` | 47 |
| PATCH | `/administracion/user/{id}/bloquear` | `@bloquear` | 48 |
| GET | `/administracion/user/avaiables-promotions/{code}` | `@avaiablesPromotions` | 49 |
| GET | `/administracion/addresses/states` | `UserController@getStates` | 53 |
| GET | `/administracion/addresses/{id}/municipalities` | `@getMunicipalities` | 54 |
| GET | `/administracion/addresses/{id}/colonies` | `@getColonies` | 55 |
| GET | `/administracion/rol/` | `RolController@index` | 59 |
| GET | `/administracion/rol/get-all` | `@get` | 60 |
| POST | `/administracion/rol/add` | `@store` | 61 |
| GET | `/administracion/rol/editar-role/{id}` | `@edit` | 62 |
| POST | `/administracion/rol/update-role/{id}` | `@updateRole` | 63 |
| DELETE | `/administracion/rol/destroy/{id}` | `@destroy` | 64 |
| POST | `/administracion/rol/table` | `@table` | 65 |
| GET | `/administracion/permisos/catalog` | `PermissionController@catalog` | 69 |
| GET | `/administracion/permisos/get-permission-for-role/{id}` | `@get` | 70 |
| POST | `/administracion/permisos/update-permission-for-role/{id}` | `@update` | 71 |
| POST | `/administracion/permisos/sync-roles` | `@syncRoles` | 78 |

Fuera del prefix, grupo `web,auth` (SIN `check_route_permission`, líneas 85-89):

| Método | URI | Controller@método | Línea | Clasificación | Nota |
|---|---|---|---|---|---|
| GET | `/permissions-auth` | `PermissionController@userPermissions` | 86 | **3** | Documentado en el propio comentario: "son los endpoints que VERIFICAN permisos, así que no pueden depender de ellos" — devuelve los permisos del propio usuario autenticado |
| POST | `/has-permission-to-view/{view}` | `@hasPermissionToView` | 87 | **3** | Mismo motivo, self-scoped |
| POST | `/all-view-has-permission` | `@allViewHasPermission` | 88 | **3** | Mismo motivo, self-scoped |

---

## 3. `app/Modules/Core/Clientes/routes.php` (242 líneas)

**100% protegida.** 3 grupos:

- `web,auth,check_route_permission` prefix `cliente` (líneas 41-219): **58 rutas**, clasificación
  **1**, cubriendo dashboard, cliente (CRUD + 15 helpers), documentos (8), info (4), servicios
  bundle/internet/voz/custom (9+6+5+5), billing + fiscal CFDI + pagos + transacciones + facturas
  (23), estadísticas + ping (8), plan promotions (6). Todas dentro del mismo wrapper — no se listan
  una por una por espacio, pero **ninguna cae fuera del grupo** (verificado línea por línea contra
  el archivo completo).
- `web,auth,check_route_permission` global, línea 223-225: `POST /helper/get-services-by-client-main-information` → `ComponentSearchServiceController@getServiceByClientMainInformationId` — clasificación **1**.
- `web,auth,role:super-administrator|DESARROLLADOR` prefix `facturacion` (líneas 233-242):
  clasificación **2** para `GET /facturacion/cfdi` (233), `GET /facturacion/cfdi/buscar` (238),
  `POST /facturacion/cfdi/{clientInvoiceId}/adjuntar` (239).

---

## 4. `app/Modules/Core/Configuracion/routes.php` (399 líneas)

Mayoritariamente protegida. 5 grupos:

1. `web,auth,check_route_permission` — `GET /admin/configuracion-nueva` (línea 63-65): **1**.
2. `web,auth` **(sin check_route_permission, a propósito y documentado)** — `GET /configuracion/credenciales-google-maps/render-config` → `MapCredentialController@renderConfig` (línea 76-77): **clasificación 4, pero documentada como intencional en el propio comentario del archivo** (líneas 67-75): expone el `api_key` de Google Maps + centro/zoom a cualquier staff autenticado (protege solo de anónimos). Riesgo **BAJO-MEDIO**: cualquier rol autenticado (incl. cuentas espejo `client`) puede leer la API key de Google Maps del sistema — no es dinero/PII de cliente, pero sí una credencial de terceros con cuota facturable.
3. `web,auth,check_route_permission` prefix `configuracion` (líneas 79-352): **clasificación 1** para **~95 rutas** (debitcustom, company-information, billing-reminder, email-setting, command, additional-fields, tools-import, credencial, medios-de-venta, comisiones, reglas-comisiones, tipos-vendedores, estados-vendedores, metodos-de-pago, credenciales-google-maps [gestión, distinto del render-config de arriba], rangos-venta, work-flow, list-template-verification, template-task, nomenclature, team, service_in_address_list, rules, finance-notification, api/catalogo, api-movil, data-plan-promotions). Todas dentro del wrapper — verificado sin excepciones.
4. `web,auth,check_route_permission` prefix `administracion` (líneas 356-386): **clasificación 1** para socios (6), ift (6), metotdo-de-pago (6) = 18 rutas.
5. `web,auth,permission:module.visibility.manage` prefix `admin/modules/visibility` (líneas 389-399): **clasificación 2** para 4 rutas (index/reorder/show/update).

---

## 5. `app/Modules/Core/Localizacion/routes.php` (74 líneas)

**100% protegida.** `web,auth,check_route_permission` prefix `administracion` (líneas 21-68): 30
rutas (ubicacion/sucursal/estado/municipio/colonia × 6 c/u), clasificación **1**. Más
`POST /helper/get-value-colony-state-municipality` (línea 72-74, grupo global mismo middleware):
clasificación **1**.

---

## 6. `app/Modules/Core/CRM/routes.php` (58 líneas)

**100% protegida.** `web,auth,check_route_permission` prefix `crm` (líneas 27-58): 16 rutas
(dashboard, CRM CRUD, conversión a cliente, documentos huérfanos ×3, documentos ×8), clasificación
**1** para todas.

---

## 7. `app/Modules/Core/Auth/routes.php` (47 líneas)

**Legítimamente pública — es el propio flujo de autenticación.** `web` únicamente (líneas 30-47),
sin `auth` ni `check_route_permission` (no pueden depender de sesión, son el punto de entrada):

| Método | URI | Controller@método | Línea | Clasificación |
|---|---|---|---|---|
| GET | `/login` | `LoginController@showLoginForm` | 31 | **2** — `$this->middleware('guest')->except('logout')` en el constructor del controller |
| POST | `/login` | `@login` | 32 | **2** — mismo guard `guest` |
| POST | `/logout` | `@logout` | 33 | **2** — excluido de `guest` (requiere haber logueado antes; Laravel invalida sesión sin more checks) |
| ANY | `/register` | closure `abort(404)` | 39-41 | **N/A** — deshabilitada a propósito (item #219), siempre 404 |
| GET | `/password/reset` | `ForgotPasswordController@showLinkRequestForm` | 43 | **5-equivalente** — flujo público estándar de recuperación (debe ser accesible sin sesión por definición) |
| POST | `/password/email` | `@sendResetLinkEmail` | 44 | **5-equivalente** — igual |
| GET | `/password/reset/{token}` | `ResetPasswordController@showResetForm` | 45 | **5-equivalente** — protegido por el token de un solo uso, no por sesión |
| POST | `/password/reset` | `@reset` | 46 | **5-equivalente** — igual, valida el token dentro del método |

---

## 8. `app/Modules/Core/Documentos/routes.php` (47 líneas)

`web,auth,check_route_permission` prefix `administracion` (líneas 21-47): 18 rutas
(document_template ×11, document_type_template ×6 + index), clasificación **1** — **con una nota
importante**: 3 de ellas también aparecen literalmente en
`CheckRoutePermission::PUBLIC_ROUTES` (`/administracion/document_template/get_variables`,
`/load_content_template`, `/show_content_template`) — el middleware las deja pasar en el paso 1
(antes de exigir sesión), así que **aunque estén declaradas dentro del grupo
`check_route_permission`, funcionalmente son públicas sin login**. Clasificación **5** para esas 3
(a propósito, documentado en la constante), **1** para las otras 15.

---

## 9. `app/Modules/Core/Documentacion/routes.php` (53 líneas)

**100% protegida.** `web,auth,check_route_permission` prefix `administracion/documentation`
(líneas 22-53): 17 rutas (documentation_menu ×8, documentation_submenu ×6, documentation_content
×4 — ajustado, ver archivo), clasificación **1** para todas.

---

## 10. `app/Modules/Core/Release/routes.php` (53 líneas)

**100% protegida.** `web,auth,check_route_permission` prefix `releases` (líneas 19-53): 20 rutas —
incluye el pipeline de deploy a producción (`/deployment/{id}/retry`, etc.) y el generador de
changelog con IA. Clasificación **1** para todas.

---

## 11. `app/Modules/Core/Dashboard/routes.php` (57 líneas)

Dos grupos:

- `web,auth,check_route_permission` (líneas 25-50): 18 rutas (`/`, `/index`, 6 stats-cards,
  `/statics/*` ×11), clasificación **1**.
- `web,auth` **sin** `check_route_permission` (líneas 55-57): `GET /home` →
  `HomeController@index`, mismo método que `/` (protegida). Clasificación **4**, riesgo **BAJO** —
  documentado en el propio comentario como comportamiento heredado de `routes/web.php` original;
  como `HomeController@index` solo arma el shell de la vista (los datos reales llegan por los
  endpoints POST protegidos de arriba), la exposición real es mínima, pero sigue siendo una
  entrada duplicada sin `check_route_permission`.

---

## 12. `app/Modules/Core/ModuleManager/routes.php` (52 líneas)

Tres grupos:

- `web,auth,check_route_permission` prefix `admin/modules` (líneas 17-34): 8 rutas, clasificación **1**.
- `web,auth,check_route_permission` prefix `admin/administracion` (líneas 37-42): 2 rutas, clasificación **1**.
- `web,auth` **sin** `check_route_permission` prefix `api/modules` (líneas 45-52):

| Método | URI | Controller@método | Línea | Clasificación | Nota |
|---|---|---|---|---|---|
| GET | `/api/modules/config-sections` | `AdminPanelController@configSections` | 48 | **3** | Filtra inline por `$user->hasRole($section['role'])` por cada sección antes de devolverla — no bloquea toda la request, pero excluye lo no autorizado por ítem |
| GET | `/api/modules/config-moved-sections` | `AdminPanelController@configMovedSections` | 51 | **3** | Mismo patrón, filtra inline por `$user->can($permission)` por ítem |

---

## 13. `app/Modules/Core/Auditoria/routes.php` (26 líneas)

**100% protegida.** `web,auth,check_route_permission`: `/administracion/activity_log` (index+table,
líneas 20-21) y `/administracion/auditoria-senales` (línea 26). Clasificación **1** para las 3.

---

## 14. `app/Modules/Core/Layout/routes.php` (21 líneas)

`web,auth` **sin** `check_route_permission` (líneas 16-21), las 4 rutas:

| Método | URI | Controller@método | Línea | Clasificación | Nota |
|---|---|---|---|---|---|
| POST | `/save-app-config-layout` | `ConfigAppLayoutController@saveAppConfigLayout` | 17 | **3** | `AppLayoutConfiguration::where('user_id', auth()->id())` — auto-scoping real |
| POST | `/save-row-status-style` | `@saveRowStatusStyle` | 18 | **3** | Mismo auto-scoping |
| POST | `/get-config-tabs` | `@getConfigTabs` | 19 | **3** | Mismo auto-scoping |
| POST | `/set-config-tabs` | `@setConfigTabs` | 20 | **3** | Mismo auto-scoping |

---

## 15. `app/Modules/Core/Notifications/routes.php` (21 líneas)

`force_json,auth:sanctum` prefix `api/push-tokens` (líneas 16-21): guard dedicado (identidad del
dispositivo móvil vía Sanctum). Clasificación **2** para `POST /` (línea 19) y
`DELETE /{token}` (línea 20).

---

## Anomalías detectadas que NO son de permisos (se documentan para no perderlas)

| Ruta | Problema |
|---|---|
| `POST /register-vendor/register` (`routes/web.php:220`) | `RegisterVendorController` no tiene método `register()` — la ruta produce error si se invoca. |
| `POST /getDataTable` (`routes/web.php:207`) | `Controller@getDataToTable` no existe en ningún lugar del repo (`grep` sin resultados). |
| `GET /register-vendor` (`routes/web.php:218-219`) | Sin ningún middleware (ni `auth` ni `web` explícito) y sin documentar como pública — a diferencia de `/csrf-refresh` o el flujo de `Auth`, no hay comentario que confirme la intención. |

---

## Metodología

Se recorrió cada archivo listado línea por línea, se identificaron los grupos `Route::group`/
`->middleware()` anidados, y para toda ruta que quedó fuera de `check_route_permission`/`role:`/
`permission:`/`can:`/guard dedicado se abrió el controller de destino para buscar `->authorize(`,
`Gate::allows(`, `Gate::authorize(`, `$this->authorize(` (clasificación 3 literal) o
auto-scoping fuerte por `auth()->id()`/`Auth::user()` que hiciera irrelevante el parámetro de ruta
(clasificación 3 por equivalencia funcional, anotado como tal). `Route::resource` no se usó en
ninguno de los 15 archivos de este lote (todas las rutas están declaradas explícitas), así que no
hubo necesidad de expandir 7 rutas CRUD implícitas.
