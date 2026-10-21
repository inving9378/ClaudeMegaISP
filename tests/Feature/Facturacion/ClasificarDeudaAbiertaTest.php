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
 * Item #571 (fase 3): facturacion:clasificar-deuda separa la deuda real de la fantasma y arma el
 * lote; facturacion:reacomodar-pagos-lote solo escribe las filas aprobadas. Datos ficticios en
 * la BD de pruebas, dentro de una transacción que se revierte; disco de reportes falso.
 */
class ClasificarDeudaAbiertaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
    }

    private function cliente(string $corte, float $saldo): int
    {
        $usuario = (int) DB::table('users')->value('id');
        $id = DB::table('clients')->insertGetId(['fecha_corte' => $corte, 'created_by' => $usuario, 'updated_by' => $usuario, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('client_main_information')->insert(['client_id' => $id, 'name' => 'SINTÉTICO CLASIFICA', 'estado' => 'Activo', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('balances')->insert(['amount' => $saldo, 'balanceable_id' => $id, 'balanceable_type' => 'App\\Models\\Client']);

        return $id;
    }

    private function pago(int $cliente, string $fecha): int
    {
        return Payment::withoutEvents(fn () => Payment::create([
            'payment_method_id' => 1, 'date' => $fecha, 'amount' => 449, 'payment_period' => substr($fecha, 0, 7),
            'receipt' => 'CLASIFICA', 'add_by' => (int) DB::table('users')->value('id'),
            'paymentable_id' => $cliente, 'paymentable_type' => 'App\\Models\\Client',
        ]))->id;
    }

    private function factura(int $cliente, string $periodo, string $vence, string $status, float $pendiente, ?int $pago = null): int
    {
        return Invoice::create([
            'number' => "CLAS-{$cliente}-{$periodo}-" . uniqid(), 'client_id' => $cliente, 'type' => Invoice::TYPE_PROFORMA,
            'period' => $periodo, 'subtotal' => 387.07, 'tax' => 61.93, 'total' => 449, 'status' => $status,
            'pending_balance' => $pendiente, 'payment_id' => $pago, 'due_date' => $vence,
        ])->id;
    }

    private function clasificar(array $clientes): array
    {
        $this->artisan('facturacion:clasificar-deuda', ['--cliente' => $clientes, '--hoy' => '2026-10-15'])->run();
        $archivos = collect(Storage::disk('local')->allFiles('reportes/facturacion'));
        $leer = function (?string $archivo) {
            $lineas = array_map('str_getcsv', array_filter(explode("\n", Storage::disk('local')->get($archivo))));
            $encabezado = array_shift($lineas);

            return collect($lineas)->map(fn ($l) => array_combine($encabezado, $l))->keyBy('cliente')->all();
        };

        return [
            $leer($archivos->first(fn ($a) => str_ends_with($a, '-lote.csv'))),
            $leer($archivos->first(fn ($a) => ! str_ends_with($a, '-lote.csv'))),
        ];
    }

    public function test_clasifica_y_arma_el_lote_solo_con_lo_que_se_puede_corregir_solo(): void
    {
        // Duplicada: diciembre pagado y otro diciembre abierto; enero sí se debe.
        $dup = $this->cliente('2026-02-01 23:59:59', -449);
        $pagoDic = $this->pago($dup, '2025-12-01 10:00:00');
        $this->factura($dup, '2025-12', '2025-12-01', Invoice::STATUS_PAID, 0, $pagoDic);
        $dic = $this->factura($dup, '2025-12', '2025-12-01', Invoice::STATUS_DRAFT, 449);
        $this->factura($dup, '2026-01', '2026-01-01', Invoice::STATUS_DRAFT, 449);

        // Abierta sin saldo: nació al cobrar; octubre todavía no vence.
        $cero = $this->cliente('2026-10-30 23:59:59', 0);
        $this->factura($cero, '2026-09', '2026-10-30', Invoice::STATUS_ISSUED, 0, $this->pago($cero, '2026-09-30 10:00:00'));
        $this->factura($cero, '2026-10', '2026-10-30', Invoice::STATUS_DRAFT, 449);

        // Cubierta: no debe nada y su corte va un ciclo adelante → se revisa a mano.
        $cubierta = $this->cliente('2026-11-06 23:59:59', 149);
        $this->factura($cubierta, '2026-10', '2026-10-04', Invoice::STATUS_DRAFT, 449);

        // Debe de verdad.
        $debe = $this->cliente('2026-09-20 23:59:59', -449);
        $this->factura($debe, '2026-09', '2026-09-20', Invoice::STATUS_DRAFT, 449);

        $archivosAntes = Invoice::whereIn('client_id', [$dup, $cero, $cubierta, $debe])->get()->toArray();
        [$lote, $clasificacion] = $this->clasificar([$dup, $cero, $cubierta, $debe]);

        $this->assertSame('FANTASMA + DEBE', $clasificacion[$dup]['categoria']);
        $this->assertSame('FANTASMA', $clasificacion[$cero]['categoria']);
        $this->assertSame('REVISAR', $clasificacion[$cubierta]['categoria']);
        $this->assertSame('DEBE', $clasificacion[$debe]['categoria']);
        $this->assertSame($archivosAntes, Invoice::whereIn('client_id', [$dup, $cero, $cubierta, $debe])->get()->toArray(), 'Clasificar no escribe.');

        $this->assertSame((string) $dic, $lote[$dup]['anular']);
        $this->assertSame('2025-12', $lote[$dup]['desde']);
        $this->assertArrayHasKey($cero, $lote);
        $this->assertArrayNotHasKey($cubierta, $lote, 'Lo que hay que revisar a mano no entra al lote.');
        $this->assertArrayNotHasKey($debe, $lote);
        $this->assertSame('', $lote[$dup]['aprobado']);

        // Lote: sin --aplicar no escribe; con --aplicar solo la fila aprobada.
        $filas = collect($lote)->map(fn ($f) => array_values(array_merge($f, ['aprobado' => (int) $f['cliente'] === $dup ? 'si' : ''])));
        $h = fopen('php://temp', 'r+');
        fputcsv($h, ['cliente', 'desde', 'pagos_desde', 'anular', 'motivo', 'aprobado']);
        $filas->each(fn ($f) => fputcsv($h, $f));
        rewind($h);
        Storage::disk('local')->put('reportes/facturacion/lote-revisado.csv', stream_get_contents($h));

        $this->artisan('facturacion:reacomodar-pagos-lote', ['archivo' => 'reportes/facturacion/lote-revisado.csv'])->run();
        $this->assertSame(Invoice::STATUS_DRAFT, Invoice::find($dic)->status);

        $this->artisan('facturacion:reacomodar-pagos-lote', ['archivo' => 'reportes/facturacion/lote-revisado.csv', '--aplicar' => true])->run();
        $this->assertSame(Invoice::STATUS_CANCELLED, Invoice::find($dic)->status);
        $this->assertSame(Invoice::STATUS_ISSUED, Invoice::where('client_id', $cero)->where('period', '2026-09')->value('status'), 'La fila sin aprobar no se toca.');
    }
}
