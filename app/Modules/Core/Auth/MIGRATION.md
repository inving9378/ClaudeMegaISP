# Migración pendiente — Core/Auth

Esta carpeta es el destino final del módulo Auth. La migración del código existente
se hará en PRs posteriores (no automatizado en el scaffold). Mueve a este módulo:

## Controllers
- `app/Http/Controllers/Auth/*` → `Controllers/`

## Vistas
- `resources/views/meganet/auth/*` → `views/`

## Rutas
- El bloque `Auth::routes()` y las rutas de cambio de contraseña en `routes/web.php`
  → `routes.php`

## Middleware / Servicios
- `app/Http/Middleware/Authenticate.php` y `RedirectIfAuthenticated.php`
- `app/Services/UserAuthenticator.php` → `Services/`
- `app/Http/Middleware/CheckRoutePermission.php` (depende de Spatie — evaluar si vive aquí o en ModuleManager)

## Modelos / Repositorios
- Lo relacionado a `User`, roles/permisos (Spatie).

## Permisos
- Tras mover, registrar permisos correspondientes en la tabla `permissions` (Spatie)
  con una migración dentro de `migrations/`.

> **Atención:** actualizar TODOS los `use App\Http\Controllers\Auth\...` y `@include('meganet.auth.*')`
> al hacer el movimiento. Hacer el cambio atómico por módulo y probar antes del siguiente.
