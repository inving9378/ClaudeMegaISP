<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * FASE 2A.5 — CANDADO DE COHERENCIA, lado runtime: ¿el scope de elegibilidad y el candado atómico
 * del reclamo seleccionan HOY el mismo conjunto de items?
 *
 * `PoolGuardCoherenceTest` compara el SQL de los dos caminos sin tocar la BD; esto compara los dos
 * CONJUNTOS sobre los items REALES, que es donde se vería una divergencia que el SQL no delata
 * (p.ej. un valor raro en una bandera, o una columna que cambió de tipo). Es READ-ONLY: sólo hace
 * SELECT, jamás escribe.
 *
 * Contrato = exit code (igual que `circuito:consultar`): 0 coinciden, 1 divergen. Así se puede
 * colgar del scheduler o de un cron sin leer la salida.
 */
class CoherenciaPoolCommand extends Command
{
    protected $signature = 'circuito:coherencia-pool {--todos : incluye también los archivados (por default se ignoran)}';

    protected $description = 'READ-ONLY: verifica que el scope de elegibilidad y el candado atómico del reclamo seleccionen el MISMO conjunto de items.';

    public function handle(): int
    {
        $archivados = (bool) $this->option('todos');

        // Camino 1 — el que filtra el SELECT del scheduler (`ejecutablesParalelo`).
        $scope = RoadmapItem::query()->elegibleParaPool();
        // Camino 2 — el que re-verifica el UPDATE de `claimNextParalelo` entre el SELECT y la escritura.
        $reclamo = DB::table('roadmap_items')->where(fn ($q) => RoadmapCircuitoService::guardReclamoAtomico($q));

        if (! $archivados) {
            $scope->whereNull('archivado_at');
            $reclamo->whereNull('archivado_at');
        }

        $idsScope   = $scope->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all();
        $idsReclamo = $reclamo->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all();

        $total = RoadmapItem::query()->when(! $archivados, fn ($q) => $q->whereNull('archivado_at'))->count();

        $soloScope   = array_values(array_diff($idsScope, $idsReclamo));
        $soloReclamo = array_values(array_diff($idsReclamo, $idsScope));

        $this->line("Muestra: {$total} items" . ($archivados ? ' (incluye archivados)' : ' vivos'));
        $this->line('  scope  elegibleParaPool ....... ' . count($idsScope) . ' items');
        $this->line('  candado atómico del reclamo ... ' . count($idsReclamo) . ' items');

        if (! $soloScope && ! $soloReclamo) {
            $this->info('✔ Coherentes: los dos caminos seleccionan EXACTAMENTE el mismo conjunto.');

            // Diagnóstico de por qué el resto queda fuera (útil cuando la flota está parada con cola).
            $fuera = $total - count($idsScope);
            if ($fuera > 0) {
                $this->line('');
                $this->line("Fuera del pool: {$fuera} items");
                $this->line('  · freno humano (columna o rótulo) ... ' . RoadmapItem::query()
                    ->when(! $archivados, fn ($q) => $q->whereNull('archivado_at'))
                    ->where(fn ($q) => RoadmapItem::sqlConFrenoHumano($q))->count());
                $this->line('  · esperando merge de Irving ......... ' . RoadmapItem::query()
                    ->when(! $archivados, fn ($q) => $q->whereNull('archivado_at'))
                    ->where('esperando_merge_irving', true)->count());
                $this->line('  · excluidos del pool (master switch)  ' . RoadmapItem::query()
                    ->when(! $archivados, fn ($q) => $q->whereNull('archivado_at'))
                    ->where('excluir_pool_automatico', true)->count());
                $this->line('  · dependen del fallback del rótulo .. ' . RoadmapItem::contarFallbackRotulo()
                    . ' (cuando marque 0 una semana, el LIKE sobre `title` se puede retirar)');
            }

            return self::SUCCESS;
        }

        $this->error('✘ DIVERGEN. El scope y el candado atómico ya no seleccionan el mismo conjunto:');
        if ($soloScope) {
            $this->line('  Sólo el SCOPE los da por elegibles (el reclamo los rechazaría → el worker');
            $this->line('  pediría trabajo, se lo darían, y el UPDATE devolvería 0 filas): '
                . implode(', ', array_slice($soloScope, 0, 30)) . (count($soloScope) > 30 ? '…' : ''));
        }
        if ($soloReclamo) {
            $this->line('  ⚠ Sólo el RECLAMO los acepta (el caso CARO: el candado dejaría pasar algo');
            $this->line('  que el guard ya excluía → una terminal trabajando sobre algo que no debía): '
                . implode(', ', array_slice($soloReclamo, 0, 30)) . (count($soloReclamo) > 30 ? '…' : ''));
        }
        $this->line('');
        $this->line('Los dos deben delegar en RoadmapItem::sqlElegibleParaPool(). Ver también');
        $this->line('tests/Unit/Modules/Addons/Roadmap/PoolGuardCoherenceTest.php');

        return self::FAILURE;
    }
}
