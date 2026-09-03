<?php

namespace App\Services;

use App\Modules\Addons\Marketing\Services\ClaudeApiClient;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ReleaseChangelogService
{
    // Archivos cuyo diff nunca se incluye en el contexto de IA. Sin anclas de fin de línea
    // ($): una línea real de `git diff --stat` es "archivo.pem | 1 +", nunca termina en el
    // nombre del archivo — un patrón anclado con $ nunca hace match contra esa línea (bug
    // encontrado por el test de Fase 3 del item roadmap #892: .pem/.key nunca se filtraban).
    private const SENSITIVE_PATTERNS = [
        '/\.env/i', '/\.pem/i', '/\.key/i', '/credential/i', '/secret/i',
    ];

    // [CIRCUITO-CC][REGLA] (item roadmap #434, ajustada por el #893 el 2026-09-03) — el
    // versionado excluye SOLO el "Desarrollador / Dev Tools" del sidebar: es una herramienta
    // interna de dev que jamás forma parte del historial de versiones. La Torre de control
    // (Hoja de Ruta / circuito CC) SÍ se incluye desde el #893 — decisión explícita de Irving:
    // el trabajo hecho en la Torre es trabajo real del sistema y debe aparecer en el changelog.
    // No la vuelvas a excluir sin una decisión igual de explícita. Exclusión por
    // módulo/namespace (prefijo de ruta), no archivo por archivo: cualquier archivo nuevo
    // dentro de estas carpetas queda excluido automáticamente sin tocar esta lista.
    private const EXCLUDED_PATH_PREFIXES = [
        'app/Modules/Addons/DevTools',             // Desarrollador / DevTools (backend)
        'resources/js/components/module/devtools', // Desarrollador / DevTools (frontend)
    ];

    // Commits por lote del resumen jerárquico (map-reduce, item roadmap #892 Fase 2). Un rango
    // que cabe en un solo lote usa el camino directo (callClaude), igual que antes — el
    // map-reduce solo entra en juego cuando el rango es más grande que un lote.
    private const BATCH_SIZE = 80;

    // Techo de seguridad: más de esto (≈1200 commits) ya no se procesa completo por costo/tiempo
    // de una sola request HTTP síncrona. Se avisa explícitamente en vez de truncar en silencio.
    private const MAX_BATCHES = 15;

    // Líneas de diff --stat que se conservan por lote (tras filtrar las sensibles).
    private const MAX_STAT_LINES = 60;

    public function __construct(private ClaudeApiClient $claude)
    {
    }

    /**
     * Devuelve un arreglo estructurado: ['title','summary','improvements', + metadatos de cobertura].
     * Título y resumen pueden venir vacíos si la IA no los produjo o el JSON vino mal formado;
     * en ese caso las mejoras llevan el texto crudo (nunca se pierde el contenido).
     *
     * Metadatos de cobertura (item roadmap #892 — antes el truncamiento a 40 commits era
     * silencioso): 'total_commits', 'resumidos_commits', 'desde_tag', 'truncado' y, si
     * truncado=true, 'aviso_truncamiento' con el texto listo para mostrar en la UI.
     */
    public function generate(string $newVersion): array
    {
        $git   = $this->gatherGitData($newVersion);
        $total = $git['total'];

        if ($total === 0) {
            return [
                'title'              => '',
                'summary'            => '',
                'improvements'       => 'No se encontraron commits nuevos desde la versión anterior.',
                'total_commits'      => 0,
                'resumidos_commits'  => 0,
                'desde_tag'          => $git['prev_tag'],
                'truncado'           => false,
                'aviso_truncamiento' => null,
            ];
        }

        $batches  = array_chunk($git['commit_lines'], self::BATCH_SIZE);
        $truncado = count($batches) > self::MAX_BATCHES;
        if ($truncado) {
            $batches = array_slice($batches, 0, self::MAX_BATCHES);
        }
        $resumidos = array_sum(array_map('count', $batches));
        $llamadas  = 0;

        if (count($batches) === 1) {
            // Camino directo (idéntico al comportamiento previo a #892): un solo lote, una sola
            // llamada. Cubre el caso común (releases normales, pocas decenas de commits) sin el
            // costo extra de map-reduce.
            $stat    = $this->computeBatchStat($batches[0], $git['env'], $git['base'], $git['exclude_pathspec']);
            $result  = $this->callClaude(implode("\n", $batches[0]), $stat, $newVersion);
            $llamadas = 1;
        } else {
            // Resumen jerárquico (map-reduce): un lote no cubre todo el rango, así que se resume
            // cada lote por separado (map) y luego se sintetizan esos resúmenes en el resultado
            // final (reduce). Cada lote reaplica el filtro de sensibles vía computeBatchStat()
            // (Fase 3 — el filtro NO es exclusivo del primer lote).
            $batchSummaries = [];
            foreach ($batches as $batch) {
                $batchSummaries[] = $this->summarizeBatch($batch, $git['env'], $git['base'], $git['exclude_pathspec']);
                $llamadas++;
            }
            $result = $this->reduceSummaries($batchSummaries, $newVersion);
            $llamadas++;
        }

        Log::channel('claude')->info('ReleaseChangelogService: resumen generado', [
            'version'           => $newVersion,
            'total_commits'     => $total,
            'resumidos_commits' => $resumidos,
            'lotes'             => count($batches),
            'llamadas_claude'   => $llamadas,
            'truncado'          => $truncado,
        ]);

        return array_merge($result, [
            'total_commits'      => $total,
            'resumidos_commits'  => $resumidos,
            'desde_tag'          => $git['prev_tag'],
            'truncado'           => $truncado,
            'aviso_truncamiento' => $truncado
                ? "Se resumieron {$resumidos} de {$total} commits desde " . ($git['prev_tag'] ?: '(sin tag previo)') . '; el resumen está incompleto.'
                : null,
        ]);
    }

    /**
     * Trae TODOS los commits del rango (sin tope) — el tope de cobertura se aplica después,
     * en generate(), como lotes explícitos (Fase 2), nunca como un --max-count silencioso.
     */
    private function gatherGitData(string $newVersion): array
    {
        $env  = $this->buildEnv();
        $base = base_path();

        $prevTag = $this->findPreviousTag($env, $base, $newVersion);
        $range   = $prevTag ? "{$prevTag}..HEAD" : null;
        $excludePathspec = $this->buildExcludePathspec();

        $rawLog = $this->runGit(
            $range
                ? "git log {$range} --oneline --no-merges -- . {$excludePathspec}"
                : "git log --oneline --no-merges -- . {$excludePathspec}",
            $env,
            $base
        );

        $commitLines = array_values(array_filter(
            array_map('rtrim', explode("\n", $rawLog)),
            fn(string $line) => $line !== ''
        ));

        return [
            'commit_lines'     => $commitLines,
            'total'            => count($commitLines),
            'prev_tag'         => $prevTag,
            'env'              => $env,
            'base'             => $base,
            'exclude_pathspec' => $excludePathspec,
        ];
    }

    /**
     * Pathspec de exclusión ':(exclude)ruta' para dejar fuera del changelog (git log / diff
     * --stat) al Dev Tools (item roadmap #434; la Torre de control se incluyó de vuelta en
     * el #893) — ver EXCLUDED_PATH_PREFIXES.
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

    /** Hash abreviado al inicio de una línea `git log --oneline` ("<hash> <subject>"). */
    private function extractHash(string $commitLine): string
    {
        return explode(' ', trim($commitLine), 2)[0] ?? '';
    }

    /**
     * `git diff --stat` acotado exactamente al lote (del padre del commit más viejo del lote
     * hasta el más nuevo), filtrado por SENSITIVE_PATTERNS. Punto único de verdad: tanto el
     * camino directo (1 lote) como cada iteración del map-reduce pasan por aquí — así el filtro
     * de sensibles se reaplica siempre, nunca solo en el primer lote (Fase 3).
     */
    private function computeBatchStat(array $batchLines, array $env, string $base, string $excludePathspec): string
    {
        if (empty($batchLines)) {
            return '';
        }

        $newestHash = $this->extractHash($batchLines[0]);
        $oldestHash = $this->extractHash($batchLines[count($batchLines) - 1]);

        // Si el commit más viejo del lote es la raíz del repo (sin padre), `~1` falla y runGit
        // devuelve salida vacía — no rompe el flujo, ese lote solo pierde su diff --stat.
        $rawStat = $this->runGit(
            "git diff --stat {$oldestHash}~1..{$newestHash} -- . {$excludePathspec}",
            $env,
            $base
        );

        return $this->filterSensitiveStat($rawStat);
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

    /**
     * Paso "map" del resumen jerárquico: resume UN lote de commits a texto plano (viñetas),
     * orientado al usuario final. Este texto se combina después en reduceSummaries().
     */
    private function summarizeBatch(array $batchLines, array $env, string $base, string $excludePathspec): string
    {
        $stat        = $this->computeBatchStat($batchLines, $env, $base, $excludePathspec);
        $commitsText = implode("\n", $batchLines);

        $prompt = <<<PROMPT
Eres el redactor de release notes de MegaISP, sistema de gestión para un ISP (proveedor de internet).
Estás resumiendo UN LOTE de commits que es solo una parte de una versión más grande — tu resultado
se combinará después con los resúmenes de otros lotes para redactar las notas finales, así que sé
conciso y factual.

Devuelve EXCLUSIVAMENTE una lista markdown de viñetas (sin encabezado, sin JSON, sin texto
introductorio ni de cierre) en español, orientada a los usuarios del sistema (no a desarrolladores),
con los cambios relevantes de este lote. Omite commits de tipo fix menor, chore, refactor o docs si
no impactan al usuario. Si NINGÚN commit del lote es relevante para el usuario final, responde
exactamente: (sin cambios relevantes para el usuario)

Commits de este lote:
{$commitsText}

Archivos modificados en este lote:
{$stat}
PROMPT;

        try {
            $response = $this->claude->messages([
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 500,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

            return trim($response['content'][0]['text'] ?? '');
        } catch (\Throwable $e) {
            Log::warning("ReleaseChangelogService lote error: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Paso "reduce" del resumen jerárquico: sintetiza los resúmenes de cada lote (ya generados
     * por summarizeBatch()) en el JSON final {title, summary, improvements}.
     */
    private function reduceSummaries(array $batchSummaries, string $version): array
    {
        $joined = '';
        foreach ($batchSummaries as $i => $summary) {
            $n = $i + 1;
            $joined .= "--- Lote {$n} ---\n{$summary}\n\n";
        }

        $prompt = <<<PROMPT
Eres el redactor de release notes de MegaISP, sistema de gestión para un ISP (proveedor de internet).
El historial de commits de esta versión era demasiado grande para procesarlo de una sola vez, así
que ya se dividió en lotes y cada lote ya tiene su propio resumen (abajo). Tu tarea: sintetizar esos
resúmenes por lote en las notas de versión finales, en español, orientadas a los usuarios del
sistema (no a desarrolladores). Evita repetir el mismo cambio si aparece en más de un lote.

Devuelve EXCLUSIVAMENTE un objeto JSON válido con esta forma exacta (sin texto antes ni después, sin fences):
{"title": "...", "summary": "...", "improvements": "..."}

Donde:
- "title": título corto y descriptivo de la versión (máx 60 caracteres). No incluyas la palabra "Versión" ni el número.
- "summary": 1 o 2 frases que resuman la versión para el usuario (máx 240 caracteres).
- "improvements": markdown con un encabezado h3 "Mejoras en esta versión" seguido de una lista con viñetas (máx 200 palabras).

Reglas de contenido:
- Lenguaje claro y amigable. No menciones nombres de funciones ni archivos técnicos.
- Ignora los lotes marcados "(sin cambios relevantes para el usuario)".
- Si detectas una mejora significativa, explícala en una frase corta.

Versión: {$version}

Resúmenes por lote:
{$joined}
PROMPT;

        try {
            $response = $this->claude->messages([
                'model'      => 'claude-sonnet-4-6',
                // 700 (igual que callClaude()) se quedaba corto aquí: con rangos grandes (10+
                // lotes) el texto a sintetizar es mucho mayor que el de una sola llamada directa,
                // el modelo no siempre respeta el límite de 200 palabras del prompt, y el JSON se
                // cortaba a medias → parseStructured() fallaba y todo el "improvements" quedaba
                // como texto crudo truncado (encontrado al verificar Fase 4 del item roadmap #892
                // con el rango real V1.32..HEAD, 726 commits / 10 lotes). 2048 da margen holgado
                // sin acercarse al límite de salida del modelo.
                'max_tokens' => 2048,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

            $raw = $response['content'][0]['text'] ?? '';
            return $this->parseStructured($raw);
        } catch (\Throwable $e) {
            Log::warning("ReleaseChangelogService reduce error: {$e->getMessage()}");
            throw $e;
        }
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
