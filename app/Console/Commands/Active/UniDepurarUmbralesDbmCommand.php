<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * UNI-T D6b (#1017) — Quita las filas DUPLICADAS de talento_dbm_thresholds (misma categoría y mismos
 * límites, sembradas dos veces el 2026-06-05). Conserva la de menor id; NO cambia ningún valor vigente.
 * Los huecos de 0.01 dB entre rangos se reportan, pero no se tocan.
 * Sin --aplicar solo simula. Con --aplicar guarda las filas retiradas en un json para revertir.
 * Reversión: --revertir=<archivo json>.
 */
class UniDepurarUmbralesDbmCommand extends Command
{
    protected $signature = 'uni:depurar-umbrales-dbm
                            {--aplicar : ejecuta el retiro (sin esto es dry-run)}
                            {--revertir= : ruta del json de reversión generado por --aplicar}
                            {--dir=/var/backups/mysql/preservados/uni : carpeta de respaldos}';

    protected $description = 'UNI-T D6b: depura umbrales dBm duplicados (dry-run por defecto), con respaldo y reversión.';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Solo DEV.');

            return 1;
        }
        if ($f = $this->option('revertir')) {
            return $this->revertir($f);
        }

        $filas = DB::table('talento_dbm_thresholds')->orderBy('id')->get();
        $vistos = [];
        $retirar = [];
        foreach ($filas as $r) {
            $llave = implode('|', [$r->categoria, $r->dbm_min, $r->dbm_max, $r->accion, $r->mensaje, $r->aplica_bono, $r->sort_order]);
            isset($vistos[$llave]) ? $retirar[] = (array) $r : $vistos[$llave] = true;
        }

        $this->info('Umbrales: '.count($filas).'. Duplicados exactos a retirar: '.count($retirar).' (ids '.implode(',', array_column($retirar, 'id')).').');
        $this->info('Quedarían: '.(count($filas) - count($retirar)).'.');

        $conservados = $filas->whereNotIn('id', array_column($retirar, 'id'))->sortBy('sort_order')->values();
        for ($i = 0; $i + 1 < $conservados->count(); $i++) {
            $a = $conservados[$i];
            $b = $conservados[$i + 1];
            if ($a->dbm_min !== null && $b->dbm_max !== null) {
                $hueco = round((float) $a->dbm_min - (float) $b->dbm_max, 2);
                if ($hueco > 0) {
                    $this->line("Hueco de $hueco dB entre {$b->categoria} y {$a->categoria} (solo informativo, no se toca).");
                }
            }
        }

        if (! $this->option('aplicar')) {
            $this->warn('Dry-run: nada modificado. Usa --aplicar.');

            return 0;
        }
        if (! $retirar) {
            $this->info('Nada que depurar.');

            return 0;
        }

        $dir = rtrim($this->option('dir'), '/');
        if (! is_dir($dir) || ! is_writable($dir)) {
            $this->error("No puedo escribir en $dir; abortado antes de modificar.");

            return 1;
        }
        $rev = "$dir/uni-umbrales-dbm-".date('Ymd-His').'-revertir.json';
        file_put_contents($rev, json_encode($retirar));

        DB::transaction(fn () => DB::table('talento_dbm_thresholds')->whereIn('id', array_column($retirar, 'id'))->delete());
        $this->info('Retiradas '.count($retirar)." filas. Reversión: php artisan uni:depurar-umbrales-dbm --revertir=$rev");

        return 0;
    }

    private function revertir(string $f): int
    {
        $filas = json_decode((string) @file_get_contents($f), true);
        if (! is_array($filas)) {
            $this->error('Archivo de reversión inválido.');

            return 1;
        }
        $n = 0;
        DB::transaction(function () use ($filas, &$n) {
            foreach ($filas as $d) {
                if (! DB::table('talento_dbm_thresholds')->where('id', $d['id'])->exists()) {
                    DB::table('talento_dbm_thresholds')->insert($d);
                    $n++;
                }
            }
        });
        $this->info("Restauradas $n filas.");

        return 0;
    }
}
