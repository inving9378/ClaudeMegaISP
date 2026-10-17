<?php

namespace Tests\Feature\Facturacion;

use App\Models\ClientEstadoCuenta;
use App\Modules\Core\Clientes\Controllers\ClientController;
use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Core\Clientes\Services\ClientBillingService;
use App\Services\Finance\Billing\ReglasFacturacion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesApplication;

/**
 * FR-02 (#536): «Cancelado» como baja definitiva. BD de pruebas en transacción revertida; clientes
 * sintéticos; client_estados_cuenta como TEMPORARY TABLE si la BD de pruebas aún no la tiene.
 */
class CanceladoBajaDefinitivaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake(config('billing.pdf_disk', 'local'));

        if (! Schema::hasTable('client_estados_cuenta')) {
            Schema::create('client_estados_cuenta', function (Blueprint $t) {
                $t->temporary();
                $t->id();
                $t->unsignedBigInteger('client_id');
                $t->string('tipo', 20);
                $t->string('clave', 20);
                $t->string('estado_cliente', 30)->nullable();
                $t->decimal('saldo', 12, 2)->default(0);
                $t->decimal('adeudo', 12, 2)->default(0);
                $t->unsignedInteger('facturas_pendientes')->default(0);
                $t->string('pdf_path', 500)->nullable();
                $t->string('pdf_sha256', 64)->nullable();
                $t->string('origen', 20)->default('sistema');
                $t->unsignedBigInteger('generado_por')->nullable();
                $t->text('error')->nullable();
                $t->timestamps();
                $t->unique(['client_id', 'tipo', 'clave']);
            });
        }
    }

    private function cliente(string $estado, float $saldo = 0): int
    {
        $u = (int) DB::table('users')->value('id');
        $id = DB::table('clients')->insertGetId(['created_by' => $u, 'updated_by' => $u, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('client_main_information')->insert(['client_id' => $id, 'name' => 'CLIENTE SINTÉTICO FR-02', 'estado' => $estado,
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('billing_configurations')->insert(['client_id' => $id, 'type_billing_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('balances')->insert(['balanceable_id' => $id, 'balanceable_type' => 'App\\Models\\Client', 'amount' => $saldo,
            'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function estado(int $id): string
    {
        return (string) DB::table('client_main_information')->where('client_id', $id)->value('estado');
    }

    public function test_cancelado_no_genera_cargos_ni_prefacturas_ni_se_reactiva(): void
    {
        $id = $this->cliente('Cancelado', 500);
        $c = Client::find($id);

        $this->assertFalse(ReglasFacturacion::generaCargos($c));
        $this->assertFalse(ReglasFacturacion::generaPrefactura($c));
        $this->assertFalse(ReglasFacturacion::reactivaConPago($c));
        $this->assertTrue(ReglasFacturacion::generaCargos(Client::find($this->cliente('Activo'))));
    }

    public function test_un_pago_a_un_cancelado_no_cobra_ni_reactiva(): void
    {
        $id = $this->cliente('Cancelado', 500);
        $c = Client::find($id);

        app(ClientBillingService::class)->billing($c, 500);
        $this->assertFalse(app(ClientBillingService::class)->billingServicesByClient($c, ClientBillingService::TYPE_BILLING_EXECUTED_PROCESS));
        $c->activarCliente();

        $this->assertSame('Cancelado', $this->estado($id));
        $this->assertSame(0, DB::table('transactions')->where('client_id', $id)->count(), 'sin cargos');
        $this->assertEquals(500, DB::table('balances')->where('balanceable_id', $id)->value('amount'), 'el saldo abonado se conserva');
    }

    public function test_ningun_proceso_automatico_saca_a_un_cliente_de_cancelado(): void
    {
        $id = $this->cliente('Cancelado');

        \App\Models\ClientMainInformation::where('client_id', $id)->first()->update(['estado' => 'Activo']);
        $this->assertSame('Cancelado', $this->estado($id), 'clase legacy');

        \App\Modules\Core\Clientes\Models\ClientMainInformation::where('client_id', $id)->first()->update(['estado' => 'Bloqueado']);
        $this->assertSame('Cancelado', $this->estado($id), 'clase modular');

        ReglasFacturacion::cambioManual(fn () => \App\Models\ClientMainInformation::where('client_id', $id)->first()->update(['estado' => 'Activo']));
        $this->assertSame('Activo', $this->estado($id), 'el cambio manual de la ficha sí puede');
        $this->assertFalse(ReglasFacturacion::esCambioManual(), 'el permiso no se queda puesto');
    }

    public function test_al_pasar_a_cancelado_se_genera_un_estado_de_cuenta_final_una_vez(): void
    {
        $id = $this->cliente('Activo', -349.5);

        \App\Models\ClientMainInformation::where('client_id', $id)->first()->update(['estado' => 'Cancelado']);

        $e = ClientEstadoCuenta::where('client_id', $id)->get();
        $this->assertCount(1, $e);
        $this->assertSame('final', $e[0]->tipo);
        $this->assertSame(now()->toDateString(), $e[0]->clave);
        $this->assertSame('Cancelado', $e[0]->estado_cliente);
        $this->assertEquals(349.5, $e[0]->adeudo);
        $this->assertNull($e[0]->error);
        Storage::disk(config('billing.pdf_disk', 'local'))->assertExists($e[0]->pdf_path);
        $this->assertSame(config('billing.pdf_path_prefix', 'billing/pdf'), dirname($e[0]->pdf_path), 'misma carpeta que las prefacturas');
        $this->assertSame(0, DB::table('billing_notifications')->where('client_id', $id)->count(), 'generarlo no lo encola para envío');

        // Reactivado a mano y cancelado otra vez el mismo día: no se duplica.
        ReglasFacturacion::cambioManual(fn () => \App\Models\ClientMainInformation::where('client_id', $id)->first()->update(['estado' => 'Activo']));
        \App\Models\ClientMainInformation::where('client_id', $id)->first()->update(['estado' => 'Cancelado']);
        $this->assertSame(1, ClientEstadoCuenta::where('client_id', $id)->count());
    }

    public function test_eliminar_ya_no_cancela_y_exige_escribir_eliminar(): void
    {
        $id = $this->cliente('Activo');
        $controller = app(ClientController::class);

        $r = $controller->destroy(Request::create("/cliente/destroy/{$id}", 'POST', ['confirmacion' => 'si']), $id);

        $this->assertSame(422, $r->getStatusCode());
        $this->assertSame('Activo', $this->estado($id), 'no pasa por Cancelado');
        $this->assertTrue(DB::table('clients')->where('id', $id)->exists());

        $r = $controller->destroy(Request::create("/cliente/destroy/{$id}", 'POST', ['confirmacion' => 'ELIMINAR']), $id);

        $this->assertSame(200, $r->getStatusCode());
        $this->assertFalse(DB::table('clients')->where('id', $id)->exists());
    }
}
