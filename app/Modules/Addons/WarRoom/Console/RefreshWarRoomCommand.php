<?php

namespace App\Modules\Addons\WarRoom\Console;

use App\Modules\Addons\WarRoom\Controllers\KpiController;
use App\Modules\Addons\WarRoom\Models\KpiSnapshot;
use App\Modules\Addons\WarRoom\Services\InsightsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshWarRoomCommand extends Command
{
    protected $signature = 'warroom:refresh
        {--period=    : Período YYYY-MM (default: mes actual)}
        {--view=all   : Vista a refrescar: all | resumen | finanzas | operaciones | ventas | red | marketing | talento}
        {--skip-insights : No regenerar insights (solo snapshot)}
        {--skip-snapshot : No escribir snapshot de KPIs (solo insights)}';

    protected $description = 'Refresca caché de insights y escribe snapshot de KPIs del War Room en background';

    private const ALL_VIEWS = ['resumen', 'finanzas', 'operaciones', 'ventas', 'red', 'marketing', 'talento'];

    public function handle(KpiController $kpiCtrl, InsightsService $insightsSvc): int
    {
        $period      = $this->option('period') ?: now()->format('Y-m');
        $viewOpt     = $this->option('view') ?: 'all';
        $views       = $viewOpt === 'all' ? self::ALL_VIEWS : [$viewOpt];
        $skipInsight = (bool) $this->option('skip-insights');
        $skipSnap    = (bool) $this->option('skip-snapshot');

        $this->info("[warroom:refresh] período={$period} vistas=" . implode(',', $views));

        // ── 1. Insights por vista ────────────────────────────────────────────────
        if (! $skipInsight) {
            foreach ($views as $view) {
                try {
                    $result = $insightsSvc->generate($view, $period);
                    $this->line("  ✓ insights [{$view}] source={$result->source}");
                } catch (\Throwable $e) {
                    $this->warn("  ✗ insights [{$view}]: {$e->getMessage()}");
                    Log::warning("warroom:refresh — insights fallaron [{$view}]", [
                        'period' => $period,
                        'err'    => $e->getMessage(),
                    ]);
                }
            }
        }

        // ── 2. Snapshot de KPIs (TODAS las vistas, una fila por período) ─────────
        if (! $skipSnap) {
            $allKpis = [];
            foreach (self::ALL_VIEWS as $view) {
                try {
                    $allKpis[$view] = $kpiCtrl->raw($view, $period);
                } catch (\Throwable $e) {
                    $this->warn("  ✗ kpis [{$view}]: {$e->getMessage()}");
                    Log::warning("warroom:refresh — kpis fallaron [{$view}]", [
                        'period' => $period,
                        'err'    => $e->getMessage(),
                    ]);
                }
            }

            KpiSnapshot::updateOrCreate(
                ['period'      => $period],
                ['kpis'        => $allKpis,
                 'snapshot_at' => now()]
            );

            $this->line("  ✓ snapshot [{$period}] " . count($allKpis) . " vistas");
        }

        $this->info("[warroom:refresh] completado.");
        return self::SUCCESS;
    }
}
