<?php

namespace Tests\Feature\Facturacion;

use App\Models\Invoice;
use App\Services\Finance\Invoice\FolioFactura;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/** #520: folio corto consecutivo por año. BD de pruebas en transacción revertida. */
class FolioFacturaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private function factura(): Invoice
    {
        $u = (int) DB::table('users')->value('id');
        $c = DB::table('clients')->insertGetId(['created_by' => $u, 'updated_by' => $u, 'created_at' => now(), 'updated_at' => now()]);

        return Invoice::create(['number' => 'FOLIO-' . uniqid(), 'client_id' => $c, 'type' => 'proforma', 'period' => '2026-11',
            'subtotal' => 1, 'tax' => 0, 'total' => 1, 'pending_balance' => 1, 'status' => Invoice::STATUS_DRAFT]);
    }

    public function test_formato(): void
    {
        $this->assertSame('MN-2026-000123', FolioFactura::formato(2026, 123));
    }

    public function test_cada_factura_nueva_recibe_el_siguiente_folio_del_anio(): void
    {
        $a = $this->factura();
        $b = $this->factura();

        $this->assertMatchesRegularExpression('/^MN-' . now()->year . '-\d{6}$/', $a->folio);
        $this->assertSame((int) substr($a->folio, -6) + 1, (int) substr($b->folio, -6));
        $this->assertNotSame($a->folio, $b->folio);
    }

    public function test_el_consecutivo_reinicia_por_anio(): void
    {
        $this->assertSame('MN-2031-000001', FolioFactura::siguiente(2031));
        $this->assertSame('MN-2031-000002', FolioFactura::siguiente(2031));
    }

    public function test_apagado_no_asigna_folio(): void
    {
        config(['billing.folio_corto' => false]);

        $this->assertNull($this->factura()->folio);
    }

    public function test_un_folio_dado_no_se_reemplaza(): void
    {
        $u = (int) DB::table('users')->value('id');
        $c = DB::table('clients')->insertGetId(['created_by' => $u, 'updated_by' => $u, 'created_at' => now(), 'updated_at' => now()]);
        $i = Invoice::create(['number' => 'FOLIO-X', 'folio' => 'MN-2020-000999', 'client_id' => $c, 'type' => 'proforma', 'period' => '2026-11',
            'subtotal' => 1, 'tax' => 0, 'total' => 1, 'pending_balance' => 1, 'status' => Invoice::STATUS_DRAFT]);

        $this->assertSame('MN-2020-000999', $i->folio);
    }
}
