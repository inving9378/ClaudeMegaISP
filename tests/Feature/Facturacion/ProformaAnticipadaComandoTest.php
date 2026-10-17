<?php

namespace Tests\Feature\Facturacion;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesApplication;

/**
 * FAC-06 (item #512): invoice:create-proformas con el interruptor apagado (regla de siempre) y
 * encendido (N días antes del corte). BD de pruebas en transacción revertida; clientes sintéticos;
 * `client_billing_pauses` como TEMPORARY TABLE si la BD de pruebas aún no la tiene (no se migra).
 */
class ProformaAnticipadaComandoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private array $clientes = [];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake(config('billing.pdf_disk', 'local'));
        Carbon::setTestNow('2026-10-16 03:00:00');

        if (! Schema::hasTable('client_billing_pauses')) {
            Schema::create('client_billing_pauses', function (Blueprint $t) {
                $t->temporary();
                $t->id();
                $t->unsignedBigInteger('client_id');
                $t->string('estado', 30)->default('programada');
                $t->timestamps();
            });
        }

        DB::table('config_finance_notifications')->where('type_config', 'proforma_invoice')
            ->update(['auto_send_notifications' => 1, 'delay_days' => '3']);

        $this->clientes = [
            'A' => $this->cliente('2026-10-18 23:59:59'), // en 2 días
            'B' => $this->cliente('2026-10-25 23:59:59'), // en 9 días
            'C' => $this->cliente('2026-10-10 23:59:59'), // ya pasó
            'D' => $this->cliente('1'),                   // inválida
            'E' => $this->cliente('2026-11-07 23:59:59'), // mes siguiente
        ];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function cliente(string $corte): int
    {
        $u = (int) DB::table('users')->value('id');
        $id = DB::table('clients')->insertGetId(['created_by' => $u, 'updated_by' => $u, 'fecha_corte' => $corte, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('client_main_information')->insert(['client_id' => $id, 'name' => 'CLIENTE SINTÉTICO FAC-06', 'estado' => 'Activo',
            'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    /** @return array<string,string> letra → periodo de la proforma creada */
    private function proformas(): array
    {
        $porId = array_flip($this->clientes);
        $r = [];
        foreach (Invoice::whereIn('client_id', $this->clientes)->get(['client_id', 'period']) as $i) {
            $r[$porId[$i->client_id]] = $i->period;
        }
        ksort($r);

        return $r;
    }

    public function test_apagado_conserva_la_regla_de_siempre(): void
    {
        config(['billing.proforma_anticipada' => false]);

        $this->artisan('invoice:create-proformas')->assertExitCode(0);

        // Día 16 (< 25): todas las del mes en curso, incluso la de corte ya pasado (C).
        $this->assertSame(['A' => '2026-10', 'B' => '2026-10', 'C' => '2026-10'], $this->proformas());
    }

    public function test_encendido_solo_los_cortes_dentro_de_n_dias_sin_retroactivo_e_idempotente(): void
    {
        config(['billing.proforma_anticipada' => true]);

        $this->artisan('invoice:create-proformas')->assertExitCode(0);
        $this->assertSame(['A' => '2026-10'], $this->proformas());

        $this->artisan('invoice:create-proformas')->assertExitCode(0);
        $this->assertSame(1, Invoice::where('client_id', $this->clientes['A'])->count(), 'no duplica el periodo');

        Carbon::setTestNow('2026-10-22 03:00:00');
        $this->artisan('invoice:create-proformas')->assertExitCode(0);
        $this->assertSame(['A' => '2026-10', 'B' => '2026-10'], $this->proformas());

        Carbon::setTestNow('2026-11-04 03:00:00');
        $this->artisan('invoice:create-proformas')->assertExitCode(0);
        $this->assertSame(['A' => '2026-10', 'B' => '2026-10', 'E' => '2026-11'], $this->proformas(), 'C (pasado) y D (inválida) nunca');
    }

    public function test_encendido_respeta_la_pausa_de_facturacion(): void
    {
        config(['billing.proforma_anticipada' => true]);
        $pausa = ['client_id' => $this->clientes['A'], 'estado' => 'en_curso', 'created_at' => now(), 'updated_at' => now()];
        if (Schema::hasColumn('client_billing_pauses', 'meses')) {   // tabla real (no la temporal mínima)
            $pausa += ['tipo' => 'sin_cuota', 'meses' => 1, 'fecha_inicio' => now()->subDay(), 'fecha_fin' => now()->addMonth()];
        }
        DB::table('client_billing_pauses')->insert($pausa);

        $this->artisan('invoice:create-proformas')->assertExitCode(0);

        $this->assertSame([], $this->proformas());
    }

    public function test_encendido_ya_no_usa_delay_days(): void
    {
        // FR-05 (#539): los días salen de «Envío prefactura» (general 5), no de delay_days.
        config(['billing.proforma_anticipada' => true]);
        DB::table('config_finance_notifications')->where('type_config', 'proforma_invoice')->update(['delay_days' => 'abc']);

        $this->artisan('invoice:create-proformas')->expectsOutputToContain('Envío prefactura: 5 días')->assertExitCode(0);

        $this->assertSame(['A' => '2026-10'], $this->proformas());
    }

    public function test_envio_prefactura_por_cliente_y_desde_el_dia_de_facturacion(): void
    {
        // FR-05 (#539). billing_configurations TEMPORAL con la columna nueva (tapa a la real solo aquí).
        Schema::create('billing_configurations', function (Blueprint $t) {
            $t->temporary();
            $t->id();
            $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('type_billing_id')->nullable();
            $t->unsignedTinyInteger('envio_prefactura_dias')->nullable();
            $t->timestamps();
        });
        config(['billing.proforma_anticipada' => true]);
        // A: corte en 2 días pero su Envío prefactura es 1 → hoy no.
        DB::table('billing_configurations')->insert(['client_id' => $this->clientes['A'], 'type_billing_id' => 3, 'envio_prefactura_dias' => 1]);
        // F: recurrente, día de facturación (fecha_pago) en 5 días y corte en 6 → sí, se cuenta desde el día de facturación.
        $f = $this->cliente('2026-10-22 23:59:59');
        DB::table('clients')->where('id', $f)->update(['fecha_pago' => '2026-10-21 00:00:00']);
        DB::table('billing_configurations')->insert(['client_id' => $f, 'type_billing_id' => 1, 'envio_prefactura_dias' => null]);
        $this->clientes['F'] = $f;

        $this->artisan('invoice:create-proformas')->assertExitCode(0);

        $this->assertSame(['F' => '2026-10'], $this->proformas(), 'periodo = mes del corte, como siempre');
        $this->assertSame('2026-10-22', substr((string) Invoice::where('client_id', $f)->value('due_date'), 0, 10), 'fecha límite = corte');

        Carbon::setTestNow('2026-10-17 03:00:00');
        $this->artisan('invoice:create-proformas')->assertExitCode(0);
        $this->assertSame(['A' => '2026-10', 'F' => '2026-10'], $this->proformas(), 'A entra a 1 día de su corte');
    }

    public function test_cancelado_no_recibe_prefactura_en_ninguna_regla(): void
    {
        // FR-02 (#536): Cancelado (baja definitiva) queda fuera de las dos reglas.
        DB::table('client_main_information')->where('client_id', $this->clientes['A'])->update(['estado' => 'Cancelado']);
        DB::table('client_main_information')->where('client_id', $this->clientes['B'])->update(['estado' => 'Cancelado']);

        config(['billing.proforma_anticipada' => false]);
        $this->artisan('invoice:create-proformas')->assertExitCode(0);
        $this->assertSame(['C' => '2026-10'], $this->proformas());

        config(['billing.proforma_anticipada' => true]);
        Carbon::setTestNow('2026-10-22 03:00:00');
        $this->artisan('invoice:create-proformas')->assertExitCode(0);
        $this->assertSame(['C' => '2026-10'], $this->proformas());
    }

    public function test_anticipada_solo_para_clientes_activos(): void
    {
        // FR-06 (#540): bloqueado e inactivo reciben estado de cuenta; Nuevo, nada.
        config(['billing.proforma_anticipada' => true]);
        DB::table('client_main_information')->where('client_id', $this->clientes['A'])->update(['estado' => 'Bloqueado']);

        $this->artisan('invoice:create-proformas')->assertExitCode(0);
        $this->assertSame([], $this->proformas());

        DB::table('client_main_information')->where('client_id', $this->clientes['A'])->update(['estado' => 'Nuevo']);
        $this->artisan('invoice:create-proformas')->assertExitCode(0);
        $this->assertSame([], $this->proformas());

        DB::table('client_main_information')->where('client_id', $this->clientes['A'])->update(['estado' => 'Activo']);
        $this->artisan('invoice:create-proformas')->assertExitCode(0);
        $this->assertSame(['A' => '2026-10'], $this->proformas());
    }

    public function test_la_simulacion_no_crea_nada(): void
    {
        $this->artisan('invoice:proformas-simular', ['--dias' => 3, '--horizonte' => 30])
            ->expectsOutputToContain('Nada se creó ni se modificó')
            ->assertExitCode(0);
        $this->artisan('invoice:proformas-simular', ['--horizonte' => 30])
            ->expectsOutputToContain('Envío prefactura de cada cliente (general: 5)')
            ->assertExitCode(0);

        $this->assertSame([], $this->proformas());
    }
}
