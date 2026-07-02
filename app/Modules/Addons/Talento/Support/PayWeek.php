<?php

namespace App\Modules\Addons\Talento\Support;

use Carbon\Carbon;

/**
 * PayWeek — punto único de verdad de la ventana de la semana de pago de Talento.
 *
 * Toda derivación de la ventana semanal del motor de compensación (liquidación,
 * bono de salud, bono de proyecto, avance, dashboards) debe pasar por aquí para
 * que los períodos coincidan EXACTO (si no, un bono queda con período huérfano).
 *
 *  Ventana NUEVA  (instante >= cutover): Sábado 18:00 -> Sábado 18:00 (siguiente).
 *    - 7 días exactos, corte único a las 18:00.
 *    - validated_at <= Sáb 18:00 cierra la semana; después -> semana siguiente.
 *
 *  Ventana LEGACY (instante <  cutover): Sábado 00:00 -> Viernes 23:59 (día completo).
 *    - Replica bit-exacto el motor histórico (startOfWeek(SATURDAY) + 6 días) para
 *      que las semanas YA pagadas se reproduzcan intactas. Cambio forward-only.
 *
 *  start_instant / end_instant son los límites INCLUSIVOS del filtro por datetime
 *  (whereBetween('validated_at', [start_instant, end_instant])):
 *    - LEGACY: [Sáb 00:00:00, Vie 23:59:59].
 *    - NUEVA : [Sáb 18:00:01, Sáb 18:00:00]. El sábado 18:00:00 exacto CIERRA la
 *      semana anterior; por eso el primer segundo incluido en la nueva es 18:00:01
 *      (evita contar dos veces el instante del corte).
 *
 *  ── Semana de TRANSICIÓN (go-live, decidida por Irving — se IMPLEMENTA en 2.2) ──
 *    La última semana LEGACY se extiende hasta el cutover: [2026-07-04 00:00 ->
 *    2026-07-11 18:00] (8 días). Todo lo validado hasta el sáb 2026-07-11 18:00 se
 *    paga con método VIEJO en un solo pago; la primera semana NUEVA arranca el
 *    2026-07-11 18:00 -> 2026-07-18 18:00. Hoy PayWeek NO implementa este tramo de
 *    8 días (steady-state); lo respaldará el guard anti-recálculo del sub-paso 2.2.
 *
 * La hora de corte y la fecha de cutover viven en config/talento.php.
 * Todos los métodos aceptan overrides opcionales para poder testear sin config.
 */
class PayWeek
{
    /**
     * Ventana de pago que contiene al instante $t.
     *
     * @return array{regime:string,start_instant:Carbon,end_instant:Carbon,period_start:string,period_end:string}
     */
    public static function boundsFor(
        Carbon $t,
        ?Carbon $cutover = null,
        ?int $cutoffHour = null,
        ?int $cutoffMinute = null
    ): array {
        $cutover      = $cutover      ?? self::cutover();
        $cutoffHour   = $cutoffHour   ?? self::cutoffHour();
        $cutoffMinute = $cutoffMinute ?? self::cutoffMinute();

        return $t->lt($cutover)
            ? self::legacyBounds($t)
            : self::newBounds($t, $cutoffHour, $cutoffMinute);
    }

    /** Ventana NUEVA: Sáb 18:00 (excl) -> Sáb 18:00 (incl). */
    protected static function newBounds(Carbon $t, int $hour, int $minute): array
    {
        // Sábado-corte de cierre = primer Sábado a las HH:MM que sea >= $t.
        $close = $t->copy()->startOfWeek(Carbon::SATURDAY)->setTime($hour, $minute, 0);
        if ($close->lt($t)) {
            $close->addDays(7); // $t cayó DESPUÉS del corte del sábado -> semana siguiente
        }
        $open = $close->copy()->subDays(7); // Sábado-corte anterior (18:00, cierre de la semana previa)

        return [
            'regime'        => 'new',
            // Límites INCLUSIVOS para whereBetween: apertura 18:00:01 (el 18:00:00 exacto
            // cerró la semana anterior), cierre 18:00:00.
            'start_instant' => $open->copy()->addSecond(),
            'end_instant'   => $close,
            'period_start'  => $open->toDateString(),  // sábado de apertura
            'period_end'    => $close->toDateString(), // sábado de cierre
        ];
    }

    /** Ventana LEGACY: Sáb 00:00 -> Vie 23:59 (día completo). Mirror del motor histórico. */
    protected static function legacyBounds(Carbon $t): array
    {
        $start = $t->copy()->startOfWeek(Carbon::SATURDAY)->startOfDay(); // Sábado 00:00
        $end   = $start->copy()->addDays(6)->endOfDay();                   // Viernes 23:59:59

        return [
            'regime'        => 'legacy',
            'start_instant' => $start,
            'end_instant'   => $end,
            'period_start'  => $start->toDateString(),
            'period_end'    => $end->toDateString(),
        ];
    }

    /** Semana de pago vigente (la que contiene "ahora"). */
    public static function current(?Carbon $now = null): array
    {
        return self::boundsFor(($now ?? Carbon::now())->copy());
    }

    /**
     * Última semana COMPLETADA (cerrada) respecto a $now. Reemplaza a
     * LiquidationService::lastWeekBounds() — devuelve la semana anterior a la vigente.
     */
    public static function lastCompleted(?Carbon $now = null): array
    {
        $current    = self::current($now);
        $justBefore = $current['start_instant']->copy()->subSecond(); // 1s antes del arranque vigente
        return self::boundsFor($justBefore);
    }

    protected static function cutover(): Carbon
    {
        return Carbon::parse(config('talento.pay_week.cutover'));
    }

    protected static function cutoffHour(): int
    {
        return (int) config('talento.pay_week.cutoff_hour', 18);
    }

    protected static function cutoffMinute(): int
    {
        return (int) config('talento.pay_week.cutoff_minute', 0);
    }
}
