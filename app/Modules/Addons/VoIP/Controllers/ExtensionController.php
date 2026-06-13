<?php

namespace App\Modules\Addons\VoIP\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Services\AsteriskProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExtensionController extends Controller
{
    public function __construct(private AsteriskProvisioningService $provisioner) {}

    public function index()
    {
        if (! auth()->user()->can('voip.extensiones.view')) {
            abort(403);
        }
        return view('addon-voip::extensiones.index');
    }

    public function data(): JsonResponse
    {
        if (! auth()->user()->can('voip.extensiones.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $extensiones = Extension::with('agente')->orderBy('numero')->get()->map(fn (Extension $e) => [
            'id'              => $e->id,
            'numero'          => $e->numero,
            'nombre'          => $e->nombre,
            'user_id'         => $e->user_id,
            'agente_nombre'   => $e->agente ? trim("{$e->agente->name} {$e->agente->father_last_name}") : null,
            'tipo_dispositivo'=> $e->tipo_dispositivo,
            'contexto'        => $e->contexto,
            'codecs'          => $e->codecs,
            'transporte'      => $e->transporte,
            'callerid'        => $e->callerid,
            'activo'          => $e->activo,
            'provisionado_at' => $e->provisionado_at?->toDateTimeString(),
            'endpoint_id'     => $e->endpointId(),
            'tiene_secret'    => ! empty($e->attributes['secret'] ?? null),
        ]);

        return response()->json($extensiones);
    }

    public function usuarios(): JsonResponse
    {
        if (! auth()->user()->can('voip.extensiones.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $users = User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'client'))
            ->orderBy('name')
            ->get(['id', 'name', 'father_last_name', 'mother_last_name']);

        return response()->json($users->map(fn ($u) => [
            'id'     => $u->id,
            'nombre' => trim("{$u->name} {$u->father_last_name}"),
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        if (! auth()->user()->can('voip.extensiones.create')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'numero'          => 'required|string|max:20|unique:voip_extensiones,numero',
            'nombre'          => 'required|string|max:100',
            'secret'          => 'required|string|min:4',
            'user_id'         => 'nullable|exists:users,id',
            'tipo_dispositivo'=> 'nullable|in:telefono_ip,softphone',
            'contexto'        => 'nullable|string|max:100',
            'codecs'          => 'nullable|string|max:100',
            'transporte'      => 'nullable|string|max:100',
            'callerid'        => 'nullable|string|max:100',
            'activo'          => 'nullable|boolean',
        ]);

        $ext = Extension::create($data);

        try {
            $this->provisioner->provisionarExtension($ext);
        } catch (\Throwable $e) {
            Log::warning("VoIP: auto-provisión falló para ext {$ext->numero}: {$e->getMessage()}");
        }

        return response()->json(['id' => $ext->id, 'endpoint_id' => $ext->endpointId()], 201);
    }

    public function update(Request $request, Extension $extension): JsonResponse
    {
        if (! auth()->user()->can('voip.extensiones.edit')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'numero'          => "required|string|max:20|unique:voip_extensiones,numero,{$extension->id}",
            'nombre'          => 'required|string|max:100',
            'secret'          => 'nullable|string|min:4',
            'user_id'         => 'nullable|exists:users,id',
            'tipo_dispositivo'=> 'nullable|in:telefono_ip,softphone',
            'contexto'        => 'nullable|string|max:100',
            'codecs'          => 'nullable|string|max:100',
            'transporte'      => 'nullable|string|max:100',
            'callerid'        => 'nullable|string|max:100',
            'activo'          => 'nullable|boolean',
        ]);

        if (empty($data['secret'])) {
            unset($data['secret']);
        }

        $extension->update($data);

        try {
            $this->provisioner->provisionarExtension($extension);
        } catch (\Throwable $e) {
            Log::warning("VoIP: re-provisión falló para ext {$extension->numero}: {$e->getMessage()}");
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Extension $extension): JsonResponse
    {
        if (! auth()->user()->can('voip.extensiones.delete')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if ($extension->estaProvisionada()) {
            try {
                $this->provisioner->desprovisionarExtension($extension);
            } catch (\Throwable $e) {
                Log::warning("VoIP: no se pudo desprovisionar ext {$extension->numero} al eliminar: {$e->getMessage()}");
            }
        }

        $extension->delete();

        return response()->json(['ok' => true]);
    }

    public function provisionar(Extension $extension): JsonResponse
    {
        if (! auth()->user()->can('voip.extensiones.provision')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        try {
            $this->provisioner->provisionarExtension($extension);
            return response()->json([
                'ok'             => true,
                'provisionado_at'=> $extension->fresh()->provisionado_at?->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            Log::error("VoIP: error provisionando ext {$extension->numero}: {$e->getMessage()}");
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function desprovisionar(Extension $extension): JsonResponse
    {
        if (! auth()->user()->can('voip.extensiones.provision')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        try {
            $this->provisioner->desprovisionarExtension($extension);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error("VoIP: error desprovisionando ext {$extension->numero}: {$e->getMessage()}");
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function estados(): JsonResponse
    {
        if (! auth()->user()->can('voip.extensiones.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        try {
            $activos = DB::connection('asterisk_rt')
                ->table('ps_contacts')
                ->where('expiration_time', '>', time())
                ->pluck('endpoint')
                ->map(fn ($ep) => explode('/', $ep)[0])  // normaliza "1001/xxx" → "1001"
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning("VoIP: no se pudo leer ps_contacts para estados — {$e->getMessage()}");
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json(['conectadas' => $activos]);
    }

    public function toggle(Extension $extension): JsonResponse
    {
        if (! auth()->user()->can('voip.extensiones.edit')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $nuevoEstado = ! $extension->activo;
        $extension->update(['activo' => $nuevoEstado]);

        if ($extension->estaProvisionada()) {
            try {
                $nuevoEstado
                    ? $this->provisioner->provisionarExtension($extension)
                    : $this->provisioner->desprovisionarExtension($extension);
            } catch (\Throwable $e) {
                Log::warning("VoIP: toggle-provisión falló para ext {$extension->numero}: {$e->getMessage()}");
                return response()->json(['ok' => true, 'activo' => $nuevoEstado, 'warning' => $e->getMessage()]);
            }
        }

        return response()->json(['ok' => true, 'activo' => $nuevoEstado]);
    }

    public function verificar(Extension $extension): JsonResponse
    {
        if (! auth()->user()->can('voip.extensiones.test')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $host = config('voip.ari_host');
        $port = config('voip.ari_port', 8088);
        $user = config('voip.ari_user');
        $pass = config('voip.ari_pass');
        $epId = $extension->endpointId();

        try {
            $resp = Http::withBasicAuth($user, $pass)
                ->timeout(5)
                ->get("http://{$host}:{$port}/ari/endpoints/PJSIP/{$epId}");

            if ($resp->status() === 404) {
                return response()->json(['ok' => false, 'estado' => 'not_found', 'msg' => 'No existe en Asterisk (sin provisionar)']);
            }

            $body = $resp->json();
            return response()->json([
                'ok'       => $resp->successful(),
                'estado'   => $body['state'] ?? 'unknown',
                'canales'  => count($body['channel_ids'] ?? []),
                'resource' => $body['resource'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
