<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Modules\Addons\Flotas\Models\FleetDriverPushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Item #101 (Fase 5.5) — registro del token de push del dispositivo del conductor.
// Self-scoped por auth()->id() (nunca por un user_id enviado por el cliente) — sin IDOR.
// El envío real vía FCM queda pendiente del item #72 (Firebase greenfield).
class FleetPushTokenController extends FleetBaseController
{
    // POST /flotas/api/push-tokens — registra o actualiza el token del dispositivo propio
    public function store(Request $request): JsonResponse
    {
        $this->authorize('fleet.push.manage');

        $data = $request->validate([
            'token'    => 'required|string|max:500',
            'platform' => 'sometimes|in:android,ios',
        ]);

        $push = FleetDriverPushToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id'      => auth()->id(),
                'platform'     => $data['platform'] ?? 'android',
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['ok' => true, 'id' => $push->id]);
    }

    // DELETE /flotas/api/push-tokens/{id} — elimina un token del dispositivo propio (ej. logout)
    public function destroy(int $id): JsonResponse
    {
        $this->authorize('fleet.push.manage');

        FleetDriverPushToken::where('user_id', auth()->id())->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }
}
