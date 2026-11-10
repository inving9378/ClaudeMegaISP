<?php

namespace Tests\Feature\MapaRed;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Item #1246 — mapared:clientes-ficticios: dry-run no escribe, crear es idempotente y --borrar solo quita lo marcado. */
class ClientesFicticiosTest extends TestCase
{
    use DatabaseTransactions;

    private function sembrarCaja(): void
    {
        DB::table('users')->insert(['name' => 'SISTEMA PRUEBA', 'login_user' => 'sistema_prueba_1246', 'is_system' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('mapared_devices')->insert([
            'type' => 'drop', 'name' => 'CAJA PRUEBA', 'lat' => 19.5, 'lng' => -99.1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function ficticios(): int
    {
        return DB::table('client_main_information')->where('email', 'like', '%@prueba.meganet.local')->count();
    }

    public function test_dry_run_no_escribe(): void
    {
        $this->sembrarCaja();
        $antes = DB::table('clients')->count();
        $this->artisan('mapared:clientes-ficticios', ['--cantidad' => 3, '--dry-run' => true])->assertExitCode(0);
        $this->assertSame($antes, DB::table('clients')->count());
        $this->assertSame(0, $this->ficticios());
    }

    public function test_crear_idempotente_y_borrar_solo_lo_marcado(): void
    {
        $this->sembrarCaja();
        $this->artisan('mapared:clientes-ficticios', ['--cantidad' => 3, '--sin-respaldo' => true])->assertExitCode(0);
        $this->assertSame(3, $this->ficticios());
        $this->artisan('mapared:clientes-ficticios', ['--cantidad' => 3, '--sin-respaldo' => true])->assertExitCode(0);
        $this->assertSame(3, $this->ficticios());
        $this->assertSame(3, DB::table('client_main_information')->whereNotNull('geodata')->where('user', 'like', 'fic\_%')->count());

        $this->artisan('mapared:clientes-ficticios', ['--borrar' => true, '--sin-respaldo' => true])->assertExitCode(0);
        $this->assertSame(0, $this->ficticios());
    }
}
