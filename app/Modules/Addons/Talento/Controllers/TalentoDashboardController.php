<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Services\DashboardService;
use Illuminate\Http\Request;

class TalentoDashboardController extends Controller
{
    public function __construct(private DashboardService $service) {}

    public function index()
    {
        $this->authorize('talento.dashboard.view');
        return view('addon-talento::talento.dashboard');
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
        $this->authorize('talento.dashboard.view');
        return response()->json(
            $this->service->tecnicoPreview($colaboradorId, $request->period_start, $request->period_end)
        );
    }

    public function simulatePay(Request $request)
    {
        $this->authorize('talento.dashboard.view');
        $request->validate([
            'colaborador_id'    => 'required|integer',
            'hypothetical_units'=> 'required|integer|min:0',
        ]);
        return response()->json(
            $this->service->simulatePay((int)$request->colaborador_id, (int)$request->hypothetical_units)
        );
    }

    public function equipoPreview(int $supervisorColaboradorId)
    {
        $this->authorize('talento.dashboard.view');
        return response()->json($this->service->equipoPreview($supervisorColaboradorId));
    }
}
