<?php

namespace App\Modules\Addons\CobranzaBlaster\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\CobranzaBlaster\Jobs\ProcessCallResultJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CobranzaWebhookController extends Controller
{
    public function amiEvent(Request $request): JsonResponse
    {
        // Solo aceptar desde localhost
        if (!in_array($request->ip(), ['127.0.0.1', '::1'])) {
            abort(403);
        }

        $evento   = $request->input('Event');
        $uniqueid = $request->input('Uniqueid');

        if (!$evento || !$uniqueid) {
            return response()->json(['error' => 'Faltan Event o Uniqueid'], 422);
        }

        $eventosRelevantes = ['ANSWER', 'BUSY', 'NOANSWER', 'FAILED', 'DTMF', 'TRANSFER', 'HANGUP'];

        if (!in_array(strtoupper($evento), $eventosRelevantes)) {
            return response()->json(['ok' => 'ignorado']);
        }

        ProcessCallResultJob::dispatch(
            strtoupper($evento),
            $uniqueid,
            $request->all()
        );

        return response()->json(['ok' => true]);
    }
}
