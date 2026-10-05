<?php

namespace App\Modules\Addons\Hub\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Core\ApiIntegration;
use App\Models\Core\ApiIntegrationProvider;
use App\Modules\Addons\IA\Models\IAAsignacion;
use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use App\Services\Core\ApiIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * Pestaña "Módulos IA" del Integration Hub: qué integración de IA (llave del
 * Hub) y qué modelo usa cada módulo. Sin asignación el módulo no usa IA.
 */
class IaModulosController extends Controller
{
    /** Sugerencias de modelo por protocolo (el campo admite cualquier otro). */
    private const MODELOS_SUGERIDOS = [
        'claude'            => ['claude-sonnet-4-6', 'claude-opus-4-7', 'claude-haiku-4-5-20251001'],
        'openai'            => ['gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini', 'gpt-4.1'],
        'gemini'            => ['gemini-2.5-flash', 'gemini-2.5-pro'],
        'openai_compatible' => [],
    ];

    private const ETIQUETAS_REQUIERE = [
        'imagenes'     => 'Imágenes',
        'pdf'          => 'PDF',
        'herramientas' => 'Herramientas',
        'voz'          => 'Voz',
    ];

    public function __construct()
    {
        $this->middleware('permission:view-integrations')->only(['index']);
        $this->middleware('permission:manage-integrations')->only(['update']);
        $this->middleware('permission:validate-integrations')->only(['probar']);
    }

    public function index(): JsonResponse
    {
        $asignaciones = IAAsignacion::all()->keyBy('clave');

        $modulos = collect(config('ia_modulos', []))->map(function ($m, $clave) use ($asignaciones) {
            $a = $asignaciones->get($clave);
            return [
                'clave'              => $clave,
                'nombre'             => $m['nombre'] ?? $clave,
                'grupo'              => $m['grupo'] ?? 'Otros',
                'requiere'           => array_values(array_map(
                    fn($r) => self::ETIQUETAS_REQUIERE[$r] ?? $r, (array) ($m['requiere'] ?? [])
                )),
                'listo'              => (bool) ($m['listo'] ?? false),
                'modelos'            => $m['modelos'] ?? null,
                'api_integration_id' => $a?->api_integration_id,
                'modelo'             => $a?->modelo,
            ];
        })->values();

        $catalogo = ApiIntegrationProvider::where('type', 'ia')->get()->keyBy('slug');

        $hub = ApiIntegrationService::instance();
        $integraciones = ApiIntegration::forCompany(1)->where('type', 'ia')->orderBy('name')->get()
            ->map(function (ApiIntegration $i) use ($catalogo, $hub) {
                $p = $catalogo->get($i->provider);
                return [
                    'id'                => $i->id,
                    'nombre'            => $i->name,
                    'proveedor'         => $p?->name ?? $i->provider,
                    'driver'            => $p?->driver,
                    'activa'            => (bool) $i->active,
                    'tiene_llave'       => (bool) $i->encrypted_value,
                    'requiere_llave'    => $p?->driver !== 'openai_compatible',
                    'estado_validacion' => $i->last_validation_status,
                    'mensaje_validacion'=> $i->last_validation_status ? $hub->ultimoMensajeValidacion($i) : null,
                    'modelos'           => self::MODELOS_SUGERIDOS[$p?->driver] ?? [],
                ];
            })->values();

        return response()->json(['data' => ['modulos' => $modulos, 'integraciones' => $integraciones]]);
    }

    public function update(Request $request, string $clave): JsonResponse
    {
        $modulo = IA::modulo($clave);
        if (!$modulo) {
            return response()->json(['error' => 'Módulo desconocido'], 404);
        }

        $v = Validator::make($request->all(), [
            'api_integration_id' => 'nullable|integer|exists:api_integrations,id',
            'modelo'             => 'required_with:api_integration_id|nullable|string|max:100',
        ], [
            'modelo.required_with' => 'Indica el modelo a usar',
        ]);
        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first()], 422);
        }

        // Quitar la asignación = el módulo deja de usar IA
        if (!$request->filled('api_integration_id')) {
            IAAsignacion::where('clave', $clave)->delete();
            return response()->json(['message' => 'Asignación quitada', 'data' => ['configurada' => false]]);
        }

        $integracion = ApiIntegration::forCompany(1)->findOrFail($request->api_integration_id);
        if ($integracion->type !== 'ia') {
            return response()->json(['error' => 'Esa integración no es de IA'], 422);
        }

        $catalogo = ApiIntegrationProvider::where('slug', $integracion->provider)->first();
        if ($problema = IA::problemaDeIntegracion($integracion, $catalogo, $modulo)) {
            return response()->json(['error' => $problema], 422);
        }

        IAAsignacion::updateOrCreate(
            ['clave' => $clave],
            ['api_integration_id' => $integracion->id, 'modelo' => trim($request->modelo)]
        );

        return response()->json(['message' => 'Guardado', 'data' => ['configurada' => IA::configurada($clave)]]);
    }

    /**
     * Prueba en vivo del módulo con SU integración y SU modelo: un mensaje
     * mínimo (o, en los de voz, que la llave vea el modelo de audio). Sirve
     * justo después de cambiar una llave o de asignar otra IA.
     */
    public function probar(string $clave): JsonResponse
    {
        $modulo = IA::modulo($clave);
        if (!$modulo) {
            return response()->json(['error' => 'Módulo desconocido'], 404);
        }

        $inicio = microtime(true);
        try {
            $r = in_array('voz', (array) ($modulo['requiere'] ?? []), true)
                ? $this->probarVoz($clave)
                : IA::enviar($clave, 'Responde únicamente con la palabra OK.', [], null, [], ['max_tokens' => 20, 'reintentos' => 0, 'timeout' => 30]);

            return response()->json(['data' => [
                'ok'        => true,
                'mensaje'   => "Funciona: respondió {$r['proveedor']} con {$r['modelo']}",
                'segundos'  => round(microtime(true) - $inicio, 1),
            ]]);
        } catch (IANoConfigurada $e) {
            return response()->json(['data' => ['ok' => false, 'mensaje' => $e->getMessage()]]);
        } catch (\Throwable $e) {
            $x = IA::explicarError($e->getMessage());
            return response()->json(['data' => [
                'ok'      => false,
                'mensaje' => "{$x['etiqueta']}. {$x['que_hacer']}",
                'detalle' => mb_substr($e->getMessage(), 0, 300),
            ]]);
        }
    }

    /**
     * Voz: generar audio de prueba cuesta y no aporta más que esto — la llave
     * autentica contra /models y el modelo de audio asignado aparece en la lista.
     */
    private function probarVoz(string $clave): array
    {
        $c = IA::credencialesPara($clave);

        $res = Http::withToken((string) $c['llave'])->timeout(15)->get($c['base'] . '/models');
        if (!$res->successful()) {
            throw new \RuntimeException("Error {$res->status()}: " . $res->body());
        }
        if (!collect($res->json('data', []))->pluck('id')->contains($c['modelo'])) {
            throw new \RuntimeException("model_not_found: {$c['modelo']}");
        }

        return ['proveedor' => $c['proveedor'], 'modelo' => $c['modelo']];
    }
}
