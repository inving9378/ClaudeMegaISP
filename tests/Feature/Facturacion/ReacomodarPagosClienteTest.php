<?php

namespace Tests\Feature\Facturacion;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesApplication;

/**
 * Item #571: facturacion:reacomodar-pagos con el mismo caso que motivó el comando (datos
 * ficticios): un pago quedó sin factura, el siguiente pagó dos (una de ellas sin dinero) y desde
 * ahí cada pago cayó en el mes anterior. Corre contra la BD de pruebas dentro de una transacción
 * que se revierte, con el disco de respaldos falso.
 */
class ReacomodarPagosClienteTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private int $cliente;
    private array $f = [];
    private array $p = [];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');

        $usuario = (int) DB::table('users')->value('id');
        $this->cliente = DB::table('clients')->insertGetId([
            'fecha_corte' => '2026-07-04 23:59:59', 'created_by' => $usuario, 'updated_by' => $usuario,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('client_main_information')->insert([
            'client_id' => $this->cliente, 'name' => 'CLIENTE SINTÉTICO REACOMODO', 'email' => "reacomodo-{$this->cliente}@example.test",
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->p['mar'] = $this->pago('2026-03-01 10:00:00', '2026-02');
        $this->p['abr'] = $this->pago('2026-04-02 10:00:00', '2026-02,2026-03');
        $this->p['may'] = $this->pago('2026-05-03 10:00:00', '2026-04');
        $this->p['jun'] = $this->pago('2026-06-04 10:00:00', '2026-05');

        $this->f['feb'] = $this->factura('2026-02', Invoice::STATUS_PAID, $this->p['abr']);
        $this->f['mar'] = $this->factura('2026-03', Invoice::STATUS_PAID, $this->p['abr']);
        $this->f['abr'] = $this->factura('2026-04', Invoice::STATUS_PAID, $this->p['may']);
        $this->f['may'] = $this->factura('2026-05', Invoice::STATUS_PAID, $this->p['jun']);
        $this->f['jun'] = $this->factura('2026-06', Invoice::STATUS_DRAFT, null);
    }

    private function pago(string $fecha, string $periodo): int
    {
        return Payment::withoutEvents(fn () => Payment::create([
            'payment_method_id' => 1, 'date' => $fecha, 'amount' => 200, 'payment_period' => $periodo,
            'receipt' => 'REACOMODO', 'add_by' => (int) DB::table('users')->value('id'),
            'paymentable_id' => $this->cliente, 'paymentable_type' => 'App\\Models\\Client',
        ]))->id;
    }

    private function factura(string $periodo, string $status, ?int $pago): int
    {
        return Invoice::create([
            'number' => "REAC-{$this->cliente}-{$periodo}-" . uniqid(), 'client_id' => $this->cliente, 'type' => Invoice::TYPE_PROFORMA,
            'period' => $periodo, 'subtotal' => 172.41, 'tax' => 27.59, 'total' => 200, 'status' => $status,
            'pending_balance' => $status === Invoice::STATUS_PAID ? 0 : 200, 'payment_id' => $pago,
            'due_date' => $periodo . '-04',
        ])->id;
    }

    private function reacomodar(array $extra = []): int
    {
        return $this->artisan('facturacion:reacomodar-pagos', [
            'cliente' => $this->cliente, '--desde' => '2026-02', '--pagos-desde' => '2026-03-01',
            '--anular' => [$this->f['mar']], '--motivo' => 'Prueba de reacomodo',
        ] + $extra)->run();
    }

    private function estado(): array
    {
        return json_decode(json_encode([
            'facturas' => DB::table('invoices')->where('client_id', $this->cliente)->orderBy('id')->get(['id', 'status', 'payment_id', 'pending_balance', 'payment_date', 'notes'])->toArray(),
            'pagos' => DB::table('payments')->where('paymentable_id', $this->cliente)->orderBy('id')->get(['id', 'amount', 'payment_period'])->toArray(),
            'saldo' => DB::table('balances')->where('balanceable_id', $this->cliente)->value('amount'),
            'movimientos' => DB::table('transactions')->where('client_id', $this->cliente)->count(),
        ]), true);
    }

    private function deshacer(): void
    {
        $sql = Storage::disk('local')->files('respaldos/facturacion');
        $archivo = collect($sql)->first(fn ($a) => str_ends_with($a, '-deshacer.sql'));
        $this->assertNotNull($archivo, 'Debe quedar el SQL para deshacer.');
        foreach (explode("\n", Storage::disk('local')->get($archivo)) as $sentencia) {
            if (trim($sentencia) !== '' && ! str_starts_with($sentencia, '--')) {
                DB::unprepared($sentencia);
            }
        }
    }

    public function test_simulacion_no_escribe_nada(): void
    {
        $antes = $this->estado();
        $this->assertSame(0, $this->reacomodar(['--dry-run' => true]));
        $this->assertSame($antes, $this->estado());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_cada_pago_queda_en_su_mes_y_la_factura_sin_dinero_se_cancela(): void
    {
        $antes = $this->estado();
        $this->assertSame(0, $this->reacomodar());

        $liga = fn ($f) => (int) Invoice::find($this->f[$f])->payment_id;
        $this->assertSame($this->p['mar'], $liga('feb'));
        $this->assertSame($this->p['abr'], $liga('abr'));
        $this->assertSame($this->p['may'], $liga('may'));
        $this->assertSame($this->p['jun'], $liga('jun'));
        $this->assertSame(Invoice::STATUS_PAID, Invoice::find($this->f['jun'])->status);

        $marzo = Invoice::find($this->f['mar']);
        $this->assertSame(Invoice::STATUS_CANCELLED, $marzo->status);
        $this->assertNull($marzo->payment_id);
        $this->assertStringContainsString('Prueba de reacomodo', $marzo->notes);

        $this->assertSame('2026-04', Payment::find($this->p['abr'])->payment_period);
        $this->assertSame('2026-06', Payment::find($this->p['jun'])->payment_period);

        $archivos = Storage::disk('local')->allFiles('respaldos/facturacion');
        $this->assertCount(3, $archivos, 'Respaldo JSON, SQL para deshacer y reporte.');

        $this->deshacer();
        $this->assertSame($antes, $this->estado(), 'El SQL para deshacer deja todo como estaba.');
    }

    public function test_monto_real_igual_al_pago_corrige_el_saldo_con_un_movimiento(): void
    {
        DB::table('balances')->insert(['amount' => 149, 'balanceable_id' => $this->cliente, 'balanceable_type' => 'App\\Models\\Client']);
        DB::table('transactions')->insert([
            'date' => '2026-05-03 10:00:00', 'cantidad' => '1', 'credit' => 349, 'account_balance' => 349, 'client_id' => $this->cliente,
            'price' => 349, 'iva' => 0, 'company_balance' => 349, 'movement' => '+ 349', 'add_to_invoice' => 0,
            'transactionable_id' => $this->cliente, 'transactionable_type' => 'App\\Models\\Client',
            'type' => 'credit', 'category' => 'Pago', 'is_payment' => 1, 'payment_id' => $this->p['may'],
            'total' => 349, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $antes = $this->estado();

        $this->assertSame(0, $this->reacomodar(['--monto-pago' => [$this->p['may'] . '=200']]));

        $this->assertEquals(0, (float) DB::table('balances')->where('balanceable_id', $this->cliente)->value('amount'));
        $correccion = DB::table('transactions')->where('client_id', $this->cliente)->where('category', 'Correccion')->first();
        $this->assertNotNull($correccion);
        $this->assertEquals(149, (float) $correccion->debit);
        $this->assertEquals(200, (float) Payment::find($this->p['may'])->amount);

        $this->deshacer();
        $this->assertSame($antes, $this->estado());
    }

    public function test_monto_real_igual_al_estado_de_cuenta_corrige_el_pago_y_no_el_saldo(): void
    {
        DB::table('balances')->insert(['amount' => 149, 'balanceable_id' => $this->cliente, 'balanceable_type' => 'App\\Models\\Client']);
        DB::table('transactions')->insert([
            'date' => '2026-05-03 10:00:00', 'cantidad' => '1', 'credit' => 349, 'account_balance' => 349, 'client_id' => $this->cliente,
            'price' => 349, 'iva' => 0, 'company_balance' => 349, 'movement' => '+ 349', 'add_to_invoice' => 0,
            'transactionable_id' => $this->cliente, 'transactionable_type' => 'App\\Models\\Client',
            'type' => 'credit', 'category' => 'Pago', 'is_payment' => 1, 'payment_id' => $this->p['may'],
            'total' => 349, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(0, $this->reacomodar(['--monto-pago' => [$this->p['may'] . '=349']]));

        $this->assertEquals(349, (float) Payment::find($this->p['may'])->amount);
        $this->assertEquals(149, (float) DB::table('balances')->where('balanceable_id', $this->cliente)->value('amount'));
        $this->assertSame(0, DB::table('transactions')->where('client_id', $this->cliente)->where('category', 'Correccion')->count());
    }

    public function test_se_detiene_si_quedan_dos_facturas_del_mismo_periodo(): void
    {
        $this->factura('2026-04', Invoice::STATUS_DRAFT, null);
        $antes = $this->estado();

        $this->assertSame(1, $this->reacomodar());
        $this->assertSame($antes, $this->estado());
    }
}
