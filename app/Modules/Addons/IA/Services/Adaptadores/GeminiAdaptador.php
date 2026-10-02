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

    public function conversarConHerramientas(array $mensajes, ?string $systemPrompt, array $herramientas, array $opciones = []): array
    {
        $contents = [];
        $respuestas = []; // functionResponse consecutivas van juntas en UN turno

        foreach ($mensajes as $m) {
            $rol = $m['rol'] ?? 'user';
            if ($rol === 'herramienta') {
                $resultado = json_decode((string) ($m['resultado'] ?? ''), true);
                $respuestas[] = ['functionResponse' => [
                    'name' => (string) ($m['nombre'] ?? ''),
                    'response' => ['resultado' => $resultado ?? (string) ($m['resultado'] ?? '')],
                ]];
                continue;
            }
            if ($respuestas) {
                $contents[] = ['role' => 'user', 'parts' => $respuestas];
                $respuestas = [];
            }
            if ($rol === 'system') {
                continue;
            }
            if ($rol === 'assistant') {
                $parts = [];
                if (($m['contenido'] ?? '') !== '') {
                    $parts[] = ['text' => (string) $m['contenido']];
                }
                foreach ($m['llamadas'] ?? [] as $l) {
                    $parts[] = ['functionCall' => ['name' => (string) $l['nombre'], 'args' => $this->comoObjeto((array) ($l['argumentos'] ?? []))]];
                }
                $contents[] = ['role' => 'model', 'parts' => $parts ?: [['text' => ' ']]];
                continue;
            }
            $contents[] = ['role' => 'user', 'parts' => [['text' => ((string) ($m['contenido'] ?? '')) ?: ' ']]];
        }
        if ($respuestas) {
            $contents[] = ['role' => 'user', 'parts' => $respuestas];
        }

        $payload = ['contents' => $contents];
        if ($systemPrompt) {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemPrompt]]];
        }
        if ($herramientas) {
            $payload['tools'] = [['functionDeclarations' => array_map(fn($h) => [
                'name' => $h['nombre'],
                'description' => $h['descripcion'],
                'parameters' => $this->esquemaGemini($h['parametros']),
            ], $this->herramientasNeutras($herramientas))]];
        }
        $config = [];
        if ($max = $this->opcion($opciones, 'max_tokens', 'max_tokens')) {
            $config['maxOutputTokens'] = (int) $max;
        }
        $temperature = $this->opcion($opciones, 'temperatura', 'temperature');
        if ($temperature !== null) {
            $config['temperature'] = (float) $temperature;
        }
        if ($config) {
            $payload['generationConfig'] = $config;
        }

        $headers = array_merge([
            'Content-Type' => 'application/json',
            'x-goog-api-key' => (string) $this->clave(),
        ], $this->proveedor->headers_personalizados ?? []);

        $json = $this->postJson($this->resolverEndpoint(), $headers, $payload, $opciones);

        $llamadas = [];
        foreach (data_get($json, 'candidates.0.content.parts', []) ?? [] as $i => $part) {
            if (isset($part['functionCall'])) {
                $llamadas[] = [
                    'id' => (string) ($part['functionCall']['id'] ?? ('llamada_' . $i)),
                    'nombre' => (string) ($part['functionCall']['name'] ?? ''),
                    'argumentos' => (array) ($part['functionCall']['args'] ?? []),
                ];
            }
        }

        return [
            'texto' => $this->parsearRespuesta($json),
            'llamadas' => $llamadas,
            'fin' => $llamadas ? 'herramientas' : match (data_get($json, 'candidates.0.finishReason')) {
                'STOP' => 'completo',
                'MAX_TOKENS' => 'max_tokens',
                default => 'otro',
            },
            'tokens_input' => data_get($json, 'usageMetadata.promptTokenCount'),
            'tokens_output' => data_get($json, 'usageMetadata.candidatesTokenCount'),
            'raw' => $json,
        ];
    }

    /**
     * Gemini acepta un subconjunto de JSON Schema: quita las claves que rechaza
     * (additionalProperties, $schema…) en todos los niveles.
     */
    private function esquemaGemini(mixed $esquema): mixed
    {
        if ($esquema instanceof \stdClass) {
            return $esquema;
        }
        if (!is_array($esquema)) {
            return $esquema;
        }
        unset($esquema['additionalProperties'], $esquema['$schema'], $esquema['default']);
        foreach ($esquema as $k => $v) {
            if (is_array($v) || $v instanceof \stdClass) {
                $esquema[$k] = $this->esquemaGemini($v);
            }
        }
        return $esquema;
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
