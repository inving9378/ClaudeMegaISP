<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcActivo;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcActivoDigital;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcInventarioAcceso;
use App\Modules\Addons\DocumentacionCorporativa\Services\EmpresaContextService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Fase 3.2 (item roadmap #751) — CRUD de los 3 recursos de inventario que
 * Fase 3.1 (#750, ya en main) dejó modelados: activos físicos (apartado V),
 * activos digitales (apartado VIII) e inventario de accesos (apartados VIII/
 * XI). Un solo controlador genérico por `{recurso}`, mismo patrón que
 * `RegistroEstructuradoController` (Fase 2c, #736): el CRUD es idéntico en
 * los 3 (alta/edición/baja scopeada a empresa), solo cambian modelo y reglas
 * de validación.
 *
 * Doble puerta: VER lo gatea el permiso del apartado dueño del recurso;
 * ADMINISTRAR (crear/editar/eliminar) lo gatea `.inventario.manage`, un
 * permiso nuevo — igual criterio que ya separa `.concesion.manage` y
 * `.registro.manage` de `.concepto.manage` (ese administra el CATÁLOGO, no
 * captura datos dentro de un concepto existente).
 *
 * `accesos` (dc_inventario_accesos) sirve conceptos de DOS apartados a la vez
 * (VIII "Credenciales de acceso" y XI "cuentas bancarias/firmas/...") y el
 * esquema no distingue cuál fila es cuál (el enum `tipo` no lo permite:
 * `usuario_sistema` se usa en conceptos de ambos apartados). Decisión
 * registrada con `circuito:reportar`: la lectura se abre con CUALQUIERA de
 * los dos permisos de apartado (viii.view OR xi.view), nunca con ambos a la
 * vez exigidos.
 *
 * `DcInventarioAcceso` nunca expone una columna cruda de secreto: el JSON
 * siempre trae `credencial`/`credencial_leyenda` vía `$appends` del modelo
 * (constante calculada, jamás dato de fila) — no hay nada especial que hacer
 * aquí para cumplir esa regla, el modelo ya la garantiza.
 */
class InventarioController extends Controller
{
    private const PERMISO_MANAGE = 'documentacion-corporativa.inventario.manage';

    /** recurso (URL) => [modelo, apartados que autorizan VER (cualquiera basta)]. */
    private const RECURSOS = [
        'activos'           => ['modelo' => DcActivo::class,           'apartados' => ['v']],
        'activos_digitales' => ['modelo' => DcActivoDigital::class,    'apartados' => ['viii']],
        'accesos'           => ['modelo' => DcInventarioAcceso::class, 'apartados' => ['viii', 'xi']],
    ];

    /** Columnas por las que `index` acepta filtrar por query string, por recurso. */
    private const COLUMNAS_FILTRABLES = [
        'activos'           => ['categoria', 'estado'],
        'activos_digitales' => ['tipo', 'titularidad_estado'],
        'accesos'           => ['tipo'],
    ];

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

        foreach (self::COLUMNAS_FILTRABLES[$recurso] ?? [] as $columna) {
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
            'activos' => [
                'categoria'            => ['required', Rule::in(DcActivo::CATEGORIAS)],
                'nombre'               => ['required', 'string', 'max:255'],
                'descripcion'          => ['nullable', 'string'],
                'identificador'        => ['nullable', 'string', 'max:255'],
                'ubicacion'            => ['nullable', 'string', 'max:255'],
                'lat'                  => ['nullable', 'numeric', 'between:-90,90'],
                'lng'                  => ['nullable', 'numeric', 'between:-180,180'],
                'fecha_adquisicion'    => ['nullable', 'date'],
                'valor_adquisicion'    => ['nullable', 'numeric', 'min:0'],
                'estado'               => ['nullable', Rule::in(DcActivo::ESTADOS)],
                'responsable_user_id'  => ['nullable', 'integer', 'exists:users,id'],
                'notas'                => ['nullable', 'string'],
            ],
            'activos_digitales' => [
                'tipo'                 => ['required', Rule::in(DcActivoDigital::TIPOS)],
                'nombre'               => ['required', 'string', 'max:255'],
                'descripcion'          => ['nullable', 'string'],
                'proveedor'            => ['nullable', 'string', 'max:255'],
                // titular es NOT NULL a nivel BD (regla de titularidad, #750): siempre requerido.
                'titular'              => ['required', 'string', 'max:255'],
                'url'                  => ['nullable', 'string', 'max:255'],
                'fecha_alta'           => ['nullable', 'date'],
                'vigencia_fin'         => ['nullable', 'date'],
                'costo_periodico'      => ['nullable', 'numeric', 'min:0'],
                'periodicidad_costo'   => ['nullable', 'string', 'max:100'],
                'responsable_user_id'  => ['nullable', 'integer', 'exists:users,id'],
                'notas'                => ['nullable', 'string'],
            ],
            'accesos' => [
                'tipo'                   => ['required', Rule::in(DcInventarioAcceso::TIPOS)],
                'institucion_o_sistema'  => ['required', 'string', 'max:255'],
                // Sin límite aquí a propósito: el mutator del modelo trunca a los últimos 4 caracteres.
                'identificador_publico'  => ['nullable', 'string'],
                'titular'                => ['nullable', 'string', 'max:255'],
                'secreto_existe'         => ['nullable', 'boolean'],
                'custodio_user_id'       => ['nullable', 'integer', 'exists:users,id'],
                'ubicacion_resguardo'    => ['nullable', 'string', 'max:255'],
                'fecha_ultima_revision'  => ['nullable', 'date'],
                'notas'                  => ['nullable', 'string'],
            ],
            default => throw new NotFoundHttpException("Recurso desconocido: {$recurso}"),
        };
    }

    private function autorizarVer(string $recurso): void
    {
        $apartados = self::RECURSOS[$recurso]['apartados'] ?? null;
        abort_if($apartados === null, 404, 'Recurso desconocido.');

        $tienePermiso = collect($apartados)->contains(
            fn (string $apartado) => auth()->user()?->can("documentacion-corporativa.apartado.{$apartado}.view")
        );

        abort_unless($tienePermiso, 403, 'No tienes permiso para ver este apartado.');
    }

    private function autorizarManage(string $recurso): void
    {
        abort_if(! array_key_exists($recurso, self::RECURSOS), 404, 'Recurso desconocido.');

        abort_unless(
            auth()->user()?->can(self::PERMISO_MANAGE),
            403,
            'No tienes permiso para administrar el inventario.'
        );
    }
}
