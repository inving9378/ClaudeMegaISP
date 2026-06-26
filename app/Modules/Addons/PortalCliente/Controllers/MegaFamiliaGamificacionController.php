<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Modules\Addons\MegaFamilia\Models\ParentalRequest;
use App\Modules\Addons\MegaFamilia\Models\ParentalReward;
use App\Modules\Addons\PortalCliente\Support\MegaFamiliaBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * RECOMPENSAS y CANJE (G6 refactor) — guard cliente.
 *
 * (Las TAREAS pasaron a nivel de cuenta + asignaciones: ver
 * MegaFamiliaAsignacionTareasController / MegaFamiliaAsignacionesController.)
 *
 * Recompensa (parental_rewards NO tiene titulo/costo_puntos):
 *   titulo→detail, costo_puntos→value, type fijo 'points'. Catálogo = granted_at NULL.
 *
 * Ownership: recompensas se resuelven SOLO vía profile->rewards(); requireProfile
 * valida que {profile} ∈ cuentas del cliente → abort(403).
 */
class MegaFamiliaGamificacionController extends MegaFamiliaBaseController
{
    // ── Recompensas ───────────────────────────────────────────────────────────

    public function storeRecompensa(Request $request, int $profile): RedirectResponse
    {
        $perfil = $this->requireProfile($profile);

        $data = $request->validate([
            'titulo'       => 'required|string|max:100',
            'costo_puntos' => 'nullable|integer|min:0|max:500',
        ]);

        ParentalReward::create([
            'profile_id' => $perfil->id,
            'type'       => 'points',
            'value'      => $data['costo_puntos'] ?? 0,
            'detail'     => $data['titulo'],
        ]);

        return redirect()->route('portal.megafamilia')
            ->with('success', "Recompensa «{$data['titulo']}» creada.");
    }

    public function destroyRecompensa(int $profile, int $id): RedirectResponse
    {
        $perfil = $this->requireProfile($profile);
        $reward = $perfil->rewards()->whereKey($id)->first();
        abort_if(! $reward, 403, 'Esa recompensa no te pertenece.');

        $detail = $reward->detail;
        $reward->delete();

        return redirect()->route('portal.megafamilia')
            ->with('success', "Recompensa «{$detail}» eliminada.");
    }

    /**
     * Solicitud de CANJE: el hijo pide canjear una recompensa-catálogo por puntos.
     * Crea una ParentalRequest type='redemption' pendiente que el padre aprueba en G7.
     * Valida ownership de la recompensa y que el balance del perfil alcance el costo.
     */
    public function canjearRecompensa(int $reward_id): RedirectResponse
    {
        // Solo recompensas-catálogo (granted_at NULL) pueden canjearse.
        $reward = ParentalReward::whereKey($reward_id)
            ->whereNull('granted_at')
            ->whereHas('profile', fn ($q) => $q->whereIn('account_id', $this->accountIds()))
            ->first();
        abort_if(! $reward, 403, 'Esa recompensa no te pertenece.');

        // Validación de balance (Σ tareas completadas − Σ recompensas otorgadas).
        $balance = MegaFamiliaBalance::forProfile((int) $reward->profile_id);
        if ($balance < (int) $reward->value) {
            return redirect()->route('portal.megafamilia')
                ->with('info', "Balance insuficiente para canjear «{$reward->detail}» ({$reward->value} pts). Disponible: {$balance} pts.");
        }

        // Evita duplicar una solicitud de canje pendiente de la misma recompensa.
        $yaPendiente = ParentalRequest::where('reward_id', $reward->id)
            ->where('status', 'pending')
            ->exists();
        if ($yaPendiente) {
            return redirect()->to(route('portal.megafamilia') . '#solicitudes')
                ->with('info', "Ya hay una solicitud de canje pendiente para «{$reward->detail}».");
        }

        ParentalRequest::create([
            'profile_id' => $reward->profile_id,
            'reward_id'  => $reward->id,
            'type'       => 'redemption',
            'status'     => 'pending',
            'detail'     => $reward->detail,
            'message'    => "Solicita canjear «{$reward->detail}» por {$reward->value} pts",
            'expires_at' => now()->addDays(7),
        ]);

        return redirect()->to(route('portal.megafamilia') . '#solicitudes')
            ->with('success', 'Solicitud de canje enviada para aprobación.');
    }
}
