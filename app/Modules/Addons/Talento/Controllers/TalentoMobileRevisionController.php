<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Services\RevisionOrdenesAppService;
use Illuminate\Http\Request;

/**
 * Revisión del supervisor en la app (13-nov-2026, sobre #1286): las órdenes terminadas de su equipo
 * que esperan su revisión, su detalle con las fotos, y validarlas o devolverlas con una nota. Mismo
 * permiso que la web (talento.work_orders.validate); el alcance lo decide RevisionOrdenesAppService.
 */
class TalentoMobileRevisionController extends Controller
{
    public function __construct(private RevisionOrdenesAppService $revision) {}

    /** GET /talento/api/revision/ordenes?page= */
    public function index(Request $request)
    {
        $this->autorizar($request);

        return response()->json($this->revision->pendientes($request->user(), max(1, (int) $request->query('page', 1))));
    }

    /** GET /talento/api/revision/ordenes/{id} */
    public function show(Request $request, int $id)
    {
        $this->autorizar($request);
        $ot = $this->revision->detalle($request->user(), $id);
        if (! $ot) {
            return response()->json(['message' => 'Orden no encontrada.'], 404);
        }

        return response()->json(['ot' => $ot]);
    }

    /** POST /talento/api/revision/ordenes/{id}/validar */
    public function validar(Request $request, int $id)
    {
        $this->autorizar($request);
        $r = $this->revision->validar($request->user(), $id);
        if (! $r['success']) {
            return response()->json(['message' => $r['message']], $r['status_code'] ?? 422);
        }

        return response()->json(['message' => 'Orden validada.', 'ot' => $this->revision->detalle($request->user(), $id)]);
    }

    /** POST /talento/api/revision/ordenes/{id}/devolver {motivo} */
    public function devolver(Request $request, int $id)
    {
        $this->autorizar($request);
        $data = $request->validate(['motivo' => 'required|string|min:5|max:1000'], [
            'motivo.required' => 'Escribe qué tiene que corregir.',
            'motivo.min' => 'Escribe qué tiene que corregir (al menos 5 letras).',
        ]);
        $r = $this->revision->devolver($request->user(), $id, trim($data['motivo']));
        if (! $r['success']) {
            return response()->json(['message' => $r['message']], $r['status_code'] ?? 422);
        }

        return response()->json(['message' => 'Orden devuelta al técnico.']);
    }

    private function autorizar(Request $request): void
    {
        abort_unless($request->user()->can('talento.work_orders.validate'), 403, 'No tienes permiso para validar órdenes.');
        abort_unless(config('unificacion.interruptores.validacion_separada'), 422, 'La revisión del supervisor no está encendida: las órdenes se validan solas al terminarse.');
    }
}
