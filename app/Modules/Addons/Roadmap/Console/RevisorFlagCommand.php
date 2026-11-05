<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Console\Command;

/**
 * #338 — Control del flag `circuito_revisor` (on/off). Es de Irving: gatea que el ejecutor
 * ejecute los `aprobado_revisor` y que corra el revisor en su ciclo. Default OFF (arranque
 * conservador). El EJECUTOR NO debe tocarlo (es solo-lectura para él, como el kill switch).
 */
class RevisorFlagCommand extends Command
{
    protected $signature = 'circuito:revisor {estado? : on|off|status}';

    protected $description = 'Enciende/apaga o consulta el flag circuito_revisor (control de Irving).';

    public function handle(RoadmapCircuitoService $svc): int
    {
        $estado = strtolower((string) $this->argument('estado') ?: 'status');

        if ($estado === 'on') {
            $svc->setRevisorEnabled(true);
        } elseif ($estado === 'off') {
            $svc->setRevisorEnabled(false);
        } elseif ($estado !== 'status') {
            $this->error("Uso: circuito:revisor {on|off|status}");

            return self::FAILURE;
        }

        $on = $svc->revisorEnabled();
        $this->line('circuito_revisor=' . ($on ? 'on' : 'off'));
        $this->line('alcance_denylist=' . count((array) config('circuito.revisor.alcance.denylist', [])) . ' términos');
        // #1076 — el que de verdad usa (Integraciones → Módulos IA o el ahorro por defecto).
        $this->line('modelo=' . \App\Modules\Addons\Roadmap\Support\IaSupervisor::modelo(\App\Modules\Addons\Roadmap\Support\IaSupervisor::REVISOR));

        return self::SUCCESS;
    }
}
