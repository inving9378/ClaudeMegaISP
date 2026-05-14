# Migración pendiente — Core/Configuracion

## Controllers
- `app/Http/Controllers/Module/Setting/*` (todo el árbol) → `Controllers/`
- `app/Http/Controllers/Utils/ComunConstantsController.php` → `Controllers/` (o `Services/` — es de configuración estática)

## Modelos
- `app/Models/CompanyInformation.php`
- `app/Models/BillingConfiguration.php`
- `app/Models/BillingReminder.php`
- `app/Models/ConfigFinanceNotification.php`
- `app/Models/CommandConfig.php`
- `app/Models/DefaultValue.php`
- `app/Models/FieldModule.php` / `FieldType.php` (campos dinámicos por módulo)
→ `Models/`

## Repositorios
- `CompanyInformationRepository.php`
- `ConfigFinanceNotificationRepository.php`
- `CommandConfigRepository.php`
- `FieldModuleRepository.php`
- `FieldTypeRepository.php`
- `FrequencyCommandRepository.php`
- `DefaultValueRepository.php`
→ `Repositories/`

## Servicios
- `app/Services/AppLayoutConfigurationService.php` (probable solapamiento con Layout — decidir)
- `app/Services/ConfigFinanceNotificationService.php`
- `app/Services/DefaultValueService.php`
- `app/Services/EmailConfigService.php`
→ `Services/`

## Rutas
- Bloque `Route::group(['prefix' => 'configuracion', ...])` en `routes/web.php` → `routes.php`

## Vistas
- `resources/views/meganet/module/setting/*` (si existe) → `views/`
