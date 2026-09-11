<?php

namespace App\Modules\Addons\Domiciliacion\Services;

use App\Modules\Addons\Domiciliacion\Models\ClientRecurringCard;
use App\Modules\Addons\Domiciliacion\Models\DomiciliacionPlan;
use App\Modules\Addons\Domiciliacion\Models\DomiciliacionSubscription;
use App\Modules\Addons\PortalCliente\Services\OpenpayService;
use RuntimeException;

/**
 * Fase 1 de domiciliación por SUSCRIPCIÓN (monto fijo mensual del cliente).
 * Crea/reusa un plan por monto y suscribe la tarjeta guardada activa del cliente.
 * NO cobra ahora: OpenPay cobra automáticamente cada mes SIN device_session_id.
 *
 * Aún NO se engancha al enrolamiento ni concilia cargos (Fase 2: webhook).
 */
class DomiciliacionSubscriptionService
{
    public function __construct(private OpenpayService $openpay) {}

    public function crearParaCliente(int $clientId, float $monto): DomiciliacionSubscription
    {
        if ($monto <= 0) {
            throw new RuntimeException('El monto mensual debe ser mayor a 0.');
        }

        $card = ClientRecurringCard::forClient($clientId)->active()->first();
        if (! $card) {
            throw new RuntimeException('El cliente no tiene una tarjeta activa para domiciliar.');
        }

        // 1. plan por monto (reusar si ya existe uno para esa tarifa)
        $plan = $this->planParaMonto($monto);

        // 2. suscribir la tarjeta guardada (merchant-initiated, sin device_session_id)
        $sub = $this->openpay->crearSuscripcion(
            $card->openpay_customer_id,
            $card->openpay_card_id,
            $plan->openpay_plan_id
        );

        // 3. registrar
        return DomiciliacionSubscription::create([
            'client_id'                => $clientId,
            'client_recurring_card_id' => $card->id,
            'openpay_customer_id'      => $card->openpay_customer_id,
            'openpay_card_id'          => $card->openpay_card_id,
            'domiciliacion_plan_id'    => $plan->id,
            'openpay_subscription_id'  => $sub->id,
            'amount'                   => round($monto, 2),
            'status'                   => $sub->status ?? 'active',
            'charge_date'              => $sub->charge_date ?? null,
            'created_by'               => auth()->id(),
        ]);
    }

    /** Plan local por monto; lo crea en OpenPay la primera vez y lo reusa después. */
    private function planParaMonto(float $monto): DomiciliacionPlan
    {
        $existing = DomiciliacionPlan::where('amount', round($monto, 2))->first();
        if ($existing) {
            return $existing;
        }

        $nombre = 'Domiciliacion mensual $' . number_format($monto, 2);
        $openpayPlanId = $this->openpay->crearPlan($monto, $nombre);

        return DomiciliacionPlan::create([
            'amount'          => round($monto, 2),
            'openpay_plan_id' => $openpayPlanId,
            'name'            => $nombre,
        ]);
    }

    /** Cancela la suscripción en OpenPay y localmente. */
    public function cancelar(DomiciliacionSubscription $sub): void
    {
        $this->openpay->cancelarSuscripcion($sub->openpay_customer_id, $sub->openpay_subscription_id);
        $sub->update(['status' => 'cancelled']);
        $sub->delete();
    }
}
