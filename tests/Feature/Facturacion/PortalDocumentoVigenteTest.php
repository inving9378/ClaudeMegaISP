<?php

namespace Tests\Feature\Facturacion;

use App\Models\Invoice;
use App\Modules\Addons\PortalCliente\Models\PortalClient;
use App\Services\Finance\Billing\PrefacturaPdfService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/**
 * FAC-10 (item #518): el Portal Cliente sirve el documento vigente solo al dueño de la factura.
 * BD de pruebas en transacción revertida, clientes sintéticos, PDF simulado.
 *
 * ⚠️ Escrita con `megaisp_test` a medio migrar (#529): el mismo escenario se validó en dev con el
 * guard `cliente` y el controlador real (docs/bitacora/2026-10-16-item-518.md).
 */
class PortalDocumentoVigenteTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private function cliente(): PortalClient
    {
        $u = (int) DB::table('users')->value('id');
        $id = DB::table('clients')->insertGetId(['created_by' => $u, 'updated_by' => $u, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('client_main_information')->insert(['client_id' => $id, 'name' => 'CLIENTE SINTÉTICO FAC-10', 'created_at' => now(), 'updated_at' => now()]);

        return PortalClient::where('client_id', $id)->firstOrFail();
    }

    private function factura(int $cliente, string $status): Invoice
    {
        return Invoice::create(['number' => "FAC10-{$cliente}-{$status}", 'client_id' => $cliente, 'type' => 'proforma', 'period' => '2026-11',
            'subtotal' => 387.07, 'tax' => 61.93, 'total' => 449, 'pending_balance' => $status === 'paid' ? 0 : 449, 'status' => $status]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PrefacturaPdfService::class, new PrefacturaPdfServiceFalso());
    }

    public function test_el_dueno_ve_su_prefactura_y_su_factura(): void
    {
        $yo = $this->cliente();
        $pendiente = $this->factura($yo->client_id, 'issued');
        $pagada = $this->factura($yo->client_id, 'paid');

        $this->actingAs($yo, 'cliente')->get('/portal/documentos/' . $pendiente->id)
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $r = $this->actingAs($yo, 'cliente')->get('/portal/documentos/' . $pagada->id)->assertOk();
        $this->assertStringContainsString('Factura-', $r->headers->get('Content-Disposition'));
    }

    public function test_factura_ajena_cancelada_o_inexistente_da_404(): void
    {
        $yo = $this->cliente();
        $otro = $this->cliente();
        $ajena = $this->factura($otro->client_id, 'issued');
        $cancelada = $this->factura($yo->client_id, 'cancelled');

        $this->actingAs($yo, 'cliente')->get('/portal/documentos/' . $ajena->id)->assertNotFound();
        $this->actingAs($yo, 'cliente')->get('/portal/documentos/' . $cancelada->id)->assertNotFound();
        $this->actingAs($yo, 'cliente')->get('/portal/documentos/999999999')->assertNotFound();
    }

    public function test_sin_sesion_de_portal_no_entra(): void
    {
        $f = $this->factura($this->cliente()->client_id, 'issued');

        $this->get('/portal/documentos/' . $f->id)->assertRedirect();
    }
}
