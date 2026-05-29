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

    // Pricing per token (USD)
    private const PRICING = [
        'claude-opus-4-7'   => ['input' => 0.000015,  'output' => 0.000075],
        'claude-sonnet-4-6' => ['input' => 0.000003,  'output' => 0.000015],
        'claude-haiku-4-5'  => ['input' => 0.00000025, 'output' => 0.00000125],
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

        while ($attempt <= $maxRetry) {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => $this->apiVersion,
                'content-type'      => 'application/json',
            ])->post("{$this->baseUrl}/messages", $params);

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
                if ($attempt < $maxRetry) {
                    sleep($backoffs[$attempt] ?? 15);
                    $attempt++;
                    continue;
                }
            }

            if ($status >= 500 && $attempt < $maxRetry) {
                Log::channel('claude')->warning("Claude server error {$status}, retry {$attempt}/{$maxRetry}");
                sleep($backoffs[$attempt] ?? 5);
                $attempt++;
                continue;
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
