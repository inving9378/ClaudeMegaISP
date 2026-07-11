<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * PICKER on-box del disparo manual (#337). Corre como meganet desde el cron cada minuto con
 * un loop interno (`--watch`) que sondea el flag cada pocos segundos → latencia de segundos
 * sin infra nueva. Cuando ve un disparo pendiente y el circuito NO está en pausa ni corriendo,
 * consume el flag y lanza vuelta.sh (que a su vez enciende el estado en vivo #335).
 *
 * Candados: flock propio (un solo picker) + respeta kill switch + no solapa vueltas (si ya
 * corre una, deja el flag pendiente → se dispara al terminar = "encola") + el flock de la
 * propia vuelta.sh como última barrera.
 */
class DisparoCheckCommand extends Command
{
    protected $signature = 'circuito:disparo-check
        {--watch=0 : segundos a sondear en un loop interno (0 = una sola pasada)}
        {--poll=3 : segundos entre sondeos en modo watch}';

    protected $description = 'Picker del disparo manual del Circuito: consume el flag y lanza la vuelta.';

    private const PICKER_LOCK = '/home/meganet/circuito/picker.lock';

    public function handle(RoadmapCircuitoService $svc): int
    {
        // Un solo picker a la vez (evita doble-consumo entre invocaciones solapadas del cron).
        $lock = @fopen(self::PICKER_LOCK, 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return self::SUCCESS; // ya hay un picker vivo
        }

        try {
            $watch = max(0, (int) $this->option('watch'));
            $poll  = max(1, (int) $this->option('poll'));

            if ($watch === 0) {
                $this->tick($svc);

                return self::SUCCESS;
            }

            $deadline = time() + $watch;
            while (time() < $deadline) {
                $this->tick($svc);
                sleep($poll);
            }

            return self::SUCCESS;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Una evaluación del flag. Best-effort: nunca revienta el loop. */
    private function tick(RoadmapCircuitoService $svc): void
    {
        // Drena la cola de MERGE (#334 F0-fix): corre en el checkout principal como meganet →
        // sí puede escribir .git. Serializado por su propio flock. Independiente del disparo.
        try {
            app(\App\Modules\Addons\Roadmap\Services\MergeRunner::class)->drain();
        } catch (\Throwable $e) {
            // no tumbar el picker por un fallo de merge; el resultado ya quedó registrado.
        }

        try {
            if (! $svc->pendingDisparo()) {
                return;
            }

            // En pausa (kill switch): NO ejecuta y descarta el flag (decisión: pausa BLOQUEA).
            if ($svc->isPaused()) {
                $svc->clearDisparo();

                return;
            }

            // No solapar: si YA corre alguna vuelta, deja el flag PENDIENTE (se sirve al terminar).
            // No consumimos aquí; el flag lo consume vuelta.sh vía `circuito:vivo --start`, así
            // una colisión con el flock nunca pierde el disparo. (#334: anyRunning agrega sesiones.)
            if ($svc->anyRunning()) {
                return;
            }

            // Lanza la vuelta detached. El flag se consume dentro de la vuelta que arranque.
            $this->lanzarVuelta();
        } catch (\Throwable $e) {
            // No tumbar el picker por un fallo puntual; el siguiente sondeo reintenta.
        }
    }

    /** Lanza deploy/circuito/vuelta.sh completamente desprendido del picker. */
    private function lanzarVuelta(): void
    {
        $script = base_path('deploy/circuito/vuelta.sh');
        // setsid + nohup + `&`: sobrevive a la salida del picker; el shell retorna al instante.
        $cmd = 'setsid nohup ' . escapeshellarg($script) . ' >/dev/null 2>&1 &';
        $p = Process::fromShellCommandline($cmd, base_path());
        $p->run();
    }
}
