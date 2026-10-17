<?php

namespace Tests\Unit\Services;

use App\Models\Invoice;
use App\Services\Finance\Billing\ReglasFacturacion as R;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/** FR-06 (#540) y FR-10: documento del mes según el estado y candado de timbrado. Puro, sin BD. */
class DocumentoPorEstadoTest extends TestCase
{
    private function doc(?string $estado, float $adeudo = 0, ?string $inactivoDesde = null, string $hoy = '2026-11-01'): ?string
    {
        return R::documentoDelMes($estado, $adeudo, $inactivoDesde ? Carbon::parse($inactivoDesde) : null, Carbon::parse($hoy));
    }

    public function test_los_cinco_estados(): void
    {
        $this->assertNull($this->doc('Nuevo', 500));
        $this->assertSame(R::DOC_PREFACTURA, $this->doc('Activo'));
        $this->assertSame(R::DOC_ESTADO_CUENTA, $this->doc('Bloqueado'));
        $this->assertSame(R::DOC_ESTADO_CUENTA, $this->doc('Bloqueado', 0), 'bloqueado siempre, deba o no');
        $this->assertSame(R::DOC_ESTADO_CUENTA, $this->doc('Inactivo', 349, '2026-06-15'));
        $this->assertNull($this->doc('Cancelado', 349), 'cancelado: solo el final, al cancelarse');
        $this->assertNull($this->doc(null));
    }

    public function test_inactivo_solo_con_adeudo_y_hasta_12_meses(): void
    {
        $this->assertNull($this->doc('Inactivo', 0, '2026-06-15'), 'sin adeudo');
        $this->assertNull($this->doc('Inactivo', 349, '2025-10-31'), 'más de 12 meses');
        $this->assertSame(R::DOC_ESTADO_CUENTA, $this->doc('Inactivo', 349, '2025-11-01'), 'justo 12 meses');
        $this->assertNull($this->doc('Inactivo', 349, null), 'sin fecha de inactivación conocida');
    }

    public function test_fin_de_mes(): void
    {
        // 31 de marzo menos 12 meses = 31 de marzo del año anterior; 29 feb bisiesto → 28 feb.
        $this->assertSame(R::DOC_ESTADO_CUENTA, $this->doc('Inactivo', 10, '2025-03-31', '2026-03-31'));
        $this->assertNull($this->doc('Inactivo', 10, '2025-03-30', '2026-03-31'));
        $this->assertSame(R::DOC_ESTADO_CUENTA, $this->doc('Inactivo', 10, '2027-02-28', '2028-02-29'));
    }

    public function test_solo_se_timbra_una_factura_pagada(): void
    {
        $f = fn ($status, $saldo) => tap(new Invoice(), function ($i) use ($status, $saldo) {
            $i->status = $status;
            $i->pending_balance = $saldo;
        });

        $this->assertTrue(R::puedeTimbrar($f(Invoice::STATUS_PAID, 0)));
        $this->assertFalse(R::puedeTimbrar($f(Invoice::STATUS_PARTIALLY_PAID, 100)));
        $this->assertFalse(R::puedeTimbrar($f(Invoice::STATUS_DRAFT, 349)), 'prefactura');
        $this->assertFalse(R::puedeTimbrar($f(Invoice::STATUS_PAID, 1)), 'pagada con saldo pendiente: no');
        $this->assertFalse(R::puedeTimbrar(null));
    }
}
