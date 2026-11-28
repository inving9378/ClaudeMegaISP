<?php

namespace App\Modules\Addons\VoIP\Services\Voz;

use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\Marketing\Services\Tts\OpenAiTtsDriver;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * MegaVoz Fase 6 — voz de la asistente. Envuelve el driver de TTS que YA EXISTE
 * (OpenAiTtsDriver, Marketing) en vez de duplicar la llamada HTTP. Lo propio de aquí es la
 * conversión a PCM crudo 8kHz/mono/16-bit que AudioSocket necesita (OpenAI entrega MP3).
 *
 * 27-nov: la voz, el tono y la velocidad salen de la pantalla (ia_bot_config). Antes la voz
 * elegida no se usaba: siempre hablaba «nova». El tono son instrucciones de habla en español
 * de México; solo las entienden los modelos gpt-4o (con tts-1 se ignoran).
 */
class VoiceTtsService
{
    public const VOCES = [
        'nova'    => 'Nova (mujer, recomendada)',
        'shimmer' => 'Shimmer (mujer)',
        'alloy'   => 'Alloy (neutral)',
        'echo'    => 'Echo (hombre)',
        'onyx'    => 'Onyx (hombre, grave)',
        'fable'   => 'Fable (neutral)',
    ];

    public const TONOS = [
        'calida'    => ['Cálida', 'Habla en español de México, con un tono cálido, cercano y natural, como una persona amable de atención a clientes por teléfono. Ritmo ágil, sin sonar a grabación.'],
        'formal'    => ['Formal', 'Habla en español de México, con un tono formal, profesional y claro, como una ejecutiva de atención a clientes por teléfono.'],
        'alegre'    => ['Alegre', 'Habla en español de México, con un tono alegre y entusiasta pero profesional, como una asesora de ventas por teléfono.'],
        'tranquila' => ['Tranquila', 'Habla en español de México con calma y pausado, pronunciando con claridad los números y los datos.'],
    ];

    public const TONO_DEFECTO      = 'calida';
    public const VELOCIDAD_DEFECTO = 1.0;
    public const VELOCIDAD_MIN     = 0.75;
    public const VELOCIDAD_MAX     = 1.25;

    /** Sube cuando cambia cómo se procesa el audio: así los saludos ya guardados se rehacen. */
    private const VERSION_CACHE = 2;

    private OpenAiTtsDriver $driver;

    /**
     * Sin tipo a propósito: con `OpenAiTtsDriver $driver = new …(1, 'voip.voz')` el
     * contenedor de Laravel inyectaba su propio OpenAiTtsDriver (clave por defecto
     * «marketing.voz_videos») e ignoraba el valor por defecto, así que la asistente hablaba
     * —o fallaba— con la IA asignada a los videos de Marketing.
     */
    public function __construct($driver = null)
    {
        $this->driver = $driver instanceof OpenAiTtsDriver ? $driver : new OpenAiTtsDriver(1, 'voip.voz');
    }

    /**
     * Voz, velocidad y tono válidos. Acepta el nombre de la voz solo (como antes) o el arreglo
     * de IaBotConfig::opcionesVoz().
     *
     * @return array{voz: string, velocidad: float, tono: string}
     */
    public static function normalizarVoz($voz): array
    {
        $voz = is_array($voz) ? $voz : ['voz' => $voz];

        $nombre    = $voz['voz'] ?? null;
        $velocidad = isset($voz['velocidad']) && is_numeric($voz['velocidad']) ? (float) $voz['velocidad'] : self::VELOCIDAD_DEFECTO;
        $tono      = $voz['tono'] ?? null;

        return [
            'voz'       => is_string($nombre) && array_key_exists($nombre, self::VOCES) ? $nombre : 'nova',
            'velocidad' => round(min(self::VELOCIDAD_MAX, max(self::VELOCIDAD_MIN, $velocidad)), 2),
            'tono'      => is_string($tono) && array_key_exists($tono, self::TONOS) ? $tono : self::TONO_DEFECTO,
        ];
    }

    /** Opciones para OpenAiTtsDriver (las instrucciones el driver solo las manda a gpt-4o). */
    public static function opcionesDriver(array $voz): array
    {
        return [
            'voice'        => $voz['voz'],
            'speed'        => $voz['velocidad'],
            'instructions' => self::TONOS[$voz['tono']][1],
            'sin_duracion' => true,
            'timeout'      => 20,
        ];
    }

    /** El tono solo lo entienden los modelos gpt-4o; tts-1 no. */
    public static function soportaTono(?string $modelo): bool
    {
        return $modelo !== null && str_starts_with($modelo, 'gpt-4o');
    }

    /** Modelo de voz asignado hoy (para la pantalla y la llave de la caché), o null si no hay. */
    public static function modeloAsignado(): ?string
    {
        try {
            return (string) IA::credencialesPara('voip.voz')['modelo'];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{success: bool, pcm_path?: string, costo_usd?: float, error?: string}
     */
    public function sintetizar(string $texto, $voz = 'nova'): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return ['success' => false, 'error' => 'Texto vacío'];
        }

        $dir = $this->carpeta('');
        $slug = 'tts_' . md5($texto . json_encode($voz)) . '_' . uniqid();

        return $this->generarPcm($texto, self::normalizarVoz($voz), "{$dir}/{$slug}.mp3", "{$dir}/{$slug}.pcm");
    }

    /**
     * Como sintetizar(), pero partiendo el texto en oraciones que se piden en paralelo (27-nov):
     * el tiempo de la voz crece con el largo del texto (medido: 63 caracteres ≈ 1.3 s, 170 ≈ 3.4 s),
     * así que la respuesta queda lista en lo que tarda su oración más larga y no la suma. Las
     * oraciones se pegan en un solo audio, en orden.
     *
     * @return array{success: bool, pcm_path?: string, costo_usd?: float, error?: string}
     */
    public function sintetizarFrases(string $texto, $voz = 'nova'): array
    {
        $frases = self::partirEnFrases($texto);
        if (count($frases) <= 1) {
            return $this->sintetizar($texto, $voz);
        }

        $voz  = self::normalizarVoz($voz);
        $base = $this->carpeta('') . '/tts_' . md5($texto . json_encode($voz)) . '_' . uniqid();
        $items = [];
        foreach ($frases as $i => $frase) {
            $items[] = [$frase, "{$base}_{$i}.mp3"];
        }

        $resultados = $this->driver->synthesizeMany($items, self::opcionesDriver($voz));
        foreach ($resultados as $r) {
            if (! ($r['success'] ?? false)) {
                foreach ($items as [, $mp3]) {
                    @unlink($mp3);
                }
                return ['success' => false, 'error' => $r['error'] ?? 'TTS falló'];
            }
        }

        // Las conversiones también en paralelo.
        $procesos = [];
        foreach ($items as $i => [, $mp3]) {
            $procesos[$i] = $this->procesoPcm($mp3, "{$base}_{$i}.pcm");
            $procesos[$i]->start();
        }

        $pcm = '';
        $ok = true;
        foreach ($procesos as $i => $proceso) {
            $proceso->wait();
            $parte = "{$base}_{$i}.pcm";
            $ok = $ok && $proceso->isSuccessful() && is_file($parte) && filesize($parte) > 0;
            $pcm .= $ok ? (string) file_get_contents($parte) : '';
            @unlink($parte);
            @unlink($items[$i][1]);
        }
        if (! $ok) {
            return ['success' => false, 'error' => 'No se pudo convertir el audio a PCM'];
        }

        file_put_contents("{$base}.pcm", $pcm);

        return [
            'success'   => true,
            'pcm_path'  => "{$base}.pcm",
            'costo_usd' => array_sum(array_map(fn ($r) => (float) ($r['cost_usd'] ?? 0), $resultados)),
        ];
    }

    /**
     * «Claro, Francisco. Tienes un saldo de 449 pesos. ¿Te doy la referencia?» → tres oraciones.
     * Lo muy corto («Sí.») va con la siguiente; más de $max se juntan al final.
     *
     * @return string[]
     */
    public static function partirEnFrases(string $texto, int $max = 4): array
    {
        $partes = preg_split('/(?<=[.!?…])\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $frases = [];
        foreach ($partes as $parte) {
            $n = count($frases);
            if ($n > 0 && mb_strlen($frases[$n - 1]) < 10) {
                $frases[$n - 1] .= ' ' . $parte;
            } else {
                $frases[] = $parte;
            }
        }
        while (count($frases) > $max) {
            $ultima = array_pop($frases);
            $frases[count($frases) - 1] .= ' ' . $ultima;
        }

        return $frases;
    }

    /**
     * Variante CACHEADA para texto FIJO que se repite entre llamadas (saludo, frases de espera,
     * «¿sigues ahí?»). `AudioSocket()` cuelga si el primer audio tarda más de ~2 s, y el saludo
     * en frío (OpenAI + ffmpeg) tarda más: con la caché sale casi al instante. La llave lleva la
     * voz, el tono, la velocidad y el modelo: cambiar cualquiera en la pantalla genera otro audio.
     *
     * @return array{success: bool, pcm_path?: string, costo_usd?: float, error?: string, cacheado?: bool}
     */
    public function sintetizarCacheado(string $texto, $voz = 'nova'): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return ['success' => false, 'error' => 'Texto vacío'];
        }

        $voz = self::normalizarVoz($voz);
        $pcmPath = $this->rutaCache($texto, $voz);

        if ($this->sintetizarCacheadoSiExiste($texto, $voz) !== null) {
            return ['success' => true, 'pcm_path' => $pcmPath, 'costo_usd' => 0.0, 'cacheado' => true];
        }

        // MP3 con nombre propio: el precalentado y una llamada pueden generar el mismo audio a la vez.
        return $this->generarPcm($texto, $voz, substr($pcmPath, 0, -4) . '.' . uniqid('', true) . '.mp3', $pcmPath) + ['cacheado' => false];
    }

    /** El audio ya guardado de $texto con esta voz, o null si aún no existe (sin llamar a OpenAI). */
    public function sintetizarCacheadoSiExiste(string $texto, $voz = 'nova'): ?string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }
        $pcmPath = $this->rutaCache($texto, self::normalizarVoz($voz));
        if (! is_file($pcmPath) || filesize($pcmPath) === 0) {
            return null;
        }
        // Marca de uso (una vez al día): purgarCache() solo borra lo que nadie ha usado.
        if (filemtime($pcmPath) < time() - 86400) {
            @touch($pcmPath);
        }

        return $pcmPath;
    }

    /** Borra los audios guardados que nadie usa hace $dias días (voces viejas, saludos con nombre). */
    public function purgarCache(int $dias = 60): int
    {
        $borrados = 0;
        foreach (glob($this->carpeta('/cache') . '/*.pcm') ?: [] as $archivo) {
            if (@filemtime($archivo) < time() - $dias * 86400 && @unlink($archivo)) {
                $borrados++;
            }
        }

        return $borrados;
    }

    private function rutaCache(string $texto, array $voz): string
    {
        $slug = md5(implode('|', [self::VERSION_CACHE, $texto, $voz['voz'], $voz['velocidad'], $voz['tono'], self::modeloAsignado()]));

        return $this->carpeta('/cache') . "/{$slug}.pcm";
    }

    /**
     * MP3 para escuchar desde el navegador (pantalla de la asistente).
     *
     * @return array{ok: true, mp3: string, costo_usd: float}|array{ok: false, error: string}
     */
    public function muestraMp3(string $texto, $voz): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return ['ok' => false, 'error' => 'No hay nada que decir.'];
        }

        $mp3Path = $this->carpeta('') . '/muestra_' . uniqid() . '.mp3';
        $r = $this->driver->synthesize($texto, $mp3Path, self::opcionesDriver(self::normalizarVoz($voz)));
        if (! ($r['success'] ?? false)) {
            @unlink($mp3Path);
            return ['ok' => false, 'error' => $r['error'] ?? 'No se pudo generar la voz.'];
        }

        $mp3 = (string) @file_get_contents($mp3Path);
        @unlink($mp3Path);

        return ['ok' => true, 'mp3' => $mp3, 'costo_usd' => (float) ($r['cost_usd'] ?? 0)];
    }

    /**
     * MP3 → PCM crudo (sin cabecera) 8kHz/mono/16-bit signed LE, lo que AudioSocket manda
     * directo como frames. -af loudnorm: el MP3 de OpenAI viene muy bajo (medido el 24-sep:
     * ~27% del rango) y en una llamada mu-law lo bajo además suena con estática. loudnorm
     * lo deja a -16 LUFS con techo de -1.5 dB, sin recortar.
     */
    private function procesoPcm(string $mp3Path, string $pcmPath): Process
    {
        $proceso = new Process([
            'ffmpeg', '-y', '-hide_banner', '-loglevel', 'error', '-i', $mp3Path,
            '-af', 'loudnorm=I=-16:TP=-1.5:LRA=11',
            '-ar', '8000', '-ac', '1', '-f', 's16le', '-acodec', 'pcm_s16le',
            $pcmPath,
        ]);
        $proceso->setTimeout(15);

        return $proceso;
    }

    private function carpeta(string $sub): string
    {
        $dir = storage_path('app/megavoz-bot-audio' . $sub);
        if (! is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        return $dir;
    }

    /** @return array{success: bool, pcm_path?: string, costo_usd?: float, error?: string} */
    private function generarPcm(string $texto, array $voz, string $mp3Path, string $pcmPath): array
    {
        $resultado = $this->driver->synthesize($texto, $mp3Path, self::opcionesDriver($voz));
        if (! ($resultado['success'] ?? false)) {
            @unlink($mp3Path);
            return ['success' => false, 'error' => $resultado['error'] ?? 'TTS falló'];
        }

        // Se escribe aparte y se renombra al final: quien lea la caché nunca ve un audio a medias.
        $parcial = $pcmPath . '.' . uniqid('', true) . '.part';
        $proceso = $this->procesoPcm($mp3Path, $parcial);
        $proceso->run();

        @unlink($mp3Path);

        if (! $proceso->isSuccessful() || ! is_file($parcial) || filesize($parcial) === 0 || ! @rename($parcial, $pcmPath)) {
            @unlink($parcial);
            Log::warning('VoiceTtsService: ffmpeg no generó PCM — ' . $proceso->getErrorOutput());
            return ['success' => false, 'error' => 'No se pudo convertir el audio a PCM'];
        }

        return [
            'success'   => true,
            'pcm_path'  => $pcmPath,
            'costo_usd' => (float) ($resultado['cost_usd'] ?? 0),
        ];
    }
}
