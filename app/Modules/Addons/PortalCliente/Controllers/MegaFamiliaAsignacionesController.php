<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Modules\Addons\MegaFamilia\Models\ParentalTaskAssignment;
use Illuminate\Http\RedirectResponse;

/**
 * Completar/rechazar ASIGNACIONES de tareas (G6 refactor) — guard cliente.
 *
 * Ownership: la asignación se resuelve SOLO si su perfil ∈ cuentas del cliente
 * (whereHas profile.account_id ∈ accountIds) → abort(403). Completar suma puntos
 * al balance del perfil (se recalcula en vivo desde las asignaciones completadas).
 */
class MegaFamiliaAsignacionesController extends MegaFamiliaBaseController
{
    private function requireAssignment(int $id): ParentalTaskAssignment
    {
        $asg = ParentalTaskAssignment::whereKey($id)
            ->whereHas('profile', fn ($q) => $q->whereIn('account_id', $this->accountIds()))
            ->with('task')
            ->first();
        abort_if(! $asg, 403, 'Esa asignación no te pertenece.');

        return $asg;
    }

    public function completar(int $id): RedirectResponse
    {
        $asg = $this->requireAssignment($id);

        if ($asg->status !== 'pending') {
            return redirect()->to(route('portal.megafamilia'))
                ->with('info', 'Esa tarea ya no estaba pendiente.');
        }

        $asg->update(['status' => 'completed', 'completed_at' => now()]);

        $titulo = optional($asg->task)->title ?? 'Tarea';

        return redirect()->to(route('portal.megafamilia'))
            ->with('success', "«{$titulo}» marcada como completada.");
    }
}
