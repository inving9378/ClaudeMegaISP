<?php

namespace App\Console\Commands\Active;

use App\Modules\Core\Auditoria\Services\BitacoraMineroService;
use Illuminate\Console\Command;

/**
 * Item #1016 — corrida programada del minero de bitácora (decisión q4:
 * scheduled cada 15 min, ventana incremental por cursor).
 */
class MinarBitacoraCommand extends Command
{
    protected $signature = 'auditoria:minar-bitacora';

    protected $description = 'Mina activity_log en busca de señales de "intención abandonada" (item #1016) y las guarda en auditoria_senales';

    public function handle(BitacoraMineroService $minero): int
    {
        $resultado = $minero->minar();

        if (!$resultado['habilitado']) {
            $this->line('Minero de bitácora deshabilitado (config auditoria.minero_bitacora.enabled).');
            return self::SUCCESS;
        }

        $this->info("Procesadas: {$resultado['procesadas']} · Señales nuevas: {$resultado['senales']}");

        return self::SUCCESS;
    }
}
