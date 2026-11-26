<?php

namespace App\Modules\Addons\CobranzaBlaster\Services;

use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Voz de las llamadas de Campañas/Blaster. La llave, la URL y el modelo salen de
 * Integraciones → Módulos IA (clave "cobranza.voz") y se leen en CADA audio:
 * cambiar la llave aplica de inmediato aunque el servicio sea singleton.
 * Sin asignación no hay voz (se registra el motivo y la llamada no se arma).
 *
 * Desde el 24-nov cada campaña elige voz, velocidad y tono, y se escucha desde el alta
 * (muestra()). El tono son instrucciones de habla: solo las entienden los modelos gpt-4o.
 */
class CobranzaTtsService
{
    private const CLAVE_IA = 'cobranza.voz';

    public const VOCES = [
        'nova'    => 'Nova (mujer, recomendada)',
        'shimmer' => 'Shimmer (mujer)',
        'alloy'   => 'Alloy (neutral)',
        'echo'    => 'Echo (hombre)',
        'onyx'    => 'Onyx (hombre, grave)',
        'fable'   => 'Fable (neutral)',
    ];

    public const TONOS = [
        'amable'    => ['Amable', 'Habla en español de México con un tono amable, cálido y cordial, sin prisa.'],
        'formal'    => ['Formal', 'Habla en español de México con un tono formal, profesional y claro.'],
        'firme'     => ['Firme', 'Habla en español de México con un tono firme y serio, respetuoso pero directo.'],
        'alegre'    => ['Alegre', 'Habla en español de México con un tono alegre y entusiasta.'],
        'tranquilo' => ['Tranquilo', 'Habla en español de México con calma y pausado, pronunciando los números con claridad.'],
    ];

    public const VELOCIDAD_DEFECTO = 0.95;
    public const VELOCIDAD_MIN     = 0.5;
    public const VELOCIDAD_MAX     = 1.5;

    /** Mensaje de cobranza de siempre; la nota de la campaña lo reemplaza y puede usar las mismas variables. */
    public const PLANTILLA_COBRANZA = 'Estimado {nombre}, le habla Meganet Telecomunicaciones. '
        . 'Su saldo vencido es de {saldo} pesos. '
        . 'Le solicitamos realizar su pago antes del {fecha}. '
        . 'Su número de cliente es {numero_cliente}. '
        . 'Gracias por su atención.';

    /** Datos de ejemplo para escuchar la nota desde el alta (no son de ningún cliente). */
    public const EJEMPLO = ['nombre' => 'Juan Pérez', 'monto' => 350.0, 'numero_cliente' => '12345'];

    protected string $audioBasePath;
    protected string $voice;

    public function __construct()
    {
        $this->voice         = (string) config('cobranza.blaster_tts_voice', 'nova');
        $this->audioBasePath = (string) config('cobranza.blaster_audio_base_path');
    }

    /** Audio de una llamada (uno por llamada: el texto lleva los datos del cliente). */
    public function generateAudio(int $llamadaId, string $texto, array $voz = []): string
    {
        // El nombre lleva el texto: si entre intentos cambia el saldo, se genera de nuevo
        // (antes se reusaba el primer audio con el saldo viejo).
        $cacheKey   = "blaster_{$llamadaId}_" . substr(md5($texto . '|' . json_encode($voz)), 0, 10);
        $outputPath = $this->audioBasePath . $cacheKey . '.wav';

        if (file_exists($outputPath)) {
            return $outputPath;
        }
        foreach (glob($this->audioBasePath . "blaster_{$llamadaId}_*.wav") ?: [] as $anterior) {
            @unlink($anterior);
        }

        $mp3Path = sys_get_temp_dir() . "/{$cacheKey}.mp3";
        $success = $this->callOpenAiTts($texto, $mp3Path, $voz);

        if (!$success) {
            Log::error("CobranzaTtsService: falló TTS para llamada #{$llamadaId}");
            return '';
        }

        $this->convertToWav($mp3Path, $outputPath);
        @unlink($mp3Path);

        return file_exists($outputPath) ? $outputPath : '';
    }

    /** Un solo audio para todas las llamadas con el mismo texto y la misma voz. */
    public function generateAudioCached(string $slot, string $texto, array $voz = []): string
    {
        $voz        = $voz === [] ? [] : $this->normalizarVoz($voz);
        $slug       = 'slot_' . md5($voz === [] ? $texto : $texto . '|' . json_encode($voz));
        $outputPath = $this->audioBasePath . $slug . '.wav';

        if (file_exists($outputPath)) {
            return $outputPath;
        }

        $mp3Path = sys_get_temp_dir() . "/{$slug}.mp3";
        $success = $this->callOpenAiTts($texto, $mp3Path, $voz);

        if (!$success) {
            return '';
        }

        $this->convertToWav($mp3Path, $outputPath);
        @unlink($mp3Path);

        return file_exists($outputPath) ? $outputPath : '';
    }

    /**
     * Texto de una llamada de cobranza: la nota de la campaña (o el mensaje de siempre si no
     * hay nota) con {nombre}, {saldo}, {fecha} y {numero_cliente} del cliente.
     */
    public function textoCobranza(?string $nota, string $nombre, float $monto, string $fecha, string $numCliente): string
    {
        $plantilla = trim((string) $nota) !== '' ? trim((string) $nota) : self::PLANTILLA_COBRANZA;

        return strtr($plantilla, [
            '{nombre}'         => $nombre,
            '{saldo}'          => number_format($monto, 2),
            '{fecha}'          => $fecha,
            '{numero_cliente}' => $this->separarDigitos($numCliente),
        ]);
    }

    /** La nota con los datos de ejemplo, para escucharla desde el alta. */
    public function textoDeEjemplo(string $nota, string $tipo, string $fecha): string
    {
        if ($tipo !== 'cobranza') {
            return trim($nota);
        }

        return $this->textoCobranza($nota, self::EJEMPLO['nombre'], self::EJEMPLO['monto'], $fecha, self::EJEMPLO['numero_cliente']);
    }

    /**
     * MP3 para escuchar desde el navegador.
     *
     * @return array{ok: true, mp3: string}|array{ok: false, error: string}
     */
    public function muestra(string $texto, array $voz): array
    {
        return $this->pedirAudio($texto, $voz);
    }

    /** Lo que el alta necesita para armar los controles de voz. */
    public function opciones(): array
    {
        $modelo = null;
        $motivo = null;
        try {
            $modelo = (string) IA::credencialesPara(self::CLAVE_IA)['modelo'];
        } catch (IANoConfigurada $e) {
            $motivo = $e->getMessage();
        } catch (\Throwable $e) {
            $motivo = 'No se pudo leer la voz asignada en Integraciones → Módulos IA.';
        }

        return [
            'asignada'     => $modelo !== null,
            'motivo'       => $motivo,
            'modelo'       => $modelo,
            'soporta_tono' => self::soportaTono($modelo),
            'voces'        => collect(self::VOCES)->map(fn ($e, $id) => ['id' => $id, 'etiqueta' => $e])->values()->all(),
            'tonos'        => collect(self::TONOS)->map(fn ($t, $id) => ['id' => $id, 'etiqueta' => $t[0]])->values()->all(),
            'voz'          => array_key_exists($this->voice, self::VOCES) ? $this->voice : 'nova',
            'velocidad'    => ['defecto' => self::VELOCIDAD_DEFECTO, 'min' => self::VELOCIDAD_MIN, 'max' => self::VELOCIDAD_MAX],
            'plantilla'    => self::PLANTILLA_COBRANZA,
        ];
    }

    /** El tono (instrucciones de habla) solo lo entienden los modelos gpt-4o; tts-1 no. */
    public static function soportaTono(?string $modelo): bool
    {
        return $modelo !== null && str_starts_with($modelo, 'gpt-4o');
    }

    /** Cuerpo de /audio/speech con la voz de la campaña. */
    public function cuerpo(string $texto, string $modelo, array $voz): array
    {
        $voz    = $this->normalizarVoz($voz);
        $cuerpo = [
            'model'           => $modelo,
            'input'           => $texto,
            'voice'           => $voz['voz'],
            'response_format' => 'mp3',
            'speed'           => $voz['velocidad'],
        ];

        if ($voz['tono'] !== null && self::soportaTono($modelo)) {
            $cuerpo['instructions'] = self::TONOS[$voz['tono']][1];
        }

        return $cuerpo;
    }

    /** @return array{voz: string, velocidad: float, tono: ?string} */
    private function normalizarVoz(array $voz): array
    {
        $nombre    = $voz['voz'] ?? null;
        $velocidad = isset($voz['velocidad']) && is_numeric($voz['velocidad']) ? (float) $voz['velocidad'] : self::VELOCIDAD_DEFECTO;
        $tono      = $voz['tono'] ?? null;

        return [
            'voz'       => is_string($nombre) && array_key_exists($nombre, self::VOCES) ? $nombre : $this->voice,
            'velocidad' => round(min(self::VELOCIDAD_MAX, max(self::VELOCIDAD_MIN, $velocidad)), 2),
            'tono'      => is_string($tono) && array_key_exists($tono, self::TONOS) ? $tono : null,
        ];
    }

    private function separarDigitos(string $valor): string
    {
        // "12345" → "1 2 3 4 5" para que TTS pronuncie dígito a dígito
        return implode(' ', str_split(preg_replace('/\D/', '', $valor)));
    }

    private function callOpenAiTts(string $texto, string $destino, array $voz = []): bool
    {
        $r = $this->pedirAudio($texto, $voz);
        if (!$r['ok']) {
            return false;
        }

        @mkdir(dirname($destino), 0755, true);
        file_put_contents($destino, $r['mp3']);

        return true;
    }

    /** @return array{ok: true, mp3: string}|array{ok: false, error: string} */
    private function pedirAudio(string $texto, array $voz): array
    {
        try {
            $c = IA::credencialesPara(self::CLAVE_IA);
        } catch (IANoConfigurada $e) {
            Log::error('CobranzaTtsService: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        try {
            $response = Http::withToken((string) $c['llave'])
                ->timeout(30)
                ->post($c['base'] . '/audio/speech', $this->cuerpo($texto, (string) $c['modelo'], $voz));

            if (!$response->successful()) {
                Log::error('OpenAI TTS error', ['status' => $response->status(), 'body' => $response->body()]);
                return ['ok' => false, 'error' => "El servicio de voz respondió {$response->status()}: "
                    . ($response->json('error.message') ?? 'sin detalle')];
            }

            // tts-1: $0.015 / 1000 caracteres (tts-1-hd el doble)
            $costo = (mb_strlen($texto) / 1000) * ($c['modelo'] === 'tts-1-hd' ? 0.030 : 0.015);
            IA::registrarUsoIntegracion($c['integracion'], self::CLAVE_IA, round($costo, 6));

            return ['ok' => true, 'mp3' => $response->body()];

        } catch (\Throwable $e) {
            Log::error('OpenAI TTS excepción', ['error' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'No se pudo generar la voz. Revisa la asignación en Integraciones → Módulos IA.'];
        }
    }

    private function convertToWav(string $mp3, string $wav): void
    {
        // Asterisk requiere WAV 8kHz mono 16-bit (ulaw compatible). La carpeta la crea
        // «Instalar dependencias» (grupo asterisk, con escritura para la app); si no está
        // o no se puede escribir, se dice en el log en vez de fallar callado (25-nov).
        $dir = dirname($wav);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            Log::error("CobranzaTtsService: no existe la carpeta de audios {$dir} y no se pudo crear; corre «Instalar dependencias» de VoIP.");
            return;
        }
        if (!is_writable($dir)) {
            Log::error("CobranzaTtsService: no se puede escribir en la carpeta de audios {$dir}; corre «Instalar dependencias» de VoIP.");
            return;
        }

        exec('ffmpeg -y -i ' . escapeshellarg($mp3) . ' -ar 8000 -ac 1 -acodec pcm_s16le ' . escapeshellarg($wav) . ' 2>&1', $salida, $codigo);
        if ($codigo !== 0) {
            @unlink($wav);
            Log::error('CobranzaTtsService: ffmpeg no pudo convertir la voz a WAV (código ' . $codigo . ')', [
                'salida' => implode("\n", array_slice($salida, -5)),
            ]);
            return;
        }
        // Asterisk lo lee por el grupo de la carpeta.
        @chmod($wav, 0644);
    }

    /**
     * Borra los audios de llamadas de más de $dias días (25-nov): sale uno por llamada y
     * sin esto llenaban el disco. Si una llamada vieja se reintenta, se genera de nuevo.
     */
    public function limpiarAudiosViejos(int $dias = 3): void
    {
        $limite = time() - $dias * 86400;
        foreach (glob($this->audioBasePath . 'blaster_*.wav') ?: [] as $archivo) {
            if (@filemtime($archivo) < $limite) {
                @unlink($archivo);
            }
        }
    }
}
