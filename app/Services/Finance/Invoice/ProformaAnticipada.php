<?php

namespace App\Services\Finance\Invoice;

use Carbon\Carbon;

/**
 * FAC-06 (item #512, padre #482): proforma N días antes del corte, en vez de «todas el día 25 del
 * mes anterior». PURA (sin BD): fechas y clasificación para el comando y su simulación.
 *
 * Criterio de corte = el que ya usa invoice:create-proformas: DATE(clients.fecha_corte) (varchar
 * «AAAA-MM-DD hh:mm:ss»); el periodo es el mes de esa fecha. Lo que no es una fecha válida
 * (vacío, «1», 2026-02-30) no tiene corte y queda fuera, igual que hoy en SQL.
 *
 * Días NATURALES (Carbon::subDays). Hábiles o reglas de meses cortos no se suponen: son decisión
 * de Irving (FAC-06b, #513).
 */
final class ProformaAnticipada
{
    /** Día del mes anterior al corte en que hoy se generan TODAS (CreateProformaInvoiceCommand). */
    public const DIA_ACTUAL = 25;

    public const MAX_DIAS = 60;

    public const IGUAL = 'igual';
    public const ADELANTADA = 'adelantada';
    public const ATRASADA = 'atrasada';

    /** delay_days de config_finance_notifications (varchar) → días válidos (0..MAX_DIAS) o null. */
    public static function dias($valor): ?int
    {
        $valor = trim((string) $valor);
        if ($valor === '' || ! ctype_digit($valor)) {
            return null;
        }
        $n = (int) $valor;

        return $n <= self::MAX_DIAS ? $n : null;
    }

    /** fecha_corte → fecha (sin hora) o null si no es una fecha real. */
    public static function corte($fechaCorte): ?Carbon
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', trim((string) $fechaCorte), $m)) {
            return null;
        }
        if (! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return Carbon::create((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
    }

    public static function periodo(Carbon $corte): string
    {
        return $corte->format('Y-m');
    }

    /** Regla actual: el día 25 del mes anterior al del corte. */
    public static function fechaActual(Carbon $corte): Carbon
    {
        return $corte->copy()->startOfMonth()->subMonthNoOverflow()->day(self::DIA_ACTUAL);
    }

    /** Regla nueva: N días naturales antes del corte. */
    public static function fechaNueva(Carbon $corte, int $n): Carbon
    {
        return $corte->copy()->subDays($n);
    }

    /** ¿Toca generarla hoy con la regla nueva? Sin retroactivo: un corte ya pasado no se recupera. */
    public static function tocaHoy(Carbon $corte, int $n, Carbon $hoy): bool
    {
        $hoy = $hoy->copy()->startOfDay();

        return $hoy->betweenIncluded(self::fechaNueva($corte, $n), $corte);
    }

    /**
     * Compara cuándo se generaría con cada regla (ambas acotadas a «no antes de hoy», porque el
     * cron no recupera días pasados). @return array{0: string, 1: int} [clase, días de diferencia]
     */
    public static function comparar(Carbon $corte, int $n, Carbon $hoy): array
    {
        $hoy = $hoy->copy()->startOfDay();
        $actual = self::fechaActual($corte)->max($hoy);
        $nueva = self::fechaNueva($corte, $n)->max($hoy);
        $dif = (int) $actual->diffInDays($nueva, false);

        return [$dif === 0 ? self::IGUAL : ($dif < 0 ? self::ADELANTADA : self::ATRASADA), $dif];
    }

    /** Días entre el 25 del mes anterior y el corte (lo que hoy recibe cada cliente de anticipación). */
    public static function anticipacionActual(Carbon $corte): int
    {
        return (int) self::fechaActual($corte)->diffInDays($corte);
    }
}
