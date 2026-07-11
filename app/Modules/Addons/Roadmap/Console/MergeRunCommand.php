<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\MergeRunner;
use Illuminate\Console\Command;

/**
 * Drena la cola de MERGE del Circuito (#334 F0-fix): ejecuta los merges encolados por la Torre
 * o el auto-merge, EN EL CHECKOUT PRINCIPAL como meganet (que sí puede escribir `.git`).
 * Lo llama el picker on-box (circuito:disparo-check) cada pocos segundos, y también sirve para
 * disparar un drain manual. Serializado por flock dentro del MergeRunner. Nunca push ni prod.
 */
class MergeRunCommand extends Command
{
    protected $signature = 'circuito:merge-run';

    protected $description = 'Ejecuta los merges encolados (auto-merge / botón de la Torre) en el checkout principal.';

    public function handle(MergeRunner $runner): int
    {
        $res = $runner->drain();
        if (! $res) {
            $this->info('Cola de merge vacía (o ya hay un drain en curso).');

            return self::SUCCESS;
        }
        foreach ($res as $r) {
            $estado = $r['ok'] ? 'OK' : ($r['escalado'] ? 'ESCALADO' : 'FALLO');
            $this->line("#{$r['item_id']}: {$estado} — " . str_replace("\n", ' ', (string) ($r['salida'] ?? '')));
        }

        return self::SUCCESS;
    }
}
