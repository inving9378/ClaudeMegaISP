<?php

namespace App\Modules\Addons\WhatsAppAgent\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\WhatsAppAgent\Services\EvolutionApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppSendController extends Controller
{
    /**
     * POST /whatsapp/api/send
     * Envío programático para uso desde otros módulos.
     */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to'            => 'required|string|max:30',
            'body'          => 'required|string|max:4096',
            'instance_slug' => 'nullable|string',
            'context'       => 'nullable|array',
        ]);

        $message = app(EvolutionApiService::class)->sendAndLog(
            to:           $data['to'],
            body:         $data['body'],
            instanceSlug: $data['instance_slug'] ?? null,
            context:      $data['context'] ?? [],
        );

        return response()->json($message, 201);
    }
}
