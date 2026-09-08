<?php

namespace App\Modules\Addons\Talento\Observers;

use App\Models\PaymentByRuleDetails;
use App\Models\Seller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoComisionEspejo;
use App\Modules\Addons\Talento\Support\PayWeek;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fase 1b del puente Vendedores→Talento (item #9990610, sub-item de #9990605).
 *
 * Escribe el espejo de solo-lectura `talento_comisiones_espejo` (tabla aislada creada en
 * la Fase 1a #9990609) cada vez que Vendedores inserta una línea de comisión real
 * (PaymentByRuleDetails / tabla payment_by_rule_commissions, ver
 * PaymentSellerController::saveDetails()). Aditivo y silencioso: si algo falla o el
 * vendedor no es colaborador Talento, NO se escribe nada y el pago real de Vendedores
 * sigue exactamente igual — este observer nunca debe poder tumbar ni alterar esa
 * transacción (decisión Irving #9990610/q2, opción 1: try/catch interno, nunca relanzar).
 *
 * Ancla de calendario (decisión Irving #9990610/q1: PayWeek por "fecha de la comisión"):
 * se usa `end_date` de la comisión (fin del período Domingo→Sábado de
 * CalculateBalanceSellerService) a las 23:59:59, NO `created_at` de la fila. Razón: las
 * comisiones se calculan/insertan a veces en lote después de cerrado el período (ver
 * PaymentSellerController::store()), así que `created_at` no refleja cuándo ocurrió el
 * hecho comercial; `end_date` sí es el último día que la comisión realmente cubre, y es
 * el ancla que el propio item sugiere explícitamente como ejemplo.
 *
 * Eventos escuchados: SOLO `created` (decisión de diseño registrada vía
 * `circuito:reportar --tipo=decision` en #9990610 -- el brief estructurado del item
 * recomendaba además `updated`/`deleted` con upsert+anulación, pero la tabla
 * `talento_comisiones_espejo` construida en la Fase 1a es un ledger append-only sin
 * columnas `updated_at`/`deleted_at`/estado -- ese ciclo de vida completo necesitaría
 * alterar un esquema ya aprobado y mergeado; se deja como mejora futura si Irving la
 * pide explícitamente). Coincide además con el mecanismo descrito textualmente en la
 * propia descripción del item, que solo menciona el evento `created`.
 */
class TalentoComisionEspejoObserver
{
    public function created(PaymentByRuleDetails $details): void
    {
        if (! config('talento.vendedores_espejo_enabled')) {
            return;
        }

        try {
            $this->escribirEspejo($details);
        } catch (Throwable $e) {
            Log::warning('Talento: fallo al escribir espejo de comisión de vendedor (no afecta el pago real)', [
                'payment_by_rule_details_id' => $details->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function escribirEspejo(PaymentByRuleDetails $details): void
    {
        $payment = $details->payment;
        if (! $payment || ! $payment->seller_id) {
            Log::info('Talento: comisión de vendedor sin payment/seller_id resoluble, se omite el espejo', [
                'payment_by_rule_details_id' => $details->id,
            ]);
            return;
        }

        $seller = Seller::find($payment->seller_id);
        if (! $seller || ! $seller->user_id) {
            Log::info('Talento: seller sin user_id resoluble, se omite el espejo', [
                'payment_by_rule_details_id' => $details->id,
                'seller_id' => $payment->seller_id,
            ]);
            return;
        }

        $colaborador = TalentoColaborador::where('user_id', $seller->user_id)->first();
        if (! $colaborador) {
            Log::info('Talento: vendedor sin colaborador Talento vinculado, se omite el espejo (no es error)', [
                'payment_by_rule_details_id' => $details->id,
                'seller_id' => $seller->id,
                'user_id' => $seller->user_id,
            ]);
            return;
        }

        if (! $details->end_date) {
            Log::warning('Talento: comisión de vendedor sin end_date, no se puede resolver PayWeek, se omite el espejo', [
                'payment_by_rule_details_id' => $details->id,
            ]);
            return;
        }

        $ancla = Carbon::parse($details->end_date)->endOfDay();
        $ventana = PayWeek::boundsFor($ancla);

        TalentoComisionEspejo::create([
            'colaborador_id' => $colaborador->id,
            'user_id' => $seller->user_id,
            'seller_id' => $seller->id,
            'payment_by_rule_id' => $payment->id,
            'payment_by_rule_details_id' => $details->id,
            'amount' => $details->amount,
            'period_start' => $ventana['period_start'],
            'period_end' => $ventana['period_end'],
            'source_period_start' => $details->start_date,
            'source_period_end' => $details->end_date,
        ]);
    }
}
