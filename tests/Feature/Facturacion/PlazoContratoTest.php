<?php

namespace Tests\Feature\Facturacion;

use App\Modules\Core\Clientes\Models\ClientMainInformation;
use App\Observers\Facturacion\PlazoContratoObserver;
use App\Support\PlazoContrato;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\CreatesApplication;

/**
 * FR-03 (#537): plazo de contrato con opción «Especial», 18 por defecto, inicio por activación o
 * alta y fin en meses naturales. BD de pruebas en transacción revertida (solo duration_contracts).
 */
class PlazoContratoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private int $especial;

    private int $seis;

    protected function setUp(): void
    {
        parent::setUp();
        PlazoContrato::olvidar();
        $this->especial = (int) (DB::table('duration_contracts')->where('name', 'Especial')->value('id')
            ?: DB::table('duration_contracts')->insertGetId(['name' => 'Especial', 'duration' => 0, 'created_at' => now(), 'updated_at' => now()]));
        $this->seis = (int) DB::table('duration_contracts')->where('duration', 6)->value('id');
    }

    protected function tearDown(): void
    {
        PlazoContrato::olvidar();
        parent::tearDown();
    }

    private function info(array $a): object
    {
        return (object) ($a + ['duration_contract_id' => null, 'plazo_especial_meses' => null,
            'activation_date' => null, 'discharge_date' => null, 'created_at' => null]);
    }

    public function test_meses_del_catalogo_especial_y_por_defecto(): void
    {
        $this->assertSame(6, PlazoContrato::meses($this->info(['duration_contract_id' => $this->seis])));
        $this->assertSame(9, PlazoContrato::meses($this->info(['duration_contract_id' => $this->especial, 'plazo_especial_meses' => 9])));
        $this->assertSame(18, PlazoContrato::meses($this->info(['duration_contract_id' => $this->especial])), 'Especial sin meses → 18');
        $this->assertSame(18, PlazoContrato::meses($this->info([])), 'sin plazo → 18');
    }

    public function test_inicio_por_activacion_valida_o_fecha_de_alta(): void
    {
        $alta = '2025-02-10 12:00:00';
        $this->assertSame('2025-03-01', PlazoContrato::inicio($this->info(['activation_date' => '2025-03-01', 'discharge_date' => $alta]))->toDateString());
        foreach (['now', '', null, '15/03/2025', '2025-02-30'] as $mala) {
            $this->assertSame('2025-02-10', PlazoContrato::inicio($this->info(['activation_date' => $mala, 'discharge_date' => $alta]))->toDateString(), var_export($mala, true));
        }
        $this->assertSame('2024-12-31', PlazoContrato::inicio($this->info(['activation_date' => 'now', 'discharge_date' => 'x', 'created_at' => '2024-12-31 08:00:00']))->toDateString());
        $this->assertNull(PlazoContrato::inicio($this->info([])));
    }

    public function test_fin_en_meses_naturales_con_fin_de_mes(): void
    {
        $fin = fn (string $inicio, int $meses) => PlazoContrato::fin($this->info([
            'activation_date' => $inicio, 'duration_contract_id' => $this->especial, 'plazo_especial_meses' => $meses,
        ]))->toDateString();

        $this->assertSame('2026-02-28', $fin('2026-01-31', 1), '31 ene + 1 mes = 28 feb');
        $this->assertSame('2028-02-28', $fin('2027-02-28', 12), 'mismo día un año después');
        $this->assertSame('2028-02-29', $fin('2027-03-31', 11), '31 mar + 11 meses = 29 feb (bisiesto)');
        $this->assertSame('2027-03-15', $fin('2025-09-15', 18));
        $this->assertSame('2027-04-11', PlazoContrato::fin($this->info(['activation_date' => '2025-10-11']))->toDateString(), 'sin plazo: 18 meses');
    }

    public function test_los_meses_solo_se_guardan_con_plazo_especial(): void
    {
        $o = new PlazoContratoObserver();

        $m = new ClientMainInformation(['duration_contract_id' => $this->seis, 'plazo_especial_meses' => 9]);
        $o->saving($m);
        $this->assertNull($m->plazo_especial_meses);

        $m = new ClientMainInformation(['duration_contract_id' => $this->especial, 'plazo_especial_meses' => 9]);
        $o->saving($m);
        $this->assertSame(9, (int) $m->plazo_especial_meses);
    }

    public function test_validacion_meses_obligatorios_solo_con_especial(): void
    {
        $v = fn ($dur, $meses) => Validator::make(['plazo_especial_meses' => $meses], ['plazo_especial_meses' => PlazoContrato::reglaMeses($dur)]);

        $this->assertTrue($v($this->especial, null)->fails());
        $this->assertTrue($v($this->especial, 0)->fails());
        $this->assertTrue($v($this->especial, 121)->fails());
        $this->assertFalse($v($this->especial, 9)->fails());
        $this->assertFalse($v($this->seis, null)->fails());
    }

    public function test_contract_months_number_usa_los_meses_especiales(): void
    {
        $u = (int) DB::table('users')->value('id');
        $c = DB::table('clients')->insertGetId(['created_by' => $u, 'updated_by' => $u, 'created_at' => now(), 'updated_at' => now()]);
        $m = new ClientMainInformation(['client_id' => $c, 'name' => 'X', 'duration_contract_id' => $this->seis]);
        $this->assertSame(6, (int) $m->contract_months_number);

        $m->duration_contract_id = $this->especial;
        $m->setRawAttributes($m->getAttributes() + ['plazo_especial_meses' => 9]);
        $this->assertSame(9, (int) $m->contract_months_number);
        $this->assertSame('Especial (9 meses)', $m->contract_months);
    }
}
