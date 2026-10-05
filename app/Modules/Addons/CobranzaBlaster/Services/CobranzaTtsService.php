<?php

namespace App\Modules\Addons\CobranzaBlaster\Services;

use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Voz de las llamadas de cobranza. La llave, la URL y el modelo salen de
 * Integraciones → Módulos IA (clave "cobranza.voz") y se leen en CADA audio:
 * cambiar la llave aplica de inmediato aunque el servicio sea singleton.
 * Sin asignación no hay voz (se registra el motivo y la llamada no se arma).
 */
class CobranzaTtsService
{
    private const CLAVE_IA = 'cobranza.voz';

    protected string $audioBasePath;
    protected string $voice;

    public function __construct()
    {
        $this->voice         = config('cobranza.blaster_tts_voice');
        $this->audioBasePath = config('cobranza.blaster_audio_base_path');
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
            . "Gracias por su atención.";
    }

    private function separarDigitos(string $valor): string
    {
        // "12345" → "1 2 3 4 5" para que TTS pronuncie dígito a dígito
        return implode(' ', str_split(preg_replace('/\D/', '', $valor)));
    }

    private function callOpenAiTts(string $texto, string $destino): bool
    {
        try {
            $c = IA::credencialesPara(self::CLAVE_IA);
        } catch (IANoConfigurada $e) {
            Log::error('CobranzaTtsService: ' . $e->getMessage());
            return false;
        }

        try {
            $response = Http::withToken((string) $c['llave'])
                ->timeout(30)
                ->post($c['base'] . '/audio/speech', [
                    'model'          => $c['modelo'],
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

            // tts-1: $0.015 / 1000 caracteres (tts-1-hd el doble)
            $costo = (mb_strlen($texto) / 1000) * ($c['modelo'] === 'tts-1-hd' ? 0.030 : 0.015);
            IA::registrarUsoIntegracion($c['integracion'], self::CLAVE_IA, round($costo, 6));
            return true;

        } catch (\Throwable $e) {
            Log::error('OpenAI TTS excepción', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function convertToWav(string $mp3, string $wav): void
    {
        // Asterisk requiere WAV 8kHz mono 16-bit (ulaw compatible)
        @mkdir(dirname($wav), 0755, true);
        exec("ffmpeg -y -i {$mp3} -ar 8000 -ac 1 -acodec pcm_s16le {$wav} 2>/dev/null");
    }
}
