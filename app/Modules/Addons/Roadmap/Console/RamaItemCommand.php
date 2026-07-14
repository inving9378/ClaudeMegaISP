<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Crea la rama dedicada de un item del Circuito (circuito/item-<id>-<slug>) desde main
 * y la registra en el item (campo branch). El ejecutor trabaja el cambio ahí, aislado
 * de la rama desplegada. NUNCA hace push ni toca prod.
 */
class RamaItemCommand extends Command
{
    protected $signature = 'circuito:rama {id : ID del item} {--sid= : SID del worker que corre la vuelta (distingue su propio claim de otra terminal)}';

    protected $description = 'Crea/checkout la rama del item (circuito/item-<id>-<slug>) desde main y la registra en el item.';

    public function handle(): int
    {
        $item = RoadmapItem::find($this->argument('id'));
        if (! $item) {
            $this->error('Item no encontrado.');
            return self::FAILURE;
        }

        // #341 reconciliado (#432): la rama se crea DESPUÉS de que el scheduler marcó el item
        // `en_progreso` y le selló su `worker_sid` (orden claim→rama). Por eso `en_progreso` por sí
        // solo NO es colisión — es el flujo normal del propio worker. Solo colisiona un humano o
        // OTRA terminal. El SID llega por --sid o por el env CIRCUITO_SID que exporta vuelta.sh.
        $sid = $this->option('sid') ?: (getenv('CIRCUITO_SID') ?: null);
        if ($motivo = $this->motivoColision($item, $sid)) {
            $this->error("El item #{$item->id} no se puede ramificar: {$motivo} (candado #341).");
            return self::FAILURE;
        }

        $slug   = Str::slug(Str::limit($item->title, 40, ''));
        $branch = "circuito/item-{$item->id}-{$slug}";

        // ¿ya existe la rama? (resume de una vuelta previa: timeout, o #438 pausada por colisión
        // y ya reanudada). La rebaso sobre `main` para que traiga lo que otros items ya integraron
        // desde que se creó — así el merge final no choca. Best-effort: si el rebase truena
        // (conflicto real), lo abandono y dejo la rama tal cual estaba; el backstop de
        // `MergeRunner::performMerge` (merge de 2 fases con verificación) sigue intacto.
        if ($this->git(['rev-parse', '--verify', $branch])->isSuccessful()) {
            $this->git(['checkout', $branch]);
            if (! $this->git(['merge-base', '--is-ancestor', 'main', $branch])->isSuccessful()) {
                $rebase = $this->git(['rebase', 'main']);
                if ($rebase->isSuccessful()) {
                    $this->info("Rama {$branch} rebasada sobre main (#438).");
                } else {
                    $this->git(['rebase', '--abort']);
                    $this->warn("No se pudo rebasar {$branch} sobre main (posible conflicto); sigue sobre su base anterior.");
                }
            }
            $this->registrar($item, $branch);
            $this->info("Rama {$branch} ya existía; checkout hecho. Registrada en el item #{$item->id}.");
            return self::SUCCESS;
        }

        if (trim($this->git(['status', '--porcelain', '--untracked-files=no'])->getOutput()) !== '') {
            $this->error('El árbol de trabajo tiene cambios sin commitear; commitea o descarta antes de ramificar.');
            return self::FAILURE;
        }

        // Rama desde el ref `main` SIN checar main. En el modelo de worktree aislado (#334
        // Fase 0) `main` vive checado en el checkout principal (/var/www/megaisp) y git PROHÍBE
        // checarlo en dos worktrees a la vez. `checkout -b X main` crea la rama desde el tip de
        // main y la checa, sin tocar main → funciona tanto en el worktree del ejecutor como en
        // el checkout principal. (Antes: `checkout main` + `checkout -b X`, que rompía en worktree.)
        if (! $this->git(['checkout', '-b', $branch, 'main'])->isSuccessful()) {
            $this->error("No se pudo crear la rama {$branch} desde main.");
            return self::FAILURE;
        }

        $this->registrar($item, $branch);
        $this->info("Rama {$branch} creada desde main y registrada en el item #{$item->id}.");
        return self::SUCCESS;
    }

    /**
     * #341: ¿hay colisión REAL que impida ramificar? `en_progreso` por sí solo NO lo es (el propio
     * worker lo acaba de reclamar antes de correr la rama). Bloquea solo:
     *   - candado humano (en_desarrollo_humano), o
     *   - en_progreso pero con `worker_sid` de OTRA terminal (distinto al SID que corre la rama).
     * Devuelve el motivo del bloqueo, o null si se puede ramificar. Público para testeo.
     */
    public function motivoColision(RoadmapItem $item, ?string $sid): ?string
    {
        if ((bool) $item->en_desarrollo_humano) {
            return 'lo bloqueó un humano (en_desarrollo_humano)';
        }
        if ($item->estado_aprobacion === 'en_progreso'
            && $sid !== null && $item->worker_sid !== null && $item->worker_sid !== $sid) {
            return "lo trabaja otra terminal ({$item->worker_sid}, no {$sid})";
        }

        return null;
    }

    private function registrar(RoadmapItem $item, string $branch): void
    {
        $item->branch = $branch;
        $item->save();
    }

    private function git(array $args): Process
    {
        $p = new Process(array_merge(['git'], $args), base_path());
        $p->run();
        return $p;
    }
}
