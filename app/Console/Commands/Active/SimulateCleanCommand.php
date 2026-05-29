<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimulateCleanCommand extends Command
{
    protected $signature   = 'embajadores:simulate-clean {--confirm : Omitir prompt interactivo}';
    protected $description = 'Elimina TODOS los registros marcados como is_test_data=1 en cascada';

    public function handle(): int
    {
        // Contar antes
        $count = DB::table('clients')->where('is_test_data', 1)->count();

        if ($count === 0) {
            $this->info('No hay datos de simulación que limpiar.');
            return 0;
        }

        if (!$this->option('confirm')) {
            $this->warn('');
            $this->warn("  Se eliminarán {$count} clientes de prueba y todos sus registros relacionados:");
            $this->warn('  referrals, commissions, rewards, profiles, prospects, follow-ups, share-logs, notif-logs');
            $this->warn('');
            $response = $this->ask('  Escriba "LIMPIAR SIMULACION" para confirmar');

            if (trim($response) !== 'LIMPIAR SIMULACION') {
                $this->error('Texto incorrecto. Operación cancelada.');
                return 1;
            }
        }

        $start = microtime(true);
        $this->info("Limpiando {$count} clientes de prueba...");

        // Las FK con onDelete('cascade') limpian automáticamente:
        // client_referral_profiles → referrals → referral_commissions
        // referrals → referral_rewards
        // referral_prospects (embajador_id FK cascade)
        // referral_notification_logs (client_id FK cascade)
        // referral_share_logs (embajador_id FK cascade)

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $deleted = DB::table('clients')->where('is_test_data', 1)->delete();

        // Orphan cleanup (tablas sin FK directa a clients)
        $this->cleanOrphans();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $elapsed = round(microtime(true) - $start, 1);
        $this->info("✓ {$deleted} clientes de prueba eliminados en {$elapsed}s.");
        return 0;
    }

    private function cleanOrphans(): void
    {
        // Limpiar referrals huérfanos (embajador_id que ya no existen)
        DB::statement('
            DELETE r FROM referrals r
            LEFT JOIN clients c ON c.id = r.embajador_id
            WHERE c.id IS NULL
        ');

        // Limpiar comisiones huérfanas
        DB::statement('
            DELETE rc FROM referral_commissions rc
            LEFT JOIN referrals r ON r.id = rc.referral_id
            WHERE r.id IS NULL
        ');

        // Limpiar rewards huérfanas
        DB::statement('
            DELETE rw FROM referral_rewards rw
            LEFT JOIN referrals r ON r.id = rw.referral_id
            WHERE r.id IS NULL
        ');

        // Limpiar profiles huérfanos
        DB::statement('
            DELETE p FROM client_referral_profiles p
            LEFT JOIN clients c ON c.id = p.client_id
            WHERE c.id IS NULL
        ');
    }
}
