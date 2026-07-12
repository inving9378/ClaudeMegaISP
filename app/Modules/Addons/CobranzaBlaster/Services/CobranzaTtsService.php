<?php

namespace App\Modules\Addons\CobranzaBlaster\Services;

use App\Services\Core\ApiIntegrationService;
use App\Services\Core\UsesApiIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Credencial OpenAI resuelta vía el Hub de integraciones (api_integrations,
 * mismo mecanismo que Marketing\Services\Tts\OpenAiTtsDriver), no env() directo.
 * Prioridad: Hub → env('OPENAI_API_KEY') como respaldo. #273.
 */
class CobranzaTtsService
{
    use UsesApiIntegration;

    protected string $audioBasePath = '/var/lib/asterisk/sounds/cobranza/';
    protected string $voice;

    public function __construct()
    {
        $this->voice = env('BLASTER_TTS_VOICE', 'nova');
    }

    public function generateAudio(
        int $llamadaId,
        string $nombre,
        float $monto,
        string $fecha,
        string $numCliente
    ): string {
        $cacheKey  = "blaster_{$llamadaId}";
        $outputPath = $this->audioBasePath . $cacheKey . '.wav';

        if (file_exists($outputPath)) {
            return $outputPath;
        }

        $mensaje = $this->buildMensaje($nombre, $monto, $fecha, $numCliente);

        $mp3Path = sys_get_temp_dir() . "/{$cacheKey}.mp3";
        $success = $this->callOpenAiTts($mensaje, $mp3Path);

        if (!$success) {
            Log::error("CobranzaTtsService: falló TTS para llamada #{$llamadaId}");
            return '';
        }

        $this->convertToWav($mp3Path, $outputPath);
        @unlink($mp3Path);

        return file_exists($outputPath) ? $outputPath : '';
    }

    public function generateAudioCached(string $slot, string $texto): string
    {
        $slug      = 'slot_' . md5($texto);
        $outputPath = $this->audioBasePath . $slug . '.wav';

        if (file_exists($outputPath)) {
            return $outputPath;
        }

        $mp3Path = sys_get_temp_dir() . "/{$slug}.mp3";
        $success = $this->callOpenAiTts($texto, $mp3Path);

        if (!$success) {
            return '';
        }

        $this->convertToWav($mp3Path, $outputPath);
        @unlink($mp3Path);

        return file_exists($outputPath) ? $outputPath : '';
    }

    private function buildMensaje(string $nombre, float $monto, string $fecha, string $numCliente): string
    {
        $montoFormateado  = number_format($monto, 2);
        $numClienteTts    = $this->separarDigitos($numCliente);

        return "Estimado {$nombre}, le habla Meganet Telecomunicaciones. "
            . "Su saldo vencido es de {$montoFormateado} pesos. "
            . "Le solicitamos realizar su pago antes del {$fecha}. "
            . "Su número de cliente es {$numClienteTts}. "
            . "Para hablar con un asesor presione uno.";
    }

    private function separarDigitos(string $valor): string
    {
        // "12345" → "1 2 3 4 5" para que TTS pronuncie dígito a dígito
        return implode(' ', str_split(preg_replace('/\D/', '', $valor)));
    }

    private function callOpenAiTts(string $texto, string $destino): bool
    {
        $apiKey = $this->resolveApiKey('openai', 'OPENAI_API_KEY');

        if (empty($apiKey)) {
            Log::error('CobranzaTtsService: sin API key de OpenAI configurada (Hub api_integrations ni env)');
            return false;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/audio/speech', [
                    'model'          => 'tts-1',
                    'input'          => $texto,
                    'voice'          => $this->voice,
                    'response_format'=> 'mp3',
                    'speed'          => 0.95,
                ]);

            if (!$response->successful()) {
                Log::error('OpenAI TTS error', ['status' => $response->status(), 'body' => $response->body()]);
                return false;
            }

            @mkdir(dirname($destino), 0755, true);
            file_put_contents($destino, $response->body());
            $this->trackUsage($texto);
            return true;

        } catch (\Throwable $e) {
            Log::error('OpenAI TTS excepción', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /** Telemetría de costo/uso en el Hub central (api_integrations_usage). Best-effort. */
    private function trackUsage(string $texto): void
    {
        $integration = $this->resolveApiIntegration('openai');
        if (!$integration) {
            return;
        }

        $costUsd = (mb_strlen($texto) / 1000) * 0.015; // tts-1: $0.015 / 1000 chars
        ApiIntegrationService::instance()->trackUsage($integration, 'cobranza_blaster_tts', 1, round($costUsd, 6));
    }

    private function convertToWav(string $mp3, string $wav): void
    {
        // Asterisk requiere WAV 8kHz mono 16-bit (ulaw compatible)
        @mkdir(dirname($wav), 0755, true);
        exec("ffmpeg -y -i {$mp3} -ar 8000 -ac 1 -acodec pcm_s16le {$wav} 2>/dev/null");
    }
}
