<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RevisorService;
use Illuminate\Console\Command;

/**
 * BRIEFS de decisión C (#338 escalonado): para items nivel C SIN brief todavía, Opus arma un brief
 * (qué se decide / opciones / riesgos / recomendación) en `comentarios_claude` para que IRVING decida.
 * NO auto-autoriza (los deja en requiere_irving). Tanda ACOTADA (Opus cuesta). Solo dev.
 */
class BriefCCommand extends Command
{
    protected $signature = 'circuito:brief-c {--limit=3 : máximo de items C a briefear en esta pasada}';

    protected $description = 'Opus prepara briefs de decisión para items C sin brief (para que Irving decida).';

    public function handle(RevisorService $revisor): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $items = RoadmapItem::query()
            ->where('nivel_riesgo', 'C')
            ->whereIn('estado_aprobacion', ['pendiente_revision', 'aprobado_irving', 'requiere_irving'])
            ->whereNotIn('status', ['done', 'cancelado'])
            ->where(function ($q) {
                $q->whereNull('comentarios_claude')
                    ->orWhere('comentarios_claude', 'not like', '%BRIEF DE DECISIÓN%');
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($items->isEmpty()) {
            $this->info('No hay items C sin brief.');

            return self::SUCCESS;
        }

        foreach ($items as $item) {
            $r = $revisor->briefarC($item);
            $this->line("#{$item->id} brief C (" . ($r['modelo'] ?? '?') . '): ' . mb_strimwidth(str_replace("\n", ' ', (string) ($r['brief'] ?? '')), 0, 90, '…'));
        }
        $this->info('Briefs C generados: ' . $items->count() . ' (en requiere_irving, para tu bandeja).');

        return self::SUCCESS;
    }
}
