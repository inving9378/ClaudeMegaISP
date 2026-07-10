<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
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

        if ($item->nivel_riesgo === 'C' && ! $this->option('force')) {
            $item->estado_aprobacion = 'requiere_irving';
            $item->save();
            $this->warn("Item #{$item->id} es nivel C: la rama {$branch} NO se auto-integra; requiere el merge de Irving. Item → requiere_irving.");
            return self::SUCCESS;
        }

        if (! $this->git(['rev-parse', '--verify', $branch])->isSuccessful()) {
            $this->error("La rama {$branch} no existe localmente.");
            return self::FAILURE;
        }

        // Árbol de trabajo limpio (solo cambios TRACKED; los untracked no afectan el merge).
        if (trim($this->git(['status', '--porcelain', '--untracked-files=no'])->getOutput()) !== '') {
            $this->error('El árbol de trabajo tiene cambios sin commitear; commitea o descarta antes de integrar.');
            return self::FAILURE;
        }

        if (! $this->git(['checkout', 'main'])->isSuccessful()) {
            $this->error('No se pudo cambiar a main.');
            return self::FAILURE;
        }

        $merge = $this->git(['merge', '--no-ff', $branch, '-m', "Integra circuito #{$item->id} ({$branch}) a main"]);
        if (! $merge->isSuccessful()) {
            $this->git(['merge', '--abort']);
            $item->estado_aprobacion = 'requiere_irving';
            $item->save();
            $this->error("Conflicto/fallo al mergear {$branch} → abortado; rama aislada, item → requiere_irving.\n"
                . $merge->getErrorOutput() . $merge->getOutput());
            return self::FAILURE;
        }

        $sha = trim($this->git(['rev-parse', 'HEAD'])->getOutput());
        $item->merge_commit = $sha;
        $log = $item->log ?: [];
        $log[] = [
            'ts'           => now()->toIso8601String(),
            'por'          => 'circuito:integrar',
            'evento'       => 'merge_a_main',
            'branch'       => $branch,
            'merge_commit' => $sha,
        ];
        $item->log = $log;
        $item->save();

        $this->info("Rama {$branch} integrada a main (merge {$sha}) para el item #{$item->id}.");
        return self::SUCCESS;
    }

    private function git(array $args): Process
    {
        $p = new Process(array_merge(['git'], $args), base_path());
        $p->run();
        return $p;
    }
}
