<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildReferralKpisCommand extends Command
{
    protected $signature   = 'embajadores:rebuild-kpis {--dry-run : Solo muestra diferencias sin actualizar}';
    protected $description = 'Recalcula total_commissions_earned, total_rewards_earned y total_referrals en client_referral_profiles desde los datos reales';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info($dryRun ? 'Modo dry-run — sin cambios en BD' : 'Reconstruyendo KPIs...');

        $start = microtime(true);

        [$updatedComisiones, $discComisiones] = $this->rebuildCommissions($dryRun);
        [$updatedRewards, $discRewards]       = $this->rebuildRewards($dryRun);
        [$updatedReferrals, $discReferrals]   = $this->rebuildReferrals($dryRun);

        $elapsed = round(microtime(true) - $start, 2);

        $this->info('');
        $this->line('═══════════════════════════════════════');
        $this->info('  KPIs rebuild — resumen');
        $this->line('═══════════════════════════════════════');
        $this->line("  total_commissions_earned: {$discComisiones} perfiles con diff" . ($dryRun ? '' : " → {$updatedComisiones} actualizados"));
        $this->line("  total_rewards_earned:     {$discRewards} perfiles con diff"    . ($dryRun ? '' : " → {$updatedRewards} actualizados"));
        $this->line("  total_referrals:          {$discReferrals} perfiles con diff"  . ($dryRun ? '' : " → {$updatedReferrals} actualizados"));
        $this->line("  Tiempo: {$elapsed}s");

        if ($dryRun && ($discComisiones + $discRewards + $discReferrals) > 0) {
            $this->warn('  → Hay desincronización. Correr sin --dry-run para reparar.');
        }

        return 0;
    }

    private function rebuildCommissions(bool $dryRun): array
    {
        // Suma de comisiones no canceladas por beneficiario
        $realSums = DB::table('referral_commissions')
            ->selectRaw('beneficiary_id, SUM(commission_amount) as real_sum')
            ->where('status', '!=', 'cancelled')
            ->groupBy('beneficiary_id')
            ->pluck('real_sum', 'beneficiary_id');

        $profiles = DB::table('client_referral_profiles')
            ->whereNotNull('plan_type')
            ->get(['id', 'client_id', 'total_commissions_earned']);

        $updated = 0;
        $discrepancies = 0;

        foreach ($profiles as $p) {
            $real = round((float) ($realSums[$p->client_id] ?? 0), 2);
            $cached = round((float) $p->total_commissions_earned, 2);

            if (abs($real - $cached) > 0.01) {
                $discrepancies++;
                if (!$dryRun) {
                    DB::table('client_referral_profiles')
                        ->where('id', $p->id)
                        ->update(['total_commissions_earned' => $real]);
                    $updated++;
                }
            }
        }

        return [$updated, $discrepancies];
    }

    private function rebuildRewards(bool $dryRun): array
    {
        $realCounts = DB::table('referral_rewards')
            ->selectRaw('embajador_id, COUNT(*) as cnt')
            ->whereIn('status', ['available', 'applied'])
            ->groupBy('embajador_id')
            ->pluck('cnt', 'embajador_id');

        $profiles = DB::table('client_referral_profiles')
            ->whereNotNull('plan_type')
            ->get(['id', 'client_id', 'total_rewards_earned']);

        $updated = 0;
        $discrepancies = 0;

        foreach ($profiles as $p) {
            $real   = (int) ($realCounts[$p->client_id] ?? 0);
            $cached = (int) $p->total_rewards_earned;

            if ($real !== $cached) {
                $discrepancies++;
                if (!$dryRun) {
                    DB::table('client_referral_profiles')
                        ->where('id', $p->id)
                        ->update(['total_rewards_earned' => $real]);
                    $updated++;
                }
            }
        }

        return [$updated, $discrepancies];
    }

    private function rebuildReferrals(bool $dryRun): array
    {
        $realCounts = DB::table('referrals')
            ->selectRaw('embajador_id, COUNT(*) as cnt')
            ->whereIn('status', ['pending_threshold', 'active', 'completed'])
            ->groupBy('embajador_id')
            ->pluck('cnt', 'embajador_id');

        $profiles = DB::table('client_referral_profiles')
            ->whereNotNull('plan_type')
            ->get(['id', 'client_id', 'total_referrals']);

        $updated = 0;
        $discrepancies = 0;

        foreach ($profiles as $p) {
            $real   = (int) ($realCounts[$p->client_id] ?? 0);
            $cached = (int) $p->total_referrals;

            if ($real !== $cached) {
                $discrepancies++;
                if (!$dryRun) {
                    DB::table('client_referral_profiles')
                        ->where('id', $p->id)
                        ->update(['total_referrals' => $real]);
                    $updated++;
                }
            }
        }

        return [$updated, $discrepancies];
    }
}
