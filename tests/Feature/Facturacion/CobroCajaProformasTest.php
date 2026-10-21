<?php

namespace Tests\Feature\Facturacion;

use App\Models\Invoice;
use App\Models\Payment;
use App\Modules\Core\Clientes\Models\Client;
use App\Services\Finance\Invoice\AvailablePeriodsService;
use App\Services\Finance\Invoice\InvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\CreatesApplication;

/**
 * Item #571: las proformas que crea el cobro en caja (InvoiceService::
 * updateProformaInvoicePendingDespuesDeUnPago). Antes nacían con saldo 0 y quedaban pagadas sin
 * dinero, vencían en la fecha de corte del día del cobro y se creaba otra factura de un periodo
 * ya pagado. Corre contra la BD de pruebas dentro de una transacción que se revierte; los pagos
 * se crean sin eventos (sin jobs ni tickets) y el costo del periodo se fija en 200.
 */
class CobroCajaProformasTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private InvoiceService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->servicio = new class extends InvoiceService {
            protected function montosDelCliente(Client $client): array
            {
                return ['subtotal' => 172.41, 'tax' => 27.59, 'total' => 200.00, 'services' => []];
            }
        };
    }

    // ─── Fixtures sintéticos ────────────────────────────────────────────────────

    private function cliente(string $fechaCorte = '2026-11-06 23:59:59'): Client
    {
        $usuario = (int) DB::table('users')->value('id');
        $id = DB::table('clients')->insertGetId([
            'fecha_corte' => $fechaCorte, 'created_by' => $usuario, 'updated_by' => $usuario,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('client_main_information')->insert([
            'client_id' => $id, 'name' => 'CLIENTE SINTÉTICO 571', 'email' => "item571-{$id}@example.test",
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Client::find($id);
    }

    private function proforma(Client $cliente, string $periodo, string $status = Invoice::STATUS_DRAFT): Invoice
    {
        $pagada = $status === Invoice::STATUS_PAID;

        return Invoice::create([
            'number' => "T571-{$cliente->id}-{$periodo}-" . uniqid(), 'client_id' => $cliente->id,
            'type' => Invoice::TYPE_PROFORMA, 'period' => $periodo, 'subtotal' => 172.41, 'tax' => 27.59,
            'total' => 200.00, 'pending_balance' => $pagada ? 0 : 200.00, 'status' => $status,
            'due_date' => $periodo . '-06',
        ]);
    }

    private function pago(Client $cliente, float $monto, string $periodos): Payment
    {
        return Payment::withoutEvents(fn () => Payment::create([
            'payment_method_id' => 1, 'date' => now()->format('Y-m-d H:i:s'), 'amount' => $monto,
            'payment_period' => $periodos, 'receipt' => 'T571', 'add_by' => (int) DB::table('users')->value('id'),
            'paymentable_id' => $cliente->id, 'paymentable_type' => 'App\\Models\\Client',
        ]));
    }

    private function proformasDe(Client $cliente, string $periodo)
    {
        return Invoice::where('client_id', $cliente->id)->where('period', $periodo)->orderBy('id')->get();
    }

    // ─── Casos ──────────────────────────────────────────────────────────────────

    public function test_periodo_faltante_nace_debiendo_y_solo_se_paga_con_dinero(): void
    {
        $cliente = $this->cliente();

        $this->servicio->updateProformaInvoicePendingDespuesDeUnPago($cliente, $this->pago($cliente, 100, '2026-12'));

        $facturas = $this->proformasDe($cliente, '2026-12');
        $this->assertCount(1, $facturas);
        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $facturas[0]->status, 'Con 100 de 200 debe quedar parcial, no pagada.');
        $this->assertEquals(100.00, (float) $facturas[0]->pending_balance);
    }

    public function test_periodo_faltante_vence_en_el_dia_de_corte_de_su_mes(): void
    {
        $cliente = $this->cliente('2026-11-06 23:59:59');
        $this->servicio->updateProformaInvoicePendingDespuesDeUnPago($cliente, $this->pago($cliente, 200, '2027-01'));
        $this->assertSame('2027-01-06', substr((string) $this->proformasDe($cliente, '2027-01')[0]->due_date, 0, 10));

        // Día 30 en febrero: se recorta al último día del mes.
        $otro = $this->cliente('2026-11-30 23:59:59');
        $this->servicio->updateProformaInvoicePendingDespuesDeUnPago($otro, $this->pago($otro, 200, '2027-02'));
        $factura = $this->proformasDe($otro, '2027-02')[0];
        $this->assertSame('2027-02-28', substr((string) $factura->due_date, 0, 10));
        $this->assertSame(Invoice::STATUS_PAID, $factura->status);
    }

    public function test_pago_que_no_alcanza_no_crea_la_factura_del_segundo_periodo(): void
    {
        $cliente = $this->cliente();
        $octubre = $this->proforma($cliente, '2026-10');

        $resultado = $this->servicio->updateProformaInvoicePendingDespuesDeUnPago($cliente, $this->pago($cliente, 200, '2026-10,2026-11'));

        $this->assertSame(['tipo' => 'aplicado', 'texto' => 'Pago aplicado a octubre 2026.'], $this->servicio->avisoDeAplicacion($resultado));
        $this->assertSame(Invoice::STATUS_PAID, $octubre->fresh()->status);
        $this->assertCount(0, $this->proformasDe($cliente, '2026-11'), 'Sin dinero no debe nacer la de noviembre.');
    }

    public function test_periodo_ya_pagado_no_recibe_otra_factura_y_el_dinero_va_al_siguiente_sin_pagar(): void
    {
        $cliente = $this->cliente();
        $this->proforma($cliente, '2026-09', Invoice::STATUS_PAID);
        $octubre = $this->proforma($cliente, '2026-10');
        $pago = $this->pago($cliente, 200, '2026-09');

        $resultado = $this->servicio->updateProformaInvoicePendingDespuesDeUnPago($cliente, $pago);

        $this->assertCount(1, $this->proformasDe($cliente, '2026-09'), 'No debe nacer otra de septiembre.');
        $this->assertSame([['pedido' => '2026-09', 'aplicado' => '2026-10']], $resultado['periodos_movidos']);
        $this->assertSame(
            ['tipo' => 'movido', 'texto' => 'Septiembre 2026 ya estaba pagado. El pago se aplicó a octubre 2026.'],
            $this->servicio->avisoDeAplicacion($resultado),
            'La ventana de caja avisa a qué mes pasó el dinero.'
        );
        $this->assertSame(Invoice::STATUS_PAID, $octubre->fresh()->status);
        $this->assertSame($pago->id, (int) $octubre->fresh()->payment_id);
        $this->assertSame('2026-10', $pago->fresh()->payment_period, 'El pago dice dónde quedó su dinero.');
    }

    public function test_dos_periodos_con_uno_pagado_en_medio_no_duplica_y_sigue_en_orden(): void
    {
        $cliente = $this->cliente();
        $octubre = $this->proforma($cliente, '2026-10');
        $this->proforma($cliente, '2026-11', Invoice::STATUS_PAID);
        $pago = $this->pago($cliente, 400, '2026-10,2026-11');

        $this->servicio->updateProformaInvoicePendingDespuesDeUnPago($cliente, $pago);

        $this->assertSame(Invoice::STATUS_PAID, $octubre->fresh()->status);
        $this->assertCount(1, $this->proformasDe($cliente, '2026-11'));
        $diciembre = $this->proformasDe($cliente, '2026-12');
        $this->assertCount(1, $diciembre);
        $this->assertSame(Invoice::STATUS_PAID, $diciembre[0]->status);
        $this->assertSame('2026-10,2026-12', $pago->fresh()->payment_period);
    }

    public function test_factura_cancelada_no_se_reusa_ni_se_propone_como_pendiente(): void
    {
        $cliente = $this->cliente();
        $this->proforma($cliente, '2026-09', Invoice::STATUS_PAID);
        $cancelada = $this->proforma($cliente, '2026-10', Invoice::STATUS_CANCELLED);
        $noviembre = $this->proforma($cliente, '2026-11');

        $periodos = (new AvailablePeriodsService())->getAvailablePeriodsByClient($cliente->id);
        $this->assertSame('2026-11', $periodos[0]['period'], 'La cancelada de octubre no es un pendiente.');

        $this->servicio->updateProformaInvoicePendingDespuesDeUnPago($cliente, $this->pago($cliente, 200, '2026-11'));
        $this->assertSame(Invoice::STATUS_CANCELLED, $cancelada->fresh()->status);
        $this->assertSame(Invoice::STATUS_PAID, $noviembre->fresh()->status);
    }
}
