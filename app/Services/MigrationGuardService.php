<?php

namespace App\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Item #534 — guardrail: ninguna migración corre en dev sin quedar con una
 * ruta segura a main. Antes de dejar correr `migrate`, exige que cada
 * migración PENDIENTE esté commiteada y en una rama con camino a main
 * (main mismo, o una rama del circuito ligada a un item vivo).
 *
 * Si git no está disponible o falla por una razón ajena al archivo revisado,
 * se falla ABIERTO (no bloquea) — el guardrail no debe convertirse en un
 * punto de falla que trabe dev por infraestructura ajena a su propósito.
 */
class MigrationGuardService
{
    public function __construct(protected Migrator $migrator)
    {
    }

    public function shouldEnforce(): bool
    {
        return app()->environment('local') && ! app()->runningUnitTests();
    }

    /**
     * @return array<int, string> violaciones "nombre_migracion: motivo"
     */
    public function checkPending(): array
    {
        try {
            $violations = [];

            foreach ($this->pendingMigrationFiles() as $name => $path) {
                if ($reason = $this->violationFor($path)) {
                    $violations[] = "{$name}: {$reason}";
                }
            }

            return $violations;
        } catch (Throwable $e) {
            Log::channel('migration_guard')->warning('Guardrail no pudo evaluarse, se deja pasar (fail-open)', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function logOverride(array $violations): void
    {
        Log::channel('migration_guard')->warning('Guardrail omitido con --force-uncommitted', [
            'rama' => $this->currentBranch(),
            'violaciones' => $violations,
        ]);
    }

    /**
     * @return array<string, string> nombre => ruta absoluta
     */
    protected function pendingMigrationFiles(): array
    {
        $paths = array_unique(array_merge([database_path('migrations')], $this->migrator->paths()));

        $files = $this->migrator->getMigrationFiles($paths);
        $ran = $this->migrator->getRepository()->getRan();

        return array_diff_key($files, array_flip($ran));
    }

    protected function violationFor(string $path): ?string
    {
        $relative = $this->relativePath($path);

        if (! $this->isTrackedInGit($relative)) {
            return 'archivo no trackeado en git (sin commitear)';
        }

        if ($this->hasUncommittedChanges($relative)) {
            return 'archivo commiteado pero con cambios sin commitear (working tree/index)';
        }

        if (! $this->hasPathToMain()) {
            $branch = $this->currentBranch() ?: '(desconocida)';

            return "commiteada en la rama '{$branch}', sin ruta registrada a main";
        }

        return null;
    }

    protected function relativePath(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '/\\');
    }

    protected function isTrackedInGit(string $relative): bool
    {
        return Process::path(base_path())
            ->run(['git', 'ls-files', '--error-unmatch', '--', $relative])
            ->successful();
    }

    protected function hasUncommittedChanges(string $relative): bool
    {
        $result = Process::path(base_path())->run(['git', 'status', '--porcelain', '--', $relative]);

        return $result->successful() && trim($result->output()) !== '';
    }

    protected function hasPathToMain(): bool
    {
        $branch = $this->currentBranch();

        if ($branch === '' || $branch === 'main') {
            return true;
        }

        if (preg_match('#^circuito/item-(\d+)-#', $branch, $m)) {
            $item = RoadmapItem::find((int) $m[1]);

            // 'estacion' (accessor derivado) === 'done' cubre completado/cancelado/rechazado/
            // archivado — un item ya cerrado no es una ruta viva a main, aunque su branch exista.
            return $item !== null && $item->branch === $branch && $item->estacion !== 'done';
        }

        return false;
    }

    protected function currentBranch(): string
    {
        $result = Process::path(base_path())->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);

        if (! $result->successful()) {
            return '';
        }

        $branch = trim($result->output());

        return $branch === 'HEAD' ? '' : $branch;
    }
}
