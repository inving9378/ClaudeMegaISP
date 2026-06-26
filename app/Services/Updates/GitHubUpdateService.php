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
     * Null = sin actualización disponible o módulo deshabilitado.
     *
     * Resultado: ['tag' => '...', 'body' => '...', 'published_at' => '...', 'url' => '...']
     */
    public function check(): ?array
    {
        if (!config('updates.enabled')) {
            return null;
        }

        return Cache::remember(self::CACHE_KEY, now()->addMinutes(config('updates.cache_minutes', 30)), function () {
            return $this->fetchFromGitHub();
        });
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
        Cache::put(self::CACHE_KEY, $result, now()->addMinutes(config('updates.cache_minutes', 30)));

        Log::channel('single')->info('GitHubUpdateService: cache refrescado — ' . ($result ? "actualización disponible ({$result['tag']})" : 'sin actualización'));
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
            return null;
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
                Log::channel('single')->warning("GitHubUpdateService: GitHub API devolvió {$response->status()}");
                return null;
            }

            $latest = $response->json();
            $latestPublishedAt = $latest['published_at'] ?? null;
            $latestTag         = $latest['tag_name'] ?? null;

            if (!$latestPublishedAt || !$latestTag) {
                return null;
            }

            // Compara por fecha: el release de GitHub es más reciente que el instalado
            $installed = Release::latest('release_date')->latest('id')->first();
            $installedDate = $installed?->release_date;

            if (!$installedDate) {
                // Sin registro local: siempre hay actualización disponible
                return $this->buildResult($latest);
            }

            $githubDate    = \Carbon\Carbon::parse($latestPublishedAt);
            $installedCarbon = \Carbon\Carbon::parse($installedDate);

            // Hay actualización si el tag de GitHub DIFIERE del instalado y su release NO es
            // anterior (mismo día o posterior). Comparar también por tag —no solo por día—
            // cubre el caso de varias releases el MISMO día (p.ej. V1.9 y V1.10 el 26/06):
            // antes la comparación por día las daba como "iguales" y la nueva quedaba invisible.
            // El gate por fecha (>=) evita "downgrades" si se elimina la última release en GitHub.
            $differentTag = $latestTag !== ($installed->version ?? null);
            $notOlder     = $githubDate->startOfDay()->gte($installedCarbon->startOfDay());

            if ($differentTag && $notOlder) {
                return $this->buildResult($latest);
            }

            return null;

        } catch (\Throwable $e) {
            Log::channel('single')->warning('GitHubUpdateService: excepción consultando GitHub: ' . $e->getMessage());
            return null;
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
