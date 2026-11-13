<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Marketing\Models\Empresa;
use App\Modules\Addons\Marketing\Models\EmpresaUsuario;
use App\Modules\Addons\Marketing\Requests\GuardarEmpresaRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * «Empresas y equipo» — marcas del grupo. Rutas con `marketing.rol:admin` +
 * `permission:marketing.empresas.gestionar`. Una persona solo ve y edita las empresas donde es
 * admin activa; cualquier otra {id} responde 404.
 */
class EmpresaController extends Controller
{
    public function vista(): View
    {
        return view('addon-marketing::empresas');
    }

    public function index(Request $request): JsonResponse
    {
        $ids = EmpresaUsuario::query()
            ->where('user_id', $request->user()->getKey())
            ->where('activo', true)
            ->where('rol', 'admin')
            ->pluck('company_id');

        $empresas = self::conConteos(Empresa::query()->whereIn('id', $ids))
            ->orderBy('id')
            ->get()
            ->map(fn (Empresa $e) => self::presentar($e))
            ->values();

        return response()->json(['ok' => true, 'data' => $empresas]);
    }

    public function store(GuardarEmpresaRequest $request): JsonResponse
    {
        $datos = $request->validated();

        try {
            $empresa = DB::transaction(function () use ($datos, $request) {
                $empresa = Empresa::create([
                    'nombre'           => $datos['nombre'],
                    'slug'             => $datos['slug'],
                    'color_primario'   => $datos['color_primario'] ?? null,
                    'color_secundario' => $datos['color_secundario'] ?? null,
                    'activo'           => true,
                ]);

                // Quien la crea queda como admin activo de la empresa nueva (misma transacción).
                EmpresaUsuario::create([
                    'company_id' => $empresa->getKey(),
                    'user_id'    => $request->user()->getKey(),
                    'rol'        => 'admin',
                    'activo'     => true,
                ]);

                return $empresa;
            });
        } catch (QueryException $e) {
            if (self::esDuplicado($e)) {
                return self::errorSlugDuplicado();
            }
            throw $e;
        }

        return response()->json(['ok' => true, 'data' => self::presentar(self::recargar($empresa))], 201);
    }

    public function update(GuardarEmpresaRequest $request, string $id): JsonResponse
    {
        // GuardarEmpresaRequest ya respondió 404 si la persona no administra esta empresa.
        $empresa = EmpresaUsuario::empresaAdministradaOFallar((int) $request->user()->getKey(), $id);
        $datos = $request->validated();

        if (array_key_exists('activo', $datos) && ! filter_var($datos['activo'], FILTER_VALIDATE_BOOLEAN) && $empresa->esPrincipal()) {
            return response()->json([
                'ok'      => false,
                'error'   => 'empresa_principal',
                'mensaje' => 'Meganet es la empresa principal y no se puede desactivar.',
            ], 422);
        }

        if (array_key_exists('activo', $datos)) {
            $datos['activo'] = filter_var($datos['activo'], FILTER_VALIDATE_BOOLEAN);
        }

        try {
            $empresa->fill($datos)->save();
        } catch (QueryException $e) {
            if (self::esDuplicado($e)) {
                return self::errorSlugDuplicado();
            }
            throw $e;
        }

        return response()->json(['ok' => true, 'data' => self::presentar(self::recargar($empresa))]);
    }

    private static function conConteos($query)
    {
        return $query->withCount([
            'asignaciones as miembros' => fn ($q) => $q->where('activo', true),
            'asignaciones as admins'   => fn ($q) => $q->where('activo', true)->where('rol', 'admin'),
        ]);
    }

    private static function recargar(Empresa $empresa): Empresa
    {
        return self::conConteos(Empresa::query()->whereKey($empresa->getKey()))->firstOrFail();
    }

    public static function presentar(Empresa $empresa): array
    {
        return [
            'id'               => (int) $empresa->getKey(),
            'nombre'           => $empresa->nombre,
            'slug'             => $empresa->slug,
            'color_primario'   => $empresa->color_primario,
            'color_secundario' => $empresa->color_secundario,
            'activo'           => (bool) $empresa->activo,
            'es_principal'     => $empresa->esPrincipal(),
            'miembros'         => (int) ($empresa->miembros ?? 0),
            'admins'           => (int) ($empresa->admins ?? 0),
            'created_at'       => self::fechaIso($empresa->created_at),
        ];
    }

    public static function fechaIso($fecha): ?string
    {
        return $fecha ? Carbon::parse($fecha)->timezone('America/Mexico_City')->toIso8601String() : null;
    }

    private static function esDuplicado(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }

    private static function errorSlugDuplicado(): JsonResponse
    {
        return response()->json([
            'message' => 'Ese identificador (slug) ya lo usa otra empresa.',
            'errors'  => ['slug' => ['Ese identificador (slug) ya lo usa otra empresa.']],
        ], 422);
    }
}
