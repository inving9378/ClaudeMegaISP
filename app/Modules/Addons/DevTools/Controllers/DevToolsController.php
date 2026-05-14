<?php

namespace App\Modules\Addons\DevTools\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\ModuleManager\Models\ModuleRegistry;
use App\Modules\Core\ModuleManager\Services\ModuleManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class DevToolsController extends Controller
{
    /** Mismo default que el resto de la app — IAChatController y ModuleManager. */
    private const CLAUDE_MODEL_DEFAULT = 'claude-sonnet-4-6';
    private const CLAUDE_MAX_TOKENS = 2048;

    /**
     * Devuelve la página standalone /devtools. Solamente accesible a
     * usuarios con role:DESARROLLADOR — gateado por middleware en
     * routes.php; aquí actuamos como segunda red de seguridad.
     */
    public function index()
    {
        if (! $this->isAuthorized()) {
            return redirect('/home');
        }

        return view('addon-devtools::index', [
            'ttydUrl' => env('TTYD_URL', 'http://127.0.0.1:7681'),
            'csrfToken' => csrf_token(),
        ]);
    }

    /**
     * GET /devtools/context — devuelve el contexto que se inyecta en cada
     * llamada al chat: CLAUDE.md, rama actual, últimos 5 commits y módulos
     * activos. Útil para que el frontend muestre un resumen de lo que
     * Claude está viendo y para auditoría.
     */
    public function context(): JsonResponse
    {
        if (! $this->isAuthorized()) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        return response()->json($this->gatherContext());
    }

    /**
     * Endpoint de chat para el panel izquierdo. Stateless: el frontend envía
     * el historial completo. Sólo DESARROLLADOR puede invocarlo.
     *
     * Inyecta el contexto del proyecto (CLAUDE.md + estado git + módulos
     * activos) como segundo bloque del system prompt, marcado con
     * cache_control=ephemeral para que las llamadas subsiguientes dentro
     * de ~5 min sólo paguen ~10% del costo de input por ese bloque.
     */
    public function chat(Request $request): JsonResponse
    {
        if (! $this->isAuthorized()) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $apiKey = env('CLAUDE_API_KEY', '');
        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'error' => 'CLAUDE_API_KEY no configurada en .env',
            ], 500);
        }

        $baseSystem = "Eres un asistente experto en desarrollo de MegaISP (Laravel 10 + Vue 3 + Quasar). "
            . "Ayudas a Irving a programar, migrar módulos y resolver problemas del sistema. "
            . "Responde en español, sé conciso y técnico. Cuando propongas comandos shell o "
            . "código, márcalo con bloques markdown. Tienes acceso al contexto actual del proyecto "
            . "en el segundo bloque del system prompt — úsalo para referencias precisas a paths, "
            . "convenciones y estado del repo.";

        $messages = [];
        foreach ($request->input('history', []) as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';
            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }
        $userMsg = trim((string) $request->input('message', ''));
        if ($userMsg === '') {
            return response()->json(['success' => false, 'error' => 'Mensaje vacío'], 422);
        }
        $messages[] = ['role' => 'user', 'content' => $userMsg];

        try {
            $context = $this->gatherContext();
            $systemBlocks = [
                ['type' => 'text', 'text' => $baseSystem],
                [
                    'type' => 'text',
                    'text' => $this->formatContextForPrompt($context),
                    'cache_control' => ['type' => 'ephemeral'],
                ],
            ];

            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model' => env('CLAUDE_MODEL', self::CLAUDE_MODEL_DEFAULT),
                'max_tokens' => self::CLAUDE_MAX_TOKENS,
                'system' => $systemBlocks,
                'messages' => $messages,
            ]);

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Claude API respondió ' . $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ], 502);
            }

            $payload = $response->json();
            $text = $payload['content'][0]['text'] ?? '';
            $usage = $payload['usage'] ?? [];

            return response()->json([
                'success' => true,
                'reply' => $text,
                'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
                'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
                'cache_creation_input_tokens' => (int) ($usage['cache_creation_input_tokens'] ?? 0),
                'cache_read_input_tokens' => (int) ($usage['cache_read_input_tokens'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function isAuthorized(): bool
    {
        return Auth::check() && Auth::user()->hasRole('DESARROLLADOR');
    }

    /**
     * Junta todo el contexto del proyecto. Usado por context() (JSON) y por
     * chat() (texto formateado en el system prompt).
     */
    private function gatherContext(): array
    {
        return [
            'claude_md' => $this->readClaudeMd(),
            'branch' => $this->gitBranch(),
            'recent_commits' => $this->gitRecentCommits(5),
            'active_modules' => $this->activeModuleSlugs(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function readClaudeMd(): string
    {
        $path = base_path('CLAUDE.md');
        if (! is_file($path)) {
            return '';
        }
        $content = @file_get_contents($path);
        return is_string($content) ? $content : '';
    }

    private function gitBranch(): string
    {
        $result = Process::path(base_path())->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
        return $result->successful() ? trim($result->output()) : 'unknown';
    }

    /**
     * Devuelve los últimos N commits como array de strings "hash subject".
     */
    private function gitRecentCommits(int $count = 5): array
    {
        $result = Process::path(base_path())
            ->run(['git', 'log', "-{$count}", '--format=%h %s']);
        if (! $result->successful()) {
            return [];
        }
        $lines = preg_split('/\r?\n/', trim($result->output()));
        return array_values(array_filter($lines, fn ($l) => $l !== ''));
    }

    /**
     * Lista de slugs activos. Si module_registry está vacío (p. ej. en una
     * instalación fresca), cae a la lista descubierta por el ModuleManager
     * para no devolver vacío en el contexto.
     */
    private function activeModuleSlugs(): array
    {
        try {
            $fromRegistry = ModuleRegistry::query()
                ->where('active', true)
                ->orderBy('slug')
                ->pluck('slug')
                ->all();
            if (! empty($fromRegistry)) {
                return $fromRegistry;
            }
        } catch (\Throwable $e) {
            // tabla aún no migrada o conexión caída — cae al discover
        }

        return array_values(array_filter(array_map(
            fn ($m) => $m['slug'] ?? null,
            app(ModuleManagerService::class)->manifests()
        )));
    }

    /**
     * Convierte el contexto en el bloque de texto que se cachea en el
     * system prompt.
     */
    private function formatContextForPrompt(array $ctx): string
    {
        $commits = empty($ctx['recent_commits'])
            ? '  (sin historial git disponible)'
            : '  - ' . implode("\n  - ", $ctx['recent_commits']);
        $modules = empty($ctx['active_modules'])
            ? '  (ninguno)'
            : '  - ' . implode("\n  - ", $ctx['active_modules']);

        $claudeMd = $ctx['claude_md'] !== ''
            ? $ctx['claude_md']
            : '(CLAUDE.md no encontrado en la raíz del proyecto)';

        return <<<TXT
# Contexto del proyecto (auto-inyectado por DevTools)

## Estado actual
- Rama git: {$ctx['branch']}
- Últimos commits:
{$commits}
- Módulos activos:
{$modules}

## CLAUDE.md
{$claudeMd}
TXT;
    }
}
