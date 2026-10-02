<?php

namespace App\Modules\Addons\Hub\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Core\ApiIntegration;
use App\Models\Core\ApiIntegrationProvider;
use App\Modules\Addons\IA\Models\IAAsignacion;
use App\Modules\Addons\IA\Services\IA;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    ];

    public function __construct()
    {
        $this->middleware('permission:view-integrations')->only(['index']);
        $this->middleware('permission:manage-integrations')->only(['update']);
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
                'api_integration_id' => $a?->api_integration_id,
                'modelo'             => $a?->modelo,
            ];
        })->values();

        $catalogo = ApiIntegrationProvider::where('type', 'ia')->get()->keyBy('slug');

        $integraciones = ApiIntegration::forCompany(1)->where('type', 'ia')->orderBy('name')->get()
            ->map(function (ApiIntegration $i) use ($catalogo) {
                $p = $catalogo->get($i->provider);
                return [
                    'id'                => $i->id,
                    'nombre'            => $i->name,
                    'proveedor'         => $p?->name ?? $i->provider,
                    'driver'            => $p?->driver,
                    'activa'            => (bool) $i->active,
                    'tiene_llave'       => (bool) $i->encrypted_value,
                    'estado_validacion' => $i->last_validation_status,
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
        if (!$catalogo || !$catalogo->driver) {
            return response()->json(['error' => "El proveedor «{$integracion->provider}» no tiene definido su protocolo de IA. Edítalo en la pestaña Proveedores."], 422);
        }

        $faltan = IA::capacidadesFaltantes($catalogo, (array) ($modulo['requiere'] ?? []));
        if ($faltan) {
            return response()->json(['error' => "«{$catalogo->name}» no soporta " . implode(' ni ', $faltan) . ', que este módulo necesita'], 422);
        }

        IAAsignacion::updateOrCreate(
            ['clave' => $clave],
            ['api_integration_id' => $integracion->id, 'modelo' => trim($request->modelo)]
        );

        return response()->json(['message' => 'Guardado', 'data' => ['configurada' => IA::configurada($clave)]]);
    }
}
