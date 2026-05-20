# Migración Core/Configuracion

## Estado

### Hecho

**Controllers** (23, git mv preservando historial):
- `app/Http/Controllers/Module/Setting/*` → `app/Modules/Core/Configuracion/Controllers/`
- Estructura de subcarpetas preservada (BillingReminder, Commission,
  CompanyInformation, Credential, EmailSetting, Finance,
  ListTemplateVerification, MediumSale, MethodPayment, Nomenclature,
  Rules, ServiceInAddressList, StatusSeller, Table, Team, TemplateTask,
  Tools, TypeSeller, WorkFlow).
- Namespace `App\Http\Controllers\Module\Setting[\<Sub>]`
  → `App\Modules\Core\Configuracion\Controllers[\<Sub>]`.
- `$this->data['url']`:
  - Caso raíz (SettingController): `'meganet.module.setting'` → `'core-configuracion'`,
    `view($this->data['url'] . '.X')` → `view($this->data['url'] . '::X')`.
  - Resto: `'meganet.module.setting.<sub>'` → `'core-configuracion::<sub>'`,
    se preserva el `view($this->data['url'] . '.X')` porque
    `'core-configuracion::sub' . '.X'` produce `'core-configuracion::sub.X'`
    que Laravel resuelve correctamente.
- `$this->data['model'] = 'App\Models\\' . $model;` → `'App\Modules\Core\Configuracion\Models\\' . $model;`
  en los 4 controladores cuyo `$model` corresponde a un modelo movido
  (CommandConfig, BillingReminder, ConfigFinanceNotification,
  CompanyInformation). EmailSettingController NO se cambió porque su
  modelo (`EmailSetting`) sigue en `App\Models`.

**Modelos** (8, git mv):
- `app/Models/{CompanyInformation,BillingConfiguration,BillingReminder,
  ConfigFinanceNotification,CommandConfig,DefaultValue,FieldModule,
  FieldType}.php` → `app/Modules/Core/Configuracion/Models/`.
- Namespace `App\Models` → `App\Modules\Core\Configuracion\Models`.
- `use App\Models\BaseModel;` añadido en los 3 modelos que extienden
  BaseModel (BillingConfiguration, CommandConfig, FieldModule), que
  antes resolvían por proximidad de namespace.

**Repositorios** (7, git mv):
- `app/Http/Repository/{CompanyInformation,ConfigFinanceNotification,
  CommandConfig,FieldModule,FieldType,FrequencyCommand,DefaultValue}Repository.php`
  → `app/Modules/Core/Configuracion/Repositories/`.
- Namespace `App\Http\Repository` → `App\Modules\Core\Configuracion\Repositories`.

**Servicios** (3, git mv):
- `app/Services/{ConfigFinanceNotificationService,DefaultValueService,EmailConfigService}.php`
  → `app/Modules/Core/Configuracion/Services/`.
- Namespace `App\Services` → `App\Modules\Core\Configuracion\Services`.

**Vistas** (39, git mv):
- Árbol completo `resources/views/meganet/module/setting/*` → `app/Modules/Core/Configuracion/views/`.
- Subcarpetas preservadas (additionalfield, billing_reminder, commandconfig,
  commission, companyinformation, credential, debitpaymentclientcustom,
  email_setting, finance, list_template_verification, maps,
  method-payments, medium-sales, nomenclature, range-sales, rules,
  seller-status, seller-type, service_in_address_list, template_task,
  tools, work_flow, etc.).
- Accesibles vía `core-configuracion::<sub>.<vista>`.

**Rutas**:
- Bloque `Route::group(['prefix' => 'configuracion', 'namespace' => 'Setting'])`
  (242 líneas, ~120 endpoints) movido a `app/Modules/Core/Configuracion/routes.php`
  envuelto en `Route::middleware(['web', 'auth', 'check_route_permission'])`.
- Todos los callables string-based (`'SettingController@index'`) convertidos
  a callable arrays con FQCN (`[SettingController::class, 'index']`) ya que
  el `namespace => 'Setting'` global desaparece.
- `MapCredentialController`, `CommissionRuleController` y `RangeSaleController`
  pertenecen a Module\Mapas y Module\Vendors\Billing y se importan tal cual;
  siguen viviendo fuera del módulo Configuracion.

**Referencias externas actualizadas** (80 archivos vía perl):
- Console commands (Active/ y Scripts/), Kernel.php.
- Controllers de Client, Finance/Invoice, Message/*, Scheduling/Task,
  Vendors, OLTs, Mapas, Router, Tickets, etc.
- Repositories paralelos en `app/Repositories/*` que importaban estos modelos.
- Services (AppMessageService, BillingService, EmailService,
  ProformaInvoiceService, ServerInfoService, etc.).
- Jobs y Notifications.
- Composer autoloader regenerado con `composer dump-autoload -o`.

### Pendiente

- **`ComunConstantsController`** (`app/Http/Controllers/Utils/ComunConstantsController.php`)
  — la MIGRATION.md original lo listaba como candidato a este módulo
  ("o `Services/` — es de configuración estática"), pero tiene 94 referencias
  externas y su función es más utility que Setting-específica.
  Decidir su destino y migrar en un PR separado.

- **Vue**: `resources/js/components/module/setting/*` sigue registrándose
  en `resources/js/app.js`. La modularización del bundle JS se evaluará
  después.

- **Traits**: `app/Http/Traits/Models/Settings/*` (utilizado por CommandConfig)
  permanece en `app/Http/Traits/`.

- **Requests** y **Helpers** específicos de Setting permanecen en
  `app/Http/Requests/module/setting/` y `app/Http/HelpersModule/module/setting/`.

- **Repositorios duplicados**: `app/Repositories/*Repository.php` (sistema
  paralelo basado en `BaseRepository`) NO se movió — sólo se actualizaron
  sus imports. Consolidación pendiente.

- **Solapamiento con Layout**: `AppLayoutConfigurationService.php` ya está
  en Core/Layout (migración previa), así que el ítem de la MIGRATION.md
  original ya estaba resuelto antes de este commit.
