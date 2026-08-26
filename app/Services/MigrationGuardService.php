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
 *
 * ── UN GUARDRAIL NO PUEDE DEPENDER DEL ESTADO QUE PROTEGE (2026-08-25) ─────────────────────────
 *
 * El 25-ago a las 18:14 una terminal corrió la suite, `migrate:fresh` borró las 500 tablas de dev,
 * y el `migrate` de vuelta NO reconstruyó nada: este guardrail no podía leer la tabla `migrations`
 * —la que ese mismo `migrate:fresh` acababa de borrar— y en esa rama fallaba CERRADO. La base
 * terminó en 0 tablas en vez de 500. El freno no evitó el daño: impidió la reparación.
 *
 * De ahí las tres reglas que ordenan este archivo, y que valen para cualquier freno del sistema:
 *
 * 1. **La decisión de BLOQUEAR se toma con git y con el sistema de archivos**, nunca con una
 *    consulta a la base que se está protegiendo. Todo lo que puede decir "no" —¿está commiteada?,
 *    ¿tiene ruta a main?, ¿trae un patrón destructivo?— es texto en disco.
 * 2. **Bloquear una acción destructiva NO es lo mismo que bloquear la reparación.** Si no se puede
 *    leer el estado aplicado (tabla `migrations` ausente = base vacía, o conexión caída), no hay
 *    nada que proteger y sí algo que reconstruir: se PERMITE, y se dice con esas palabras en el
 *    log. Ver `estadoAplicadoLegible()`.
 * 3. **La base sólo puede volver el guardrail MÁS estricto, nunca ser el motivo de que falle.**
 *    Los dos refinamientos que la consultan (item cerrado en `hasPathToMain`, contracción madura
 *    en `isContractionExempt`) se saltan sin ruido si la base no responde: su ausencia deja el
 *    veredicto en manos de git, que es donde vive la decisión.
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
        if (! $this->estadoAplicadoLegible()) {
            return $this->permitirReconstruccion('checkPending');
        }

        try {
            $violations = [];

            foreach ($this->pendingMigrationFiles() as $name => $path) {
                if ($reason = $this->violationFor($path)) {
                    $violations[] = "{$name}: {$reason}";
                }
            }

            return $violations;
        } catch (Throwable $e) {
            // Aquí ya sólo caben fallos de git/filesystem: el estado aplicado se comprobó arriba.
            Log::channel('migration_guard')->warning('Guardrail no pudo evaluarse por git/archivos, se deja pasar', [
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
        if (! $this->estadoAplicadoLegible()) {
            return $this->permitirReconstruccion('checkDestructive');
        }

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
            Log::channel('migration_guard')->warning('Guardrail destructivo no pudo evaluarse por git/archivos, se deja pasar', [
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

        // Item #1020: el guard es sobre up() ("disciplina expansiva" — ver cabecera de la
        // clase); down() existe justo para reversar lo que up() creó/agregó, así que
        // Schema::dropIfExists() de una tabla que el propio up() acaba de crear no pierde
        // datos de nadie y no debe contar como destructivo. Que down() sea seguro/probado es
        // el sub-item 3 de #1012 (#1019, up→down→up), un guard distinto a este. Se corta el
        // escaneo en la línea donde arranca `function down`; si el archivo no lo declara, se
        // escanea completo (mismo comportamiento que antes).
        $finDeUp = null;
        foreach ($lines as $i => $line) {
            if (preg_match('/function\s+down\s*\(/', $line)) {
                $finDeUp = $i;
                break;
            }
        }

        foreach ($lines as $i => $line) {
            if ($finDeUp !== null && $i >= $finDeUp) {
                break;
            }

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
        try {
            return $this->contraccionMaduraSegunRoadmap();
        } catch (Throwable $e) {
            // Sin base no hay excepción de contracción: el guardrail queda MÁS estricto, que es
            // el lado seguro. Nunca al revés (ver regla 3 del doc de clase).
            return false;
        }
    }

    protected function contraccionMaduraSegunRoadmap(): bool
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
     * ¿Se puede leer el estado aplicado (la tabla `migrations`)?
     *
     * Se pregunta DELIBERADAMENTE y por adelantado, en vez de descubrirlo por una excepción a
     * media evaluación. Ésa es toda la diferencia entre las dos formas de fallar: una excepción
     * genérica no distingue "la base está vacía" de "git no respondió", y el 25-ago esa mezcla se
     * resolvió hacia el lado que impidió reconstruir.
     *
     * `false` significa una de dos cosas, y las dos quieren lo mismo: no hay tabla `migrations`
     * (base recién vaciada o recién creada — no hay nada que proteger, sí algo que reconstruir),
     * o la conexión no responde (y entonces `migrate` no va a poder hacer daño de todos modos,
     * porque tampoco va a poder conectarse).
     */
    public function estadoAplicadoLegible(): bool
    {
        try {
            return $this->migrator->getRepository()->repositoryExists();
        } catch (Throwable $e) {
            Log::channel('migration_guard')->info('No se pudo leer el estado aplicado', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * La decisión de permitir la reconstrucción, con nombre propio y su rastro.
     *
     * NO es "fail-open": es la respuesta correcta a una base sin estado que proteger. Se registra
     * aparte para que en el log se distinga de un guardrail que se rindió, que es justo lo que
     * nadie pudo distinguir la noche del incidente.
     *
     * @return array<int, string> siempre vacío (sin violaciones)
     */
    protected function permitirReconstruccion(string $chequeo): array
    {
        Log::channel('migration_guard')->warning(
            'Estado aplicado ilegible: se PERMITE migrar (reconstrucción). Un freno que impide restaurar es peor que no tener freno.',
            ['chequeo' => $chequeo, 'rama' => $this->currentBranch()]
        );

        return [];
    }

    /**
     * ¿La base dice que este item ya está cerrado? Refinamiento OPCIONAL: sólo puede volver el
     * guardrail más ESTRICTO, nunca ser el motivo de que falle. `null` = sin opinión (la base no
     * respondió), y entonces manda git.
     */
    protected function itemCerradoSegunRoadmap(string $branch): ?bool
    {
        if (! preg_match('#^circuito/item-(\d+)-#', $branch, $m)) {
            return null;
        }

        try {
            $item = RoadmapItem::find((int) $m[1]);

            if ($item === null || $item->branch !== $branch) {
                return null;
            }

            // 'estacion' (accessor derivado) === 'done' cubre completado/cancelado/rechazado/
            // archivado — un item ya cerrado no es una ruta viva a main, aunque su branch exista.
            return $item->estacion === 'done';
        } catch (Throwable $e) {
            return null;
        }
    }

    /** ¿La rama existe de verdad como referencia de git? (decisión sin base de datos) */
    protected function ramaExiste(string $branch): bool
    {
        return Process::path(base_path())
            ->run(['git', 'rev-parse', '--verify', '--quiet', 'refs/heads/'.$branch])
            ->successful();
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

    /**
     * ¿Esta rama tiene ruta registrada a main?
     *
     * El veredicto lo da GIT: main mismo, HEAD suelto, o una rama del circuito que existe como
     * referencia real. La Hoja de Ruta entra sólo como refinamiento que puede ENDURECER (un item
     * ya cerrado no es ruta viva), y si no contesta, no pasa nada: manda git. Antes este método
     * consultaba `roadmap_items` para poder decir "sí", así que con la base caída lanzaba, y esa
     * excepción era la que terminaba decidiendo — desde fuera, sin querer.
     */
    protected function hasPathToMain(): bool
    {
        $branch = $this->currentBranch();

        if ($branch === '' || $branch === 'main') {
            return true;
        }

        if (! preg_match('#^circuito/item-\d+-#', $branch) || ! $this->ramaExiste($branch)) {
            return false;
        }

        return $this->itemCerradoSegunRoadmap($branch) !== true;
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
