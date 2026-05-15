<?php

namespace App\Modules\Core\Configuracion\Middleware;

use App\Modules\Core\Configuracion\Models\ApiMobileLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Middleware "después del request" que registra cada hit a /api/megafamilia/*
 * en `api_mobile_logs`. Se aplica como `terminable middleware` para no
 * agregar latencia al request — el log se escribe después de devolver
 * la respuesta al cliente.
 */
class LogApiMobileAccess
{
    public function handle(Request $request, Closure $next)
    {
        $request->attributes->set('_api_mobile_started_at', microtime(true));
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        try {
            $startedAt = (float) $request->attributes->get('_api_mobile_started_at', microtime(true));
            $duration = (int) round((microtime(true) - $startedAt) * 1000);

            ApiMobileLog::create([
                'user_id' => Auth::id(),
                'method' => $request->getMethod(),
                'endpoint' => mb_substr($request->getPathInfo(), 0, 255),
                'status' => method_exists($response, 'getStatusCode') ? (int) $response->getStatusCode() : 0,
                'ip' => $request->ip(),
                'duration_ms' => $duration,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // No queremos romper el request por un fallo de log; solo lo
            // anotamos en el log normal para diagnóstico.
            Log::warning('LogApiMobileAccess::terminate failed', ['error' => $e->getMessage()]);
        }
    }
}
