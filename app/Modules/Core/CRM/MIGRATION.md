# Migración pendiente — Core/CRM

El usuario indicó "crear con estructura base" pero ya existe lógica CRM funcional
que debe mudarse aquí en lugar de duplicarse.

## Controllers
- `app/Http/Controllers/Module/Crm/*` → `Controllers/`

## Modelos
- `app/Models/Crm.php`
- `app/Models/CrmMainInformation.php`
- `app/Models/CrmLeadInformation.php`
- `app/Models/DealCrm.php`
- `app/Models/DocumentCrm.php`
→ `Models/`

## Repositorios
- `app/Http/Repository/CrmRepository.php`
- `app/Http/Repository/DocumentCrmRepository.php`
→ `Repositories/`

## Servicios
- `app/Services/ContractCrmService.php` → `Services/`

## Vistas
- `resources/views/meganet/module/crm/*` (si existe) → `views/`

## Vue (no mover físicamente)
- `resources/js/components/module/crm/*` se queda registrado en `resources/js/app.js`.

## Rutas
- Bloque `Route::group(['prefix' => 'crm', ...])` en `routes/web.php` → `routes.php`

## Permisos
- Mantener entradas existentes en tabla `permissions` (Spatie); el módulo no las recrea.
