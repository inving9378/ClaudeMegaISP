<?php

namespace App\Services\Updates;

use App\Models\Release;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubUpdateService
{
    const CACHE_KEY = 'github_update_check';

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
                // Sin registro local: siempre hay actualización disponible
                return $this->buildResult($latest);
            }

            if (VersionComparator::isNewer($latestTag, $installed->version)) {
                return $this->buildResult($latest);
            }

            return null;

        } catch (\Throwable $e) {
            return $this->checkFailed('No se pudo contactar a GitHub: ' . $e->getMessage());
        }
    }

    private function buildResult(array $release): array
    {
        return [
            'tag'          => $release['tag_name'],
            'name'         => $release['name'] ?? $release['tag_name'],
            'body'         => $release['body'] ?? '',
            'published_at' => $release['published_at'],
            'url'          => $release['html_url'] ?? '',
        ];
    }
}
