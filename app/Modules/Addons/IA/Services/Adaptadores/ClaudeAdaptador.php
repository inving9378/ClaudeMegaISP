<?php

namespace App\Modules\Addons\IA\Services\Adaptadores;

class ClaudeAdaptador extends AdaptadorHttpBase
{
    protected function nombreApi(): string
    {
        return 'Claude';
    }

    public function enviarMensaje(array $historial, string $mensaje, array $imagenes = [], ?string $systemPrompt = null, array $opciones = []): array
    {
        $payload = $this->construirPayload($historial, $mensaje, $imagenes, $systemPrompt, $opciones);
        $endpoint = $this->proveedor->endpoint_url ?: 'https://api.anthropic.com/v1/messages';

        $headers = array_merge([
            'x-api-key' => (string) $this->clave(),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ], $this->proveedor->headers_personalizados ?? []);

        $json = $this->postJson($endpoint, $headers, $payload, $opciones);

        return [
            'texto' => $this->parsearRespuesta($json),
            'tokens_input' => data_get($json, 'usage.input_tokens'),
            'tokens_output' => data_get($json, 'usage.output_tokens'),
            'fin' => match ($json['stop_reason'] ?? null) {
                'end_turn', 'stop_sequence' => 'completo',
                'max_tokens' => 'max_tokens',
                default => 'otro',
            },
            'raw' => $json,
        ];
    }

    public function conversarConHerramientas(array $mensajes, ?string $systemPrompt, array $herramientas, array $opciones = []): array
    {
        $messages = [];
        $resultados = []; // tool_result consecutivos van juntos en UN turno 'user'

        foreach ($mensajes as $m) {
            $rol = $m['rol'] ?? 'user';
            if ($rol === 'herramienta') {
                $resultados[] = ['type' => 'tool_result', 'tool_use_id' => (string) $m['id'], 'content' => (string) ($m['resultado'] ?? '')];
                continue;
            }
            if ($resultados) {
                $messages[] = ['role' => 'user', 'content' => $resultados];
                $resultados = [];
            }
            if ($rol === 'system') {
                continue;
            }
            if ($rol === 'assistant') {
                $bloques = [];
                if (($m['contenido'] ?? '') !== '') {
                    $bloques[] = ['type' => 'text', 'text' => (string) $m['contenido']];
                }
                foreach ($m['llamadas'] ?? [] as $l) {
                    $bloques[] = ['type' => 'tool_use', 'id' => (string) $l['id'], 'name' => (string) $l['nombre'], 'input' => $this->comoObjeto((array) ($l['argumentos'] ?? []))];
                }
                $messages[] = ['role' => 'assistant', 'content' => $bloques ?: (string) ($m['contenido'] ?? '')];
                continue;
            }
            $messages[] = ['role' => 'user', 'content' => (string) ($m['contenido'] ?? '')];
        }
        if ($resultados) {
            $messages[] = ['role' => 'user', 'content' => $resultados];
        }

        $payload = [
            'model' => $this->proveedor->modelo_default,
            'max_tokens' => (int) $this->opcion($opciones, 'max_tokens', 'max_tokens', 4096),
            'messages' => $messages,
        ];
        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }
        if ($herramientas) {
            $payload['tools'] = array_map(fn($h) => [
                'name' => $h['nombre'], 'description' => $h['descripcion'], 'input_schema' => $h['parametros'],
            ], $this->herramientasNeutras($herramientas));
        }
        if (array_key_exists('temperatura', $opciones) && $opciones['temperatura'] !== null) {
            $payload['temperature'] = (float) $opciones['temperatura'];
        }

        $headers = array_merge([
            'x-api-key' => (string) $this->clave(),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ], $this->proveedor->headers_personalizados ?? []);

        $json = $this->postJson($this->proveedor->endpoint_url ?: 'https://api.anthropic.com/v1/messages', $headers, $payload, $opciones);

        $llamadas = [];
        foreach ($json['content'] ?? [] as $b) {
            if (($b['type'] ?? '') === 'tool_use') {
                $llamadas[] = ['id' => (string) $b['id'], 'nombre' => (string) $b['name'], 'argumentos' => (array) ($b['input'] ?? [])];
            }
        }

        return [
            'texto' => $this->parsearRespuesta($json),
            'llamadas' => $llamadas,
            'fin' => $llamadas ? 'herramientas' : match ($json['stop_reason'] ?? null) {
                'end_turn', 'stop_sequence' => 'completo',
                'max_tokens' => 'max_tokens',
                default => 'otro',
            },
            'tokens_input' => data_get($json, 'usage.input_tokens'),
            'tokens_output' => data_get($json, 'usage.output_tokens'),
            'raw' => $json,
        ];
    }

    public function construirPayload(array $historial, string $mensaje, array $imagenes, ?string $systemPrompt = null, array $opciones = []): array
    {
        $this->exigirSoportePdf($imagenes);

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
            'max_tokens' => (int) $this->opcion($opciones, 'max_tokens', 'max_tokens', 4096),
            'messages' => $messages,
        ];

        // Claude no tiene modo JSON nativo: 'json' se ignora (el prompt ya lo pide).
        // Temperatura solo por llamada: este adaptador nunca leyó config_extra.temperature.
        if (array_key_exists('temperatura', $opciones) && $opciones['temperatura'] !== null) {
            $payload['temperature'] = (float) $opciones['temperatura'];
        }

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
