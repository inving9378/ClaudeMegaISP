<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Modules\Addons\Flotas\Models\FleetProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetProviderController extends FleetBaseController
{
    public function index(): JsonResponse
    {
        $this->authorize('fleet.view');

        return response()->json([
            'providers' => FleetProvider::forClient($this->clientId())->orderBy('name')->get(),
        ]);
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

        $provider = FleetProvider::forClient($this->clientId())->findOrFail($id);
        $provider->update($request->except(['client_id']));

        return response()->json(['provider' => $provider->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('fleet.providers.manage');

        FleetProvider::forClient($this->clientId())->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }
}
