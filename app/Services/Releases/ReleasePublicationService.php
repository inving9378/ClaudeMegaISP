<?php

namespace App\Services\Releases;

use App\Models\Release;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Item roadmap #9990671 (F1): estado real de publicación de cada versión, consultando
 * GitHub por API HTTPS con token (NUNCA por SSH — a propósito, no depende de `git
 * ls-remote origin` ni de ninguna otra vía que use la llave SSH del #9990642 frenado).
 *
 * Regla de honestidad del item: si GitHub no responde, el estado de TODAS las versiones
 * es 'desconocido', nunca 'publicada' — una pantalla que miente en verde es peor que una
 * que no sabe.
 */
class ReleasePublicationService
{
    const CACHE_KEY = 'releases:publicacion-estado';
    const CACHE_MINUTES = 5;

    /**
     * @return array{ok:bool, error:?string, no_publicadas:int, estados: array<string,string>}
     */
    public function estado(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_MINUTES), fn () => $this->calcular());
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function calcular(): array
    {
        $versiones = Release::orderByDesc('release_date')->pluck('version');

        [$tagsGithub, $ok, $error] = $this->releasesDeGithub();

        if (!$ok) {
            return [
                'ok' => false,
                'error' => $error,
                'no_publicadas' => 0,
                'estados' => $versiones->mapWithKeys(fn ($v) => [$v => 'desconocido'])->all(),
            ];
        }

        $noPublicadas = 0;
        $estados = [];
        foreach ($versiones as $version) {
            $publicada = isset($tagsGithub[$version]);
            $estados[$version] = $publicada ? 'publicada' : 'no_publicada';
            if (!$publicada) {
                $noPublicadas++;
            }
        }

        return [
            'ok' => true,
            'error' => null,
            'no_publicadas' => $noPublicadas,
            'estados' => $estados,
        ];
    }

    /**
     * Mismo patrón que ReconcileReleasesCommand::releasesDeGithub() (item #9990669), pero
     * SOLO la parte HTTPS — a propósito no se reusa ese comando porque su cruce completo
     * también consulta `git ls-remote origin` (SSH), que es justo lo que este item pide
     * evitar.
     *
     * @return array{0: array<string,bool>, 1: bool, 2: ?string}
     */
    private function releasesDeGithub(): array
    {
        $token = config('deployment.github.token', '');
        $repo = config('deployment.github.repo', '');

        if (empty($token) || empty($repo)) {
            return [[], false, 'GITHUB_TOKEN o GITHUB_REPO no configurados en .env'];
        }

        $tags = [];
        $page = 1;

        try {
            do {
                $response = Http::withToken($token)
                    ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
                    ->timeout(15)
                    ->get("https://api.github.com/repos/{$repo}/releases", ['per_page' => 100, 'page' => $page]);

                if (!$response->successful()) {
                    return [[], false, "GitHub respondió {$response->status()}: " . $response->body()];
                }

                $batch = $response->json();
                if (!is_array($batch)) {
                    return [[], false, 'GitHub devolvió una respuesta que no es un arreglo JSON.'];
                }

                foreach ($batch as $release) {
                    $tag = $release['tag_name'] ?? null;
                    if ($tag) {
                        $tags[$tag] = true;
                    }
                }

                $page++;
            } while (count($batch) === 100 && $page <= 10);
        } catch (\Throwable $e) {
            return [[], false, 'Excepción al consultar GitHub: ' . $e->getMessage()];
        }

        return [$tags, true, null];
    }
}
