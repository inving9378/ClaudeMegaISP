# Migración pendiente — Core/Layout

## Vistas a mover (todo `resources/views/meganet/layout/`)
- `master.blade.php`
- `master-without-nav.blade.php`
- `topbar.blade.php`
- `sidebar.blade.php`
- `head.blade.php`
- `title-meta.blade.php`
- `footer.blade.php`
- `right-sidebar.blade.php`
- `vendor-scripts.blade.php`
- `modals.blade.php`
- `change-password.blade.php`

Destino: `views/`. Tras moverlas, registrar el namespace `core-layout::` y reemplazar
en todo el codebase `@extends('meganet.layout.master')` por `@extends('core-layout::master')`.

## Controladores / servicios
- `app/Http/Controllers/ConfigAppLayoutController.php` → `Controllers/`
- `app/Services/AppLayoutConfigurationService.php` → `Services/`

## Asset / Vue
- `resources/js/shared/ModeVisualBody.vue` y `NotificationTopbar.vue` continúan registrados
  desde `resources/js/app.js`. No mover Vue todavía: Mix compila un único bundle global.

## Modelo
- `app/Models/AppLayoutConfiguration.php` → `Models/`

## Provider
- Los `View::share('configLayout', ...)` y `View::share('logoMeganet', ...)` que están en
  `AppServiceProvider::boot()` deben moverse a `ModuleServiceProvider::boot()` aquí, gateados
  por `parent::boot()`.

> Hacer commit aparte para el movimiento de blades y otro para los `@include` actualizados.
