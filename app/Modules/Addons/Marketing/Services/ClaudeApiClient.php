<?php

namespace App\Modules\Addons\Marketing\Services;

use App\Models\Marketing\Setting;
use App\Services\Core\UsesApiIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeApiClient
{
    use UsesApiIntegration;
    protected string $apiKey;
    protected string $baseUrl = 'https://api.anthropic.com/v1';
    protected string $apiVersion = '2023-06-01';

    // Item roadmap #9990624 Fase 1 — antes esta llamada NO tenía timeout: una respuesta lenta
    // (ej. ReleaseChangelogService con un rango grande de commits) se quedaba esperando
    // indefinidamente, colgando quien la invocara. 120s da margen holgado para una respuesta
    // normal de Claude sin dejar un request/job atorado para siempre.
    // Fase A #9990624 — subido 90→120 y añadido connectTimeout: si el DNS/socket de la API no
    // levanta, cortar en 10s en vez de gastar el timeout completo esperando conexión.
    private const REQUEST_TIMEOUT_SECONDS = 120;

    // Corte del handshake TCP/TLS (no de la respuesta). Sin esto, un endpoint inalcanzable
    // consumía los 120s enteros solo intentando conectar.
    private const CONNECT_TIMEOUT_SECONDS = 10;

    // Techo TOTAL de la operación, reintentos incluidos. El timeout de arriba acota UNA llamada;
    // los reintentos por 429/5xx (con sus backoffs) podían apilarse por encima. Este deadline
    // garantiza que messages() nunca tarde mucho más que una llamada larga + un reintento:
    // antes de cada sleep/reintento se comprueba que aún cabemos en la ventana.
    private const MAX_TOTAL_SECONDS = 180;

    // Pricing per token (USD)
    private const PRICING = [
        'claude-opus-4-7'   => ['input' => 0.000015,  'output' => 0.000075],
        'claude-sonnet-4-6' => ['input' => 0.000003,  'output' => 0.000015],
        'claude-haiku-4-5'  => ['input' => 0.00000025, 'output' => 0.00000125],
        // Alias defensivo: id de snapshot obsoleto que aún puede llegar de configs/llamadas
        // viejas (#235). Sin esta fila, calculateCost caía al tier 'default' (precio de
        // opus, ~5x el de sonnet) para un modelo que en realidad es sonnet.
        'claude-sonnet-4-20250514' => ['input' => 0.000003,  'output' => 0.000015],
        'default'           => ['input' => 0.000015,  'output' => 0.000075],
    ];

    public function __construct(int $companyId = 1)
    {
        $this->apiKey = $this->resolveApiKey('anthropic', 'CLAUDE_API_KEY', 'claude_api_key', $companyId) ?? '';
    }

    /**
     * Call /v1/messages — supports tools and multi-turn.
     *
     * @param array $params {model, max_tokens, messages, system?, tools?, tool_choice?, temperature?}
     * @return array Full Claude response
     */
    public function messages(array $params): array
    {
        $attempt  = 0;
        $maxRetry = 3;
        $backoffs  = [1, 5, 15]; // seconds
        $deadline  = microtime(true) + self::MAX_TOTAL_SECONDS; // techo total (reintentos incluidos)

        while ($attempt <= $maxRetry) {
            try {
                $response = Http::withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => $this->apiVersion,
                    'content-type'      => 'application/json',
                ])->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                  ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                  ->post("{$this->baseUrl}/messages", $params);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Log::channel('claude')->error("Claude API timeout tras " . self::REQUEST_TIMEOUT_SECONDS . "s: {$e->getMessage()}");
                throw new \RuntimeException('Claude API no respondió a tiempo (timeout de ' . self::REQUEST_TIMEOUT_SECONDS . 's).', 0, $e);
            }

            $status = $response->status();
            $body   = $response->json() ?? [];

            Log::channel('claude')->debug('Claude API call', [
                'model'  => $params['model'] ?? '?',
                'status' => $status,
                'usage'  => $body['usage'] ?? null,
            ]);

            if ($status === 200) {
                return $body;
            }

            if ($status === 401) {
                Log::channel('claude')->critical('Claude API key invalid');
                throw new \RuntimeException('Claude API key inválida. Configura claude_api_key en marketing_settings.');
            }

            if ($status === 429) {
                Log::channel('claude')->warning("Claude rate limit, retry {$attempt}/{$maxRetry}");
                $espera = $backoffs[$attempt] ?? 15;
                // Solo reintentar si el backoff aún cabe dentro del techo total.
                if ($attempt < $maxRetry && (microtime(true) + $espera) < $deadline) {
                    sleep($espera);
                    $attempt++;
                    continue;
                }
            }

            if ($status >= 500 && $attempt < $maxRetry) {
                $espera = $backoffs[$attempt] ?? 5;
                if ((microtime(true) + $espera) < $deadline) {
                    Log::channel('claude')->warning("Claude server error {$status}, retry {$attempt}/{$maxRetry}");
                    sleep($espera);
                    $attempt++;
                    continue;
                }
            }

            $errorMsg = $body['error']['message'] ?? $response->body();
            Log::channel('claude')->error("Claude API error {$status}: {$errorMsg}");
            throw new \RuntimeException("Claude API error {$status}: {$errorMsg}");
        }

        throw new \RuntimeException('Claude API unreachable after retries.');
    }

    /**
     * Calculate cost in USD from a usage block.
     */
    public function calculateCost(array $usage, string $model): float
    {
        $tier = self::PRICING[$model] ?? self::PRICING['default'];
        $inputTokens  = $usage['input_tokens']  ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;
        return ($inputTokens * $tier['input']) + ($outputTokens * $tier['output']);
    }
}
