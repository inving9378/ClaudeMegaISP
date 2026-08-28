<?php

namespace App\Console\Commands\Scripts;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rollback quirúrgico de un batch de `users:backfill-orphan-clients` (item #105,
 * decisión de Irving pregunta q3): borra EXACTAMENTE los `users` creados en ese
 * batch, usando el mapeo guardado en `orphan_client_backfill_log` — nunca toca
 * ningún otro registro.
 *
 * Uso:
 *   php artisan users:rollback-orphan-backfill {batch} --dry-run
 *   php artisan users:rollback-orphan-backfill {batch}
 */
class RollbackOrphanClientBackfillCommand extends Command
{
    protected $signature = 'users:rollback-orphan-backfill
                            {batch : uuid del batch a deshacer (lo imprime el comando de backfill al terminar)}
                            {--dry-run : Solo lista qué se borraría}';

    protected $description = 'Deshace un batch del backfill de users espejo huérfanos (item #105), quirúrgico por id';

    public function handle(): int
    {
        $batch  = (string) $this->argument('batch');
        $dryRun = (bool) $this->option('dry-run');

        $rows = DB::table('orphan_client_backfill_log')->where('batch', $batch)->get();

        if ($rows->isEmpty()) {
            $this->error("No hay filas registradas para el batch {$batch}.");
            return self::FAILURE;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Batch {$batch}: {$rows->count()} usuario(s) a borrar.");
        $this->table(
            ['user_id', 'client_id', 'login_user'],
            $rows->map(fn ($r) => [$r->user_id, $r->client_id, $r->login_user])->all()
        );

        if ($dryRun) {
            $this->warn('DRY-RUN: no se borró nada.');
            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($rows as $r) {
            $user = User::find($r->user_id);
            if (! $user) {
                continue;
            }
            // login_user es la llave de correspondencia real con la fila que registramos
            // (por si el id fue reciclado) — nunca borrar por id solo.
            if ($user->login_user !== $r->login_user) {
                $this->warn("  Saltado user_id={$r->user_id}: login_user actual no coincide con el registrado ({$user->login_user} != {$r->login_user}).");
                continue;
            }

            DB::transaction(function () use ($user) {
                $user->syncRoles([]);
                $user->delete();
            });
            $deleted++;
        }

        DB::table('orphan_client_backfill_log')->where('batch', $batch)->delete();

        $this->info("Rollback completado: {$deleted} usuario(s) borrados, log del batch limpiado.");

        return self::SUCCESS;
    }
}
