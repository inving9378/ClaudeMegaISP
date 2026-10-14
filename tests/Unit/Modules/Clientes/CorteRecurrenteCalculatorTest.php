<?php

namespace Tests\Unit\Modules\Clientes;

use App\Modules\Core\Clientes\Services\CorteRecurrenteCalculator as C;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Regla de corte de recurrentes (2026-10-14). PHPUnit puro: sin Laravel, sin base de datos.
 * billing_date=13, billing_expiration=1 => el corte cae el día 14.
 */
class CorteRecurrenteCalculatorTest extends TestCase
{
    private function corte(string $f, int $meses): string
    {
        return C::nuevoCorte(Carbon::parse($f), $meses, 13, 1)->toDateString();
    }

    public function test_ejemplo_debe_3_meses_paga_2_corte_sube_dos_meses(): void
    {
        $this->assertSame('2026-09-14', $this->corte('2026-07-14 23:59:59', 2));
    }

    public function test_ejemplo_paga_3_meses_corte_queda_en_octubre_sin_servicio_hoy_14_10(): void
    {
        $this->assertSame('2026-10-14', $this->corte('2026-07-14 23:59:59', 3));
    }

    public function test_ejemplo_paga_4_meses_corte_pasa_a_noviembre(): void
    {
        $this->assertSame('2026-11-14', $this->corte('2026-07-14 23:59:59', 4));
    }

    public function test_cliente_3712_un_mes_no_salta_dos(): void
    {
        $this->assertSame('2026-11-14', $this->corte('2026-10-14 23:59:59', 1));
    }

    public function test_6538_dos_mensualidades_desde_10_14(): void
    {
        $this->assertSame('2026-12-14', $this->corte('2026-10-14 23:59:59', 2));
    }

    public function test_corte_con_hora_manual_se_normaliza_al_final_del_dia(): void
    {
        $this->assertSame('2026-12-14 23:59:59', C::nuevoCorte(Carbon::parse('2026-11-14 16:31'), 1, 13, 1)->toDateTimeString());
    }

    public function test_dia_31_no_desborda_meses_cortos(): void
    {
        // billing_date 31, sin tolerancia: de enero a febrero cae el 28.
        $this->assertSame('2027-02-28', C::nuevoCorte(Carbon::parse('2027-01-31'), 1, 31, 0)->toDateString());
        // y a marzo vuelve al 31 (no se queda en 28).
        $this->assertSame('2027-03-31', C::nuevoCorte(Carbon::parse('2027-01-31'), 2, 31, 0)->toDateString());
    }

    public function test_ciclos_pendientes_y_saldados(): void
    {
        $this->assertSame(0, C::ciclosPendientes(0, 449));
        $this->assertSame(0, C::ciclosPendientes(20, 449));
        $this->assertSame(1, C::ciclosPendientes(-449, 449));
        $this->assertSame(2, C::ciclosPendientes(-449.01, 449));
        $this->assertSame(3, C::ciclosPendientes(-1347, 449));
        // debe 3 (-1347), paga 2 (898) => queda -449: saldó 2
        $this->assertSame(2, C::ciclosSaldados(-1347, -449, 449));
        // debe 4, paga 3 => queda 1 pendiente: saldó 3 y sigue con deuda
        $this->assertSame(3, C::ciclosSaldados(-1796, -449, 449));
        // paga todo y le sobra: saldó los 3 (lo sobrante lo cobra el flujo normal)
        $this->assertSame(3, C::ciclosSaldados(-1347, 20, 449));
        // sin deuda previa: no hay ciclos saldados
        $this->assertSame(0, C::ciclosSaldados(0, 449, 449));
        // pago parcial que no completa ni un ciclo
        $this->assertSame(0, C::ciclosSaldados(-449, -300, 449));
    }
}
