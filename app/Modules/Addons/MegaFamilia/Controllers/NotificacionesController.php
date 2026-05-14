<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificacionesController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::notificaciones.index');
    }

    public function history(): JsonResponse
    {
        $rows = ParentalEvent::where('action', 'broadcast_notification')
            ->orderByDesc('id')
            ->paginate(20);
        return response()->json($rows);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string',
            'message' => 'required|string',
            'segment' => 'required|in:all,plan,profile_type',
            'target' => 'nullable|string',
        ]);

        // FCM aún no integrado — sólo registramos el broadcast como evento.
        $event = ParentalEvent::create([
            'account_id' => 1, // placeholder hasta cablear cuentas
            'action' => 'broadcast_notification',
            'detail' => json_encode($data),
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true, 'event' => $event, 'note' => 'FCM dispatch pendiente.']);
    }
}
