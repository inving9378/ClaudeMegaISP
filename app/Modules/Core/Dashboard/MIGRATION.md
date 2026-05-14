# Migración pendiente — Core/Dashboard

## Controllers
- `app/Http/Controllers/HomeController.php` (acción dashboard) → `Controllers/`
- `app/Http/Controllers/StaticsController.php` (estadísticas del dashboard) → `Controllers/`

## Vistas
- `resources/views/meganet/pages/dashboard/*` → `views/`

## Vue (no mover físicamente todavía)
- `resources/js/components/module/dashboard/Dashboard.vue` y sus hijos siguen registrándose
  en `resources/js/app.js`. La modularización del bundle JS se evaluará después.

## Widgets aportados por otros módulos
Diseñar un contrato `DashboardWidget` (interface o clase abstracta dentro de `Services/`)
que otros módulos puedan implementar. Cargar los widgets activos vía
`ModuleManagerService` filtrando por módulos `active=true`.

## Rutas
- `Route::get('/', 'HomeController@index')` (o equivalente) → `routes.php`
