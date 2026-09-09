<?php

namespace App\Console\Commands\Active;

use App\Models\Release;
use App\Services\Updates\VersionComparator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

/**
 * Diagnóstico READ-ONLY (item roadmap #9990669): cruza las 4 fuentes de verdad de una versión
 * publicada — tabla `releases`, tags de git locales, tags en `origin` (GitHub) y GitHub Releases
 * (API) — y da un veredicto por versión. NUNCA escribe en ninguna de las 4 fuentes: no crea tags,
 * no toca la BD, no llama a la API de GitHub más que con GET.
 *
 * Veredictos (mutuamente excluyentes, por prioridad):
 *   RELEASE_SIN_FILA  — existe GitHub Release pero la tabla `releases` no tiene esa versión.
 *   PUBLICADA         — está en las 4 fuentes: tabla + tag local + tag en origin + GitHub Release.
 *   SOLO_LOCAL        — el tag existe localmente pero nunca llegó a origin (no hubo `git push`).
 *   TAG_SIN_RELEASE   — el tag sí llegó a alguna fuente remota (origin y/o tabla) pero no existe
 *                       el objeto "GitHub Release" (el `git_push` corrió; `github_release` no, o
 *                       falló). Es el estado por defecto cuando hay tag pero no Release.
 *   SOLO_TABLA        — caso patológico: fila en `releases` sin tag en ningún lado (ni local, ni
 *                       origin, ni GitHub). No está en la lista de 4 del item, se deja aparte para
 *                       no forzar una etiqueta engañosa sobre un caso que no encaja en ninguna.
 */
class ReconcileReleasesCommand extends Command
{
    protected $signature = 'releases:reconciliar
                            {version? : Filtra el reporte a una sola versión exacta (ej. V1.34-09.09.2026)}
                            {--dry-run : No-op — el comando es SIEMPRE de solo lectura, la flag existe solo por consistencia de interfaz con el resto de comandos releases:*}';

    protected $description = 'Cruza tabla releases / tags locales / tags en origin / GitHub Releases y da un veredicto por versión (solo lectura, item #9990669)';

    public function handle(): int
    {
        $filtro = $this->argument('version');

        $tabla = $this->versionesDeTabla();
        $local = $this->tagsLocales();
        $remoto = $this->tagsRemotos();

        [$github, $githubExtra, $githubOk, $githubError] = $this->releasesDeGithub();

        if (!$githubOk) {
            $this->error("No se pudo consultar la API de GitHub: {$githubError}");
            $this->warn('El veredicto de cada versión sin ese dato NO es confiable — se aborta sin imprimir tabla.');
            return self::FAILURE;
        }

        $versiones = array_unique(array_merge(
            array_keys($tabla),
            $local,
            $remoto,
            array_keys($github)
        ));

        if ($filtro !== null) {
            $versiones = array_values(array_filter($versiones, fn ($v) => $v === $filtro));
            if (empty($versiones)) {
                $this->warn("«{$filtro}» no aparece en ninguna de las 4 fuentes.");
                return self::SUCCESS;
            }
        }

        usort($versiones, fn ($a, $b) => $this->compararDesc($a, $b));

        $rows = [];
        $conteo = [];

        foreach ($versiones as $version) {
            $enTabla  = array_key_exists($version, $tabla);
            $enLocal  = in_array($version, $local, true);
            $enRemoto = in_array($version, $remoto, true);
            $enGithub = array_key_exists($version, $github);

            $veredicto = $this->veredicto($enTabla, $enLocal, $enRemoto, $enGithub);
            $conteo[$veredicto] = ($conteo[$veredicto] ?? 0) + 1;

            $rows[] = [
                $version,
                $enTabla ? 'sí' : 'NO',
                $enLocal ? 'sí' : 'NO',
                $enRemoto ? 'sí' : 'NO',
                $enGithub ? 'sí' : 'NO',
                $veredicto,
            ];
        }

        $this->table(['Versión', 'Tabla', 'Local', 'Origin', 'GitHub Release', 'Veredicto'], $rows);

        $this->newLine();
        $this->line('Resumen: ' . collect($conteo)->map(fn ($n, $v) => "{$v}={$n}")->implode('  '));
        $this->line('Total de versiones cruzadas: ' . count($versiones));

        return self::SUCCESS;
    }

    private function veredicto(bool $enTabla, bool $enLocal, bool $enRemoto, bool $enGithub): string
    {
        if ($enGithub && !$enTabla) {
            return 'RELEASE_SIN_FILA';
        }

        if ($enTabla && $enLocal && $enRemoto && $enGithub) {
            return 'PUBLICADA';
        }

        if ($enLocal && !$enRemoto) {
            return 'SOLO_LOCAL';
        }

        if (!$enLocal && !$enRemoto && !$enGithub) {
            return 'SOLO_TABLA';
        }

        return 'TAG_SIN_RELEASE';
    }

    /** @return array<string,string> version => release_date (o '') */
    private function versionesDeTabla(): array
    {
        return Release::pluck('release_date', 'version')
            ->map(fn ($d) => (string) $d)
            ->all();
    }

    /** @return string[] */
    private function tagsLocales(): array
    {
        $p = Process::fromShellCommandline("git tag -l 'V*'", base_path(), $this->gitEnv(), null, 30);
        $p->run();

        return array_values(array_filter(array_map('trim', explode("\n", $p->getOutput()))));
    }

    /** @return string[] */
    private function tagsRemotos(): array
    {
        $p = Process::fromShellCommandline("git ls-remote --tags origin 'refs/tags/V*'", base_path(), $this->gitEnv(), null, 30);
        $p->run();

        if (!$p->isSuccessful()) {
            $this->warn('No se pudo leer tags de origin (git ls-remote falló): ' . trim($p->getErrorOutput()));
            return [];
        }

        $tags = [];
        foreach (explode("\n", $p->getOutput()) as $line) {
            if (!preg_match('#refs/tags/(V\S+?)(\^\{\})?$#', trim($line), $m)) {
                continue;
            }
            $tags[$m[1]] = true;
        }

        return array_keys($tags);
    }

    /**
     * @return array{0: array<string,array>, 1: array<string,array>, 2: bool, 3: ?string}
     *         [tag_name => release cruda, tag_name => {html_url, published_at}, ok, error]
     */
    private function releasesDeGithub(): array
    {
        $token = config('deployment.github.token', '');
        $repo  = config('deployment.github.repo', '');

        if (empty($token) || empty($repo)) {
            return [[], [], false, 'GITHUB_TOKEN o GITHUB_REPO no configurados en .env'];
        }

        $tags = [];
        $extra = [];
        $page = 1;

        try {
            do {
                $response = Http::withToken($token)
                    ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
                    ->timeout(15)
                    ->get("https://api.github.com/repos/{$repo}/releases", ['per_page' => 100, 'page' => $page]);

                if (!$response->successful()) {
                    return [[], [], false, "GitHub respondió {$response->status()}: " . $response->body()];
                }

                $batch = $response->json();
                if (!is_array($batch)) {
                    return [[], [], false, 'GitHub devolvió una respuesta que no es un arreglo JSON.'];
                }

                foreach ($batch as $release) {
                    $tag = $release['tag_name'] ?? null;
                    if (!$tag) {
                        continue;
                    }
                    $tags[$tag] = true;
                    $extra[$tag] = [
                        'html_url'     => $release['html_url'] ?? '',
                        'published_at' => $release['published_at'] ?? null,
                    ];
                }

                $page++;
            } while (count($batch) === 100 && $page <= 10);
        } catch (\Throwable $e) {
            return [[], [], false, 'Excepción al consultar GitHub: ' . $e->getMessage()];
        }

        return [$tags, $extra, true, null];
    }

    private function compararDesc(string $a, string $b): int
    {
        if (VersionComparator::isNewer($a, $b)) {
            return -1;
        }
        if (VersionComparator::isNewer($b, $a)) {
            return 1;
        }

        return strcmp($b, $a);
    }

    private function gitEnv(): array
    {
        $home = getenv('HOME')
            ?: (function_exists('posix_getpwuid') && function_exists('posix_getuid')
                ? (posix_getpwuid(posix_getuid())['dir'] ?? '/root')
                : '/root');

        return [
            'PATH'                => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME'                => $home,
            'LC_ALL'              => 'C',
            'LANG'                => 'C',
            // Permite operar git aunque el dueño del directorio del worktree sea otro usuario
            // (mismo patrón que DeploymentService::buildEnv() / RoadmapCircuitoService).
            'GIT_CONFIG_COUNT'    => '1',
            'GIT_CONFIG_KEY_0'    => 'safe.directory',
            'GIT_CONFIG_VALUE_0'  => base_path(),
        ];
    }
}
