<?php

namespace App\Modules\Addons\VoIP\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\VoIP\Models\Troncal;
use App\Modules\Addons\VoIP\Services\AsteriskProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TroncalController extends Controller
{
    public function __construct(private AsteriskProvisioningService $provisioner) {}

    // ── Vista ────────────────────────────────────────────────────────────────

    public function index()
    {
        if (! auth()->user()->can('voip.troncales.view')) {
            abort(403);
        }
        return view('addon-voip::troncales.index');
    }

    // ── API JSON ─────────────────────────────────────────────────────────────

    public function data(): JsonResponse
    {
        if (! auth()->user()->can('voip.troncales.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $troncales = Troncal::orderBy('nombre')->get()->map(function (Troncal $t) {
            return [
                'id'             => $t->id,
                'nombre'         => $t->nombre,
                'proveedor'      => $t->proveedor,
                'tipo'           => $t->tipo,
                'direccion'      => $t->direccion,
                'host'           => $t->host,
                'puerto'         => $t->puerto,
                'usuario'        => $t->usuario,
                'contexto'       => $t->contexto,
                'did'            => $t->did,
                'codecs'         => $t->codecs,
                'transporte'     => $t->transporte,
                'activo'         => $t->activo,
                'provisionado_at'=> $t->provisionado_at?->toDateTimeString(),
                'endpoint_id'    => $t->endpointId(),
                'tiene_secret'   => ! empty($t->attributes['secret'] ?? null),
            ];
        });

        return response()->json($troncales);
    }

    public function store(Request $request): JsonResponse
    {
        if (! auth()->user()->can('voip.troncales.create')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'nombre'     => 'required|string|max:100|unique:voip_troncales,nombre',
            'proveedor'  => 'nullable|string|max:100',
            'tipo'       => 'required|in:registro,ip',
            'direccion'  => 'required|in:entrante,saliente,ambas',
            'host'       => 'required|string|max:255',
            'puerto'     => 'nullable|integer|min:1|max:65535',
            'usuario'    => 'nullable|string|max:100',
            'secret'     => 'nullable|string|min:4',
            'contexto'   => 'nullable|string|max:100',
            'did'        => 'nullable|string|max:50',
            'codecs'     => 'nullable|string|max:100',
            'transporte' => 'nullable|string|max:100',
            'activo'     => 'nullable|boolean',
        ]);

        $troncal = Troncal::create($data);

        return response()->json($troncal->only([
            'id', 'nombre', 'proveedor', 'tipo', 'direccion',
            'host', 'puerto', 'usuario', 'contexto', 'did',
            'codecs', 'transporte', 'activo', 'provisionado_at',
        ]), 201);
    }

    public function update(Request $request, Troncal $troncal): JsonResponse
    {
        if (! auth()->user()->can('voip.troncales.edit')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'nombre'     => "required|string|max:100|unique:voip_troncales,nombre,{$troncal->id}",
            'proveedor'  => 'nullable|string|max:100',
            'tipo'       => 'required|in:registro,ip',
            'direccion'  => 'required|in:entrante,saliente,ambas',
            'host'       => 'required|string|max:255',
            'puerto'     => 'nullable|integer|min:1|max:65535',
            'usuario'    => 'nullable|string|max:100',
            'secret'     => 'nullable|string|min:4',
            'contexto'   => 'nullable|string|max:100',
            'did'        => 'nullable|string|max:50',
            'codecs'     => 'nullable|string|max:100',
            'transporte' => 'nullable|string|max:100',
            'activo'     => 'nullable|boolean',
        ]);

        // No sobreescribir el secret cifrado si llega vacío
        if (empty($data['secret'])) {
            unset($data['secret']);
        }

        $troncal->update($data);

        // Si el tipo cambió, marcar como no provisionada
        if (isset($data['tipo']) && $data['tipo'] !== $troncal->getOriginal('tipo')) {
            $troncal->update(['provisionado_at' => null]);
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Troncal $troncal): JsonResponse
    {
        if (! auth()->user()->can('voip.troncales.delete')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if ($troncal->estiProvisionada()) {
            try {
                $this->provisioner->desprovisionar($troncal);
            } catch (\Throwable $e) {
                Log::warning("VoIP: no se pudo desprovisionar {$troncal->nombre} al eliminar: {$e->getMessage()}");
            }
        }

        $troncal->delete();

        return response()->json(['ok' => true]);
    }

    // ── Provisión ────────────────────────────────────────────────────────────

    public function provisionar(Troncal $troncal): JsonResponse
    {
        if (! auth()->user()->can('voip.troncales.provision')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        try {
            $this->provisioner->provisionar($troncal);
            return response()->json([
                'ok'             => true,
                'provisionado_at'=> $troncal->fresh()->provisionado_at?->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            Log::error("VoIP: error provisionando {$troncal->nombre}: {$e->getMessage()}");
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function desprovisionar(Troncal $troncal): JsonResponse
    {
        if (! auth()->user()->can('voip.troncales.provision')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        try {
            $this->provisioner->desprovisionar($troncal);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error("VoIP: error desprovisionando {$troncal->nombre}: {$e->getMessage()}");
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ── Tests de conectividad ─────────────────────────────────────────────────

    public function probarConexion(): JsonResponse
    {
        if (! auth()->user()->can('voip.troncales.test')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $host  = config('voip.ari_host');
        $port  = config('voip.ari_port', 8088);
        $user  = config('voip.ari_user');
        $pass  = config('voip.ari_pass');

        try {
            $resp = Http::withBasicAuth($user, $pass)
                ->timeout(5)
                ->get("http://{$host}:{$port}/ari/asterisk/info");

            return response()->json([
                'ok'      => $resp->successful(),
                'status'  => $resp->status(),
                'version' => $resp->json('system.asterisk_version') ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function verificarTroncal(Troncal $troncal): JsonResponse
    {
        if (! auth()->user()->can('voip.troncales.test')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $host = config('voip.ari_host');
        $port = config('voip.ari_port', 8088);
        $user = config('voip.ari_user');
        $pass = config('voip.ari_pass');
        $epId = $troncal->endpointId();

        try {
            $resp = Http::withBasicAuth($user, $pass)
                ->timeout(5)
                ->get("http://{$host}:{$port}/ari/endpoints/PJSIP/{$epId}");

            if ($resp->status() === 404) {
                return response()->json([
                    'ok'     => false,
                    'estado' => 'not_found',
                    'msg'    => 'El endpoint no existe en Asterisk (¿sin provisionar?)',
                ]);
            }

            $body = $resp->json();
            return response()->json([
                'ok'         => $resp->successful(),
                'estado'     => $body['state'] ?? 'unknown',
                'canales'    => count($body['channel_ids'] ?? []),
                'resource'   => $body['resource'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
