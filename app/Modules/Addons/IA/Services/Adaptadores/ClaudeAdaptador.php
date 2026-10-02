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
