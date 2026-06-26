<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Modules\Addons\MegaFamilia\Models\ParentalReward;
use App\Modules\Addons\MegaFamilia\Models\ParentalTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Escrituras de GAMIFICACIÓN — Tareas y Recompensas (G6) — guard cliente.
 *
 * Mapeos de schema (parental_rewards NO tiene titulo/costo_puntos):
 *   - Tarea:      titulo→title, descripcion→description, puntos→points.
 *   - Recompensa: titulo→detail, costo_puntos→value, type fijo 'points'.
 *     (Es una recompensa "canjeable por N puntos" definida por el padre; los
 *      campos granted_at/used_at quedan null — no es el grant automático del admin.)
 *
 * Ownership: tareas/recompensas se resuelven SOLO vía profile->tasks()/rewards();
 * requireProfile valida que {profile} ∈ cuentas del cliente; requireTask resuelve
 * la tarea solo si su perfil pertenece al cliente → abort(403).
 */
class MegaFamiliaGamificacionController extends MegaFamiliaBaseController
{
    /** Tarea resuelta SOLO si su perfil ∈ cuentas del cliente; si no, 403. */
    private function requireTask(int $id): ParentalTask
    {
        $task = ParentalTask::whereKey($id)
            ->whereHas('profile', fn ($q) => $q->whereIn('account_id', $this->accountIds()))
            ->first();
        abort_if(! $task, 403, 'Esa tarea no te pertenece.');

        return $task;
    }

    // ── Tareas ──────────────────────────────────────────────────────────────

    public function storeTarea(Request $request, int $profile): RedirectResponse
    {
        $perfil = $this->requireProfile($profile);

        $data = $request->validate([
            'titulo'      => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:1000',
            'puntos'      => 'nullable|integer|min:0|max:500',
        ]);

        ParentalTask::create([
            'profile_id'  => $perfil->id,
            'title'       => $data['titulo'],
            'description' => $data['descripcion'] ?? null,
            'points'      => $data['puntos'] ?? 0,
            'status'      => 'pending',
        ]);

        return redirect()->route('portal.megafamilia')
            ->with('success', "Tarea «{$data['titulo']}» creada.");
    }

    public function destroyTarea(int $profile, int $id): RedirectResponse
    {
        $perfil = $this->requireProfile($profile);
        $task   = $perfil->tasks()->whereKey($id)->first();
        abort_if(! $task, 403, 'Esa tarea no te pertenece.');

        $title = $task->title;
        $task->delete();

        return redirect()->route('portal.megafamilia')
            ->with('success', "Tarea «{$title}» eliminada.");
    }

    public function completarTarea(int $id): RedirectResponse
    {
        $task = $this->requireTask($id);

        // No existe Service de puntos en la app: el completado solo cambia estado.
        if ($task->status !== 'pending') {
            return redirect()->route('portal.megafamilia')
                ->with('info', "La tarea «{$task->title}» ya no estaba pendiente.");
        }

        $task->update(['status' => 'completed', 'completed_at' => now()]);

        return redirect()->route('portal.megafamilia')
            ->with('success', "Tarea «{$task->title}» marcada como completada.");
    }

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
}
