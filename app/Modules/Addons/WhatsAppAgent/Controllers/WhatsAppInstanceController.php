<?php

namespace App\Modules\Addons\WhatsAppAgent\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use App\Modules\Addons\WhatsAppAgent\Services\EvolutionApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class WhatsAppInstanceController extends Controller
{
    /** GET /whatsapp/instances — vista */
    public function panel()
    {
        return view('addon-whatsapp-agent::instances', [
            'fakeMode' => (bool) config('whatsapp.fake', false),
        ]);
    }

    /** GET /whatsapp/api/instances */
    public function index(): JsonResponse
    {
        return response()->json(WhatsAppInstance::orderBy('id')->get());
    }

    /** POST /whatsapp/api/instances */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'             => 'required|string|max:100',
            'slug'             => 'required|string|unique:whatsapp_instances,slug|alpha_dash',
            'instance_id'      => 'required|string|max:100',
            'api_url'          => 'required|url',
            'api_key'          => 'required|string',
            'phone_number'     => 'nullable|string',
            'default_instance' => 'sometimes|boolean',
        ]);

        $data['api_key'] = Crypt::encryptString($data['api_key']);
        $instance        = WhatsAppInstance::create($data);

        try {
            app(EvolutionApiService::class)->createInstance($instance);
        } catch (\Throwable $e) {
            Log::warning('No se pudo crear instancia en Evolution API', ['error' => $e->getMessage()]);
        }

        return response()->json($instance, 201);
    }

    /** GET /whatsapp/api/instances/{id}/qr */
    public function getQr(int $id): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($id);
        $qrData   = app(EvolutionApiService::class)->getQrCode($instance);

        $qrImage = $qrData['qrcode']
            ?? $qrData['base64']
            ?? $qrData['code']
            ?? null;

        if ($qrImage) {
            $instance->update([
                'qr_code'       => $qrImage,
                'qr_expires_at' => now()->addMinutes(2),
                'status'        => 'qr_pending',
            ]);
        }

        return response()->json($qrData);
    }

    /** GET /whatsapp/api/instances/{id}/status */
    public function connectionStatus(int $id): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($id);
        $status   = app(EvolutionApiService::class)->getConnectionStatus($instance);

        // Evolution v2 anida el state: {"instance":{"state":"open"}}
        // El fake mode devuelve top-level: {"state":"open"}
        $state = $status['instance']['state'] ?? $status['state'] ?? null;
        $mapped = $state === 'open' ? 'connected' : 'disconnected';
        $instance->update(['status' => $mapped]);

        return response()->json(['status' => $mapped, 'raw' => $status]);
    }

    /** PATCH /whatsapp/api/instances/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($id);

        $data = $request->validate([
            'name'             => 'sometimes|string|max:100',
            'phone_number'     => 'nullable|string',
            'default_instance' => 'sometimes|boolean',
            'active'           => 'sometimes|boolean',
        ]);

        $instance->update($data);

        return response()->json($instance);
    }

    /** DELETE /whatsapp/api/instances/{id} */
    public function destroy(int $id): JsonResponse
    {
        WhatsAppInstance::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}
