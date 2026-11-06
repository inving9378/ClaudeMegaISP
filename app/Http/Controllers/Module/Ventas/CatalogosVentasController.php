<?php

namespace App\Http\Controllers\Module\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Ventas\VentaModalidad;
use App\Models\Ventas\VentaModoPago;
use App\Models\Ventas\VentaParametroReglamento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * CRUD parametrizado de los 3 catálogos del motor de ventas (item #9990808,
 * Fase 1 de #9990781): modalidades, modos-pago, parametros-reglamento.
 *
 * Un solo controller (a criterio del item) porque las 3 rutas son el mismo
 * CRUD con distinta forma; el slug `catalogo` viaja en la ruta (`->defaults()`
 * en routes/web.php) y resuelve modelo + permisos + validación aquí.
 */
class CatalogosVentasController extends Controller
{
    private const CATALOGOS = [
        'modalidades' => [
            'model'   => VentaModalidad::class,
            'permiso' => 'ventas_talento_catalogo_modalidades',
            'tipo'    => 'estandar',
        ],
        'modos-pago' => [
            'model'   => VentaModoPago::class,
            'permiso' => 'ventas_talento_catalogo_modos_pago',
            'tipo'    => 'estandar',
        ],
        'parametros-reglamento' => [
            'model'   => VentaParametroReglamento::class,
            'permiso' => 'ventas_talento_catalogo_parametros_reglamento',
            'tipo'    => 'reglamento',
        ],
    ];

    public function index(Request $request, string $catalogo)
    {
        $config = $this->resolverCatalogo($catalogo);
        abort_unless($request->user()->can($config['permiso'] . '_ver'), 403);

        $modelClass = $config['model'];
        $orderColumn = $config['tipo'] === 'reglamento' ? 'clave' : 'orden';

        return response()->json(
            $modelClass::orderBy($orderColumn)->get()
        );
    }

    public function store(Request $request, string $catalogo)
    {
        $config = $this->resolverCatalogo($catalogo);
        abort_unless($request->user()->can($config['permiso'] . '_crear'), 403);

        $validator = Validator::make($request->all(), $this->reglasValidacion($catalogo));
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $modelClass = $config['model'];
        $registro = $modelClass::create(array_merge($validator->validated(), [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return response()->json($registro, 201);
    }

    public function update(Request $request, string $catalogo, int $id)
    {
        $config = $this->resolverCatalogo($catalogo);
        abort_unless($request->user()->can($config['permiso'] . '_editar'), 403);

        $modelClass = $config['model'];
        $registro = $modelClass::findOrFail($id);

        $validator = Validator::make($request->all(), $this->reglasValidacion($catalogo, $id));
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $registro->fill(array_merge($validator->validated(), [
            'updated_by' => $request->user()->id,
        ]));
        $registro->save();

        return response()->json($registro);
    }

    public function destroy(Request $request, string $catalogo, int $id)
    {
        $config = $this->resolverCatalogo($catalogo);
        abort_unless($request->user()->can($config['permiso'] . '_eliminar'), 403);

        $modelClass = $config['model'];
        $registro = $modelClass::findOrFail($id);
        $registro->updated_by = $request->user()->id;
        $registro->save();
        $registro->delete();

        return response()->json(['deleted' => true]);
    }

    private function resolverCatalogo(string $catalogo): array
    {
        abort_unless(isset(self::CATALOGOS[$catalogo]), 404);

        return self::CATALOGOS[$catalogo];
    }

    private function reglasValidacion(string $catalogo, ?int $ignorarId = null): array
    {
        $config = self::CATALOGOS[$catalogo];
        $modelClass = $config['model'];
        $tabla = (new $modelClass())->getTable();

        if ($config['tipo'] === 'reglamento') {
            $uniqueClave = 'unique:' . $tabla . ',clave' . ($ignorarId ? ',' . $ignorarId : '');

            return [
                'clave'       => ['required', 'string', 'max:100', $uniqueClave],
                'nombre'      => ['required', 'string', 'max:150'],
                'tipo'        => ['required', 'in:entero,decimal,texto,booleano'],
                'valor'       => ['nullable', 'string', 'max:255'],
                'descripcion' => ['nullable', 'string'],
                'activo'      => ['sometimes', 'boolean'],
            ];
        }

        $uniqueCodigo = 'unique:' . $tabla . ',codigo' . ($ignorarId ? ',' . $ignorarId : '');

        return [
            'codigo'  => ['required', 'string', 'max:60', $uniqueCodigo],
            'nombre'  => ['required', 'string', 'max:150'],
            'activo'  => ['sometimes', 'boolean'],
            'orden'   => ['sometimes', 'integer'],
        ];
    }
}
