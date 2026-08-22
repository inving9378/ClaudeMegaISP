<?php

namespace App\Console\Commands;

use App\Services\MigrationGuardService;
use Illuminate\Database\Console\Migrations\MigrateCommand;

/**
 * Item #534 — reemplaza el comando `migrate` nativo (ver
 * AppServiceProvider::boot(), $this->app->extend(MigrateCommand::class, ...))
 * para que NINGUNA migración corra en dev sin una ruta segura a main.
 *
 * Item #1018 — el mismo gate además rechaza migraciones pendientes con
 * operaciones destructivas (dropColumn/dropTable/renameColumn/truncate/
 * change() reductor), salvo excepción `contraccion_de: V{n}` madura (ver
 * MigrationGuardService::checkDestructive()).
 *
 * Escape hatch para casos legítimos (rollback de emergencia, debugging, o
 * un change() que solo amplía): --force-uncommitted. Queda auditado en
 * storage/logs/migration-guard.log.
 */
class GuardedMigrateCommand extends MigrateCommand
{
    protected $signature = 'migrate {--database= : The database connection to use}
                {--force : Force the operation to run when in production}
                {--force-uncommitted : Omite el guardrail de migraciones sin ruta segura a main (uso manual, queda auditado)}
                {--path=* : The path(s) to the migrations files to be executed}
                {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
                {--schema-path= : The path to a schema dump file}
                {--pretend : Dump the SQL queries that would be run}
                {--seed : Indicates if the seed task should be re-run}
                {--seeder= : The class name of the root seeder}
                {--step : Force the migrations to be run so they can be rolled back individually}';

    public function handle()
    {
        $guard = $this->laravel->make(MigrationGuardService::class);

        if ($guard->shouldEnforce() && ! $this->option('pretend')) {
            $violations = $guard->checkPending();

            if ($violations !== []) {
                if (! $this->option('force-uncommitted')) {
                    $this->components->error('Guardrail de migraciones (item #534): hay migraciones pendientes sin ruta segura a main.');

                    foreach ($violations as $violation) {
                        $this->components->warn($violation);
                    }

                    $this->components->info('Commitea el archivo (y, si aplica, liga la rama a un item del circuito) antes de migrar.');
                    $this->components->info('Excepción legítima (rollback de emergencia/debug): --force-uncommitted (queda auditado).');

                    return 1;
                }

                $guard->logOverride($violations);
                $this->components->warn('Guardrail omitido con --force-uncommitted (queda auditado en storage/logs/migration-guard.log).');
            }

            $destructivas = $guard->checkDestructive();

            if ($destructivas !== []) {
                if (! $this->option('force-uncommitted')) {
                    $this->components->error('Guardrail de migraciones (item #1018): hay operaciones destructivas sin excepción de contracción madura.');

                    foreach ($destructivas as $violation) {
                        $this->components->warn($violation);
                    }

                    $this->components->info('Patrón en dos tiempos: agrega/deja de usar en esta versión; retira en una versión de contracción posterior.');
                    $this->components->info('Excepción: declara `contraccion_de: V{n}` en el item cuando esa versión ya lleve el mínimo de días aplicada en producción.');
                    $this->components->info('Escape hatch (uso manual, queda auditado): --force-uncommitted.');

                    return 1;
                }

                $guard->logOverride($destructivas);
                $this->components->warn('Guardrail destructivo omitido con --force-uncommitted (queda auditado en storage/logs/migration-guard.log).');
            }
        }

        return parent::handle();
    }
}
