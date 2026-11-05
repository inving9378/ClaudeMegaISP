<?php

namespace App\Modules\Addons\Marketing\Services;

use App\Models\Marketing\Setting;
use App\Services\Core\SaludIA;
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

    // Pricing per token (USD), lista oficial de Anthropic al 2026-10-30.
    // Haiku 5.5: tarifa de prompts de hasta 100K tokens (arriba de eso cuesta 5x: ver calculateCost).
    // Sin modelos retirados: una llamada a un id retirado falla antes de costar algo.
    private const PRICING = [
        'claude-opus-5-5'   => ['input' => 0.000004,  'output' => 0.000020],
        'claude-opus-4-7'   => ['input' => 0.000005,  'output' => 0.000025],
        'claude-sonnet-5-5' => ['input' => 0.000002,  'output' => 0.000010],
        'claude-sonnet-4-6' => ['input' => 0.000003,  'output' => 0.000015],
        'claude-haiku-5-5'  => ['input' => 0.0000001, 'output' => 0.0000005],
        'claude-haiku-4-5'  => ['input' => 0.000001,  'output' => 0.000005],
        'default'           => ['input' => 0.000005,  'output' => 0.000025],
    ];

    // Caché de prompts (#1076): escribir en la caché de 5 min cuesta 1.25× la entrada, en la de
    // 1 h 2×; leer de ella 0.1×. Mismo multiplicador para todos los modelos.
    private const CACHE_ESCRITURA_5M = 1.25;
    private const CACHE_ESCRITURA_1H = 2.0;
    private const CACHE_LECTURA      = 0.1;

    // Haiku 5.5 cobra 5× entrada y salida cuando el prompt pasa de 100K tokens.
    private const LARGO_UMBRAL = 100000;
    private const LARGO_FACTOR = ['claude-haiku-5-5' => 5.0];

    public function __construct(int $companyId = 1)
    {
        $this->apiKey = $this->resolveApiKey('anthropic', 'CLAUDE_API_KEY', 'claude_api_key', $companyId) ?? '';
    }

    /**
     * Call /v1/messages — supports tools and multi-turn.
     *
     * @param array $params   {model, max_tokens, messages, system?, tools?, tool_choice?, temperature?}
     * @param array $contexto (#1076) etiqueta de la llamada, NO viaja a la API: `pieza` (p. ej.
     *                        'circuito.revisor') e `item` (id del roadmap). Se escribe en el log del
     *                        canal `claude` junto con el costo, para medir el gasto por pieza.
     * @return array Full Claude response
     */
    public function messages(array $params, array $contexto = []): array
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

            $linea = [
                'model'  => $params['model'] ?? '?',
                'status' => $status,
                'usage'  => $body['usage'] ?? null,
            ];
            // #1076 — pieza + costo en la misma línea: `circuito:gasto-ia` suma el gasto por pieza
            // y por día leyendo solo este log. Sin contexto la línea queda igual que antes.
            if (! empty($contexto['pieza'])) {
                $linea['pieza'] = (string) $contexto['pieza'];
            }
            if (! empty($contexto['item'])) {
                $linea['item'] = (int) $contexto['item'];
            }
            if ($status === 200 && is_array($body['usage'] ?? null)) {
                $usd = self::costoUsd($body['usage'], (string) ($params['model'] ?? ''));
                $linea['stop_reason'] = $body['stop_reason'] ?? null;
                $linea['costo_usd']   = round($usd, 6);
                $linea['costo_mxn']   = round($usd * self::tipoCambioMxn(), 4);
            }
            Log::channel('claude')->debug('Claude API call', $linea);

            // #1073: llave rechazada, sin saldo o sin cuota dejan ciego al supervisor sin ruido.
            SaludIA::registrar('anthropic', 'ClaudeApiClient:' . ($params['model'] ?? '?'), $status, (string) data_get($body, 'error.message', ''));

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
     *
     * #1076 — incluye la caché de prompts: lo escrito en ella se cobra a 1.25× (5 min) o 2× (1 h)
     * la entrada y lo leído a 0.1×. Antes solo contaba `input_tokens`, que con caché es apenas la
     * parte NO cacheada. Haiku 5.5 con un prompt de más de 100K tokens cobra 5×.
     */
    public function calculateCost(array $usage, string $model): float
    {
        return self::costoUsd($usage, $model);
    }

    /** Lo mismo que calculateCost(), sin instanciar (no resuelve la llave): para leer logs. */
    public static function costoUsd(array $usage, string $model): float
    {
        $tier = self::PRICING[$model] ?? self::PRICING['default'];

        $entrada  = (int) ($usage['input_tokens'] ?? 0);
        $salida   = (int) ($usage['output_tokens'] ?? 0);
        $lectura  = (int) ($usage['cache_read_input_tokens'] ?? 0);
        $escritura = (int) ($usage['cache_creation_input_tokens'] ?? 0);
        $escrituraHora = (int) data_get($usage, 'cache_creation.ephemeral_1h_input_tokens', 0);
        $escrituraHora = min($escrituraHora, $escritura);
        $escrituraCinco = $escritura - $escrituraHora;

        $factor = 1.0;
        if (($entrada + $lectura + $escritura) > self::LARGO_UMBRAL) {
            $factor = self::LARGO_FACTOR[$model] ?? 1.0;
        }

        $precioEntrada = $tier['input'] * $factor;
        $precioSalida  = $tier['output'] * $factor;

        return ($entrada * $precioEntrada)
            + ($escrituraCinco * $precioEntrada * self::CACHE_ESCRITURA_5M)
            + ($escrituraHora * $precioEntrada * self::CACHE_ESCRITURA_1H)
            + ($lectura * $precioEntrada * self::CACHE_LECTURA)
            + ($salida * $precioSalida);
    }

    /** Pesos por dólar para reportar el gasto (#1076). Config `services.anthropic.tipo_cambio_mxn`. */
    public static function tipoCambioMxn(): float
    {
        $tc = (float) config('services.anthropic.tipo_cambio_mxn', 19);

        return $tc > 0 ? $tc : 19.0;
    }
}
