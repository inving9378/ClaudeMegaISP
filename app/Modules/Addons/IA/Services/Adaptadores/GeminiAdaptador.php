<?php

namespace App\Modules\Addons\IA\Services\Adaptadores;

use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Services\IAAdaptadorInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiAdaptador implements IAAdaptadorInterface
{
    public function __construct(protected IAProveedor $proveedor)
    {
    }

    public function enviarMensaje(array $historial, string $mensaje, array $imagenes = [], ?string $systemPrompt = null): array
    {
        $payload = $this->construirPayload($historial, $mensaje, $imagenes, $systemPrompt);
        $endpoint = $this->resolverEndpoint();

        $headers = array_merge([
            'Content-Type' => 'application/json',
        ], $this->proveedor->headers_personalizados ?? []);

        $response = Http::withHeaders($headers)
            ->timeout(120)
            ->post($endpoint . '?key=' . urlencode($this->proveedor->api_key), $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Gemini API error: ' . $response->body());
        }

        $json = $response->json();

        return [
            'texto' => $this->parsearRespuesta($json),
            'tokens_input' => data_get($json, 'usageMetadata.promptTokenCount'),
            'tokens_output' => data_get($json, 'usageMetadata.candidatesTokenCount'),
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
        $contents = [];

        foreach ($historial as $h) {
            if (($h['rol'] ?? null) === 'system') {
                continue;
            }
            $contents[] = [
                'role' => $h['rol'] === 'assistant' ? 'model' : 'user',
                'parts' => $this->construirParts(
                    $h['contenido'] ?? '',
                    $h['imagenes'] ?? []
                ),
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => $this->construirParts($mensaje, $imagenes),
        ];

        $payload = ['contents' => $contents];

        if ($systemPrompt) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemPrompt]],
            ];
        }

        return $payload;
    }

    public function parsearRespuesta(array $respuesta): string
    {
        $parts = data_get($respuesta, 'candidates.0.content.parts', []);
        $texto = '';
        foreach ($parts as $p) {
            $texto .= $p['text'] ?? '';
        }
        return $texto;
    }

    protected function construirParts(string $texto, array $imagenes): array
    {
        $parts = [];
        foreach ($imagenes as $img) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $img['mime'] ?? 'image/jpeg',
                    'data' => $img['data'],
                ],
            ];
        }
        if ($texto !== '') {
            $parts[] = ['text' => $texto];
        }
        return $parts;
    }

    protected function resolverEndpoint(): string
    {
        $url = $this->proveedor->endpoint_url
            ?: 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent';

        return str_replace('{model}', $this->proveedor->modelo_default, $url);
    }
}
