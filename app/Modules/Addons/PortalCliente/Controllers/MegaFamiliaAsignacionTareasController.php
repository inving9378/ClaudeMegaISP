<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Modules\Addons\MegaFamilia\Models\ParentalTask;
use App\Modules\Addons\MegaFamilia\Models\ParentalTaskAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Tareas a nivel de CUENTA + asignaciones por perfil (G6 refactor) — guard cliente.
 *
 * El padre crea UNA tarea de la cuenta y la asigna a 1..N perfiles; cada asignación
 * (ParentalTaskAssignment) lleva su propio estado. `assignment_type`:
 *   - cada_uno: la tarea aplica a cada perfil asignado (N asignaciones).
 *   - solo_uno: la tarea la hace un solo perfil (máx 1 asignación).
 *
 * profile_id de la tarea (NOT NULL, legacy admin/API) se fija al primer perfil
 * asignado; la VERDAD de a quién está asignada vive en las assignments.
 */
class MegaFamiliaAsignacionTareasController extends MegaFamiliaBaseController
{
    public function store(Request $request): RedirectResponse
    {
        $account = $this->requireAccount($request->integer('account_id') ?: null);

        $data = $request->validate([
            'account_id'      => 'nullable|integer',
            'titulo'          => 'required|string|max:100',
            'descripcion'     => 'nullable|string|max:1000',
            'puntos'          => 'nullable|integer|min:0|max:500',
            'assignment_type' => 'required|in:cada_uno,solo_uno',
            'profiles'        => 'required|array|min:1',
            'profiles.*'      => 'integer',
        ]);

        // solo_uno → un único perfil.
        if ($data['assignment_type'] === 'solo_uno' && count($data['profiles']) > 1) {
            throw ValidationException::withMessages([
                'profiles' => 'Una tarea «solo uno» se asigna a un único perfil.',
            ]);
        }

        // Ownership: TODOS los perfiles deben pertenecer a esta cuenta del cliente.
        $valid = $account->profiles()->whereIn('id', $data['profiles'])->pluck('id');
        abort_if($valid->count() !== count(array_unique($data['profiles'])), 403, 'Perfil ajeno a la cuenta.');

        $task = ParentalTask::create([
            'account_id'      => $account->id,
            'profile_id'      => $valid->first(), // legacy NOT NULL; la verdad son las assignments
            'title'           => $data['titulo'],
            'description'     => $data['descripcion'] ?? null,
            'points'          => $data['puntos'] ?? 0,
            'assignment_type' => $data['assignment_type'],
            'status'          => 'pending',
        ]);

        foreach ($valid as $pid) {
            ParentalTaskAssignment::create([
                'task_id'    => $task->id,
                'profile_id' => $pid,
                'account_id' => $account->id,
                'status'     => 'pending',
            ]);
        }

        return redirect()->to(route('portal.megafamilia') . '#asignaciones')
            ->with('success', "Tarea «{$data['titulo']}» asignada a {$valid->count()} perfil(es).");
    }

    public function destroy(int $id): RedirectResponse
    {
        $task = ParentalTask::whereKey($id)
            ->whereIn('account_id', $this->accountIds())
            ->has('assignments')
            ->first();
        abort_if(! $task, 403, 'Esa tarea no te pertenece.');

        $title = $task->title;
        $task->delete(); // cascade → assignments

        return redirect()->to(route('portal.megafamilia') . '#asignaciones')
            ->with('success', "Tarea «{$title}» eliminada.");
    }
}
