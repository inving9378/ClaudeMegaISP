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

        $baseSystem = "Eres el asistente de desarrollo de MegaISP (Sistema Medussa).\n"
            . "Conoces el proyecto completo gracias a la memoria persistente del sistema.\n\n"
            . "REGLAS:\n"
            . "- Responde siempre en español.\n"
            . "- Usa tablas para comparar opciones.\n"
            . "- Genera prompts listos para Claude Code cuando se requieran cambios de código.\n"
            . "- Nunca repitas pasos ya validados.\n"
            . "- Commits siempre selectivos por scope, nunca `git add -A`.\n"
            . "- Arquitectura modular es prioridad sobre todo.\n"
            . "- Ante cualquier duda técnica, primero diagnostica con `ls`/`cat`/`grep` antes de modificar.\n"
            . "- Cuando propongas comandos shell o código, márcalo con bloques markdown.\n\n"
            . "El desarrollador es Irving — visual, directo, prefiere métricas concretas y respuestas sin relleno.\n"
            . "Tienes acceso al contexto actual del proyecto en el segundo bloque del system prompt — "
            . "úsalo para referencias precisas a paths, convenciones y estado del repo.";

        $messages = [];
        foreach ($request->input('history', []) as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';
            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }
        $userMsg = trim((string) $request->input('message', ''));
        $attachments = $request->input('attachments', []);
        if ($userMsg === '' && empty($attachments)) {
            return response()->json(['success' => false, 'error' => 'Mensaje vacío'], 422);
        }

        // Si hay attachments, transformar el último mensaje user a content blocks
        // multimodales (imagen=base64 vision; archivos texto=text con cita).
        if (! empty($attachments)) {
            $contentBlocks = $this->buildAttachmentBlocks($attachments);
            // Bloque de texto del usuario al final — Claude vision sugiere
            // poner imágenes antes de la pregunta para mejor calidad.
            if ($userMsg !== '') {
                $contentBlocks[] = ['type' => 'text', 'text' => $userMsg];
            } elseif (empty($contentBlocks)) {
                // Sin texto ni bloques válidos → cae a mensaje plano vacío
                $messages[] = ['role' => 'user', 'content' => '(adjunto sin contenido extraíble)'];
            }
            if (! empty($contentBlocks)) {
                $messages[] = ['role' => 'user', 'content' => $contentBlocks];
            }
        } else {
            $messages[] = ['role' => 'user', 'content' => $userMsg];
        }

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

    /**
     * GET /devtools/nav-items — devuelve la lista de items principales del
     * sistema filtrada por los permisos del usuario actual, para alimentar
     * la columna sidebar del DevtoolsPanel (que vive en una página
     * master-without-nav y necesita su propio menú).
     *
     * Lista hardcoded — la fidelidad con el sidebar real del sistema no es
     * objetivo aquí; es una navegación de atajos para el usuario DESARROLLADOR.
     */
    public function navItems(): JsonResponse
    {
        $user = auth()->user();

        $items = [
            [
                'label'      => 'Dashboard',
                'icon'       => 'fas fa-tachometer-alt',
                'route'      => '/dashboard',
                'permission' => 'dashboard_view_dashboard',
                'children'   => [],
            ],
            [
                'label'      => 'Clientes',
                'icon'       => 'fas fa-users',
                'route'      => '/clientes',
                'permission' => 'client_view_client',
                'children'   => [
                    ['label' => 'Lista',   'route' => '/clientes'],
                    ['label' => 'Morosos', 'route' => '/clientes/morosos'],
                ],
            ],
            [
                'label'      => 'Finanzas',
                'icon'       => 'fas fa-dollar-sign',
                'route'      => '/finanzas',
                'permission' => 'finance_view_finance',
                'children'   => [
                    ['label' => 'Pagos',        'route' => '/finanzas/pagos'],
                    ['label' => 'Facturas',     'route' => '/finanzas/facturas'],
                    ['label' => 'Contabilidad', 'route' => '/finanzas/contabilidad'],
                ],
            ],
            [
                'label'      => 'Red',
                'icon'       => 'fas fa-network-wired',
                'route'      => '/red',
                'permission' => 'network_view_network',
                'children'   => [
                    ['label' => 'Routers', 'route' => '/red/routers'],
                    ['label' => 'OLTs',    'route' => '/red/olts'],
                    ['label' => 'IPs',     'route' => '/red/ips'],
                ],
            ],
            [
                'label'      => 'Tickets',
                'icon'       => 'fas fa-ticket-alt',
                'route'      => '/tickets',
                'permission' => 'ticket_view_ticket',
                'children'   => [],
            ],
            [
                'label'      => 'Inventario',
                'icon'       => 'fas fa-boxes',
                'route'      => '/inventario',
                'permission' => 'inventory_view_inventory',
                'children'   => [],
            ],
            [
                'label'      => 'Mapas',
                'icon'       => 'fas fa-map-marked-alt',
                'route'      => '/mapas',
                'permission' => 'maps_view_maps',
                'children'   => [],
            ],
            [
                'label'      => 'Reportes',
                'icon'       => 'fas fa-chart-bar',
                'route'      => '/reportes',
                'permission' => 'report_view_report',
                'children'   => [],
            ],
            [
                'label'      => 'IA',
                'icon'       => 'fas fa-robot',
                'route'      => '/ia',
                'permission' => 'ia_view_chat',
                'children'   => [],
            ],
            [
                'label'      => 'Configuración',
                'icon'       => 'fas fa-cog',
                'route'      => '/configuracion',
                'permission' => 'setting_view_setting',
                'children'   => [],
            ],
            [
                'label'      => 'DevTools',
                'icon'       => 'fas fa-tools',
                'route'      => '/devtools',
                'permission' => null,
                'active'     => true,
                'children'   => [],
            ],
        ];

        $filtered = array_values(array_filter($items, function ($item) use ($user) {
            if ($item['permission'] === null) {
                return true;
            }
            return $user && $user->can($item['permission']);
        }));

        return response()->json($filtered);
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
     * Convierte la lista de attachments del request a content blocks que la
     * Claude API entiende. Imágenes → bloque "image" base64 (vision).
     * Archivos de texto → bloque "text" con el contenido entre fences.
     * Archivos binarios sin extracción → bloque "text" con placeholder.
     *
     * El payload espera attachments con keys: type ('image'|'file'),
     * name, mimeType, base64.
     */
    private function buildAttachmentBlocks(array $attachments): array
    {
        $blocks = [];
        foreach ($attachments as $att) {
            $type = $att['type'] ?? '';
            $b64 = $att['base64'] ?? '';
            $mime = $att['mimeType'] ?? '';
            $name = $att['name'] ?? 'archivo';
            if ($b64 === '') {
                continue;
            }
            if ($type === 'image') {
                $blocks[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $mime !== '' ? $mime : 'image/png',
                        'data' => $b64,
                    ],
                ];
                continue;
            }
            // type === 'file' → intentar decodificar como texto.
            $raw = base64_decode($b64, true);
            $isTextMime = $mime !== '' && (
                str_starts_with($mime, 'text/')
                || in_array($mime, ['application/json', 'application/javascript', 'application/x-php'], true)
            );
            $isTextExt = (bool) preg_match('/\.(txt|md|json|csv|php|js|vue|py)$/i', $name);
            if (($isTextMime || $isTextExt) && $raw !== false) {
                // Truncar a 50 KB para no inflar el payload — suficiente para
                // archivos de código razonables, y evita exceder context window
                // si el desarrollador adjunta logs gigantes.
                $excerpt = mb_substr($raw, 0, 50000);
                $blocks[] = [
                    'type' => 'text',
                    'text' => "[Archivo adjunto: {$name}]\n```\n{$excerpt}\n```",
                ];
            } else {
                $blocks[] = [
                    'type' => 'text',
                    'text' => "[Archivo adjunto binario: {$name} ({$mime}) — contenido no extraído]",
                ];
            }
        }
        return $blocks;
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
