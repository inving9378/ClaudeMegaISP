<?php

namespace Tests\Feature\Facturacion;

use App\Models\ClientHistorialCambio;
use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Core\Clientes\Services\ClientBillingService;
use App\Services\Finance\Billing\CapaContrato;
use App\Support\PlazoContrato;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\CreatesApplication;

/**
 * FR-07 (#541): capa de contrato — vigente, cumplido sin renovación (tope de facturas sin pago y paso
 * a Inactivo), renovado, Especial, fin de mes, sin retroactivo y apagada = regla de siempre.
 * BD de pruebas en transacción revertida (requiere las migraciones 2026_10_17_* aplicadas).
 */
class CapaContratoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private int $seis;

    private int $especial;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        PlazoContrato::olvidar();
        Carbon::setTestNow('2026-11-11 05:00:00');
        config(['billing.capa_contrato' => true]);
        DB::table('billing_config')->update(['reglas_facturacion_desde' => '2026-10-17 12:00:00']);
        $this->seis = (int) DB::table('duration_contracts')->where('duration', 6)->value('id');
        $this->especial = (int) DB::table('duration_contracts')->where('name', 'Especial')->value('id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        PlazoContrato::olvidar();
        parent::tearDown();
    }

    private function cliente(string $activacion, array $extra = []): Client
    {
        $u = (int) DB::table('users')->value('id');
        $id = DB::table('clients')->insertGetId(['created_by' => $u, 'updated_by' => $u, 'fecha_pago' => '2026-11-11 00:00:00',
            'fecha_corte' => '2026-11-12 23:59:59', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('client_main_information')->insert($extra + ['client_id' => $id, 'name' => 'SINTÉTICO FR-07', 'estado' => 'Bloqueado',
            'type_of_billing_id' => 1, 'duration_contract_id' => $this->seis, 'activation_date' => $activacion,
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('billing_configurations')->insert(['client_id' => $id, 'type_billing_id' => 1, 'billing_date' => 11, 'period' => 1,
            'billing_expiration' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('balances')->insert(['balanceable_id' => $id, 'balanceable_type' => 'App\\Models\\Client', 'amount' => 0,
            'created_at' => now(), 'updated_at' => now()]);

        return Client::find($id);
    }

    private function cargos(Client $c, int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            DB::table('client_contrato_cargos')->insert(['client_id' => $c->id, 'ciclo' => "2026-0{$i}-11", 'estado_contrato' => 'cumplido',
                'plazo_numero' => 1, 'created_at' => now()->subDays(40 - $i)]);
        }
    }

    private function decidir(Client $c, string $ciclo = '2026-11-11'): ?array
    {
        return CapaContrato::decidirCargoSinSaldo($c, Carbon::parse($ciclo));
    }

    public function test_estado_del_contrato_vigente_cumplido_renovado_y_fin_de_mes(): void
    {
        $info = (object) ['duration_contract_id' => $this->seis, 'plazo_especial_meses' => null, 'activation_date' => '2026-01-31',
            'discharge_date' => null, 'created_at' => null];
        $this->assertSame(PlazoContrato::VIGENTE, PlazoContrato::estadoEn($info, Carbon::parse('2026-07-30'), false)['estado']);
        $c = PlazoContrato::estadoEn($info, Carbon::parse('2026-07-31'), false);
        $this->assertSame([PlazoContrato::CUMPLIDO, '2026-07-31'], [$c['estado'], $c['fin']->toDateString()], '31 ene + 6 meses = 31 jul');

        $r = PlazoContrato::estadoEn($info, Carbon::parse('2027-03-01'), true);
        $this->assertSame([PlazoContrato::RENOVADO, 3, '2027-01-31', '2027-07-31'],
            [$r['estado'], $r['plazo'], $r['inicio']->toDateString(), $r['fin']->toDateString()], 'plazos desde el inicio original, sin recorrerse');

        $esp = (object) ['duration_contract_id' => $this->especial, 'plazo_especial_meses' => 10, 'activation_date' => '2026-01-15',
            'discharge_date' => null, 'created_at' => null];
        $this->assertSame('2026-11-15', PlazoContrato::estadoEn($esp, Carbon::parse('2026-11-11'), false)['fin']->toDateString());
    }

    public function test_apagada_o_ciclo_anterior_a_las_reglas_sigue_la_regla_de_siempre(): void
    {
        $c = $this->cliente('2024-01-10');
        config(['billing.capa_contrato' => false]);
        $this->assertNull($this->decidir($c));

        config(['billing.capa_contrato' => true]);
        $this->assertNull($this->decidir($c, '2026-10-11'), 'sin retroactivo: ciclo antes de las reglas');
        $this->assertNotNull($this->decidir($c, '2026-10-17'));
    }

    public function test_vigente_se_factura_aunque_no_pague(): void
    {
        $c = $this->cliente('2026-09-11');   // 6 meses → vence 2027-03-11
        $this->cargos($c, 5);
        $d = $this->decidir($c);

        $this->assertTrue($d['cobrar']);
        $this->assertSame(PlazoContrato::VIGENTE, $d['estado_contrato']);
    }

    public function test_cumplido_sin_renovacion_hasta_tres_facturas_sin_pago_y_luego_inactivo(): void
    {
        $c = $this->cliente('2025-01-10');   // cumplido el 2025-07-10
        foreach ([0, 1, 2] as $previos) {
            DB::table('client_contrato_cargos')->where('client_id', $c->id)->delete();
            $this->cargos($c, $previos);
            $d = $this->decidir($c);
            $this->assertTrue($d['cobrar'], "con {$previos} sin pago todavía se factura");
            $this->assertSame($previos + 1, $d['impagos']);
        }
        DB::table('client_contrato_cargos')->where('client_id', $c->id)->delete();
        $this->cargos($c, 3);
        $d = $this->decidir($c);
        $this->assertFalse($d['cobrar']);
        $this->assertTrue($d['inactivar']);

        // Un pago reinicia la cuenta.
        // Fila directa (sin observer de pagos): solo importa que exista un pago más reciente.
        DB::table('payments')->insert(['paymentable_id' => $c->id, 'paymentable_type' => 'App\\Models\\Client', 'amount' => 100,
            'date' => '10/11/2026', 'payment_method_id' => 1, 'receipt' => 'FR07-PRUEBA', 'add_by' => (int) DB::table('users')->value('id'),
            'created_at' => now(), 'updated_at' => now()]);
        $this->assertTrue($this->decidir($c)['cobrar']);
    }

    public function test_el_tope_es_configurable_por_cliente(): void
    {
        $c = $this->cliente('2025-01-10');
        DB::table('billing_configurations')->where('client_id', $c->id)->update(['facturas_max_sin_pago' => 1]);
        $this->cargos($c, 1);
        $this->assertFalse($this->decidir($c)['cobrar']);
    }

    public function test_renovado_se_factura_y_anota_la_renovacion_una_vez(): void
    {
        $c = $this->cliente('2025-01-10');
        DB::table('client_bundle_services')->insert(['client_id' => $c->id, 'bundle_id' => (int) DB::table('bundles')->value('id'),
            'automatic_renewal' => 1, 'estado' => 'Activo', 'price' => 349, 'contract_start_date' => '2025-01-10',
            'created_at' => now(), 'updated_at' => now()]);
        $d = $this->decidir($c);
        $this->assertTrue($d['cobrar']);
        $this->assertSame([PlazoContrato::RENOVADO, 4], [$d['estado_contrato'], $d['plazo']]);

        CapaContrato::registrarCargo($c->id, Carbon::parse('2026-11-11'), $d);
        CapaContrato::registrarCargo($c->id, Carbon::parse('2026-11-11'), $d);
        $h = ClientHistorialCambio::where('client_id', $c->id)->where('campo', 'renovacion')->get();
        $this->assertCount(1, $h);
        $this->assertSame('10/01/2027', $h[0]->valor_nuevo);
        $this->assertSame(1, DB::table('client_contrato_cargos')->where('client_id', $c->id)->count());
    }

    public function test_nuevo_e_inactivo_no_generan_cargos_por_contrato(): void
    {
        $this->assertFalse($this->decidir($this->cliente('2026-09-11', ['estado' => 'Nuevo']))['cobrar']);
        $this->assertFalse($this->decidir($this->cliente('2026-09-11', ['estado' => 'Inactivo']))['cobrar']);
    }

    public function test_el_motor_no_cobra_y_pasa_a_inactivo_al_llegar_al_tope(): void
    {
        $c = $this->cliente('2025-01-10');
        $this->cargos($c, 3);

        $cobro = app(ClientBillingService::class)->billingServicesByClient($c, ClientBillingService::TYPE_BILLING_EXECUTED_PROCESS);

        $this->assertFalse($cobro);
        $this->assertSame('Inactivo', DB::table('client_main_information')->where('client_id', $c->id)->value('estado'));
        $h = ClientHistorialCambio::where('client_id', $c->id)->where('campo', 'estado')->latest('id')->first();
        $this->assertSame(['Bloqueado', 'Inactivo', 'sistema'], [$h->valor_anterior, $h->valor_nuevo, $h->origen]);
        $this->assertStringContainsString('3 facturas seguidas sin pago', $h->motivo);
    }

    public function test_el_motor_cobra_el_vigente_y_registra_el_cargo(): void
    {
        $c = $this->cliente('2026-09-11');

        $cobro = app(ClientBillingService::class)->billingServicesByClient($c, ClientBillingService::TYPE_BILLING_EXECUTED_PROCESS);

        $this->assertTrue($cobro);
        $this->assertSame(1, DB::table('client_contrato_cargos')->where('client_id', $c->id)->where('ciclo', '2026-11-11')->count());
        $this->assertStringStartsWith('2026-12-11', (string) DB::table('clients')->where('id', $c->id)->value('fecha_pago'));
    }

    public function test_apagada_el_motor_no_registra_cargos_de_contrato(): void
    {
        config(['billing.capa_contrato' => false]);
        $c = $this->cliente('2025-01-10');
        $this->cargos($c, 3);

        app(ClientBillingService::class)->billingServicesByClient($c, ClientBillingService::TYPE_BILLING_EXECUTED_PROCESS);

        $this->assertSame('Bloqueado', DB::table('client_main_information')->where('client_id', $c->id)->value('estado'));
        $this->assertSame(3, DB::table('client_contrato_cargos')->where('client_id', $c->id)->count());
    }
}
