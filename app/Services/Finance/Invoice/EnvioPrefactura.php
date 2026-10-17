<?php

namespace App\Services\Finance\Invoice;

use App\Models\TypeBilling;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FR-05 (#539, decisión de Irving 2026-10-17): «Envío prefactura» = días antes del DÍA DE
 * FACTURACIÓN en que sale la prefactura. Valor por cliente (billing_configurations) o, vacío, el
 * general (billing_config, 5 por defecto). Sustituye a delay_days de #512/#513.
 *
 * Día de facturación: pago recurrente → fecha_pago (el día de facturación del cliente, anclado al
 * mes natural); los demás tipos → fecha_corte (su vencimiento). El periodo y la fecha límite de la
 * proforma siguen saliendo de fecha_corte, como hasta hoy (ProformaAnticipada).
 */
final class EnvioPrefactura
{
    public const DIAS_POR_DEFECTO = 5;

    private static ?bool $hayColumnaGeneral = null;

    /** Valor general (billing_config.envio_prefactura_dias), o 5 si no hay uno válido. */
    public static function diasPorDefecto(): int
    {
        if (self::$hayColumnaGeneral === null) {
            self::$hayColumnaGeneral = Schema::hasColumn('billing_config', 'envio_prefactura_dias');
        }
        if (self::$hayColumnaGeneral) {
            $n = ProformaAnticipada::dias(DB::table('billing_config')->orderBy('id')->value('envio_prefactura_dias'));
            if ($n !== null) {
                return $n;
            }
        }

        return self::DIAS_POR_DEFECTO;
    }

    /** Días del cliente: los suyos (0..60) o, si no tiene o no son válidos, el general. */
    public static function diasDe($billingConfiguration, ?int $general = null): int
    {
        $propio = $billingConfiguration ? ProformaAnticipada::dias($billingConfiguration->envio_prefactura_dias ?? null) : null;

        return $propio ?? ($general ?? self::diasPorDefecto());
    }

    /** Día de facturación del ciclo que viene (ver arriba) o null si no hay una fecha válida. */
    public static function fechaDeFacturacion($cliente, $billingConfiguration): ?Carbon
    {
        $recurrente = $billingConfiguration
            && (int) ($billingConfiguration->type_billing_id ?? 0) === TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT;

        return ProformaAnticipada::corte($recurrente ? ($cliente->fecha_pago ?? null) : ($cliente->fecha_corte ?? null));
    }

    /** Solo para pruebas. */
    public static function olvidar(): void
    {
        self::$hayColumnaGeneral = null;
    }
}
