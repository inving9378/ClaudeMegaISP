<?php

namespace App\Modules\Addons\Flotas\Services;

use App\Modules\Addons\Flotas\Models\FleetSubscription;
use App\Modules\Addons\Flotas\Models\FleetSubscriptionEvent;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Support\FleetPlans;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de negocio de las suscripciones SaaS de Flotas.
 *
 * Ciclo: trial → (active | expired) ; active ↔ past_due ; * → cancelled.
 * Trial vencido sin pago = expired; datos retenidos 90 días; reactivable siempre.
 */
class FleetSubscriptionService
{
    /** Umbrales (días antes del fin de trial) en los que se notifica. */
    public const TRIAL_REMINDER_DAYS = [30, 15, 7, 1];

    /** Días que se retienen los datos tras expirar el trial sin pago. */
    public const DATA_RETENTION_DAYS = 90;

    // ── Lectura ─────────────────────────────────────────────────────────────────

    public function catalog(): array
    {
        return FleetPlans::all();
    }

    public function forClient(int $clientId): ?FleetSubscription
    {
        return FleetSubscription::forClient($clientId)->latest('id')->first();
    }

    /** ¿El cliente puede usar el módulo de flotas ahora? */
    public function clientHasAccess(int $clientId): bool
    {
        $sub = $this->forClient($clientId);
        return $sub ? $sub->isUsable() : false;
    }

    // ── Alta / cambio de plan ─────────────────────────────────────────────────────

    /** Inicia (o reactiva en trial) la suscripción de un cliente. */
    public function startTrial(int $clientId, string $plan): FleetSubscription
    {
        if (! FleetPlans::exists($plan)) {
            throw new \InvalidArgumentException("Plan inválido: {$plan}");
        }

        $existing = $this->forClient($clientId);
        if ($existing && $existing->isUsable()) {
            // Ya tiene acceso: tratar como cambio de plan en lugar de duplicar.
            return $this->changePlan($existing, $plan);
        }

        $trialDays = FleetPlans::trialDays($plan);
        $now       = now();

        $sub = new FleetSubscription();
        $sub->forceFill([
            'client_id'         => $clientId,
            'plan'              => $plan,
            'status'            => 'trial',
            'trial_starts_at'   => $now,
            'trial_ends_at'     => $now->copy()->addDays($trialDays),
            'price_per_vehicle' => FleetPlans::pricePerVehicle($plan),
            'auto_renew'        => true,
        ]);
        $sub->save();

        $this->syncVehicleCount($sub);
        $this->logEvent($sub, $existing ? 'reactivated' : 'trial_started', [
            'plan'       => $plan,
            'trial_days' => $trialDays,
            'trial_ends' => $sub->trial_ends_at?->toDateString(),
        ]);

        return $sub->fresh();
    }

    public function changePlan(FleetSubscription $sub, string $plan): FleetSubscription
    {
        if (! FleetPlans::exists($plan)) {
            throw new \InvalidArgumentException("Plan inválido: {$plan}");
        }
        if ($sub->plan === $plan) {
            return $sub;
        }

        $from = $sub->plan;
        $sub->plan              = $plan;
        $sub->price_per_vehicle = FleetPlans::pricePerVehicle($plan);
        $sub->save();

        $this->syncVehicleCount($sub);
        $this->logEvent($sub, 'plan_changed', ['from' => $from, 'to' => $plan]);

        return $sub->fresh();
    }

    /** Recalcula vehicles_count y monthly_price desde la flota real del cliente. */
    public function syncVehicleCount(FleetSubscription $sub): FleetSubscription
    {
        $count = FleetVehicle::forClient($sub->client_id)->count();
        $price = round($count * (float) $sub->price_per_vehicle, 2);

        if ($count !== (int) $sub->vehicles_count || abs($price - (float) $sub->monthly_price) > 0.001) {
            $previous = (int) $sub->vehicles_count;
            $sub->vehicles_count = $count;
            $sub->monthly_price  = $price;
            $sub->save();

            $this->logEvent($sub, 'vehicles_changed', [
                'from'          => $previous,
                'to'            => $count,
                'monthly_price' => $price,
            ]);
        }

        return $sub;
    }

    // ── Pago / facturación ────────────────────────────────────────────────────────

    /** Convierte el trial en suscripción pagada (tras configurar método de pago). */
    public function activate(FleetSubscription $sub): FleetSubscription
    {
        $this->syncVehicleCount($sub);

        $sub->status            = 'active';
        $sub->started_at        = $sub->started_at ?? now();
        $sub->next_billing_date = now()->addMonthNoOverflow()->startOfDay();
        $sub->data_retention_until = null;
        $sub->save();

        $this->logEvent($sub, 'activated', [
            'plan'          => $sub->plan,
            'monthly_price' => (float) $sub->monthly_price,
            'next_billing'  => $sub->next_billing_date?->toDateString(),
        ]);

        return $sub->fresh();
    }

    /**
     * Marca un ciclo facturado y avanza la fecha de cobro un mes.
     * (La generación del cargo real en el módulo Pagos se engancha aquí en una sub-tarea posterior.)
     */
    public function markBilled(FleetSubscription $sub): FleetSubscription
    {
        $this->syncVehicleCount($sub);

        $sub->status            = 'active';
        $sub->last_billed_at    = now()->startOfDay();
        $sub->next_billing_date = ($sub->next_billing_date ? Carbon::parse($sub->next_billing_date) : now())
            ->addMonthNoOverflow()->startOfDay();
        $sub->save();

        $this->logEvent($sub, 'billed', [
            'amount'       => (float) $sub->monthly_price,
            'vehicles'     => (int) $sub->vehicles_count,
            'next_billing' => $sub->next_billing_date?->toDateString(),
        ]);

        return $sub->fresh();
    }

    // ── Cancelación / expiración / reactivación ─────────────────────────────────────

    public function cancel(FleetSubscription $sub, ?string $reason = null): FleetSubscription
    {
        $sub->status        = 'cancelled';
        $sub->cancelled_at  = now();
        $sub->cancel_reason = $reason;
        $sub->auto_renew    = false;
        $sub->data_retention_until = now()->addDays(self::DATA_RETENTION_DAYS)->startOfDay();
        $sub->save();

        $this->logEvent($sub, 'cancelled', ['reason' => $reason]);

        return $sub->fresh();
    }

    public function expire(FleetSubscription $sub): FleetSubscription
    {
        $sub->status = 'expired';
        $sub->data_retention_until = now()->addDays(self::DATA_RETENTION_DAYS)->startOfDay();
        $sub->save();

        $this->logEvent($sub, 'expired', [
            'trial_ended' => $sub->trial_ends_at?->toDateString(),
        ]);

        return $sub->fresh();
    }

    // ── Mantenimiento diario (cron) ────────────────────────────────────────────────

    /**
     * Recorre suscripciones y aplica: recordatorios de trial, expiración de trials
     * vencidos y avance de la fecha de facturación. Devuelve un resumen.
     */
    public function runDailyMaintenance(): array
    {
        $summary = ['reminders' => 0, 'expired' => 0, 'due_for_billing' => 0];

        // 1. Trials: recordatorios + expiración
        FleetSubscription::where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->chunkById(200, function ($subs) use (&$summary) {
                foreach ($subs as $sub) {
                    $daysLeft = (int) ceil(now()->floatDiffInDays(Carbon::parse($sub->trial_ends_at), false));

                    if ($daysLeft <= 0) {
                        $this->expire($sub);
                        $summary['expired']++;
                        continue;
                    }

                    $threshold = $this->reminderThresholdFor($daysLeft);
                    if ($threshold !== null && (int) $sub->trial_notified_days !== $threshold) {
                        $sub->trial_notified_days = $threshold;
                        $sub->save();
                        $this->logEvent($sub, $threshold <= 7 ? 'trial_expiring' : 'trial_reminder', [
                            'days_left' => $daysLeft,
                            'threshold' => $threshold,
                        ]);
                        // TODO (sub-tarea): disparar notificación real (WhatsApp/email) aquí.
                        $summary['reminders']++;
                    }
                }
            });

        // 2. Activas con cobro vencido → candidatas a facturar
        $summary['due_for_billing'] = FleetSubscription::whereIn('status', ['active', 'past_due'])
            ->whereNotNull('next_billing_date')
            ->whereDate('next_billing_date', '<=', now())
            ->count();

        return $summary;
    }

    /** El mayor umbral de recordatorio que aplica para los días restantes dados. */
    private function reminderThresholdFor(int $daysLeft): ?int
    {
        foreach (self::TRIAL_REMINDER_DAYS as $t) { // ordenado desc
            if ($daysLeft <= $t) {
                return $t;
            }
        }
        return null;
    }

    // ── Bitácora ──────────────────────────────────────────────────────────────────

    public function logEvent(FleetSubscription $sub, string $type, array $metadata = []): void
    {
        FleetSubscriptionEvent::create([
            'subscription_id' => $sub->id,
            'client_id'       => $sub->client_id,
            'event_type'      => $type,
            'metadata'        => $metadata ?: null,
            'occurred_at'     => now(),
            'created_by'      => auth()->id(),
        ]);
    }
}
