<?php

namespace App\Console\Commands\Active;

use App\Models\Referrals\ClientReferralProfile;
use App\Services\Referrals\ReferralStandingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Respaldo de auto-sanación: recalcula los contadores denormalizados de TODOS los
 * perfiles de embajador desde los datos reales, usando la MISMA fuente de verdad que
 * los observers (ReferralStandingService). Pensado para correr nightly y para backfill
 * de datos sembrados por vías que no disparan observers (p.ej. inserts raw del
 * SimulateEmbajadoresCommand).
 *
 * NO toca threshold_amount_paid (lo mantiene AccumulateClientThreshold).
 */
class RebuildReferralKpisCommand extends Command
{
    protected $signature   = 'embajadores:rebuild-kpis {--dry-run : Solo muestra diferencias sin actualizar}';
    protected $description  = 'Recalcula total_referrals, total_commissions_earned y total_rewards_earned en client_referral_profiles vía ReferralStandingService (fuente de verdad única)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $this->info($dryRun ? 'Modo dry-run — sin cambios en BD' : 'Reconstruyendo KPIs vía ReferralStandingService...');

        $start    = microtime(true);
        $profiles = ClientReferralProfile::query()
            ->get(['id', 'total_referrals', 'total_commissions_earned', 'total_rewards_earned']);

        $checked = 0;
        $changed = 0;

        if ($dryRun) {
            DB::beginTransaction();
        }

        foreach ($profiles as $p) {
            $before = [
                'total_referrals'          => (int) $p->total_referrals,
                'total_commissions_earned' => round((float) $p->total_commissions_earned, 2),
                'total_rewards_earned'     => (int) $p->total_rewards_earned,
            ];

            $after = ReferralStandingService::computeCountersForProfile((int) $p->id);
            $checked++;

            $afterNorm = [
                'total_referrals'          => (int) $after['total_referrals'],
                'total_commissions_earned' => round((float) $after['total_commissions_earned'], 2),
                'total_rewards_earned'     => (int) $after['total_rewards_earned'],
            ];

            if ($before !== $afterNorm) {
                $changed++;
            }
        }

        if ($dryRun) {
            DB::rollBack();
        }

        $elapsed = round(microtime(true) - $start, 2);

        $this->line('═══════════════════════════════════════');
        $this->info('  KPIs rebuild — resumen');
        $this->line('═══════════════════════════════════════');
        $this->line("  Perfiles revisados: {$checked}");
        $this->line("  Perfiles con diff:  {$changed}" . ($dryRun ? ' (dry-run, no aplicados)' : ' → actualizados'));
        $this->line("  Tiempo: {$elapsed}s");

        if ($dryRun && $changed > 0) {
            $this->warn('  → Hay desincronización. Correr sin --dry-run para reparar.');
        }

        return 0;
    }
}
