<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Modules\Addons\Flotas\Models\FleetPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FleetPhotoController extends FleetBaseController
{
    public function store(Request $request): JsonResponse
    {
        $this->authorize('fleet.manage');

        $request->validate([
            'vehicle_id' => 'required|integer',
            'photo_type' => 'nullable|in:front,side,rear,dashboard,general',
            'photo'      => 'required|image|max:5120',
        ]);

        $this->vehicleForClient($request->vehicle_id);

        $path = Storage::disk('local')->putFile(
            "fleet/photos/{$request->vehicle_id}",
            $request->file('photo')
        );

        $photo = FleetPhoto::create([
            'vehicle_id' => $request->vehicle_id,
            'photo_type' => $request->photo_type ?? 'general',
            'file_path'  => $path,
            'taken_at'   => now(),
        ]);

        return response()->json(['photo' => $photo], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('fleet.manage');

        $photo = FleetPhoto::forClient($this->clientId())->findOrFail($id);
        Storage::disk('local')->delete($photo->file_path);
        $photo->delete();

        return response()->json(['ok' => true]);
    }
}
