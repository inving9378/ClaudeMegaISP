<?php

namespace Tests\Unit\Services;

use App\Services\Finance\Invoice\ProformaAnticipada as P;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/** FAC-06 (item #512): fechas de la proforma anticipada. PURA, sin BD. */
class ProformaAnticipadaTest extends TestCase
{
    public function test_delay_days_valido_o_null(): void
    {
        $this->assertSame(1, P::dias('1'));
        $this->assertSame(21, P::dias(' 21 '));
        $this->assertSame(0, P::dias('0'));
        foreach (['', null, '-3', 'abc', '2.5', '61'] as $malo) {
            $this->assertNull(P::dias($malo), var_export($malo, true));
        }
    }

    public function test_fecha_de_corte_canonica_y_fechas_invalidas(): void
    {
        $this->assertSame('2026-11-07', P::corte('2026-11-07 23:59:59')->toDateString());
        $this->assertSame('2026-11-07', P::corte('2026-11-07')->toDateString());
        $this->assertSame('2028-02-29', P::corte('2028-02-29 10:00')->toDateString());
        foreach (['', null, '1', '31', '2026-02-30 00:00:00', '2027-02-29', '07/11/2026', 'mañana'] as $malo) {
            $this->assertNull(P::corte($malo), var_export($malo, true));
        }
    }

    public function test_periodo_es_el_mes_del_corte(): void
    {
        $this->assertSame('2026-11', P::periodo(P::corte('2026-11-30 23:59:59')));
        $this->assertSame('2027-01', P::periodo(P::corte('2027-01-01 00:00:00')));
    }

    public function test_regla_actual_dia_25_del_mes_anterior_con_cambio_de_anio(): void
    {
        $this->assertSame('2026-10-25', P::fechaActual(P::corte('2026-11-07'))->toDateString());
        $this->assertSame('2025-12-25', P::fechaActual(P::corte('2026-01-15'))->toDateString());
        $this->assertSame('2026-02-25', P::fechaActual(P::corte('2026-03-31'))->toDateString(), 'marzo 31 no desborda a febrero');
    }

    public function test_regla_nueva_dias_naturales_en_meses_cortos_y_bisiestos(): void
    {
        $this->assertSame('2026-03-01', P::fechaNueva(P::corte('2026-03-31'), 30)->toDateString());
        $this->assertSame('2026-02-28', P::fechaNueva(P::corte('2026-03-01'), 1)->toDateString());
        $this->assertSame('2028-02-29', P::fechaNueva(P::corte('2028-03-01'), 1)->toDateString());
        $this->assertSame('2026-12-31', P::fechaNueva(P::corte('2027-01-21'), 21)->toDateString());
        $this->assertSame('2026-11-30', P::fechaNueva(P::corte('2026-11-30'), 0)->toDateString(), 'N=0 → el mismo día del corte');
    }

    public function test_toca_hoy_sin_retroactivo(): void
    {
        $corte = P::corte('2026-11-07');
        $this->assertFalse(P::tocaHoy($corte, 3, Carbon::parse('2026-11-03')));
        $this->assertTrue(P::tocaHoy($corte, 3, Carbon::parse('2026-11-04 15:30')));
        $this->assertTrue(P::tocaHoy($corte, 3, Carbon::parse('2026-11-07')), 'un cron atrasado aún la crea el día del corte');
        $this->assertFalse(P::tocaHoy($corte, 3, Carbon::parse('2026-11-08')), 'corte pasado: no se recupera');
    }

    public function test_comparar_contra_la_regla_actual(): void
    {
        $hoy = Carbon::parse('2026-10-01');
        $this->assertSame([P::IGUAL, 0], P::comparar(P::corte('2026-11-07'), 13, $hoy));      // 10-25 vs 10-25
        $this->assertSame([P::ADELANTADA, -5], P::comparar(P::corte('2026-11-07'), 18, $hoy)); // 10-20
        $this->assertSame([P::ATRASADA, 12], P::comparar(P::corte('2026-11-07'), 1, $hoy));    // 11-06
        // Las dos reglas se acotan a «no antes de hoy» (el cron no recupera días).
        $this->assertSame([P::IGUAL, 0], P::comparar(P::corte('2026-10-20'), 30, Carbon::parse('2026-10-16')));
    }

    public function test_anticipacion_actual(): void
    {
        $this->assertSame(7, P::anticipacionActual(P::corte('2026-11-01')));
        $this->assertSame(36, P::anticipacionActual(P::corte('2026-11-30')));
        $this->assertSame(34, P::anticipacionActual(P::corte('2026-03-31')));
    }
}
