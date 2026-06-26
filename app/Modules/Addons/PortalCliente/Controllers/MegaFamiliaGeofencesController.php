<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Modules\Addons\MegaFamilia\Models\ParentalGeofence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Escrituras de GEOCERCAS familiares (G5) — guard cliente.
 *
 * OJO de schema: parental_geofences NO tiene columna account_id — solo profile_id.
 * Por eso cada geocerca se ata a un PERFIL (selector obligatorio) y el ownership
 * se verifica como "el perfil ∈ cuentas del cliente" (requireProfile) y la geocerca
 * se resuelve SOLO a través de ese perfil → abort(403) ante id ajeno.
 * client_isp_id lo deriva el observer desde el perfil.
 *
 * Sin mapa Leaflet (mejora futura): lat/lng/radio como inputs numéricos.
 */
class MegaFamiliaGeofencesController extends MegaFamiliaBaseController
{
    private function rules(): array
    {
        return [
            'profile_id'     => 'required|integer',
            'nombre'         => 'required|string|max:100',
            'latitud'        => 'required|numeric|between:-90,90',
            'longitud'       => 'required|numeric|between:-180,180',
            'radio_metros'   => 'required|integer|min:50|max:10000',
            'direccion'      => 'nullable|string|max:255',
            'alert_on_enter' => 'nullable|boolean',
            'alert_on_exit'  => 'nullable|boolean',
            'active'         => 'nullable|boolean',
        ];
    }

    /** Geocerca resuelta SOLO si su perfil ∈ cuentas del cliente; si no, 403. */
    private function requireGeofence(int $id): ParentalGeofence
    {
        $geo = ParentalGeofence::whereKey($id)
            ->whereHas('profile', fn ($q) => $q->whereIn('account_id', $this->accountIds()))
            ->first();
        abort_if(! $geo, 403, 'Esa geocerca no te pertenece.');

        return $geo;
    }

    public function store(Request $request): RedirectResponse
    {
        $data   = $request->validate($this->rules());
        $perfil = $this->requireProfile((int) $data['profile_id']); // ownership

        ParentalGeofence::create([
            'profile_id'     => $perfil->id,
            'name'           => $data['nombre'],
            'address'        => $data['direccion'] ?? null,
            'lat'            => $data['latitud'],
            'lng'            => $data['longitud'],
            'radius_meters'  => $data['radio_metros'],
            'alert_on_enter' => $request->boolean('alert_on_enter'),
            'alert_on_exit'  => $request->boolean('alert_on_exit'),
            'active'         => $request->boolean('active'),
        ]);

        return redirect()->route('portal.megafamilia')
            ->with('success', "Geocerca «{$data['nombre']}» creada.");
    }

    public function edit(int $id): View
    {
        $geo      = $this->requireGeofence($id);
        $profiles = $this->requireAccount($geo->profile->account_id)->profiles()->orderBy('name')->get();

        return view('addon-portal-cliente::megafamilia_geocerca_edit', [
            'cmi'      => auth('cliente')->user(),
            'geo'      => $geo,
            'profiles' => $profiles,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $geo    = $this->requireGeofence($id);
        $data   = $request->validate($this->rules());
        $perfil = $this->requireProfile((int) $data['profile_id']); // el nuevo perfil también debe ser del cliente

        $geo->update([
            'profile_id'     => $perfil->id,
            'name'           => $data['nombre'],
            'address'        => $data['direccion'] ?? null,
            'lat'            => $data['latitud'],
            'lng'            => $data['longitud'],
            'radius_meters'  => $data['radio_metros'],
            'alert_on_enter' => $request->boolean('alert_on_enter'),
            'alert_on_exit'  => $request->boolean('alert_on_exit'),
            'active'         => $request->boolean('active'),
        ]);

        return redirect()->route('portal.megafamilia')
            ->with('success', "Geocerca «{$geo->name}» actualizada.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $geo  = $this->requireGeofence($id);
        $name = $geo->name;
        $geo->delete();

        return redirect()->route('portal.megafamilia')
            ->with('success', "Geocerca «{$name}» eliminada.");
    }
}
