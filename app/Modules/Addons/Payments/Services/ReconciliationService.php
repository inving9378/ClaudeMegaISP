<?php

namespace App\Modules\Addons\Payments\Services;

use App\Modules\Addons\Payments\Models\ReconciliationTicket;

/**
 * Servicio de la cola de conciliación (motor de cobro nativo).
 *
 * ─── PUNTO DE INSERCIÓN PARA FLUJOS FUTUROS ───────────────────────────────
 * Cuando exista la lógica de conciliación automática (fases posteriores),
 * cada flujo que detecte un pago/transferencia dudosa debe llamar a
 * ReconciliationService::raise(...) para encolar un ticket. Por ahora la cola
 * solo muestra y resuelve; NADIE crea tickets automáticamente todavía.
 *
 * Ejemplos de quién llamará raise() más adelante:
 *   - Webhook/import bancario: referencia recibida que no casa con ningún
 *     cliente → raise(reason: 'unmatched_reference', detail, amount).
 *   - Aplicación de pago: monto recibido ≠ esperado → 'amount_mismatch'.
 *   - Detección de duplicados → 'duplicate'.
 *   - Marca manual de un operador → 'manual_review'.
 */
class ReconciliationService
{
    public const REASONS = ['unmatched_reference', 'amount_mismatch', 'duplicate', 'manual_review'];

    /**
     * Encola un ticket de conciliación. Idempotencia mínima: si ya existe un
     * ticket ABIERTO para el mismo payment_id y razón, lo devuelve sin duplicar.
     *
     * @param  string    $reason    uno de self::REASONS
     * @param  int|null   $clientId  cliente involucrado (si se conoce)
     * @param  int|null   $paymentId pago relacionado (si aplica)
     */
    public static function raise(
        string $reason,
        ?string $detail = null,
        ?float $amount = null,
        ?int $clientId = null,
        ?int $paymentId = null
    ): ReconciliationTicket {
        if (!in_array($reason, self::REASONS, true)) {
            $reason = 'manual_review';
        }

        if ($paymentId !== null) {
            $existente = ReconciliationTicket::open()
                ->where('payment_id', $paymentId)
                ->where('reason', $reason)
                ->first();
            if ($existente) {
                return $existente;
            }
        }

        return ReconciliationTicket::create([
            'client_id'  => $clientId,
            'payment_id' => $paymentId,
            'reason'     => $reason,
            'detail'     => $detail,
            'amount'     => $amount,
            'status'     => 'open',
            'created_by' => auth()->id(),
        ]);
    }
}
