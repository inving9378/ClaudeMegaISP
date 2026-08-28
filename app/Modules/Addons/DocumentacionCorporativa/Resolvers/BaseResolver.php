<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Resolvers;

use App\Modules\Addons\DocumentacionCorporativa\Contracts\ConceptoResolver;
use App\Modules\Addons\DocumentacionCorporativa\Contracts\FormatoNoSoportadoException;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Exportación compartida por los seis resolvedores.
 *
 * Cada driver decide QUÉ datos devuelve; el archivo se arma igual para todos, así
 * que agregar un resolvedor nuevo no obliga a reimplementar la exportación.
 * PDF con dompdf, siguiendo el patrón de `ContractClientService`.
 */
abstract class BaseResolver implements ConceptoResolver
{
    public const FORMATOS = ['csv', 'pdf'];

    public function exportar(DcConcepto $concepto, int $empresaId, string $formato): string
    {
        $formato = mb_strtolower(trim($formato));

        if (! in_array($formato, self::FORMATOS, true)) {
            throw FormatoNoSoportadoException::para($formato);
        }

        $resultado = $this->resolver($concepto, $empresaId);
        $destino   = $this->rutaTemporal($concepto, $formato);

        if ($formato === 'csv') {
            $this->escribirCsv($destino, $resultado->datos);

            return $destino;
        }

        $pdf = Pdf::loadView('addon-documentacion-corporativa::export.concepto', [
            'concepto'  => $concepto,
            'resultado' => $resultado,
            'generado'  => now(),
        ]);
        file_put_contents($destino, $pdf->output());

        return $destino;
    }

    /**
     * Filas planas a CSV. Sin librería: es un volcado de arrays asociativos y
     * `fputcsv` ya escapa comillas y separadores correctamente.
     */
    protected function escribirCsv(string $destino, array $filas): void
    {
        $handle = fopen($destino, 'w');
        // BOM para que Excel abra los acentos sin romperlos.
        fwrite($handle, "\xEF\xBB\xBF");

        $filas = array_values(array_filter($filas, 'is_array'));

        if ($filas === []) {
            fputcsv($handle, ['Sin registros en este entorno']);
            fclose($handle);

            return;
        }

        fputcsv($handle, array_keys($filas[0]));
        foreach ($filas as $fila) {
            fputcsv($handle, array_map(
                fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v, JSON_UNESCAPED_UNICODE),
                $fila
            ));
        }
        fclose($handle);
    }

    protected function rutaTemporal(DcConcepto $concepto, string $extension): string
    {
        $dir = storage_path('app/documentacion_corporativa/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0770, true);
        }

        return $dir . '/' . Str::slug($concepto->slug) . '-' . now()->format('Ymd-His')
            . '-' . Str::random(6) . '.' . $extension;
    }
}
