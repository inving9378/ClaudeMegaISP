<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Modules\Addons\MegaFamilia\Models\ParentalGeofence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Escrituras de GEOCERCAS familiares (G5) — guard cliente.
 *
 * Dos formas (columna `type`):
 *   - circle:  lat/lng/radius_meters (coordinates = null).
 *   - polygon: coordinates = array JSON [[lat,lng],...] (≥3 vértices). lat/lng se
 *     llenan con el CENTROIDE (columnas NOT NULL) y radius_meters = 0.
 *
 * parental_geofences NO tiene account_id — cada geocerca se ata a un PERFIL; el
 * ownership se verifica vía el perfil (requireProfile) y requireGeofence resuelve
 * la geocerca solo si su perfil ∈ cuentas del cliente → abort(403).
 */
class MegaFamiliaGeofencesController extends MegaFamiliaBaseController
{
    /** Geocerca resuelta SOLO si su perfil ∈ cuentas del cliente; si no, 403. */
    private function requireGeofence(int $id): ParentalGeofence
    {
        $geo = ParentalGeofence::whereKey($id)
            ->whereHas('profile', fn ($q) => $q->whereIn('account_id', $this->accountIds()))
            ->first();
        abort_if(! $geo, 403, 'Esa geocerca no te pertenece.');

        return $geo;
    }

    /**
     * Valida la entrada según el tipo y devuelve [profile_id, payload].
     * El payload trae type/lat/lng/radius_meters/coordinates + campos comunes.
     */
    private function validatedGeofence(Request $request): array
    {
        $base = $request->validate([
            'profile_id'     => 'required|integer',
            'nombre'         => 'required|string|max:100',
            'tipo'           => 'required|in:circle,polygon',
            'direccion'      => 'nullable|string|max:255',
            'alert_on_enter' => 'nullable|boolean',
            'alert_on_exit'  => 'nullable|boolean',
            'active'         => 'nullable|boolean',
        ]);

        if ($base['tipo'] === 'circle') {
            $c = $request->validate([
                'latitud'      => 'required|numeric|between:-90,90',
                'longitud'     => 'required|numeric|between:-180,180',
                'radio_metros' => 'required|integer|min:50|max:10000',
            ]);
            $payload = [
                'type'          => 'circle',
                'lat'           => $c['latitud'],
                'lng'           => $c['longitud'],
                'radius_meters' => $c['radio_metros'],
                'coordinates'   => null,
            ];
        } else {
            // coordinates llega como JSON string desde el input hidden.
            $coords = json_decode((string) $request->input('coordinates'), true);
            $request->merge(['coordinates' => $coords]);
            $request->validate([
                'coordinates'     => 'required|array|min:3',
                'coordinates.*'   => 'array|size:2',
                'coordinates.*.0' => 'numeric|between:-90,90',
                'coordinates.*.1' => 'numeric|between:-180,180',
            ]);
            [$clat, $clng] = $this->centroid($coords);
            $payload = [
                'type'          => 'polygon',
                'coordinates'   => $coords,
                'lat'           => $clat,
                'lng'           => $clng,
                'radius_meters' => 0, // irrelevante para polígono (columna NOT NULL)
            ];
        }

        $payload += [
            'name'           => $base['nombre'],
            'address'        => $base['direccion'] ?? null,
            'alert_on_enter' => $request->boolean('alert_on_enter'),
            'alert_on_exit'  => $request->boolean('alert_on_exit'),
            'active'         => $request->boolean('active'),
        ];

        return [(int) $base['profile_id'], $payload];
    }

    /** Centroide (promedio de vértices) → [lat, lng] con 7 decimales. */
    private function centroid(array $coords): array
    {
        $n = count($coords);
        $sumLat = array_sum(array_column($coords, 0));
        $sumLng = array_sum(array_column($coords, 1));

        return [round($sumLat / $n, 7), round($sumLng / $n, 7)];
    }

    public function store(Request $request): RedirectResponse
    {
        [$profileId, $payload] = $this->validatedGeofence($request);
        $perfil = $this->requireProfile($profileId); // ownership
        $payload['profile_id'] = $perfil->id;

        ParentalGeofence::create($payload);

        return redirect()->route('portal.megafamilia')
            ->with('success', "Geocerca «{$payload['name']}» creada.");
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
        $geo = $this->requireGeofence($id);
        [$profileId, $payload] = $this->validatedGeofence($request);
        $perfil = $this->requireProfile($profileId); // el nuevo perfil también debe ser del cliente
        $payload['profile_id'] = $perfil->id;

        $geo->update($payload);

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
