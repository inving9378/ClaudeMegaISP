<?php

namespace App\Modules\Addons\WhatsAppAgent\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppFunction;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use App\Modules\Addons\WhatsAppAgent\Services\EvolutionApiService;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppFunctionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'instance_id'      => 'nullable|string|max:100',
            'phone_number'     => 'nullable|string',
            'default_instance' => 'sometimes|boolean',
        ]);

        // Autenticación con Evolution INVISIBLE: la apikey y la URL salen SIEMPRE del .env
        // (config/whatsapp), nunca del usuario/request. Las columnas api_url/api_key son
        // NOT NULL: api_url se llena con la global (solo display) y api_key queda vacía —
        // la credencial ya NO se guarda por-instancia; todas las llamadas usan la global.
        $data['api_url']     = (string) config('whatsapp.api_url');
        $data['api_key']     = '';
        $data['instance_id'] = $data['instance_id'] ?? $data['slug']; // etiqueta; a Evolution va el slug

        $instance = WhatsAppInstance::create($data);

        // #9991240 — la línea se guarda aunque Evolution falle (una línea que va a usar Meta no
        // necesita Evolution, y «Conectar / Ver QR» la vuelve a crear on-demand), pero la UI se
        // entera: antes respondía 201 sin más y el problema aparecía después, al pedir el QR.
        $evolution = $this->crearEnEvolution($instance);

        return response()->json(array_merge($instance->toArray(), ['evolution' => $evolution]), 201);
    }

    /** GET /whatsapp/api/evolution/salud — #9991240: ¿Evolution está instalada y acepta la llave? */
    public function evolutionHealth(Request $request): JsonResponse
    {
        return response()->json(app(EvolutionApiService::class)->salud($request->boolean('fresco')));
    }

    /**
     * Crea la instancia en Evolution y traduce el resultado a {ok, mensaje} para la UI.
     * createInstance() solo lanza si no logra conectar; si Evolution contesta con error lo
     * devuelve en el cuerpo (`error`), mismo criterio que ensureInstanceExists().
     */
    private function crearEnEvolution(WhatsAppInstance $instance): array
    {
        $service = app(EvolutionApiService::class);

        try {
            $respuesta = $service->createInstance($instance);
        } catch (\Throwable $e) {
            Log::warning('No se pudo crear instancia en Evolution API', ['error' => $e->getMessage()]);
            $service->salud(true); // refresca el banner de salud con el dato nuevo

            return [
                'ok'      => false,
                'mensaje' => 'La línea se guardó, pero Evolution API no está instalada o no responde en este '
                    . 'servidor, así que todavía no se puede conectar por QR. Cuando Evolution esté '
                    . 'funcionando, «Conectar / Ver QR» la crea sola.',
                'detalle' => $e->getMessage(),
            ];
        }

        if (empty($respuesta['error'])) {
            return ['ok' => true];
        }

        // "already in use": ya existía en Evolution con ese nombre — para nosotros está bien.
        $texto = json_encode($respuesta['response']['message'] ?? $respuesta['error'], JSON_UNESCAPED_UNICODE);
        if (str_contains((string) $texto, 'already in use')) {
            return ['ok' => true];
        }

        Log::warning('Evolution API rechazó crear la instancia', ['instance' => $instance->slug, 'respuesta' => $respuesta]);

        return [
            'ok'      => false,
            'mensaje' => 'La línea se guardó, pero Evolution API rechazó crearla. Revisa que la llave de '
                . 'Evolution del servidor sea la correcta; «Conectar / Ver QR» lo vuelve a intentar.',
            'detalle' => mb_substr((string) $texto, 0, 300),
        ];
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
            // Item: migración a la API oficial de Meta, configurable por línea.
            'provider'         => 'sometimes|in:evolution,meta',
        ]);

        $instance->update($data);

        return response()->json($instance);
    }

    /** DELETE /whatsapp/api/instances/{id} */
    public function destroy(int $id): JsonResponse
    {
        // Ya NO se bloquea por ser dueña única de funciones (decisión de Irving: una función
        // puede quedar sin línea, con aviso en la UI). El FK cascadeOnDelete quita sus
        // asignaciones y esas funciones quedan en 0 líneas.
        WhatsAppInstance::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    /**
     * POST /whatsapp/api/instances/{id}/disconnect — cierra la sesión REAL en Evolution
     * (logout). NO borra la fila local; la línea queda 'disconnected' y se revincula por QR.
     */
    public function disconnect(Request $request, int $id): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('whatsapp_manage_instances'), 403);

        $instance = WhatsAppInstance::findOrFail($id);
        $result   = app(EvolutionApiService::class)->disconnect($instance);
        $instance->update(['status' => 'disconnected']);

        return response()->json(['status' => 'disconnected', 'raw' => $result]);
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

    /** DELETE /whatsapp/api/instances/{id}/functions/{functionId} — quitar. Puede dejar la función en 0 líneas. */
    public function unassignFunction(Request $request, int $id, int $functionId): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('whatsapp_manage_instances'), 403);

        $instance = WhatsAppInstance::findOrFail($id);
        $function = WhatsAppFunction::findOrFail($functionId);

        // Ya NO bloquea la última asignación (la función puede quedar sin línea; el aviso lo da la UI).
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
