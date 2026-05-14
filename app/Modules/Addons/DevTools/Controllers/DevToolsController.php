<?php

namespace App\Modules\Addons\DevTools\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class DevToolsController extends Controller
{
    /** Mismo default que el resto de la app — IAChatController y ModuleManager. */
    private const CLAUDE_MODEL_DEFAULT = 'claude-sonnet-4-20250514';
    private const CLAUDE_MAX_TOKENS = 2048;

    /**
     * Devuelve la página standalone /devtools. Solamente accesible a
     * usuarios con role:DESARROLLADOR — gateado por middleware en
     * routes.php; aquí actuamos como segunda red de seguridad.
     */
    public function index()
    {
        if (! Auth::check() || ! Auth::user()->hasRole('DESARROLLADOR')) {
            return redirect('/home');
        }

        return view('addon-devtools::index', [
            'ttydUrl' => env('TTYD_URL', 'http://127.0.0.1:7681'),
            'csrfToken' => csrf_token(),
        ]);
    }

    /**
     * Endpoint de chat para el panel izquierdo. Stateless: el frontend envía
     * el historial completo. Sólo DESARROLLADOR puede invocarlo.
     */
    public function chat(Request $request): JsonResponse
    {
        if (! Auth::check() || ! Auth::user()->hasRole('DESARROLLADOR')) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $apiKey = env('CLAUDE_API_KEY', '');
        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'error' => 'CLAUDE_API_KEY no configurada en .env',
            ], 500);
        }

        $system = "Eres un asistente experto en desarrollo de MegaISP (Laravel 10 + Vue 3 + Quasar). "
            . "Ayudas a Irving a programar, migrar módulos y resolver problemas del sistema. "
            . "Responde en español, sé conciso y técnico. Cuando propongas comandos shell o "
            . "código, márcalo con bloques markdown.";

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
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model' => env('CLAUDE_MODEL', self::CLAUDE_MODEL_DEFAULT),
                'max_tokens' => self::CLAUDE_MAX_TOKENS,
                'system' => $system,
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
            $inputTokens = (int) ($payload['usage']['input_tokens'] ?? 0);
            $outputTokens = (int) ($payload['usage']['output_tokens'] ?? 0);

            return response()->json([
                'success' => true,
                'reply' => $text,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
