<?php

namespace Tests\Feature\Talento;

use App\Modules\Addons\Talento\Services\CajaNivel2SimulacionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\CreatesApplication;

/** MR-34 regla 2 (#1296): simulación apagada, idempotente y sin tocar el ledger. */
class CajaNivel2SimulacionTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private array $week = ['period_start' => '2031-01-04 18:00:00', 'period_end' => '2031-01-11 18:00:00'];

    private function inspeccion(int $col, string $caja, float $dbm, bool $valida = true): void
    {
        DB::table('talento_caja_inspections')->insert([
            'caja_ref' => $caja, 'inspected_by' => $col, 'power_measured' => $dbm,
            'supervisor_validated' => $valida, 'created_at' => '2031-01-06 10:00:00',
        ]);
    }

    private function colaborador(): int
    {
        $uid = DB::table('users')->insertGetId([
            'name' => 'Sint', 'father_last_name' => 'N2', 'email' => Str::random(10) . '@example.test',
            'login_user' => 'n2_' . Str::random(8), 'password' => 'x', 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('talento_colaboradores')->insertGetId([
            'user_id' => $uid, 'type' => 'interno', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_apagada_no_simula_y_no_toca_ledger(): void
    {
        config(['talento.caja_nivel2.enabled' => false]);
        $c = $this->colaborador();
        $this->inspeccion($c, 'CAJA-N2-A', -20.0);
        $ledger = DB::table('talento_ledger_entries')->count();
        $r = app(CajaNivel2SimulacionService::class)->simular($this->week);
        $this->assertSame(0, $r['evaluadas']);
        $this->assertSame($ledger, DB::table('talento_ledger_entries')->count());
    }

    public function test_umbral_borde_e_idempotencia(): void
    {
        config(['talento.caja_nivel2.enabled' => true, 'talento.caja_nivel2.estricto' => true]);
        $c = $this->colaborador();
        $this->inspeccion($c, 'CAJA-N2-OK', -21.0);
        $this->inspeccion($c, 'CAJA-N2-BORDE', -23.0);
        $this->inspeccion($c, 'CAJA-N2-MAL', -25.0);
        $this->inspeccion($c, 'CAJA-N2-SINVAL', -20.0, false);
        $svc = app(CajaNivel2SimulacionService::class);
        $r = $svc->simular($this->week);
        $this->assertSame(3, $r['evaluadas']);
        $this->assertSame(1, $r['nuevas']);
        $r2 = $svc->simular($this->week);
        $this->assertSame(0, $r2['nuevas']);
        $this->assertSame(1, $r2['ya_existian']);
        $this->assertCount(1, $svc->reporte('2031-01-04'));

        config(['talento.caja_nivel2.estricto' => false]);
        $this->assertTrue($svc->califica(-23.0));
    }
}
