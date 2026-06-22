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

            // Comparar por fecha de DÍA (sin hora): published_at trae hora (p.ej. 21:01:15Z)
            // mientras release_date se guarda como fecha (00:00:00). Sin normalizar, la MISMA
            // versión del mismo día daría falso positivo (21:01 > 00:00). Solo hay actualización
            // si el Release de GitHub es de un día POSTERIOR al instalado.
            // TODO(futuro): si llegan a publicarse 2 releases el mismo día, comparar por tag/semver.
            if ($githubDate->startOfDay()->gt($installedCarbon->startOfDay())) {
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
