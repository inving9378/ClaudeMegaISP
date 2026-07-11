<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Console\Command;

/**
 * Estado EN VIVO de la vuelta (#335). Lo llama el wrapper vuelta.sh (usuario meganet,
 * que SÍ puede leer el log) para espejear a BD el heartbeat + el tail del log, de modo
 * que la Torre (www-data, sin acceso al log) muestre "corriendo AHORA".
 *
 *   --start  marca el arranque de la vuelta.
 *   --watch  loop de latido cada N seg hasta recibir SIGTERM (el wrapper lo mata al cerrar).
 *   --end    marca el fin de la vuelta.
 */
class VivoCommand extends Command
{
    protected $signature = 'circuito:vivo
        {--start : marca el arranque de la vuelta}
        {--watch : loop de latido hasta SIGTERM}
        {--end : marca el fin de la vuelta}
        {--log= : ruta del log de la vuelta}
        {--interval=15 : segundos entre latidos en --watch}';

    protected $description = 'Espejea a BD el estado en vivo de la vuelta del Circuito (heartbeat + tail del log).';

    public function handle(RoadmapCircuitoService $svc): int
    {
        $log = (string) ($this->option('log') ?: '');

        if ($this->option('start')) {
            $svc->liveStart($log);
            $this->info('live: start');

            return self::SUCCESS;
        }

        if ($this->option('end')) {
            $svc->liveEnd();
            $this->info('live: end');

            return self::SUCCESS;
        }

        if ($this->option('watch')) {
            return $this->watch($svc, $log);
        }

        $this->error('Especifica --start, --watch o --end.');

        return self::INVALID;
    }

    /** Loop de latido; termina limpio al recibir SIGTERM/SIGINT (o si lo matan de plano). */
    private function watch(RoadmapCircuitoService $svc, string $log): int
    {
        $interval = max(3, (int) $this->option('interval'));
        $stop = false;

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            $handler = function () use (&$stop) {
                $stop = true;
            };
            pcntl_signal(SIGTERM, $handler);
            pcntl_signal(SIGINT, $handler);
        }

        while (! $stop) {
            $svc->liveBeat($log ?: null);
            // Dormir en tramos de 1s para reaccionar rápido a la señal de paro.
            for ($i = 0; $i < $interval && ! $stop; $i++) {
                sleep(1);
            }
        }

        $this->info('live: watch terminado');

        return self::SUCCESS;
    }
}
