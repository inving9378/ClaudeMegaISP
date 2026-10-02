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

        $payload = [
            'model' => (string) $this->proveedor->modelo_default,
            'messages' => $messages,
        ];
        $this->aplicarLimites($payload, $opciones);

        // JSON nativo: OpenAI exige que la palabra "json" aparezca en los mensajes,
        // si no responde 400. Solo se activa cuando el prompt ya lo pide.
        if (!empty($opciones['json']) && stripos($systemPrompt . ' ' . $mensaje, 'json') !== false) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        return $payload;
    }

    public function conversarConHerramientas(array $mensajes, ?string $systemPrompt, array $herramientas, array $opciones = []): array
    {
        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        foreach ($mensajes as $m) {
            $rol = $m['rol'] ?? 'user';
            if ($rol === 'herramienta') {
                $messages[] = ['role' => 'tool', 'tool_call_id' => (string) $m['id'], 'content' => (string) ($m['resultado'] ?? '')];
            } elseif ($rol === 'assistant') {
                $turno = ['role' => 'assistant', 'content' => ($m['contenido'] ?? '') !== '' ? (string) $m['contenido'] : null];
                if (!empty($m['llamadas'])) {
                    $turno['tool_calls'] = array_map(fn($l) => [
                        'id' => (string) $l['id'],
                        'type' => 'function',
                        'function' => ['name' => (string) $l['nombre'], 'arguments' => json_encode($this->comoObjeto((array) ($l['argumentos'] ?? [])), JSON_UNESCAPED_UNICODE)],
                    ], $m['llamadas']);
                }
                $messages[] = $turno;
            } elseif ($rol === 'system') {
                $messages[] = ['role' => 'system', 'content' => (string) ($m['contenido'] ?? '')];
            } else {
                $messages[] = ['role' => 'user', 'content' => (string) ($m['contenido'] ?? '')];
            }
        }

        $payload = [
            'model' => (string) $this->proveedor->modelo_default,
            'messages' => $messages,
        ];
        if ($herramientas) {
            $payload['tools'] = array_map(fn($h) => [
                'type' => 'function',
                'function' => ['name' => $h['nombre'], 'description' => $h['descripcion'], 'parameters' => $h['parametros']],
            ], $this->herramientasNeutras($herramientas));
        }
        $this->aplicarLimites($payload, $opciones);

        $headers = ['Content-Type' => 'application/json'];
        if ($clave = $this->clave()) {
            $headers['Authorization'] = 'Bearer ' . $clave;
        }
        $headers = array_merge($headers, $this->proveedor->headers_personalizados ?? []);

        $json = $this->postJson($this->proveedor->endpoint_url ?: 'https://api.openai.com/v1/chat/completions', $headers, $payload, $opciones);

        $llamadas = [];
        foreach (data_get($json, 'choices.0.message.tool_calls', []) ?? [] as $tc) {
            $args = json_decode((string) data_get($tc, 'function.arguments', '{}'), true);
            $llamadas[] = [
                'id' => (string) ($tc['id'] ?? ''),
                'nombre' => (string) data_get($tc, 'function.name', ''),
                'argumentos' => is_array($args) ? $args : [],
            ];
        }

        return [
            'texto' => $this->parsearRespuesta($json),
            'llamadas' => $llamadas,
            'fin' => $llamadas ? 'herramientas' : match (data_get($json, 'choices.0.finish_reason')) {
                'stop' => 'completo',
                'length' => 'max_tokens',
                default => 'otro',
            },
            'tokens_input' => data_get($json, 'usage.prompt_tokens'),
            'tokens_output' => data_get($json, 'usage.completion_tokens'),
            'raw' => $json,
        ];
    }

    /**
     * max_tokens y temperatura. Los modelos de razonamiento (o1/o3/o4…, gpt-5…)
     * rechazan max_tokens y temperature: usan max_completion_tokens y sin temperatura.
     */
    private function aplicarLimites(array &$payload, array $opciones): void
    {
        $razonamiento = (bool) preg_match('/^(o\d|gpt-5)/i', (string) $payload['model']);

        $maxTokens = $this->opcion($opciones, 'max_tokens', 'max_tokens');
        if ($maxTokens) {
            $payload[$razonamiento ? 'max_completion_tokens' : 'max_tokens'] = (int) $maxTokens;
        }

        $temperature = $this->opcion($opciones, 'temperatura', 'temperature');
        if ($temperature !== null && !$razonamiento) {
            $payload['temperature'] = (float) $temperature;
        }
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
