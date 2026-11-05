<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoLiquidation;
use App\Modules\Addons\Talento\Models\TalentoLiquidationEvent;
use Illuminate\Support\Facades\DB;

/**
 * UNI-T J4 F1: transiciones de estado de la liquidación, draft(calculada) → closed(cerrada) → paid(pagada).
 * Cierre y pago son acciones separadas. Prohibido retroceder, pagar sin cerrar o tocar una pagada;
 * reabrir solo por ajuste (J3, talento_period_adjustments), nunca desde aquí.
 * Todo detrás de unificacion.interruptores.liquidacion_estados_j4 (apagado = solo draft/closed como hoy).
 */
class LiquidationStateService
{
    public function __construct(private LiquidationService $liquidations) {}

    public static function activo(): bool
    {
        return (bool) config('unificacion.interruptores.liquidacion_estados_j4', false);
    }

    public function cerrar(TalentoLiquidation $liq, ?int $userId = null): TalentoLiquidation
    {
        $this->exigirActivo();
        if ($liq->status !== 'draft') {
            throw new \RuntimeException($liq->status === 'paid'
                ? 'La liquidación ya está pagada; no se puede modificar.'
                : 'La liquidación ya está cerrada.');
        }

        return DB::transaction(function () use ($liq, $userId) {
            $cerrada = $this->liquidations->close($liq);
            $this->evento($cerrada, 'cierre', 'draft', 'closed', $userId);
            return $cerrada;
        });
    }

    /** Marca como pagada una liquidación cerrada. No mueve dinero: solo registra el estado y la referencia. */
    public function pagar(TalentoLiquidation $liq, ?int $userId = null, ?string $referencia = null): TalentoLiquidation
    {
        $this->exigirActivo();

        return DB::transaction(function () use ($liq, $userId, $referencia) {
            $actual = TalentoLiquidation::lockForUpdate()->findOrFail($liq->id);
            if ($actual->status === 'paid') {
                throw new \RuntimeException('La liquidación ya está pagada.');
            }
            if ($actual->status !== 'closed') {
                throw new \RuntimeException('Solo se puede pagar una liquidación cerrada.');
            }
            $actual->update([
                'status'            => 'paid',
                'paid_at'           => now(),
                'paid_by'           => $userId,
                'payment_reference' => $referencia,
            ]);
            $this->evento($actual, 'pago', 'closed', 'paid', $userId, $referencia);
            return $actual->fresh();
        });
    }

    /** Cualquier otro movimiento (retroceder/reabrir) está prohibido. */
    public function reabrir(TalentoLiquidation $liq): never
    {
        throw new \RuntimeException('Una liquidación cerrada o pagada no se reabre: se corrige con un ajuste en la siguiente semana.');
    }

    private function exigirActivo(): void
    {
        if (!self::activo()) {
            throw new \RuntimeException('Los estados de liquidación (J4) están apagados.');
        }
    }

    private function evento(TalentoLiquidation $liq, string $tipo, ?string $de, ?string $a, ?int $userId, ?string $ref = null): void
    {
        TalentoLiquidationEvent::create([
            'liquidation_id' => $liq->id, 'tipo' => $tipo, 'from_status' => $de,
            'to_status' => $a, 'user_id' => $userId, 'referencia' => $ref,
        ]);
    }
}
