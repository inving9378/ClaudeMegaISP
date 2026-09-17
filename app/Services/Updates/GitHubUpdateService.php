<?php

namespace App\Services\Updates;

use App\Models\Release;
use App\Services\Release\ReleaseNotesRenderer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubUpdateService
{
    const CACHE_KEY = 'github_update_check';

    public function __construct(private ReleaseNotesRenderer $renderer)
    {
    }

    /**
     * Devuelve info del último GitHub Release si es más reciente que la versión instalada.
     *
     * Tres resultados posibles — NO confundir los dos últimos (item #529):
     *   - array con 'tag'          → hay actualización disponible.
     *   - null                     → NO hay actualización (estás al día). Respuesta CONFIABLE.
     *   - array con 'check_failed' → NO SE PUDO consultar (red, token vencido, 403 de
     *                                rate-limit…). NO significa "estás al día".
     */
    public function check(): ?array
    {
        if (!config('updates.enabled')) {
            return null;
        }

        $cached = Cache::get(self::CACHE_KEY);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->fetchFromGitHub();
        Cache::put(self::CACHE_KEY, $result, $this->ttlFor($result));

        return $result;
    }

    /**
     * Refresca el cache desde GitHub (para llamar desde el cron).
     */
    public function refresh(): void
    {
        if (!config('updates.enabled')) {
            return;
        }

        Cache::forget(self::CACHE_KEY);
        $result = $this->fetchFromGitHub();
        Cache::put(self::CACHE_KEY, $result, $this->ttlFor($result));

        Log::channel('single')->info('GitHubUpdateService: cache refrescado — ' . match (true) {
            ($result['check_failed'] ?? false) => 'NO se pudo consultar (' . ($result['error'] ?? 's/d') . ')',
            $result !== null                   => "actualización disponible ({$result['tag']})",
            default                            => 'sin actualización',
        });
    }

    /**
     * Un fallo de consulta se cachea muy poco: es un estado transitorio y el usuario espera
     * que reintentar sirva de algo. Un resultado bueno sí aguanta la ventana normal.
     */
    private function ttlFor(?array $result): \DateTimeInterface
    {
        return ($result['check_failed'] ?? false)
            ? now()->addMinutes(config('updates.error_cache_minutes', 2))
            : now()->addMinutes(config('updates.cache_minutes', 30));
    }

    /**
     * Estado "no se pudo consultar", distinguible de "no hay actualización".
     */
    private function checkFailed(string $motivo): array
    {
        Log::channel('single')->warning("GitHubUpdateService: {$motivo}");

        return ['check_failed' => true, 'error' => $motivo];
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function fetchFromGitHub(): ?array
    {
        $token = config('updates.read_token', '');
        $repo  = config('updates.repo', '');

        if (empty($repo)) {
            return $this->checkFailed('GITHUB_REPO no configurado — no se puede consultar actualizaciones.');
        }

        try {
            $response = Http::withHeaders(array_filter([
                    'Accept'               => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'Authorization'        => $token ? "Bearer {$token}" : null,
                ]))
                ->timeout(10)
                ->get("https://api.github.com/repos/{$repo}/releases/latest");

            if (!$response->successful()) {
                return $this->checkFailed("GitHub respondió {$response->status()} al consultar el último release.");
            }

            $latest = $response->json();
            $latestPublishedAt = $latest['published_at'] ?? null;
            $latestTag         = $latest['tag_name'] ?? null;

            if (!$latestPublishedAt || !$latestTag) {
                return $this->checkFailed('GitHub devolvió una respuesta sin tag ni fecha de publicación.');
            }

            // Compara por número de versión (major.minor), NO por fecha-como-string: el
            // tag es la clave primaria; la fecha embebida solo desempata (VersionComparator).
            $installed = Release::latest('release_date')->latest('id')->first();

            if (!$installed || !$installed->version) {
                // Sin registro local: siempre hay actualización disponible, pero sin versión
                // instalada de referencia no hay rango que acumular (item #9990672).
                return $this->buildRangeResult($latest, null);
            }

            if (VersionComparator::isNewer($latestTag, $installed->version)) {
                return $this->buildRangeResult($latest, $installed->version);
            }

            return null;

        } catch (\Throwable $e) {
            return $this->checkFailed('No se pudo contactar a GitHub: ' . $e->getMessage());
        }
    }

    private function buildResult(array $release): array
    {
        $body = $release['body'] ?? '';

        return [
            'tag'          => $release['tag_name'],
            'name'         => $release['name'] ?? $release['tag_name'],
            'body'         => $body,
            'body_html'    => $this->renderer->markdown((string) $body),
            'published_at' => $release['published_at'],
            'url'          => $release['html_url'] ?? '',
        ];
    }

    /**
     * Item #9990672 (F1·B) — enriquece el resultado base con el SALTO COMPLETO Y ACUMULADO
     * instalada..destino: aplicar una versión es un checkout, no un parche (si prod está en
     * V1.33 y aparece V1.36, aplicar V1.36 trae TAMBIÉN V1.34 y V1.35). Antes la pantalla solo
     * mostraba el último release (`releases/latest`); esto agrega el rango completo sin quitar
     * ninguna de las llaves existentes (`tag`/`name`/`body`/...) que ya consume `apply()`.
     *
     * Sin `$installedTag` (sin registro local de referencia) no hay rango que acumular: se
     * degrada al comportamiento anterior (un solo "salto" = el destino).
     */
    private function buildRangeResult(array $latest, ?string $installedTag): array
    {
        $base                  = $this->buildResult($latest);
        $base['installed_tag'] = $installedTag;

        $token = config('updates.read_token', '');
        $repo  = config('updates.repo', '');

        $versions = $installedTag
            ? $this->fetchReleasesInRange($repo, $token, $installedTag, $base['tag'])
            : [];

        if (empty($versions)) {
            // Sin rango (sin instalada de referencia, o la consulta de la lista falló):
            // al menos el destino, para no dejar la pantalla vacía.
            $versions = [[
                'tag'          => $base['tag'],
                'name'         => $base['name'],
                'body'         => $base['body'],
                'body_html'    => $base['body_html'],
                'published_at' => $base['published_at'],
                'url'          => $base['url'],
                'manual_steps' => $this->extractManualSteps($base['body']),
            ]];
        }

        $base['jump_count']  = count($versions);
        $base['is_big_jump'] = count($versions) >= 3;
        $base['versions']    = $versions;

        $base['manual_steps'] = array_values(array_filter(array_map(
            fn (array $v) => $v['manual_steps'] ? ['tag' => $v['tag'], 'steps' => $v['manual_steps']] : null,
            $versions
        )));

        $migrationsCount     = $installedTag ? $this->migrationsCountBetween($repo, $token, $installedTag, $base['tag']) : null;
        $base['migrations']  = [
            'count'   => $migrationsCount,
            'unknown' => $migrationsCount === null,
        ];

        return $base;
    }

    /**
     * Releases de GitHub entre `$installedTag` (exclusivo) y `$latestTag` (inclusivo), ORDENADOS
     * ascendente (el más viejo primero), cada uno con su `manual_steps` extraído del body.
     * Best-effort: arreglo vacío si la consulta falla (el caller degrada al destino solo).
     */
    private function fetchReleasesInRange(string $repo, string $token, string $installedTag, string $latestTag): array
    {
        try {
            $response = Http::withHeaders(array_filter([
                    'Accept'               => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'Authorization'        => $token ? "Bearer {$token}" : null,
                ]))
                ->timeout(10)
                ->get("https://api.github.com/repos/{$repo}/releases", ['per_page' => 100]);

            if (!$response->successful()) {
                return [];
            }

            $releases = $response->json() ?? [];
        } catch (\Throwable $e) {
            Log::channel('single')->warning("GitHubUpdateService: no se pudo listar releases del rango {$installedTag}..{$latestTag}: {$e->getMessage()}");
            return [];
        }

        $enRango = array_values(array_filter($releases, function ($r) use ($installedTag, $latestTag) {
            $tag = $r['tag_name'] ?? null;
            if (!$tag) {
                return false;
            }
            if (!VersionComparator::isNewer($tag, $installedTag)) {
                return false; // debe ser posterior a la instalada
            }

            return $tag === $latestTag || !VersionComparator::isNewer($tag, $latestTag); // no más nueva que el destino
        }));

        usort($enRango, function ($a, $b) {
            $pa = VersionComparator::parse($a['tag_name']);
            $pb = VersionComparator::parse($b['tag_name']);

            return [$pa['major'], $pa['minor']] <=> [$pb['major'], $pb['minor']];
        });

        return array_map(function ($r) {
            $body = (string) ($r['body'] ?? '');

            return [
                'tag'          => $r['tag_name'],
                'name'         => $r['name'] ?? $r['tag_name'],
                'body'         => $body,
                'body_html'    => $this->renderer->markdown($body),
                'published_at' => $r['published_at'] ?? null,
                'url'          => $r['html_url'] ?? '',
                'manual_steps' => $this->extractManualSteps($body),
            ];
        }, $enRango);
    }

    /**
     * Cuenta migraciones NUEVAS (`database/migrations/*.php` agregados) entre dos tags vía la
     * Compare API de GitHub — funciona sin tener el código del rango descargado localmente (a
     * diferencia de `migrate:status`, que necesita los archivos ya en el filesystem). Null =
     * no se pudo determinar (nunca se inventa un número — item #9990672, "conteo correcto").
     */
    private function migrationsCountBetween(string $repo, string $token, string $base, string $head): ?int
    {
        if ($base === $head) {
            return 0;
        }

        try {
            $response = Http::withHeaders(array_filter([
                    'Accept'               => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'Authorization'        => $token ? "Bearer {$token}" : null,
                ]))
                ->timeout(10)
                ->get("https://api.github.com/repos/{$repo}/compare/{$base}...{$head}");

            if (!$response->successful()) {
                return null;
            }

            $files = $response->json('files') ?? [];

            return count(array_filter(
                $files,
                fn ($f) => ($f['status'] ?? '') === 'added' && str_starts_with((string) ($f['filename'] ?? ''), 'database/migrations/')
            ));
        } catch (\Throwable $e) {
            Log::channel('single')->warning("GitHubUpdateService: no se pudo contar migraciones del rango {$base}...{$head}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Extrae la sección "Pasos manuales" del changelog (markdown), si el Release la declaró.
     * Convención aditiva (item #9990672, punto 5): el cuerpo del GitHub Release ya se arma por
     * bloques `ReleaseDescription` con título libre (`DeploymentService::buildReleaseBody()`) —
     * un bloque titulado "Pasos manuales" basta para que esta pantalla lo recoja, sin columna
     * nueva ni tocar el generador de changelog. Sin esa sección ⇒ null (no se inventa nada).
     */
    private function extractManualSteps(string $body): ?string
    {
        if (!preg_match('/^#{1,6}\s*Pasos\s+manuales\s*:?\s*$/im', $body, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $resto = substr($body, $m[0][1] + strlen($m[0][0]));

        if (preg_match('/^#{1,6}\s+\S/m', $resto, $siguiente, PREG_OFFSET_CAPTURE)) {
            $resto = substr($resto, 0, $siguiente[0][1]);
        }

        $texto = trim($resto);

        return $texto !== '' ? $texto : null;
    }
}
