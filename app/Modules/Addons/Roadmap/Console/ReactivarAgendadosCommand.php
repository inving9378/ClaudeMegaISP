<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;

/**
 * Fase 2 de #921. `sqlElegibleParaPool()` ya excluye del pool cualquier item con `agendado_para`
 * futuro (Fase 1). Este comando es el que los REGRESA: busca items con `agendado_para` vencido
 * (<= NOW() de SQL, igual que el candado de elegibilidad) y limpia el campo para que vuelvan al
 * pool sin intervención humana. Corre diario vía Kernel.php (mismo patrón que
 * `activitylog:archive`/`invoice:create-proformas`, no vive en `command_configs`).
 *
 * El PULSO (#957) no lo sella este comando: ya existe un mecanismo genérico para esto desde
 * #808 (`RoadmapCircuitoService::sellarLatido()`, disparado por el listener único de
 * `CommandFinished` en `ModuleServiceProvider::vigilarProcesosProgramados()`). Basta con
 * declarar este comando en `config('circuito.procesos_programados')` — nada que instrumentar
 * aquí. Ese pulso ya alimenta el panel "motores" de la Torre (`GET /api/roadmap/torre-config`)
 * y la primera línea del digest diario; ambos leen "¿corrió en las últimas N horas?" y avisan
 * si no, que es justo el requisito no negociable del item ("un agendado que muere en silencio
 * es peor que la bandera manual"). Reintroducir una tabla/columna de heartbeat propia aquí
 * hubiera sido duplicar ese servicio — ver la regla de servicios compartidos únicos.
 */
class ReactivarAgendadosCommand extends Command
{
    protected $signature = 'circuito:reactivar-agendados';

    protected $description = 'Limpia agendado_para de los items del Roadmap cuya fecha ya pasó, para que vuelvan al pool (#921 Fase 2).';

    public function handle(): int
    {
        $items = RoadmapItem::agendados()->whereRaw('agendado_para <= NOW()')->get();

        $ids = [];
        foreach ($items as $item) {
            $log = is_array($item->log) ? $item->log : [];
            $log[] = [
                'ts'                    => now()->toIso8601String(),
                'por'                   => 'circuito:reactivar-agendados',
                'evento'                => 'agendado_reactivado',
                'agendado_para_previo'  => optional($item->agendado_para)->toIso8601String(),
            ];
            $item->log = $log;
            $item->agendado_para = null;
            $item->save();
            $ids[] = $item->id;
        }

        $this->info(count($ids) > 0
            ? 'Reactivados ' . count($ids) . ' item(s): #' . implode(', #', $ids)
            : 'Sin items agendados vencidos.');

        return self::SUCCESS;
    }
}
