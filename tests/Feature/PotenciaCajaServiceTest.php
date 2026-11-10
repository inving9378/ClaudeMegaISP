<?php

namespace Tests\Feature;

use App\Modules\Addons\MapaRed\Services\MapaRedNapHealthService;
use App\Modules\Addons\MapaRed\Services\PotenciaCajaService;
use App\Services\OLTsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Item #1235 — datos sintéticos, OLTsService simulado (nunca SmartOLT). Solo por nombre. */
class PotenciaCajaServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (!Schema::hasColumn('mapared_devices_ports', 'potencia_inicial_dbm')) {
            $this->markTestSkipped('Falta migrar potencia_inicial_dbm en la BD de pruebas.');
        }
    }

    private function olts(bool $falla): OLTsService
    {
        return new class($falla) extends OLTsService {
            public function __construct(private bool $falla) {}

            public function syncSignal($id)
            {
                if ($this->falla) {
                    throw new \RuntimeException('presupuesto agotado');
                }

                return ['success' => true];
            }
        };
    }

    public function test_constante_y_caja_es_minimo_mas_uno(): void
    {
        $this->assertSame(1.0, PotenciaCajaService::MARGEN_CAJA_DB);
        $r = (new PotenciaCajaService($this->olts(true)))->calcular(999999999);
        $this->assertNull($r['potencia_caja_dbm']);
        $this->assertSame(0, $r['total_clientes']);
    }

    public function test_semaforo_usa_signal_1490_como_rx(): void
    {
        $src = file_get_contents(base_path('app/Modules/Addons/MapaRed/Services/MapaRedNapHealthService.php'));
        $this->assertStringContainsString("'rx_dbm' => \$onu->signal_1490", $src);
        $this->assertTrue(class_exists(MapaRedNapHealthService::class));
    }
}
