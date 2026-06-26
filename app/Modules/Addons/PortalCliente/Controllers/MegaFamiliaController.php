<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use App\Modules\Addons\MegaFamilia\Models\ParentalProfile;
use App\Modules\Addons\MegaFamilia\Models\ParentalRule;
use App\Services\Tenant\CurrentClientResolver;
use Illuminate\Support\Facades\Auth;

/**
 * Panel "MegaFamilia" en el portal de cliente (guard `cliente`). Solo lectura.
 *
 * Tenancy por client_isp_id denormalizado: las cuentas se resuelven con
 * ParentalAccount::forClient($clientId) y TODA query de hijos (perfiles, dispositivos,
 * reglas) deriva EXCLUSIVAMENTE del set de account_ids de esas cuentas. Así un
 * dispositivo/perfil de otro cliente nunca puede aparecer.
 */
class MegaFamiliaController extends Controller
{
    public function index(CurrentClientResolver $resolver)
    {
        $clientId = $resolver->resolve();
        $cmi      = Auth::guard('cliente')->user();

        // Fail-closed: sin client_id resuelto no se expone nada (forClient(null) → 0).
        $cuentas = $clientId
            ? ParentalAccount::forClient($clientId)
                ->withCount(['profiles', 'devices'])
                ->with(['profiles' => fn ($q) => $q->withCount('devices')
                    ->with([
                        'devices'   => fn ($d) => $d->orderBy('id'),
                        'appBlocks' => fn ($b) => $b->orderBy('id'),
                        'webBlocks' => fn ($b) => $b->orderBy('id'),
                        'schedules' => fn ($s) => $s->orderBy('id'),
                        'geofences' => fn ($g) => $g->orderBy('id'),
                    ])
                    ->orderBy('id')])
                ->get()
            : collect();

        // Sin MegaFamilia activa → estado vacío con CTA, NO error.
        if ($cuentas->isEmpty()) {
            return view('addon-portal-cliente::megafamilia', [
                'cmi'    => $cmi,
                'active' => false,
            ]);
        }

        // SEGURIDAD: el set de account_ids del cliente es la ÚNICA puerta a los hijos.
        $accountIds = $cuentas->pluck('id');
        $profileIds = ParentalProfile::whereIn('account_id', $accountIds)->pluck('id');

        // ParentalRule no tiene columna de estado (es 1:1 por perfil) → se cuentan todas,
        // siempre derivadas de los perfiles de las cuentas del cliente.
        $reglas = $profileIds->isEmpty() ? 0 : ParentalRule::whereIn('profile_id', $profileIds)->count();

        $standing = [
            'cuentas'      => $cuentas->count(),
            'perfiles'     => (int) $cuentas->sum('profiles_count'),
            'dispositivos' => (int) $cuentas->sum('devices_count'),
            'reglas'       => $reglas,
        ];

        return view('addon-portal-cliente::megafamilia', [
            'cmi'      => $cmi,
            'active'   => true,
            'cuentas'  => $cuentas,
            'standing' => $standing,
        ]);
    }
}
