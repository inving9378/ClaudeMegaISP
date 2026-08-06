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

            // Compara por fecha: el release de GitHub es más reciente que el instalado
            $installed = Release::latest('release_date')->latest('id')->first();
            $installedDate = $installed?->release_date;

            if (!$installedDate) {
                // Sin registro local: siempre hay actualización disponible
                return $this->buildResult($latest);
            }

            // published_at de GitHub viene en UTC (sufijo Z); release_date se guarda en la zona
            // local (app.timezone). Normalizamos AMBAS a la misma zona ANTES de comparar el día:
            // si no, los startOfDay() caen en husos distintos (6h en America/Mexico_City) y una
            // release del MISMO día se ve como "anterior" (00:00Z = 18:00 del día previo local).
            $tz           = config('app.timezone') ?: 'UTC';
            $githubDay    = \Carbon\Carbon::parse($latestPublishedAt)->setTimezone($tz)->startOfDay();
            $installedDay = \Carbon\Carbon::parse($installedDate)->setTimezone($tz)->startOfDay();

            // Hay actualización si el tag de GitHub DIFIERE del instalado y su release NO es de un
            // día anterior (mismo día o posterior). Comparar por tag —no solo por día— cubre varias
            // releases el MISMO día (p.ej. V1.9 y V1.11 el 26/06). El gate por fecha (>=) evita
            // "downgrades" si se elimina la última release en GitHub.
            $differentTag = $latestTag !== ($installed->version ?? null);
            $notOlder     = $githubDay->gte($installedDay);

            if ($differentTag && $notOlder) {
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
