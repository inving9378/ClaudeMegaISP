<?php

namespace App\Support;

use App\Modules\Core\Configuracion\Models\CompanyInformation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Logo de la empresa reducido y embebido como data URI, para `${data.logo_pdf}` (items #480/#497).
 * dompdf no tiene que leer del disco (sin líos de chroot/permisos en prod) y el PDF no carga el
 * PNG original de ~280 KB. Lo usan PrefacturaDataProvider y el validador de plantillas (para que
 * la previsualización genérica del editor también lo muestre).
 */
final class LogoPdf
{
    /** Alto en px del logo embebido: la plantilla lo pinta a 36px; esto da ~300 dpi. */
    private const ALTO_PX = 120;

    /** 1×1 transparente: mejor un hueco que el "Image not found" de dompdf. */
    public const PIXEL_TRANSPARENTE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    public static function dataUri(): string
    {
        $ruta = self::ruta();
        if ($ruta === null) {
            return self::PIXEL_TRANSPARENTE;
        }

        $clave = 'prefactura:logo_pdf:' . md5($ruta . '|' . filesize($ruta) . '|' . filemtime($ruta) . '|' . self::ALTO_PX);

        return Cache::rememberForever($clave, function () use ($ruta) {
            try {
                return self::reducir($ruta);
            } catch (\Throwable $e) {
                Log::warning('[Prefactura] No se pudo reducir el logo: ' . $e->getMessage());

                return self::PIXEL_TRANSPARENTE;
            }
        });
    }

    private static function ruta(): ?string
    {
        $empresa = CompanyInformation::first();
        $candidatas = [];
        if ($empresa && $empresa->url_logo) {
            $candidatas[] = public_path(ltrim(str_replace('\\', '/', $empresa->url_logo), '/'));
        }
        $candidatas[] = public_path('images/logo_meganet_oficial.png');

        foreach ($candidatas as $ruta) {
            if (is_file($ruta) && is_readable($ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    private static function reducir(string $ruta): string
    {
        $origen = @imagecreatefromstring((string) file_get_contents($ruta));
        if (! $origen) {
            return self::PIXEL_TRANSPARENTE;
        }

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $nuevoAlto = min(self::ALTO_PX, $alto);
        $nuevoAncho = max(1, (int) round($ancho * $nuevoAlto / max(1, $alto)));

        $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        imagefill($destino, 0, 0, imagecolorallocatealpha($destino, 0, 0, 0, 127));
        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        ob_start();
        imagepng($destino, null, 9);
        $png = (string) ob_get_clean();
        imagedestroy($origen);
        imagedestroy($destino);

        return 'data:image/png;base64,' . base64_encode($png);
    }
}
