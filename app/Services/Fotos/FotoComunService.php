<?php

namespace App\Services\Fotos;

use App\Modules\Addons\Talento\Support\ExifFoto;
use Intervention\Image\ImageManagerStatic as Image;

/**
 * Servicio común de fotos (item #1733, F2). Orden: leer EXIF (GPS y fecha) → enderezar →
 * reducir a `lado_max` px (lado largo) con `calidad` → marca de agua proporcional.
 * El EXIF se lee ANTES de reducir porque al recodificar se pierde. Solo para subidas nuevas.
 */
final class FotoComunService
{
    public const LADO_MAX = 1600;
    public const CALIDAD = 80;
    /** Alto de la fuente de la marca como fracción del ancho (~2,5 %). */
    public const FUENTE_PROPORCION = 0.025;

    /**
     * Procesa el archivo EN SITIO (lo reemplaza por un JPEG reducido).
     *
     * @param  ?string  $marca  texto de la marca de agua (null = sin marca)
     * @return array{bytes_original: int, bytes: int, ancho: int, alto: int, exif: array, exif_json: ?string}|null
     *         null si no se pudo leer como imagen (el archivo queda intacto).
     */
    public static function procesar(string $ruta, ?string $marca = null, int $lado = self::LADO_MAX, int $calidad = self::CALIDAD): ?array
    {
        if (! is_file($ruta)) {
            return null;
        }
        $original = (int) filesize($ruta);
        $exif = ExifFoto::leer($ruta);

        try {
            $img = Image::make($ruta)->orientate();
            if (max($img->width(), $img->height()) > $lado) {
                $img->resize($lado, $lado, function ($c) {
                    $c->aspectRatio();
                    $c->upsize();
                });
            }
            if ($marca !== null && $marca !== '') {
                self::marcar($img, $marca);
            }
            $jpg = (string) $img->encode('jpg', $calidad);
            $ancho = $img->width();
            $alto = $img->height();
            $img->destroy();
        } catch (\Throwable) {
            return null;
        }

        file_put_contents($ruta, $jpg);

        return [
            'bytes_original' => $original,
            'bytes'          => strlen($jpg),
            'ancho'          => $ancho,
            'alto'           => $alto,
            'exif'           => $exif,
            'exif_json'      => json_encode([
                'lat'       => $exif['lat'],
                'lng'       => $exif['lng'],
                'tomada_at' => $exif['tomada_at']?->toIso8601String(),
            ]),
        ];
    }

    /**
     * Copia para la IA de visión (item #1738, F7): enderezada y de máximo `lado` px, SOLO en memoria
     * (no se guarda). Si ya cabe, o no se puede leer como imagen, devuelve los bytes originales.
     *
     * @return array{mime: string, data: string} `data` en base64, listo para IA::enviar
     */
    public static function paraIa(string $bytes, string $mime = 'image/jpeg', int $lado = self::LADO_MAX, int $calidad = self::CALIDAD): array
    {
        try {
            $img = Image::make($bytes)->orientate();
            if (max($img->width(), $img->height()) > $lado) {
                $img->resize($lado, $lado, function ($c) {
                    $c->aspectRatio();
                    $c->upsize();
                });
                $bytes = (string) $img->encode('jpg', $calidad);
                $mime = 'image/jpeg';
            }
            $img->destroy();
        } catch (\Throwable) {
            // se manda el original
        }

        return ['mime' => $mime, 'data' => base64_encode($bytes)];
    }

    /** Columnas aditivas (bytes, bytes_original, exif_json) que SÍ existan en $tabla; [] si no se procesó. */
    public static function columnas(string $tabla, ?array $r): array
    {
        if (! $r) {
            return [];
        }
        $out = [];
        foreach (['bytes', 'bytes_original', 'exif_json'] as $c) {
            if (\Illuminate\Support\Facades\Schema::hasColumn($tabla, $c)) {
                $out[$c] = $r[$c];
            }
        }

        return $out;
    }

    /** Barra inferior translúcida con el texto; tamaño de fuente proporcional al ancho. */
    private static function marcar($img, string $texto): void
    {
        $px = max(10, (int) round($img->width() * self::FUENTE_PROPORCION));
        $fuente = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        $img->rectangle(0, $img->height() - (int) ($px * 1.9), $img->width(), $img->height(), function ($d) {
            $d->background([0, 0, 0, 0.55]);
        });
        $img->text($texto, (int) round($px * 0.6), $img->height() - (int) round($px * 0.5), function ($f) use ($px, $fuente) {
            if (is_file($fuente)) {
                $f->file($fuente);
            }
            $f->size($px);
            $f->color('#ffffff');
        });
    }
}
