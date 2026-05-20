<?php

namespace App\Modules\Addons\IA\Services\Adaptadores;

use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Services\IAAdaptadorInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Adaptador para OpenAI y APIs compatibles (Ollama, DeepSeek, Groq, etc.).
 * Cuando el driver del proveedor es "openai_compatible" se usa el mismo
 * adaptador pero con endpoint/headers/modelo configurables.
 */
class OpenAIAdaptador implements IAAdaptadorInterface
{
    public function __construct(protected IAProveedor $proveedor)
    {
    }

    public function enviarMensaje(array $historial, string $mensaje, array $imagenes = [], ?string $systemPrompt = null): array
    {
        $payload = $this->construirPayload($historial, $mensaje, $imagenes, $systemPrompt);
        $endpoint = $this->proveedor->endpoint_url ?: 'https://api.openai.com/v1/chat/completions';

        $headers = array_merge([
            'Authorization' => 'Bearer ' . $this->proveedor->api_key,
            'Content-Type' => 'application/json',
        ], $this->proveedor->headers_personalizados ?? []);

        $response = Http::withHeaders($headers)
            ->timeout(120)
            ->post($endpoint, $payload);

        if (!$response->successful()) {
            throw new RuntimeException('OpenAI API error: ' . $response->body());
        }

        $json = $response->json();

        return [
            'texto' => $this->parsearRespuesta($json),
            'tokens_input' => data_get($json, 'usage.prompt_tokens'),
            'tokens_output' => data_get($json, 'usage.completion_tokens'),
            'raw' => $json,
        ];
    }

    public function probarConexion(): bool
    {
        try {
            $this->enviarMensaje([], 'ping', []);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function construirPayload(array $historial, string $mensaje, array $imagenes, ?string $systemPrompt = null): array
    {
        $messages = [];

        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        foreach ($historial as $h) {
            $rol = match ($h['rol'] ?? 'user') {
                'assistant' => 'assistant',
                'system' => 'system',
                default => 'user',
            };
            $messages[] = [
                'role' => $rol,
                'content' => $this->formatearContenido(
                    $h['contenido'] ?? '',
                    $h['imagenes'] ?? []
                ),
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $this->formatearContenido($mensaje, $imagenes),
        ];

        $payload = [
            'model' => $this->proveedor->modelo_default,
            'messages' => $messages,
        ];

        $maxTokens = data_get($this->proveedor->config_extra, 'max_tokens');
        if ($maxTokens) {
            $payload['max_tokens'] = (int) $maxTokens;
        }

        $temperature = data_get($this->proveedor->config_extra, 'temperature');
        if ($temperature !== null) {
            $payload['temperature'] = (float) $temperature;
        }

        return $payload;
    }

    public function parsearRespuesta(array $respuesta): string
    {
        return (string) data_get($respuesta, 'choices.0.message.content', '');
    }

    protected function formatearContenido(string $texto, array $imagenes): array|string
    {
        if (empty($imagenes)) {
            return $texto;
        }

        $partes = [['type' => 'text', 'text' => $texto]];
        foreach ($imagenes as $img) {
            $mime = $img['mime'] ?? 'image/jpeg';
            $partes[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:' . $mime . ';base64,' . $img['data'],
                ],
            ];
        }
        return $partes;
    }
}
