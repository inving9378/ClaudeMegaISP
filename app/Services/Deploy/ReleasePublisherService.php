<?php

namespace App\Services\Deploy;

use App\Models\Release;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * ReleasePublisher atómico (item roadmap #9990674, F3 de la épica #9990668): emitir y
 * publicar una versión son UN SOLO ACTO. Falla cerrado — si un paso truena a mitad de
 * camino, deshace lo ya alcanzado (compensación) en vez de dejar una versión fantasma
 * (tag sin Release, o Release sin fila `releases` actualizada).
 *
 * Pasos (spec del item, sin desviación):
 *   0. Pre-vuelo (ReleasePreflightService, F2 #9990673) — si el veredicto es 'rojo', ABORTA
 *      sin tocar nada (ni push, ni tag, ni GitHub).
 *   1. push de los commits pendientes a origin/main.
 *   2. tag anotado + push del tag a origin.
 *   3. GitHub Release por API (crea o actualiza si ya existiera).
 *   4. fila en `releases`: github_release_id + published_at + estado_publicacion=publicada.
 *
 * COMPENSACIÓN — si falla en 2/3/4: borra el tag remoto (si llegó a empujarse), borra el tag
 * local (si lo creó este acto), y despublica el GitHub Release (si llegó a crearse) antes de
 * marcar `estado_publicacion=fallida`. Nunca queda una versión a medias.
 *
 * Sólo corre en la instancia PUBLICADORA (`config('deployment.publisher')`, mismo gate que
 * `skip_if_not_publisher` del pipeline clásico, item #530) — publicar código/Release es
 * trabajo exclusivo del publicador.
 */
class ReleasePublisherService
{
    public function __construct(
        private ReleasePreflightService $preflight,
        private DeploymentService $deploy,
    ) {
    }

    /**
     * @return array{ok:bool,version:string,failed_at:?string,message:?string,github_release_id:?int,html_url:?string,steps:array}
     */
    public function publish(Release $release, ?string $branch = null): array
    {
        $version = $release->version;
        $steps   = [];

        if (config('deployment.publisher', false) !== true) {
            return $this->fail($release, 'guard_publicador', 'Esta instancia no es la publicadora (deployment.publisher=false) — nada que compensar, no se tocó nada.', $steps);
        }

        // Paso 0 — pre-vuelo BLOQUEANTE (a diferencia de F2, que sólo reporta). Un veredicto
        // 'amarillo' (warnings) no frena; sólo 'rojo' (algún check en 'fail') aborta.
        try {
            $report = $this->preflight->evaluate($version, $branch);
        } catch (Throwable $e) {
            return $this->fail($release, 'preflight', 'El pre-vuelo lanzó una excepción, se aborta por precaución: ' . $e->getMessage(), $steps);
        }
        $steps['preflight'] = $report;
        if (($report['veredicto'] ?? 'rojo') === 'rojo') {
            return $this->fail($release, 'preflight', 'Pre-vuelo en ROJO — se aborta sin crear nada.', $steps);
        }

        $env  = $this->gitEnv();
        $base = base_path();

        // Paso 1 — push de commits.
        $push = $this->runGit('git push origin main', $env, $base, 180);
        $steps['push_commits'] = $this->stepDetail($push);
        if (!$push->isSuccessful()) {
            return $this->fail($release, 'push_commits', 'No se pudo empujar los commits a origin/main: ' . trim($push->getErrorOutput()), $steps);
        }

        // Paso 2 — tag anotado + push del tag.
        $tagCreatedHere = false;
        if (!$this->tagExistsLocally($version, $env, $base)) {
            $tagCreate = $this->runGit('git tag -a ' . escapeshellarg($version) . ' -m ' . escapeshellarg("Release {$version}"), $env, $base, 15);
            $steps['tag_create'] = $this->stepDetail($tagCreate);
            if (!$tagCreate->isSuccessful()) {
                return $this->fail($release, 'tag_create', 'No se pudo crear el tag anotado: ' . trim($tagCreate->getErrorOutput()), $steps);
            }
            $tagCreatedHere = true;
        }

        $tagPush = $this->runGit('git push origin ' . escapeshellarg("refs/tags/{$version}"), $env, $base, 60);
        $steps['tag_push'] = $this->stepDetail($tagPush);
        if (!$tagPush->isSuccessful()) {
            $this->compensarTag($tagCreatedHere, false, $version, $env, $base);
            return $this->fail($release, 'tag_push', 'No se pudo empujar el tag a origin (compensado): ' . trim($tagPush->getErrorOutput()), $steps);
        }

        // Paso 3 — GitHub Release por API.
        [$ghOk, $ghId, $ghUrl, $ghMsg] = $this->createGithubRelease($release, $version);
        $steps['github_release'] = ['ok' => $ghOk, 'detail' => $ghMsg, 'html_url' => $ghUrl];
        if (!$ghOk) {
            $this->compensarTag($tagCreatedHere, true, $version, $env, $base);
            return $this->fail($release, 'github_release', "GitHub Release falló (tag revertido): {$ghMsg}", $steps);
        }

        // Paso 4 — fila en releases.
        try {
            $release->forceFill([
                'github_release_id'  => $ghId,
                'published_at'       => now(),
                'estado_publicacion' => 'publicada',
            ])->save();
        } catch (Throwable $e) {
            $this->deleteGithubRelease((int) $ghId);
            $this->compensarTag($tagCreatedHere, true, $version, $env, $base);
            return $this->fail($release, 'db_update', 'No se pudo actualizar la fila releases (GitHub Release y tag revertidos): ' . $e->getMessage(), $steps);
        }

        return [
            'ok'                => true,
            'version'           => $version,
            'failed_at'         => null,
            'message'           => null,
            'github_release_id' => $ghId,
            'html_url'          => $ghUrl,
            'steps'             => $steps,
        ];
    }

    /**
     * Compensación: deshace lo alcanzado. `$remoteAlreadyPushed` distingue si hace falta
     * borrar el tag DEL REMOTO (paso 2 completo) o sólo el local (paso 2 truena antes del push).
     */
    private function compensarTag(bool $tagCreatedHere, bool $remoteAlreadyPushed, string $version, array $env, string $base): void
    {
        if ($remoteAlreadyPushed) {
            $del = $this->runGit('git push origin --delete ' . escapeshellarg("refs/tags/{$version}"), $env, $base, 30);
            if (!$del->isSuccessful()) {
                Log::channel('single')->error("ReleasePublisherService: compensación falló al borrar el tag remoto {$version}: " . trim($del->getErrorOutput()));
            }
        }
        if ($tagCreatedHere) {
            $del = $this->runGit('git tag -d ' . escapeshellarg($version), $env, $base, 10);
            if (!$del->isSuccessful()) {
                Log::channel('single')->error("ReleasePublisherService: compensación falló al borrar el tag local {$version}: " . trim($del->getErrorOutput()));
            }
        }
    }

    /** @return array{0:bool,1:?int,2:?string,3:string} [ok, github_release_id, html_url, mensaje] */
    private function createGithubRelease(Release $release, string $version): array
    {
        $token = config('deployment.github.token', '');
        $repo  = config('deployment.github.repo', '');

        if (empty($token) || empty($repo)) {
            return [false, null, null, 'deployment.github.token o deployment.github.repo están vacíos.'];
        }

        $body = $this->deploy->buildReleaseBody($release, $version);

        try {
            $response = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
                ->timeout(20)
                ->post("https://api.github.com/repos/{$repo}/releases", [
                    'tag_name'         => $version,
                    'target_commitish' => 'main',
                    'name'             => $release->title ? "{$version} — {$release->title}" : $version,
                    'body'             => $body,
                    'draft'            => false,
                    'prerelease'       => false,
                ]);

            if ($response->successful()) {
                return [true, (int) $response->json('id'), (string) $response->json('html_url'), 'GitHub Release creado.'];
            }

            return [false, null, null, "GitHub Releases API ({$response->status()}): " . $response->body()];
        } catch (Throwable $e) {
            return [false, null, null, 'Excepción al crear el GitHub Release: ' . $e->getMessage()];
        }
    }

    private function deleteGithubRelease(?int $githubReleaseId): void
    {
        if (!$githubReleaseId) {
            return;
        }

        $token = config('deployment.github.token', '');
        $repo  = config('deployment.github.repo', '');
        if (empty($token) || empty($repo)) {
            return;
        }

        try {
            Http::withToken($token)
                ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
                ->timeout(20)
                ->delete("https://api.github.com/repos/{$repo}/releases/{$githubReleaseId}");
        } catch (Throwable $e) {
            Log::channel('single')->error("ReleasePublisherService: compensación falló al despublicar el GitHub Release #{$githubReleaseId}: " . $e->getMessage());
        }
    }

    private function fail(Release $release, string $atStep, string $message, array $steps): array
    {
        try {
            $release->forceFill(['estado_publicacion' => 'fallida'])->save();
        } catch (Throwable $e) {
            Log::channel('single')->error("ReleasePublisherService: no se pudo marcar estado_publicacion=fallida para {$release->version}: " . $e->getMessage());
        }

        Log::channel('single')->warning("ReleasePublisherService: publicación de {$release->version} falló en «{$atStep}»: {$message}");

        return [
            'ok'                => false,
            'version'           => $release->version,
            'failed_at'         => $atStep,
            'message'           => $message,
            'github_release_id' => null,
            'html_url'          => null,
            'steps'             => $steps,
        ];
    }

    private function tagExistsLocally(string $version, array $env, string $base): bool
    {
        $process = $this->runGit('git tag -l ' . escapeshellarg($version), $env, $base, 10);
        return trim($process->getOutput()) === $version;
    }

    private function stepDetail(Process $process): array
    {
        return [
            'exit_code' => $process->getExitCode(),
            'output'    => trim($process->getOutput()),
            'error'     => trim($process->getErrorOutput()),
        ];
    }

    private function runGit(string $cmd, array $env, string $base, int $timeout): Process
    {
        $process = Process::fromShellCommandline($cmd, $base, $env, null, $timeout);
        $process->run();
        return $process;
    }

    /** Mismo patrón que ReleasePreflightService::gitEnv() / DeploymentService::buildEnv() — copiado a propósito. */
    private function gitEnv(): array
    {
        $home = getenv('HOME')
            ?: (function_exists('posix_getpwuid') && function_exists('posix_getuid')
                ? (posix_getpwuid(posix_getuid())['dir'] ?? '/root')
                : '/root');

        return [
            'PATH'               => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME'               => $home,
            'LC_ALL'             => 'C',
            'LANG'               => 'C',
            'GIT_CONFIG_COUNT'   => '1',
            'GIT_CONFIG_KEY_0'   => 'safe.directory',
            'GIT_CONFIG_VALUE_0' => base_path(),
        ];
    }
}
