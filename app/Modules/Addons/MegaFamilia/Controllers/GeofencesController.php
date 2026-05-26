<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalGeofence;
use App\Modules\Addons\MegaFamilia\Models\ParentalProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeofencesController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::geofences.index');
    }

    public function data(Request $request): JsonResponse
    {
        $list = ParentalGeofence::query()
            ->with('profile:id,name,profile_type,photo')
            ->when($request->profile_id, fn ($q, $v) => $q->where('profile_id', $v))
            ->when($request->active === 'true',  fn ($q) => $q->where('active', true))
            ->when($request->active === 'false', fn ($q) => $q->where('active', false))
            ->orderByDesc('id')
            ->get();

        $profiles = ParentalProfile::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'profile_type', 'photo']);

        return response()->json(['list' => $list, 'profiles' => $profiles]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateGeofence($request, true);
        $data = $this->normalizeTriggers($data);
        $geo = ParentalGeofence::create($data);
        return response()->json(['success' => true, 'geofence' => $geo]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $geo = ParentalGeofence::findOrFail($id);
        $data = $this->validateGeofence($request, false);
        $data = $this->normalizeTriggers($data);
        $geo->update($data);
        return response()->json(['success' => true, 'geofence' => $geo]);
    }

    public function destroy(int $id): JsonResponse
    {
        ParentalGeofence::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function toggle(int $id): JsonResponse
    {
        $geo = ParentalGeofence::findOrFail($id);
        $geo->update(['active' => ! $geo->active]);
        return response()->json(['success' => true, 'active' => (bool) $geo->active]);
    }

    private function validateGeofence(Request $request, bool $isCreate): array
    {
        $rule = $isCreate ? 'required' : 'sometimes';
        return $request->validate([
            'profile_id'     => "{$rule}|exists:parental_profiles,id",
            'name'           => "{$rule}|string|max:120",
            'address'        => 'sometimes|nullable|string|max:500',
            'type'           => 'sometimes|nullable|string|max:64',
            'lat'            => "{$rule}|numeric|between:-90,90",
            'lng'            => "{$rule}|numeric|between:-180,180",
            'radius_meters'  => "{$rule}|integer|min:50|max:50000",
            'trigger_type'   => 'sometimes|in:enter,exit,both',
            'alert_on_enter' => 'sometimes|boolean',
            'alert_on_exit'  => 'sometimes|boolean',
            'active'         => 'sometimes|boolean',
        ]);
    }

    /**
     * Acepta `trigger_type` (enter/exit/both) y lo mapea a los booleanos
     * `alert_on_enter` / `alert_on_exit` del schema. Si se mandan los
     * booleanos directos, se respetan.
     */
    private function normalizeTriggers(array $data): array
    {
        if (isset($data['trigger_type'])) {
            $t = $data['trigger_type'];
            $data['alert_on_enter'] = in_array($t, ['enter', 'both'], true);
            $data['alert_on_exit']  = in_array($t, ['exit',  'both'], true);
            unset($data['trigger_type']);
        }
        return $data;
    }
}
