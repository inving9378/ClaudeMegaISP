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
 * Hub) y qué modelo usa cada función. Cada módulo puede tener una IA general
 * que usan sus funciones sin IA propia; sin propia ni general no hay IA.
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
        $this->middleware('permission:manage-integrations')->only(['update', 'updateGeneral']);
        $this->middleware('permission:validate-integrations')->only(['probar', 'probarGeneral']);
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
                'efectivo'           => IA::efectivo($clave),
            ];
        })->values();

        $generales = collect(IA::grupos())->map(function ($grupo, $slug) use ($asignaciones) {
            $g = $asignaciones->get(IA::claveGeneral($grupo));
            return [
                'grupo'              => $grupo,
                'slug'               => $slug,
                'api_integration_id' => $g?->api_integration_id,
                'modelo'             => $g?->modelo,
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

        return response()->json(['data' => ['modulos' => $modulos, 'generales' => $generales, 'integraciones' => $integraciones]]);
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

        $grupo = $modulo['grupo'] ?? null;
        $hayGeneral = $grupo && IAAsignacion::where('clave', IA::claveGeneral($grupo))->whereNotNull('api_integration_id')->exists();
        $modelo = trim((string) $request->modelo);

        // Sin integración propia: usa la general del módulo (con su propio modelo, si se indicó)
        if (!$request->filled('api_integration_id')) {
            if ($modelo === '') {
                IAAsignacion::where('clave', $clave)->delete();
                $mensaje = $hayGeneral ? "Usa la IA general de «{$grupo}»" : 'Sin IA: queda apagada hasta que elijas una propia o la general del módulo';
            } elseif (!$hayGeneral) {
                return response()->json(['error' => "Para dejar solo el modelo propio, primero elige la IA general de «{$grupo}» (o una integración para esta función)."], 422);
            } else {
                IAAsignacion::updateOrCreate(['clave' => $clave], ['api_integration_id' => null, 'modelo' => $modelo]);
                $mensaje = "Usa la IA general de «{$grupo}» con el modelo {$modelo}";
            }
            return $this->respuestaGuardado($clave, $mensaje);
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
            ['api_integration_id' => $integracion->id, 'modelo' => $modelo]
        );

        return $this->respuestaGuardado($clave, "Usa su propia IA: {$integracion->name} con {$modelo}");
    }

    private function respuestaGuardado(string $clave, string $mensaje): JsonResponse
    {
        $efectivo = IA::efectivo($clave);
        return response()->json(['message' => $mensaje, 'data' => ['configurada' => !$efectivo['problema'], 'efectivo' => $efectivo]]);
    }

    /**
     * IA general del módulo (grupo): la usan todas sus funciones sin IA propia.
     * Devuelve avisos de lo que conviene revisar (funciones que no pueden usarla,
     * modelos propios que se quitaron por ser del proveedor anterior).
     */
    public function updateGeneral(Request $request, string $slug): JsonResponse
    {
        $grupo = IA::grupos()[$slug] ?? null;
        if (!$grupo) {
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

        $claveGeneral = IA::claveGeneral($grupo);
        $anterior = IAAsignacion::with('integracion')->where('clave', $claveGeneral)->first();
        $funciones = collect(IA::funcionesDe($grupo));
        $propias = IAAsignacion::whereIn('clave', $funciones)->get()->keyBy('clave');
        $nombre = fn($clave) => IA::modulo($clave)['nombre'] ?? $clave;
        // Funciones que toman la integración de la general (no tienen una propia)
        $heredan = $funciones->reject(fn($c) => $propias->get($c)?->api_integration_id);
        // "Solo modelo propio" de las funciones de chat: depende del proveedor de la general
        $soloModelo = $heredan->filter(fn($c) => $propias->get($c)?->modelo && empty(IA::modulo($c)['modelos']));
        $avisos = [];

        if (!$request->filled('api_integration_id')) {
            IAAsignacion::where('clave', $claveGeneral)->delete();
            // Sin general, un "solo modelo" ya no tiene a qué aplicarse
            $sinIntegracion = $heredan->filter(fn($c) => $propias->get($c)?->modelo);
            IAAsignacion::whereIn('clave', $sinIntegracion)->whereNull('api_integration_id')->delete();
            if ($heredan->isNotEmpty()) {
                $avisos[] = 'Quedan sin IA hasta que les pongas una propia: ' . $heredan->map($nombre)->implode(', ') . '.';
            }
            return response()->json(['message' => "«{$grupo}» ya no tiene IA general", 'avisos' => $avisos]);
        }

        $integracion = ApiIntegration::forCompany(1)->findOrFail($request->api_integration_id);
        if ($integracion->type !== 'ia') {
            return response()->json(['error' => 'Esa integración no es de IA'], 422);
        }
        $catalogo = ApiIntegrationProvider::where('slug', $integracion->provider)->first();
        if ($problema = IA::problemaDeIntegracion($integracion, $catalogo, [], "IA general de «{$grupo}»")) {
            return response()->json(['error' => $problema], 422);
        }

        $modelo = trim($request->modelo);
        IAAsignacion::updateOrCreate(['clave' => $claveGeneral], ['api_integration_id' => $integracion->id, 'modelo' => $modelo]);

        // Cambió el protocolo (p. ej. Claude → OpenAI): un modelo propio del proveedor anterior ya no existe en el nuevo
        $driverAnterior = $anterior?->integracion
            ? ApiIntegrationProvider::where('slug', $anterior->integracion->provider)->value('driver')
            : null;
        if ($driverAnterior && $driverAnterior !== $catalogo->driver && $soloModelo->isNotEmpty()) {
            IAAsignacion::whereIn('clave', $soloModelo)->whereNull('api_integration_id')->delete();
            $avisos[] = 'Se quitó el modelo propio de ' . $soloModelo->map($nombre)->implode(', ')
                . ' porque era del proveedor anterior; ahora usan ' . $modelo . '.';
        }

        $noPueden = $heredan->map(function ($c) use ($catalogo, $nombre) {
            $faltan = IA::capacidadesFaltantes($catalogo, (array) (IA::modulo($c)['requiere'] ?? []));
            return $faltan ? $nombre($c) . ' — necesita ' . implode(' y ', $faltan) : null;
        })->filter();
        if ($noPueden->isNotEmpty()) {
            $avisos[] = "{$catalogo->name} no sirve para: " . $noPueden->implode('; ') . '. Ponles una IA propia.';
        }

        return response()->json(['message' => "IA general de «{$grupo}»: {$integracion->name} con {$modelo}", 'avisos' => $avisos]);
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

        return $this->respuestaPrueba(fn() => in_array('voz', (array) ($modulo['requiere'] ?? []), true)
            ? $this->probarVoz($clave)
            : IA::enviar($clave, 'Responde únicamente con la palabra OK.', [], null, [], ['max_tokens' => 20, 'reintentos' => 0, 'timeout' => 30]));
    }

    /** Prueba en vivo de la IA general del módulo. */
    public function probarGeneral(string $slug): JsonResponse
    {
        $grupo = IA::grupos()[$slug] ?? null;
        if (!$grupo) {
            return response()->json(['error' => 'Módulo desconocido'], 404);
        }

        return $this->respuestaPrueba(fn() => IA::probarGeneral($grupo));
    }

    /** Corre la prueba y traduce el resultado (o el error del proveedor) para la pantalla. */
    private function respuestaPrueba(callable $prueba): JsonResponse
    {
        $inicio = microtime(true);
        try {
            $r = $prueba();

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
