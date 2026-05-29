<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildReferralClosuresCommand extends Command
{
    protected $signature   = 'embajadores:rebuild-closures';
    protected $description = 'Reconstruye referral_closures desde chain_path de la tabla referrals';

    public function handle(): int
    {
        $total = DB::table('referrals')->count();
        $this->info("Reconstruyendo closure table para {$total} referrals...");

        $start = microtime(true);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('referral_closures')->truncate();

        $bar  = $this->output->createProgressBar($total);
        $rows = [];

        DB::table('referrals')->orderBy('id')->chunk(500, function ($referrals) use (&$rows, $bar) {
            foreach ($referrals as $r) {
                $clientId  = (int) $r->referred_client_id;
                $ancestors = array_values(array_filter(explode('/', $r->chain_path)));

                // Self-reference (depth=0)
                $rows[] = [
                    'ancestor_id'   => $clientId,
                    'descendant_id' => $clientId,
                    'depth'         => 0,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];

                // Ancestros en orden: [root, ..., padre_directo]
                // chain_path="/root/mid/padre/" → ancestors=[root, mid, padre]
                // padre está en posición -1 → depth=1, mid → depth=2, root → depth=n
                $reversed = array_reverse($ancestors);
                foreach ($reversed as $depth => $ancestorId) {
                    $rows[] = [
                        'ancestor_id'   => (int) $ancestorId,
                        'descendant_id' => $clientId,
                        'depth'         => $depth + 1,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];
                }

                $bar->advance();
            }

            if (count($rows) >= 2000) {
                DB::table('referral_closures')->insertOrIgnore($rows);
                $rows = [];
            }
        });

        if (!empty($rows)) {
            DB::table('referral_closures')->insertOrIgnore($rows);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $bar->finish();

        $closureCount = DB::table('referral_closures')->count();
        $elapsed      = round(microtime(true) - $start, 2);

        $this->info('');
        $this->info("✓ {$closureCount} filas en referral_closures ({$elapsed}s)");

        return 0;
    }
}
