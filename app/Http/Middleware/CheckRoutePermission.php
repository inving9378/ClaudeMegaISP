<?php

namespace App\Http\Middleware;

use App\Http\Traits\PermissionTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRoutePermission
{
    use PermissionTrait;

    const PUBLIC_ROUTES = [
        '/administracion/document_template/get_variables',
        '/administracion/document_template/load_content_template',
        '/configuracion/credencial/image-front',
        '/configuracion/metodos-de-pago/get-all-methods',
        '/configuracion/credencial/image-back',
        '/configuracion/credencial/image-logo',
        '/administracion/document_template/show_content_template',
        '/helper/get-value-colony-state-municipality',
        '/helper/get-services-by-client-main-information',
        '/configuracion/service_in_address_list',
        '/configuracion/service_in_address_list/table',
        '/cliente/get-client-status/{id}',
        '/inventory/inventory_store/my-store/{id}'
    ];

    public function handle(Request $request, Closure $next)
    {
        $requestPath = parse_url($request->getRequestUri(), PHP_URL_PATH);

        // 1. Primero verificar si es una ruta pública (sin importar permisos o autenticación)
        foreach (self::PUBLIC_ROUTES as $publicRoute) {
            $regex = $this->convertRouteToRegex($publicRoute);
            if (preg_match($regex, $requestPath)) {
                return $next($request); // Acceso directo sin verificar permisos
            }
        }

        // 2. Si no es pública, verificar autenticación
        if (!Auth::check()) {
            return redirect()->route('login'); // O lanzar un 403
        }

        if (Auth::user()->isNotActive()) {
            Auth::logout();
            return redirect()->route('login');
        }
        // 3. Si es admin/developer, permitir acceso sin revisar permisos
        if (Auth::user()->isAdmin() || Auth::user()->isDevelopment() || Auth::user()->isSuperAdmin()) {
            return $next($request);
        }

        // 4. Si no es pública ni admin, verificar permisos específicos
        $route_permission = config('route_permission');
        $permissions = $this->getPermissionForUserAuthenticated();

        $has_permission = collect($route_permission)
            ->filter(function ($value, $key) use ($permissions, $requestPath) {
                foreach ($value as $pattern) {
                    $regex = $this->convertRouteToRegex($pattern);
                    if (preg_match($regex, $requestPath)) {
                        return isset($permissions[$key]);
                    }
                }
                return false;
            });

        if ($has_permission->isNotEmpty()) {
            return $next($request);
        }

        // 5. Si no cumple nada, denegar acceso
        return response(view('meganet.pages.403'), 403);
    }

    /**
     * Convierte una ruta con parámetros ({id}) en una expresión regular compatible.
     */
    protected function convertRouteToRegex(string $route): string
    {
        $regex = preg_replace('/\{[^\}]+\}/', '[^/]+', $route);
        return '#^' . $regex . '$#';
    }
}
