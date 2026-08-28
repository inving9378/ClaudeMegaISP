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
            // Solo cuentan las líneas que de verdad son entradas de cron. Un comentario
            // en prosa que mencione la ruta (como el que documenta esta misma sonda) no
            // es una compuerta pausada, y contarlo desplazaría el número que se muestra.
            // Debe invocar de verdad el wrapper y traer una expresión de cron de 5 campos.
            // Las líneas pausadas llevan un prefijo ('# PAUSADO-...: * * * * * ...'), así que
            // la expresión se busca en cualquier posición, no anclada al inicio.
            if (! str_contains($l, 'cron-wrap.sh')
                || ! preg_match('/(^|:|\s)([\d*\/,-]+\s+){4}[\d*\/,-]+\s+\S/', $l)) {
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

        // COLGADA ≠ huérfana (2026-08-26). El cron lanza TODAS sus vueltas desprendidas, así que
        // PPID=1 es el estado normal de una vuelta sana y por sí solo no dice nada. La anomalía real
        // —la del 22-ago— es la que sobrevive a su propio timeout: `timeout` mata al agente hijo, no
        // al bucle padre. Se sigue publicando `huerfanas` (informativo, y para no romper a un lector
        // viejo del snapshot), pero lo que enciende la alarma es esto.
        $umbralColgada = (int) config('circuito.vuelta_colgada_seg', 3600);
        $colgadas      = array_values(array_filter($vueltas, fn ($v) => $v['segundos'] > $umbralColgada));

        // Agentes `claude -p` colgando del ejecutor: el síntoma visible del bucle del 22-ago.
        $agentes = (int) trim((string) $this->sh("ps -eo cmd 2>/dev/null | grep -c 'timeout [0-9]* claude -p'"));

        return [
            'vueltas'          => count($vueltas),
            'huerfanas'        => count($huerfanas),
            'pids_huerfanos'   => array_column($huerfanas, 'pid'),
            'mas_vieja_seg'    => $maxSeg,
            'colgadas'         => count($colgadas),
            'pids_colgados'    => array_column($colgadas, 'pid'),
            'umbral_colgada'   => $umbralColgada,
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
        $log = $this->rutaLogActivo();
        $bytes = ($log !== null && file_exists($log)) ? (int) filesize($log) : 0;

        // Errores del último minuto: SOLO líneas de nivel ERROR/CRITICAL/ALERT/EMERGENCY
        // (antes contaba cualquier línea del minuto sin filtrar nivel — "errores_ult_min"
        // medía volumen de log, no errores), leyendo solo la cola del archivo.
        $errores = $log === null ? 0 : (int) trim((string) $this->sh(
            'tail -c 2000000 ' . escapeshellarg($log) . ' 2>/dev/null | grep -cE '
            . escapeshellarg('^\[' . now()->format('Y-m-d H:i') . '[^]]*\]\s+\S+\.(ERROR|CRITICAL|ALERT|EMERGENCY):')
        ));

        return [
            'laravel_log_bytes' => $bytes,
            'errores_ult_min'   => $errores,
            'disco_libre'       => trim((string) $this->sh("df -h / 2>/dev/null | tail -1 | awk '{print \$4}'")),
            'disco_uso_pct'     => trim((string) $this->sh("df -h / 2>/dev/null | tail -1 | awk '{print \$5}'")),
        ];
    }

    /**
     * Ruta del log activo AHORA MISMO, derivada de la config real de logging — no una ruta fija.
     * El item #175 cambió el canal default de 'single' a 'daily' (mismo incidente: laravel.log
     * llegó a 1.7 GB sin rotación), y esta sonda seguía apuntando a `storage/logs/laravel.log` a
     * secas: ese archivo quedó CONGELADO desde el cambio de canal, así que `errores_ult_min`
     * llevaba días leyendo un archivo que ya no crece — cero errores para siempre, alarma muda,
     * exactamente la falsa calma que este umbral existe para evitar. Sigue el canal 'stack' hasta
     * su primer canal real y arma el nombre fechado que usa el driver 'daily' de Monolog.
     */
    private function rutaLogActivo(): ?string
    {
        $canal = (string) config('logging.default', 'stack');
        $cfg = (array) config("logging.channels.{$canal}", []);

        if (($cfg['driver'] ?? null) === 'stack') {
            $primero = (string) (($cfg['channels'] ?? [])[0] ?? '');
            $cfg = (array) config("logging.channels.{$primero}", []);
        }

        $ruta = $cfg['path'] ?? null;
        if (! is_string($ruta) || $ruta === '') {
            return null;
        }

        if (($cfg['driver'] ?? null) !== 'daily') {
            return $ruta;
        }

        $ext = pathinfo($ruta, PATHINFO_EXTENSION);
        $base = $ext !== '' ? mb_substr($ruta, 0, -(mb_strlen($ext) + 1)) : $ruta;

        return $base . '-' . now()->format('Y-m-d') . ($ext !== '' ? '.' . $ext : '');
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
