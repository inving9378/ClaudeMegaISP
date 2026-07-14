<?php

namespace App\Console\Commands\Scripts;

use App\Models\User;
use App\Services\Security\PasswordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Backfill masivo: crea la fila `users` "espejo" (rol client) para clientes de
 * `client_main_information` que nunca la tuvieron.
 *
 * Replica EXACTAMENTE el mismo camino que ya crea estos usuarios hoy en alta
 * normal (App\Observers\ClientMainInformationObserver::createNewUserRoleClient)
 * — mismos campos, mismo PasswordService::make() a bcrypt, mismo rol `client`.
 * No es una ruta nueva de creación de usuarios, solo pone al día filas que
 * quedaron huérfanas (CMI creada antes de que existiera el Observer, o creada
 * sin password en su momento y luego actualizada).
 *
 * El bcrypt resultante en `users.password` NO se usa para el login del portal
 * (ese valida contra `client_main_information.password` en texto plano) ni
 * habilita acceso al panel admin (el rol `client` no está en STAFF_ROLES de
 * LoginController) — es solo el registro espejo que ya usan MegaFamilia,
 * scheduling y otros módulos internos.
 *
 * Idempotente y re-ejecutable:
 *  - Sólo procesa client_id de CMI que aún NO tienen fila en `users`.
 *  - Sólo con `client_main_information.password` no vacío (sin eso el
 *    Observer tampoco crearía el usuario hoy).
 *  - Salta (sin abortar el lote) cualquier fila que choque con un
 *    `login_user` ya existente (unique) u otro error de escritura.
 *
 * Uso:
 *   php artisan users:backfill-orphan-clients --dry-run
 *   php artisan users:backfill-orphan-clients
 */
class BackfillOrphanClientUsersCommand extends Command
{
    protected $signature = 'users:backfill-orphan-clients
                            {--dry-run : Sólo reporta, no escribe en la BD}
                            {--chunk=200 : Tamaño de lote}';

    protected $description = 'Crea la fila users (rol client) para client_main_information huérfanos (idempotente)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk  = max(1, (int) $this->option('chunk'));

        $role = Role::where('name', 'client')->first();
        if (! $role) {
            $this->error('No existe el rol "client". Abortando.');
            return self::FAILURE;
        }

        $existingClientIds = User::query()
            ->whereNotNull('client_id')
            ->pluck('client_id')
            ->all();

        $query = DB::table('client_main_information')
            ->whereNotIn('client_id', $existingClientIds)
            ->whereNotNull('password')
            ->where('password', '!=', '');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No hay client_main_information huérfanos que procesar.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Procesando {$total} clientes sin fila users...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $created = 0;
        $skipped = 0;

        $query->orderBy('id')
            ->chunk($chunk, function ($rows) use (&$created, &$skipped, $dryRun, $bar, $role) {
                foreach ($rows as $cmi) {
                    $bar->advance();

                    if (empty($cmi->user)) {
                        $skipped++;
                        $this->newLine();
                        $this->warn("  Saltado client_id={$cmi->client_id}: sin login_user (user).");
                        continue;
                    }

                    if ($dryRun) {
                        $created++;
                        continue;
                    }

                    try {
                        DB::transaction(function () use ($cmi, $role) {
                            $user = new User();
                            $user->name = $cmi->name;
                            $user->email = $cmi->email;
                            $user->father_last_name = $cmi->father_last_name ?? null;
                            $user->mother_last_name = $cmi->mother_last_name ?? null;
                            $user->phone = $cmi->phone;
                            $user->location = $cmi->location ?? null;
                            $user->login_user = $cmi->user;
                            $user->password = PasswordService::make($cmi->password);
                            $user->client_id = $cmi->client_id;
                            $user->save();

                            $user->assignRole($role);
                        });
                        $created++;
                    } catch (\Throwable $e) {
                        $skipped++;
                        $this->newLine();
                        $this->warn("  Saltado client_id={$cmi->client_id}: {$e->getMessage()}");
                    }
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Creados', 'Saltados'],
            [[$created, $skipped]]
        );

        if ($dryRun) {
            $this->warn('DRY-RUN: no se escribió nada. Quita --dry-run para aplicar.');
        } else {
            $this->info('Backfill completado.');
        }

        return self::SUCCESS;
    }
}
