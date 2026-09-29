<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoCajaBaseline;
use App\Modules\Addons\Talento\Models\TalentoHealthBonusLog;
use App\Modules\Addons\Talento\Services\HealthBonusService;
use App\Modules\Addons\Talento\Support\Actor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TalentoCajaController extends Controller
{
    /** Mismos 3 roles que ya usa esTecnico() en TalentoColaboradorFicha.vue. */
    private const ROLES_TECNICO = ['TECNICO', 'TECNICO_INSTALADOR', 'TECNICO_PLANTA'];

    public function __construct(private HealthBonusService $bonusService) {}

    /**
     * "Guardar settings" (política del bono: monto/umbral) — admin/
     * DESARROLLADOR, o CUALQUIER supervisor (tiene al menos un subordinado
     * directo — David, 28-sep: a diferencia de "registrar baseline", esto
     * SÍ es una decisión, no una lectura de campo, y no está atada a un
     * colaborador puntual como para exigir que sea "su" supervisor).
     */
    private function puedeGestionarSettings(): bool
    {
        if (auth()->user()->can('talento.caja.manage')) {
            return true;
        }

        return (bool) Actor::for(auth()->user())->talento()?->subordinados()->exists();
    }

    /**
     * Ver el log de bono de salud de un colaborador puntual: uno mismo,
     * su supervisor directo, o staff — mismo criterio que el resto del
     * módulo. NO se usa talento.health_bonus.view aquí a propósito: la
     * tienen TECNICO/TECNICO_PLANTA/TECNICO_INSTALADOR directo (para ver
     * el log de bono en su propia ficha), y sin este scoping esa misma
     * permission abría el historial de bonos de CUALQUIER otro
     * colaborador (qué OT, cuánta pérdida, si ganó bono y cuánto).
     */
    private function puedeVerBonusLogDe($colaboradorId): bool
    {
        if (auth()->user()->can('talento.employees.view')) {
            return true;
        }
        $miPropioColaborador = Actor::for(auth()->user())->talento();
        if (! $miPropioColaborador) return false;
        return (string) $miPropioColaborador->id === (string) $colaboradorId
            || $miPropioColaborador->subordinados()->where('id', $colaboradorId)->exists();
    }

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
        // David (28-sep, corrigiendo su propio pedido anterior): "registrar
        // baseline" es la LECTURA REAL de dBm que el técnico toma en campo
        // con su medidor, parado frente a la caja — nadie más tiene ese
        // dato. A diferencia de "Guardar settings" (política del bono, esa
        // SÍ solo admin/supervisor), esto lo puede hacer cualquier técnico
        // activo además de quien tenga el permiso general.
        if (! auth()->user()->can('talento.caja.manage')) {
            $miColaborador = Actor::for(auth()->user())->talento();
            $esTecnico = $miColaborador && !empty(array_intersect(
                $miColaborador->user?->getRoleNames()->toArray() ?? [],
                self::ROLES_TECNICO
            ));
            abort_unless($esTecnico, 403, 'No tienes permiso para registrar baselines de cajas.');
        }

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
        // Sin filtro por colaborador_id = listado global → exige el
        // permiso de STAFF de verdad. Con filtro = uno mismo, supervisor
        // directo también pueden verlo.
        if ($request->filled('colaborador_id')) {
            abort_unless($this->puedeVerBonusLogDe($request->colaborador_id), 403);
        } else {
            $this->authorize('talento.employees.view');
        }

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
        // Sin authorize('talento.caja.view') puro: un técnico viendo su
        // propia ficha (pestaña Cajas ODB, lectura) no lo tiene como
        // permiso Spatie directo — basta con talento.view (ya exigido por
        // el middleware de ruta) para leer; puede_gestionar en la
        // respuesta es lo que decide si el frontend muestra el botón de
        // guardar.
        return response()->json([
            'health_bonus_amount'    => (float)(DB::table('settings')->where('key','talento_health_bonus_amount')->value('value') ?? 30),
            'health_bonus_max_loss_db' => (float)(DB::table('settings')->where('key','talento_health_bonus_max_loss_db')->value('value') ?? 1.0),
            'puede_gestionar' => $this->puedeGestionarSettings(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        abort_unless($this->puedeGestionarSettings(), 403);

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
