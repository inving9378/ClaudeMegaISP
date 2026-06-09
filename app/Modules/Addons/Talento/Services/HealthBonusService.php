<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\Task;
use App\Modules\Addons\Talento\Models\TalentoCajaBaseline;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoHealthBonusLog;
use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthBonusService
{
    /**
     * Evaluate and potentially award the health bonus for a validated, billable work order.
     *
     * Loss is calculated as:  loss = baseline_power_dbm - client_power_dbm
     * (both are negative dBm values; a higher-magnitude client signal = worse = positive loss)
     *
     * Examples with baseline = -21.00:
     *   client -21.8  → loss = -21.0 - (-21.8) = +0.8 dB  ≤ 1.0  → BONUS ✓
     *   client -22.5  → loss = -21.0 - (-22.5) = +1.5 dB  > 1.0  → NO BONUS
     *   client -20.5  → loss = -21.0 - (-20.5) = -0.5 dB  ≤ 1.0  → BONUS ✓
     *
     * Non-billable orders are evaluated (logged) but never awarded.
     */
    public function evaluate(int $workOrderId): TalentoHealthBonusLog
    {
        $order = TalentoWorkOrder::with('colaborador')->findOrFail($workOrderId);

        $bonusAmount = (float) (DB::table('settings')->where('key', 'talento_health_bonus_amount')->value('value') ?? 30);
        $maxLossDb   = (float) (DB::table('settings')->where('key', 'talento_health_bonus_max_loss_db')->value('value') ?? 1.0);

        // Resolve caja_ref from the order
        $cajaRef     = $this->resolveCajaRef($order);
        $baseline    = $cajaRef ? TalentoCajaBaseline::latestFor($cajaRef) : null;
        $baselinePwr = $baseline ? (float)$baseline->baseline_power_dbm : null;

        // Resolve client power reading
        [$clientPwr, $powerSource] = $this->resolveClientPower($order);

        // If baseline or client power unknown → log as skipped, no bonus
        if ($baselinePwr === null || $clientPwr === null) {
            return $this->logResult($order, $cajaRef, $baselinePwr, $clientPwr, null,
                $maxLossDb, false, 0, $powerSource,
                $baselinePwr === null ? 'Sin baseline de caja' : 'Sin señal del cliente'
            );
        }

        $lossDb     = round($baselinePwr - $clientPwr, 2); // negative OK (client stronger than baseline)
        $qualifies  = $lossDb <= $maxLossDb;
        $awarded    = $qualifies && $order->is_billable;

        $log = $this->logResult(
            $order, $cajaRef, $baselinePwr, $clientPwr, $lossDb,
            $maxLossDb, $awarded, $awarded ? $bonusAmount : 0.0,
            $powerSource,
            !$qualifies ? "Pérdida {$lossDb} dB > máximo {$maxLossDb} dB" :
                (!$order->is_billable ? 'Orden no pagable — bono no aplica' : null)
        );

        // Write ledger entry only when actually awarded
        if ($awarded) {
            $periodStart = $order->validated_at
                ? $order->validated_at->copy()->startOfWeek(\Carbon\Carbon::SATURDAY)->toDateString()
                : now()->startOfWeek(\Carbon\Carbon::SATURDAY)->toDateString();
            $periodEnd = \Carbon\Carbon::parse($periodStart)->addDays(6)->toDateString();

            TalentoLedgerEntry::create([
                'colaborador_id' => $order->colaborador_id,
                'type'           => 'credit',
                'concept'        => 'bonus',
                'amount'         => $bonusAmount,
                'reference_type' => TalentoWorkOrder::class,
                'reference_id'   => $order->id,
                'period_start'   => $periodStart,
                'period_end'     => $periodEnd,
                'notes'          => "Bono salud red — pérdida {$lossDb} dB (caja {$cajaRef})",
                'created_by'     => auth()->id(),
            ]);
        }

        return $log;
    }

    /**
     * Evaluate and potentially award the health bonus for a validated task (tipo=campo).
     * Same business logic as evaluate() but resolves fields from Task instead of TalentoWorkOrder.
     */
    public function evaluateTask(Task $task): TalentoHealthBonusLog
    {
        // Resolve colaborador via task_user pivot
        $userId      = $task->users()->value('users.id');
        $colaborador = $userId ? TalentoColaborador::where('user_id', $userId)->first() : null;

        if (! $colaborador) {
            throw new \RuntimeException("Task {$task->id} sin colaborador vinculado; bono salud omitido.");
        }

        $bonusAmount = (float) (DB::table('settings')->where('key', 'talento_health_bonus_amount')->value('value') ?? 30);
        $maxLossDb   = (float) (DB::table('settings')->where('key', 'talento_health_bonus_max_loss_db')->value('value') ?? 1.0);

        // Resolve client_id via client_main_information
        $clientId = $task->client_main_information_id
            ? DB::table('client_main_information')->where('id', $task->client_main_information_id)->value('client_id')
            : null;

        // Resolve caja_ref: olt_onu → odb_name, fallback client ONU
        $cajaRef = null;
        if ($task->olt_onu_id) {
            $odb = DB::table('olt_onus')->where('id', $task->olt_onu_id)->value('odb_name');
            $cajaRef = ($odb !== null && $odb !== '') ? (string)$odb : null;
        }
        if (! $cajaRef && $clientId) {
            $odb = DB::table('olt_onus')->where('client_id', $clientId)
                ->whereNotNull('odb_name')->where('odb_name', '!=', '')->value('odb_name');
            $cajaRef = $odb ? (string)$odb : null;
        }

        $baseline    = $cajaRef ? TalentoCajaBaseline::latestFor($cajaRef) : null;
        $baselinePwr = $baseline ? (float)$baseline->baseline_power_dbm : null;

        // Resolve client power: olt_onu signal → client additional → none
        $clientPwr   = null;
        $powerSource = 'none';
        if ($task->olt_onu_id) {
            $sig = DB::table('olt_onus')->where('id', $task->olt_onu_id)
                ->select('signal_1310', 'signal_1490')->first();
            if ($sig) {
                $val = $sig->signal_1310 ?? $sig->signal_1490;
                if ($val !== null && is_numeric($val) && (float)$val < 0) {
                    $clientPwr = (float)$val; $powerSource = 'olt_onu';
                }
            }
        }
        if ($clientPwr === null && $clientId) {
            $sig = DB::table('olt_onus')->where('client_id', $clientId)
                ->whereNotNull('signal_1310')->orderBy('last_synced_at', 'desc')->first();
            if ($sig && is_numeric($sig->signal_1310) && (float)$sig->signal_1310 < 0) {
                $clientPwr = (float)$sig->signal_1310; $powerSource = 'olt_onu';
            }
        }
        if ($clientPwr === null && $clientId) {
            $raw = DB::table('client_additional_information')->where('client_id', $clientId)->value('power_dbm');
            if ($raw !== null) {
                $val = (float)$raw;
                if ($val < 0) { $clientPwr = $val; $powerSource = 'client_additional'; }
            }
        }

        if ($baselinePwr === null || $clientPwr === null) {
            return TalentoHealthBonusLog::create([
                'tarea_id'           => $task->id,
                'colaborador_id'     => $colaborador->id,
                'caja_ref'           => $cajaRef,
                'baseline_power_dbm' => $baselinePwr,
                'client_power_dbm'   => $clientPwr,
                'loss_db'            => null,
                'max_loss_db'        => $maxLossDb,
                'bonus_awarded'      => false,
                'bonus_amount'       => 0,
                'power_source'       => $powerSource,
                'skip_reason'        => $baselinePwr === null ? 'Sin baseline de caja' : 'Sin señal del cliente',
            ]);
        }

        $lossDb    = round($baselinePwr - $clientPwr, 2);
        $qualifies = $lossDb <= $maxLossDb;
        $awarded   = $qualifies && (bool)$task->is_billable;

        $log = TalentoHealthBonusLog::create([
            'tarea_id'           => $task->id,
            'colaborador_id'     => $colaborador->id,
            'caja_ref'           => $cajaRef,
            'baseline_power_dbm' => $baselinePwr,
            'client_power_dbm'   => $clientPwr,
            'loss_db'            => $lossDb,
            'max_loss_db'        => $maxLossDb,
            'bonus_awarded'      => $awarded,
            'bonus_amount'       => $awarded ? $bonusAmount : 0.0,
            'power_source'       => $powerSource,
            'skip_reason'        => ! $qualifies
                ? "Pérdida {$lossDb} dB > máximo {$maxLossDb} dB"
                : (! $task->is_billable ? 'Orden no pagable — bono no aplica' : null),
        ]);

        if ($awarded) {
            $periodStart = $task->validated_at
                ? \Carbon\Carbon::parse($task->validated_at)->startOfWeek(\Carbon\Carbon::SATURDAY)->toDateString()
                : now()->startOfWeek(\Carbon\Carbon::SATURDAY)->toDateString();
            $periodEnd = \Carbon\Carbon::parse($periodStart)->addDays(6)->toDateString();

            TalentoLedgerEntry::create([
                'colaborador_id' => $colaborador->id,
                'type'           => 'credit',
                'concept'        => 'bonus',
                'amount'         => $bonusAmount,
                'reference_type' => Task::class,
                'reference_id'   => $task->id,
                'period_start'   => $periodStart,
                'period_end'     => $periodEnd,
                'notes'          => "Bono salud red — pérdida {$lossDb} dB (caja {$cajaRef})",
                'created_by'     => auth()->id(),
            ]);
        }

        return $log;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function resolveCajaRef(TalentoWorkOrder $order): ?string
    {
        // 1. Order has a caja_id pointing to talento_caja_baselines
        if ($order->caja_id) {
            return DB::table('talento_caja_baselines')->where('id', $order->caja_id)->value('caja_ref');
        }
        // 2. Order has olt_onu_id → use odb_name as caja_ref
        if ($order->olt_onu_id) {
            $odb = DB::table('olt_onus')->where('id', $order->olt_onu_id)->value('odb_name');
            return ($odb !== null && $odb !== '') ? (string)$odb : null;
        }
        // 3. Client has a linked ONU
        if ($order->client_id) {
            $odb = DB::table('olt_onus')->where('client_id', $order->client_id)->whereNotNull('odb_name')
                ->where('odb_name', '!=', '')->value('odb_name');
            return $odb ? (string)$odb : null;
        }
        return null;
    }

    /** Returns [float|null $powerDbm, string $source] */
    private function resolveClientPower(TalentoWorkOrder $order): array
    {
        // 1. olt_onus.signal_1310 (most reliable — from SmartOLT sync)
        if ($order->olt_onu_id) {
            $sig = DB::table('olt_onus')->where('id', $order->olt_onu_id)
                ->select('signal_1310', 'signal_1490')
                ->first();
            if ($sig) {
                $val = $sig->signal_1310 ?? $sig->signal_1490;
                if ($val !== null && is_numeric($val) && (float)$val < 0) {
                    return [(float)$val, 'olt_onu'];
                }
            }
        }
        // 2. Client's linked ONU
        if ($order->client_id) {
            $sig = DB::table('olt_onus')->where('client_id', $order->client_id)
                ->whereNotNull('signal_1310')->orderBy('last_synced_at', 'desc')->first();
            if ($sig && is_numeric($sig->signal_1310) && (float)$sig->signal_1310 < 0) {
                return [(float)$sig->signal_1310, 'olt_onu'];
            }
        }
        // 3. client_additional_information.power_dbm
        if ($order->client_id) {
            $raw = DB::table('client_additional_information')
                ->where('client_id', $order->client_id)->value('power_dbm');
            if ($raw !== null) {
                $val = (float)$raw;
                if ($val < 0) {
                    return [$val, 'client_additional'];
                }
            }
        }
        return [null, 'none'];
    }

    private function logResult(
        TalentoWorkOrder $order,
        ?string $cajaRef,
        ?float $baseline,
        ?float $clientPwr,
        ?float $lossDb,
        float $maxLossDb,
        bool $awarded,
        float $amount,
        ?string $source,
        ?string $skipReason
    ): TalentoHealthBonusLog {
        return TalentoHealthBonusLog::create([
            'work_order_id'      => $order->id,
            'colaborador_id'     => $order->colaborador_id,
            'caja_ref'           => $cajaRef,
            'baseline_power_dbm' => $baseline,
            'client_power_dbm'   => $clientPwr,
            'loss_db'            => $lossDb,
            'max_loss_db'        => $maxLossDb,
            'bonus_awarded'      => $awarded,
            'bonus_amount'       => $amount,
            'power_source'       => $source,
            'skip_reason'        => $skipReason,
        ]);
    }
}
