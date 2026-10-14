<?php

namespace App\Modules\Core\Clientes\Services;

use Carbon\Carbon;

/**
 * Reglas del periodo de gracia y del cobro sin saldo para "Pagos Recurrentes" (2026-10-14).
 *
 * - La gracia SOLO existe para quien ya cumplió la duración de su contrato (pagos reales >= meses
 *   contratados). Dura DIAS_GRACIA (90) para todos; durante la gracia el sistema SIGUE cobrando
 *   (si no paga, lo debe). Al vencer: inactivo + address list (comando aparte, con lista previa).
 * - Dentro del contrato NO hay gracia: se suspende al pasar el corte y el cobro automático sigue
 *   acumulando deuda solo hasta que la deuda llega a los meses que le faltan del contrato.
 *
 * Clase pura (sin BD) para probarla con PHPUnit sin tocar ninguna base.
 */
class GraciaRecurrenteCalculator
{
    public const DIAS_GRACIA = 90;

    /** La gracia solo aplica cuando el contrato ya está cumplido. */
    public static function aplicaGracia(int $mesesRestantesContrato): bool
    {
        return $mesesRestantesContrato <= 0;
    }

    public static function graciaVencida(?Carbon $finGracia, Carbon $ahora): bool
    {
        return $finGracia !== null && $finGracia->lt($ahora);
    }

    /**
     * ¿El cobro automático puede cargar un ciclo a un cliente SIN saldo suficiente?
     *
     * @param int         $mesesRestantesContrato meses de contrato aún no cubiertos con pagos reales
     * @param int         $ciclosDeuda            mensualidades que ya debe (saldo negativo / costo)
     * @param Carbon|null $finGracia              fecha_fin_periodo_gracia (null = sin gracia todavía)
     */
    public static function puedeCobrarSinSaldo(int $mesesRestantesContrato, int $ciclosDeuda, ?Carbon $finGracia, Carbon $ahora): bool
    {
        if ($mesesRestantesContrato > 0) {
            // Dentro del contrato: la deuda no puede pasar de lo que le falta por cubrir.
            return $ciclosDeuda < $mesesRestantesContrato;
        }

        // Contrato cumplido: se cobra mientras no haya gracia aún (este cobro la inicia) o siga vigente.
        return !self::graciaVencida($finGracia, $ahora);
    }
}
