<?php

namespace App\Jobs\Referrals;

use App\Events\Referrals\ReferralCommissionsApplied;
use App\Models\Balance;
use App\Models\Referrals\ReferralCommission;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Jobs\Referrals\Concerns\MeasuresPerformance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApplyReferralCommissions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MeasuresPerformance;

    public function __construct()
    {
        $this->onQueue('referrals');
    }

    public function handle(): void
    {
        $this->startMeasure();
        $beneficiaryIds = ReferralCommission::where('status', 'approved')
            ->whereNotNull('apply_after_at')
            ->where('apply_after_at', '<=', now())
            ->whereNull('applied_at')
            ->distinct()
            ->pluck('beneficiary_id');

        foreach ($beneficiaryIds as $beneficiaryId) {
            $credited      = false;
            $creditedTotal = 0.0;
            $creditedCount = 0;

            try {
                DB::transaction(function () use ($beneficiaryId, &$credited, &$creditedTotal, &$creditedCount) {
                    $commissions = ReferralCommission::where('beneficiary_id', $beneficiaryId)
                        ->where('status', 'approved')
                        ->whereNotNull('apply_after_at')
                        ->where('apply_after_at', '<=', now())
                        ->whereNull('applied_at')
                        ->lockForUpdate()
                        ->get();

                    if ($commissions->isEmpty()) {
                        return;
                    }

                    $total = round($commissions->sum('commission_amount'), 2);

                    $balance = Balance::where('balanceable_id', $beneficiaryId)
                        ->where('balanceable_type', 'App\Models\Client')
                        ->lockForUpdate()
                        ->first();

                    if (!$balance) {
                        Log::warning('ApplyReferralCommissions: sin balance para cliente', [
                            'client_id' => $beneficiaryId,
                            'total'     => $total,
                        ]);
                        return;
                    }

                    // Usar raw query para evitar que el observer ClientBalanceObserver::updating()
                    // evalúe lógica de periodo de gracia (solo relevante para saldos negativos).
                    DB::table('balances')->where('id', $balance->id)->increment('amount', $total);

                    $commissions->each(fn($c) => $c->update([
                        'status'     => 'applied',
                        'applied_at' => now(),
                    ]));

                    $credited      = true;
                    $creditedTotal = $total;
                    $creditedCount = $commissions->count();
                    $this->countProcessed($creditedCount);
                });
            } catch (\Throwable $e) {
                Log::error('ApplyReferralCommissions: error al acreditar beneficiario', [
                    'beneficiary_id' => $beneficiaryId,
                    'error'          => $e->getMessage(),
                ]);
            }

            if ($credited) {
                try {
                    $embajador = Client::find($beneficiaryId);
                    if ($embajador) {
                        event(new ReferralCommissionsApplied($embajador, $creditedTotal, $creditedCount));
                    }
                } catch (\Throwable $e) {
                    Log::error('ApplyReferralCommissions: error al disparar ReferralCommissionsApplied', [
                        'beneficiary_id' => $beneficiaryId,
                        'error'          => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->recordSuccess(['beneficiaries' => $beneficiaryIds->count()]);
    }
}
