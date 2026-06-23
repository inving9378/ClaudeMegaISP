<?php

namespace App\Services\Tenant;

use App\Models\Client;
use App\Models\ClientMainInformation;
use Illuminate\Support\Facades\Auth;

/**
 * Resuelve "el cliente actual" (clients.id) de forma UNIFORME para los tres guards:
 * panel admin (web), portal ('cliente') y app móvil ('sanctum'). Las tres caras
 * resuelven al MISMO client_id → es la base de \App\Traits\BelongsToClientTenant.
 *
 * GARANTÍA: el guard web admin replica EXACTAMENTE FleetBaseController::clientId():
 * super-administrator/DESARROLLADOR → null (ven los registros internos Meganet);
 * cualquier otro usuario → su client_id. Así Flotas no cambia de comportamiento.
 */
class CurrentClientResolver
{
    /** Roles internos Meganet: client_id null → ven registros sin dueño. */
    private const INTERNAL_ROLES = ['super-administrator', 'DESARROLLADOR'];

    public function resolve(): ?int
    {
        // Portal: guard de sesión 'cliente', inequívoco.
        if (Auth::guard('cliente')->check()) {
            return $this->fromPortal();
        }

        // App móvil: SOLO si hay un Bearer token real. El guard 'sanctum' hace
        // fallback al guard web cuando NO hay token (feature SPA de Sanctum), así
        // que sin esta condición un request del panel admin se confundiría con la
        // API y Flotas cambiaría de comportamiento. Con token → es la API.
        if (request()->bearerToken() && Auth::guard('sanctum')->check()) {
            return $this->fromApi();
        }

        // Panel admin (guard web por defecto).
        return $this->fromWebAdmin();
    }

    /**
     * Panel admin (guard web). Equivalente EXACTO a FleetBaseController::clientId():
     * roles internos → null; el resto → su client_id.
     */
    private function fromWebAdmin(): ?int
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        return $user->hasRole(self::INTERNAL_ROLES) ? null : ($user->client_id ?? null);
    }

    /** Portal cliente (guard 'cliente'): el CMI autenticado expone client_id. */
    private function fromPortal(): ?int
    {
        $user = Auth::guard('cliente')->user();

        return ($user && $user->client_id) ? (int) $user->client_id : null;
    }

    /**
     * App móvil (guard 'sanctum'): users.id → Client; si no, login_user → CMI → client_id.
     * Mismo camino que ApiController::resolveClientForCurrentUser().
     */
    private function fromApi(): ?int
    {
        $user = Auth::guard('sanctum')->user();
        if (! $user) {
            return null;
        }

        $client = Client::where('user_id', $user->id)->first();
        if ($client) {
            return (int) $client->id;
        }

        if (! $user->login_user) {
            return null;
        }

        $cmi = ClientMainInformation::where('user', $user->login_user)->first();

        return $cmi ? (int) $cmi->client_id : null;
    }
}
