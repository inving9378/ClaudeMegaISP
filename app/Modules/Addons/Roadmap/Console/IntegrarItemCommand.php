<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Integra la rama de un item del Circuito (circuito/item-<id>-<slug>) a `main` (la línea
 * de dev), tras la verificación de regresión. Enrutamiento por nivel:
 *   - A/B verificados → merge --no-ff a main + registra merge_commit en el item.
 *   - C → NO se auto-integra; el item vuelve a requiere_irving (lo mergea Irving).
 * SOLO opera git LOCAL (merge a main). NUNCA hace push ni toca prod (.108). Si el merge
 * falla (conflicto), aborta, deja la rama aislada y escala el item a requiere_irving.
 */
class IntegrarItemCommand extends Command
{
    protected $signature = 'circuito:integrar {id : ID del item a integrar} {--force : integrar aunque sea nivel C}';

    protected $description = 'Integra la rama del item a main (A/B auto; C requiere a Irving). Nunca push ni prod.';

    public function handle(): int
    {
        $item = RoadmapItem::find($this->argument('id'));
        if (! $item) {
            $this->error('Item no encontrado.');
            return self::FAILURE;
        }
        if (! $item->branch) {
            $this->error("El item #{$item->id} no tiene rama registrada (corre circuito:rama primero).");
            return self::FAILURE;
        }

        $branch = $item->branch;
        $svc = app(RoadmapCircuitoService::class);
        $force = (bool) $this->option('force');

        // El merge YA NO lo hace este comando: el ejecutor puede correr en un worktree que NO puede
        // checar `main` (#334), y www-data no puede escribir `.git`. Se ENCOLA y lo aplica el runner
        // on-box (meganet) en el checkout principal, serializado y con verificación de regresión.

        // Nivel C nunca se auto-integra (solo Irving con el botón / --force).
        if ($item->nivel_riesgo === 'C' && ! $force) {
            $item->estado_aprobacion = 'requiere_irving';
            $item->save();
            $this->warn("Item #{$item->id} es nivel C: NO se auto-integra; requiere el merge de Irving. Item → requiere_irving.");
            return self::SUCCESS;
        }

        // Toggle OFF (manual): el ejecutor NO encola; la rama espera el botón de Irving en la Torre.
        if (! $force && ! $svc->autoMergeOn()) {
            $this->warn("Auto-merge OFF: la rama {$branch} NO se integra sola; espera el ✓ de Irving en la Torre.");
            return self::SUCCESS;
        }

        // Auto-merge ON (o --force de Irving): encola. El runner hace el merge real.
        $svc->enqueueMerge($item->id, $force ? 'boton' : 'ejecutor', $force ? 'boton' : 'auto');
        $this->info("Merge de la rama {$branch} ENCOLADO (lo aplica el runner on-box en el principal). Item #{$item->id}.");
        return self::SUCCESS;
    }

    private function git(array $args): Process
    {
        $p = new Process(array_merge(['git'], $args), base_path());
        $p->run();
        return $p;
    }
}
