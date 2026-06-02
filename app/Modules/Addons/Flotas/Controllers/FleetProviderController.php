<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetProviderController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('fleet.view');

        $providers = FleetProvider::where(fn($q) => $this->scopeClient($q))
            ->orderBy('name')
            ->get();

        return response()->json(['providers' => $providers]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('fleet.providers.manage');

        $data = $request->validate([
            'name'         => 'required|string|max:200',
            'type'         => 'nullable|in:workshop,dealer,parts,other',
            'contact_name' => 'nullable|string|max:150',
            'phone'        => 'nullable|string|max:20',
            'email'        => 'nullable|email',
            'address'      => 'nullable|string|max:255',
            'notes'        => 'nullable|string',
        ]);

        $data['client_id'] = $this->clientId();
        $provider = FleetProvider::create($data);

        return response()->json(['provider' => $provider], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('fleet.providers.manage');

        $provider = FleetProvider::where(fn($q) => $this->scopeClient($q))->findOrFail($id);
        $provider->update($request->except(['client_id']));

        return response()->json(['provider' => $provider->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('fleet.providers.manage');

        FleetProvider::where(fn($q) => $this->scopeClient($q))->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    private function scopeClient($q)
    {
        $user = auth()->user();
        if ($user->hasRole(['super-administrator', 'DESARROLLADOR'])) {
            return $q->whereNull('client_id');
        }
        return $q->where('client_id', $user->client_id ?? 0);
    }

    private function clientId(): ?int
    {
        $user = auth()->user();
        return $user->hasRole(['super-administrator', 'DESARROLLADOR']) ? null : ($user->client_id ?? null);
    }
}
