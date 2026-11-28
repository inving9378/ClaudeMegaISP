<?php

namespace App\Modules\Addons\VoIP\Services\Voz;

use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MegaVoz Fase 6 — transcripción de voz (OpenAI). La llave, la URL y el modelo salen de
 * Integraciones → Módulos IA (clave "voip.transcripcion") y se leen en CADA transcripción:
 * el bot de voz corre como proceso de días y así un cambio de llave aplica sin reiniciarlo.
 *
 * 27-nov: además de whisper-1 acepta gpt-4o-mini-transcribe y gpt-4o-transcribe. Con audio
 * de llamada (8 kHz) entienden mejor («con gusto te ayudo» donde Whisper oyó «con
 * gustayudo») y el mini cuesta la mitad. Esos no traen `no_speech_prob`: contra el silencio
 * queda el detector de voz de AudioSocketServer y la lista de frases inventadas.
 */
class VoiceSttService
{
    private const CLAVE_IA = 'voip.transcripcion';

    // Tarifa pública de OpenAI por minuto de audio.
    private const COSTO_POR_MINUTO_USD = ['whisper-1' => 0.006, 'gpt-4o-transcribe' => 0.006, 'gpt-4o-mini-transcribe' => 0.003];

    // Frases que Whisper "alucina" (viene de sus datos de entrenamiento —
    // subtítulos de YouTube) cuando el audio que recibe NO tiene habla real
    // — visto en vivo (24-sep-2026) con audio de línea real: "¡Suscríbete!"
    // y "Subtítulos realizados por la comunidad de Amara.org" sin que nadie
    // dijera nada. Segunda capa de defensa (la primera es `no_speech_prob`
    // abajo, que en teoría ya debería atrapar estos casos, pero un
    // blocklist barato no está de más contra los clásicos conocidos).
    private const FRASES_ALUCINADAS = [
        'subtítulos realizados por',
        'subtitulos realizados por',
        'amara.org',
        'suscríbete',
        'suscribete',
        'like and subscribe',
        'gracias por ver el video',
        'gracias por ver este video',
        'www.youtube.com',
    ];

    /**
     * @return array{success: bool, texto?: string, costo_usd?: float, error?: string}
     */
    public function transcribir(string $wavPath, string $idioma = 'es'): array
    {
        try {
            $c = IA::credencialesPara(self::CLAVE_IA);
        } catch (IANoConfigurada $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        if (! is_file($wavPath)) {
            return ['success' => false, 'error' => "Archivo no existe: {$wavPath}"];
        }

        $modelo = (string) $c['modelo'];

        try {
            $response = Http::withToken((string) $c['llave'])
                ->timeout(20)
                ->attach('file', file_get_contents($wavPath), basename($wavPath))
                ->post($c['base'] . '/audio/transcriptions', [
                    'model'           => $modelo,
                    'language'        => self::idioma($idioma),
                    // verbose_json trae `segments[].no_speech_prob`, la señal de Whisper para
                    // «esto no era habla» (silencio/ruido). Los gpt-4o solo aceptan json.
                    'response_format' => self::formatoPara($modelo),
                ]);

            if ($response->status() !== 200) {
                $error = $response->json('error.message') ?? $response->body();
                Log::warning("VoiceSttService: Whisper error {$response->status()}: {$error}");
                return ['success' => false, 'error' => "Whisper error {$response->status()}: {$error}"];
            }

            $texto = trim((string) $response->json('text', ''));
            $costo = round((self::COSTO_POR_MINUTO_USD[$modelo] ?? 0.006) * ($this->duracionSegundos($wavPath) / 60), 6);
            IA::registrarUsoIntegracion($c['integracion'], self::CLAVE_IA, $costo);

            if ($texto === '') {
                return ['success' => true, 'texto' => '', 'costo_usd' => $costo];
            }

            $segments = $response->json('segments', []);
            if (is_array($segments) && count($segments) > 0) {
                $noSpeechProbs = array_column($segments, 'no_speech_prob');
                $maxNoSpeech = $noSpeechProbs === [] ? 0.0 : max($noSpeechProbs);
                if ($maxNoSpeech >= 0.6) {
                    Log::info("VoiceSttService: descartado por no_speech_prob={$maxNoSpeech} — texto habría sido: \"{$texto}\"");
                    return ['success' => true, 'texto' => '', 'costo_usd' => $costo];
                }
            }

            if (self::esAlucinacion($texto)) {
                Log::info("VoiceSttService: descartado por frase alucinada conocida — texto: \"{$texto}\"");
                return ['success' => true, 'texto' => '', 'costo_usd' => $costo];
            }

            return [
                'success'   => true,
                'texto'     => $texto,
                'costo_usd' => $costo,
            ];
        } catch (\Throwable $e) {
            Log::warning('VoiceSttService: excepción — ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** Los gpt-4o-*-transcribe no aceptan verbose_json. */
    public static function formatoPara(string $modelo): string
    {
        return str_starts_with($modelo, 'gpt-4o') ? 'json' : 'verbose_json';
    }

    /** OpenAI quiere el código ISO de dos letras: «es-MX» → «es». */
    public static function idioma(?string $idioma): string
    {
        $codigo = strtolower(substr(trim((string) $idioma), 0, 2));

        return preg_match('/^[a-z]{2}$/', $codigo) ? $codigo : 'es';
    }

    /** ¿Es una de las frases que el modelo inventa con audio sin habla? */
    public static function esAlucinacion(string $texto): bool
    {
        $normalizado = mb_strtolower($texto);
        foreach (self::FRASES_ALUCINADAS as $frase) {
            if (str_contains($normalizado, $frase)) {
                return true;
            }
        }

        return false;
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
