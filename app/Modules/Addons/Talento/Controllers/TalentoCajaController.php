<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoCajaBaseline;
use App\Modules\Addons\Talento\Models\TalentoHealthBonusLog;
use App\Modules\Addons\Talento\Services\HealthBonusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TalentoCajaController extends Controller
{
    public function __construct(private HealthBonusService $bonusService) {}

    public function index()
    {
        $this->authorize('talento.caja.view');
        return view('addon-talento::talento.cajas');
    }

    // ── Baseline CRUD ─────────────────────────────────────────────────────────

    public function data(Request $request)
    {
        $this->authorize('talento.caja.view');

        $q = TalentoCajaBaseline::when($request->search, fn($q, $s) => $q->where('caja_ref', 'like', "%$s%"))
            ->orderBy('caja_ref')->orderBy('registered_at', 'desc')
            ->paginate($request->per_page ?? 30);

        return response()->json($q);
    }

    public function latestPerCaja()
    {
        $this->authorize('talento.caja.view');

        // One row per caja_ref (latest)
        $rows = DB::table('talento_caja_baselines as b1')
            ->whereNotExists(fn($q) =>
                $q->from('talento_caja_baselines as b2')
                    ->whereColumn('b2.caja_ref', 'b1.caja_ref')
                    ->where('b2.registered_at', '>', DB::raw('b1.registered_at'))
            )
            ->select('b1.*')
            ->orderBy('b1.caja_ref')
            ->get();

        return response()->json($rows);
    }

    public function store(Request $request)
    {
        $this->authorize('talento.caja.manage');

        $data = $request->validate([
            'caja_ref'           => 'required|string|max:60',
            'baseline_power_dbm' => 'required|numeric|between:-50,0',
            'olt_onu_id'         => 'nullable|exists:olt_onus,id',
            'registered_at'      => 'nullable|date',
            'notes'              => 'nullable|string',
        ]);

        $baseline = TalentoCajaBaseline::create(array_merge($data, [
            'registered_by' => auth()->id(),
            'registered_at' => $data['registered_at'] ?? now(),
        ]));

        return response()->json($baseline, 201);
    }

    // ── Health bonus ──────────────────────────────────────────────────────────

    public function bonusLog(Request $request)
    {
        $this->authorize('talento.health_bonus.view');

        $q = TalentoHealthBonusLog::when($request->colaborador_id, fn($q, $v) => $q->where('colaborador_id', $v))
            ->when($request->work_order_id, fn($q, $v) => $q->where('work_order_id', $v))
            ->when($request->awarded, fn($q) => $q->where('bonus_awarded', true))
            ->orderBy('checked_at', 'desc')
            ->paginate($request->per_page ?? 25);

        return response()->json($q);
    }

    public function evaluateBonus($workOrderId)
    {
        $this->authorize('talento.work_orders.validate');

        $result = $this->bonusService->evaluate((int)$workOrderId);
        return response()->json($result);
    }

    // ── Settings ──────────────────────────────────────────────────────────────

    public function getSettings()
    {
        $this->authorize('talento.caja.view');
        return response()->json([
            'health_bonus_amount'    => (float)(DB::table('settings')->where('key','talento_health_bonus_amount')->value('value') ?? 30),
            'health_bonus_max_loss_db' => (float)(DB::table('settings')->where('key','talento_health_bonus_max_loss_db')->value('value') ?? 1.0),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $this->authorize('talento.caja.manage');

        $data = $request->validate([
            'health_bonus_amount'      => 'sometimes|numeric|min:0',
            'health_bonus_max_loss_db' => 'sometimes|numeric|min:0|max:10',
        ]);

        $now = now();
        if (isset($data['health_bonus_amount'])) {
            DB::table('settings')->updateOrInsert(['key' => 'talento_health_bonus_amount'],
                ['value' => (string)$data['health_bonus_amount'], 'updated_at' => $now]);
        }
        if (isset($data['health_bonus_max_loss_db'])) {
            DB::table('settings')->updateOrInsert(['key' => 'talento_health_bonus_max_loss_db'],
                ['value' => (string)$data['health_bonus_max_loss_db'], 'updated_at' => $now]);
        }

        return $this->getSettings();
    }
}
