<?php

namespace App\Modules\Addons\VoIP\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Models\RangoNumeracion;
use App\Modules\Addons\VoIP\Services\AsteriskProvisioningService;
use App\Modules\Addons\VoIP\Services\ReclamadorExtensionAutomatico;
use App\Modules\Addons\VoIP\Services\SegmentosTelefono;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExtensionController extends Controller
{
    public function __construct(
        private AsteriskProvisioningService $provisioner,
    ) {}

    /**
     * Segmentos (17-nov-2026, ver SegmentosTelefono): 1000–1999 aparatos físicos,
     * 2000–2999 teléfonos web. Ya no hay gemelas: guardar o borrar una extensión no crea
     * ni toca ninguna otra. Un número que ya tenía y no cambia se acepta tal cual (las
     * gemelas viejas `webNNNN` se pueden editar hasta que «Sincronizar» las pase).
     */
    private function reglaSegmento(bool $esWeb, ?string $actual = null): \Closure
    {
        return function (string $atributo, $valor, \Closure $falla) use ($esWeb, $actual) {
            $n = trim((string) $valor);
            if ($actual !== null && $n === $actual) {
                return;
            }
            if ($esWeb && ! SegmentosTelefono::esNumeroWeb($n)) {
                $falla('Un teléfono web va del 2000 al 2999 (misma área que el físico: 2100 atención, 2200 técnicos…).');
            } elseif (! $esWeb && SegmentosTelefono::esNumeroWeb($n)) {
                $falla('Del 2000 al 2999 son teléfonos web. Un aparato físico va del 1000 al 1999.');
            } elseif (SegmentosTelefono::baseDeGemela($n) !== null) {
                $falla('Los números con «web» ya no se usan: el teléfono web lleva su propio número del 2000 al 2999.');
            } elseif (($otro = $this->parecidoQueExiste($n)) !== null) {
                // Aparato y navegador son dos lugares distintos: que nadie marque uno creyendo
                // que timbra el otro (1003 ↔ 2003).
                $falla($esWeb
                    ? "La {$otro->numero} es un aparato: el teléfono web lleva un número que no se le parezca, para que no se confundan."
                      . (($libre = SegmentosTelefono::disponible(SegmentosTelefono::rangoDe($n))) ? " Disponible: {$libre}." : '')
                    : "La {$otro->numero} es el teléfono web de {$this->dueno($otro)}: el aparato lleva un número que no se le parezca, para que no se confundan.");
            }
        };
    }

    private function parecidoQueExiste(string $numero): ?Extension
    {
        $parecido = SegmentosTelefono::parecido($numero);

        return $parecido !== null ? Extension::with('agente')->where('numero', $parecido)->first() : null;
    }

    private function dueno(Extension $e): string
    {
        return $e->agente ? trim("{$e->agente->name} {$e->agente->father_last_name}") : $e->nombre;
    }

    /** Un teléfono web por persona. */
    private function reglaUnaPorPersona(?int $ignorarId = null): \Closure
    {
        return function (string $atributo, $valor, \Closure $falla) use ($ignorarId) {
            $otra = Extension::where('user_id', $valor)->where('es_webrtc', true)
                ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
                ->value('numero');
            if ($otra !== null) {
                $falla("Esa persona ya tiene su teléfono web ({$otra}): es uno por persona.");
            }
        };
    }

    public function index()
    {
        if (! auth()->user()?->can('voip.extensiones.view')) {
            abort(403);
        }
        return view('addon-voip::extensiones.index');
    }

    public function data(): JsonResponse
    {
        if (! auth()->user()?->can('voip.extensiones.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Si de verdad existe en Asterisk (no solo lo que recuerda provisionado_at).
        try {
            $enAsterisk = DB::connection('asterisk_rt')->table('ps_endpoints')->pluck('id')->flip();
        } catch (\Throwable $e) {
            $enAsterisk = null;
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
            'es_webrtc'       => (bool) $e->es_webrtc,
            'numero_anterior' => $e->numero_anterior,
            'tiene_secret'    => ! empty($e->attributes['secret'] ?? null),
            'en_asterisk'     => $enAsterisk === null ? null : isset($enAsterisk[$e->endpointId()]),
        ]);

        return response()->json($extensiones);
    }

    public function usuarios(): JsonResponse
    {
        if (! auth()->user()?->can('voip.extensiones.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // "whereHas any role AND whereDoesntHave client" omite colaboradores que
        // también tienen el rol client asignado (datos mixtos en producción).
        // La lista explícita es la única que captura correctamente al personal completo.
        // Al agregar un rol de staff nuevo: actualizar esta lista.
        $rolesStaff = [
            'Administrador', 'ADMINISTRADOR_COMPLETO', 'Almacen', 'conductor', 'CONTADOR',
            'DESARROLLADOR', 'Mostrador', 'PUBLICADOR', 'Socio', 'Super Administrador',
            'super-administrator', 'SUPERVISOR_MOSTRADOR', 'TECNICO', 'TECNICO_INSTALADOR',
            'TECNICO_PLANTA', 'Vendedor',
        ];

        $users = User::whereHas('roles', fn ($q) => $q->whereIn('name', $rolesStaff))
            ->where('estado', 'activo')
            ->orderBy('name')
            ->get(['id', 'name', 'father_last_name', 'mother_last_name']);

        return response()->json($users->map(fn ($u) => [
            'id'     => $u->id,
            'nombre' => trim("{$u->name} {$u->father_last_name}"),
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        if (! auth()->user()?->can('voip.extensiones.create')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $esWeb = $request->boolean('es_webrtc');
        $data = $request->validate([
            // Teléfono web sin número: se le da el primero disponible del área de la persona.
            'numero'          => [$esWeb ? 'nullable' : 'required', 'string', 'max:20', 'unique:voip_extensiones,numero', $this->reglaSegmento($esWeb)],
            'nombre'          => 'required|string|max:100',
            // El teléfono web no se configura a mano: el navegador la recibe sola.
            'secret'          => $esWeb ? 'nullable|string|min:4' : 'required|string|min:4',
            'user_id'         => $esWeb ? ['required', 'exists:users,id', $this->reglaUnaPorPersona()] : 'nullable|exists:users,id',
            'tipo_dispositivo'=> 'nullable|in:telefono_ip,softphone',
            'contexto'        => 'nullable|string|max:100',
            'codecs'          => 'nullable|string|max:100',
            'transporte'      => ['nullable', 'string', 'max:100', $this->reglaTransporte()],
            'callerid'        => 'nullable|string|max:100',
            'activo'          => 'nullable|boolean',
        ], [
            'user_id.required' => 'Un teléfono web es de una persona: elige el agente.',
        ]);

        if ($esWeb) {
            if (trim((string) ($data['numero'] ?? '')) === '') {
                $data['numero'] = $this->numeroWebDisponible((int) $data['user_id']);
                if ($data['numero'] === null) {
                    return response()->json(['errors' => ['numero' => ['No hay números libres del 2000 al 2999.']]], 422);
                }
            }
            $data = SegmentosTelefono::DATOS_WEB + $data;
            $data['secret']   = ($data['secret'] ?? '') !== '' ? $data['secret'] : Str::random(32);
            $data['callerid'] = ($data['callerid'] ?? '') !== '' ? $data['callerid'] : $data['numero'];
        } else {
            $data['es_webrtc'] = false;
        }
        $data['voip_rango_numeracion_id'] = SegmentosTelefono::rangoDe($data['numero'])?->id;

        $ext = Extension::create($data);

        try {
            $this->provisioner->provisionarExtension($ext);
        } catch (\Throwable $e) {
            Log::warning("VoIP: auto-provisión falló para ext {$ext->numero}: {$e->getMessage()}");
        }

        return response()->json(['id' => $ext->id, 'numero' => $ext->numero, 'endpoint_id' => $ext->endpointId()], 201);
    }

    /** Primer número web disponible en el área de la persona (por su rol; si no, oficinas). */
    private function numeroWebDisponible(int $userId): ?string
    {
        $user = User::find($userId);
        $area = $user ? ReclamadorExtensionAutomatico::areaDe($user) : null;

        return SegmentosTelefono::disponible(RangoNumeracion::porCodigo($area ?? 'oficinas')->first());
    }

    /** transport-externo solo existe si el servidor lo tiene configurado (ASTERISK_BIND_EXTERNO). */
    private function reglaTransporte(): \Closure
    {
        return function (string $atributo, $valor, \Closure $falla) {
            if ($valor === AsteriskProvisioningService::TRANSPORTE_EXTERNO
                && trim((string) config('voip.asterisk.bind_externo', '')) === '') {
                $falla('Este servidor no tiene configurado el transporte para teléfonos de fuera (ASTERISK_BIND_EXTERNO).');
            }
        };
    }

    public function update(Request $request, Extension $extension): JsonResponse
    {
        if (! auth()->user()?->can('voip.extensiones.edit')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $esWeb = (bool) $extension->es_webrtc;
        $data = $request->validate([
            'numero'          => ['required', 'string', 'max:20', "unique:voip_extensiones,numero,{$extension->id}",
                                  $this->reglaSegmento($esWeb, $extension->numero)],
            'nombre'          => 'required|string|max:100',
            'secret'          => 'nullable|string|min:4',
            'user_id'         => $esWeb ? ['required', 'exists:users,id', $this->reglaUnaPorPersona($extension->id)] : 'nullable|exists:users,id',
            'tipo_dispositivo'=> 'nullable|in:telefono_ip,softphone',
            'contexto'        => 'nullable|string|max:100',
            'codecs'          => 'nullable|string|max:100',
            'transporte'      => ['nullable', 'string', 'max:100', $this->reglaTransporte()],
            'callerid'        => 'nullable|string|max:100',
            'activo'          => 'nullable|boolean',
        ]);

        if (empty($data['secret'])) {
            unset($data['secret']);
        }
        if ($esWeb) {
            $data = array_merge($data, SegmentosTelefono::DATOS_WEB);
        }

        // El endpoint de Asterisk se llama como el número: si cambia, el viejo se quita
        // (antes se quedaba vivo en Asterisk con la contraseña de siempre).
        $anterior = $extension->numero;
        if (trim($data['numero']) !== $anterior) {
            try {
                $this->provisioner->desprovisionarExtension($extension);
            } catch (\Throwable $e) {
                Log::warning("VoIP: al cambiar {$anterior} → {$data['numero']} no se pudo quitar el número viejo de Asterisk: {$e->getMessage()}");
            }
            if (($data['callerid'] ?? '') === $anterior) {
                $data['callerid'] = trim($data['numero']);
            }
            $data['voip_rango_numeracion_id'] = SegmentosTelefono::rangoDe($data['numero'])?->id ?? $extension->voip_rango_numeracion_id;
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
        if (! auth()->user()?->can('voip.extensiones.delete')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Solo ella: desde el 17-nov-2026 los teléfonos web ya no van ligados a un físico.
        $aBorrar = [$extension];

        $grupos = \App\Modules\Addons\VoIP\Models\GrupoTimbrado::whereHas('extensiones',
            fn ($q) => $q->whereIn('voip_extensiones.id', collect($aBorrar)->pluck('id'))
        )->get();

        foreach ($aBorrar as $ext) {
            // Siempre, no solo si provisionado_at dice que está: si quedó desalineado,
            // borrar de Asterisk lo que no existe no hace daño.
            try {
                $this->provisioner->desprovisionarExtension($ext);
            } catch (\Throwable $e) {
                Log::warning("VoIP: no se pudo desprovisionar ext {$ext->numero} al eliminar: {$e->getMessage()}");
            }
            $ext->delete();   // la liga con grupos se borra en cascada
        }

        // Que la cola y el plan de marcado dejen de llamarla.
        if ($grupos->isNotEmpty()) {
            try {
                foreach ($grupos as $g) {
                    if ($g->es_cola) {
                        $this->provisioner->provisionarCola($g->fresh('extensiones'));
                    }
                }
                app(\App\Modules\Addons\VoIP\Services\DialplanGeneratorService::class)->regenerar();
            } catch (\Throwable $e) {
                Log::warning("VoIP: al eliminar ext {$extension->numero} no se pudo actualizar la cola o el plan: {$e->getMessage()}");
            }
        }

        return response()->json(['ok' => true, 'borradas' => collect($aBorrar)->pluck('numero')]);
    }

    public function provisionar(Extension $extension): JsonResponse
    {
        if (! auth()->user()?->can('voip.extensiones.provision')) {
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
        if (! auth()->user()?->can('voip.extensiones.provision')) {
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
        if (! auth()->user()?->can('voip.extensiones.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        try {
            [$conectadas, $equipos] = Cache::remember('voip_ext_estados', 10, function () {
                [$conectadas, $agentes] = $this->provisioner->conexiones();

                return [$conectadas, array_map(fn ($ua) => self::clasificarEquipo($ua), $agentes)];
            });
        } catch (\Throwable $e) {
            Log::warning("VoIP: no se pudo leer ps_contacts para estados — {$e->getMessage()}");
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json(['conectadas' => $conectadas, 'equipos' => $equipos]);
    }

    private static function clasificarEquipo(?string $ua): array
    {
        if (empty($ua)) {
            return ['tipo' => 'desconocido', 'modelo' => null, 'user_agent' => $ua];
        }

        // Zoiper 5/3: reporta "Z 5.6.6 v2.10.20.5" — la palabra "Zoiper" no aparece en el UA
        if (preg_match('/^Z\s+(\d[\d.]+)/i', $ua, $m)) {
            return ['tipo' => 'softphone', 'modelo' => "Zoiper {$m[1]}", 'user_agent' => $ua];
        }

        $uaLow = strtolower($ua);

        $fisicos = [
            'grandstream', 'yealink', 'cisco', 'polycom', 'snom', 'fanvil',
            'htek', 'akuvox', 'gigaset', 'vtech', 'escene', 'panasonic', 'aastra',
        ];

        foreach ($fisicos as $kw) {
            if (str_contains($uaLow, $kw)) {
                $partes = preg_split('/\s+/', trim($ua), 3);
                $modelo = isset($partes[1]) ? "{$partes[0]} {$partes[1]}" : $partes[0];
                return ['tipo' => 'fisico', 'modelo' => $modelo, 'user_agent' => $ua];
            }
        }

        $softphones = [
            'zoiper'      => 'Zoiper',
            'microsip'    => 'MicroSIP',
            'linphone'    => 'Linphone',
            'bria'        => 'Bria',
            'counterpath' => 'CounterPath',
            'x-lite'      => 'X-Lite',
            'groundwire'  => 'Groundwire',
            'acrobits'    => 'Acrobits',
            'phonerlite'  => 'PhonerLite',
            '3cxphone'    => '3CX Phone',
            '3cx'         => '3CX',
            'twinkle'     => 'Twinkle',
            'jitsi'       => 'Jitsi',
            'pjsua'       => 'PJSUA',
            'telephone'   => 'Telephone (macOS)',
        ];

        foreach ($softphones as $kw => $label) {
            if (str_contains($uaLow, $kw)) {
                return ['tipo' => 'softphone', 'modelo' => $label, 'user_agent' => $ua];
            }
        }

        return ['tipo' => 'desconocido', 'modelo' => null, 'user_agent' => $ua];
    }

    public function toggle(Extension $extension): JsonResponse
    {
        if (! auth()->user()?->can('voip.extensiones.edit')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $nuevoEstado = ! $extension->activo;
        $extension->update(['activo' => $nuevoEstado]);

        // Al desactivar se quita de Asterisk (y provisionado_at queda vacío), así que al
        // reactivar hay que darla de alta siempre: antes solo se hacía si seguía marcada
        // como provisionada, y una extensión reactivada se quedaba fuera de Asterisk.
        try {
            if ($nuevoEstado) {
                $this->provisioner->provisionarExtension($extension);
            } elseif ($extension->estaProvisionada()) {
                $this->provisioner->desprovisionarExtension($extension);
            }
        } catch (\Throwable $e) {
            Log::warning("VoIP: toggle-provisión falló para ext {$extension->numero}: {$e->getMessage()}");
            return response()->json(['ok' => true, 'activo' => $nuevoEstado, 'warning' => $e->getMessage()]);
        }

        return response()->json(['ok' => true, 'activo' => $nuevoEstado]);
    }

    public function verificar(Extension $extension): JsonResponse
    {
        if (! auth()->user()?->can('voip.extensiones.test')) {
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
