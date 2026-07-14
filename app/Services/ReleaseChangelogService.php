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

    // [CIRCUITO-CC][REGLA] (item roadmap #434) — el versionado NUNCA incluye la Torre de
    // control ni el "Desarrollador / Dev Tools" del sidebar: son herramientas internas de
    // dev, jamás forman parte del historial de versiones. Exclusión por módulo/namespace
    // (prefijo de ruta), no archivo por archivo: cualquier archivo nuevo dentro de estas
    // carpetas queda excluido automáticamente sin tocar esta lista.
    private const EXCLUDED_PATH_PREFIXES = [
        'app/Modules/Addons/Roadmap',                          // Hoja de Ruta / circuito CC (backend de la Torre)
        'app/Modules/Addons/DevTools',                         // Desarrollador / DevTools (backend)
        'resources/js/components/module/releases/torre-control', // Torre de control (frontend: panorama, roadmap, terminales, integración, reporte)
        'resources/js/components/module/devtools',             // Desarrollador / DevTools (frontend)
    ];

    private const MAX_COMMITS = 40;
    private const MAX_STAT_LINES = 60;

    public function __construct(private ClaudeApiClient $claude)
    {
    }

    /**
     * Devuelve un arreglo estructurado: ['title' => ..., 'summary' => ..., 'improvements' => ...].
     * Título y resumen pueden venir vacíos si la IA no los produjo o el JSON vino mal formado;
     * en ese caso las mejoras llevan el texto crudo (nunca se pierde el contenido).
     */
    public function generate(string $newVersion): array
    {
        ['commits' => $commits, 'stat' => $stat] = $this->gatherGitData($newVersion);

        if (empty($commits)) {
            return [
                'title'        => '',
                'summary'      => '',
                'improvements' => 'No se encontraron commits nuevos desde la versión anterior.',
            ];
        }

        return $this->callClaude($commits, $stat, $newVersion);
    }

    private function gatherGitData(string $newVersion): array
    {
        $env  = $this->buildEnv();
        $base = base_path();

        $prevTag = $this->findPreviousTag($env, $base, $newVersion);

        $range = $prevTag ? "{$prevTag}..HEAD" : null;
        $excludePathspec = $this->buildExcludePathspec();

        $commits = $this->runGit(
            $range
                ? "git log {$range} --oneline --no-merges --max-count=" . self::MAX_COMMITS . " -- . {$excludePathspec}"
                : "git log --oneline --no-merges --max-count=" . self::MAX_COMMITS . " -- . {$excludePathspec}",
            $env,
            $base
        );

        $rawStat = $range
            ? $this->runGit("git diff --stat {$range} -- . {$excludePathspec}", $env, $base)
            : $this->runGit("git diff --stat HEAD~10..HEAD -- . {$excludePathspec}", $env, $base);

        $stat = $this->filterSensitiveStat($rawStat);

        return ['commits' => $commits, 'stat' => $stat];
    }

    /**
     * Pathspec de exclusión ':(exclude)ruta' para dejar fuera del changelog (git log / diff
     * --stat) a la Torre de control y al Dev Tools (item roadmap #434) — ver EXCLUDED_PATH_PREFIXES.
     */
    private function buildExcludePathspec(): string
    {
        $parts = array_map(
            fn(string $path) => escapeshellarg(":(exclude){$path}"),
            self::EXCLUDED_PATH_PREFIXES
        );

        return implode(' ', $parts);
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

    private function callClaude(string $commits, string $stat, string $version): array
    {
        $prompt = <<<PROMPT
Eres el redactor de release notes de MegaISP, sistema de gestión para un ISP (proveedor de internet).
Tu tarea: dado el historial de commits y el árbol de archivos modificados, redacta las notas de versión
en español, orientadas a los usuarios del sistema (no a desarrolladores).

Devuelve EXCLUSIVAMENTE un objeto JSON válido con esta forma exacta (sin texto antes ni después, sin fences):
{"title": "...", "summary": "...", "improvements": "..."}

Donde:
- "title": título corto y descriptivo de la versión (máx 60 caracteres). No incluyas la palabra "Versión" ni el número.
- "summary": 1 o 2 frases que resuman la versión para el usuario (máx 240 caracteres).
- "improvements": markdown con un encabezado h3 "Mejoras en esta versión" seguido de una lista con viñetas (máx 200 palabras).

Reglas de contenido:
- Lenguaje claro y amigable. No menciones nombres de funciones ni archivos técnicos.
- Omite commits de tipo fix menor, chore, refactor si no impactan al usuario.
- Si detectas una mejora significativa, explícala en una frase corta.

Versión: {$version}

Commits:
{$commits}

Archivos modificados:
{$stat}
PROMPT;

        try {
            $response = $this->claude->messages([
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 700,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

            $raw = $response['content'][0]['text'] ?? '';
            return $this->parseStructured($raw);
        } catch (\Throwable $e) {
            Log::warning("ReleaseChangelogService Claude error: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Parsea la respuesta de la IA a ['title','summary','improvements'] de forma robusta:
     * quita fences ```json, intenta json_decode, y si todo falla mete el texto crudo en
     * "improvements" (título/resumen vacíos) — nunca lanza excepción por formato.
     */
    private function parseStructured(string $raw): array
    {
        $text = trim($raw);

        // Quitar fences de código ```json ... ```
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', trim($text));
        $text = trim($text);

        $data = json_decode($text, true);

        // Si no decodificó, intentar extraer el primer objeto {...}
        if (!is_array($data) && preg_match('/\{.*\}/s', $text, $m)) {
            $data = json_decode($m[0], true);
        }

        if (is_array($data) && (isset($data['improvements']) || isset($data['title']) || isset($data['summary']))) {
            return [
                'title'        => trim((string) ($data['title'] ?? '')),
                'summary'      => trim((string) ($data['summary'] ?? '')),
                'improvements' => trim((string) ($data['improvements'] ?? '')),
            ];
        }

        // Fallback: JSON mal formado → el texto crudo va a Mejoras, título/resumen vacíos.
        return [
            'title'        => '',
            'summary'      => '',
            'improvements' => $raw,
        ];
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
