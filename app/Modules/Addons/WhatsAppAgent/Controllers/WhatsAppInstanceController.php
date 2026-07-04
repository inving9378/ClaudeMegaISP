<?php

namespace App\Modules\Addons\WhatsAppAgent\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppFunction;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use App\Modules\Addons\WhatsAppAgent\Services\EvolutionApiService;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppFunctionService;
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
        // Eager-load de las funciones asignadas (Fase 3) para pintar los checks por línea.
        return response()->json(
            WhatsAppInstance::with('functions:id,name,slug,exclusive')->orderBy('id')->get()
        );
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

        $update = ['status' => $mapped];

        // Persistir el número REAL (ownerJid de Evolution) UNA sola vez si aún no lo
        // tenemos, para no depender de Evolution en cada render (ni golpearlo en cada
        // poll del QR). Best-effort: un fallo no rompe el sync.
        if (blank($instance->phone_number)) {
            try {
                $profile = app(EvolutionApiService::class)->getInstanceProfile($instance);
                if (! empty($profile['number'])) {
                    $update['phone_number'] = $profile['number'];
                }
            } catch (\Throwable $e) {
                // best-effort — se reintenta en el siguiente sync
            }
        }

        $instance->update($update);

        return response()->json(['status' => $mapped, 'phone_number' => $instance->phone_number, 'raw' => $status]);
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
        // El observer WhatsAppInstance::deleting corre guardInstanceRemoval → si la línea
        // es dueña única de alguna función, lanza WhatsAppFunctionException (render 422)
        // y el borrado se aborta. Aquí no hay que hacer nada extra.
        WhatsAppInstance::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ── Funciones por línea (Fase 3) — gate whatsapp_manage_instances ────────────

    /** GET /whatsapp/api/instances/functions-catalog — funciones activas para los checks. */
    public function functionsCatalog(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('whatsapp_manage_instances'), 403);

        return response()->json(
            WhatsAppFunction::active()->orderBy('position')->orderBy('name')
                ->get(['id', 'name', 'slug', 'exclusive', 'color'])
        );
    }

    /** POST /whatsapp/api/instances/{id}/functions — asignar (body: function_id). */
    public function assignFunction(Request $request, int $id): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('whatsapp_manage_instances'), 403);
        $data = $request->validate(['function_id' => 'required|integer|exists:whatsapp_functions,id']);

        $instance = WhatsAppInstance::findOrFail($id);
        $function = WhatsAppFunction::findOrFail($data['function_id']);

        $result = app(WhatsAppFunctionService::class)->assign($instance, $function, auth()->id());

        // Nombre de la línea de la que se movió (para el mensaje de la UI).
        $fromName = null;
        if (! empty($result['from'])) {
            $fromName = WhatsAppInstance::find($result['from'])?->name;
        }

        return response()->json(array_merge($result, ['from_name' => $fromName]));
    }

    /** DELETE /whatsapp/api/instances/{id}/functions/{functionId} — quitar. 422 si huérfana. */
    public function unassignFunction(Request $request, int $id, int $functionId): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('whatsapp_manage_instances'), 403);

        $instance = WhatsAppInstance::findOrFail($id);
        $function = WhatsAppFunction::findOrFail($functionId);

        // Si es la única línea → WhatsAppFunctionService::unassign lanza la excepción (422).
        app(WhatsAppFunctionService::class)->unassign($instance, $function);

        return response()->json(['success' => true]);
    }

    /** POST /whatsapp/api/instances/{id}/functions/{functionId}/reassign (body: to_instance_id). */
    public function reassignFunction(Request $request, int $id, int $functionId): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('whatsapp_manage_instances'), 403);
        $data = $request->validate(['to_instance_id' => 'required|integer|exists:whatsapp_instances,id']);

        $function = WhatsAppFunction::findOrFail($functionId);
        $from     = WhatsAppInstance::findOrFail($id);
        $to       = WhatsAppInstance::findOrFail($data['to_instance_id']);

        app(WhatsAppFunctionService::class)->reassign($function, $from, $to, auth()->id());

        return response()->json(['success' => true]);
    }
}
