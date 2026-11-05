<?php

namespace App\Modules\Addons\FirmaElectronica\Services;

use App\Modules\Addons\FirmaElectronica\Models\FirmaEvidencia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Constancia de evidencia de firma: PDF generado desde firma_evidencias y unido al
 * documento original con pdfunite. No constituye certificación (ver docs/firma-electronica-constancia-nom151.md).
 */
class FirmaConstanciaService
{
    private const DISCO = 'local';

    /**
     * Devuelve la ruta (disco local privado) del PDF unido y guarda su sha256 en
     * firma_evidencias.sha256_firmado (write-once: el modelo es inmutable, se usa query builder
     * solo mientras la columna siga NULL).
     */
    public function generar(FirmaEvidencia $ev, string $pdfOriginalAbs): string
    {
        if (! is_file($pdfOriginalAbs)) {
            throw new RuntimeException('PDF original inexistente.');
        }

        $dir = 'private/firma-electronica/constancias';
        Storage::disk(self::DISCO)->makeDirectory($dir);
        $base = "{$dir}/sol{$ev->solicitud_id}-ev{$ev->id}";

        $html = view()->file(__DIR__.'/../views/constancia.blade.php', ['ev' => $ev])->render();
        Storage::disk(self::DISCO)->put("{$base}-constancia.pdf", Pdf::loadHTML($html)->output());

        $constancia = Storage::disk(self::DISCO)->path("{$base}-constancia.pdf");
        $salida = Storage::disk(self::DISCO)->path("{$base}-firmado.pdf");

        $p = new Process(['pdfunite', $pdfOriginalAbs, $constancia, $salida]);
        $p->setTimeout(60)->run();
        if (! $p->isSuccessful() || ! is_file($salida)) {
            throw new RuntimeException('pdfunite falló: '.trim($p->getErrorOutput()));
        }

        DB::table('firma_evidencias')->where('id', $ev->id)->whereNull('sha256_firmado')
            ->update(['sha256_firmado' => hash_file('sha256', $salida)]);

        return "{$base}-firmado.pdf";
    }
}
