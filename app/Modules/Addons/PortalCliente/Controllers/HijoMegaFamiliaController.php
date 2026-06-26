<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Modules\Addons\PortalCliente\Support\MegaFamiliaBalance;
use Illuminate\View\View;

/**
 * Vista previa READ-ONLY de la app del hijo, para que el papá vea desde el portal
 * cómo se vería MegaFamilia en el dispositivo del hijo.
 *
 * Ownership: requireProfile (heredado) valida que el perfil ∈ cuentas del cliente
 * → abort(403). Todo es solo lectura: los botones de acción van deshabilitados.
 */
class HijoMegaFamiliaController extends MegaFamiliaBaseController
{
    public function index(int $profile_id): View
    {
        $profile = $this->requireProfile($profile_id);
        $profile->load([
            'account',
            'taskAssignments' => fn ($a) => $a->with('task')->orderByDesc('id'),
            'rewards'         => fn ($r) => $r->orderByDesc('id'),
            'devices'         => fn ($d) => $d->orderBy('id'),
            'schedules'       => fn ($s) => $s->orderBy('id'),
        ]);

        return view('addon-portal-cliente::hijo_megafamilia_preview', [
            'cmi'          => auth('cliente')->user(),
            'profile'      => $profile,
            'balance'      => MegaFamiliaBalance::forProfile($profile->id),
            'tareas'       => $profile->taskAssignments,
            'recompensas'  => $profile->rewards,
            'dispositivos' => $profile->devices,
            'horarios'     => $profile->schedules,
        ]);
    }
}
