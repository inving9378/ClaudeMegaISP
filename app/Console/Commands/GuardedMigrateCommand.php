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

        return $this->conCandadoDeEsquema(fn () => parent::handle());
    }

    /**
     * Item #915 (sub-item de #911) — CANDADO DE ESQUEMA ENTRE WORKTREES.
     *
     * Los 6 worktrees del circuito (`/home/meganet/circuito/wt-N`) aíslan ARCHIVOS, pero NO estado:
     * los tres `.env` revisados apuntan a la misma `DB_DATABASE=megaisp`. Dos vueltas migrando a la
     * vez sobre esa base se pisan de verdad, y eso no lo atrapa ningún detector de archivos ni la
     * serialización por módulo (que lo venía mitigando por accidente).
     *
     * Es la precondición para aflojar el pre-filtro de módulo (#916): al permitir varias terminales
     * en el mismo módulo, desaparece esa mitigación accidental.
     *
     * Lock de archivo exclusivo y BLOQUEANTE: el segundo `migrate` espera su turno en vez de fallar
     * — una migración legítima nunca debe perderse por concurrencia. Si el lock no se puede tomar
     * (FS de solo lectura, permisos), NO se bloquea la migración: se avisa y se sigue, porque este
     * candado es una protección de concurrencia, no la autorización para migrar.
     */
    private function conCandadoDeEsquema(callable $run)
    {
        $ruta = storage_path('app/circuito');
        if (! is_dir($ruta)) {
            @mkdir($ruta, 0775, true);
        }
        $fh = @fopen($ruta . '/migrate-esquema.lock', 'c');
        if ($fh === false) {
            $this->components->warn('No se pudo abrir el candado de esquema (#915): se migra sin serializar.');

            return $run();
        }

        if (! flock($fh, LOCK_EX | LOCK_NB)) {
            $this->components->info('Otra vuelta está migrando sobre la misma base: esperando el candado de esquema (#915)…');
            flock($fh, LOCK_EX);   // bloqueante: espera su turno, no falla
        }

        try {
            return $run();
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }
}
