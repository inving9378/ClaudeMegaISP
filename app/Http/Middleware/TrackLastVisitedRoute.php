<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrackLastVisitedRoute
{
    // Prefijos excluidos: nunca se guardan como "última ruta"
    private const SKIP_PREFIXES = [
        '/login', '/logout', '/register', '/password',
        '/api/', '/webhooks/',
        '/sin-modulos',
        '/administracion/activity_log',
        // SPA blacklist (sincronizado con spa-nav.js)
        '/mapas',
        '/embajadores/metrics',
        '/embajadores/video',
        '/administracion/documentation/documentation_content',
        // Assets (defensivo, nginx los sirve antes, pero por si acaso)
        '/js/', '/css/', '/fonts/', '/images/', '/storage/',
        // DevTools
        '/devtools',
    ];

    // Extensiones de archivo a ignorar
    private const SKIP_EXTENSIONS = [
        'js', 'css', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico',
        'woff', 'woff2', 'ttf', 'eot', 'map', 'pdf',
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Solo GET, solo usuarios autenticados y activos
        if (!$request->isMethod('GET') || !Auth::check()) {
            return $response;
        }

        // Solo respuestas HTML (2xx)
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return $response;
        }

        // Ignorar AJAX / peticiones que esperan JSON
        if ($request->ajax() || $request->expectsJson()) {
            return $response;
        }

        $path = $request->path();

        // Ignorar por extensión
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext && in_array($ext, self::SKIP_EXTENSIONS)) {
            return $response;
        }

        // Ignorar por prefijo
        $fullPath = '/' . ltrim($path, '/');
        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($fullPath, $prefix)) {
                return $response;
            }
        }

        // Guardar solo si cambió
        $user = Auth::user();
        if ($user->last_visited_route !== $fullPath) {
            $user->timestamps = false;
            $user->last_visited_route = $fullPath;
            $user->saveQuietly();
            $user->timestamps = true;
        }

        return $response;
    }
}
