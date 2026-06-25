<?php

namespace App\Jobs\Referrals;

use App\Events\Referrals\ReferralCommissionGenerated;
use App\Events\Referrals\ReferralThresholdCovered;
use App\Modules\Core\Clientes\Models\ClientInvoice;
use App\Models\Referrals\ClientReferralProfile;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralCommission;
use App\Models\Referrals\ReferralCommissionTier;
use App\Models\Referrals\ReferralReward;
use App\Models\Referrals\ReferralSetting;
use App\Modules\Core\Clientes\Models\ClientInternetService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Jobs\Referrals\Concerns\MeasuresPerformance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessReferralCommissions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MeasuresPerformance;

    public function __construct(private ClientInvoice $invoice) {}

    public function handle(): void
    {
        $this->startMeasure();
        $settings = ReferralSetting::current();

        if (!$settings->program_active) {
            $this->recordSuccess(['skipped' => 'program_inactive']);
            return;
        }

        $eventsToFire = [];

        DB::transaction(function () use ($settings, &$eventsToFire) {
            $referral = Referral::where('referred_client_id', $this->invoice->client_id)
                ->whereIn('status', ['pending_threshold', 'active'])
                ->lockForUpdate()
                ->first();

            if (!$referral) {
                return;
            }

            // Acumular el pago al umbral del referido (R2)
            $referral->referred_threshold_amount_paid += (float) $this->invoice->total;

            $justActivated = false;
            if ($referral->status === 'pending_threshold'
                && $referral->referred_threshold_amount_paid >= (float) $settings->threshold_amount
            ) {
                $referral->status                        = 'active';
                $referral->referred_threshold_covered_at = now();
                $referral->commission_window_start       = now();
                $justActivated                           = true;
            }

            if ($referral->status !== 'active') {
                $referral->save();
                return;
            }

            $embajadorProfile = ClientReferralProfile::where('client_id', $referral->embajador_id)
                ->where('is_eligible', true)
                ->first();

            if (!$embajadorProfile || !$embajadorProfile->plan_type) {
                $referral->save();
                return;
            }

            if ($embajadorProfile->plan_type === 'single_reward') {
                $this->processPlanA($referral, $embajadorProfile, $justActivated);
            } else {
                $this->processPlanB($referral, $embajadorProfile, $settings, $eventsToFire);
            }

            if ($justActivated) {
                $eventsToFire[] = new ReferralThresholdCovered($referral);
            }

            $referral->save();
        });

        foreach ($eventsToFire as $evt) {
            try {
                event($evt);
            } catch (\Throwable $e) {
                Log::error('ProcessReferralCommissions: error al disparar evento', [
                    'event' => get_class($evt),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->countProcessed(1);
        $this->recordSuccess(['invoice_id' => $this->invoice->id, 'client_id' => $this->invoice->client_id]);
    }

    // ── Plan A: mensualidad gratis única cuando el referido cruza el umbral ──

    private function processPlanA(Referral $referral, ClientReferralProfile $embajadorProfile, bool $justActivated): void
    {
        if (!$justActivated) {
            return;
        }

        // Idempotencia: no crear si ya existe una reward para este referral
        $exists = ReferralReward::where('referral_id', $referral->id)->exists();
        if ($exists) {
            return;
        }

        $planValue = $this->getEmbajadorPlanValue($referral->embajador_id);

        ReferralReward::create([
            'embajador_id'        => $referral->embajador_id,
            'referral_id'         => $referral->id,
            'type'                => 'free_month',
            'plan_value_snapshot' => $planValue,
            'status'              => 'available',
            'available_at'        => now(),
            'expires_at'          => now()->addMonths(12),
        ]);

        // total_rewards_earned lo recomputa ReferralCommissionObserver / ReferralObserver.
        $referral->status = 'completed';
    }

    // ── Plan B: comisión por pago, hasta 5 niveles, máximo 12 pagos (R4) ──

    private function processPlanB(Referral $referral, ClientReferralProfile $embajadorProfile, ReferralSetting $settings, array &$eventsToFire): void
    {
        if ($referral->commissions_paid_count >= 12) {
            $referral->status = 'completed';
            return;
        }

        $speedMbps = $this->getClientSpeedMbps($this->invoice->client_id);
        $tier      = $speedMbps ? ReferralCommissionTier::resolveForSpeedMbps($speedMbps) : null;

        if (!$tier) {
            Log::warning('ProcessReferralCommissions: sin tier para cliente', [
                'client_id'  => $this->invoice->client_id,
                'invoice_id' => $this->invoice->id,
                'speed_mbps' => $speedMbps,
            ]);
            return;
        }

        $chain = $referral->getUpstreamChain($settings->max_levels);
        [$period_month, $period_year] = $this->resolveInvoicePeriod();
        $commissionCreated = false;

        foreach ($chain as $index => $beneficiaryClientId) {
            $level = $index + 1;
            $rate  = $tier->getRateForLevel($level);

            if ($rate <= 0) {
                continue;
            }

            $beneficiaryProfile = ClientReferralProfile::where('client_id', $beneficiaryClientId)
                ->where('is_eligible', true)
                ->where('plan_type', 'multilevel')
                ->first();

            if (!$beneficiaryProfile) {
                continue;
            }

            $baseAmount       = (float) $this->invoice->total;
            $commissionAmount = round($baseAmount * $rate / 100, 2);

            $commission = ReferralCommission::firstOrNew([
                'invoice_id'     => $this->invoice->id,
                'beneficiary_id' => $beneficiaryClientId,
                'level'          => $level,
            ]);

            if (!$commission->exists) {
                $commission->fill([
                    'referral_id'       => $referral->id,
                    'commission_pct'    => $rate,
                    'base_amount'       => $baseAmount,
                    'commission_amount' => $commissionAmount,
                    'period_month'      => $period_month,
                    'period_year'       => $period_year,
                    'status'            => 'approved',
                    'apply_after_at'    => now()->addDays(15),
                ])->save();

                // total_commissions_earned lo recomputa ReferralCommissionObserver.
                $commissionCreated = true;
                $eventsToFire[]    = new ReferralCommissionGenerated($commission);
            } elseif ($commission->status === 'cancelled') {
                // Refund fue revertido: el cliente volvió a pagar esta misma factura
                $commission->update([
                    'status'         => 'approved',
                    'apply_after_at' => now()->addDays(15),
                    'applied_at'     => null,
                ]);
                $commissionCreated = true;
                $eventsToFire[]    = new ReferralCommissionGenerated($commission);
            }
            // Else: ya existe y está activa → idempotencia, no hacer nada
        }

        if ($commissionCreated) {
            $referral->commissions_paid_count++;
            if ($referral->commissions_paid_count >= 12) {
                $referral->status = 'completed';
            }
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function getClientSpeedMbps(int $clientId): ?float
    {
        $service = ClientInternetService::where('client_id', $clientId)
            ->where('estado', ClientInternetService::STATE_ACTIVE)
            ->with('internet')
            ->first();

        if (!$service || !$service->internet) {
            return null;
        }

        $kbps = (float) $service->internet->download_speed;
        return $kbps > 0 ? round($kbps / 1024, 2) : null;
    }

    private function getEmbajadorPlanValue(int $embajadorId): float
    {
        $service = ClientInternetService::where('client_id', $embajadorId)
            ->where('estado', ClientInternetService::STATE_ACTIVE)
            ->with('internet')
            ->first();

        return (float) ($service?->internet?->price ?? 0);
    }

    private function resolveInvoicePeriod(): array
    {
        try {
            $date = Carbon::parse($this->invoice->document_date ?: $this->invoice->created_at);
            return [$date->month, $date->year];
        } catch (\Throwable) {
            return [now()->month, now()->year];
        }
    }
}
