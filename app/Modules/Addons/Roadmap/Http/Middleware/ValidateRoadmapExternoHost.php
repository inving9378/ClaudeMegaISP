<?php

namespace App\Modules\Addons\Roadmap\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * CIRC-05 PASO 6 (#9991016) — segunda capa del guardrail de entorno de roadmap-externo.
 *
 * El registro de rutas ya está acotado a `app()->environment('local','dev')`
 * (`routes.php`), pero eso no valida el `Host:` de la petición. Este middleware rechaza
 * cualquier request cuyo host no esté en `config('circuito.roadmap_externo_hosts')` —
 * mitiga host-header-spoofing o un proxy mal configurado que reenvíe con un Host falso.
 *
 * Se aplica SOLO a los 3 grupos de rutas de `RoadmapExternalController` (no al conector MCP,
 * que es otra superficie/controller, fuera de alcance de este item).
 */
class ValidateRoadmapExternoHost
{
    public function handle(Request $request, Closure $next)
    {
        $permitidos = (array) config('circuito.roadmap_externo_hosts', []);
        $host = $request->getHost();

        if ($permitidos !== [] && ! in_array($host, $permitidos, true)) {
            Log::channel('circuito_despacho')->warning('roadmap-externo: host rechazado', [
                'host' => $host,
                'ip'   => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json(['error' => 'No autorizado'], 403);
        }

        return $next($request);
    }
}
