<?php

namespace Tests\Feature\Facturacion;

use App\Models\ClientHistorialCambio;
use App\Models\User;
use App\Modules\Core\Clientes\Models\Client;
use App\Services\Clientes\HistorialCliente;
use App\Services\Finance\Billing\ReglasFacturacion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesApplication;

/**
 * FR-04 (#538): historial de cambios de estado y de contrato con su origen. BD de pruebas en
 * transacción revertida; client_historial_cambios (y client_estados_cuenta) como TEMPORARY TABLE si
 * la BD de pruebas aún no las tiene.
 */
class HistorialCambiosClienteTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake(config('billing.pdf_disk', 'local'));
        if (! Schema::hasTable('client_historial_cambios')) {
            Schema::create('client_historial_cambios', function (Blueprint $t) {
                $t->temporary();
                $t->id();
                $t->unsignedBigInteger('client_id');
                $t->string('campo', 40);
                $t->string('valor_anterior', 255)->nullable();
                $t->string('valor_nuevo', 255)->nullable();
                $t->string('origen', 20);
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('motivo', 500)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
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
            });
        }
    }

    private function cliente(string $estado, float $saldo = 0, ?int $plazo = null): int
    {
        $u = (int) DB::table('users')->value('id');
        $id = DB::table('clients')->insertGetId(['created_by' => $u, 'updated_by' => $u, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('client_main_information')->insert(['client_id' => $id, 'name' => 'CLIENTE SINTÉTICO FR-04', 'estado' => $estado,
            'duration_contract_id' => $plazo, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('balances')->insert(['balanceable_id' => $id, 'balanceable_type' => 'App\\Models\\Client', 'amount' => $saldo,
            'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function info(int $id)
    {
        return \App\Models\ClientMainInformation::where('client_id', $id)->first();
    }

    private function historial(int $id): array
    {
        return ClientHistorialCambio::where('client_id', $id)->orderBy('id')->get()
            ->map(fn ($c) => [$c->campo, $c->valor_anterior, $c->valor_nuevo, $c->origen])->all();
    }

    public function test_cambio_automatico_queda_como_sistema(): void
    {
        $id = $this->cliente('Activo');

        $this->info($id)->update(['estado' => 'Bloqueado']);
        \App\Modules\Core\Clientes\Models\ClientMainInformation::where('client_id', $id)->first()->update(['estado' => 'Inactivo']);

        $this->assertSame([['estado', 'Activo', 'Bloqueado', 'sistema'], ['estado', 'Bloqueado', 'Inactivo', 'sistema']], $this->historial($id));
    }

    public function test_cambio_en_la_ficha_queda_como_usuario_con_quien(): void
    {
        $id = $this->cliente('Activo');
        $user = User::query()->first();
        $this->actingAs($user);

        ReglasFacturacion::cambioManual(fn () => $this->info($id)->update(['estado' => 'Cancelado']));

        $c = ClientHistorialCambio::where('client_id', $id)->first();
        $this->assertSame(['estado', 'Activo', 'Cancelado', 'usuario'], [$c->campo, $c->valor_anterior, $c->valor_nuevo, $c->origen]);
        $this->assertSame($user->id, (int) $c->user_id);
    }

    public function test_reactivacion_por_pago_queda_como_pago_y_sin_repetir_valores(): void
    {
        $id = $this->cliente('Bloqueado', 0);

        HistorialCliente::conOrigen('pago', fn () => Client::find($id)->activarCliente(), 'Pago #1');
        Client::find($id)->activarCliente();      // ya está Activo: no se registra nada más

        $h = $this->historial($id);
        $this->assertSame([['estado', 'Bloqueado', 'Activo', 'pago']], $h);
        $this->assertSame('Activo', DB::table('client_main_information')->where('client_id', $id)->value('estado'));
    }

    public function test_cambio_de_plazo_con_nombres_del_catalogo(): void
    {
        $seis = (int) DB::table('duration_contracts')->where('duration', 6)->value('id');
        $doce = (int) DB::table('duration_contracts')->where('duration', 12)->value('id');
        $id = $this->cliente('Activo', 0, $seis);

        HistorialCliente::conOrigen('usuario', fn () => $this->info($id)->update(['duration_contract_id' => $doce]));

        $this->assertSame([['plazo', '6 Meses', '12 Meses', 'usuario']], $this->historial($id));
    }

    public function test_un_cancelado_que_no_cambia_no_deja_historial(): void
    {
        $id = $this->cliente('Cancelado');

        $this->info($id)->update(['estado' => 'Activo']);  // automático: se queda Cancelado

        $this->assertSame([], $this->historial($id));
    }
}
