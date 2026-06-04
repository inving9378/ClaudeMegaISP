<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoResponsibilityWindow;
use App\Modules\Addons\Talento\Services\WarrantyWindowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TalentoWarrantyController extends Controller
{
    public function __construct(private WarrantyWindowService $service) {}

    /** Check warranty classification for a client (for UI) */
    public function classify(Request $request)
    {
        $this->authorize('talento.warranty.view');

        $data = $request->validate(['client_id' => 'required|integer|exists:clients,id']);

        $result = $this->service->classifyWarrantyOrder($data['client_id']);

        return response()->json([
            'is_billable'     => $result['is_billable'],
            'points'          => $result['points'],
            'type_id'         => $result['type_id'],
            'window'          => $result['window'],
            'window_expires'  => $result['window']?->expires_at,
        ]);
    }

    /** List active windows (admin) */
    public function data(Request $request)
    {
        $this->authorize('talento.warranty.view');

        $q = TalentoResponsibilityWindow::with(['colaborador.user'])
            ->when($request->colaborador_id, fn($q, $v) => $q->where('colaborador_id', $v))
            ->when($request->client_id,      fn($q, $v) => $q->where('client_id', $v))
            ->when($request->active_only,    fn($q) => $q->where('active', true)->where('expires_at', '>', now()))
            ->orderBy('expires_at', 'desc')
            ->paginate($request->per_page ?? 25);

        return response()->json($q);
    }

    /** Override: reclassify non-billable warranty to billable */
    public function override(Request $request, $workOrderId)
    {
        $this->authorize('talento.warranty.override');

        $data = $request->validate([
            'reason'                   => 'required|string|max:500',
            'evidence_ref'             => 'nullable|string|max:255',
            'responsibility_window_id' => 'nullable|integer',
        ]);

        $this->service->override(
            (int)$workOrderId,
            $data['responsibility_window_id'] ?? null,
            $data['reason'],
            $data['evidence_ref'] ?? null
        );

        return response()->json(['ok' => true]);
    }

    /** Override history for an order */
    public function overrides($workOrderId)
    {
        $this->authorize('talento.warranty.view');

        $rows = DB::table('talento_warranty_overrides')
            ->where('work_order_id', $workOrderId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($rows);
    }
}
