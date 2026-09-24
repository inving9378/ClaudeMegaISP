<?php

namespace App\Modules\Addons\VoIP\Services\Voz;

use App\Services\Core\UsesApiIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MegaVoz Fase 6 — transcripción de voz (Whisper, OpenAI). No existía NINGÚN
 * STT en el repo antes de esto; la resolución de la API key SÍ existe (mismo
 * trait/proveedor 'openai' que ya usa OpenAiTtsDriver para TTS) — sin
 * credencial nueva que pedir.
 */
class VoiceSttService
{
    use UsesApiIntegration;

    private string $apiKey;
    private string $endpoint = 'https://api.openai.com/v1/audio/transcriptions';

    // Precio Whisper: $0.006 USD por minuto (tarifa pública de OpenAI).
    private const COSTO_POR_MINUTO_USD = 0.006;

    public function __construct(int $companyId = 1)
    {
        $this->apiKey = $this->resolveApiKey('openai', 'OPENAI_API_KEY', 'openai_api_key', $companyId) ?? '';
    }

    /**
     * @return array{success: bool, texto?: string, costo_usd?: float, error?: string}
     */
    public function transcribir(string $wavPath, string $idioma = 'es'): array
    {
        if ($this->apiKey === '') {
            return ['success' => false, 'error' => 'OpenAI API key no configurada (openai_api_key)'];
        }

        if (! is_file($wavPath)) {
            return ['success' => false, 'error' => "Archivo no existe: {$wavPath}"];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->attach('file', file_get_contents($wavPath), basename($wavPath))
                ->post($this->endpoint, [
                    'model'    => 'whisper-1',
                    'language' => $idioma,
                ]);

            if ($response->status() !== 200) {
                $error = $response->json('error.message') ?? $response->body();
                Log::warning("VoiceSttService: Whisper error {$response->status()}: {$error}");
                return ['success' => false, 'error' => "Whisper error {$response->status()}: {$error}"];
            }

            $texto = trim((string) $response->json('text', ''));

            return [
                'success'   => true,
                'texto'     => $texto,
                'costo_usd' => round(self::COSTO_POR_MINUTO_USD * ($this->duracionSegundos($wavPath) / 60), 6),
            ];
        } catch (\Throwable $e) {
            Log::warning('VoiceSttService: excepción — ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function duracionSegundos(string $wavPath): float
    {
        // WAV PCM: bytes de datos / (sample_rate * canales * bytes_por_muestra).
        // Evita depender de ffprobe solo para esto (ya se llama a ffmpeg aparte
        // para la conversión de TTS; aquí el archivo YA es PCM crudo conocido).
        $size = @filesize($wavPath) ?: 0;
        $header = 44; // cabecera WAV estándar
        $bytesAudio = max(0, $size - $header);
        return $bytesAudio / (8000 * 1 * 2);
    }
}
