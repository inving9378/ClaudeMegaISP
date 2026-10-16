<?php

namespace App\Modules\Addons\DevTools\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use App\Modules\Core\ModuleManager\Models\ModuleRegistry;
use App\Modules\Core\ModuleManager\Services\ModuleManagerService;
use App\Services\TerminalIdentityTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Process;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DevToolsController extends Controller
{
    /** Mismo default que el resto de la app — IAChatController y ModuleManager. */
    private const IA_MAX_TOKENS = 2048;

    /** Cookie de identidad de terminal — Fase 1 ttyd-por-usuario (item #9991177). */
    private const TERMINAL_TOKEN_COOKIE = 'megaisp_term_token';

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

        $response = response()->view('addon-devtools::index', [
            'ttydUrl' => $this->resolveTtydUrl(),
            'csrfToken' => csrf_token(),
        ]);

        return $this->withTerminalTokenCookie($response);
    }

    /**
     * GET /devtools/terminal-token — emite (o renueva) la cookie de identidad
     * de terminal. Fase 1 ttyd-por-usuario (item #9991177): a diferencia del
     * resto de DevTools, este endpoint NO se restringe a DESARROLLADOR/
     * super-administrator — "cada usuario del admin" según el diseño
     * aprobado — por eso vive en su propio grupo de rutas (`web`+`auth`,
     * sin `role:`) en routes.php. El token en sí NO otorga acceso a ttyd:
     * solo permite, una vez activado el paso root del runbook
     * (deploy/README-terminal-por-usuario.md), nombrar la sesión tmux con
     * el login_user real en vez de "cualquiera libre".
     */
    public function terminalToken(): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['success' => false, 'error' => 'No autenticado'], 401);
        }

        return $this->withTerminalTokenCookie(response()->json(['success' => true]));
    }

    /**
     * GET /devtools/terminal-auth — puerta de la terminal web ttyd.
     *
     * NO lo llama el navegador: lo llama nginx (`auth_request`) antes de cada
     * petición a /ttyd/ (HTML, /token y el handshake del WebSocket), reenviando
     * la cookie de sesión del usuario. Contrato con nginx (ver
     * deploy/ttyd/aplicar-ttyd-seguro.sh):
     *   204 + header X-MegaISP-Term-User → pasa; nginx reenvía ese header a ttyd
     *        (que corre con `-H`, así que sin él rechaza la conexión) y ttyd lo
     *        expone al bash como TTYD_USER → ~/.bashrc abre la sesión tmux de
     *        ESE usuario (web-<login_user>).
     *   401 → sin sesión.   403 → con sesión pero sin rol de DevTools.
     *
     * El header lleva el login_user tal cual, NO el token HMAC de
     * TerminalIdentityTokenService: ttyd 1.7.7 guarda ese valor en un buffer de
     * 30 bytes y corta el WebSocket si mide 30 caracteres o más (medido
     * 2026-10-15; el token mide ~90). Es confiable sin firma porque a ttyd solo
     * se llega por nginx (escucha en 127.0.0.1) y nginx pisa cualquier valor que
     * mande el cliente.
     *
     * Responde siempre con código explícito: auth_request trata un 302 (el
     * redirect a /login del middleware `auth`) como error 500, por eso esta
     * ruta no lleva `auth` y verifica aquí.
     *
     * Existe por el incidente del 2026-10-14: ttyd escuchaba en 0.0.0.0 sin
     * contraseña y hubo shells abiertos desde IPs externas.
     */
    public function terminalAuth(Request $request): SymfonyResponse
    {
        if (! Auth::check()) {
            return response('', 401);
        }

        $user = Auth::user();
        $loginUser = (string) ($user->login_user ?? $user->getAuthIdentifier());

        if (! $this->isAuthorized()) {
            $this->auditarTerminal('Terminal web denegada (sin rol de DevTools)', $request, $loginUser);

            return response('', 403);
        }

        // Una apertura = un handshake del WebSocket; el HTML y /token no se
        // registran para no triplicar cada apertura en la bitácora.
        if (str_ends_with((string) $request->header('X-Original-URI'), '/ws')) {
            $this->auditarTerminal('Terminal web abierta', $request, $loginUser);
        }

        // Debe caber en el buffer de ttyd (≤29) y servir de nombre de sesión
        // tmux; si el login_user no cumple, se identifica por id.
        $identidad = preg_match('/^[A-Za-z0-9_-]{1,29}$/', $loginUser)
            ? $loginUser
            : 'id' . $user->getAuthIdentifier();

        return response('', 204)
            ->header('X-MegaISP-Term-User', $identidad)
            ->header('Cache-Control', 'no-store');
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
     * activos) en el system prompt. La IA (integración + modelo) se decide en
     * Integraciones → Módulos IA (devtools.chat). Formato neutro de proveedor:
     * ya no usa el prompt caching propio de Anthropic.
     */
    public function chat(Request $request): JsonResponse
    {
        if (! $this->isAuthorized()) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
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

        $historial = [];
        foreach ($request->input('history', []) as $msg) {
            $content = $msg['content'] ?? '';
            if (is_string($content) && $content !== '') {
                $historial[] = ['rol' => ($msg['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user', 'contenido' => $content];
            }
        }
        $userMsg = trim((string) $request->input('message', ''));
        $attachments = $request->input('attachments', []);
        if ($userMsg === '' && empty($attachments)) {
            return response()->json(['success' => false, 'error' => 'Mensaje vacío'], 422);
        }

        // Adjuntos: imágenes → visión; archivos de texto → se agregan al mensaje.
        [$imagenes, $textos] = $this->buildAdjuntos(is_array($attachments) ? $attachments : []);
        $mensaje = trim(implode("\n\n", array_filter([...$textos, $userMsg])));
        if ($mensaje === '') {
            $mensaje = '(adjunto sin contenido extraíble)';
        }

        try {
            $system = $baseSystem . "\n\n" . $this->formatContextForPrompt($this->gatherContext());

            $r = IA::enviar('devtools.chat', $mensaje, $imagenes, $system, $historial, [
                'max_tokens' => self::IA_MAX_TOKENS, 'timeout' => 60,
            ]);

            return response()->json([
                'success' => true,
                'reply' => $r['texto'],
                'input_tokens' => (int) ($r['tokens_input'] ?? 0),
                'output_tokens' => (int) ($r['tokens_output'] ?? 0),
                'cache_creation_input_tokens' => 0,
                'cache_read_input_tokens' => 0,
            ]);
        } catch (IANoConfigurada $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
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

    private function resolveTtydUrl(): string
    {
        // TTYD_URL env (leído vía config/devtools.php) permite override (ej. dev
        // remoto en otro host). Por defecto usamos el proxy nginx /ttyd/ que sirve
        // ttyd same-origin, eliminando la restricción cross-origin que impedía
        // acceder a window.term.
        $env = config('devtools.ttyd_url', '');
        if ($env !== '') {
            return $env;
        }
        return '/ttyd/';
    }

    private function isAuthorized(): bool
    {
        return Auth::check() && Auth::user()->hasAnyRole(['DESARROLLADOR', 'super-administrator']);
    }

    /**
     * Rastro de quién abrió (o intentó abrir) la terminal web, en activity_log
     * con log_name='devtools-terminal'. Un fallo al registrar nunca bloquea la
     * terminal: es auditoría, no la puerta.
     */
    private function auditarTerminal(string $descripcion, Request $request, string $loginUser): void
    {
        try {
            activity('devtools-terminal')
                ->causedBy(Auth::user())
                ->withProperties([
                    'login_user' => $loginUser,
                    // nginx pone X-Real-IP con la IP real del cliente; $request->ip()
                    // sería 127.0.0.1 (la subpetición viene del propio nginx).
                    'ip' => $request->header('X-Real-IP', $request->ip()),
                    'uri' => $request->header('X-Original-URI'),
                ])
                ->log($descripcion);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[devtools-terminal] no se pudo registrar en activity_log: ' . $e->getMessage());
        }
    }

    /**
     * Adjunta la cookie de identidad de terminal (Fase 1 ttyd-por-usuario,
     * item #9991177) a la respuesta dada, para el usuario admin actualmente
     * autenticado. No-op si no hay usuario en sesión.
     *
     * Tipado contra el Response base de Symfony (no Illuminate\Http\Response)
     * porque también recibe JsonResponse, que NO extiende a aquel — ambos
     * comparten `cookie()` vía Illuminate\Http\ResponseTrait.
     */
    private function withTerminalTokenCookie(SymfonyResponse $response): SymfonyResponse
    {
        $user = Auth::user();
        if (! $user) {
            return $response;
        }

        $loginUser = (string) ($user->login_user ?? $user->getAuthIdentifier());
        $token = app(TerminalIdentityTokenService::class)->issue($loginUser);

        // 1 minuto de cookie (~60s, igual al TTL real que valida el HMAC
        // dentro del propio token) — Secure solo si la request ya es HTTPS,
        // para no romper el acceso actual por HTTP plano en LAN/dev.
        return $response->cookie(
            self::TERMINAL_TOKEN_COOKIE,
            $token,
            1,
            '/',
            null,
            request()->isSecure(),
            true,
            false,
            'Lax'
        );
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
     * Separa los attachments del request en formato neutro de IA:
     * imágenes → [['mime','data']] (visión); archivos de texto → texto con el
     * contenido entre fences; binarios sin extracción → placeholder.
     *
     * El payload espera attachments con keys: type ('image'|'file'),
     * name, mimeType, base64.
     *
     * @return array{0: array<int, array{mime:string, data:string}>, 1: array<int, string>}
     */
    private function buildAdjuntos(array $attachments): array
    {
        $imagenes = [];
        $textos = [];
        foreach ($attachments as $att) {
            $type = $att['type'] ?? '';
            $b64 = $att['base64'] ?? '';
            $mime = $att['mimeType'] ?? '';
            $name = $att['name'] ?? 'archivo';
            if ($b64 === '') {
                continue;
            }
            if ($type === 'image') {
                $imagenes[] = ['mime' => $mime !== '' ? $mime : 'image/png', 'data' => $b64];
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
                $textos[] = "[Archivo adjunto: {$name}]\n```\n{$excerpt}\n```";
            } else {
                $textos[] = "[Archivo adjunto binario: {$name} ({$mime}) — contenido no extraído]";
            }
        }
        return [$imagenes, $textos];
    }

    /**
     * Convierte el contexto en el bloque de texto que va en el system prompt.
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
