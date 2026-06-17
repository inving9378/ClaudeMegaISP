<?php

namespace App\Services;

use App\Modules\Addons\Marketing\Services\ClaudeApiClient;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ReleaseChangelogService
{
    // Archivos cuyo diff nunca se incluye en el contexto de IA
    private const SENSITIVE_PATTERNS = [
        '/\.env/i', '/\.pem$/i', '/\.key$/i', '/credential/i', '/secret/i',
    ];

    private const MAX_COMMITS = 40;
    private const MAX_STAT_LINES = 60;

    public function __construct(private ClaudeApiClient $claude)
    {
    }

    public function generate(string $newVersion): string
    {
        ['commits' => $commits, 'stat' => $stat] = $this->gatherGitData($newVersion);

        if (empty($commits)) {
            return 'No se encontraron commits nuevos desde la versión anterior.';
        }

        return $this->callClaude($commits, $stat, $newVersion);
    }

    private function gatherGitData(string $newVersion): array
    {
        $env  = $this->buildEnv();
        $base = base_path();

        $prevTag = $this->findPreviousTag($env, $base, $newVersion);

        $range = $prevTag ? "{$prevTag}..HEAD" : null;

        $commits = $this->runGit(
            $range
                ? "git log {$range} --oneline --no-merges --max-count=" . self::MAX_COMMITS
                : "git log --oneline --no-merges --max-count=" . self::MAX_COMMITS,
            $env,
            $base
        );

        $rawStat = $range
            ? $this->runGit("git diff --stat {$range}", $env, $base)
            : $this->runGit("git diff --stat HEAD~10..HEAD", $env, $base);

        $stat = $this->filterSensitiveStat($rawStat);

        return ['commits' => $commits, 'stat' => $stat];
    }

    private function findPreviousTag(array $env, string $base, string $newVersion): ?string
    {
        $output = $this->runGit('git tag --sort=-version:refname', $env, $base);
        $tags   = array_filter(explode("\n", trim($output)));
        $tags   = array_values(array_filter($tags, fn($t) => $t !== $newVersion));
        return $tags[0] ?? null;
    }

    private function filterSensitiveStat(string $stat): string
    {
        $lines = explode("\n", $stat);
        $safe  = [];
        $count = 0;

        foreach ($lines as $line) {
            // Siempre incluir la línea de resumen final (N files changed…)
            if (str_contains($line, 'files changed') || str_contains($line, 'file changed')) {
                $safe[] = $line;
                continue;
            }
            if ($count >= self::MAX_STAT_LINES) continue;

            $sensitive = false;
            foreach (self::SENSITIVE_PATTERNS as $pattern) {
                if (preg_match($pattern, $line)) {
                    $sensitive = true;
                    break;
                }
            }
            if (!$sensitive) {
                $safe[] = $line;
                $count++;
            }
        }

        return implode("\n", $safe);
    }

    private function callClaude(string $commits, string $stat, string $version): string
    {
        $prompt = <<<PROMPT
Eres el redactor de release notes de MegaISP, sistema de gestión para un ISP (proveedor de internet).
Tu tarea: dado el historial de commits y el árbol de archivos modificados, produce un resumen de mejoras
en español, orientado a los usuarios del sistema (no a desarrolladores).

Reglas:
- Máximo 200 palabras.
- Formato markdown: encabezado h3 "Mejoras en esta versión", seguido de una lista con viñetas.
- Usa lenguaje claro y amigable. No menciones nombres de funciones ni archivos técnicos.
- Omite commits de tipo fix menor, chore, refactor si no impactan al usuario.
- Si detectas una mejora significativa, explícala en una frase corta.

Versión: {$version}

Commits:
{$commits}

Archivos modificados:
{$stat}

Escribe SOLO el resumen, sin explicaciones previas ni cierre.
PROMPT;

        try {
            $response = $this->claude->messages([
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 512,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

            return $response['content'][0]['text'] ?? 'No se pudo generar el resumen.';
        } catch (\Throwable $e) {
            Log::warning("ReleaseChangelogService Claude error: {$e->getMessage()}");
            throw $e;
        }
    }

    private function runGit(string $command, array $env, string $base): string
    {
        $process = Process::fromShellCommandline($command, $base, $env, null, 30);
        $process->run();
        return $process->getOutput();
    }

    private function buildEnv(): array
    {
        return [
            'PATH'               => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME'               => '/root',
            'GIT_CONFIG_COUNT'   => '1',
            'GIT_CONFIG_KEY_0'   => 'safe.directory',
            'GIT_CONFIG_VALUE_0' => base_path(),
        ];
    }
}
