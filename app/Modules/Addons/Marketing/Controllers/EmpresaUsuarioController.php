<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Addons\Marketing\Models\EmpresaUsuario;
use App\Modules\Addons\Marketing\Requests\ActualizarAsignacionRequest;
use App\Modules\Addons\Marketing\Requests\GuardarAsignacionRequest;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * «Empresas y equipo» — quién trabaja en cada empresa y con qué rol (admin | marketing | ventas).
 * Solo escribe marketing_empresa_usuario: NUNCA da ni quita roles o permisos Spatie del sistema.
 */
class EmpresaUsuarioController extends Controller
{
    private const MSJ_YA_ASIGNADO = 'Esa persona ya está en esta empresa.';

    private const MSJ_ULTIMO_ADMIN = 'La empresa debe conservar al menos un administrador activo.';

    public function index(Request $request, string $id): JsonResponse
    {
        $empresa = EmpresaUsuario::empresaAdministradaOFallar((int) $request->user()->getKey(), $id);

        $miembros = EmpresaUsuario::query()
            ->with('usuario:id,name,father_last_name,mother_last_name,login_user,email')
            ->where('company_id', $empresa->getKey())
            ->get()
            ->map(fn (EmpresaUsuario $a) => self::presentar($a))
            ->sort(fn ($a, $b) => [$b['activo'], mb_strtolower($a['nombre'])] <=> [$a['activo'], mb_strtolower($b['nombre'])])
            ->values();

        return response()->json(['ok' => true, 'data' => $miembros]);
    }

    public function store(GuardarAsignacionRequest $request, string $id): JsonResponse
    {
        $empresa = EmpresaUsuario::empresaAdministradaOFallar((int) $request->user()->getKey(), $id);
        $datos = $request->validated();

        try {
            [$asignacion, $status] = DB::transaction(function () use ($empresa, $datos) {
                $existente = EmpresaUsuario::query()
                    ->where('company_id', $empresa->getKey())
                    ->where('user_id', (int) $datos['user_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existente && $existente->activo) {
                    return [null, 422];
                }

                if ($existente) {
                    // Estaba dada de baja: se reactiva con el rol nuevo.
                    $existente->fill(['rol' => $datos['rol'], 'activo' => true])->save();

                    return [$existente, 200];
                }

                $nueva = EmpresaUsuario::create([
                    'company_id' => $empresa->getKey(),
                    'user_id'    => (int) $datos['user_id'],
                    'rol'        => $datos['rol'],
                    'activo'     => true,
                ]);

                return [$nueva, 201];
            });
        } catch (QueryException $e) {
            // Doble envío simultáneo: el unique(company_id, user_id) atrapa al segundo.
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
            [$asignacion, $status] = [null, 422];
        }

        if (! $asignacion) {
            return response()->json(['ok' => false, 'error' => 'ya_asignado', 'mensaje' => self::MSJ_YA_ASIGNADO], 422);
        }

        return response()->json(['ok' => true, 'data' => self::presentar($asignacion->load('usuario'))], $status);
    }

    public function update(ActualizarAsignacionRequest $request, string $id, string $userId): JsonResponse
    {
        $empresa = EmpresaUsuario::empresaAdministradaOFallar((int) $request->user()->getKey(), $id);
        $datos = $request->validated();

        if (! ctype_digit($userId)) {
            return self::noEncontrado();
        }

        $resultado = DB::transaction(function () use ($empresa, $datos, $userId) {
            $asignacion = EmpresaUsuario::query()
                ->where('company_id', $empresa->getKey())
                ->where('user_id', (int) $userId)
                ->lockForUpdate()
                ->first();

            if (! $asignacion) {
                return 404;
            }

            $nuevoRol = (isset($datos['rol']) && $datos['rol'] !== null) ? $datos['rol'] : $asignacion->rol;
            $nuevoActivo = (array_key_exists('activo', $datos) && $datos['activo'] !== null)
                ? filter_var($datos['activo'], FILTER_VALIDATE_BOOLEAN)
                : (bool) $asignacion->activo;

            $eraAdminActivo = $asignacion->activo && $asignacion->rol === 'admin';
            $quedaAdminActivo = $nuevoActivo && $nuevoRol === 'admin';

            if ($eraAdminActivo && ! $quedaAdminActivo) {
                $otrosAdmins = EmpresaUsuario::query()
                    ->where('company_id', $empresa->getKey())
                    ->where('id', '!=', $asignacion->getKey())
                    ->where('activo', true)
                    ->where('rol', 'admin')
                    ->lockForUpdate()
                    ->count();

                if ($otrosAdmins === 0) {
                    return 'ultimo_admin';
                }
            }

            $asignacion->fill(['rol' => $nuevoRol, 'activo' => $nuevoActivo])->save();

            return $asignacion;
        });

        if ($resultado === 404) {
            return self::noEncontrado();
        }

        if ($resultado === 'ultimo_admin') {
            return response()->json(['ok' => false, 'error' => 'ultimo_admin', 'mensaje' => self::MSJ_ULTIMO_ADMIN], 422);
        }

        return response()->json(['ok' => true, 'data' => self::presentar($resultado->load('usuario'))]);
    }

    public function disponibles(Request $request, string $id): JsonResponse
    {
        $empresa = EmpresaUsuario::empresaAdministradaOFallar((int) $request->user()->getKey(), $id);

        $datos = $request->validate([
            'buscar' => ['required', 'string', 'min:2', 'max:60'],
        ], [
            'buscar.required' => 'Escribe al menos 2 letras para buscar.',
            'buscar.string'   => 'La búsqueda debe ser texto.',
            'buscar.min'      => 'Escribe al menos 2 letras para buscar.',
            'buscar.max'      => 'La búsqueda no puede pasar de 60 caracteres.',
        ]);

        // Comodines de LIKE escapados: lo que escribe la persona se busca literal.
        $patron = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $datos['buscar']) . '%';

        $miembrosActivos = EmpresaUsuario::query()
            ->where('company_id', $empresa->getKey())
            ->where('activo', true)
            ->pluck('user_id');

        $personas = User::query()
            ->select(['id', 'name', 'father_last_name', 'mother_last_name', 'login_user', 'email'])
            ->where('estado', 'activo')
            ->where(fn ($q) => $q->whereNull('is_system')->orWhere('is_system', false))
            ->whereHas('roles', fn ($q) => $q->where('name', '!=', 'client'))
            ->whereNotIn('id', $miembrosActivos)
            ->where(function ($q) use ($patron) {
                $q->where('name', 'like', $patron)
                    ->orWhere('father_last_name', 'like', $patron)
                    ->orWhere('login_user', 'like', $patron)
                    ->orWhere('email', 'like', $patron);
            })
            ->orderBy('name')
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(fn (User $u) => [
                'id'         => (int) $u->getKey(),
                'nombre'     => ContextoEmpresaController::nombreDe($u),
                'login_user' => $u->login_user,
                'email'      => $u->email,
            ])
            ->values();

        return response()->json(['ok' => true, 'data' => $personas]);
    }

    private static function presentar(EmpresaUsuario $asignacion): array
    {
        $usuario = $asignacion->usuario;

        return [
            'user_id'     => (int) $asignacion->user_id,
            'nombre'      => $usuario ? ContextoEmpresaController::nombreDe($usuario) : '',
            'login_user'  => $usuario?->login_user,
            'email'       => $usuario?->email,
            'rol'         => $asignacion->rol,
            'activo'      => (bool) $asignacion->activo,
            'asignado_at' => EmpresaController::fechaIso($asignacion->created_at),
        ];
    }

    private static function noEncontrado(): JsonResponse
    {
        return response()->json([
            'ok'      => false,
            'error'   => 'no_encontrado',
            'mensaje' => 'Esa persona no está en esta empresa.',
        ], 404);
    }
}
