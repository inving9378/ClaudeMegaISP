<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\ThomasService;
use Illuminate\Console\Command;

/**
 * TORRE V2 — vuelta del supervisor.
 *
 * NO reparte trabajo: eso ya lo hace `circuito:scheduler`, el único despachador (#432 B1), y
 * duplicarlo crearía una segunda verdad sobre quién trabaja qué. Esta vuelta cubre lo que al
 * scheduler le faltaba:
 *   - resolver consultas que quedaron colgadas (la terminal preguntó y se le acabó el turno),
 *   - sellar la estimación de esfuerzo de lo que está en cola o en vuelo,
 *   - reportar las invariantes del reparto (ocio con cola, colisiones de módulo).
 *
 * Corre enganchado al scheduler (cada minuto) y también a mano. Respeta el kill switch.
 */
class ThomasCommand extends Command
{
    protected $signature = 'circuito:thomas
        {--dry : evalúa y reporta sin escribir nada}
        {--diagnostico : solo imprime el estado del reparto}';

    protected $description = 'Vuelta del supervisor Thomas: resuelve consultas, estima esfuerzo y vigila el reparto.';

    public function handle(ThomasService $thomas): int
    {
        if ($this->option('diagnostico')) {
            $this->line(json_encode($thomas->diagnostico(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $res = $thomas->tick(! $this->option('dry'));

        if (isset($res['saltado'])) {
            $this->warn('Thomas no corrió: ' . $res['saltado']);

            return self::SUCCESS;
        }

        $consultas = $res['consultas_resueltas'] ?? [];
        foreach ($consultas as $c) {
            $this->line(($this->option('dry') ? 'DRY ' : '') . "#{$c['item']} → {$c['decision']}");
        }

        $this->info(($this->option('dry') ? 'DRY ' : '') . 'Thomas: ' . count($consultas)
            . ' consulta(s) resuelta(s), ' . ($res['esfuerzos_sellados'] ?? 0) . ' estimación(es) sellada(s).');

        return self::SUCCESS;
    }
}
