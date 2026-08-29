<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcAccionista;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcActa;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcCapitalVariacion;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcContrato;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcPoder;
use App\Modules\Addons\DocumentacionCorporativa\Services\EmpresaContextService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Fase 2c (item roadmap #736) — CRUD mínimo de los 5 registros estructurados
 * que el catálogo (Fase 0) declara como tipo `inventario`: libro de acciones,
 * variaciones de capital, actas (asamblea/consejo), poderes (apartado I) y
 * contratos (apartado VI). Un solo controlador genérico por `{recurso}`
 * porque el patrón es IDÉNTICO en los 5 (alta/edición/baja scopeada a
 * empresa, sin lógica propia por tabla) — cinco controladores casi iguales
 * habría sido la abstracción equivocada en sentido contrario.
 *
 * Misma doble puerta que `ConcesionController`: VER lo gatea el permiso del
 * apartado dueño del recurso (`.apartado.i.view` / `.apartado.vi.view`, ya
 * gateado también por `InventarioResolver` al resolver el concepto);
 * ADMINISTRAR (crear/editar/eliminar) lo gatea `.registro.manage`, un
 * permiso nuevo — `.concepto.manage` ya significa otra cosa (administrar el
 * CATÁLOGO de conceptos), no capturar datos dentro de un concepto existente.
 */
class RegistroEstructuradoController extends Controller
{
    private const PERMISO_MANAGE = 'documentacion-corporativa.registro.manage';

    /** recurso (URL) => [modelo, apartado dueño]. */
    private const RECURSOS = [
        'accionistas'          => ['modelo' => DcAccionista::class,      'apartado' => 'i'],
        'capital_variaciones'  => ['modelo' => DcCapitalVariacion::class, 'apartado' => 'i'],
        'actas'                => ['modelo' => DcActa::class,            'apartado' => 'i'],
        'poderes'              => ['modelo' => DcPoder::class,           'apartado' => 'i'],
        'contratos'            => ['modelo' => DcContrato::class,        'apartado' => 'vi'],
    ];

    /** Columnas por las que `index` acepta filtrar por query string (además de empresa_id). */
    private const COLUMNAS_FILTRABLES = ['tipo'];

    public function __construct(private EmpresaContextService $empresas)
    {
    }

    public function index(Request $request, string $recurso): JsonResponse
    {
        $this->autorizarVer($recurso);
        $empresa = $this->empresas->actual();

        /** @var class-string<Model> $modelo */
        $modelo = self::RECURSOS[$recurso]['modelo'];

        $query = $modelo::deEmpresa($empresa->id);

        foreach (self::COLUMNAS_FILTRABLES as $columna) {
            if ($request->filled($columna)) {
                $query->where($columna, $request->input($columna));
            }
        }

        return response()->json(['data' => $query->orderByDesc('id')->get()]);
    }

    public function store(Request $request, string $recurso): JsonResponse
    {
        $this->autorizarManage($recurso);
        $empresa = $this->empresas->actual();

        /** @var class-string<Model> $modelo */
        $modelo   = self::RECURSOS[$recurso]['modelo'];
        $validado = $request->validate($this->reglas($recurso));
        $validado['empresa_id'] = $empresa->id;

        $registro = $modelo::create($validado);

        return response()->json($registro->fresh(), 201);
    }

    public function update(Request $request, string $recurso, int $id): JsonResponse
    {
        $this->autorizarManage($recurso);
        $empresa = $this->empresas->actual();

        /** @var class-string<Model> $modelo */
        $modelo   = self::RECURSOS[$recurso]['modelo'];
        $registro = $modelo::deEmpresa($empresa->id)->findOrFail($id);
        $registro->update($request->validate($this->reglas($recurso)));

        return response()->json($registro->fresh());
    }

    public function destroy(string $recurso, int $id): JsonResponse
    {
        $this->autorizarManage($recurso);
        $empresa = $this->empresas->actual();

        /** @var class-string<Model> $modelo */
        $modelo = self::RECURSOS[$recurso]['modelo'];
        $modelo::deEmpresa($empresa->id)->findOrFail($id)->delete();

        return response()->json(['deleted' => true]);
    }

    private function reglas(string $recurso): array
    {
        return match ($recurso) {
            'accionistas' => [
                'nombre_razon_social' => ['required', 'string', 'max:255'],
                'porcentaje'          => ['required', 'numeric', 'min:0', 'max:100'],
                'num_acciones'        => ['nullable', 'integer', 'min:0'],
                'tipo_serie'          => ['nullable', 'string', 'max:100'],
                'fecha_alta'          => ['required', 'date'],
                'fecha_baja'          => ['nullable', 'date'],
            ],
            'capital_variaciones' => [
                'fecha'              => ['required', 'date'],
                'tipo'               => ['required', Rule::in(DcCapitalVariacion::TIPOS)],
                'monto'              => ['required', 'numeric', 'min:0'],
                'capital_resultante' => ['required', 'numeric', 'min:0'],
                'nota'               => ['nullable', 'string'],
            ],
            'actas' => [
                'tipo'          => ['required', Rule::in(DcActa::TIPOS)],
                'fecha'         => ['required', 'date'],
                'folio'         => ['nullable', 'string', 'max:255'],
                'resumen'       => ['nullable', 'string'],
                'protocolizada' => ['nullable', 'boolean'],
                'documento_id'  => ['nullable', 'integer', 'exists:dc_documentos,id'],
            ],
            'poderes' => [
                'apoderado'          => ['required', 'string', 'max:255'],
                'tipo_poder'         => ['required', 'string', 'max:255'],
                'alcance'            => ['nullable', 'string'],
                'fecha_otorgamiento' => ['required', 'date'],
                'vigencia_fin'       => ['nullable', 'date'],
                'revocado'           => ['nullable', 'boolean'],
                'documento_id'       => ['nullable', 'integer', 'exists:dc_documentos,id'],
            ],
            'contratos' => [
                'tipo'         => ['required', Rule::in(DcContrato::TIPOS)],
                'contraparte'  => ['required', 'string', 'max:255'],
                'objeto'       => ['nullable', 'string'],
                'fecha_inicio' => ['required', 'date'],
                'fecha_fin'    => ['nullable', 'date'],
                'monto'        => ['nullable', 'numeric', 'min:0'],
                'documento_id' => ['nullable', 'integer', 'exists:dc_documentos,id'],
            ],
            default => throw new NotFoundHttpException("Recurso desconocido: {$recurso}"),
        };
    }

    private function autorizarVer(string $recurso): void
    {
        $apartado = self::RECURSOS[$recurso]['apartado'] ?? null;
        abort_if($apartado === null, 404, 'Recurso desconocido.');

        abort_unless(
            auth()->user()?->can("documentacion-corporativa.apartado.{$apartado}.view"),
            403,
            'No tienes permiso para ver este apartado.'
        );
    }

    private function autorizarManage(string $recurso): void
    {
        abort_if(! array_key_exists($recurso, self::RECURSOS), 404, 'Recurso desconocido.');

        abort_unless(
            auth()->user()?->can(self::PERMISO_MANAGE),
            403,
            'No tienes permiso para administrar registros estructurados.'
        );
    }
}
