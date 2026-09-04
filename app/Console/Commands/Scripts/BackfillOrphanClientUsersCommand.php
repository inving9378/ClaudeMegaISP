<?php

namespace App\Console\Commands\Scripts;

use App\Models\User;
use App\Services\Security\PasswordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
 * Auditoría (item #105, decisión de Irving pregunta q3): cada corrida real
 * (sin --report) escribe en `orphan_client_backfill_log` el mapeo
 * client_id → user_id creado, agrupado por un `batch` (uuid) — así
 * `users:rollback-orphan-backfill {batch}` puede borrar EXACTAMENTE esos ids
 * sin tocar nada más.
 *
 * Uso:
 *   php artisan users:backfill-orphan-clients --report          # solo diagnóstico, no escribe nada
 *   php artisan users:backfill-orphan-clients --dry-run          # simula la corrida real (cuenta creados/saltados)
 *   php artisan users:backfill-orphan-clients --limit=10         # corrida real acotada (validación con muestra)
 *   php artisan users:backfill-orphan-clients                    # corrida real completa
 */
class BackfillOrphanClientUsersCommand extends Command
{
    protected $signature = 'users:backfill-orphan-clients
                            {--report : Solo diagnóstico detallado (colisiones, duplicados, muestra) — no escribe nada}
                            {--dry-run : Solo reporta, no escribe en la BD}
                            {--limit= : Procesa como máximo N candidatos (para validar con una muestra antes de correr todo)}
                            {--chunk=200 : Tamaño de lote}';

    protected $description = 'Crea la fila users (rol client) para client_main_information huérfanos (idempotente)';

    public function handle(): int
    {
        $report = (bool) $this->option('report');
        $dryRun = (bool) $this->option('dry-run') || $report;
        $chunk  = max(1, (int) $this->option('chunk'));
        $limit  = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        $role = Role::where('name', 'client')->first();
        if (! $role) {
            $this->error('No existe el rol "client". Abortando.');
            return self::FAILURE;
        }

        $existingClientIds = User::query()
            ->whereNotNull('client_id')
            ->pluck('client_id')
            ->all();

        $baseQuery = fn () => DB::table('client_main_information')
            ->whereNotIn('client_id', $existingClientIds);

        $procesables = (clone $baseQuery())
            ->whereNotNull('password')
            ->where('password', '!=', '');

        if ($report) {
            return $this->handleReport($baseQuery(), $procesables);
        }

        $total = $limit !== null ? min($limit, $procesables->count()) : $procesables->count();

        if ($total === 0) {
            $this->info('No hay client_main_information huérfanos que procesar.');
            return self::SUCCESS;
        }

        $batch = (string) Str::uuid();

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Procesando {$total} clientes sin fila users (batch={$batch})...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $created = 0;
        $skipped = 0;
        $processed = 0;

        $procesables->orderBy('id')
            ->chunk($chunk, function ($rows) use (&$created, &$skipped, &$processed, $dryRun, $bar, $role, $batch, $total) {
                foreach ($rows as $cmi) {
                    if ($processed >= $total) {
                        return false;
                    }
                    $processed++;
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
                        DB::transaction(function () use ($cmi, $role, $batch) {
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

                            DB::table('orphan_client_backfill_log')->insert([
                                'batch'      => $batch,
                                'client_id'  => $cmi->client_id,
                                'user_id'    => $user->id,
                                'login_user' => $user->login_user,
                                'created_at' => now(),
                            ]);
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
            $this->info("Backfill completado. batch={$batch}");
            if ($created > 0) {
                $this->line("Para deshacer ESTE batch: php artisan users:rollback-orphan-backfill {$batch}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Diagnóstico detallado sin escribir nada (item #105, decisión q1 de Irving):
     * cuántos, qué datos se llenarían, colisiones de login_user, duplicados, rol a asignar.
     */
    private function handleReport($baseQuery, $procesablesQuery): int
    {
        $totalOrphans = (clone $baseQuery)->count();
        $sinPassword  = (clone $baseQuery)->where(function ($q) {
            $q->whereNull('password')->orWhere('password', '');
        })->count();
        $procesables = (clone $procesablesQuery)->get(['id', 'client_id', 'user', 'name', 'email', 'phone']);

        $this->info('=== Reporte backfill users espejo (item #105) ===');
        $this->line("Total client_main_information huérfanos (sin fila en users): {$totalOrphans}");
        $this->line("  Con password (procesables tal cual):  {$procesables->count()}");
        $this->line("  Sin password (NO se crean, igual que el Observer): {$sinPassword}");
        $this->newLine();

        // Colisiones: el login_user (cmi.user) del candidato ya existe en users.login_user
        // (de un usuario que NO es el propio espejo a crear) → el INSERT tronaría por unique.
        $candidateLogins = $procesables->pluck('user')->filter()->values();
        $colisiones = User::query()
            ->whereIn('login_user', $candidateLogins)
            ->get(['login_user', 'id', 'client_id'])
            ->keyBy('login_user');

        // Duplicados: el mismo `user` (login) repetido entre los propios huérfanos candidatos
        // — el primero se crea, el segundo colisionaría contra el que se acaba de crear.
        $duplicadosInternos = $procesables->groupBy('user')
            ->filter(fn ($rows) => $rows->count() > 1);

        $this->line('Colisiones con login_user ya existente en users: ' . $colisiones->count());
        foreach ($colisiones as $login => $u) {
            $this->warn("  {$login} ya pertenece a users.id={$u->id} (client_id={$u->client_id})");
        }

        $this->newLine();
        $this->line('Duplicados internos (mismo login_user entre varios huérfanos candidatos): ' . $duplicadosInternos->count());
        foreach ($duplicadosInternos as $login => $rows) {
            $this->warn("  {$login}: client_id " . $rows->pluck('client_id')->implode(', '));
        }

        $this->newLine();
        $this->line('Rol a asignar a todos: client');
        $this->newLine();

        $sample = $procesables->take(10);
        $this->line('Muestra de datos que se llenarían (primeros ' . $sample->count() . '):');
        $this->table(
            ['client_id', 'login_user', 'name', 'email'],
            $sample->map(fn ($c) => [
                $c->client_id,
                $c->user,
                $c->name,
                $c->email ?: '(NULL)',
            ])->all()
        );

        return self::SUCCESS;
    }
}
