<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Modules\Addons\MegaFamilia\Models\ParentalSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Escrituras de HORARIOS de internet (G4) — guard cliente.
 *
 * parental_schedules guarda `days` como ARRAY JSON (multi-día), `start_time`/
 * `end_time` (TIME) y `action` enum block/allow. El form ofrece selección de uno
 * o varios días (checkboxes) que se persisten como array de enteros 0-6 (0=Domingo).
 *
 * Ownership: el horario se resuelve SOLO vía profile->schedules(); requireProfile
 * valida que {profile} ∈ cuentas del cliente → abort(403). client_isp_id lo deriva
 * el observer del perfil.
 */
class MegaFamiliaHorariosController extends MegaFamiliaBaseController
{
    private function rules(): array
    {
        return [
            'nombre'      => 'required|string|max:100',
            'days'        => 'required|array|min:1',
            'days.*'      => 'integer|between:0,6',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin'    => 'required|date_format:H:i|after:hora_inicio',
            'action'      => 'required|in:block,allow',
            'active'      => 'nullable|boolean',
        ];
    }

    public function store(Request $request, int $profile): RedirectResponse
    {
        $perfil = $this->requireProfile($profile);
        $data   = $request->validate($this->rules());

        ParentalSchedule::create([
            'profile_id' => $perfil->id,
            'name'       => $data['nombre'],
            'days'       => array_map('intval', $data['days']),
            'start_time' => $data['hora_inicio'],
            'end_time'   => $data['hora_fin'],
            'action'     => $data['action'],
            'active'     => $request->boolean('active'),
        ]);

        return redirect()->route('portal.megafamilia')
            ->with('success', "Horario «{$data['nombre']}» creado.");
    }

    public function edit(int $profile, int $id): View
    {
        $perfil   = $this->requireProfile($profile);
        $schedule = $perfil->schedules()->whereKey($id)->first();
        abort_if(! $schedule, 403, 'Ese horario no te pertenece.');

        return view('addon-portal-cliente::megafamilia_horario_edit', [
            'cmi'      => auth('cliente')->user(),
            'perfil'   => $perfil,
            'schedule' => $schedule,
        ]);
    }

    public function update(Request $request, int $profile, int $id): RedirectResponse
    {
        $perfil   = $this->requireProfile($profile);
        $schedule = $perfil->schedules()->whereKey($id)->first();
        abort_if(! $schedule, 403, 'Ese horario no te pertenece.');

        $data = $request->validate($this->rules());

        $schedule->update([
            'name'       => $data['nombre'],
            'days'       => array_map('intval', $data['days']),
            'start_time' => $data['hora_inicio'],
            'end_time'   => $data['hora_fin'],
            'action'     => $data['action'],
            'active'     => $request->boolean('active'),
        ]);

        return redirect()->route('portal.megafamilia')
            ->with('success', "Horario «{$schedule->name}» actualizado.");
    }

    public function destroy(int $profile, int $id): RedirectResponse
    {
        $perfil   = $this->requireProfile($profile);
        $schedule = $perfil->schedules()->whereKey($id)->first();
        abort_if(! $schedule, 403, 'Ese horario no te pertenece.');

        $name = $schedule->name;
        $schedule->delete();

        return redirect()->route('portal.megafamilia')
            ->with('success', "Horario «{$name}» eliminado.");
    }
}
