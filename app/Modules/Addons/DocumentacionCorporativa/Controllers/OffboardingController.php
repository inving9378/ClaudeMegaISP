<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcActivoDigital;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcInventarioAcceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Checklist de offboarding (apartado XII, Fase 5d-1, item roadmap #815).
 *
 * Doble puerta igual que el resto del módulo: `.offboarding.ver` para
 * consultar los accesos/activos digitales pendientes de un colaborador que
 * sale, `.offboarding.gestionar` (más fino) para marcarlos como revocados.
 *
 * Revocación SIEMPRE una por una — nunca masiva/automática (la Opción C de
 * #667 la descartó explícitamente por tocar la frontera dura de permisos).
 */
class OffboardingController extends Controller
{
    private const PERMISO_VER       = 'documentacion-corporativa.offboarding.ver';
    private const PERMISO_GESTIONAR = 'documentacion-corporativa.offboarding.gestionar';

    /** Selector de colaborador: busca por nombre o login_user (mismo criterio que el resto del sistema). */
    public function colaboradores(Request $request): JsonResponse
    {
        $this->autorizar(self::PERMISO_VER);

        $q = trim((string) $request->query('q', ''));

        $usuarios = User::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('login_user', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('father_last_name', 'like', "%{$q}%")
                        ->orWhere('mother_last_name', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'father_last_name', 'mother_last_name', 'login_user'])
            ->map(fn ($u) => [
                'id'    => $u->id,
                'label' => trim(implode(' ', array_filter([$u->name, $u->father_last_name, $u->mother_last_name])))
                    . " ({$u->login_user})",
            ])
            ->values();

        return response()->json($usuarios);
    }

    /** Accesos y activos digitales AÚN NO revocados a cargo de un colaborador. */
    public function pendientes(Request $request): JsonResponse
    {
        $this->autorizar(self::PERMISO_VER);

        $userId = $request->integer('user_id');
        abort_if(!$userId, 422, 'Falta el colaborador.');

        $accesos = DcInventarioAcceso::deCustodio($userId)
            ->whereNull('revocado_at')
            ->orderBy('institucion_o_sistema')
            ->get(['id', 'tipo', 'institucion_o_sistema', 'identificador_publico', 'ubicacion_resguardo']);

        $activos = DcActivoDigital::deResponsable($userId)
            ->whereNull('revocado_at')
            ->orderBy('nombre')
            ->get(['id', 'tipo', 'nombre', 'proveedor', 'url']);

        return response()->json([
            'accesos' => $accesos,
            'activos' => $activos,
        ]);
    }

    /** Marca UNA fila (acceso o activo) como revocada. Nunca masivo/automático. */
    public function revocar(Request $request): JsonResponse
    {
        $this->autorizar(self::PERMISO_GESTIONAR);

        $data = $request->validate([
            'tipo' => 'required|in:acceso,activo',
            'id'   => 'required|integer',
        ]);

        $modelo   = $data['tipo'] === 'acceso' ? DcInventarioAcceso::class : DcActivoDigital::class;
        $registro = $modelo::findOrFail($data['id']);

        if (!$registro->estaRevocado()) {
            $registro->revocado_at          = now();
            $registro->revocado_por_user_id = auth()->id();
            $registro->save();
        }

        return response()->json(['ok' => true, 'revocado_at' => $registro->revocado_at]);
    }

    private function autorizar(string $permiso): void
    {
        abort_unless(
            auth()->user()?->can($permiso),
            403,
            'No tienes permiso para el checklist de offboarding.'
        );
    }
}
