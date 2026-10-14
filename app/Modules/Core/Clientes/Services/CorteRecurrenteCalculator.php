<?php

namespace App\Modules\Core\Clientes\Services;

use Carbon\Carbon;

/**
 * Regla de fecha_corte para clientes de "Pagos Recurrentes" (decisión del equipo, 2026-10-14).
 *
 * - El corte es FIJO: cae siempre en el día de facturación (billing_date) + billing_expiration.
 * - El corte solo avanza cuando el cliente PAGA: un mes por cada mensualidad pagada, contado
 *   desde el corte anterior (NO desde el día en que pagó, NO desde fecha_pago).
 * - Los cobros automáticos sin saldo acumulan deuda pero NO mueven el corte.
 * - El servicio sigue cortado mientras el corte nuevo sea anterior a hoy o quede deuda
 *   (la reactivación sigue decidiéndose por saldo >= 0 en ClientBillingService).
 *
 * Clase pura (sin BD ni Eloquent) para poder probarla con PHPUnit sin tocar ninguna base.
 */
class CorteRecurrenteCalculator
{
    /** Mensualidades que el saldo todavía debe (0 si el saldo es >= 0). */
    public static function ciclosPendientes(float $saldo, float $costoCiclo): int
    {
        $saldo = round($saldo, 2);
        if ($costoCiclo <= 0 || $saldo >= 0) {
            return 0;
        }

        return (int) ceil(round(-$saldo / $costoCiclo, 6));
    }

    /** Mensualidades de deuda que un pago saldó (saldo antes vs. después del pago). */
    public static function ciclosSaldados(float $saldoAntes, float $saldoDespues, float $costoCiclo): int
    {
        return max(0, self::ciclosPendientes($saldoAntes, $costoCiclo) - self::ciclosPendientes($saldoDespues, $costoCiclo));
    }

    /**
     * Nuevo corte = corte anterior + $meses, manteniendo el día fijo de facturación.
     *
     * @param Carbon $corteAnterior   corte vigente (incluye ya billing_expiration)
     * @param int    $meses           mensualidades pagadas
     * @param int    $billingDate     día de facturación configurado (1-31)
     * @param int    $billingExpiration días de tolerancia después del día de facturación
     */
    public static function nuevoCorte(Carbon $corteAnterior, int $meses, int $billingDate, int $billingExpiration): Carbon
    {
        // Mes de facturación al que pertenece el corte (quita los días de tolerancia).
        $mesBase = $corteAnterior->copy()->subDays($billingExpiration)->startOfMonth();
        $destino = $mesBase->addMonthsNoOverflow($meses);
        $dia = min(max($billingDate, 1), $destino->daysInMonth);

        return $destino->day($dia)->addDays($billingExpiration)->endOfDay();
    }
}
