<?php

namespace App\Modules\Addons\Flotas\Http\Middleware;

use App\Modules\Addons\Flotas\Services\FleetSubscriptionService;
use App\Services\Tenant\CurrentClientResolver;
use Closure;
use Illuminate\Http\Request;

/**
 * Marca la petición como contexto "portal" para los controladores de Flotas (#652).
 *
 * Solo se aplica al grupo /portal/flotas/api (alias 'flotas.portal'). Fail-closed: exige sesión
 * del guard 'cliente' con client_id y suscripción de Flotas usable (prueba, promo o activa).
 */
class FlotasPortalContext
{
    public const ATTR = 'flotas.portal';

    public function __construct(
        private CurrentClientResolver $resolver,
        private FleetSubscriptionService $subs,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $clientId = $this->resolver->resolve();
        abort_unless($clientId && auth('cliente')->check(), 403, 'Sesión sin cliente.');
        abort_unless($this->subs->clientHasAccess($clientId), 403, 'Tu servicio de Flotas no está activo. Solicita su activación.');

        $request->attributes->set(self::ATTR, true);

        return $next($request);
    }
}
