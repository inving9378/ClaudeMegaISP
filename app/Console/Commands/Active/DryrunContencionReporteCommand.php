<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;

/**
 * Item #831 (opción 1 elegida por Irving — medir antes de resolver): lee
 * storage/logs/dryrun-contention-*.log (escrito por el trait MideContencionDryrun,
 * usado por schema:rebuild-dryrun y deploy:dry-run-migrations) y resume las colisiones
 * detectadas sobre la BD compartida `{database}_dryrun`. SOLO LECTURA — no toca la BD
 * ni los comandos instrumentados; sirve para armar el reporte que la opción 1 pide tras
 * correr el circuito con varios agentes en paralelo.
 */
class DryrunContencionReporteCommand extends Command
{
    protected $signature = 'dryrun:reporte-contencion {--dias=1 : Cuántos archivos de log diarios incluir (más recientes primero)}';

    protected $description = 'Resume las colisiones detectadas por la instrumentación del item #831 sobre megaisp_dryrun.';

    public function handle(): int
    {
        $dias = max(1, (int) $this->option('dias'));

        $files = collect(glob(storage_path('logs/dryrun-contention-*.log')) ?: [])
            ->sortDesc()
            ->take($dias);

        if ($files->isEmpty()) {
            $this->warn('Sin archivos de log todavía (storage/logs/dryrun-contention-*.log). Nada que reportar aún.');
            return 0;
        }

        $eventos = [];
        foreach ($files as $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                if (!preg_match('/^\[(?<ts>[^\]]+)\][^:]*:\s*(?<msg>.*?)\s*(?<json>\{.*\})\s*$/', $line, $m)) {
                    continue;
                }
                $ctx = json_decode($m['json'], true);
                if (!is_array($ctx)) {
                    continue;
                }
                $eventos[] = array_merge(['ts' => $m['ts'], 'msg' => $m['msg']], $ctx);
            }
        }

        $intentos   = array_values(array_filter($eventos, fn ($e) => array_key_exists('colision', $e)));
        $colisiones = array_values(array_filter($intentos, fn ($e) => $e['colision'] === true));
        $fines      = array_values(array_filter($eventos, fn ($e) => array_key_exists('resultado', $e)));
        $fallidos   = array_filter($fines, fn ($e) => ($e['resultado'] ?? null) === 'FALLÓ');

        $this->info(sprintf(
            'Intentos de lock: %d · Colisiones detectadas: %d · Operaciones cerradas: %d (%d FALLÓ/omitido)',
            count($intentos),
            count($colisiones),
            count($fines),
            count($fallidos)
        ));

        if (empty($colisiones)) {
            $this->line('Ninguna colisión detectada en el rango leído.');
            return 0;
        }

        $this->table(
            ['ts', 'comando', 'sid', 'temp_db'],
            array_map(fn ($e) => [$e['ts'], $e['comando'] ?? '-', $e['sid'] ?? '-', $e['temp_db'] ?? '-'], $colisiones)
        );

        return 0;
    }
}
