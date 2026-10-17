<?php

namespace Tests\Unit\Services;

use App\Services\Finance\Invoice\EnvioPrefactura;
use PHPUnit\Framework\TestCase;

/** FR-05 (#539): días de «Envío prefactura» y día de facturación. Puro: no toca la BD. */
class EnvioPrefacturaTest extends TestCase
{
    public function test_dias_del_cliente_o_el_general(): void
    {
        $this->assertSame(5, EnvioPrefactura::diasDe(null, 5));
        $this->assertSame(5, EnvioPrefactura::diasDe((object) ['envio_prefactura_dias' => null], 5), 'vacío = general');
        $this->assertSame(3, EnvioPrefactura::diasDe((object) ['envio_prefactura_dias' => 3], 5));
        $this->assertSame(0, EnvioPrefactura::diasDe((object) ['envio_prefactura_dias' => 0], 5), '0 = el mismo día');
        $this->assertSame(5, EnvioPrefactura::diasDe((object) ['envio_prefactura_dias' => 99], 5), 'fuera de rango = general');
    }

    public function test_dia_de_facturacion_por_tipo(): void
    {
        $cliente = (object) ['fecha_pago' => '2026-11-11 00:00:00', 'fecha_corte' => '2026-11-12 23:59:59'];

        $this->assertSame('2026-11-11', EnvioPrefactura::fechaDeFacturacion($cliente, (object) ['type_billing_id' => 1])->toDateString(), 'recurrente: fecha de pago');
        $this->assertSame('2026-11-12', EnvioPrefactura::fechaDeFacturacion($cliente, (object) ['type_billing_id' => 3])->toDateString(), 'personalizado: fecha de corte');
        $this->assertSame('2026-11-12', EnvioPrefactura::fechaDeFacturacion($cliente, null)->toDateString());
        $this->assertNull(EnvioPrefactura::fechaDeFacturacion((object) ['fecha_pago' => '1', 'fecha_corte' => null], (object) ['type_billing_id' => 1]));
    }
}
