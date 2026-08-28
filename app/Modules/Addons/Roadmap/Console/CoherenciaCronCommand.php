<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\CronScriptsGuard;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * #233 — candado de coherencia crontab↔disco para los scripts de `deploy/circuito/`. READ-ONLY.
 *
 * Contrato = exit code (0 todo ejecutable, 1 hay algo roto), igual que `circuito:coherencia-pool`,
 * para poder colgarlo de un cron o de la Torre sin parsear la salida.
 */
class CoherenciaCronCommand extends Command
{
    protected $signature = 'circuito:coherencia-cron';

    protected $description = 'READ-ONLY: verifica que todo script de deploy/circuito/ que el crontab real invoca por ruta siga siendo ejecutable.';

    public function handle(): int
    {
        $p = Process::fromShellCommandline('crontab -l 2>/dev/null');
        $p->setTimeout(5);
        $p->run();
        $salida = $p->getOutput();

        if (trim($salida) === '') {
            $this->warn('Sin crontab legible para este usuario — nada que verificar.');

            return self::SUCCESS;
        }

        $problemas = CronScriptsGuard::problemas($salida);

        if (! $problemas) {
            $this->info('✔ Todos los scripts de deploy/circuito/ que el crontab invoca son ejecutables.');

            return self::SUCCESS;
        }

        $this->error('✘ El crontab apunta a scripts de deploy/circuito/ que no se pueden ejecutar:');
        foreach ($problemas as $item) {
            $this->line("  · {$item['archivo']} — {$item['motivo']}");
            $this->line("    línea: {$item['linea']}");
        }

        return self::FAILURE;
    }
}
