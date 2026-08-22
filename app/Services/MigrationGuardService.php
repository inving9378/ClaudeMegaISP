<?php

namespace App\Services;

use App\Models\Release;
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
 *
 * Item #1018 (sub-item 2/5 de "regreso a versión anterior", #1012) — extiende
 * ESTE mismo guardrail (no uno paralelo) con un segundo chequeo: ninguna
 * migración pendiente puede traer una operación destructiva (dropColumn/
 * dropTable/renameColumn/truncate/change() reductor) salvo que el item ligado
 * a la rama declare `contraccion_de: V{n}` y esa versión ya lleve
 * `min_dias_antes_de_contraccion` (config/migration_guard.php) aplicada en
 * producción (releases.aplicada_en_prod_at, vínculo técnico del item #1017).
 */
class MigrationGuardService
{
    /**
     * patrón regex => [motivo, sugerencia expansiva]. Una migración destructiva real
     * casi siempre cabe en dos versiones: V(n) agrega/deja de usar, V(n+1) retira —
     * solo cuando V(n) ya maduró en producción (ver isContractionExempt()).
     */
    private const DESTRUCTIVE_PATTERNS = [
        '/Schema::drop(?!IfExists)\(/' => [
            'motivo' => 'Schema::drop() elimina la tabla por completo',
            'sugerencia' => 'deja de usarla en esta versión (código ya no lee/escribe ahí); retírala en una versión de contracción posterior',
        ],
        '/Schema::dropIfExists\(/' => [
            'motivo' => 'Schema::dropIfExists() elimina la tabla por completo',
            'sugerencia' => 'deja de usarla en esta versión (código ya no lee/escribe ahí); retírala en una versión de contracción posterior',
        ],
        '/->dropColumn(s)?\(/' => [
            'motivo' => 'dropColumn() elimina la columna y sus datos',
            'sugerencia' => 'agrega la columna nueva y migra los lectores primero; retira la vieja en una versión de contracción posterior',
        ],
        '/->renameColumn\(/' => [
            'motivo' => 'renameColumn() rompe a cualquier lector que siga usando el nombre viejo',
            'sugerencia' => 'agrega la columna nueva, escribe en ambas, migra los lectores; retira la vieja en una versión de contracción posterior',
        ],
        '/->truncate\(\)/' => [
            'motivo' => 'truncate() borra todas las filas de la tabla',
            'sugerencia' => 'si es un catálogo, usa upsert/firstOrCreate en vez de truncar',
        ],
        '/DB::statement\(\s*[\'"]\s*TRUNCATE/i' => [
            'motivo' => 'TRUNCATE por SQL crudo borra todas las filas de la tabla',
            'sugerencia' => 'si es un catálogo, usa upsert/firstOrCreate en vez de truncar',
        ],
        '/->change\(\)/' => [
            'motivo' => '->change() puede reducir tipo/longitud de forma destructiva (el validador no distingue ampliar de reducir, así que trata todo change() como sospechoso)',
            'sugerencia' => 'si solo AMPLÍA (longitud mayor, columna ahora nullable) es seguro y puedes usar --force-uncommitted documentando por qué; si REDUCE, usa el patrón de dos tiempos',
        ],
    ];

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

    /**
     * Item #1018 — inspecciona el CONTENIDO de las migraciones pendientes buscando
     * operaciones destructivas. A diferencia de checkPending() (¿está commiteada?),
     * este chequeo mira qué HACE la migración. Fail-open igual que checkPending: si
     * git/filesystem fallan por una razón ajena, no bloquea.
     *
     * @return array<int, string> violaciones "nombre_migracion:linea: motivo — alternativa: sugerencia"
     */
    public function checkDestructive(): array
    {
        try {
            $exempt = $this->isContractionExempt();
            $violations = [];

            foreach ($this->pendingMigrationFiles() as $name => $path) {
                foreach ($this->destructiveMatchesIn($path) as $match) {
                    if ($exempt) {
                        continue;
                    }

                    $violations[] = "{$name}:{$match['linea']}: {$match['motivo']} — alternativa: {$match['sugerencia']}";
                }
            }

            return $violations;
        } catch (Throwable $e) {
            Log::channel('migration_guard')->warning('Guardrail destructivo no pudo evaluarse, se deja pasar (fail-open)', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<int, array{linea: int, motivo: string, sugerencia: string}>
     */
    protected function destructiveMatchesIn(string $path): array
    {
        $matches = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];

        foreach ($lines as $i => $line) {
            foreach (self::DESTRUCTIVE_PATTERNS as $pattern => $info) {
                if (preg_match($pattern, $line)) {
                    $matches[] = ['linea' => $i + 1, 'motivo' => $info['motivo'], 'sugerencia' => $info['sugerencia']];
                }
            }
        }

        return $matches;
    }

    /**
     * ¿El item ligado a la rama actual declara `contraccion_de: V{n}` y esa versión ya
     * maduró en producción? Fail-closed a propósito: sin item resoluble, sin declaración,
     * sin release con ese `version`, o sin `aplicada_en_prod_at` poblada, NO hay excepción
     * — no se inventa una fecha de madurez que nadie confirmó.
     */
    protected function isContractionExempt(): bool
    {
        $item = $this->resolveItemFromBranch();
        if (! $item) {
            return false;
        }

        $texto = implode("\n", array_filter([$item->description, $item->prompt, $item->comentarios_claude]));
        if (! preg_match('/contraccion_de:\s*V?([\w.\-]+)/i', $texto, $m)) {
            return false;
        }

        $release = Release::where('version', $m[1])->first();
        if (! $release || ! $release->aplicada_en_prod_at) {
            return false;
        }

        $minDias = (int) config('migration_guard.min_dias_antes_de_contraccion', 14);

        return $release->aplicada_en_prod_at->lte(now()->subDays($minDias));
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

    /**
     * Item ligado a la rama actual por convención `circuito/item-<id>-...`, sin filtrar por
     * estado — a diferencia de hasPathToMain() (que exige el item VIVO), aquí un item ya
     * `completado` sigue siendo válido para leer su declaración `contraccion_de` (lo normal
     * es que la contracción se declare cuando la versión que la habilita ya cerró).
     */
    protected function resolveItemFromBranch(): ?RoadmapItem
    {
        $branch = $this->currentBranch();

        if (! preg_match('#^circuito/item-(\d+)-#', $branch, $m)) {
            return null;
        }

        return RoadmapItem::find((int) $m[1]);
    }
}
