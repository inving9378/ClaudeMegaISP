<?php

namespace App\Modules\Addons\IA\Services\Adaptadores;

class GeminiAdaptador extends AdaptadorHttpBase
{
    protected function nombreApi(): string
    {
        return 'Gemini';
    }

    public function enviarMensaje(array $historial, string $mensaje, array $imagenes = [], ?string $systemPrompt = null, array $opciones = []): array
    {
        $payload = $this->construirPayload($historial, $mensaje, $imagenes, $systemPrompt, $opciones);

        // La llave va en header, no en la URL: en la URL terminaba en logs y excepciones.
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'x-goog-api-key' => (string) $this->clave(),
        ], $this->proveedor->headers_personalizados ?? []);

        $json = $this->postJson($this->resolverEndpoint(), $headers, $payload, $opciones);

        return [
            'texto' => $this->parsearRespuesta($json),
            'tokens_input' => data_get($json, 'usageMetadata.promptTokenCount'),
            'tokens_output' => data_get($json, 'usageMetadata.candidatesTokenCount'),
            'fin' => match (data_get($json, 'candidates.0.finishReason')) {
                'STOP' => 'completo',
                'MAX_TOKENS' => 'max_tokens',
                default => 'otro',
            },
            'raw' => $json,
        ];
    }

    public function construirPayload(array $historial, string $mensaje, array $imagenes, ?string $systemPrompt = null, array $opciones = []): array
    {
        $this->exigirSoportePdf($imagenes);

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

        $config = [];
        if ($max = $this->opcion($opciones, 'max_tokens', 'max_tokens')) {
            $config['maxOutputTokens'] = (int) $max;
        }
        $temperature = $this->opcion($opciones, 'temperatura', 'temperature');
        if ($temperature !== null) {
            $config['temperature'] = (float) $temperature;
        }
        if (!empty($opciones['json'])) {
            $config['responseMimeType'] = 'application/json';
        }
        if ($config) {
            $payload['generationConfig'] = $config;
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
        // Gemini rechaza un turno sin parts.
        if ($texto !== '' || empty($parts)) {
            $parts[] = ['text' => $texto !== '' ? $texto : ' '];
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
