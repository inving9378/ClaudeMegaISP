<?php

namespace App\Console\Commands\Active;

use App\Services\Deploy\ReleasePreflightService;
use Illuminate\Console\Command;

/**
 * Preflight de release READ-ONLY (item roadmap #9990681, F2b de la épica #9990668). Corre los
 * 9 chequeos de ReleasePreflightService::evaluate() y los imprime en tabla + veredicto final.
 * No toca ni pipeline de deploy ni prod — F2c (item futuro) decidirá si esto se engancha.
 */
class ReleasePreflightCommand extends Command
{
    protected $signature = 'release:preflight
                            {version : Versión a evaluar (ej. V1.34-09.09.2026)}
                            {--branch= : Rama/commit a evaluar en vez de HEAD}';

    protected $description = 'Corre los 9 chequeos de preflight de release (solo lectura, item #9990681)';

    public function handle(ReleasePreflightService $service): int
    {
        $version = $this->argument('version');
        $branch  = $this->option('branch');

        $resultado = $service->evaluate($version, $branch);

        $filas = array_map(fn (array $check) => [
            $check['key'],
            $check['label'],
            strtoupper($check['status']),
            $check['detail'],
        ], $resultado['checks']);

        $this->table(['Check', 'Descripción', 'Estado', 'Detalle'], $filas);

        $etiquetas = ['verde' => 'VERDE (ok)', 'amarillo' => 'AMARILLO (warn)', 'rojo' => 'ROJO (fail)'];
        $this->line('');
        $this->line('Veredicto: ' . ($etiquetas[$resultado['veredicto']] ?? $resultado['veredicto']));

        return self::SUCCESS;
    }
}
