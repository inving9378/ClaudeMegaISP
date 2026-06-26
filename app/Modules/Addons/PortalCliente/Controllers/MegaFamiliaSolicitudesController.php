<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use App\Modules\Addons\MegaFamilia\Models\ParentalRequest;
use App\Modules\Addons\MegaFamilia\Models\ParentalReward;
use App\Modules\Addons\PortalCliente\Support\MegaFamiliaBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * SOLICITUDES de permiso y CANJE (G7) — guard cliente.
 *
 * Dos tipos (columna `type`):
 *   - permission (time_extra/app_unlock/web_unlock): el padre solo cambia status.
 *   - redemption: el padre aprueba → se OTORGA la recompensa creando una
 *     ParentalReward con granted_at=now() (que descuenta del balance del perfil).
 *
 * Scoping por relación: la solicitud se resuelve SOLO si su perfil ∈ cuentas del
 * cliente. aprobar/rechazar usan requireRequest → abort(403) ante id ajeno.
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
                ->with(['profile', 'device', 'reward'])
                ->orderByDesc('created_at')
                ->get();

        // Balance por perfil (para mostrar suficiencia en las solicitudes de canje).
        $balances = MegaFamiliaBalance::forProfiles(
            $solicitudes->pluck('profile_id')->all()
        );

        return view('addon-portal-cliente::megafamilia_solicitudes', [
            'cmi'         => auth('cliente')->user(),
            'solicitudes' => $solicitudes,
            'balances'    => $balances,
        ]);
    }

    public function aprobar(int $id): RedirectResponse
    {
        $req = $this->requireRequest($id);

        if ($req->status !== 'pending') {
            return redirect()->route('portal.megafamilia.solicitudes')
                ->with('info', 'Esa solicitud ya había sido respondida.');
        }

        // CANJE: otorgar la recompensa (crear ParentalReward con granted_at).
        if ($req->type === 'redemption') {
            $reward = $req->reward; // recompensa-catálogo solicitada

            if (! $reward) {
                return redirect()->route('portal.megafamilia.solicitudes')
                    ->with('info', 'La recompensa de esta solicitud ya no existe.');
            }

            // Revalida balance al momento de aprobar (pudo cambiar desde la solicitud).
            $balance = MegaFamiliaBalance::forProfile((int) $req->profile_id);
            if ($balance < (int) $reward->value) {
                return redirect()->route('portal.megafamilia.solicitudes')
                    ->with('info', "Balance insuficiente ({$balance} pts) para aprobar el canje de «{$reward->detail}» ({$reward->value} pts).");
            }

            ParentalReward::create([
                'profile_id' => $req->profile_id,
                'type'       => 'points',
                'value'      => $reward->value,
                'detail'     => $reward->detail,
                'granted_at' => now(),
                'expires_at' => now()->addDays(7),
            ]);

            $req->update(['status' => 'approved', 'responded_at' => now()]);

            $hijo = optional($req->profile)->name ?? 'Tu hijo';

            return redirect()->route('portal.megafamilia.solicitudes')
                ->with('success', "Canje aprobado. {$hijo} ahora tiene «{$reward->detail}».");
        }

        // PERMISO: solo cambia status.
        $req->update(['status' => 'approved', 'responded_at' => now()]);

        return redirect()->route('portal.megafamilia.solicitudes')
            ->with('success', 'Solicitud aprobada.');
    }

    public function rechazar(int $id): RedirectResponse
    {
        $req = $this->requireRequest($id);

        if ($req->status !== 'pending') {
            return redirect()->route('portal.megafamilia.solicitudes')
                ->with('info', 'Esa solicitud ya había sido respondida.');
        }

        $req->update(['status' => 'rejected', 'responded_at' => now()]);

        $palabra = $req->type === 'redemption' ? 'Canje rechazado.' : 'Solicitud rechazada.';

        return redirect()->route('portal.megafamilia.solicitudes')->with('success', $palabra);
    }

    /** Solicitud resuelta SOLO si su perfil ∈ cuentas del cliente; si no, 403. */
    private function requireRequest(int $id): ParentalRequest
    {
        $req = ParentalRequest::whereKey($id)
            ->whereHas('profile', fn ($q) => $q->whereIn('account_id', $this->accountIds()))
            ->with('reward')
            ->first();
        abort_if(! $req, 403, 'Esa solicitud no te pertenece.');

        return $req;
    }
}
