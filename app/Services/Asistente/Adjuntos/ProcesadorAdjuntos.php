<?php

namespace App\Services\Asistente\Adjuntos;

use App\Models\Asistente\Adjunto;
use App\Services\Fotos\FotoComunService;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManagerStatic as Image;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Prepara cada archivo para que JARVIS lo entienda, una sola vez al subirlo:
 *  - foto: una vista reducida (la que ve la IA) y una miniatura (la del chat);
 *  - PDF: su texto; si es escaneado (sin texto), imágenes de sus primeras páginas;
 *  - Excel/CSV: hojas con columnas y filas (HojaDeCalculo);
 *  - Word y texto: su texto;
 *  - video: cuadros repartidos en todo el video + lo que se oye;
 *  - audio: lo que se oye (transcripción con la IA de voz del sistema).
 * Devuelve lo que se guarda en `meta`, con un `resumen` corto para el chat.
 */
class ProcesadorAdjuntos
{
    private const MAX_CUADROS = 8;

    private const MAX_PAGINAS_COMO_IMAGEN = 10;

    private const SEGUNDOS_A_TRANSCRIBIR = 300;

    private const MAX_CARACTERES_TEXTO = 2000000;

    public function __construct(private HojaDeCalculo $hojas)
    {
    }

    public function procesar(Adjunto $a): array
    {
        return match ($a->tipo) {
            'imagen'    => $this->imagen($a),
            'pdf'       => $this->pdf($a),
            'hoja'      => $this->hoja($a),
            'documento' => $this->documento($a),
            'video'     => $this->video($a),
            'audio'     => $this->audio($a),
            default     => throw new AdjuntoRechazado('Ese tipo de archivo no lo puedo leer.'),
        };
    }

    private function imagen(Adjunto $a): array
    {
        $origen = $a->original();
        if (in_array($a->extension, ['heic', 'heif'], true)) {
            $origen = $this->desdeHeic($a);
        }

        try {
            $img = Image::make($origen)->orientate();
            [$ancho, $alto] = [$img->width(), $img->height()];
            $img->destroy();
        } catch (\Throwable) {
            throw new AdjuntoRechazado('No pude abrir esa foto. ¿Me la mandas como JPG o PNG?');
        }

        $vista = FotoComunService::paraIa((string) file_get_contents($origen), (string) $a->mime);
        if (!in_array($vista['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            $vista = ['mime' => 'image/jpeg', 'data' => base64_encode((string) Image::make($origen)->orientate()->encode('jpg', 85))];
        }
        $archivoVista = 'vista.' . ($vista['mime'] === 'image/jpeg' ? 'jpg' : substr($vista['mime'], 6));
        file_put_contents($a->ruta($archivoVista), base64_decode($vista['data']));
        $this->miniatura($origen, $a->ruta('miniatura.jpg'));

        return [
            'resumen' => "Foto · {$ancho}×{$alto}",
            'ancho'   => $ancho,
            'alto'    => $alto,
            'vista'   => ['archivo' => $archivoVista, 'mime' => $vista['mime']],
        ];
    }

    private function desdeHeic(Adjunto $a): string
    {
        $destino = $a->ruta('convertida.jpg');
        foreach ([[$this->bin('heif-convert'), $a->original(), $destino], [$this->bin('ffmpeg'), '-y', '-loglevel', 'error', '-i', $a->original(), $destino]] as $cmd) {
            if ($cmd[0] && $this->correr($cmd, 60) && is_file($destino)) {
                return $destino;
            }
        }

        throw new AdjuntoRechazado('No pude abrir esa foto del iPhone (HEIC). ¿Me la mandas como JPG?');
    }

    private function pdf(Adjunto $a): array
    {
        $paginas = null;
        if ($this->bin('pdfinfo')) {
            $p = new Process([$this->bin('pdfinfo'), $a->original()]);
            $p->setTimeout(30)->run();
            if (preg_match('/^Pages:\s+(\d+)/m', $p->getOutput(), $m)) {
                $paginas = (int) $m[1];
            }
            if (!$p->isSuccessful() && str_contains($p->getErrorOutput(), 'Incorrect password')) {
                throw new AdjuntoRechazado('Ese PDF tiene contraseña y no lo puedo abrir.');
            }
        }

        $texto = '';
        if ($this->bin('pdftotext')) {
            $this->correr([$this->bin('pdftotext'), '-layout', '-enc', 'UTF-8', $a->original(), $a->ruta('texto.txt')], 90);
            $texto = is_file($a->ruta('texto.txt')) ? (string) file_get_contents($a->ruta('texto.txt')) : '';
        }
        $texto = $this->guardarTexto($a, $texto);

        // Escaneado (sin texto): se guardan sus primeras páginas como imágenes para poder leerlo.
        $imagenes = [];
        if (mb_strlen(trim($texto)) < 20 && $this->bin('pdftoppm')) {
            $this->correr([$this->bin('pdftoppm'), '-jpeg', '-r', '110', '-l', (string) self::MAX_PAGINAS_COMO_IMAGEN, '-scale-to', '1600', $a->original(), $a->ruta('pagina')], 120);
            foreach (glob($a->ruta('pagina-*.jpg')) ?: [] as $f) {
                $imagenes[] = basename($f);
            }
            sort($imagenes, SORT_NATURAL);
        }

        $resumen = 'PDF' . ($paginas ? ' · ' . $paginas . ($paginas === 1 ? ' página' : ' páginas') : '') . ($imagenes ? ' · escaneado' : '');

        return [
            'resumen'    => $resumen,
            'paginas'    => $paginas,
            'caracteres' => mb_strlen($texto),
            'escaneado'  => (bool) $imagenes,
            'imagenes'   => $imagenes,
        ];
    }

    private function hoja(Adjunto $a): array
    {
        try {
            $hojas = $this->hojas->leer($a->original(), (string) $a->extension);
        } catch (\Throwable $e) {
            Log::warning('[JARVIS] no se pudo leer la hoja', ['adjunto' => $a->id, 'error' => $e->getMessage()]);

            throw new AdjuntoRechazado('No pude abrir ese archivo de Excel. Si tiene contraseña, quítasela; si no, guárdalo de nuevo como .xlsx o .csv.');
        }
        if (!$hojas) {
            throw new AdjuntoRechazado('Ese archivo de Excel está vacío.');
        }

        $indice = [];
        foreach ($hojas as $i => $h) {
            $n = $i + 1;
            file_put_contents($a->ruta("hoja-{$n}.json"), json_encode($h, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
            $indice[] = [
                'n'         => $n,
                'nombre'    => $h['nombre'],
                'filas'     => count($h['filas']),
                'columnas'  => $h['columnas'],
                'recortada' => $h['recortada'],
            ];
        }
        $filas = array_sum(array_column($indice, 'filas'));
        $etiqueta = in_array($a->extension, ['csv', 'tsv'], true) ? 'CSV' : 'Excel';

        return [
            'resumen' => $etiqueta . (count($hojas) > 1 ? ' · ' . count($hojas) . ' hojas' : '') . ' · ' . number_format($filas) . ($filas === 1 ? ' fila' : ' filas'),
            'hojas'   => $indice,
        ];
    }

    private function documento(Adjunto $a): array
    {
        $texto = match ($a->extension) {
            'docx', 'odt', 'rtf' => $this->textoDeWord($a),
            'html', 'htm'        => html_entity_decode(strip_tags(preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', (string) file_get_contents($a->original()))), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            default              => (string) file_get_contents($a->original()),
        };
        if (!mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
        }
        $texto = $this->guardarTexto($a, preg_replace("/[ \t]+\n/", "\n", $texto));
        if (trim($texto) === '') {
            throw new AdjuntoRechazado('Ese documento no tiene texto que pueda leer.');
        }

        return ['resumen' => 'Documento · ' . number_format(mb_strlen($texto)) . ' caracteres', 'caracteres' => mb_strlen($texto)];
    }

    /**
     * Texto de Word/LibreOffice sin librerías: .docx y .odt son un ZIP con el documento en XML
     * (así no depende de que el servidor tenga instalado PhpWord). RTF se limpia de sus códigos.
     */
    private function textoDeWord(Adjunto $a): string
    {
        if ($a->extension === 'rtf') {
            $rtf = (string) file_get_contents($a->original());
            $rtf = preg_replace_callback("/\\\\'([0-9a-f]{2})/i", fn ($m) => mb_convert_encoding(chr(hexdec($m[1])), 'UTF-8', 'Windows-1252'), $rtf);
            $rtf = preg_replace('/\\\\(par|line)\b ?/', "\n", $rtf);
            $rtf = preg_replace('/\{\\\\\*[^{}]*\}|\\\\[a-z]+-?\d* ?|[{}]/i', '', $rtf);

            return trim($rtf);
        }

        $zip = new \ZipArchive();
        if ($zip->open($a->original()) !== true) {
            throw new AdjuntoRechazado('No pude abrir ese documento. Guárdalo de nuevo como .docx o mándalo en PDF.');
        }
        $xml = (string) $zip->getFromName($a->extension === 'odt' ? 'content.xml' : 'word/document.xml');
        $zip->close();
        if ($xml === '') {
            throw new AdjuntoRechazado('Ese documento no tiene texto que pueda leer.');
        }

        // Fin de párrafo, renglón y celda → saltos; tabulador → tab; lo demás, solo el texto.
        $xml = preg_replace(['#</w:p>|</text:p>|</text:h>|<w:br\s*/>|<text:line-break\s*/>#', '#</w:tc>|</table:table-cell>#', '#<w:tab\s*/>|<text:tab\s*/>#'], ["\n", ' | ', "\t"], $xml);

        return trim(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    private function video(Adjunto $a): array
    {
        if (!$this->bin('ffmpeg') || !$this->bin('ffprobe')) {
            throw new AdjuntoRechazado('En este servidor todavía no puedo ver videos. Mándame fotos o capturas del video.');
        }
        $info = $this->sonda($a->original());
        $duracion = (float) ($info['duracion'] ?? 0);

        $n = $duracion > 0 ? (int) max(1, min(self::MAX_CUADROS, ceil($duracion / 4))) : 1;
        $cuadros = [];
        for ($i = 0; $i < $n; $i++) {
            $segundo = $duracion > 0 ? round($duracion * ($i + 0.5) / $n, 1) : 0;
            $archivo = 'cuadro-' . ($i + 1) . '.jpg';
            $ok = $this->correr([$this->bin('ffmpeg'), '-y', '-loglevel', 'error', '-ss', (string) $segundo, '-i', $a->original(), '-frames:v', '1', '-vf', "scale='min(1024,iw)':-2", '-q:v', '4', $a->ruta($archivo)], 60);
            if ($ok && is_file($a->ruta($archivo))) {
                $cuadros[] = ['archivo' => $archivo, 'segundo' => $segundo];
            }
        }
        if (!$cuadros) {
            throw new AdjuntoRechazado('No pude abrir ese video. ¿Me lo mandas en MP4?');
        }
        $this->miniatura($a->ruta($cuadros[0]['archivo']), $a->ruta('miniatura.jpg'));

        $transcripcion = $info['audio'] ? $this->transcribir($a) : ['texto' => null, 'error' => null];

        return [
            'resumen'       => 'Video · ' . $this->duracionLegible($duracion),
            'duracion'      => $duracion,
            'ancho'         => $info['ancho'] ?? null,
            'alto'          => $info['alto'] ?? null,
            'cuadros'       => $cuadros,
            'con_audio'     => (bool) $info['audio'],
            'transcripcion' => $transcripcion['texto'] !== null,
            'transcripcion_error' => $transcripcion['error'],
        ];
    }

    private function audio(Adjunto $a): array
    {
        if (!$this->bin('ffmpeg') || !$this->bin('ffprobe')) {
            throw new AdjuntoRechazado('En este servidor todavía no puedo escuchar audios.');
        }
        $info = $this->sonda($a->original());
        $duracion = (float) ($info['duracion'] ?? 0);
        $t = $this->transcribir($a);

        return [
            'resumen'       => 'Audio · ' . $this->duracionLegible($duracion),
            'duracion'      => $duracion,
            'transcripcion' => $t['texto'] !== null,
            'transcripcion_error' => $t['error'],
        ];
    }

    /**
     * Lo que se oye, con la IA de transcripción del sistema (la misma de VoIP: Integraciones →
     * Módulos IA, «voip.transcripcion»). Se parte en tramos cortos porque esa transcripción tiene
     * un tiempo límite por llamada.
     *
     * @return array{texto:?string, error:?string}
     */
    private function transcribir(Adjunto $a): array
    {
        $servicio = \App\Modules\Addons\VoIP\Services\Voz\VoiceSttService::class;
        if (!class_exists($servicio)) {
            return ['texto' => null, 'error' => 'sin servicio de transcripción'];
        }

        $patron = $a->ruta('tramo-%02d.wav');
        $this->correr([$this->bin('ffmpeg'), '-y', '-loglevel', 'error', '-i', $a->original(), '-vn', '-ac', '1', '-ar', '8000', '-c:a', 'pcm_s16le',
            '-t', (string) self::SEGUNDOS_A_TRANSCRIBIR, '-f', 'segment', '-segment_time', '75', $patron], 120);
        $tramos = glob($a->ruta('tramo-*.wav')) ?: [];
        sort($tramos, SORT_NATURAL);

        $partes = [];
        $error = null;
        foreach ($tramos as $tramo) {
            $r = app($servicio)->transcribir($tramo, 'es');
            if (!($r['success'] ?? false)) {
                $error = (string) ($r['error'] ?? 'falló la transcripción');
                break;
            }
            $partes[] = trim((string) ($r['texto'] ?? ''));
        }
        foreach ($tramos as $tramo) {
            @unlink($tramo);
        }

        if ($error !== null && !$partes) {
            Log::info('[JARVIS] no se pudo transcribir', ['adjunto' => $a->id, 'error' => $error]);

            return ['texto' => null, 'error' => str_contains($error, 'asign') || str_contains($error, 'IA') ? 'sin IA de transcripción' : 'falló la transcripción'];
        }

        $texto = trim(implode(' ', array_filter($partes)));
        file_put_contents($a->ruta('transcripcion.txt'), $texto);

        return ['texto' => $texto, 'error' => null];
    }

    /** @return array{duracion:?float, ancho:?int, alto:?int, audio:bool} */
    private function sonda(string $ruta): array
    {
        $p = new Process([$this->bin('ffprobe'), '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $ruta]);
        $p->setTimeout(30)->run();
        $json = json_decode($p->getOutput(), true) ?: [];

        $video = collect($json['streams'] ?? [])->firstWhere('codec_type', 'video');
        $audio = collect($json['streams'] ?? [])->firstWhere('codec_type', 'audio');

        return [
            'duracion' => isset($json['format']['duration']) ? (float) $json['format']['duration'] : null,
            'ancho'    => isset($video['width']) ? (int) $video['width'] : null,
            'alto'     => isset($video['height']) ? (int) $video['height'] : null,
            'audio'    => $audio !== null,
        ];
    }

    private function miniatura(string $origen, string $destino): void
    {
        try {
            Image::make($origen)->orientate()->fit(320, 320, fn ($c) => $c->upsize())->encode('jpg', 78)->save($destino);
        } catch (\Throwable) {
            // Sin miniatura el chat muestra el ícono del tipo.
        }
    }

    private function guardarTexto(Adjunto $a, string $texto): string
    {
        $texto = mb_substr(trim($texto), 0, self::MAX_CARACTERES_TEXTO);
        file_put_contents($a->ruta('texto.txt'), $texto);

        return $texto;
    }

    private function duracionLegible(float $segundos): string
    {
        $s = (int) round($segundos);

        return $s >= 3600 ? sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60) : sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    }

    /** Ruta absoluta del programa (PHP-FPM corre sin PATH), o null si el servidor no lo tiene. */
    private function bin(string $comando): ?string
    {
        static $cache = [];
        if (!array_key_exists($comando, $cache)) {
            $cache[$comando] = (new ExecutableFinder())->find($comando, null, ['/usr/bin', '/usr/local/bin', '/bin']);
        }

        return $cache[$comando];
    }

    private function correr(array $cmd, int $segundos): bool
    {
        try {
            $p = new Process($cmd);
            $p->setTimeout($segundos)->run();

            return $p->isSuccessful();
        } catch (\Throwable $e) {
            Log::info('[JARVIS] falló un comando al preparar un archivo', ['cmd' => $cmd[0], 'error' => $e->getMessage()]);

            return false;
        }
    }
}
