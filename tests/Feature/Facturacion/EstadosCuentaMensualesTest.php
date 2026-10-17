<?php

namespace Tests\Feature\Facturacion;

use App\Models\ClientEstadoCuenta;
use App\Services\Finance\Billing\ReglasFacturacion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesApplication;

/**
 * FR-06 (#540): estado de cuenta mensual según el estado, sin retroactivo, y FR-10 (no timbrar lo no
 * pagado). BD de pruebas en transacción revertida (requiere las migraciones 2026_10_17_* aplicadas).
 */
class EstadosCuentaMensualesTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private array $c = [];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake(config('billing.pdf_disk', 'local'));
        DB::table('billing_config')->update(['reglas_facturacion_desde' => '2026-10-17 12:00:00']);
        DB::table('client_estados_cuenta')->where('clave', '2026-11')->delete();
        $this->c = [
            'activo'          => $this->cliente('Activo', -349),
            'nuevo'           => $this->cliente('Nuevo', -349),
            'bloqueado'       => $this->cliente('Bloqueado', -698),
            'inactivo_debe'   => $this->cliente('Inactivo', -349, '2026-08-01 10:00'),
            'inactivo_al_dia' => $this->cliente('Inactivo', 0, '2026-08-01 10:00'),
            'inactivo_viejo'  => $this->cliente('Inactivo', -349, '2024-01-10 10:00'),
            'inactivo_sin'    => $this->cliente('Inactivo', -349, null),
            'cancelado'       => $this->cliente('Cancelado', -349),
        ];
    }

    private function cliente(string $estado, float $saldo, ?string $suspension = null): int
    {
        $u = (int) DB::table('users')->value('id');
        $id = DB::table('clients')->insertGetId(['created_by' => $u, 'updated_by' => $u, 'fecha_suspension' => $suspension,
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('client_main_information')->insert(['client_id' => $id, 'name' => 'SINTÉTICO FR-06', 'estado' => $estado,
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('balances')->insert(['balanceable_id' => $id, 'balanceable_type' => 'App\\Models\\Client', 'amount' => $saldo,
            'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function generados(): array
    {
        $porId = array_flip($this->c);

        return ClientEstadoCuenta::whereIn('client_id', $this->c)->where('tipo', 'mensual')->get()
            ->map(fn ($e) => $porId[$e->client_id])->sort()->values()->all();
    }

    public function test_apagado_no_genera_nada(): void
    {
        config(['billing.estado_cuenta_mensual' => false]);
        $this->artisan('facturacion:estados-cuenta-mensuales', ['--hoy' => '2026-11-01'])->expectsOutputToContain('apagado')->assertExitCode(0);
        $this->assertSame([], $this->generados());
    }

    public function test_encendido_solo_bloqueados_e_inactivos_que_deben_y_una_vez_al_mes(): void
    {
        config(['billing.estado_cuenta_mensual' => true]);

        $this->artisan('facturacion:estados-cuenta-mensuales', ['--hoy' => '2026-11-01'])->assertExitCode(0);
        $this->assertSame(['bloqueado', 'inactivo_debe'], $this->generados());

        $this->artisan('facturacion:estados-cuenta-mensuales', ['--hoy' => '2026-11-15'])->assertExitCode(0);
        $this->assertSame(2, ClientEstadoCuenta::whereIn('client_id', $this->c)->where('clave', '2026-11')->count(), 'uno por mes');
        $this->assertSame(0, DB::table('billing_notifications')->whereIn('client_id', $this->c)->count(), 'generar no envía');
    }

    public function test_sin_retroactivo_antes_de_la_fecha_de_las_reglas(): void
    {
        config(['billing.estado_cuenta_mensual' => true]);
        $this->artisan('facturacion:estados-cuenta-mensuales', ['--hoy' => '2026-10-01'])->expectsOutputToContain('aún no aplican')->assertExitCode(0);
        $this->assertSame([], $this->generados());
    }

    public function test_la_simulacion_no_genera(): void
    {
        $this->artisan('facturacion:estados-cuenta-mensuales', ['--simular' => true, '--hoy' => '2026-11-01'])
            ->expectsOutputToContain('Simulación: nada se generó')->assertExitCode(0);
        $this->assertSame([], $this->generados());
    }

    public function test_inactivo_desde_el_historial_antes_que_la_fecha_de_suspension(): void
    {
        $id = $this->c['inactivo_viejo'];
        $this->assertSame('2024-01-10', ReglasFacturacion::inactivoDesde($id)->toDateString());
        DB::table('client_historial_cambios')->insert(['client_id' => $id, 'campo' => 'estado', 'valor_anterior' => 'Bloqueado',
            'valor_nuevo' => 'Inactivo', 'origen' => 'sistema', 'created_at' => '2026-09-05 08:00:00']);
        $this->assertSame('2026-09-05', ReglasFacturacion::inactivoDesde($id)->toDateString());
    }

    public function test_no_se_timbra_una_factura_no_pagada(): void
    {
        $id = $this->c['activo'];
        $inv = \App\Models\Invoice::create(['number' => 'FR06-' . uniqid(), 'client_id' => $id, 'type' => 'proforma', 'period' => '2026-11',
            'subtotal' => 349, 'tax' => 0, 'total' => 349, 'pending_balance' => 349, 'status' => \App\Models\Invoice::STATUS_DRAFT]);
        $this->actingAs(\App\Models\User::query()->firstOrFail());
        \Illuminate\Support\Facades\Gate::before(fn () => true);

        $r = app(\App\Modules\Core\Clientes\Controllers\ClientFiscalDataController::class)
            ->timbrar($id, Request::create('/', 'POST', ['invoice_id' => $inv->id]));

        $this->assertSame(422, $r->getStatusCode());
        $this->assertStringContainsString('no pagado', $r->getData(true)['error']);
    }
}
