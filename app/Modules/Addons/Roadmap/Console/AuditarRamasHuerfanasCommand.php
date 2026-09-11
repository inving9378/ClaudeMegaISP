<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * #9990843 — auditoría 100% READ-ONLY: agrupa todas las ramas locales `circuito/item-<id>-*` por
 * id de item y reporta los que tienen MÁS DE UNA. Es la detección retroactiva del bug real que
 * dejó huérfana la rama de trabajo de #9990718 (22 commits, provisionador VoIP probado) mientras
 * el merge-runner integraba una rama hermana de 1 commit (doc-only) que había ganado el registro
 * `item->branch` en una visita posterior.
 *
 * `circuito:rama` (mismo item #9990843) ya previene la causa raíz hacia adelante: busca la rama
 * del item por ID, no por el slug recién calculado del título, así que un renombre del título ya
 * no puede volver a bifurcar. Este comando es el complemento retroactivo: no toca git ni el
 * modelo, solo lista — para que un humano (o Jarvis) revise si alguna de las ramas huérfanas ya
 * detectadas trae trabajo real que nunca llegó a `main`.
 *
 *   php artisan circuito:auditar-ramas-huerfanas
 */
class AuditarRamasHuerfanasCommand extends Command
{
    protected $signature = 'circuito:auditar-ramas-huerfanas';

    protected $description = '#9990843 — audita, solo lectura, qué items tienen más de una rama circuito/item-<id>-* (riesgo de rama huérfana con trabajo real sin mergear).';

    public function handle(): int
    {
        $p = new Process(['git', 'for-each-ref', '--format=%(refname:short)', 'refs/heads/circuito/item-*']);
        $p->setTimeout(30);
        $p->run();

        $ramas = array_values(array_filter(array_map('trim', explode("\n", trim($p->getOutput())))));

        $porItem = [];
        foreach ($ramas as $rama) {
            if (! preg_match('/^circuito\/item-(\d+)-/', $rama, $m)) {
                continue;
            }
            $porItem[(int) $m[1]][] = $rama;
        }

        $conflictivos = array_filter($porItem, fn ($lista) => count($lista) > 1);

        $this->line('');
        $this->line('<options=bold>AUDITORÍA — ramas circuito/item-<id>-* duplicadas (#9990843)</>');
        $this->line('  ramas circuito/item-* totales: ' . count($ramas) . '  ·  items con >1 rama: ' . count($conflictivos));

        if ($conflictivos === []) {
            $this->info('Ningún item tiene más de una rama circuito/item-<id>-* — sin ramas huérfanas detectables por este criterio.');

            return self::SUCCESS;
        }

        foreach ($conflictivos as $id => $lista) {
            $this->line('');
            $this->line("<options=bold;fg=yellow>Item #{$id}</> — " . count($lista) . ' ramas:');
            $item = RoadmapItem::find($id);
            foreach ($lista as $rama) {
                $count = new Process(['git', 'rev-list', '--count', "main..{$rama}"]);
                $count->run();
                $n = $count->isSuccessful() ? trim($count->getOutput()) : '?';
                $registrada = ($item && $item->branch === $rama) ? ' [registrada en item->branch]' : '';
                // "ya en main" = el CONTENIDO de la rama ya está incluido en main (0 commits únicos,
                // o mergeado por otra vía) — no depende de que ESTE item tenga su propio merge_commit.
                $mergeada = $this->esAncestroDeMain($rama) ? ' [ya en main]' : '';
                $this->line("    - {$rama} ({$n} commits vs main){$registrada}{$mergeada}");
            }
        }

        $this->line('');
        $this->warn('Ninguna acción automática se toma aquí — revisar caso por caso si la rama NO marcada '
            . '[ya en main] trae trabajo real que se perdió.');

        return self::SUCCESS;
    }

    private function esAncestroDeMain(string $branch): bool
    {
        $p = new Process(['git', 'merge-base', '--is-ancestor', $branch, 'main']);
        $p->run();

        return $p->isSuccessful();
    }
}
