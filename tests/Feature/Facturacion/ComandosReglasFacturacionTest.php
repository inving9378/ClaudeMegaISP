<?php

namespace Tests\Feature\Facturacion;

use App\Models\ClientEstadoCuenta;
use App\Models\ClientHistorialCambio;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesApplication;

/**
 * FR-08 (#542): comandos para producción — simulación (solo lectura), inactivos antiguos a cancelado
 * (dry-run por defecto, aplica solo con confirmación) y diagnóstico de datos. BD de pruebas en
 * transacción revertida, clientes sintéticos.
 */
class ComandosReglasFacturacionTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private array $c = [];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
        DB::table('billing_config')->update(['reglas_facturacion_desde' => '2026-10-17 12:00:00']);
        $this->c = [
            'activo'        => $this->cliente('Activo', 0),
            'bloqueado'     => $this->cliente('Bloqueado', -349),
            'inac_reciente' => $this->cliente('Inactivo', -349, '2026-06-01 10:00'),
            'inac_al_dia'   => $this->cliente('Inactivo', 0, '2026-06-01 10:00'),
            'inac_viejo'    => $this->cliente('Inactivo', -349, '2024-01-01 10:00'),
            'inac_sin'      => $this->cliente('Inactivo', -349, null),
            'now'           => $this->cliente('Activo', 0, null, ['activation_date' => 'now', 'type_of_billing_id' => 3], '2026-11-30 23:59:59'),
        ];
    }

    private function cliente(string $estado, float $saldo, ?string $suspension = null, array $extra = [], ?string $corte = null): int
    {
        $u = (int) DB::table('users')->value('id');
        $id = DB::table('clients')->insertGetId(['created_by' => $u, 'updated_by' => $u, 'fecha_suspension' => $suspension,
            'fecha_corte' => $corte, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('client_main_information')->insert($extra + ['client_id' => $id, 'name' => 'SINTÉTICO FR-08', 'estado' => $estado,
            'type_of_billing_id' => 1, 'activation_date' => '2026-01-10', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('balances')->insert(['balanceable_id' => $id, 'balanceable_type' => 'App\\Models\\Client', 'amount' => $saldo,
            'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function estado(string $k): string
    {
        return (string) DB::table('client_main_information')->where('client_id', $this->c[$k])->value('estado');
    }

    public function test_la_simulacion_no_cambia_nada_y_clasifica_a_cada_cliente(): void
    {
        $antes = DB::table('client_main_information')->whereIn('client_id', $this->c)->pluck('estado', 'client_id')->all();

        $this->artisan('facturacion:reglas-simular', ['--hoy' => '2026-11-01', '--detalle' => true])
            ->expectsOutputToContain('Nada se creó ni se modificó')->assertExitCode(0);

        $csv = Storage::disk('local')->get(Storage::disk('local')->files('simulaciones')[0]);
        $fila = function (string $k) use ($csv) {
            preg_match('/^' . $this->c[$k] . ',.*$/m', $csv, $m);

            return explode(',', $m[0]);
        };
        $this->assertSame('prefactura', $fila('activo')[3]);
        $this->assertSame('estado_cuenta', $fila('bloqueado')[3]);
        $this->assertSame('estado_cuenta', $fila('inac_reciente')[3]);
        $this->assertSame('nada', $fila('inac_viejo')[3]);
        $ultimo = fn (string $k) => array_slice($fila($k), -1)[0];
        $this->assertSame(['sin_adeudo', 'mas_de_12_meses', 'sin_fecha'], [$ultimo('inac_al_dia'), $ultimo('inac_viejo'), $ultimo('inac_sin')]);
        $this->assertSame('vigente', $fila('activo')[4]);
        $this->assertSame($antes, DB::table('client_main_information')->whereIn('client_id', $this->c)->pluck('estado', 'client_id')->all());
    }

    public function test_inactivos_a_cancelado_solo_lista_sin_confirmacion(): void
    {
        $this->artisan('facturacion:inactivos-a-cancelado', ['--hoy' => '2026-11-01'])->expectsOutputToContain('dry-run')->assertExitCode(0);
        $this->artisan('facturacion:inactivos-a-cancelado', ['--hoy' => '2026-11-01', '--aplicar' => true, '--confirmar' => 'si'])->assertExitCode(1);

        $this->assertSame(['Inactivo', 'Inactivo', 'Inactivo'], [$this->estado('inac_al_dia'), $this->estado('inac_viejo'), $this->estado('inac_sin')]);
    }

    public function test_inactivos_a_cancelado_aplica_con_confirmacion_historial_y_estado_de_cuenta_final(): void
    {
        Artisan::call('facturacion:inactivos-a-cancelado', ['--hoy' => '2026-11-01', '--aplicar' => true, '--confirmar' => 'CANCELAR']);

        $this->assertSame('Cancelado', $this->estado('inac_al_dia'));
        $this->assertSame('Cancelado', $this->estado('inac_viejo'));
        $this->assertSame('Inactivo', $this->estado('inac_reciente'), 'reciente y con adeudo: no');
        $this->assertSame('Inactivo', $this->estado('inac_sin'), 'sin fecha: solo con --incluir-sin-fecha');
        $h = ClientHistorialCambio::where('client_id', $this->c['inac_viejo'])->where('campo', 'estado')->first();
        $this->assertSame(['Inactivo', 'Cancelado', 'sistema'], [$h->valor_anterior, $h->valor_nuevo, $h->origen]);
        $this->assertStringContainsString('más de 12 meses', $h->motivo);
        $this->assertTrue(ClientEstadoCuenta::where('client_id', $this->c['inac_viejo'])->where('tipo', 'final')->exists());

        Artisan::call('facturacion:inactivos-a-cancelado', ['--hoy' => '2026-11-01', '--aplicar' => true, '--confirmar' => 'CANCELAR', '--incluir-sin-fecha' => true]);
        $this->assertSame('Cancelado', $this->estado('inac_sin'));
    }

    public function test_el_diagnostico_encuentra_activacion_now_y_corte_29_31(): void
    {
        Artisan::call('facturacion:diagnostico-datos', ['--ids' => true]);
        $salida = Artisan::output();

        $this->assertMatchesRegularExpression('/Fecha de activación «now»[^\n]*\b' . $this->c['now'] . '\b/u', $salida);
        $this->assertMatchesRegularExpression('/corte en día 29-31[^\n]*\b' . $this->c['now'] . '\b/u', $salida);
        $this->assertMatchesRegularExpression('/sin fecha de inactivación[^\n]*\b' . $this->c['inac_sin'] . '\b/u', $salida);
        $this->assertStringContainsString('Solo lectura', $salida);
    }
}
