<?php

namespace App\Console\Commands\Scripts;

use App\Models\User;
use App\Services\Security\PasswordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill masivo de contraseñas: convierte el esquema legacy
 * `base64_encode(plano)` de `users.password` a bcrypt real.
 *
 * Es seguro y re-ejecutable:
 *  - Sólo toca filas cuyo password NO es ya bcrypt.
 *  - Recupera el plano vía base64_decode (el legacy es reversible) y lo
 *    re-hashea con PasswordService::make().
 *  - Usa DB::update por id (no dispara observers ni model events → no
 *    re-propaga base64 desde client_main_information).
 *
 * Pre-requisito: la verificación ya debe aceptar ambos formatos
 * (App\Services\Security\PasswordService), cosa que LoginController y el
 * login de MegaFamilia ya hacen. Por eso correr esto NO tumba el login.
 *
 * Uso:
 *   php artisan auth:rehash-passwords --dry-run
 *   php artisan auth:rehash-passwords
 */
class RehashPasswordsCommand extends Command
{
    protected $signature = 'auth:rehash-passwords
                            {--dry-run : Sólo reporta, no escribe en la BD}
                            {--chunk=200 : Tamaño de lote}';

    protected $description = 'Migra users.password de base64 legacy a bcrypt (idempotente)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk  = max(1, (int) $this->option('chunk'));

        $total = User::query()
            ->whereNotNull('password')
            ->where('password', '!=', '')
            ->count();

        if ($total === 0) {
            $this->info('No hay usuarios con contraseña que procesar.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Procesando {$total} usuarios...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $migrated = 0;   // legacy base64 → bcrypt
        $already  = 0;   // ya estaban en bcrypt
        $skipped  = 0;   // password no decodificable / formato raro

        User::query()
            ->whereNotNull('password')
            ->where('password', '!=', '')
            ->select(['id', 'password'])
            ->chunkById($chunk, function ($users) use (&$migrated, &$already, &$skipped, $dryRun, $bar) {
                foreach ($users as $user) {
                    $bar->advance();

                    if (PasswordService::isHashed($user->password)) {
                        $already++;
                        continue;
                    }

                    $plain = base64_decode($user->password, true);
                    // Validamos que sea base64 legítimo y round-trip exacto.
                    if ($plain === false || base64_encode($plain) !== $user->password) {
                        $skipped++;
                        $this->newLine();
                        $this->warn("  Saltado user id={$user->id}: password no es base64 válido.");
                        continue;
                    }

                    if (! $dryRun) {
                        DB::table('users')
                            ->where('id', $user->id)
                            ->update(['password' => PasswordService::make($plain)]);
                    }
                    $migrated++;
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Migrados (base64→bcrypt)', 'Ya en bcrypt', 'Saltados'],
            [[$migrated, $already, $skipped]]
        );

        if ($dryRun) {
            $this->warn('DRY-RUN: no se escribió nada. Quita --dry-run para aplicar.');
        } else {
            $this->info('Migración completada.');
        }

        return self::SUCCESS;
    }
}
