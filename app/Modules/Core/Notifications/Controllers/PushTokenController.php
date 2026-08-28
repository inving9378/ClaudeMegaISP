<?php

namespace App\Modules\Core\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Notifications\Models\PushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Registro transversal de push tokens FCM (item #627). SOLO registro/baja —
 * el envío real (credenciales FCM, sender) es infra aparte, fuera de alcance
 * a propósito (ver comentarios_claude del item).
 *
 * user_id SIEMPRE sale de Auth::id() (sanctum), NUNCA del payload — anti-IDOR.
 */
class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'     => 'required|string|max:255',
            'platform'  => 'nullable|string|in:android,ios,web',
            'device_id' => 'nullable|string|max:255',
        ]);

        $pushToken = PushToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id'     => Auth::id(),
                'platform'    => $data['platform'] ?? null,
                'device_id'   => $data['device_id'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'id'      => $pushToken->id,
        ]);
    }

    public function destroy(string $token): JsonResponse
    {
        PushToken::where('token', $token)
            ->where('user_id', Auth::id())
            ->delete();

        return response()->json(['success' => true]);
    }
}
