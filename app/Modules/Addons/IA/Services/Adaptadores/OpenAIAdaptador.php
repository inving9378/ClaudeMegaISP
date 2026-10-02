<?php

namespace App\Modules\Addons\IA\Services\Adaptadores;

/**
 * Adaptador para OpenAI y APIs compatibles (Ollama, DeepSeek, Groq, etc.).
 * Cuando el driver del proveedor es "openai_compatible" se usa el mismo
 * adaptador pero con endpoint/headers/modelo configurables.
 *
 * Ollama: driver=openai_compatible, endpoint http://<host>:11434/v1/chat/completions,
 * api_key vacía (no se manda Authorization).
 */
class OpenAIAdaptador extends AdaptadorHttpBase
{
    protected function nombreApi(): string
    {
        return 'OpenAI';
    }

    public function enviarMensaje(array $historial, string $mensaje, array $imagenes = [], ?string $systemPrompt = null, array $opciones = []): array
    {
        $payload = $this->construirPayload($historial, $mensaje, $imagenes, $systemPrompt, $opciones);
        $endpoint = $this->proveedor->endpoint_url ?: 'https://api.openai.com/v1/chat/completions';

        $headers = ['Content-Type' => 'application/json'];
        if ($clave = $this->clave()) {
            $headers['Authorization'] = 'Bearer ' . $clave;
        }
        $headers = array_merge($headers, $this->proveedor->headers_personalizados ?? []);

        $json = $this->postJson($endpoint, $headers, $payload, $opciones);

        return [
            'texto' => $this->parsearRespuesta($json),
            'tokens_input' => data_get($json, 'usage.prompt_tokens'),
            'tokens_output' => data_get($json, 'usage.completion_tokens'),
            'fin' => match (data_get($json, 'choices.0.finish_reason')) {
                'stop' => 'completo',
                'length' => 'max_tokens',
                default => 'otro',
            },
            'raw' => $json,
        ];
    }

    public function construirPayload(array $historial, string $mensaje, array $imagenes, ?string $systemPrompt = null, array $opciones = []): array
    {
        $this->exigirSoportePdf($imagenes);

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

        $modelo = (string) $this->proveedor->modelo_default;
        $payload = [
            'model' => $modelo,
            'messages' => $messages,
        ];

        // Modelos de razonamiento (o1/o3/o4…, gpt-5…) rechazan max_tokens y temperature.
        $razonamiento = (bool) preg_match('/^(o\d|gpt-5)/i', $modelo);

        $maxTokens = $this->opcion($opciones, 'max_tokens', 'max_tokens');
        if ($maxTokens) {
            $payload[$razonamiento ? 'max_completion_tokens' : 'max_tokens'] = (int) $maxTokens;
        }

        $temperature = $this->opcion($opciones, 'temperatura', 'temperature');
        if ($temperature !== null && !$razonamiento) {
            $payload['temperature'] = (float) $temperature;
        }

        // JSON nativo: OpenAI exige que la palabra "json" aparezca en los mensajes,
        // si no responde 400. Solo se activa cuando el prompt ya lo pide.
        if (!empty($opciones['json']) && stripos($systemPrompt . ' ' . $mensaje, 'json') !== false) {
            $payload['response_format'] = ['type' => 'json_object'];
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
            if ($this->esPdf($img)) {
                $partes[] = [
                    'type' => 'file',
                    'file' => [
                        'filename' => 'documento.pdf',
                        'file_data' => 'data:application/pdf;base64,' . $img['data'],
                    ],
                ];
                continue;
            }
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
