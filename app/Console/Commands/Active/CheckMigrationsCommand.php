<?php

namespace App\Console\Commands\Active;

use App\Services\MigrationGuardService;
use Illuminate\Console\Command;

/**
 * Item #1018 — valida las migraciones PENDIENTES sin migrar (a diferencia de
 * GuardedMigrateCommand, que corre en la ruta real de `php artisan migrate`).
 * Reusa MigrationGuardService (mismo mecanismo del item #534, no uno paralelo);
 * pensado para uso manual y para el hook de pre-commit versionado en
 * scripts/pre-commit-check-migrations.sh.
 */
class CheckMigrationsCommand extends Command
{
    protected $signature = 'megaisp:check-migrations';

    protected $description = 'Valida (sin migrar) que las migraciones pendientes estén commiteadas con ruta a main y sin operaciones destructivas sin excepción (items #534/#1018).';

    public function handle(MigrationGuardService $guard): int
    {
        if (! $guard->shouldEnforce()) {
            $this->components->info('Guardrail no aplica en este entorno (solo local, fuera de tests).');

            return self::SUCCESS;
        }

        $violations = array_merge($guard->checkPending(), $guard->checkDestructive());

        if ($violations === []) {
            $this->components->info('Sin violaciones: migraciones pendientes con ruta a main y sin operaciones destructivas sin excepción.');

            return self::SUCCESS;
        }

        $this->components->error('Migraciones pendientes con violaciones del guardrail:');

        foreach ($violations as $violation) {
            $this->components->warn($violation);
        }

        return self::FAILURE;
    }
}
