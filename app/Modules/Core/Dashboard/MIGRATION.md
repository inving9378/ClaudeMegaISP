# Migración Core/Dashboard

## Estado

### Hecho
- **Controllers** (git mv preservando historial):
  - `app/Http/Controllers/HomeController.php` → `Controllers/HomeController.php`
  - `app/Http/Controllers/StaticsController.php` → `Controllers/StaticsController.php`
  - Namespace `App\Http\Controllers` → `App\Modules\Core\Dashboard\Controllers`
- **Vistas** (git mv):
  - `resources/views/meganet/module/started-page.blade.php` → `views/started-page.blade.php`
  - `resources/views/meganet/module/started-page-client.blade.php` → `views/started-page-client.blade.php`
  - Las llamadas `view('meganet.module.started-page[-client]')` en HomeController
    se actualizaron a `view('core-dashboard::started-page[-client]')`.
- **Rutas**:
  - `/`, `/index`, `/get-*-card-in-dashboard-c` y el bloque `/statics/*` movidos a
    `routes.php` dentro de `Route::middleware(['auth', 'check_route_permission'])`.
  - Ruta nombrada `home` (`/home`) movida a `routes.php` (sin
    `check_route_permission`, el `auth` lo aporta el constructor del controlador).

### Pendiente
- **Vue**: `resources/js/components/module/dashboard/Dashboard.vue` y sus hijos
  siguen registrándose en `resources/js/app.js`. La modularización del bundle JS
  se evaluará en una fase posterior.
- **Widgets aportados por otros módulos**: diseñar un contrato `DashboardWidget`
  (interface o clase abstracta dentro de `Services/`) que otros módulos puedan
  implementar. Cargar los widgets activos vía `ModuleManagerService` filtrando
  por módulos `active=true`.
- **Permisos**: los strings `dashboard_view_card_client_inline`,
  `dashboard_view_card_client_new`, `dashboard_view_card_tickets_open_new` y
  `dashboard_view_card_device_not_responding` que aparecen en
  `HomeController::getHomeStatisticsForTarjetsByStatus()` deberían quedar
  registrados formalmente en la tabla `permissions` mediante una migración
  dentro de `migrations/` (no estaban sembrados antes).
