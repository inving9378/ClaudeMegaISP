<?php

namespace Tests\Unit\Talento;

use App\Modules\Addons\Talento\Support\PayWeek;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO toca BD, NO migrate:fresh.

/**
 * Tests unitarios del helper PayWeek (ventana de la semana de pago).
 * Cutover de referencia = go-live real: sábado 2026-07-11 18:00.
 *   - Semana de transición (8 días): [2026-07-04 00:00 → 2026-07-11 18:00] (regime 'transition').
 *   - Primera semana nueva: [2026-07-11 18:00:01 → 2026-07-18 18:00:00].
 *   - Estado estable NUEVO se prueba con una semana de agosto lejos del borde.
 *
 * ⚠️ Validar con tinker (regla del proyecto: no correr el test runner contra la BD).
 *    Este archivo queda como especificación ejecutable / CI aislado.
 */
class PayWeekTest extends TestCase
{
    private function cutover(): Carbon
    {
        return Carbon::parse('2026-07-11 18:00:00');
    }

    private function bounds(string $instant): array
    {
        return PayWeek::boundsFor(Carbon::parse($instant), $this->cutover(), 18, 0);
    }

    // ───────────────────────── Estado estable: ventana NUEVA (agosto) ─────────────────────────

    /** Sábado 18:00 EXACTO cierra la semana nueva (corte inclusivo). */
    public function test_nueva_sabado_18_00_cierra_la_semana(): void
    {
        $b = $this->bounds('2026-08-08 18:00:00');
        $this->assertSame('new', $b['regime']);
        $this->assertSame('2026-08-01', $b['period_start']);
        $this->assertSame('2026-08-08', $b['period_end']);
    }

    /** Sábado 18:00:01 pasa a la semana siguiente. */
    public function test_nueva_sabado_despues_del_corte_pasa_a_la_siguiente(): void
    {
        $b = $this->bounds('2026-08-08 18:00:01');
        $this->assertSame('2026-08-08', $b['period_start']);
        $this->assertSame('2026-08-15', $b['period_end']);
    }

    /** Domingo cae en la semana que cierra el próximo sábado. */
    public function test_nueva_domingo(): void
    {
        $b = $this->bounds('2026-08-09 10:00:00');
        $this->assertSame('2026-08-08', $b['period_start']);
        $this->assertSame('2026-08-15', $b['period_end']);
    }

    /** El filtro nuevo es inclusivo: apertura 18:00:01, cierre 18:00:00. */
    public function test_nueva_instantes_del_filtro(): void
    {
        $b = $this->bounds('2026-08-05 12:00:00');
        $this->assertSame('2026-08-01 18:00:01', $b['start_instant']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-08 18:00:00', $b['end_instant']->format('Y-m-d H:i:s'));
    }

    // ───────────────────────── Semana de TRANSICIÓN (8 días) ─────────────────────────

    /** Viernes 10-jul cae en la semana de transición. */
    public function test_transicion_viernes(): void
    {
        $b = $this->bounds('2026-07-10 12:00:00');
        $this->assertSame('transition', $b['regime']);
        $this->assertSame('2026-07-04', $b['period_start']);
        $this->assertSame('2026-07-11', $b['period_end']);
    }

    /** Sábado 04 (inicio de la transición) también cae en ella. */
    public function test_transicion_sabado_inicio(): void
    {
        $b = $this->bounds('2026-07-04 00:00:00');
        $this->assertSame('transition', $b['regime']);
        $this->assertSame('2026-07-04', $b['period_start']);
        $this->assertSame('2026-07-11', $b['period_end']);
    }

    /** Sábado 11 antes del corte → transición (aún vieja). */
    public function test_transicion_sabado_11_antes_del_corte(): void
    {
        foreach (['2026-07-11 10:00:00', '2026-07-11 17:59:00', '2026-07-11 18:00:00'] as $t) {
            $b = $this->bounds($t);
            $this->assertSame('transition', $b['regime'], "instante $t");
            $this->assertSame('2026-07-04', $b['period_start'], "instante $t");
            $this->assertSame('2026-07-11', $b['period_end'], "instante $t");
        }
    }

    /** Sábado 11 DESPUÉS del corte (18:00:01) → primera semana NUEVA. */
    public function test_transicion_sabado_11_despues_del_corte_es_nueva(): void
    {
        $b = $this->bounds('2026-07-11 18:00:01');
        $this->assertSame('new', $b['regime']);
        $this->assertSame('2026-07-11', $b['period_start']);
        $this->assertSame('2026-07-18', $b['period_end']);
    }

    /** La transición es de 8 días: filtro [07-04 00:00:00, 07-11 18:00:00]. */
    public function test_transicion_instantes_de_filtro_8_dias(): void
    {
        $b = $this->bounds('2026-07-07 12:00:00');
        $this->assertSame('2026-07-04 00:00:00', $b['start_instant']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-11 18:00:00', $b['end_instant']->format('Y-m-d H:i:s'));
    }

    // ───────────────────────── Legacy anterior a la transición (INTACTO) ─────────────────────────

    /** Antes de la semana de transición: legacy Sáb 00:00 → Vie 23:59, sin cambios. */
    public function test_legacy_antes_de_la_transicion(): void
    {
        $b = $this->bounds('2026-07-01 12:00:00'); // miércoles de la semana [06-27 → 07-03]
        $this->assertSame('legacy', $b['regime']);
        $this->assertSame('2026-06-27', $b['period_start']);
        $this->assertSame('2026-07-03', $b['period_end']);
        $this->assertSame('2026-06-27 00:00:00', $b['start_instant']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-03 23:59:59', $b['end_instant']->format('Y-m-d H:i:s'));
    }
}
