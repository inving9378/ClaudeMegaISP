<?php

namespace App\Modules\Addons\Flotas\Services;

use App\Modules\Addons\Flotas\Models\FleetSubscription;
use App\Modules\Addons\Flotas\Models\FleetSubscriptionEvent;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\Notifications\SubscriptionAlertDispatcher;
use App\Modules\Addons\Flotas\Support\FleetPlans;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    /** Meses gratis de la promoción de activación (#620). */
    public const PROMO_MONTHS = 3;

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
    public function startTrial(int $clientId, string $plan, bool $promo = false): FleetSubscription
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
        // Promoción: 3 meses calendario gratis; al vencer pasa sola a 'active' (ver runDailyMaintenance).
        $endsAt    = $promo ? $now->copy()->addMonthsNoOverflow(self::PROMO_MONTHS) : $now->copy()->addDays($trialDays);

        $sub = new FleetSubscription();
        $sub->forceFill([
            'client_id'          => $clientId,
            'plan'               => $plan,
            'status'             => 'trial',
            'trial_starts_at'    => $now,
            'trial_ends_at'      => $endsAt,
            'is_promo'           => $promo,
            'base_price'         => FleetPlans::basePrice($plan),
            'included_units'     => FleetPlans::includedUnits($plan),
            'overage_unit_price' => FleetPlans::overageUnitPrice(),
            'auto_renew'         => true,
        ]);
        $sub->save();

        $this->syncVehicleCount($sub);
        $this->logEvent($sub, $promo ? 'promo_started' : ($existing ? 'reactivated' : 'trial_started'), [
            'plan'         => $plan,
            'trial_days'   => $trialDays,
            'promo_months' => $promo ? self::PROMO_MONTHS : null,
            'trial_ends'   => $sub->trial_ends_at?->toDateString(),
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
        $sub->plan                = $plan;
        $sub->base_price          = FleetPlans::basePrice($plan);
        $sub->included_units      = FleetPlans::includedUnits($plan);
        $sub->overage_unit_price  = FleetPlans::overageUnitPrice();
        $sub->save();

        $this->syncVehicleCount($sub);
        $this->logEvent($sub, 'plan_changed', ['from' => $from, 'to' => $plan]);

        return $sub->fresh();
    }

    /**
     * Recalcula vehicles_count, overage y monthly_price desde la flota real del
     * cliente. Modelo D: monthly_price = base_price + overage_units * overage_unit_price,
     * donde overage_units = max(0, vehicles_count - included_units).
     */
    public function syncVehicleCount(FleetSubscription $sub): FleetSubscription
    {
        $count = FleetVehicle::forClient($sub->client_id)->count();

        // Bloque automático por unidades (F2b): el plan sigue a la flota registrada.
        $autoPlan = FleetPlans::keyForUnits($count);
        if ($autoPlan !== $sub->plan) {
            $from = $sub->plan;
            $sub->plan               = $autoPlan;
            $sub->base_price         = FleetPlans::basePrice($autoPlan);
            $sub->included_units     = FleetPlans::includedUnits($autoPlan);
            $sub->overage_unit_price = FleetPlans::overageUnitPrice();
            $sub->save();
            $this->logEvent($sub, 'plan_changed', ['from' => $from, 'to' => $autoPlan, 'auto' => true]);
        }

        $overageUnits = max(0, $count - (int) $sub->included_units);
        $overageAmount = round($overageUnits * (float) $sub->overage_unit_price, 2);
        $price        = round((float) $sub->base_price + $overageAmount, 2);

        if (
            $count !== (int) $sub->vehicles_count
            || $overageUnits !== (int) $sub->overage_units
            || abs($price - (float) $sub->monthly_price) > 0.001
        ) {
            $previous = (int) $sub->vehicles_count;
            $sub->vehicles_count  = $count;
            $sub->overage_units   = $overageUnits;
            $sub->overage_amount  = $overageAmount;
            $sub->monthly_price   = $price;
            $sub->save();

            $this->logEvent($sub, 'vehicles_changed', [
                'from'           => $previous,
                'to'             => $count,
                'overage_units'  => $overageUnits,
                'overage_amount' => $overageAmount,
                'monthly_price'  => $price,
            ]);
        }

        return $sub;
    }

    /**
     * Punto único de enganche del aviso por cambio de bloque (decisión 5: el bloque
     * sigue a las unidades registradas). Devuelve el cambio {from,to,units} o null si
     * no cambió de bloque. Deja evento en el historial; F3 (#621) cuelga aquí el aviso al cliente.
     */
    public function avisarCambioDeBloque(FleetSubscription $sub, int $unidadesAntes, int $unidadesDespues): ?array
    {
        $from = FleetPlans::keyForUnits($unidadesAntes);
        $to   = FleetPlans::keyForUnits($unidadesDespues);
        // Arriba del tope (Pro + excedente) el bloque no cambia pero el precio sí: también se avisa al subir.
        $arribaDelTope = $unidadesDespues > $unidadesAntes
            && $unidadesDespues > (int) FleetPlans::get($to)['included_units'];
        if ($from === $to && ! $arribaDelTope) {
            return null;
        }

        $this->logEvent($sub, 'block_change_notice', ['from' => $from, 'to' => $to, 'units' => $unidadesDespues]);

        $planTo = FleetPlans::get($to);
        $extra  = max(0, $unidadesDespues - (int) $planTo['included_units']);
        $precio = (float) $planTo['base_price'] + $extra * (float) $planTo['overage_unit_price'];
        $aviso  = [
            'from'    => $from,
            'to'      => $to,
            'units'   => $unidadesDespues,
            'mensaje' => 'Pasas al bloque ' . $planTo['name'] . ' ($' . number_format($precio, 2) . '/mes); se reflejará en tu próxima factura.',
        ];

        // Solo se avisa por el canal de suscripción cuando el bloque SUBE (decisión 3). Nunca bloquea.
        if ($unidadesDespues > $unidadesAntes) {
            try {
                (new SubscriptionAlertDispatcher())->dispatchBlockChange($sub, $aviso['mensaje']);
            } catch (\Throwable $e) {
                Log::warning('Flotas: aviso de cambio de bloque falló: ' . $e->getMessage());
            }
        }

        return $aviso;
    }

    /** Convierte el trial en suscripción pagada (tras configurar método de pago). */
    public function activate(FleetSubscription $sub, string $eventType = 'activated'): FleetSubscription
    {
        $this->syncVehicleCount($sub);

        $sub->status            = 'active';
        $sub->started_at        = $sub->started_at ?? now();
        $sub->next_billing_date = now()->addMonthNoOverflow()->startOfDay();
        $sub->data_retention_until = null;
        $sub->save();

        $this->logEvent($sub, $eventType, [
            'plan'          => $sub->plan,
            'monthly_price' => (float) $sub->monthly_price,
            'next_billing'  => $sub->next_billing_date?->toDateString(),
        ]);

        return $sub->fresh();
    }

    /**
     * Ajuste de un mes cerrado que lleva la factura $invoiceId (#622, Opción A): deja registro para
     * no volver a ajustarlo. Idempotente por (mes ajustado, factura).
     */
    public function registrarAjuste(FleetSubscription $sub, string $ajusteDe, float $monto, int $invoiceId): void
    {
        $existe = \App\Modules\Addons\Flotas\Models\FleetSubscriptionEvent::where('subscription_id', $sub->id)
            ->where('event_type', 'billed')
            ->where('metadata->ajuste_de', $ajusteDe)
            ->where('metadata->invoice_id', $invoiceId)->exists();
        if (! $existe) {
            $this->logEvent($sub, 'billed', ['tipo' => 'ajuste', 'ajuste_de' => $ajusteDe, 'monto_ajuste' => $monto, 'invoice_id' => $invoiceId]);
        }
    }

    /**
     * Marca un ciclo facturado y avanza la fecha de cobro un mes.
     * (La generación del cargo real en el módulo Pagos se engancha aquí en una sub-tarea posterior.)
     */
    public function markBilled(FleetSubscription $sub, array $cargo = []): FleetSubscription
    {
        // Cargo por adelantado de un período (#622): idempotente por período+factura.
        if (isset($cargo['periodo'], $cargo['invoice_id'])
            && \App\Modules\Addons\Flotas\Models\FleetSubscriptionEvent::where('subscription_id', $sub->id)
                ->where('event_type', 'billed')
                ->where('metadata->periodo', $cargo['periodo'])
                ->where('metadata->invoice_id', $cargo['invoice_id'])->exists()) {
            return $sub;
        }
        // El cargo por adelantado solo deja registro: no cambia estado ni fecha de cobro
        // (una promo en curso no debe pasar a 'active' por aparecer en una factura).
        if (isset($cargo['periodo'], $cargo['invoice_id'])) {
            $this->logEvent($sub, 'billed', [
                'amount'   => (float) $cargo['monto_adelantado'],
                'vehicles' => (int) $cargo['unidades'],
            ] + $cargo);

            return $sub;
        }

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
        ] + $cargo);

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

                    if ($daysLeft <= 0 && $sub->is_promo) {
                        // Promo terminada: empieza a cobrarse sola (dentro de la factura mensual).
                        $this->activate($sub, 'promo_converted');
                        $summary['converted'] = ($summary['converted'] ?? 0) + 1;
                        continue;
                    }

                    if ($daysLeft <= 0) {
                        $this->expire($sub);
                        $summary['expired']++;
                        continue;
                    }

                    $threshold = $this->reminderThresholdFor($daysLeft);
                    if ($threshold !== null && (int) $sub->trial_notified_days !== $threshold) {
                        $sub->trial_notified_days = $threshold;
                        $sub->save();
                        $eventType = $threshold <= 7 ? 'trial_expiring' : 'trial_reminder';
                        $this->logEvent($sub, $eventType, [
                            'days_left' => $daysLeft,
                            'threshold' => $threshold,
                        ]);

                        try {
                            (new SubscriptionAlertDispatcher())->dispatchTrialReminder($sub, $eventType, $daysLeft);
                        } catch (\Throwable $e) {
                            // Best-effort: un fallo de notificación no debe tumbar el mantenimiento diario.
                            Log::warning("Flotas SubscriptionAlert: fallo despachando recordatorio de la suscripción {$sub->id}: {$e->getMessage()}");
                        }

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
