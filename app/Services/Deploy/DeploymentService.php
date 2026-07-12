<?php

namespace App\Services\Deploy;

use App\Models\DeploymentLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class DeploymentService
{
    public function run(DeploymentLog $log, string $version, string $title = ''): void
    {
        $commitMessage = trim("release: {$version}" . ($title ? " — {$title}" : ''));

        // Paso 0 — refrescar el cache de config ANTES de leer los pasos del pipeline.
        // Una edición de config/deployment.php quedaba enmascarada por un
        // bootstrap/cache/config.php stale (causó que git_add intentara agregar
        // public/mix-manifest.json, ya gitignored, y el deploy fallara). DEBE correr
        // antes de config('deployment.steps') para proteger también la corrida ACTUAL.
        $configRefresh = $this->refreshDeploymentConfig();

        $configSteps = collect(config('deployment.steps'))
            ->filter(fn($s) => $s['enabled'] ?? true)
            ->map(function ($s) use ($version, $title, $commitMessage) {
                $s['name'] = $this->interpolate($s['name'], $version, $title, $commitMessage);
                if (isset($s['command'])) {
                    $s['command'] = $this->interpolate($s['command'], $version, $title, $commitMessage);
                }
                return $s;
            })
            ->values()
            ->all();

        $initialSteps = array_map(fn($s) => [
            'key'         => $s['key'],
            'name'        => $s['name'],
            'status'      => 'pending',
            'output'      => '',
            'exit_code'   => null,
            'duration_ms' => 0,
            'ran_at'      => null,
        ], $configSteps);

        // El refresh de config corre antes del loop (ya completado): se muestra como
        // primer paso en el log para que quede auditable en la respuesta del deploy.
        array_unshift($initialSteps, $configRefresh);

        $log->update([
            'status'     => 'running',
            'steps'      => $initialSteps,
            'started_at' => now(),
        ]);

        foreach ($configSteps as $step) {
            // Skip si DEPLOY_REMOTE_URL no está definido
            if (($step['skip_if_no_remote'] ?? false) && empty(config('deployment.remote_url'))) {
                $log->updateStep($step['key'], [
                    'status'      => 'skipped',
                    'output'      => 'DEPLOY_REMOTE_URL no configurado — paso omitido.',
                    'exit_code'   => 0,
                    'duration_ms' => 0,
                    'ran_at'      => now()->toIso8601String(),
                ]);
                continue;
            }

            // Skip fuera de producción (item roadmap #245): impide que crear una Release en
            // dev dispare push/GitHub Release/deploy remoto reales.
            if (($step['skip_if_not_production'] ?? false) && !app()->environment('production')) {
                $log->updateStep($step['key'], [
                    'status'      => 'skipped',
                    'output'      => 'Entorno no-producción — paso omitido (política de release, item #245).',
                    'exit_code'   => 0,
                    'duration_ms' => 0,
                    'ran_at'      => now()->toIso8601String(),
                ]);
                continue;
            }

            // Skip si el tag ya existe localmente
            if (($step['skip_if_tag_exists'] ?? false) && $this->tagExists($version)) {
                $log->updateStep($step['key'], [
                    'status'      => 'skipped',
                    'output'      => "Tag {$version} ya existe localmente.",
                    'exit_code'   => 0,
                    'duration_ms' => 0,
                    'ran_at'      => now()->toIso8601String(),
                ]);
                continue;
            }

            // Skip del commit si no hay nada en el stage — INDEPENDIENTE DEL IDIOMA.
            // (Antes se parseaba "nothing to commit" del output, que falla si git
            //  responde en español.) `git diff --cached --quiet` → exit 0 = stage vacío.
            if (($step['skip_on_nothing_to_commit'] ?? false) && $this->nothingStaged()) {
                $log->updateStep($step['key'], [
                    'status'      => 'skipped',
                    'output'      => 'Sin cambios en el stage — commit omitido.',
                    'exit_code'   => 0,
                    'duration_ms' => 0,
                    'ran_at'      => now()->toIso8601String(),
                ]);
                Log::channel('single')->info("Deploy #{$log->id} — paso '{$step['key']}': SKIPPED (stage vacío)");
                continue;
            }

            $log->updateStep($step['key'], [
                'status' => 'running',
                'ran_at' => now()->toIso8601String(),
            ]);

            [$exitCode, $output, $durationMs] = match ($step['type'] ?? 'shell') {
                'http'           => $this->executeHttpDeploy($step, $version, $title, $log),
                'secret_check'   => $this->executeSecretCheck(),
                'staging_gate'   => $this->executeStagingGate(),
                'github_release' => $this->executeGithubRelease($step, $version, $log),
                'backup'         => $this->executeBackup($version),
                default          => $this->executeStep($step),
            };

            $success = $exitCode === 0;

            $log->updateStep($step['key'], [
                'status'      => $success ? 'success' : 'failed',
                'output'      => mb_substr(trim($output), -4000),
                'exit_code'   => $exitCode,
                'duration_ms' => $durationMs,
            ]);

            Log::channel('single')->info("Deploy #{$log->id} — '{$step['key']}': " . ($success ? 'OK' : "FAILED (exit {$exitCode})"));

            if (!$success && ($step['critical'] ?? true)) {
                $log->update([
                    'status'           => 'failed',
                    'error_message'    => "Paso «{$step['name']}» falló con código {$exitCode}.",
                    'finished_at'      => now(),
                    'duration_seconds' => (int) now()->diffInSeconds($log->started_at),
                ]);
                return;
            }
        }

        $log->update([
            'status'           => 'success',
            'finished_at'      => now(),
            'duration_seconds' => (int) now()->diffInSeconds($log->started_at),
        ]);
    }

    /**
     * Refresca el cache de configuración ANTES de que el pipeline lea sus pasos.
     *
     * Por qué un subproceso + recarga en memoria (y no un simple paso shell):
     *  - `config:cache` reconstruye bootstrap/cache/config.php en una app fresca con
     *    .env cargado → los valores env() (remote_url, github.token…) quedan correctos
     *    aunque el worker de cola haya booteado con un cache viejo.
     *  - Luego recargamos SOLO la sección 'deployment' en el proceso actual desde ese
     *    cache recién escrito, para que los command-strings y release_artifacts de ESTA
     *    corrida no usen los valores stale que el worker tenía en memoria.
     *
     * No es crítico: si algo falla, se sigue con la config en memoria y solo se avisa.
     */
    private function refreshDeploymentConfig(): array
    {
        $startedAt = microtime(true);
        $record = [
            'key'         => 'config_cache',
            'name'        => 'Refrescar configuración (config:cache)',
            'status'      => 'success',
            'output'      => '',
            'exit_code'   => 0,
            'duration_ms' => 0,
            'ran_at'      => now()->toIso8601String(),
        ];

        try {
            Artisan::call('config:cache');

            $cacheFile = base_path('bootstrap/cache/config.php');
            if (is_file($cacheFile)) {
                $fresh = require $cacheFile;
                if (is_array($fresh) && isset($fresh['deployment'])) {
                    config(['deployment' => $fresh['deployment']]);
                }
            }

            $record['output'] = 'Configuración recacheada; sección «deployment» recargada en memoria.';
        } catch (\Throwable $e) {
            $record['output'] = 'Aviso: no se pudo recachear la config (' . $e->getMessage() . '). Se usa la config en memoria.';
            Log::channel('single')->warning('Deploy — refreshDeploymentConfig falló: ' . $e->getMessage());
        }

        $record['duration_ms'] = (int) ((microtime(true) - $startedAt) * 1000);
        return $record;
    }

    /**
     * Primer paso crítico del pipeline: respalda la BD ANTES de publicar.
     * Corre en el worker (sin límite de memoria ni timeout del request web).
     * El volcado es por streaming (memoria constante). Si falla, el paso es
     * crítico → el deploy se marca fallido y no se publica.
     */
    private function executeBackup(string $version): array
    {
        $startedAt = microtime(true);

        try {
            $ok = (new \App\Services\BackupDb\BackupDbTestService())->backup($version);
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

            if (!$ok) {
                return [1, "El respaldo de la base de datos no se completó (revisa storage/logs). Versión {$version}.", $durationMs];
            }

            $zipFile = storage_path("backup_test/{$version}/{$version}.zip");
            if (!is_file($zipFile) || filesize($zipFile) === 0) {
                return [1, "El respaldo no generó un ZIP válido en {$zipFile}.", $durationMs];
            }

            $sizeMb = round(filesize($zipFile) / 1048576, 2);
            return [0, "Respaldo creado: {$zipFile} ({$sizeMb} MB).", $durationMs];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
            return [1, 'Excepción durante el respaldo: ' . $e->getMessage(), $durationMs];
        }
    }

    private function executeHttpDeploy(array $step, string $version, string $title, DeploymentLog $log): array
    {
        $remoteUrl = rtrim(config('deployment.remote_url'), '/');
        $secret    = config('deployment.webhook_secret', '');
        $startedAt = microtime(true);

        $release = $log->release;
        $payload = [
            'version'      => $version,
            'title'        => $title,
            'summary'      => $release?->summary,
            'release_date' => $release?->release_date,
        ];

        // — Paso 1: disparar el deploy remoto —
        try {
            $trigger = Http::timeout(30)
                ->withHeader('X-Deploy-Token', $secret)
                ->post("{$remoteUrl}/api/webhook/deploy", $payload);
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
            return [1, 'Error de conexión con servidor remoto: ' . $e->getMessage(), $durationMs];
        }

        if (!$trigger->successful()) {
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
            return [1, "Webhook rechazado ({$trigger->status()}): " . $trigger->body(), $durationMs];
        }

        $remoteLogId = $trigger->json('log_id');
        if (!$remoteLogId) {
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
            return [1, 'El servidor remoto no devolvió log_id. Respuesta: ' . $trigger->body(), $durationMs];
        }

        // — Paso 2: polling hasta que el deploy remoto termine —
        $maxWait    = max(60, ($step['timeout'] ?? 700) - 30);
        $deadline   = microtime(true) + $maxWait;
        $lastOutput = "Deploy remoto iniciado (log #{$remoteLogId}). Esperando resultados...";
        $pollUrl    = "{$remoteUrl}/api/webhook/deploy/{$remoteLogId}/status";

        while (microtime(true) < $deadline) {
            sleep(5);

            try {
                $poll = Http::timeout(15)
                    ->withHeader('X-Deploy-Token', $secret)
                    ->get($pollUrl);

                if (!$poll->successful()) continue;

                $data         = $poll->json();
                $remoteStatus = $data['status'] ?? 'unknown';
                $lastOutput   = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                // Actualizar output del paso en el log local (visible en el modal)
                $log->updateStep('remote_deploy', ['output' => $lastOutput]);

                if (in_array($remoteStatus, ['success', 'failed'])) {
                    $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
                    return [$remoteStatus === 'success' ? 0 : 1, $lastOutput, $durationMs];
                }
            } catch (\Throwable $e) {
                $lastOutput .= "\n[poll error: {$e->getMessage()}]";
            }
        }

        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
        return [1, "Timeout ({$maxWait}s) esperando al servidor remoto.\n{$lastOutput}", $durationMs];
    }

    private function interpolate(string $text, string $version, string $title, string $message): string
    {
        return str_replace(
            ['{version}', '{title}', '{message}'],
            [$version,    $title,    $message],
            $text
        );
    }

    private function tagExists(string $version): bool
    {
        $process = Process::fromShellCommandline("git tag -l {$version}", base_path(), $this->buildEnv());
        $process->run();
        return trim($process->getOutput()) === $version;
    }

    /**
     * ¿El stage está vacío? `git diff --cached --quiet` devuelve exit 0 si no hay nada
     * preparado para commitear. Detección independiente del idioma de git.
     */
    private function nothingStaged(): bool
    {
        $process = Process::fromShellCommandline('git diff --cached --quiet', base_path(), $this->buildEnv());
        $process->run();
        return $process->getExitCode() === 0;
    }

    private function executeSecretCheck(): array
    {
        $startedAt = microtime(true);
        $process   = Process::fromShellCommandline('git status --porcelain', base_path(), $this->buildEnv());
        $process->run();
        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

        // Patrones de archivos que NO deben entrar al commit de release
        $patterns = [
            '/\.env(\b|\.)/',               // .env, .env.local, .env.production…
            '/\.pem$/i',
            '/\.key$/i',
            '/credential/i',
            '/secret.*\.(json|yaml|yml|txt)$/i',
        ];

        // Plantillas versionadas (NO secretos): .env.example, .env.dist, .env.sample…
        // Son parte del repo a propósito (documentan las variables sin valores reales),
        // así que se EXCEPTÚAN del denylist antes de evaluarlo. Sin esto, el patrón
        // `.env*` las confundía con archivos sensibles y abortaba deploys legítimos.
        $templateWhitelist = '/\.(example|dist|sample)$/i';

        $flagged = [];
        foreach (explode("\n", trim($process->getOutput())) as $line) {
            if (!trim($line)) continue;
            $file = ltrim(substr($line, 2)); // porcelain: "XY filename"
            if (preg_match($templateWhitelist, $file)) {
                continue;
            }
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $file)) {
                    $flagged[] = trim($line);
                    break;
                }
            }
        }

        if ($flagged) {
            return [
                1,
                "ABORTADO — archivos sensibles detectados en el árbol de trabajo:\n"
                    . implode("\n", $flagged)
                    . "\n\nAgrega estos archivos a .gitignore o elimínalos antes de desplegar.",
                $durationMs,
            ];
        }

        $porcelain = trim($process->getOutput()) ?: '(árbol limpio)';
        return [0, "Sin archivos sensibles.\n{$porcelain}", $durationMs];
    }

    /**
     * Gate de staging (defensa PRIMARIA contra `git add -A`). Allowlist: el release solo
     * puede tocar los artefactos de build de Mix (config('deployment.release_artifacts')).
     * Si el working tree tiene CUALQUIER archivo cambiado fuera del allowlist → aborta y
     * los lista (commitéalos o stashéalos antes de desplegar). El denylist de secretos
     * (executeSecretCheck) sigue como 2ª capa, escaneando todo incluso dentro del allowlist.
     */
    private function executeStagingGate(): array
    {
        $startedAt = microtime(true);
        $process   = Process::fromShellCommandline('git status --porcelain', base_path(), $this->buildEnv());
        $process->run();
        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

        $allowlist = config('deployment.release_artifacts', []);

        $offenders = [];
        foreach (explode("\n", trim($process->getOutput())) as $line) {
            if (!trim($line)) continue;
            $file = $this->porcelainPath($line);
            if (!$this->isAllowedArtifact($file, $allowlist)) {
                $offenders[] = trim($line);
            }
        }

        if ($offenders) {
            return [
                1,
                "ABORTADO — hay cambios sin relación con el release (fuera del allowlist de artefactos):\n"
                    . implode("\n", $offenders)
                    . "\n\nCommitéalos o stashéalos antes de desplegar. El release solo puede tocar: "
                    . implode(', ', $allowlist),
                $durationMs,
            ];
        }

        $porcelain = trim($process->getOutput()) ?: '(árbol limpio)';
        return [0, "Staging válido — solo artefactos de build.\n{$porcelain}", $durationMs];
    }

    /** Extrae la ruta de una línea porcelain ("XY ruta"; en renames usa el destino). */
    private function porcelainPath(string $line): string
    {
        $path = ltrim(substr($line, 2));
        if (str_contains($path, ' -> ')) {
            $path = substr($path, strpos($path, ' -> ') + 4); // destino del rename
        }
        return trim($path, '"'); // git entrecomilla rutas con caracteres especiales
    }

    /** ¿La ruta cae dentro de alguna entrada del allowlist (archivo exacto o carpeta)? */
    private function isAllowedArtifact(string $file, array $allowlist): bool
    {
        foreach ($allowlist as $allowed) {
            $allowed = rtrim($allowed, '/');
            if ($file === $allowed || str_starts_with($file, $allowed . '/')) {
                return true;
            }
        }
        return false;
    }

    private function executeGithubRelease(array $step, string $version, DeploymentLog $log): array
    {
        $startedAt = microtime(true);
        $token     = config('deployment.github.token', '');
        $repo      = config('deployment.github.repo', '');

        if (empty($token) || empty($repo)) {
            $msg = 'GITHUB_TOKEN o GITHUB_REPO no configurados — GitHub Release omitido.';
            Log::channel('single')->warning("Deploy #{$log->id} — github_release: {$msg}");
            return [0, $msg, 0];
        }

        $release = $log->release;

        // Construir el cuerpo: descriptions manuales + summary del release
        $body  = '';
        if ($release) {
            $descriptions = \App\Models\ReleaseDescription::where('release_id', $release->id)
                ->orderBy('id')
                ->get();

            foreach ($descriptions as $desc) {
                if ($desc->title) {
                    $body .= "### {$desc->title}\n";
                }
                if ($desc->description) {
                    $body .= strip_tags(html_entity_decode($desc->description)) . "\n\n";
                }
            }

            if ($release->summary && !$body) {
                $body = $release->summary;
            }
        }

        $body = trim($body) ?: "Versión {$version}";

        try {
            $apiUrl = "https://api.github.com/repos/{$repo}/releases/by_tag/{$version}";
            $existing = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
                ->get($apiUrl);

            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

            if ($existing->successful()) {
                // Actualiza el release existente
                $releaseId = $existing->json('id');
                $response  = Http::withToken($token)
                    ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
                    ->patch("https://api.github.com/repos/{$repo}/releases/{$releaseId}", [
                        'name' => $release?->title ? "{$version} — {$release->title}" : $version,
                        'body' => $body,
                    ]);
                $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
                if ($response->successful()) {
                    return [0, "GitHub Release actualizado: " . $response->json('html_url'), $durationMs];
                }
                return [0, "No se pudo actualizar el GitHub Release ({$response->status()}): " . $response->body(), $durationMs];
            }

            // Crea un nuevo GitHub Release
            $response = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
                ->post("https://api.github.com/repos/{$repo}/releases", [
                    'tag_name'         => $version,
                    'target_commitish' => 'main',
                    'name'             => $release?->title ? "{$version} — {$release->title}" : $version,
                    'body'             => $body,
                    'draft'            => false,
                    'prerelease'       => false,
                ]);

            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

            if ($response->successful()) {
                Log::channel('single')->info("Deploy #{$log->id} — GitHub Release creado: " . $response->json('html_url'));
                return [0, "GitHub Release creado: " . $response->json('html_url'), $durationMs];
            }

            $msg = "GitHub Releases API ({$response->status()}): " . $response->body();
            Log::channel('single')->warning("Deploy #{$log->id} — github_release falló (no crítico): {$msg}");
            // Salida 0: no crítico, el publish no debe romperse por esto
            return [0, $msg, $durationMs];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
            $msg = 'Excepción al publicar GitHub Release: ' . $e->getMessage();
            Log::channel('single')->warning("Deploy #{$log->id} — github_release excepción: {$msg}");
            return [0, $msg, $durationMs];
        }
    }

    private function executeStep(array $step): array
    {
        $output    = '';
        $exitCode  = 0;
        $startedAt = microtime(true);

        try {
            $process = Process::fromShellCommandline(
                $step['command'],
                base_path(),
                $this->buildEnv(),
                null,
                $step['timeout'] ?? 60
            );

            $process->run(function ($type, $buffer) use (&$output) {
                $output .= $buffer;
            });

            $exitCode = $process->getExitCode() ?? 0;
        } catch (\Throwable $e) {
            $exitCode = 1;
            $output   = $e->getMessage();
        }

        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

        return [$exitCode, $output, $durationMs];
    }

    private function buildEnv(): array
    {
        $gitConfig  = config('deployment.git', []);
        $passphrase = env('SSH_KEY_PASSPHRASE', '');

        // HOME = home del usuario que corre el worker (no hardcodeado a /root), para que
        // git y ssh encuentren ~/.gitconfig, ~/.ssh/config y la llave de push de ese usuario.
        // En opción A el worker de 'deploy' corre como meganet → usa /home/meganet.
        $home = getenv('HOME')
            ?: (function_exists('posix_getpwuid') && function_exists('posix_getuid')
                ? (posix_getpwuid(posix_getuid())['dir'] ?? '/root')
                : '/root');

        $env = [
            'PATH'               => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME'               => $home,
            // Fuerza salida de git en inglés (C locale): el parseo del pipeline es
            // predecible sin importar el idioma del sistema (el server responde en español).
            'LC_ALL'             => 'C',
            'LANG'               => 'C',
            'COMPOSER_HOME'      => sys_get_temp_dir() . '/composer',
            // Permite que el proceso PHP opere git aunque el dueño del directorio sea distinto
            'GIT_CONFIG_COUNT'   => '1',
            'GIT_CONFIG_KEY_0'   => 'safe.directory',
            'GIT_CONFIG_VALUE_0' => base_path(),
            // Identidad git para el commit automático
            'GIT_AUTHOR_NAME'    => $gitConfig['author_name']  ?? 'MegaISP Release',
            'GIT_AUTHOR_EMAIL'   => $gitConfig['author_email'] ?? 'releases@meganet.com',
            'GIT_COMMITTER_NAME' => $gitConfig['author_name']  ?? 'MegaISP Release',
            'GIT_COMMITTER_EMAIL'=> $gitConfig['author_email'] ?? 'releases@meganet.com',
        ];

        // Si la llave SSH tiene passphrase, crea un script SSH_ASKPASS temporal
        // para que SSH lo use en lugar de pedir input interactivo
        if (!empty($passphrase)) {
            $askPassScript = sys_get_temp_dir() . '/ssh_askpass_deploy.sh';
            file_put_contents($askPassScript, "#!/bin/sh\necho " . escapeshellarg($passphrase) . "\n");
            chmod($askPassScript, 0700);

            $env['SSH_ASKPASS']         = $askPassScript;
            $env['SSH_ASKPASS_REQUIRE'] = 'force';
            $env['DISPLAY']             = '';
        }

        return $env;
    }
}
