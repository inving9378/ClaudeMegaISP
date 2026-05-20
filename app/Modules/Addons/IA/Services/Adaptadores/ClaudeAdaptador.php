<?php

namespace App\Modules\Addons\IA\Services\Adaptadores;

use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Services\IAAdaptadorInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ClaudeAdaptador implements IAAdaptadorInterface
{
    public function __construct(protected IAProveedor $proveedor)
    {
    }

    public function enviarMensaje(array $historial, string $mensaje, array $imagenes = [], ?string $systemPrompt = null): array
    {
        $payload = $this->construirPayload($historial, $mensaje, $imagenes, $systemPrompt);
        $endpoint = $this->proveedor->endpoint_url ?: 'https://api.anthropic.com/v1/messages';

        $headers = array_merge([
            'x-api-key' => $this->proveedor->api_key,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ], $this->proveedor->headers_personalizados ?? []);

        $response = Http::withHeaders($headers)
            ->timeout(120)
            ->post($endpoint, $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Claude API error: ' . $response->body());
        }

        $json = $response->json();

        return [
            'texto' => $this->parsearRespuesta($json),
            'tokens_input' => data_get($json, 'usage.input_tokens'),
            'tokens_output' => data_get($json, 'usage.output_tokens'),
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

        foreach ($historial as $h) {
            if (($h['rol'] ?? null) === 'system') {
                continue;
            }
            $messages[] = [
                'role' => $h['rol'] === 'assistant' ? 'assistant' : 'user',
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
            'max_tokens' => data_get($this->proveedor->config_extra, 'max_tokens', 4096),
            'messages' => $messages,
        ];

        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }

        return $payload;
    }

    public function parsearRespuesta(array $respuesta): string
    {
        $bloques = $respuesta['content'] ?? [];
        $texto = '';
        foreach ($bloques as $bloque) {
            if (($bloque['type'] ?? '') === 'text') {
                $texto .= $bloque['text'] ?? '';
            }
        }
        return $texto;
    }

    protected function formatearContenido(string $texto, array $imagenes): array|string
    {
        if (empty($imagenes)) {
            return $texto;
        }

        $partes = [];
        foreach ($imagenes as $img) {
            $mime = $img['mime'] ?? 'image/jpeg';
            if ($mime === 'application/pdf') {
                $partes[] = [
                    'type' => 'document',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => 'application/pdf',
                        'data' => $img['data'],
                    ],
                ];
                continue;
            }
            $partes[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $mime,
                    'data' => $img['data'],
                ],
            ];
        }
        $partes[] = ['type' => 'text', 'text' => $texto];
        return $partes;
    }
}
