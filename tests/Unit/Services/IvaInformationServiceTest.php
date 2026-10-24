<?php

namespace Tests\Unit\Services;

use App\Services\IvaInformationService;
use PHPUnit\Framework\TestCase;

/** Desglose de IVA de un precio con IVA incluido: base + IVA debe dar el precio exacto. Sin BD. */
class IvaInformationServiceTest extends TestCase
{
    private function desglose(float $total, float $tasa = 16): array
    {
        return (new IvaInformationService($tasa, $total))->getIvaInformation();
    }

    public function test_99_ya_no_pierde_el_centavo(): void
    {
        $r = $this->desglose(99);
        $this->assertSame(85.34, $r['monto']);
        $this->assertSame(13.66, $r['iva']);
        $this->assertSame(99.00, round($r['monto'] + $r['iva'], 2));
    }

    public function test_base_mas_iva_da_el_total_en_todos_los_precios(): void
    {
        for ($centavos = 1; $centavos <= 300000; $centavos += 7) {
            $total = (float) ($centavos / 100);
            $r = $this->desglose($total);
            $this->assertSame($total, round($r['monto'] + $r['iva'], 2), "total {$total}");
            // Tolerancia del SAT: el IVA no se aleja más de un centavo de Tasa * Base.
            $this->assertLessThanOrEqual(0.01, abs($r['iva'] - 0.16 * $r['monto']), "total {$total}");
        }
    }

    public function test_precios_negativos_y_sin_iva(): void
    {
        $r = $this->desglose(-99);
        $this->assertSame(-99.00, round($r['monto'] + $r['iva'], 2));

        $r = $this->desglose(99, 0);
        $this->assertSame(99.00, (float) $r['monto']);
        $this->assertSame(0.00, (float) $r['iva']);
    }
}
