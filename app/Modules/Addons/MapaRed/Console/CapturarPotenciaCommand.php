<?php

namespace App\Modules\Addons\MapaRed\Console;

use App\Modules\Addons\MapaRed\Models\MapaRedPuerto;
use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
use App\Modules\Addons\MapaRed\Services\MapaRedPotenciaHistorialService;
use Illuminate\Console\Command;

/** Item #1270 (MR-33) — captura diaria de potencia por caja + purga a 12 meses. */
class CapturarPotenciaCommand extends Command
{
    protected $signature = 'mapared:potencia-capturar';
    protected $description = 'Guarda la lectura diaria de potencia de cada caja con enlaces activos y purga lecturas de más de 12 meses';

    public function handle(MapaRedPotenciaHistorialService $servicio): int
    {
        $ids = MapaRedPuerto::query()
            ->where('puertable_type', MapaRedLayer::class)
            ->where('rol', MapaRedPuerto::ROL_NAP_SALIDA)
            ->distinct()->pluck('puertable_id');

        $cuenta = [];
        foreach ($ids as $id) {
            try {
                $r = $servicio->capturar((int) $id);
            } catch (\Throwable $e) {
                $r = 'error';
            }
            $cuenta[$r] = ($cuenta[$r] ?? 0) + 1;
        }
        $purgadas = $servicio->purgar();
        $this->info('Cajas: ' . json_encode($cuenta) . " · purgadas: {$purgadas}");

        return self::SUCCESS;
    }
}
