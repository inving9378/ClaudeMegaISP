<?php

namespace App\Services\Contratable;

use App\Models\Contratable\ClientContratableSubscription;
use App\Models\Invoice;
use Carbon\Carbon;

/**
 * Lógica de prueba gratis de los servicios contratables — STATELESS.
 *
 * No muta ningún contador: la prueba se DERIVA contando las proformas ya generadas
 * para el cliente desde que activó la suscripción. Es idempotente por construcción
 * (re-generar la misma proforma no cambia el conteo) y seguro para llamarse dentro de
 * `price_service` sin efectos secundarios — requisito de que `calculateAmounts` siga PURA.
 *
 * Definición: una suscripción está EN PRUEBA si el nº de proformas del cliente con
 * `period` ANTERIOR al período en curso (y creadas desde `activated_at`) es menor que
 * `meses_prueba`. Se excluye el período en curso a propósito: así el resultado es
 * idéntico exista o no todavía la proforma de este período (el motor llama a
 * calculateAmounts una vez antes de crear la factura y otra después).
 *
 * `trial_invoices_remaining` del esqueleto FASE 1 queda como informativo: NO es la
 * fuente de verdad de esta decisión.
 */
class ContratableTrialService
{
    /** Mismo umbral que CreateProformaInvoiceCommand::PROFORMA_CREATION_DAY. */
    private const PROFORMA_CREATION_DAY = 25;

    /**
     * Período de facturación en curso ('Y-m'), replicando EXACTAMENTE la regla del
     * comando de proformas: a partir del día 25 se factura el mes siguiente.
     */
    public function currentPeriod(?Carbon $now = null): string
    {
        $today = $now ?? Carbon::now();

        return $today->day >= self::PROFORMA_CREATION_DAY
            ? $today->copy()->addMonth()->format('Y-m')
            : $today->format('Y-m');
    }

    /**
     * Nº de proformas ya facturadas al cliente desde la activación, en períodos
     * anteriores al actual. Es el "consumo" de la prueba.
     */
    public function trialUsed(ClientContratableSubscription $sub, ?string $currentPeriod = null): int
    {
        if (! $sub->activated_at) {
            return 0;
        }

        $currentPeriod = $currentPeriod ?? $this->currentPeriod();

        return Invoice::query()
            ->where('client_id', $sub->client_id)
            ->where('type', Invoice::TYPE_PROFORMA)
            ->where('created_at', '>=', $sub->activated_at)
            ->where('period', '<', $currentPeriod)
            ->count();
    }

    /**
     * ¿La suscripción está dentro de su ventana de prueba para el período en curso?
     * Sin `activated_at` (suscripción incompleta) → false; el inyector además la salta.
     */
    public function isInTrial(ClientContratableSubscription $sub, ?string $currentPeriod = null): bool
    {
        if (! $sub->activated_at) {
            return false;
        }

        $meses = (int) ($sub->service->meses_prueba ?? 0);
        if ($meses <= 0) {
            return false;
        }

        return $this->trialUsed($sub, $currentPeriod) < $meses;
    }

    /**
     * ¿Este período es la ÚLTIMA factura gratis? (para el aviso pre-cobro). Es decir,
     * está en prueba ahora pero el próximo período ya será con costo.
     * El ENVÍO del aviso se cablea en una fase posterior; aquí solo se expone el flag.
     */
    public function isLastFreeInvoice(ClientContratableSubscription $sub, ?string $currentPeriod = null): bool
    {
        $meses = (int) ($sub->service->meses_prueba ?? 0);
        if ($meses <= 0 || ! $sub->activated_at) {
            return false;
        }

        return $this->trialUsed($sub, $currentPeriod) === ($meses - 1);
    }
}
