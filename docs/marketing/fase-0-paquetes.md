# Fase 0 — Cimientos: multiempresa, roles y guía visual · PAQUETES

Supervisor: `ingeniero-supervisor` · Dueño: **Henry** · Fecha: 2026-11-12
Worktree: `/var/www/megaisp-henry-marketing` · Rama: `feat/marketing-360-equipo`
Nadie commitea. Nadie migra la base `megaisp`. Nadie trabaja en `/var/www/megaisp`.

> **Estado: EN CONSTRUCCIÓN (12-nov).** Henry respondió D1 SÍ, D2 SÍ, D3 SÍ (recomendación tal cual). D4–D7 con su valor por defecto. Producción: el botón «Buscar actualizaciones» lo aprieta Henry (si su usuario no tiene `updates.apply` en prod, Irving) y Henry hace la prueba rápida.
> por defecto; D5 y D7 no bloquean la construcción.

---

## 0. Hechos encontrados en el código (condicionan todo el diseño)

1. **Migraciones del módulo**: `BaseModuleServiceProvider::boot()` hace `loadMigrationsFrom(<módulo>/migrations)`
   → las migraciones nuevas van en `app/Modules/Addons/Marketing/migrations/` y `migrate --path=app/Modules/Addons/Marketing/migrations`
   las encuentra. Hoy ahí solo hay `2026_09_14_210000_create_marketing_conversation_permissions.php`, que **no ha corrido
   en `megaisp_test`** (correrá junto con las de esta fase al aplicar el `--path`; es inocua: crea 4 permisos).
2. **Permisos desde `module.json`**: `ModuleLifecycleService::registerPermissions()` los crea **solo al instalar/actualizar
   el módulo** (cambio de versión). Como la versión no se sube a mitad del plan, **el permiso nuevo se crea por migración**
   (patrón ya usado en el módulo: `Permission::firstOrCreate` + `PermissionSyncService::syncPermissionToBaseRoles()`, que
   lo da con `givePermissionTo` a `super-administrator` y `DESARROLLADOR`) **y además se declara en `module.json`**.
3. **`check_route_permission` no sirve para rutas nuevas sin tocar `config/route_permission.php`** (fuera de lo autorizado):
   para usuarios no-admin busca la URL en ese archivo, y hoy **no hay ni una entrada `/marketing/**`**. Por eso:
   - las rutas **nuevas** NO usan `check_route_permission`; se protegen con `auth` + middleware propios del módulo +
     el middleware Spatie `permission:` (registrado en `app/Http/Kernel.php`, mismo patrón que `MarketingBrandKitController`).
   - las rutas **existentes** conservan sus middleware actuales (no se quita `check_route_permission` de ningún grupo).
4. **Hoy `/api/marketing/*` solo exige `auth`** (cualquier usuario con sesión, de cualquier área, puede leer/crear leads,
   conversaciones, etc.). Fase 0 lo cierra: además exigirá empresa de Marketing asignada.
5. **El menú lateral de Marketing NO sale de `module.json`**: `addon-marketing` está en `$sidebarHardcoded` y se pinta desde
   `resources/views/module-sidebar/marketing.blade.php` (fuera de lo autorizado). `admin_cards[]` de `module.json` sí se
   pinta (en `/admin/administracion`, filtrado por permiso).
6. **Tablas `marketing_*` y `company_id`** (base dev, lectura): 18 tablas tienen `company_id bigint unsigned NOT NULL DEFAULT 1`;
   **7 no lo tienen**: `marketing_messages`, `marketing_lead_activities`, `marketing_attributions`, `marketing_lead_pipeline`,
   `marketing_pipeline_stages`, `marketing_publication_logs`, `marketing_pilot_campaign_sends` (son hijas de otra tabla).
   Todas las filas actuales tienen `company_id = 1`.
7. **Modelos de Marketing que usan OTROS módulos** (sin usuario de Marketing en sesión): Payments (conciliación WhatsApp)
   usa `Conversation`, `Message`, `Lead`, `Setting`; `UsesApiIntegration` (llaves de API de TODO el sistema) y Domiciliación
   usan `Setting`; `MarketingPublishDueCommand` usa `Publication`. Un scope que se active "por usuario autenticado"
   rompería a Payments (sus usuarios no tienen empresa de Marketing). → el scope de esta fase **solo se activa dentro de
   las rutas de Marketing** (ver 2.3).
8. **Defectos que YA existen** (no son de esta fase; QA los marca "preexistente" comparando contra la base, no se arreglan aquí):
   - `MarketingBrandKitController` exige el permiso `marketing.brand_kit.configure`, que **no existe** → el API del Brand Kit
     responde 403 a todos.
   - Rutas legado que usan tablas inexistentes (`marketing_templates`, `campaign_contents`, `campaign_schedules`, columna
     `marketing_leads.campaign_id`): `/marketing/stats`, `/marketing/templates*`, `/marketing/campaigns/table`,
     `/marketing/content/*`, `/marketing/leads-legacy/*` → 500.
   - El sidebar de Marketing pregunta por permisos que no existen (`marketing.leads.view`, `marketing.campaigns.view`, …).
9. **Quién usa Marketing hoy**: las pantallas `/marketing/*` solo las abren los roles que `CheckRoutePermission` deja pasar
   sin mapeo: `super-administrator`, `DESARROLLADOR`, `Super Administrador`, `Administrador`. En dev: 12 personas activas con
   `super-administrator`/`DESARROLLADOR` y 1 más con `Super Administrador`/`Administrador` (ninguna es cuenta espejo `client`).
10. **Quasar** se registra con lista cerrada de componentes en `app.js`; ya están `QTable, QSelect, QDialog, QInput, QToggle,
    QBadge, QChip, QCard*, QSkeleton, QBanner, QTooltip, QForm, QInnerLoading, QSeparator, QSpace, QBtn, QIcon`. **No se agrega
    ninguno** (app.js solo admite una línea).
11. Colores reales: sistema LM (CSS servido `public/assets/css/bootstrap.min.css` / `app.min.css`): primario `#5156be`,
    éxito `#2ab57d`, info `#4ba6ef`, aviso `#ffbf53`, peligro `#fd625e`, secundario `#74788d`, claro `#e9e9ef`,
    oscuro `#343a40`, fuente `"IBM Plex Sans", sans-serif`; modo oscuro `body[data-layout-mode=dark]` (fondo `#313533`,
    texto `#ced4da`). Brand Kit Meganet (`marketing_settings`, grupo `brand`): primario `#0066CC`, secundario `#FF6600`,
    acento `#00CC66`, texto `#FFFFFF`.

---

## 1. Resumen de lo que se construye

- 2 tablas nuevas (`marketing_empresas`, `marketing_empresa_usuario`) + permiso `marketing.empresas.gestionar` +
  asignación inicial del equipo actual a la empresa 1 (Meganet). 4 migraciones.
- **Contexto de empresa activa** por petición (`EmpresaActiva`) que fija un middleware del módulo, y un **scope global**
  que filtra los modelos de negocio de Marketing **solo cuando ese contexto está puesto**.
- Middleware de rol por empresa (`admin`/`marketing`/`ventas`) aplicado a **todas** las rutas autenticadas del módulo,
  sin cambiar URI, método, nombre ni controlador.
- API de contexto (selector de empresa) + API de Empresas y equipo + pantalla "Empresas y equipo".
- Guía visual + kit de componentes del módulo.

---

## 2. CONTRATO COMPARTIDO (lo único que comparten los 3 agentes)

### 2.1 Tablas nuevas

**`marketing_empresas`** — migración `2026_11_12_100000_create_marketing_empresas_table.php`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | `bigIncrements` (bigint unsigned) | mismo tipo que `company_id` de las tablas `marketing_*` |
| `nombre` | `string(120)` | requerido |
| `slug` | `string(60)` **unique** | `[a-z0-9-]`, 2–60 |
| `color_primario` | `string(7)` nullable | `#RRGGBB` |
| `color_secundario` | `string(7)` nullable | `#RRGGBB` |
| `activo` | `boolean` default `true` | la empresa 1 no se puede desactivar |
| `created_by`, `updated_by` | `unsignedBigInteger` nullable | se llenan en el modelo con `auth()->id()` |
| `created_at`, `updated_at` | timestamps | |

Sembrado en la misma migración (idempotente, `insertOrIgnore` con id explícito):
`{id: 1, nombre: "Meganet", slug: "meganet", color_primario: "#0066CC", color_secundario: "#FF6600", activo: 1}`.
`down()`: `dropIfExists`. **No se agregan FKs a tablas existentes.**

**`marketing_empresa_usuario`** — migración `2026_11_12_100100_create_marketing_empresa_usuario_table.php`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | `bigIncrements` | |
| `company_id` | `unsignedBigInteger` | FK → `marketing_empresas.id` `restrictOnDelete` |
| `user_id` | `unsignedBigInteger` | FK → `users.id` `cascadeOnDelete` |
| `rol` | `string(20)` | `admin` \| `marketing` \| `ventas` (lista cerrada en `EmpresaUsuario::ROLES`) |
| `activo` | `boolean` default `true` | quitar a alguien = `activo=false` (no se borra la fila) |
| `created_by`, `updated_by` | `unsignedBigInteger` nullable | `created_by = null` ⇒ asignado por la migración inicial |
| timestamps | | |
| índices | `unique(company_id, user_id)`, `index(user_id, activo)` | |

**Permiso** — migración `2026_11_12_100200_create_marketing_empresas_gestionar_permission.php`:
`Permission::firstOrCreate(['name'=>'marketing.empresas.gestionar','guard_name'=>'web'])` +
`app(PermissionSyncService::class)->syncPermissionToBaseRoles('marketing.empresas.gestionar')`. `down()` vacío
(criterio del proyecto para catálogo de permisos). **Nunca** `syncRoles`/`syncPermissions`.

**Asignación inicial** — migración `2026_11_12_100300_asigna_equipo_actual_a_empresa_meganet.php` (ESCRITURA DE DATOS, ver D1):
- Selector **por nombre de rol, nunca por id** (dev ≠ prod). Usuarios con `estado='activo'`, `is_system` nulo o 0, y que
  **no** tengan el rol `client` (cuentas espejo).
- rol `admin` ← tienen `super-administrator` o `DESARROLLADOR`.
- rol `marketing` ← tienen `Super Administrador` o `Administrador` y no quedaron como admin.
- `insertOrIgnore` sobre `(company_id=1, user_id)`, `created_by = null`. Imprime con `echo`/`Log::info` cuántos asignó por rol.
- `down()`: borra solo `company_id=1 AND created_by IS NULL`.
- En dev da hoy **12 admin + 1 marketing**. En prod se calcula allá al desplegar.

### 2.2 Clases nuevas (nombres exactos; todo bajo `App\Modules\Addons\Marketing\`)

| Clase | Archivo | Qué es |
|---|---|---|
| `Models\Empresa` | `Models/Empresa.php` | extiende `App\Models\BaseModel` (activity log). `$table='marketing_empresas'`, `$fillable=['nombre','slug','color_primario','color_secundario','activo']`, `casts activo=>bool`. Relación `asignaciones()` hasMany `EmpresaUsuario` (fk `company_id`). Const `PRINCIPAL_ID = 1`. **No** lleva el scope. |
| `Models\EmpresaUsuario` | `Models/EmpresaUsuario.php` | extiende `BaseModel`. `$table='marketing_empresa_usuario'`, `$fillable=['company_id','user_id','rol','activo']`, `const ROLES=['admin','marketing','ventas']`. Relaciones `empresa()`, `usuario()` (→ `App\Models\User`). **No** lleva el scope. |
| `Support\EmpresaActiva` | `Support/EmpresaActiva.php` | contexto de la petición. Registrado con `$this->app->scoped(EmpresaActiva::class)` (se limpia solo entre peticiones y entre jobs de cola). |
| `Models\Scopes\EmpresaScope` | `Models/Scopes/EmpresaScope.php` | `implements Illuminate\Database\Eloquent\Scope`. |
| `Models\Concerns\PerteneceAEmpresa` | `Models/Concerns/PerteneceAEmpresa.php` | trait: `bootPerteneceAEmpresa()` agrega `EmpresaScope` + eventos `creating`/`updating`. |
| `Middleware\ResolverEmpresaActiva` | `Middleware/ResolverEmpresaActiva.php` | alias **`marketing.empresa`** |
| `Middleware\RequiereRolEmpresa` | `Middleware/RequiereRolEmpresa.php` | alias **`marketing.rol`** (parámetros: roles separados por coma) |
| `Middleware\SoloEmpresaPrincipal` | `Middleware/SoloEmpresaPrincipal.php` | alias **`marketing.principal`** |
| `Controllers\ContextoEmpresaController` | | `show`, `cambiar` |
| `Controllers\EmpresaController` | | `vista`, `index`, `store`, `update` |
| `Controllers\EmpresaUsuarioController` | | `index`, `store`, `update`, `disponibles` |
| `Requests\GuardarEmpresaRequest`, `Requests\GuardarAsignacionRequest`, `Requests\ActualizarAsignacionRequest` | `Requests/…` | FormRequest con mensajes en español |

API de `EmpresaActiva` (QA la usa en sus pruebas de modelos):

```php
public function establecer(Empresa $empresa, string $rol): void;
public function limpiar(): void;
public function hay(): bool;            // ¿hay contexto en esta petición?
public function id(): ?int;             // company_id activo o null
public function empresa(): ?Empresa;
public function rol(): ?string;         // 'admin' | 'marketing' | 'ventas' | null
```

Clave de sesión de la empresa elegida: **`marketing.empresa_activa_id`**.

### 2.3 Comportamiento del alcance por empresa (riesgo 1 resuelto)

**Regla única: el scope filtra solo si `EmpresaActiva::hay()`. Y el contexto lo pone únicamente el middleware
`marketing.empresa`, que solo está en las rutas autenticadas del módulo Marketing.**

| Camino | ¿Pasa por `marketing.empresa`? | Comportamiento |
|---|---|---|
| Pantallas y API de Marketing con sesión | Sí | filtra `{tabla}.company_id = activa`; altas fuerzan `company_id = activa` |
| Webhook Evolution, webhook Meta Ads, formulario público, embed.js, tracking piloto | No | **igual que hoy** (sin filtro; `company_id` lo pone su código o el default 1). Aunque el navegador tenga sesión de staff. |
| Jobs en cola (`ProcessIncomingMessageJob`, `ProcessMetaLeadJob`, `ScoreLeadJob`, `RenderVideoJob`, `PublishPostJob`, …) | No (worker) | **igual que hoy**. `scoped()` garantiza que no arrastran contexto de un job anterior. |
| Comandos artisan / cron (`MarketingPublishDueCommand`, …) | No | igual que hoy |
| Otros módulos (Payments, Hub/`UsesApiIntegration`, Domiciliación, WarRoom, Roadmap) | No | igual que hoy (además WarRoom/Payments/Roadmap usan `DB::table`, que nunca lleva scopes) |
| Job despachado con `QUEUE_CONNECTION=sync` dentro de una petición de Marketing (pruebas) | hereda contexto | filtra por la empresa de quien lo pidió (correcto) |

Detalle del trait `PerteneceAEmpresa` (cuando hay contexto):
- `EmpresaScope::apply()` → `$builder->where($model->qualifyColumn('company_id'), $ctx->id())` (columna calificada para joins/whereHas).
- `creating`: `company_id = $ctx->id()` **siempre** (pisa lo que venga en el request o en el código).
- `updating`: si `company_id` está sucio, se regresa a `getOriginal('company_id')` en silencio.
- Sin contexto: el trait no hace nada.
- Un id de otra empresa → `findOrFail` lanza `ModelNotFoundException` → **404**.

**Riesgo residual "falla abierta"** (una ruta autenticada de Marketing sin `marketing.empresa` quedaría sin filtro):
lo cierra la prueba de QA `RutasMarketingProtegidasTest` (toda ruta con `auth` cuyo URI empiece con `marketing` o
`api/marketing` debe llevar `marketing.empresa`).

### 2.4 Qué modelos llevan el scope

**Estrictos — llevan `use PerteneceAEmpresa;`** (datos de negocio o credenciales propias de cada marca):

| Modelo | Tabla |
|---|---|
| `App\Models\Marketing\Lead` y `App\Modules\Addons\Marketing\Models\Lead` | `marketing_leads` |
| `App\Models\Marketing\Campaign` y `App\Modules\Addons\Marketing\Models\Campaign` | `marketing_campaigns` |
| `App\Models\Marketing\Conversation` | `marketing_conversations` |
| `App\Models\Marketing\GeneratedContent` | `marketing_generated_content` |
| `App\Models\Marketing\LeadForm` | `marketing_lead_forms` |
| `App\Models\Marketing\MultivariantCampaign` | `marketing_multivariant_campaigns` |
| `App\Models\Marketing\PilotCampaign` | `marketing_pilot_campaigns` |
| `App\Models\Marketing\Pipeline` | `marketing_pipelines` |
| `App\Models\Marketing\Publication` | `marketing_publications` |
| `App\Models\Marketing\PublicationChannel` | `marketing_publication_channels` (tokens de la página de cada marca) |
| `App\Models\Marketing\ContentTemplate` | `marketing_content_templates` |

**Compartidos en Fase 0 — SIN scope** (configuración o catálogo que hoy es uno solo, de Meganet; ver D3):
`Setting` (Brand Kit + llaves; lo leen Payments, Hub y Domiciliación con `company_id=1` explícito), `Channel`,
`LeadSource`, `VideoTemplate`, `MarketingNiche`, `Asset` (biblioteca de B-roll/música del motor de video), `AiAgentConfig`.

**Hijos sin `company_id` — SIN scope**, se aíslan por su padre (los controladores cargan primero al padre con scope):
`Message`, `LeadActivity`, `Attribution`, `LeadPipeline`, `PipelineStage`, `PublicationLog`, `PilotCampaignSend`.
Si QA encuentra un endpoint que carga un hijo directo por id sin pasar por el padre, es falla de backend.

**Validaciones `exists:` sobre tablas estrictas** (pasan por alto el scope) que backend debe acotar a la empresa activa
con `Rule::exists(...)->where('company_id', app(EmpresaActiva::class)->id())`:
- `ContentGeneratorController.php` líneas ~25 y ~55: `campaign_id` → `marketing_campaigns`
- `PublishingController.php` línea ~98: `channel_ids.*` → `marketing_publication_channels`

### 2.5 Middleware, matriz de roles y respuestas

Registro (en `ModuleServiceProvider`): `register()` → `$this->app->scoped(Support\EmpresaActiva::class)`;
`boot()` → `$this->app['router']->aliasMiddleware('marketing.empresa', …)`, `('marketing.rol', …)`, `('marketing.principal', …)`.

**`marketing.empresa`** (`ResolverEmpresaActiva`):
1. Sin usuario → 401 JSON / redirect a login (no debería pasar: va después de `auth`).
2. Asignaciones activas del usuario (`activo=1`) cuya empresa está `activo=1`.
3. Ninguna → **403 `sin_empresa`**.
4. Si `session('marketing.empresa_activa_id')` es una de ellas, esa; si no, la de menor `company_id`, y se guarda en sesión.
5. `EmpresaActiva::establecer($empresa, $asignacion->rol)` → sigue.

**`marketing.rol:<r1>,<r2>`**: sin contexto → 403 `sin_empresa`; rol activo no está en la lista → **403 `sin_permiso_rol`**.

**`marketing.principal`**: `EmpresaActiva::id() !== 1` → **403 `config_compartida`**.

Formato de las negativas:

| Código | Petición JSON (`expectsJson()` o `ajax()`) | Navegación de página |
|---|---|---|
| `sin_empresa` | `403 {"ok":false,"error":"sin_empresa","mensaje":"Todavía no perteneces a ninguna empresa de Marketing. Pide a un administrador de Marketing que te agregue en «Empresas y equipo»."}` | 403 con la vista `addon-marketing::sin-empresa` |
| `sin_permiso_rol` | `403 {"ok":false,"error":"sin_permiso_rol","mensaje":"Tu rol en esta empresa no permite esta acción."}` | 403 con la vista existente `meganet.pages.403` |
| `config_compartida` | `403 {"ok":false,"error":"config_compartida","mensaje":"Esta configuración es de Meganet y la comparten todas las empresas; solo se cambia con Meganet como empresa activa."}` | 403 `meganet.pages.403` |
| Spatie `permission:` | 403 (formato de Spatie; QA solo verifica el status) | 403 |
| id de otra empresa / inexistente | 404 | 404 |

**Matriz rol × rutas EXISTENTES** (121 rutas hoy). Regla dura: **no cambia ningún método, URI, nombre ni acción**; solo
se agregan middleware; se conserva el **orden de registro** (p. ej. `campaigns/generate` antes que `campaigns/{id}`).
El orden de middleware queda: los que ya tenía el grupo (`web`, `auth`[, `check_route_permission`]) **y después**
`marketing.empresa`, `marketing.rol:…`[, `marketing.principal`].

| Grupo | Middleware que se agrega | Rutas |
|---|---|---|
| **P — Públicas** (7) | **ninguno** (no llevan `marketing.empresa`) | `webhooks/marketing/meta-ads` (GET/POST), `webhooks/marketing/evolution`, `public/marketing/lead-form/{slug}`, `public/marketing/lead-form/{slug}/submit`, `public/marketing/embed.js`, `marketing/pilot/track/open/{token}.gif`, `marketing/pilot/track/click/{token}` |
| **V — Ventas también** (18) | `marketing.empresa`, `marketing.rol:admin,marketing,ventas` | API: `GET/POST api/marketing/leads`, `GET/PUT api/marketing/leads/{id}`, `GET api/marketing/leads/{id}/activities`, las 8 de `api/marketing/conversations/**`, `GET api/marketing/lead-sources`. Vistas: `GET marketing/leads`, `GET marketing/leads/{id}`, `GET marketing/conversations`, `GET marketing/conversations/{id}` |
| **MP — Config compartida** (8) | `marketing.empresa`, `marketing.rol:admin,marketing`, `marketing.principal` | `PUT api/marketing/brand-kit`, `PUT api/marketing/brand-kit/integrations`, `POST api/marketing/brand-kit/logo`, `DELETE api/marketing/brand-kit/logo`, `PUT api/marketing/niches/{id}`, `POST api/marketing/voice-comparator/assign-niche`, `GET marketing/meta/oauth/start`, `GET marketing/meta/oauth/callback` |
| **M — Marketing** (88) | `marketing.empresa`, `marketing.rol:admin,marketing` | **todas las demás** rutas autenticadas de `marketing/**` y `api/marketing/**` (incluye `DELETE api/marketing/leads/{id}`, `POST …/leads/{id}/assign`, `POST …/leads/{id}/score`, el dashboard `GET marketing`, campañas, contenido, video, publicación, piloto, lead-forms, brand-kit GET) |

Nota: en `conversations` las acciones send/toggle-ai/assign/close siguen exigiendo además sus permisos Spatie
`marketing.conversation.*` (no se tocan). El Brand Kit sigue exigiendo `marketing.brand_kit.configure` (preexistente roto, ver 0.8).

### 2.6 Rutas NUEVAS (10)

Todas en `routes.php` del módulo, **fuera** de los grupos con `check_route_permission`. Base de middleware:
`['web','auth','marketing.empresa']`.

| # | Método | URL | Nombre | Middleware adicional | Acción |
|---|---|---|---|---|---|
| 1 | GET | `/api/marketing/contexto` | `api.marketing.contexto.show` | — | `ContextoEmpresaController@show` |
| 2 | POST | `/api/marketing/contexto/empresa-activa` | `api.marketing.contexto.cambiar` | — | `ContextoEmpresaController@cambiar` |
| 3 | GET | `/marketing/empresas` | `marketing.empresas.view` | `marketing.rol:admin`, `permission:marketing.empresas.gestionar` | `EmpresaController@vista` → `view('addon-marketing::empresas')` |
| 4 | GET | `/api/marketing/empresas` | `api.marketing.empresas.index` | ídem 3 | `EmpresaController@index` |
| 5 | POST | `/api/marketing/empresas` | `api.marketing.empresas.store` | ídem 3 | `EmpresaController@store` |
| 6 | PUT | `/api/marketing/empresas/{id}` | `api.marketing.empresas.update` | ídem 3 | `EmpresaController@update` |
| 7 | GET | `/api/marketing/empresas/{id}/usuarios` | `api.marketing.empresas.usuarios.index` | ídem 3 | `EmpresaUsuarioController@index` |
| 8 | POST | `/api/marketing/empresas/{id}/usuarios` | `api.marketing.empresas.usuarios.store` | ídem 3 | `EmpresaUsuarioController@store` |
| 9 | PUT | `/api/marketing/empresas/{id}/usuarios/{userId}` | `api.marketing.empresas.usuarios.update` | ídem 3 | `EmpresaUsuarioController@update` |
| 10 | GET | `/api/marketing/empresas/{id}/usuarios-disponibles` | `api.marketing.empresas.usuarios.disponibles` | ídem 3 | `EmpresaUsuarioController@disponibles` |

Reglas de `{id}` (rutas 6–10): la empresa `{id}` debe existir **y** el usuario debe tener asignación **activa** con rol
`admin` en **esa** empresa; si no → **404** (no 403). Total de rutas tras la fase: **131**.

**Formas JSON** (fechas ISO-8601 con zona `America/Mexico_City`; textos en español):

1) `GET /api/marketing/contexto` → 200
```json
{
  "ok": true,
  "usuario": { "id": 9001, "nombre": "Ana Ejemplo" },
  "empresa_activa": { "id": 1, "nombre": "Meganet", "slug": "meganet",
                      "color_primario": "#0066CC", "color_secundario": "#FF6600", "rol": "admin" },
  "empresas": [
    { "id": 1, "nombre": "Meganet", "slug": "meganet", "color_primario": "#0066CC", "rol": "admin" },
    { "id": 2, "nombre": "Meganet Empresarial", "slug": "meganet-empresarial", "color_primario": "#1F3A93", "rol": "ventas" }
  ],
  "capacidades": { "gestionar_empresas": true, "editar_config_compartida": true }
}
```
`gestionar_empresas` = rol activo `admin` **y** `user->can('marketing.empresas.gestionar')`.
`editar_config_compartida` = empresa activa id 1 **y** rol en `admin|marketing`. `empresas` = solo asignaciones activas
de empresas activas, orden por `id`.

2) `POST /api/marketing/contexto/empresa-activa` body `{"company_id": 2}` → 200 con la misma forma de (1), ya con la
empresa 2 activa (se guarda en sesión). `company_id` que no es una asignación activa del usuario (o empresa inactiva,
o inexistente) → **404**. `company_id` faltante/no entero → 422. *(Aquí `company_id` viene del request pero solo como
**elección**, validada contra las asignaciones; nunca se usa sin validar.)*

4) `GET /api/marketing/empresas` → 200 — **solo las empresas donde el usuario es admin activo** (no todas):
```json
{ "ok": true, "data": [
  { "id": 1, "nombre": "Meganet", "slug": "meganet", "color_primario": "#0066CC", "color_secundario": "#FF6600",
    "activo": true, "es_principal": true, "miembros": 13, "admins": 12, "created_at": "2026-11-12T10:00:00-06:00" }
] }
```
`miembros` y `admins` cuentan solo asignaciones activas.

5) `POST /api/marketing/empresas` body
`{"nombre":"Meganet Empresarial","slug":"meganet-empresarial","color_primario":"#1F3A93","color_secundario":null}`
→ **201** `{"ok":true,"data":{<empresa como en 4>}}`. Quien la crea queda asignado como `admin` activo (misma transacción).
Validación: `nombre` requerido, texto 2–120 (recortado), único sin distinguir mayúsculas; `slug` opcional
(si falta = `Str::slug(nombre)`), regex `^[a-z0-9]+(?:-[a-z0-9]+)*$`, 2–60, único; colores nulos o `^#[0-9A-Fa-f]{6}$`;
`activo` no se acepta al crear (siempre `true`). Errores → 422 formato estándar de Laravel
`{"message":"…","errors":{"campo":["mensaje en español"]}}`.

6) `PUT /api/marketing/empresas/{id}` body con cualquier subconjunto de `nombre, slug, color_primario, color_secundario, activo`
→ 200 `{"ok":true,"data":{…}}`. `activo:false` sobre id 1 → **422** `{"ok":false,"error":"empresa_principal","mensaje":"Meganet es la empresa principal y no se puede desactivar."}`.

7) `GET /api/marketing/empresas/{id}/usuarios` → 200
```json
{ "ok": true, "data": [
  { "user_id": 9001, "nombre": "Ana Ejemplo", "login_user": "ana_ejemplo", "email": "ana@ejemplo.mx",
    "rol": "admin", "activo": true, "asignado_at": "2026-11-12T10:00:00-06:00" }
] }
```
Incluye activos e inactivos (activos primero, luego por nombre).

8) `POST /api/marketing/empresas/{id}/usuarios` body `{"user_id": 3, "rol": "ventas"}` → **201** `{"ok":true,"data":{<miembro>}}`
(o **200** si la fila existía inactiva y se reactiva con el rol nuevo). Ya activo → **422**
`{"ok":false,"error":"ya_asignado","mensaje":"Esa persona ya está en esta empresa."}`.
`user_id` válido = existe, `estado='activo'`, `is_system` no verdadero, y tiene al menos un rol distinto de `client`;
si no → 422 en `errors.user_id`. `rol` ∈ `admin|marketing|ventas`.

9) `PUT /api/marketing/empresas/{id}/usuarios/{userId}` body `{"rol":"marketing"}` y/o `{"activo":false}` → 200
`{"ok":true,"data":{<miembro>}}`. Si el cambio deja la empresa **sin ningún admin activo** → **422**
`{"ok":false,"error":"ultimo_admin","mensaje":"La empresa debe conservar al menos un administrador activo."}`
(aplica también a uno mismo). `{userId}` sin fila en esa empresa → 404.

10) `GET /api/marketing/empresas/{id}/usuarios-disponibles?buscar=dia` → 200
`{"ok":true,"data":[{"id":9002,"nombre":"Luis Ejemplo","login_user":"luis_ejemplo","email":"luis@ejemplo.mx"}]}`.
`buscar` 2–60 caracteres (si no, 422); busca en `name`, `father_last_name`, `login_user`, `email` con bindings
(nada de SQL armado); máx. 20; excluye miembros **activos** de esa empresa, `is_system`, inactivos y quienes solo tienen
rol `client`. Solo esos 4 campos (nada de teléfono, dirección, contraseña).

### 2.7 Permisos y roles

- **Spatie (global), nuevo**: `marketing.empresas.gestionar` — "Marketing: administrar empresas (marcas) y su equipo".
  Lo crea la migración 2.1 y lo reciben `super-administrator` y `DESARROLLADOR` (aditivo). Se declara también en
  `module.json` → `permissions[]`. A cualquier otra persona se lo da Irving/Henry desde el editor de roles (decisión humana).
- **Roles de Marketing = rol por empresa** en `marketing_empresa_usuario.rol` (no son roles Spatie, porque una persona
  puede ser `admin` en una marca y `ventas` en otra y Spatie es global). La pantalla "Empresas y equipo" **solo escribe
  esa tabla**: **nunca** da ni quita permisos ni roles Spatie (si lo hiciera, un admin de Marketing podría darse permisos
  del sistema como `manage-integrations`).
- Para entrar a "Empresas y equipo" se necesitan **las dos** cosas: rol `admin` en la empresa activa **y** el permiso
  `marketing.empresas.gestionar`.
- Usuario con permisos Spatie de Marketing pero **sin empresa**: cualquier ruta autenticada de Marketing le responde
  `sin_empresa` (403 + pantalla explicativa). No hay atajo para super-admins futuros: se les agrega desde la pantalla (D1).

### 2.8 Frontend: nombres y piezas compartidas

- Una sola línea en `resources/js/app.js`, inmediatamente **antes** de `app.use(VueApexCharts);`:
  ```js
  require("./components/module/marketing/registrar").default(app); // Marketing IA: kit + pantallas nuevas (Fase 0+)
  ```
  `registrar.js` exporta `default function registrarMarketing(app)` que hace `app.component(...)` de todo lo nuevo.
  Las fases siguientes registran ahí sus pantallas **sin volver a tocar app.js**.
- Tag de la pantalla: **`<marketing-empresas-equipo>`** (prop `auth-user-id`), componente
  `resources/js/components/module/marketing/empresas/MarketingEmpresasEquipo.vue`.
- Vistas Blade (las escribe **backend**, contenido exacto):
  - `app/Modules/Addons/Marketing/views/empresas.blade.php`
    ```blade
    @extends('core-layout::master')
    @section('title') Empresas y equipo · Marketing @endsection
    @section('content')
        <marketing-empresas-equipo :auth-user-id="{{ auth()->id() }}"></marketing-empresas-equipo>
    @endsection
    ```
  - `app/Modules/Addons/Marketing/views/sin-empresa.blade.php`: `@extends('core-layout::master')`, una tarjeta con título
    «Aún no tienes empresa en Marketing» y el texto del mensaje `sin_empresa` de 2.5. Sin Vue, sin estilos globales
    (estilos en línea o `<style>` dentro de la sección con clase raíz `.mkt-sin-empresa`).
- Kit (`resources/js/components/module/marketing/kit/`), tags globales:

| Tag | Archivo | Props / slots |
|---|---|---|
| `mkt-raiz` | `MktRaiz.vue` | prop `color-marca` (String, opcional). Envuelve la pantalla, define las variables `--mkt-*` en su `<style scoped>` y alterna la clase `mkt-raiz--oscuro` observando `body[data-layout-mode]` con `MutationObserver` (sin `:global`). |
| `mkt-encabezado-pantalla` | `MktEncabezadoPantalla.vue` | `titulo` (req), `subtitulo`, `icono` (Material Icons), `con-selector-empresa` (Boolean). Slot `acciones`. |
| `mkt-selector-empresa` | `MktSelectorEmpresa.vue` | sin props. `GET /api/marketing/contexto`; con 1 empresa muestra chip con nombre; con varias, `q-select`; al cambiar → `POST /api/marketing/contexto/empresa-activa` y `window.location.reload()`. Emite `cargado(contexto)`. |
| `mkt-tarjeta-kpi` | `MktTarjetaKpi.vue` | `etiqueta`, `valor` (Number\|null), `formato` (`numero`\|`mxn`\|`porcentaje`), `variacion` (Number\|null, % vs periodo anterior), `icono`, `cargando`. |
| `mkt-tarjeta-panel` | `MktTarjetaPanel.vue` | `titulo`, `subtitulo`, `cargando`. Slots `default`, `acciones`, `pie`. |
| `mkt-tabla-ejecutiva` | `MktTablaEjecutiva.vue` | `columnas`, `filas`, `fila-key` (default `id`), `cargando`, `vacio-titulo`, `vacio-texto`. Reexpone los slots `body-cell-*` de `q-table`. |
| `mkt-estado-vacio` | `MktEstadoVacio.vue` | `icono`, `titulo`, `texto`. Slot `accion`. |
| `mkt-insignia-estado` | `MktInsigniaEstado.vue` | `texto`, `tono` (`exito`\|`aviso`\|`peligro`\|`info`\|`neutro`). |
| — | `formato.js` | `formatoMXN(v)` → `"$1,234.56 MXN"` (`Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN'})` + `" MXN"`; null → `"—"`), `formatoNumero`, `formatoPorcentaje`, `formatoFecha`. |
| — | `tokens.js` | constantes de color de la guía (para ApexCharts en fases siguientes). |
| — | `index.js` | exporta todo lo anterior. |

- Variables de diseño (definidas en `MktRaiz.vue`, nunca en `:root`): `--mkt-primario #5156be`, `--mkt-exito #2ab57d`,
  `--mkt-info #4ba6ef`, `--mkt-aviso #ffbf53`, `--mkt-peligro #fd625e`, `--mkt-neutro #74788d`, `--mkt-borde #e9e9ef`,
  `--mkt-texto #343a40`, `--mkt-texto-suave #74788d`, `--mkt-fondo-tarjeta #ffffff`, `--mkt-marca` (= `color-marca` o
  `#0066CC`), fuente `"IBM Plex Sans", sans-serif`; modo oscuro: fondo de tarjeta `#313533`, texto `#ced4da`
  (frontend confirma cada valor con grep en el CSS servido antes de fijarlo en la guía).

### 2.9 Base de datos de pruebas (riesgo 3 resuelto)

- Pruebas: `MarketingTestCase` (DatabaseTransactions) sobre `megaisp_test` (lo fuerza `phpunit.xml`).
- **Solo backend** aplica/revierte las migraciones de la fase en `megaisp_test`, en cuanto las escriba:
  ```bash
  cd /var/www/megaisp-henry-marketing
  flock /tmp/megaisp-suite.lock env APP_ENV=testing DB_DATABASE=megaisp_test php artisan migrate --path=app/Modules/Addons/Marketing/migrations --force
  ```
  (corre las 4 nuevas + la pendiente `2026_09_14_210000…`, inocua). Si cambia una migración ya aplicada:
  `migrate:rollback --path=app/Modules/Addons/Marketing/migrations --step=<n>` con el mismo prefijo, y vuelve a aplicar.
  QA y frontend **nunca** migran; si sus pruebas truenan por tabla faltante, esperan a backend.
- Cómo fijar contexto en pruebas:
  - De modelo: `app(\App\Modules\Addons\Marketing\Support\EmpresaActiva::class)->establecer($empresa, 'admin')` / `->limpiar()`.
  - HTTP: `$this->actingAs($u)->withSession(['marketing.empresa_activa_id' => $id])->getJson(...)`.
  - Datos de la empresa B: crearlos **sin contexto** con `company_id` explícito (sin contexto el trait no lo pisa).
- Cada agente crea sus usuarios/empresas dentro de su prueba (rollback); `megaisp_test` hoy tiene 1 usuario y los roles
  `super-administrator` (id 1) y `DESARROLLADOR` (id 10); no existe `admin@meganet.com`.
- IA, Meta, Evolution: siempre `Http::fake()` / `Queue::fake()` / `Bus::fake()`. Nada real.
- Pruebas **por archivo**, nunca por directorio.

### 2.10 Validación en navegador (riesgo 4 resuelto) — solo `127.0.0.1`

1. Compilar en el worktree (solo frontend): `cd /var/www/megaisp-henry-marketing && NODE_OPTIONS=--max-old-space-size=4096 npm run dev`.
2. Usuarios y empresas de vista previa en `megaisp_test` (idempotente; lo corre frontend; QA puede re-correrlo). Contraseña
   por variable de entorno elegida en la sesión, **nunca escrita en archivos**:
   ```bash
   cd /var/www/megaisp-henry-marketing
   read -s -p "Contraseña de vista previa: " MKT_PREVIEW_PASS; export MKT_PREVIEW_PASS; echo
   flock /tmp/megaisp-suite.lock env APP_ENV=testing DB_DATABASE=megaisp_test php artisan tinker --execute='
   use App\Models\User; use Illuminate\Support\Facades\{DB,Hash}; use Spatie\Permission\Models\Role;
   DB::table("marketing_empresas")->insertOrIgnore(["id"=>2,"nombre"=>"Empresa Prueba B","slug"=>"prueba-b","color_primario"=>"#1F3A93","activo"=>1,"created_at"=>now(),"updated_at"=>now()]);
   $mk = function($login,$rol,$empresas) { $u = User::firstOrNew(["login_user"=>$login]);
     $u->forceFill(["name"=>"Prev ".$login,"father_last_name"=>"PRUEBA","email"=>$login."@preview.local","password"=>Hash::make(getenv("MKT_PREVIEW_PASS")),"estado"=>"activo"])->save();
     if ($rol) { $u->assignRole($rol); }
     foreach ($empresas as $cid=>$r) { DB::table("marketing_empresa_usuario")->updateOrInsert(["company_id"=>$cid,"user_id"=>$u->id],["rol"=>$r,"activo"=>1,"created_by"=>$u->id,"updated_at"=>now(),"created_at"=>now()]); } };
   $mk("mkt_prev_admin","super-administrator",[1=>"admin",2=>"admin"]);
   $mk("mkt_prev_marketing","super-administrator",[1=>"marketing"]);
   $mk("mkt_prev_ventas","super-administrator",[2=>"ventas"]);
   $mk("mkt_prev_sinempresa","super-administrator",[]);'
   ```
   (Llevan `super-administrator` solo para pasar `check_route_permission` de las pantallas viejas; lo que se valida es la
   capa por empresa. Si el layout `core-layout::master` truena en `megaisp_test` por falta de catálogo, **no se siembran
   catálogos a mano**: se reporta al supervisor y la validación queda en pruebas HTTP.)
3. Servidor (frontend en **8096**, QA en **8097**):
   ```bash
   cd /var/www/megaisp-henry-marketing && env APP_ENV=local APP_URL=http://127.0.0.1:8096 DB_DATABASE=megaisp_test \
     QUEUE_CONNECTION=null MAIL_MAILER=log php artisan serve --host=127.0.0.1 --port=8096 --no-reload   # SIN --no-reload el hijo lee .env y cae en la base megaisp
   ```
4. Playwright desde `node_modules/playwright` (sin Chrome del sistema), login con `login_user` + `MKT_PREVIEW_PASS`.
   Capturas en el scratchpad de la sesión, nunca en `public/`.
5. Limpieza al cerrar la fase (la corre QA tras su reporte):
   `DELETE` de `marketing_empresa_usuario` y `model_has_roles` de esos 4 usuarios, de los 4 `users` `mkt_prev_*` y de la
   empresa id 2 de `megaisp_test`, con el mismo `flock`/`env`.

---

## 3. PAQUETE BACKEND (`agente-backend`)

**Archivos que puede crear/modificar (lista cerrada):**

Crear:
- `app/Modules/Addons/Marketing/migrations/2026_11_12_100000_create_marketing_empresas_table.php`
- `app/Modules/Addons/Marketing/migrations/2026_11_12_100100_create_marketing_empresa_usuario_table.php`
- `app/Modules/Addons/Marketing/migrations/2026_11_12_100200_create_marketing_empresas_gestionar_permission.php`
- `app/Modules/Addons/Marketing/migrations/2026_11_12_100300_asigna_equipo_actual_a_empresa_meganet.php`
- `app/Modules/Addons/Marketing/Models/Empresa.php`
- `app/Modules/Addons/Marketing/Models/EmpresaUsuario.php`
- `app/Modules/Addons/Marketing/Models/Scopes/EmpresaScope.php`
- `app/Modules/Addons/Marketing/Models/Concerns/PerteneceAEmpresa.php`
- `app/Modules/Addons/Marketing/Support/EmpresaActiva.php`
- `app/Modules/Addons/Marketing/Middleware/ResolverEmpresaActiva.php`
- `app/Modules/Addons/Marketing/Middleware/RequiereRolEmpresa.php`
- `app/Modules/Addons/Marketing/Middleware/SoloEmpresaPrincipal.php`
- `app/Modules/Addons/Marketing/Controllers/ContextoEmpresaController.php`
- `app/Modules/Addons/Marketing/Controllers/EmpresaController.php`
- `app/Modules/Addons/Marketing/Controllers/EmpresaUsuarioController.php`
- `app/Modules/Addons/Marketing/Requests/GuardarEmpresaRequest.php`
- `app/Modules/Addons/Marketing/Requests/GuardarAsignacionRequest.php`
- `app/Modules/Addons/Marketing/Requests/ActualizarAsignacionRequest.php`
- `app/Modules/Addons/Marketing/views/empresas.blade.php` (contenido exacto en 2.8)
- `app/Modules/Addons/Marketing/views/sin-empresa.blade.php`
- `tests/Feature/Marketing/Fase0/Backend/**` (sus pruebas; p. ej. `EmpresaActivaYScopeTest.php`, `EmpresasApiBackendTest.php`)

Modificar:
- `app/Modules/Addons/Marketing/routes.php` — middleware según la matriz 2.5 + las 10 rutas de 2.6. Sin cambiar
  métodos/URIs/nombres/acciones ni el orden de registro de las 121 existentes.
- `app/Modules/Addons/Marketing/module.json` — agregar a `permissions[]` el permiso 2.7; a `admin_cards[]`
  `{"title":"Marketing — Empresas y equipo","description":"Marcas del grupo Meganet y quién trabaja en cada una (admin, marketing, ventas).","icon":"building","url":"/marketing/empresas","permission":"marketing.empresas.gestionar"}`;
  a `api_endpoints[]` las 9 rutas API nuevas. **No subir `version`.** No tocar `menu[]` (no se pinta, ver 0.5).
- `app/Modules/Addons/Marketing/ModuleServiceProvider.php` — `scoped()` + 3 alias de middleware.
- Agregar `use PerteneceAEmpresa;` (y nada más) a: `app/Models/Marketing/{Lead,Campaign,Conversation,GeneratedContent,LeadForm,MultivariantCampaign,PilotCampaign,Pipeline,Publication,PublicationChannel,ContentTemplate}.php`
  y `app/Modules/Addons/Marketing/Models/{Lead,Campaign}.php`.
- `app/Modules/Addons/Marketing/Controllers/ContentGeneratorController.php` y `.../PublishingController.php` — **solo**
  acotar las reglas `exists:` listadas en 2.4.

**Tareas en orden:** (1) migraciones + aplicarlas en `megaisp_test` (2.9) ; (2) `EmpresaActiva`, scope, trait, modelos ;
(3) middleware + `ModuleServiceProvider` ; (4) `routes.php` ; (5) controladores/requests/vistas ; (6) `module.json` ;
(7) pruebas propias ; (8) revisar con `grep` que ningún controlador del módulo use `withoutGlobalScope(s)`.

**Pruebas propias mínimas** (normal / error / vacío / borde): scope con y sin contexto, `creating` y `updating` del trait,
resolución de empresa activa (sesión válida, inválida, ninguna, empresa inactiva), las 10 rutas nuevas (incluye 404 de otra
empresa, `ultimo_admin`, `empresa_principal`, `ya_asignado`, slug duplicado, color inválido, acentos/emojis en nombre),
idempotencia de la migración de asignación (ejecutar `up()` dos veces dentro de la transacción no duplica).

**Fuera de alcance de backend:** cualquier archivo no listado (en particular `config/route_permission.php`,
`resources/views/module-sidebar/marketing.blade.php`, `app/Http/Kernel.php`, `app/Modules/Addons/IA/config/modulos.php`
— esta fase no necesita claves de IA), los defectos preexistentes de 0.8, y los Vue.

---

## 4. PAQUETE FRONTEND (`agente-frontend`)

**Archivos que puede crear/modificar (lista cerrada):**

Crear:
- `docs/marketing/guia-visual.md` (estilo ejecutivo / panel directivo: jerarquía, espaciado en múltiplos de 8 px, tarjetas,
  KPIs arriba, tablas, gráficas, estados vacío/carga/error/sin permiso, responsive 375 px, colores 2.8 con su origen,
  modo oscuro, iconos Material en `q-btn` y FA5 validado con grep, formato MXN, reglas `scoped`/Bootstrap `.row`).
- `resources/js/components/module/marketing/kit/MktRaiz.vue`, `MktEncabezadoPantalla.vue`, `MktSelectorEmpresa.vue`,
  `MktTarjetaKpi.vue`, `MktTarjetaPanel.vue`, `MktTablaEjecutiva.vue`, `MktEstadoVacio.vue`, `MktInsigniaEstado.vue`,
  `formato.js`, `tokens.js`, `index.js`
- `resources/js/components/module/marketing/registrar.js`
- `resources/js/components/module/marketing/empresas/MarketingEmpresasEquipo.vue`
- `resources/js/components/module/marketing/empresas/EmpresaFormDialog.vue`
- `resources/js/components/module/marketing/empresas/EquipoEmpresaPanel.vue`
- `resources/js/components/module/marketing/empresas/AsignarUsuarioDialog.vue`
- `tests/Feature/Marketing/Fase0/Frontend/PantallaEmpresasTest.php` (la vista responde 200 a admin con el tag
  `<marketing-empresas-equipo`, y 403 a `marketing`/`ventas`/sin empresa)

Modificar:
- `resources/js/app.js` — **solo** la línea de 2.8.
- `resources/js/components/module/marketing/MarketingDashboard.vue` — **solo** reemplazar el encabezado actual por
  `<mkt-encabezado-pantalla titulo="Marketing" con-selector-empresa>` conservando el botón "Nueva campaña" en el slot
  `acciones` y agregando ahí un botón "Empresas y equipo" (→ `/marketing/empresas`) visible solo si
  `capacidades.gestionar_empresas`. Nada más de esa pantalla cambia (su rediseño es Fase 1).

**Pantalla "Empresas y equipo"** (`/marketing/empresas`), dentro de `<mkt-raiz :color-marca="…">`:
- Encabezado con selector de empresa y botón "Nueva empresa".
- Tabla ejecutiva de empresas (nombre con muestra de color, slug, insignia Activa/Inactiva, miembros, admins, "Principal"
  para id 1) con acción "Editar" (diálogo: nombre, slug, 2 colores, interruptor activo deshabilitado para la principal).
- Al elegir una empresa: panel de equipo (nombre, usuario, correo, rol editable en línea con `q-select`, interruptor
  activo, insignia de rol) + botón "Agregar persona" (diálogo con búsqueda ≥2 letras contra la ruta 10 y rol).
- Errores del API mostrados con el `mensaje` del backend (`ultimo_admin`, `ya_asignado`, `empresa_principal`, 422 por campo).
- Estados: cargando (skeleton), vacío (con acción), error, sin permiso (si el contexto dice `gestionar_empresas=false`,
  mensaje y sin botones). Sin `v-html`. Todo `<style scoped>`; arreglo de Bootstrap `.row > *` solo con selector scoped.
- Doble clic en "Guardar" no duplica (botón con `loading` mientras la petición está en curso).
- Mientras backend termina: maquetar con los JSON de ejemplo de 2.6.

**Pruebas:** `npm run dev` sin errores ni advertencias nuevas; navegador (2.10) a 1440 y 375 px: pantalla nueva con
datos, vacía, error, cambio de empresa con el selector, usuario `ventas` y `sinempresa` (ve la pantalla «Aún no tienes
empresa»), Dashboard sigue abriendo; consola sin errores. `PantallaEmpresasTest.php` por nombre.

**Fuera de alcance:** sidebar, layout, CSS global, `public/`, cualquier otro `.vue` de Marketing distinto de los listados,
cualquier `.php` fuera de su prueba.

---

## 5. PAQUETE QA (`agente-qa`)

**Archivos que puede crear/modificar (lista cerrada):**

Crear (todos extienden `Tests\Feature\Marketing\MarketingTestCase`; helper propio en un trait de su carpeta):
- `tests/Feature/Marketing/Fase0/Qa/Concerns/EscenarioEmpresas.php` — crea empresa A (=1) y B, usuarios
  admin/marketing/ventas de A, admin de B, sin-empresa y sin-permiso Spatie; datos de cada modelo estricto en A y en B.
- `tests/Feature/Marketing/Fase0/Qa/EsquemaEmpresasTest.php` — tablas, columnas, tipos, `unique`, FK, semilla id 1.
- `tests/Feature/Marketing/Fase0/Qa/AlcanceModelosTest.php` — por cada modelo estricto de 2.4: con contexto A no ve B
  (`count`, `find`, `whereHas`, relación desde un modelo compartido); `creating` fuerza A aunque mande `company_id` B;
  `updating` no mueve de empresa; sin contexto ve ambas (igual que hoy). Modelos compartidos e hijos: sin filtro.
- `tests/Feature/Marketing/Fase0/Qa/CaminosSinSesionTest.php` — webhook Evolution y Meta Ads (GET verify / POST),
  formulario público (show/submit, también con sesión de un usuario de la empresa B abierta: debe seguir encontrando
  el formulario de A y crear el lead con el `company_id` del formulario), tracking piloto, y
  `ProcessIncomingMessageJob`/`ProcessMetaLeadJob` corriendo sin contexto: mismos resultados que en la base.
- `tests/Feature/Marketing/Fase0/Qa/RutasMarketingProtegidasTest.php` — (a) toda ruta con `auth` y URI `marketing*` o
  `api/marketing*` tiene `marketing.empresa`; las 7 públicas no; (b) las 121 rutas existentes conservan
  método+URI+nombre+acción (lista fija escrita desde 2.5) y su grupo de rol es el de la matriz; (c) existen las 10 nuevas
  con su middleware; total 131.
- `tests/Feature/Marketing/Fase0/Qa/AislamientoEndpointsExistentesTest.php` — usuario de A contra recursos de B por id
  directo en **cada** ruta existente con `{id}` (ver/editar/borrar/acciones) → 404 y la fila de B queda intacta;
  listados, conteos y stats (`api/marketing/leads`, `conversations`, `lead-forms`, `generated-content`,
  `multivariant-campaigns`, `pilot-campaigns`, `publishing/publications`, `publishing/channels`,
  `publishing/dashboard/stats|recent`) sin filas de B; `exists:` de 2.4 rechaza ids de B (422).
- `tests/Feature/Marketing/Fase0/Qa/MatrizRolesTest.php` — admin/marketing/ventas/sin empresa/empresa inactiva/asignación
  inactiva contra una ruta representativa de cada grupo V, M, MP y nuevas; JSON y navegación de página; `config_compartida`
  con la empresa B activa.
- `tests/Feature/Marketing/Fase0/Qa/ContextoEmpresaApiTest.php` — rutas 1–2 (forma exacta, cambio, 404 de empresa ajena
  /inactiva/inexistente, 422, sesión con id basura).
- `tests/Feature/Marketing/Fase0/Qa/EmpresasApiTest.php` y `.../EquipoApiTest.php` — rutas 3–10 con ataque: vacíos, nulos,
  10 000 caracteres, emojis/acentos, `<script>`, inyección SQL en `buscar`, arrays donde va texto, `rol` fuera de lista,
  `user_id` de cuenta `is_system`/inactiva/solo `client`, doble envío, último admin (incluido uno mismo), desactivar la
  principal, admin de B intentando `{id}` de A → 404, respuesta de `usuarios-disponibles` sin campos sensibles.
- `tests/Feature/Marketing/Fase0/Qa/MigracionAsignacionTest.php` — ejecuta `up()` de la migración 100300 dentro de la
  transacción con usuarios sembrados: selector por rol, excluye `client`/`is_system`/inactivos, idempotente, `down()` solo
  borra `created_by IS NULL`.
- `docs/marketing/fase-0-qa-reporte.md` — reporte (formato del agente QA + paso 8 de la skill).

Modificar (solo precondiciones, **sin tocar ninguna aserción**; se reporta en el paso 4 de la skill):
- `tests/Feature/Marketing/ConversationControllerTest.php` — en `setUp`, asignar `$this->user` a la empresa 1 con rol
  `admin` (sin esto la fase rompe sus 200 por `sin_empresa`).
- `tests/Feature/Marketing/MultivariantCampaignControllerTest.php` — en `actingAsAdmin()`, si el usuario existe,
  asegurar su asignación a la empresa 1 (`admin`). (Hoy ya falla en la base porque `admin@meganet.com` no existe en
  `megaisp_test`; sigue siendo preexistente.)

**Al cierre:** skill `pruebas-antes-de-avanzar` completa (N=4 migraciones en el paso 2), navegador en el puerto 8097,
clasificar cada falla como **nueva** o **preexistente** (comparando contra la base `megaisp-base-marketing`), limpieza de
2.10.5. QA no toca código de la aplicación ni migra.

---

## 6. Regla de no-pisarse

| Archivo / carpeta | Dueño único |
|---|---|
| `app/Modules/Addons/Marketing/routes.php`, `module.json`, `ModuleServiceProvider.php` | backend |
| `app/Modules/Addons/Marketing/{migrations,Models,Support,Middleware,Controllers,Requests}/**` (los listados) | backend |
| `app/Modules/Addons/Marketing/views/empresas.blade.php`, `sin-empresa.blade.php` | backend |
| `app/Models/Marketing/*.php` (los 11 listados) | backend |
| `tests/Feature/Marketing/Fase0/Backend/**` | backend |
| `resources/js/app.js` (1 línea) | frontend |
| `resources/js/components/module/marketing/{kit/**,registrar.js,empresas/**,MarketingDashboard.vue}` | frontend |
| `docs/marketing/guia-visual.md` | frontend |
| `tests/Feature/Marketing/Fase0/Frontend/**` | frontend |
| `tests/Feature/Marketing/Fase0/Qa/**`, `ConversationControllerTest.php`, `MultivariantCampaignControllerTest.php` | QA |
| `docs/marketing/fase-0-qa-reporte.md` | QA |
| `docs/marketing/fase-0-paquetes.md`, `docs/bitacora/2026-11-12-marketing-fase-0.md` | supervisor |

Base `megaisp_test`: migrar/revertir = solo backend; usuarios de vista previa = frontend (QA puede re-correr el script
idempotente y hace la limpieza). Puertos: frontend 8096, QA 8097. Base `megaisp`: solo lectura para todos.

---

## 7. Criterios de aceptación (verificables)

1. `megaisp_dryrun`: las 4 migraciones corren en limpio, `rollback --step=4` y re-`migrate` pasan; ninguna toca tablas existentes.
2. `marketing_empresas` tiene la fila 1 «Meganet»; `marketing_empresa_usuario` queda con las asignaciones del selector (en dev: 12 admin + 1 marketing).
3. `super-administrator` y `DESARROLLADOR` tienen `marketing.empresas.gestionar`; nadie más lo recibió por la fase.
4. Con Meganet activa, todas las pantallas de Marketing que abrían antes siguen abriendo igual (Leads, Conversaciones,
   Video, Publicación, Piloto, Dashboard) y muestran los mismos datos de hoy.
5. Un usuario de la empresa B no ve ninguna fila de A en listados/conteos y recibe **404** al pedir un id de A en
   cualquier ruta con `{id}`; ninguna fila de A cambia.
6. Webhooks, formulario público, tracking piloto y jobs se comportan exactamente como en la base (mismas filas, `company_id` 1).
7. Payments (conciliación WhatsApp) y las llaves de API del Hub no cambian (sus pruebas existentes siguen igual que en la base).
8. `ventas`: entra a Leads y Conversaciones; en campañas/video/publicación/dashboard recibe 403. `marketing`: todo menos
   "Empresas y equipo". Usuario sin empresa: pantalla «Aún no tienes empresa en Marketing» / JSON `sin_empresa`.
9. Con la empresa B activa, escribir Brand Kit/nichos/voz de nicho/Meta OAuth → 403 `config_compartida`.
10. En pantalla (Henry, como admin): abre `/marketing`, ve el encabezado nuevo con el nombre «Meganet» y el botón
    «Empresas y equipo»; crea «Empresa Prueba», se ve como admin de ella, agrega a una persona como `ventas`, cambia su
    rol, intenta quitarse como último admin y ve el aviso; cambia de empresa con el selector y la página recarga con la
    nueva empresa; la tarjeta aparece también en `/admin/administracion`.
11. Se ve bien a 1440 y 375 px, sin errores en consola, modo claro y oscuro.
12. `RutasMarketingProtegidasTest` en verde: 131 rutas, todas las autenticadas con `marketing.empresa`.
13. Suite completa: 0 fallas nuevas contra la base; grep de seguridad y de reglas del sistema sin coincidencias nuevas.
14. `git diff --stat` solo muestra archivos de la sección 6.

---

## 8. Riesgos y cómo se cubren

| # | Riesgo | Cobertura |
|---|---|---|
| R1 | Bot/webhooks/jobs/otros módulos sin sesión | Scope activado solo por contexto de las rutas de Marketing (2.3) + `CaminosSinSesionTest` + criterio 6/7 |
| R2 | Usuarios actuales pierden acceso | Migración de asignación por rol (2.1) + pantalla de equipo; los que no entren ven mensaje claro (D1) |
| R3 | Tablas nuevas en pruebas | Solo backend migra `megaisp_test` con `--path` y candado (2.9) |
| R4 | Validación en navegador sin vhost | `artisan serve` en 127.0.0.1 con `megaisp_test` y usuarios `mkt_prev_*` (2.10); si el layout no arranca ahí, se reporta y no se siembra a mano |
| R5 | Archivos compartidos entre agentes | Tabla de dueños (6); app.js 1 línea + `registrar.js` para no volver a tocarlo |
| R6 | Ruta autenticada sin `marketing.empresa` (falla abierta) | `RutasMarketingProtegidasTest` |
| R7 | `exists:` y `DB::table` saltan el scope | Lista de 2.4 corregida; `DB::table` en el módulo solo en `QueryPlansTool` (tabla de planes, no de Marketing) |
| R8 | Reordenar rutas cambia cuál responde | Prohibido cambiar orden; test de snapshot (2.5 / QA b) |
| R9 | Empresa nueva sin catálogos (sin canales, plantillas propias) | Por diseño en Fase 0 (D3): ve estados vacíos, no datos de Meganet; los catálogos compartidos siguen siendo los de Meganet |
| R10 | Admin de Marketing escalando permisos del sistema | La pantalla solo escribe `marketing_empresa_usuario`; nunca Spatie (2.7) |
| R11 | La migración de datos corre en dev al mezclar y en prod al desplegar | Selector por rol (portable), idempotente, `down()` acotado; D7 |
| R12 | Defectos preexistentes confundidos con regresiones | QA compara contra la base y los etiqueta (0.8) |

---

## 9. Decisiones que necesitan a Henry (RESUELTAS 12-nov: D1 SÍ · D2 SÍ · D3 SÍ · D4–D7 por defecto)

| # | Pregunta | Recomendación | ¿Bloquea? |
|---|---|---|---|
| **D1** | Asignación inicial por migración a Meganet: `admin` = quien tiene `super-administrator`/`DESARROLLADOR`; `marketing` = `Super Administrador`/`Administrador`; excluye cuentas espejo `client`, bot y usuarios inactivos (en dev: 12 + 1). Quien no quede asignado (y los super-admins futuros) ve «Aún no tienes empresa» hasta que un admin lo agregue — sin atajos. Es **escritura de datos**: corre en dev al mezclar y en prod al desplegar. | Sí, así | **Sí** |
| **D2** | Rol `ventas` en Fase 0: Leads (ver, crear, editar, historial) y Conversaciones; **no** borra ni reasigna leads, no dispara el puntaje IA, no entra al Dashboard ni a campañas/video/publicación. | Sí, así | **Sí** |
| **D3** | Configuración compartida en Fase 0 (Brand Kit, nichos, plantillas de video, biblioteca de medios, catálogo de canales y fuentes, configuración del bot) sigue siendo **una sola, la de Meganet**, y solo se cambia con Meganet como empresa activa. Las empresas nuevas arrancan **sin** campañas, formularios, canales de publicación ni contenido propios (estados vacíos). Brand Kit por marca, más adelante. | Sí, así | **Sí** |
| D4 | El menú lateral de Marketing vive en `resources/views/module-sidebar/marketing.blade.php` (fuera de lo autorizado). ¿Se autoriza agregar ahí UNA entrada «Empresas y equipo» o se entra solo por el botón del Dashboard y la tarjeta de `/admin/administracion`? | Sin menú por ahora (botón + tarjeta) | No (default: sin menú) |
| D5 | Las pantallas viejas siguen tras `check_route_permission` sin mapeo → solo personas con rol de administración del sistema las abren; alguien con rol de empresa `marketing`/`ventas` pero sin esos roles **no podrá abrirlas**. Arreglarlo exige tocar `config/route_permission.php` (pedir OK a Irving) o cambiar ese candado por los del módulo. | Decidir en Fase 1 | No |
| D6 | Colores: base del sistema LM (indigo `#5156be` y su paleta) + acento de la marca de cada empresa (Meganet azul `#0066CC` / naranja `#FF6600`). | Sí, así | No (default) |
| D7 | Bitácora: el supervisor deja en `docs/bitacora/2026-11-12-marketing-fase-0.md`, antes de mezclar, qué escribe la migración D1, en qué bases y quién la aprueba (regla de escrituras en producción). | Sí | No |

Para información (no se arreglan en Fase 0, salen de la investigación): Brand Kit responde 403 a todos (permiso inexistente);
rutas legado de plantillas/estadísticas/tabla de campañas/contenido/leads-legacy dan 500 por tablas que no existen; el menú
de Marketing pregunta por permisos inexistentes; `/api/marketing/*` hoy está abierto a cualquier usuario con sesión
(Fase 0 lo cierra).
