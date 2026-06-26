<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use App\Modules\Addons\MegaFamilia\Models\ParentalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * SOLICITUDES de permiso (G7) — guard cliente.
 *
 * Las solicitudes las generan los dispositivos hijo (tiempo extra, desbloquear
 * app/sitio). El padre las aprueba/rechaza desde el portal.
 *
 * Scoping por relación: la solicitud se resuelve SOLO si su perfil ∈ cuentas del
 * cliente (whereHas profile.account_id ∈ accountIds). aprobar/rechazar usan
 * requireRequest → abort(403) ante id ajeno.
 */
class MegaFamiliaSolicitudesController extends MegaFamiliaBaseController
{
    public function index(): View
    {
        // Lectura tolerante: sin cuenta MegaFamilia → estado vacío (no 403, no fuga).
        $accountIds = ParentalAccount::forClient($this->clientId())->pluck('id')->all();

        $solicitudes = empty($accountIds)
            ? collect()
            : ParentalRequest::whereHas('profile', fn ($q) => $q->whereIn('account_id', $accountIds))
                ->where('status', 'pending')
                ->with(['profile', 'device'])
                ->orderByDesc('id')
                ->get();

        return view('addon-portal-cliente::megafamilia_solicitudes', [
            'cmi'         => auth('cliente')->user(),
            'solicitudes' => $solicitudes,
        ]);
    }

    public function aprobar(int $id): RedirectResponse
    {
        return $this->responder($id, 'approved', 'aprobada');
    }

    public function rechazar(int $id): RedirectResponse
    {
        return $this->responder($id, 'rejected', 'rechazada');
    }

    private function responder(int $id, string $status, string $palabra): RedirectResponse
    {
        $req = $this->requireRequest($id);

        // Idempotencia: solo se responde lo pendiente.
        if ($req->status !== 'pending') {
            return redirect()->route('portal.megafamilia.solicitudes')
                ->with('info', 'Esa solicitud ya había sido respondida.');
        }

        $req->update(['status' => $status, 'responded_at' => now()]);

        return redirect()->route('portal.megafamilia.solicitudes')
            ->with('success', "Solicitud {$palabra}.");
    }

    /** Solicitud resuelta SOLO si su perfil ∈ cuentas del cliente; si no, 403. */
    private function requireRequest(int $id): ParentalRequest
    {
        $req = ParentalRequest::whereKey($id)
            ->whereHas('profile', fn ($q) => $q->whereIn('account_id', $this->accountIds()))
            ->first();
        abort_if(! $req, 403, 'Esa solicitud no te pertenece.');

        return $req;
    }
}
