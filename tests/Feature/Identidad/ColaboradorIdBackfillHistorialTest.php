<?php

namespace Tests\Feature\Identidad;

use App\Modules\Core\Clientes\Models\ClientMainInformation;
use App\Observers\Identidad\ColaboradorIdBridgeObserver;
use App\Services\Identidad\ColaboradorIdResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** UNI-T C2 (#878): historial del observer y reversión del backfill. Solo contra megaisp_test. */
class ColaboradorIdBackfillHistorialTest extends TestCase
{
    use DatabaseTransactions;

    public function test_observer_registra_historial_solo_con_flag_encendido(): void
    {
        $m = new ClientMainInformation();
        $m->setRawAttributes(['id' => 987654321, 'seller_id' => 5, 'colaborador_id' => 7], true);
        $m->forceFill(['colaborador_id' => 9]);
        $m->syncChanges();

        config(['identidad.doble_escritura_colaborador_id' => false]);
        (new ColaboradorIdBridgeObserver())->updated($m);
        $this->assertSame(0, DB::table('identidad_cambios_historial')->where('registro_id', 987654321)->count());

        config(['identidad.doble_escritura_colaborador_id' => true]);
        (new ColaboradorIdBridgeObserver())->updated($m);
        $fila = DB::table('identidad_cambios_historial')->where('registro_id', 987654321)->first();
        $this->assertNotNull($fila);
        $this->assertSame('colaborador_id', $fila->campo);
        $this->assertEquals(7, $fila->valor_anterior);
        $this->assertEquals(9, $fila->valor_nuevo);
    }

    public function test_backfill_revertir_con_archivo_inexistente_falla(): void
    {
        $this->artisan('identidad:backfill-colaborador-id', ['--revertir' => '/tmp/no-existe-uni.json'])
            ->assertExitCode(1);
        ColaboradorIdResolver::resetCache();
    }
}
