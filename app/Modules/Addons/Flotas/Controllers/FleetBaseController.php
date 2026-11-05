<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Services\Tenant\CurrentClientResolver;

abstract class FleetBaseController extends Controller
{
    /**
     * Permisos del panel que el portal del cliente NUNCA obtiene (solo Meganet): proveedores,
     * gestión de suscripción y administración de flotas de clientes (#652).
     */
    private const PORTAL_DENEGADOS = [
        'fleet.providers.manage',
        'fleet.subscriptions.manage',
        'fleet.push.manage',
        'fleet.assign',
    ];

    /**
     * ¿La petición viene del grupo de rutas /portal/flotas/api (middleware 'flotas.portal')?
     * Se decide SOLO por ese middleware explícito, jamás por "hay sesión de portal" (#634).
     */
    protected function enPortal(): bool
    {
        return (bool) request()->attributes->get(\App\Modules\Addons\Flotas\Http\Middleware\FlotasPortalContext::ATTR, false);
    }

    /**
     * Cliente actual. Panel: roles internos → null, el resto su client_id, e IGNORA el guard
     * 'cliente' (#634). Portal (grupo 'flotas.portal'): el client_id del guard 'cliente'.
     */
    protected function clientId(): ?int
    {
        $resolver = app(CurrentClientResolver::class);

        return $this->enPortal() ? $resolver->resolve() : $resolver->resolveForPanel();
    }

    /**
     * Punto único de autorización. Panel: idéntico al `authorize()` de siempre (Spatie).
     * Portal: el cliente no es usuario web; se le permite todo salvo PORTAL_DENEGADOS
     * (la suscripción usable y el client_id ya los exige el middleware).
     */
    protected function autorizar(string $permiso): void
    {
        if ($this->enPortal()) {
            abort_if(in_array($permiso, self::PORTAL_DENEGADOS, true), 403, 'Función no disponible en el portal.');

            return;
        }

        $this->authorize($permiso);
    }

    protected function vehicleForClient(int $id): FleetVehicle
    {
        return FleetVehicle::forClient($this->clientId())->findOrFail($id);
    }
}
