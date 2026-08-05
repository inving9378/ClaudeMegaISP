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

        // #438 — colisión EN VUELO: si el scheduler ya marcó a este item como PERDEDOR (otro item
        // en vuelo toca los mismos archivos y arrancó primero), este es su checkpoint natural para
        // autopausarse — NUNCA se mata el proceso a mitad de la vuelta. Deja la rama intacta (con
        // todo lo ya commiteado) y regresa el item a un estado que el circuito vuelve a despachar
        // solo cuando el ganador termine (RoadmapCircuitoService::reanudarColisionesResueltas).
        if ($item->colision_pausada_por && ! $force) {
            $item->worker_sid = null;
            $item->save();
            $this->warn("Item #{$item->id} PAUSADO por colisión en vuelo con #{$item->colision_pausada_por} (candado #438); "
                . "NO se integra ahora. Su rama {$branch} queda intacta; se reanuda automáticamente cuando el otro termine.");
            return self::SUCCESS;
        }

        // El merge YA NO lo hace este comando: el ejecutor puede correr en un worktree que NO puede
        // checar `main` (#334), y www-data no puede escribir `.git`. Se ENCOLA y lo aplica el runner
        // on-box (meganet) en el checkout principal, serializado y con verificación de regresión.

        // Nivel C nunca se auto-integra (solo Irving con el botón / --force).
        // #507 — Si la rama TRAE CONTENIDO (trabajo terminado), el item pasa a un estado TERMINAL de
        // despacho: `esperando_merge_irving` (fuera del pool, fuera de la bandeja) en vez de volver a
        // `requiere_irving`, que lo devolvía al churn: bandeja → Irving re-aprueba → worker lo toma →
        // no puede mergear → bandeja otra vez (el #117 dio 13 vueltas así). Si la rama está VACÍA
        // (el worker se rehusó a ejecutar) no hay nada que mergear → sí es decisión de Irving.
        if ($item->nivel_riesgo === 'C' && ! $force) {
            if ($this->ramaTieneContenido($branch)) {
                $this->parquearEsperandoMerge($item, "nivel C con trabajo terminado en {$branch}");
                $this->warn("Item #{$item->id} (nivel C) terminado → ESPERANDO TU MERGE. Sale del pool; ya no se re-despacha ni vuelve a la bandeja.");
            } else {
                $item->estado_aprobacion = 'requiere_irving';
                $item->save();
                $this->warn("Item #{$item->id} (nivel C) tiene rama SIN contenido (nada que mergear) → requiere_irving.");
            }
            return self::SUCCESS;
        }

        // Toggle OFF (manual): el ejecutor NO encola; la rama espera el botón de Irving en la Torre.
        // #507 — mismo trato que el C: con contenido, se parquea fuera del pool.
        if (! $force && ! $svc->autoMergeOn()) {
            if ($this->ramaTieneContenido($branch)) {
                $this->parquearEsperandoMerge($item, "auto-merge OFF, rama {$branch} terminada");
                $this->warn("Auto-merge OFF: rama {$branch} terminada → ESPERANDO TU MERGE (fuera del pool).");
            } else {
                $this->warn("Auto-merge OFF: la rama {$branch} no tiene contenido; no se parquea. Espera revisión de Irving.");
            }
            return self::SUCCESS;
        }

        // Auto-merge ON (o --force de Irving): encola. El runner hace el merge real.
        $svc->enqueueMerge($item->id, $force ? 'boton' : 'ejecutor', $force ? 'boton' : 'auto');
        $this->info("Merge de la rama {$branch} ENCOLADO (lo aplica el runner on-box en el principal). Item #{$item->id}.");
        return self::SUCCESS;
    }

    /**
     * #507 — Parquea el item en el estado terminal de despacho "esperando el merge de Irving":
     * conserva la autorización (`aprobado_irving`, no es una decisión pendiente) y lo saca del pool.
     * Solo lo revive el merge (MergeRunner → `completado` con merge_commit) o Irving a mano.
     */
    private function parquearEsperandoMerge(RoadmapItem $item, string $motivo): void
    {
        $item->estado_aprobacion       = 'aprobado_irving';
        $item->esperando_merge_irving  = true;
        $item->decision_resuelta       = true;
        $item->excluir_pool_automatico = true;
        $item->worker_sid              = null;
        $item->motivo_bloqueo          = "Esperando merge de Irving ({$motivo}).";
        $item->save();
    }

    /**
     * #507 — ¿la rama aporta CONTENIDO real sobre main (≥1 commit propio)? Los refs viven en el
     * object-store compartido, así que resuelve igual desde cualquier worktree. Si git falla (rama
     * perdida, etc.) devuelve false: conservador, no parquea como "listo para merge" algo dudoso.
     */
    private function ramaTieneContenido(string $branch): bool
    {
        $p = $this->git(['rev-list', '--count', 'main..' . $branch]);

        return $p->isSuccessful() && (int) trim($p->getOutput()) > 0;
    }

    private function git(array $args): Process
    {
        $p = new Process(array_merge(['git'], $args), base_path());
        $p->run();
        return $p;
    }
}
