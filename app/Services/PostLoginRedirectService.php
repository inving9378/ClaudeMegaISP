<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class PostLoginRedirectService
{
    // Rutas candidatas (en orden de preferencia) para usuarios sin last_visited_route.
    //
    // David (29-sep): un técnico plano (rol "TECNICO", a diferencia de
    // TECNICO_PLANTA/TECNICO_INSTALADOR) no tiene dashboard_view_dashboard,
    // así que caía a '/crm/listar' (SÍ tiene crm_view_crm, heredado —
    // muchos técnicos también son Vendedor) — pero esa pantalla no le
    // muestra nada útil ("no tiene permisos para ver clientes"), lo deja
    // varado sin poder ni llegar al sidebar para navegar a Talento.
    // '/talento' se sube a la 2ª posición: /talento/colaborador/{id})
    // resuelve a SU ficha o a "mi equipo" — siempre útil para quien tiene
    // talento.view. Solo afecta a quien tiene talento.view Y NO tiene
    // dashboard_view_dashboard (en la práctica: el rol "TECNICO" puro —
    // Mostrador/TECNICO_PLANTA/TECNICO_INSTALADOR ya tienen dashboard y
    // siguen cayendo en '/' igual que antes; Vendedor puro no tiene
    // talento.view, sigue cayendo en '/crm/listar' sin cambio).
    private const FALLBACK_ROUTES = [
        '/'            => 'dashboard_view_dashboard',
        '/talento'                => 'talento.view',
        '/crm/listar'  => 'crm_view_crm',
        '/cliente'     => 'client_view_dashboard',
        '/tickets'     => 'ticket_view_dashboard',
        '/vendedores'  => 'seller_view_seller',
        '/finanzas/transacciones' => 'finance_view_transactions',
        '/scheduling/task'        => 'task_view_task',
        '/flotas'                 => 'fleet.view',
        '/warroom'                => 'warroom.view',
        '/embajadores'            => 'embajadores.view',
        '/cobranza'               => 'cobranza.view',
        '/voip/troncales'         => 'voip.troncales.view',
        '/inventory'              => 'inventory_view_inventory',
        '/olts'                   => 'olt_view',
        '/mapas'                  => 'maps_view_maps',
        '/red/router'             => 'router_view_router',
    ];

    public function resolve(User $user): string
    {
        // 1. Si tiene last_visited_route Y aún tiene permiso → redirigir ahí
        $lastRoute = $user->last_visited_route;
        if ($lastRoute && $this->userCanAccessPath($user, $lastRoute)) {
            return $lastRoute;
        }

        // 2. Buscar la primera ruta que el usuario puede ver
        foreach (self::FALLBACK_ROUTES as $path => $permName) {
            if ($user->can($permName)) {
                return $path;
            }
        }

        // 3. Sin módulos accesibles → pantalla informativa
        return '/sin-modulos';
    }

    private function userCanAccessPath(User $user, string $path): bool
    {
        if ($user->isAdmin() || $user->isDevelopment() || $user->isSuperAdmin()) {
            return true;
        }

        $routePermission = config('route_permission', []);
        $userPerms = $user->getAllPermissions()->pluck('name')->toArray();
        $userPermsMap = array_flip($userPerms);

        foreach ($routePermission as $permName => $patterns) {
            if (!isset($userPermsMap[$permName])) {
                continue;
            }
            foreach ($patterns as $pattern) {
                $regex = $this->convertRouteToRegex($pattern);
                if (preg_match($regex, $path)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function convertRouteToRegex(string $route): string
    {
        $regex = str_replace('**', '.+', $route);
        $regex = preg_replace('/\{[^\}]+\}/', '[^/]+', $regex);
        return '#^' . $regex . '$#';
    }
}
