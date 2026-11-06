<?php

namespace App\Modules\Core\Auth\Middleware;

use App\Http\Traits\PermissionTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckRoutePermission
{
    use PermissionTrait;

    /**
     * core-documentos (item #839 → #978): paths legado que hoy abren por el
     * permiso legado config_view_system EN PARALELO al granular documentos.*.
     * Mapea cada path al granular que YA lo cubre, para loguear cuándo el
     * acceso real dependió SOLO del fallback legado. Decisión de Irving
     * (#978, opción 2): no retirar el fallback todavía — dejarlo 1-2 ciclos
     * con este log de advertencia y retirarlo después si queda mudo.
     */
    const DOCUMENTOS_LEGACY_FALLBACK = [
        '/administracion/document_template' => 'documentos.view',
        '/administracion/document_template/table' => 'documentos.view',
        '/administracion/document_template/get_data_template/{id}' => 'documentos.view',
        '/administracion/document_type_template' => 'documentos.view',
        '/administracion/document_type_template/table' => 'documentos.view',
        '/administracion/document_template/add' => 'documentos.create',
        '/administracion/document_type_template/add' => 'documentos.create',
        '/administracion/document_template/update/{id}' => 'documentos.edit',
        '/administracion/document_type_template/editar/{id}' => 'documentos.edit',
        '/administracion/document_type_template/update/{id}' => 'documentos.edit',
        '/administracion/document_template/destroy/{id}' => 'documentos.delete',
        '/administracion/document_type_template/destroy/{id}' => 'documentos.delete',
    ];

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
        '/inventory/inventory_store/my-store/{id}',
        '/whatsapp/webhook/{slug}',
        '/whatsapp/webhook/meta/{slug}',
        '/payments/spei/webhook',
        '/webhooks/marketing/meta-ads',
        '/webhooks/marketing/evolution',
        '/public/marketing/lead-form/{slug}',
        '/public/marketing/lead-form/{slug}/submit',
        '/public/marketing/embed.js',
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
            $this->logDocumentosLegacyFallbackIfUsed($requestPath, $permissions);
            return $next($request);
        }

        // 5. Si no cumple nada, denegar acceso.
        // Política de denegación silenciosa (item #537 Fase 1, decisión q2 de
        // Irving): la navegación de página completa a algo sin permiso ya NO
        // muestra la pantalla 403 — redirige en silencio al Dashboard. Las
        // llamadas AJAX/JSON conservan el 403 real intacto (resources/js/
        // helpers/Form.js atrapa error.response.status===403 en varios flujos
        // existentes; cambiarles el código de respuesta los rompería en silencio).
        if ($this->shouldDenySilently($request)) {
            return $this->silentDenialResponse($requestPath);
        }

        return response(view('meganet.pages.403'), 403);
    }

    /**
     * Si el acceso a un path de core-documentos dependió SOLO del permiso
     * legado config_view_system (el usuario no trae el granular documentos.*
     * equivalente), lo deja registrado. Ver DOCUMENTOS_LEGACY_FALLBACK / #978.
     */
    protected function logDocumentosLegacyFallbackIfUsed(string $requestPath, array $permissions): void
    {
        foreach (self::DOCUMENTOS_LEGACY_FALLBACK as $pattern => $granularPermission) {
            $regex = $this->convertRouteToRegex($pattern);
            if (!preg_match($regex, $requestPath)) {
                continue;
            }

            if (isset($permissions['config_view_system']) && !isset($permissions[$granularPermission])) {
                Log::warning(
                    "[core-documentos][fallback-legado] Acceso a {$requestPath} otorgado solo por config_view_system (falta {$granularPermission}). Ver item roadmap #978.",
                    ['user_id' => Auth::id(), 'path' => $requestPath, 'granular_esperado' => $granularPermission]
                );
            }

            return;
        }
    }

    /** ¿Aplica la denegación silenciosa a esta petición? Solo navegación de página completa. */
    protected function shouldDenySilently(Request $request): bool
    {
        if ($request->expectsJson() || $request->ajax()) {
            return false;
        }

        return (bool) config('permission_hierarchy.silent_denial_enabled', true);
    }

    /**
     * Redirige en silencio al destino configurado. Si la ruta pedida YA es ese
     * destino y también se deniega, cae al 403 clásico para evitar un loop de
     * redirección infinito.
     */
    protected function silentDenialResponse(string $requestPath)
    {
        $target = config('permission_hierarchy.silent_denial_redirect', '/');

        if (rtrim($requestPath, '/') === rtrim($target, '/') || $requestPath === $target) {
            return response(view('meganet.pages.403'), 403);
        }

        return redirect($target);
    }

    /**
     * Convierte una ruta con parámetros a expresión regular.
     * Soporta {id}/{param} (segmento sin /) y ** (cualquier subcamino).
     * Ejemplos: /talento/api/** → #^/talento/api/.+$#
     */
    protected function convertRouteToRegex(string $route): string
    {
        $regex = str_replace('**', '.+', $route);
        $regex = preg_replace('/\{[^\}]+\}/', '[^/]+', $regex);
        return '#^' . $regex . '$#';
    }
}
