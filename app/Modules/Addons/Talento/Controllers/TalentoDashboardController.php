<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Services\DashboardService;
use App\Modules\Addons\Talento\Support\Actor;
use Illuminate\Http\Request;

/**
 * David (29-sep): "ponlo en el dashboard nuevo... elimina ese del sidebar"
 * — este dashboard se consolida dentro de /talento (TalentoColaboradores.vue,
 * arriba de la tabla), esta ruta/vista queda retirada. tecnicoPreview()/
 * simulatePay()/equipoPreview() muestran cuota y PAGO PROYECTADO — datos de
 * nómina — y no tenían NINGÚN scoping por colaborador (solo el permiso
 * general talento.dashboard.view, que TECNICO tiene directo): cualquier
 * técnico podía elegir a CUALQUIER otro colaborador del selector (roster
 * completo) y ver su pago proyectado. Se cierra con el mismo criterio de
 * siempre: uno mismo, su supervisor directo, o staff.
 */
class TalentoDashboardController extends Controller
{
    public function __construct(private DashboardService $service) {}

    /** Uno mismo, su supervisor directo, o staff (talento.employees.view). */
    private function puedeVerPreviewDe($colaboradorId): bool
    {
        if (auth()->user()->can('talento.employees.view')) {
            return true;
        }
        $miPropioColaborador = Actor::for(auth()->user())->talento();
        if (! $miPropioColaborador) {
            return false;
        }
        return (string) $miPropioColaborador->id === (string) $colaboradorId
            || $miPropioColaborador->subordinados()->where('id', $colaboradorId)->exists();
    }

    public function infoCards()
    {
        $this->authorize('talento.dashboard.view');
        return response()->json($this->service->infoCards());
    }

    public function dailyProduction(Request $request)
    {
        $this->authorize('talento.dashboard.view');
        $days = (int)($request->days ?? 30);
        $colId = $request->colaborador_id ? (int)$request->colaborador_id : null;
        return response()->json($this->service->dailyProduction($days, $colId));
    }

    public function tecnicoPreview(Request $request, int $colaboradorId)
    {
        abort_unless($this->puedeVerPreviewDe($colaboradorId), 403);
        return response()->json(
            $this->service->tecnicoPreview($colaboradorId, $request->period_start, $request->period_end)
        );
    }

    public function simulatePay(Request $request)
    {
        $request->validate([
            'colaborador_id'    => 'required|integer',
            'hypothetical_units'=> 'required|integer|min:0',
        ]);
        abort_unless($this->puedeVerPreviewDe((int) $request->colaborador_id), 403);
        return response()->json(
            $this->service->simulatePay((int)$request->colaborador_id, (int)$request->hypothetical_units)
        );
    }

    public function equipoPreview(int $supervisorColaboradorId)
    {
        // Aquí el "objetivo" es el propio supervisor (se muestra SU
        // equipo) — no aplica esSuSupervisor (nadie es supervisor de un
        // supervisor por transitividad aquí), es literalmente "verse a
        // uno mismo" o staff.
        if (! auth()->user()->can('talento.employees.view')) {
            $miPropioColaborador = Actor::for(auth()->user())->talento();
            abort_unless(
                $miPropioColaborador && (string) $miPropioColaborador->id === (string) $supervisorColaboradorId,
                403
            );
        }
        return response()->json($this->service->equipoPreview($supervisorColaboradorId));
    }
}
