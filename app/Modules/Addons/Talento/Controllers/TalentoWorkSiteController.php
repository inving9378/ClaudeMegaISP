<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoWorkSite;
use Illuminate\Http\Request;

class TalentoWorkSiteController extends Controller
{
    public function data(Request $request)
    {
        $this->authorize('talento.work_sites.view');

        $q = TalentoWorkSite::when($request->active, fn($q) => $q->active())
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%$s%"))
            ->orderBy('name')
            ->get();

        return response()->json($q);
    }

    public function store(Request $request)
    {
        $this->authorize('talento.work_sites.manage');

        $data = $request->validate([
            'name'      => 'required|string|max:120',
            'type'      => 'required|in:bodega,oficina,predio,otro',
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_m'  => 'required|integer|min:10|max:5000',
            'active'    => 'boolean',
        ]);

        return response()->json(TalentoWorkSite::create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $this->authorize('talento.work_sites.manage');

        $site = TalentoWorkSite::findOrFail($id);

        $data = $request->validate([
            'name'      => 'sometimes|string|max:120',
            'type'      => 'sometimes|in:bodega,oficina,predio,otro',
            'latitude'  => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'radius_m'  => 'sometimes|integer|min:10|max:5000',
            'active'    => 'sometimes|boolean',
        ]);

        $site->update($data);
        return response()->json($site);
    }

    public function destroy($id)
    {
        $this->authorize('talento.work_sites.manage');

        $site = TalentoWorkSite::findOrFail($id);
        $site->delete();

        return response()->json(['ok' => true]);
    }

    public function defaultRadius()
    {
        $this->authorize('talento.work_sites.view');

        $radius = (int) \Illuminate\Support\Facades\DB::table('settings')
            ->where('key', 'talento_checkin_default_radius_m')
            ->value('value') ?? 300;

        return response()->json(['default_radius_m' => $radius]);
    }

    public function updateDefaultRadius(Request $request)
    {
        $this->authorize('talento.work_sites.manage');

        $data = $request->validate(['radius_m' => 'required|integer|min:10|max:5000']);

        \Illuminate\Support\Facades\DB::table('settings')->updateOrInsert(
            ['key' => 'talento_checkin_default_radius_m'],
            ['value' => (string)$data['radius_m'], 'updated_at' => now()]
        );

        return response()->json(['default_radius_m' => $data['radius_m']]);
    }
}
