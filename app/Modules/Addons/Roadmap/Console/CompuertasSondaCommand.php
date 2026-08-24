<?php

namespace App\Modules\Addons\Roadmap\Console;

use Illuminate\Console\Command;

/**
 * Sonda del sistema operativo para el tablero de compuertas de la Torre.
 *
 * POR QUÉ EXISTE: el panel corre en php-fpm como `www-data`, y `/home/meganet` es
 * `drwx------`. Desde la web no se pueden ver ni los flock de los worktrees, ni el
 * crontab de `meganet`, ni los procesos del ejecutor. Un tablero que dijera "cron
 * activo" sin poder mirarlo estaría inventando — y esa es exactamente la falla que
 * costó una hora el 24-ago (supervisor decía "detenido", el proceso seguía vivo).
 *
 * Así que la medición del SO la hace esta sonda, que corre por cron COMO `meganet`,
 * y deja un snapshot en `storage/app/torre/compuertas-so.json`. El panel lo lee y
 * SIEMPRE muestra su antigüedad: si el snapshot está viejo, esa es una compuerta en
 * rojo por sí misma, no un dato que se presenta como fresco.
 *
 * Su línea de cron es independiente de las de `deploy/circuito`: pausar el circuito
 * no debe dejar ciego al tablero.
 */
class CompuertasSondaCommand extends Command
{
    protected $signature = 'circuito:compuertas-sonda {--print : Muestra el JSON en pantalla en vez de solo escribirlo}';

    protected $description = 'Mide el estado del SO (cron, procesos, locks, workers, logs) para el tablero de compuertas';

    /** Ruta del snapshot. `storage/app` es 777 en este box, así que www-data lo lee. */
    public const SNAPSHOT = 'torre/compuertas-so.json';

    /** Mismo valor que SchedulerCommand::RUNTIME: ahí viven los flock de los worktrees. */
    private const RUNTIME = '/home/meganet/circuito';

    public function handle(): int
    {
        $datos = [
            'medido_en'   => now()->toIso8601String(),
            'medido_ts'   => now()->timestamp,
            'hostname'    => gethostname(),
            'cron'        => $this->medirCron(),
            'ejecutor'    => $this->medirEjecutor(),
            'workers'     => $this->medirWorkers(),
            'slots'       => $this->medirSlots(),
            'logs'        => $this->medirLogs(),
        ];

        $ruta = storage_path('app/' . self::SNAPSHOT);
        @mkdir(dirname($ruta), 0775, true);
        file_put_contents($ruta, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        @chmod($ruta, 0664);

        if ($this->option('print')) {
            $this->line(json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }

    /** Líneas del circuito en el crontab de quien corre la sonda (`meganet`). */
    private function medirCron(): array
    {
        $salida = $this->sh('crontab -l 2>/dev/null');
        $lineas = array_filter(explode("\n", (string) $salida));

        $activas = $pausadas = [];
        foreach ($lineas as $l) {
            if (! str_contains($l, 'deploy/circuito')) {
                continue;
            }
            // Una línea comentada es una pausa deliberada; una sin comentar, cron vivo.
            ltrim($l)[0] === '#' ? $pausadas[] = trim($l) : $activas[] = trim($l);
        }

        return [
            'legible'  => $salida !== null,
            'activas'  => count($activas),
            'pausadas' => count($pausadas),
            'muestra'  => array_slice($activas ?: $pausadas, 0, 3),
        ];
    }

    /**
     * Ejecutor: vueltas vivas, la más antigua, y si alguna quedó huérfana (PPID=1).
     * El huérfano importa porque el 22-ago una vuelta corrió 1d21h reparentada a init,
     * fuera del alcance del cron: pausar el cron no la habría detenido.
     */
    private function medirEjecutor(): array
    {
        $raw = (string) $this->sh("ps -eo pid,ppid,etimes,cmd 2>/dev/null | grep 'deploy/circuito/vuelta.sh' | grep -v grep");
        $vueltas = [];
        foreach (array_filter(explode("\n", $raw)) as $l) {
            if (! preg_match('/^\s*(\d+)\s+(\d+)\s+(\d+)\s+(.*)$/', $l, $m)) {
                continue;
            }
            $vueltas[] = ['pid' => (int) $m[1], 'ppid' => (int) $m[2], 'segundos' => (int) $m[3]];
        }

        $huerfanas = array_values(array_filter($vueltas, fn ($v) => $v['ppid'] === 1));
        $maxSeg    = $vueltas ? max(array_column($vueltas, 'segundos')) : 0;

        // Agentes `claude -p` colgando del ejecutor: el síntoma visible del bucle del 22-ago.
        $agentes = (int) trim((string) $this->sh("ps -eo cmd 2>/dev/null | grep -c 'timeout [0-9]* claude -p'"));

        return [
            'vueltas'          => count($vueltas),
            'huerfanas'        => count($huerfanas),
            'pids_huerfanos'   => array_column($huerfanas, 'pid'),
            'mas_vieja_seg'    => $maxSeg,
            'agentes_claude'   => max(0, $agentes - 1), // -1: el propio grep
            'timeout_nominal'  => (int) config('circuito.vuelta_timeout_seg', 600),
        ];
    }

    /** Workers de supervisor: lo que de verdad corre, no lo que supervisor cree. */
    private function medirWorkers(): array
    {
        $raw = (string) $this->sh("ps -eo pid,etimes,cmd 2>/dev/null | grep 'artisan queue:work' | grep -v grep");
        $vivos = array_values(array_filter(explode("\n", $raw)));

        // `supervisorctl` normalmente exige root en este box; si no se puede, se dice.
        $ctl = $this->sh('supervisorctl status 2>&1');
        $ctlLegible = $ctl !== null && ! str_contains((string) $ctl, 'Permission denied');

        $declarados = [];
        if ($ctlLegible) {
            foreach (array_filter(explode("\n", (string) $ctl)) as $l) {
                if (preg_match('/^(\S+)\s+(\S+)/', $l, $m)) {
                    $declarados[$m[1]] = $m[2];
                }
            }
        }

        return [
            'procesos_vivos'    => count($vivos),
            'ctl_legible'       => $ctlLegible,
            'declarados'        => $declarados,
            'esperados'         => 3,
        ];
    }

    /** Slots: un worktree libre = su flock wt-K.lock NO tomado. */
    private function medirSlots(): array
    {
        $n    = (int) config('circuito.paralelismo', 3);
        $dir  = (string) config('circuito.runtime_dir', self::RUNTIME);
        $libres = $ocupados = [];

        for ($k = 1; $k <= $n; $k++) {
            $f = "{$dir}/wt-{$k}.lock";
            if (! file_exists($f)) {
                $libres[] = "wt-{$k}";
                continue;
            }
            $h = @fopen($f, 'c');
            if (! $h) {
                $ocupados[] = "wt-{$k}";
                continue;
            }
            if (@flock($h, LOCK_EX | LOCK_NB)) {
                @flock($h, LOCK_UN);
                $libres[] = "wt-{$k}";
            } else {
                $ocupados[] = "wt-{$k}";
            }
            @fclose($h);
        }

        // Lock del scheduler: si está tomado sin scheduler vivo, quedó huérfano.
        $sf = "{$dir}/scheduler.lock";
        $schedTomado = false;
        if (file_exists($sf) && ($h = @fopen($sf, 'c'))) {
            $schedTomado = ! @flock($h, LOCK_EX | LOCK_NB);
            if (! $schedTomado) {
                @flock($h, LOCK_UN);
            }
            @fclose($h);
        }

        return [
            'total'            => $n,
            'libres'           => $libres,
            'ocupados'         => $ocupados,
            'scheduler_tomado' => $schedTomado,
        ];
    }

    /** Cascada de errores y tamaño del log: 268.896 excepciones pasaron inadvertidas. */
    private function medirLogs(): array
    {
        $log = storage_path('logs/laravel.log');
        $bytes = file_exists($log) ? (int) filesize($log) : 0;

        // Errores del último minuto, leyendo solo la cola del archivo.
        $errores = (int) trim((string) $this->sh(
            'tail -c 2000000 ' . escapeshellarg($log) . ' 2>/dev/null | grep -c '
            . escapeshellarg('^\[' . now()->format('Y-m-d H:i'))
        ));

        return [
            'laravel_log_bytes' => $bytes,
            'errores_ult_min'   => $errores,
            'disco_libre'       => trim((string) $this->sh("df -h / 2>/dev/null | tail -1 | awk '{print \$4}'")),
            'disco_uso_pct'     => trim((string) $this->sh("df -h / 2>/dev/null | tail -1 | awk '{print \$5}'")),
        ];
    }

    /** Ejecuta un comando de shell devolviendo null si no se pudo. */
    private function sh(string $cmd): ?string
    {
        if (! function_exists('shell_exec')) {
            return null;
        }
        try {
            return @shell_exec($cmd);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
