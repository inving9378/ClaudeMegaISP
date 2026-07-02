<?php

namespace Tests\Unit\Talento;

use App\Modules\Addons\Talento\Support\PayWeek;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO toca BD, NO migrate:fresh.

/**
 * Tests unitarios del helper PayWeek (ventana de la semana de pago).
 * No dependen de la app ni de la BD: se inyecta el cutover explícito.
 *
 * ⚠️ Validar con tinker (regla del proyecto: no correr el test runner contra la BD
 *    de dev/prod). Este archivo queda como especificación ejecutable / CI aislado.
 */
class PayWeekTest extends TestCase
{
    /** Cutover fijo de referencia: sábado 2026-07-04 18:00. */
    private function cutover(): Carbon
    {
        return Carbon::parse('2026-07-04 18:00:00');
    }

    private function bounds(string $instant): array
    {
        return PayWeek::boundsFor(Carbon::parse($instant), $this->cutover(), 18, 0);
    }

    /** Sábado 17:59 cierra la semana que termina ESE sábado 18:00. */
    public function test_sabado_antes_del_corte_cierra_la_semana_actual(): void
    {
        $b = $this->bounds('2026-07-11 17:59:00');
        $this->assertSame('new', $b['regime']);
        $this->assertSame('2026-07-04', $b['period_start']);
        $this->assertSame('2026-07-11', $b['period_end']);
    }

    /** Sábado 18:00 EXACTO todavía cierra la semana (corte inclusivo). */
    public function test_sabado_18_00_exacto_es_inclusivo(): void
    {
        $b = $this->bounds('2026-07-11 18:00:00');
        $this->assertSame('2026-07-04', $b['period_start']);
        $this->assertSame('2026-07-11', $b['period_end']);
    }

    /** Sábado 18:01 (después del corte) pasa a la semana SIGUIENTE. */
    public function test_sabado_despues_del_corte_pasa_a_la_siguiente(): void
    {
        $b = $this->bounds('2026-07-11 18:01:00');
        $this->assertSame('2026-07-11', $b['period_start']);
        $this->assertSame('2026-07-18', $b['period_end']);
    }

    /** Domingo pertenece a la semana que cierra el próximo sábado. */
    public function test_domingo_va_a_la_semana_que_cierra_el_proximo_sabado(): void
    {
        $b = $this->bounds('2026-07-12 10:00:00');
        $this->assertSame('2026-07-11', $b['period_start']);
        $this->assertSame('2026-07-18', $b['period_end']);
    }

    /** Viernes pertenece a la semana que cierra el sábado siguiente. */
    public function test_viernes_va_a_la_semana_que_cierra_el_sabado_siguiente(): void
    {
        $b = $this->bounds('2026-07-17 10:00:00');
        $this->assertSame('2026-07-11', $b['period_start']);
        $this->assertSame('2026-07-18', $b['period_end']);
    }

    /** El corte inclusivo/exclusivo separa 17:59 y 18:01 en semanas distintas. */
    public function test_1759_y_1801_caen_en_semanas_distintas(): void
    {
        $a = $this->bounds('2026-07-11 17:59:00');
        $b = $this->bounds('2026-07-11 18:01:00');
        $this->assertNotSame($a['period_end'], $b['period_end']);
    }

    /** Antes del cutover rige la ventana LEGACY (Sáb 00:00 -> Vie 23:59), intacta. */
    public function test_antes_del_cutover_usa_ventana_legacy(): void
    {
        $b = $this->bounds('2026-07-01 12:00:00'); // miércoles pre-cutover
        $this->assertSame('legacy', $b['regime']);
        $this->assertSame('2026-06-27', $b['period_start']); // sábado
        $this->assertSame('2026-07-03', $b['period_end']);   // viernes
    }

    /** Filtro nuevo inclusivo: apertura 18:00:01 (el 18:00:00 cierra la semana previa), cierre 18:00:00. */
    public function test_instantes_del_filtro_nuevo(): void
    {
        $b = $this->bounds('2026-07-08 12:00:00');
        $this->assertSame('2026-07-04 18:00:01', $b['start_instant']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-11 18:00:00', $b['end_instant']->format('Y-m-d H:i:s'));
    }
}
