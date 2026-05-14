# Migración Core/CRM

## Estado

### Hecho

**Controllers** (4, git mv preservando historial):
- `app/Http/Controllers/Module/Crm/{Crm,CrmInformation,Dashboard,DocumentCrm}Controller.php`
  → `app/Modules/Core/CRM/Controllers/`
- Namespace `App\Http\Controllers\Module\Crm` → `App\Modules\Core\CRM\Controllers`.
- En CrmController, DashboardController y DocumentCrmController:
  - `$this->data['url']` cambia de `'meganet.module.crm'` (o
    `'meganet.module.' . Str::lower($model)`) a `'core-crm'`.
  - Llamadas `view($this->data['url'] . '.X', ...)` cambian a
    `view($this->data['url'] . '::X', ...)` para usar el namespace de
    vistas que BaseModuleServiceProvider registra.
  - `$this->data['model'] = 'App\Models\\' . $model;` actualizado a
    `'App\Modules\Core\CRM\Models\\' . $model;` (FQCN dinámico).

**Modelos** (5, git mv):
- `app/Models/{Crm,CrmMainInformation,CrmLeadInformation,DealCrm,DocumentCrm}.php`
  → `app/Modules/Core/CRM/Models/`
- Namespace `App\Models` → `App\Modules\Core\CRM\Models`.
- Añadidos imports explícitos `use App\Models\BaseModel;` y de cualquier
  otra clase de `App\Models` referenciada (LogActivity, TypeBilling,
  Seller, CommissionDetail, PaymentDetail, File).
- Constante `MODEL_RELATION_TO_CREATE_FIELD_MODULE` en Crm.php actualizada
  a string con el nuevo FQCN.

**Repositorios** (2, git mv):
- `app/Http/Repository/{Crm,DocumentCrm}Repository.php`
  → `app/Modules/Core/CRM/Repositories/`
- Namespace `App\Http\Repository` → `App\Modules\Core\CRM\Repositories`.
- ⚠️ Nota: existen también `app/Repositories/{Crm,CrmMain,CrmLead,DealCrm,
  DocumentCrm}Repository.php` (sistema paralelo basado en `BaseRepository`).
  Estos archivos NO se movieron — sólo se actualizaron sus
  `use App\Models\Crm…` para apuntar al nuevo namespace. Decidir en una
  fase posterior si se consolidan ambos sistemas de repositorios.

**Servicio** (1, git mv):
- `app/Services/ContractCrmService.php` → `app/Modules/Core/CRM/Services/`.
- Namespace `App\Services` → `App\Modules\Core\CRM\Services`.
- `use App\Http\Repository\CrmRepository / DocumentCrmRepository` ahora
  apuntan al nuevo namespace `App\Modules\Core\CRM\Repositories\…`.

**Vistas** (5, git mv):
- `resources/views/meganet/module/crm/{add,convert,dashboard,edit,index}.blade.php`
  → `app/Modules/Core/CRM/views/` (sin subcarpeta `crm/`).
- Acceso por namespace `core-crm::<nombre>` (registrado automáticamente por
  `BaseModuleServiceProvider`).

**Rutas**:
- Bloque `Route::group(['prefix' => 'crm', 'namespace' => 'Crm'])` movido a
  `app/Modules/Core/CRM/routes.php` envuelto en
  `Route::middleware(['web', 'auth', 'check_route_permission'])`.
- `routes/web.php` queda con un comentario apuntando al nuevo destino.

**Referencias externas actualizadas** (26 archivos):
- Observers, Rules, Events, Providers, Controllers (Vendors, Shared,
  Administration/DocumentTemplate, Utils/*), Helpers de módulo, Repositories
  duplicados en `app/Repositories/`, Services, y
  `Modules/Core/Dashboard/Controllers/StaticsController.php`.
- Todos los `use App\Models\Crm…`, `use App\Http\Repository\Crm…Repository`,
  `use App\Services\ContractCrmService` se actualizaron al nuevo namespace.
- Composer autoloader regenerado con `composer dump-autoload -o` para
  evitar entradas obsoletas en `vendor/composer/autoload_classmap.php`.

### Pendiente

- **Vue**: `resources/js/components/module/crm/*` sigue registrándose en
  `resources/js/app.js`. La modularización del bundle JS se evaluará después.
- **Traits**: `app/Http/Traits/Models/Crm/CrmTrait.php` permanece en
  `app/Http/Traits/`; podría moverse a `app/Modules/Core/CRM/Traits/` en
  un PR posterior si se decide reorganizar los traits por módulo.
- **Helpers**: `app/Http/HelpersModule/module/crm/*Helper.php` permanecen en
  su ubicación original — sólo se actualizaron sus imports.
- **Requests**: `app/Http/Requests/module/crm/*Request.php` permanecen en
  su ubicación original.
- **Repositorios duplicados**: ver nota arriba sobre
  `app/Repositories/Crm*Repository.php`.
- **Permisos**: las entradas existentes en la tabla `permissions` (Spatie)
  para acciones CRM se mantienen tal cual; el módulo no las recrea.
