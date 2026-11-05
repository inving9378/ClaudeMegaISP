<?php

namespace App\Console\Commands\Active;

use App\Models\PingStatistic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Retención de ping_statistics (#996, sub-pendiente de la épica #988): conserva el crudo
 * 90 días y lo demás se borra. daily_ping_statistics (resumen diario, se actualiza en vivo
 * en SyncPingMonitoring) NO se toca nunca. Borra por lotes para no bloquear la tabla.
 * Sin --force solo simula.
 */
class PurgePingStatistics extends Command
{
    protected $signature = 'ping:purge-raw
                            {--days=90 : Días de crudo a conservar}
                            {--force : Ejecutar el borrado real (sin esto, solo simula)}';

    protected $description = 'Borra ping_statistics (crudo) con más de N días; el resumen diario se conserva';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $query = PingStatistic::where('recorded_at', '<', $cutoff);
        $total = (clone $query)->count();

        if (! $this->option('force')) {
            $this->info("[dry-run] {$total} filas de ping_statistics anteriores a {$cutoff} se borrarían. Usa --force.");
            return self::SUCCESS;
        }

        $deleted = 0;
        do {
            $n = PingStatistic::where('recorded_at', '<', $cutoff)->limit(5000)->delete();
            $deleted += $n;
        } while ($n > 0);

        Log::info("ping:purge-raw: {$deleted} filas borradas (anteriores a {$cutoff}).");
        $this->info("{$deleted} filas borradas.");

        return self::SUCCESS;
    }
}
