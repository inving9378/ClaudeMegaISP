<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\DocumentacionCorporativa\Services\OffboardingOtrosItemsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backend de los 6 ítems fijos de offboarding sin tabla propia (Fase 5d-2a,
 * item roadmap #839): correo, VPN, WhatsApp, equipo, respaldo, finiquito RH.
 *
 * Misma doble puerta que `OffboardingController` (#815, apartado XII):
 * `.offboarding.ver` para consultar, `.offboarding.gestionar` para marcar.
 * Sin UI todavía (item #840, bloqueado hasta que #815 mergee a main).
 */
class OffboardingOtrosItemsController extends Controller
{
    private const PERMISO_VER       = 'documentacion-corporativa.offboarding.ver';
    private const PERMISO_GESTIONAR = 'documentacion-corporativa.offboarding.gestionar';

    public function __construct(private OffboardingOtrosItemsService $items)
    {
    }

    /** Los 6 ítems fijos con su estado actual para un colaborador. */
    public function index(Request $request): JsonResponse
    {
        $this->autorizar(self::PERMISO_VER, 'consultar el checklist de offboarding');

        $userId = $request->integer('user_id');
        abort_if(!$userId, 422, 'Falta el colaborador.');

        return response()->json(array_values($this->items->listar($userId)));
    }

    /** Marca un ítem como completado/pendiente + notas + evidencia opcional. */
    public function marcar(Request $request): JsonResponse
    {
        $this->autorizar(self::PERMISO_GESTIONAR, 'gestionar el checklist de offboarding');

        $validado = $request->validate([
            'user_id'             => 'required|integer',
            'item_clave'          => 'required|string|in:' . implode(',', array_keys(OffboardingOtrosItemsService::ITEMS)),
            'completado'          => 'nullable|boolean',
            'fecha'               => 'nullable|date',
            'responsable_user_id' => 'nullable|integer',
            'notas'               => 'nullable|string|max:2000',
            'evidencia'           => 'nullable|file|max:10240',
        ]);

        $item = $this->items->marcar(
            (int) $validado['user_id'],
            $validado['item_clave'],
            $validado,
            $request->file('evidencia')
        );

        return response()->json($item);
    }

    private function autorizar(string $permiso, string $accion): void
    {
        abort_unless(
            auth()->user()?->can($permiso),
            403,
            "No tienes permiso para {$accion}."
        );
    }
}
