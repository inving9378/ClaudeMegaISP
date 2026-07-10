<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\CircuitoEjecucion;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Registra una fila de ejecución del circuito (la llama el wrapper vuelta.sh al cerrar).
 * Timing/modo/rc los pasa el wrapper; items/propuestas/decisiones/ejecuto vienen del
 * bloque CIRCUITO_META (JSON) que emite el prompt del ejecutor.
 */
class RegistrarEjecucionCommand extends Command
{
    protected $signature = 'circuito:registrar-ejecucion
        {--started= : epoch de inicio}
        {--finished= : epoch de fin}
        {--modo=aviso_previo}
        {--pausado=0}
        {--rc= : código de salida}
        {--log= : ruta del log}
        {--meta= : JSON con items_tocados,n_propuestas,n_decisiones,ejecuto,resumen}';

    protected $description = 'Registra una ejecución (vuelta) del ejecutor on-box del Circuito.';

    public function handle(): int
    {
        $meta = json_decode((string) ($this->option('meta') ?: '{}'), true);
        if (! is_array($meta)) {
            $meta = [];
        }

        $started  = $this->option('started');
        $finished = $this->option('finished');
        $dur = ($started !== null && $finished !== null) ? max(0, (int) $finished - (int) $started) : null;

        CircuitoEjecucion::create([
            'started_at'   => $started ? Carbon::createFromTimestamp((int) $started) : now(),
            'finished_at'  => $finished ? Carbon::createFromTimestamp((int) $finished) : now(),
            'duracion_seg' => $dur,
            'modo'         => (string) ($this->option('modo') ?: 'aviso_previo'),
            'pausado'      => $this->option('pausado') === '1',
            'rc'           => $this->option('rc') !== null ? (int) $this->option('rc') : null,
            'items_tocados' => $meta['items_tocados'] ?? null,
            'n_propuestas' => (int) ($meta['n_propuestas'] ?? 0),
            'n_decisiones' => (int) ($meta['n_decisiones'] ?? 0),
            'ejecuto'      => (bool) ($meta['ejecuto'] ?? false),
            'resumen'      => isset($meta['resumen']) ? mb_substr((string) $meta['resumen'], 0, 2000) : null,
            'log_path'     => $this->option('log'),
        ]);

        $this->info('ejecucion registrada');
        return self::SUCCESS;
    }
}
