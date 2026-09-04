<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Services;

use App\Modules\Addons\DocumentacionCorporativa\Models\DcApartado;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcEntrega;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcEntregaItem;
use App\Modules\Addons\DocumentacionCorporativa\Resolvers\ResolverFactory;
use Throwable;
use ZipArchive;

/**
 * Fase 5b.1 (item roadmap #810) — arma el paquete de una entrega (apartado
 * XIV): recorre los apartados/conceptos pedidos, exporta cada uno según el
 * nivel de detalle y los sella en un ZIP con hash SHA-256.
 *
 * Un concepto que falla NO tumba el resto: se registra como
 * `error_exportacion` en su `DcEntregaItem` y la entrega sigue armándose con
 * lo demás.
 */
class EntregaService
{
    public function __construct(private ResolverFactory $resolvers)
    {
    }

    public function generar(
        int $empresaId,
        array $apartadoClaves,
        string $nivelDetalle,
        ?int $solicitudId,
        int $userId
    ): DcEntrega {
        $entrega = DcEntrega::create([
            'empresa_id'           => $empresaId,
            'solicitud_id'         => $solicitudId,
            'generado_por_user_id' => $userId,
            'fecha_entrega'        => now(),
            'estado'               => DcEntrega::ESTADO_GENERANDO,
        ]);

        $dir = storage_path("app/documentacion_corporativa/entregas/{$empresaId}");
        if (! is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        $rutaZip = $dir . '/entrega_' . $entrega->id . '_' . now()->format('Ymd-His') . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($rutaZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $entrega->update([
                'estado' => DcEntrega::ESTADO_FALLIDA,
                'error'  => "No se pudo crear el ZIP en {$rutaZip}",
            ]);

            return $entrega;
        }

        $indice     = [];
        $temporales = [];

        try {
            $apartados = DcApartado::deEmpresa($empresaId)->activos()
                ->whereIn('clave', $apartadoClaves)
                ->orderBy('orden')
                ->get();

            foreach ($apartados as $apartado) {
                foreach ($apartado->conceptos()->activos()->get() as $concepto) {
                    $indice[] = $this->procesarConcepto(
                        $zip,
                        $entrega,
                        $apartado,
                        $concepto,
                        $empresaId,
                        $nivelDetalle,
                        $temporales
                    );
                }
            }

            // libzip no persiste en disco un ZIP sin entradas (pasa siempre en nivel
            // 'agregado', que no exporta archivos) — el placeholder garantiza que
            // `hash_file()` de abajo siempre tenga un ZIP real que hashear.
            if ($zip->numFiles === 0) {
                $zip->addFromString(
                    'README.txt',
                    "Entrega nivel '{$nivelDetalle}': sin archivos exportados, solo métricas en el índice.\n"
                );
            }

            $zip->close();

            foreach ($temporales as $temporal) {
                @unlink($temporal);
            }

            $entrega->update([
                'ruta_zip'    => $rutaZip,
                'hash_sha256' => hash_file('sha256', $rutaZip),
                'indice'      => $indice,
                'estado'      => DcEntrega::ESTADO_GENERADA,
            ]);
        } catch (Throwable $e) {
            @$zip->close();
            foreach ($temporales as $temporal) {
                @unlink($temporal);
            }

            $entrega->update([
                'estado' => DcEntrega::ESTADO_FALLIDA,
                'error'  => $e->getMessage(),
            ]);
        }

        return $entrega;
    }

    /** Resuelve, exporta y encarpeta un concepto; registra su `DcEntregaItem`. */
    private function procesarConcepto(
        ZipArchive $zip,
        DcEntrega $entrega,
        DcApartado $apartado,
        DcConcepto $concepto,
        int $empresaId,
        string $nivelDetalle,
        array &$temporales
    ): array {
        $archivoIncluido = null;
        $estadoResuelto  = null;
        $metricas        = [];

        try {
            $resolver       = $this->resolvers->para($concepto, $empresaId);
            $resultado      = $resolver->resolver($concepto, $empresaId);
            $estadoResuelto = $resultado->estado;
            $metricas       = $resultado->metricas;

            if ($nivelDetalle !== 'agregado') {
                $carpeta = $apartado->clave . '/' . $concepto->slug;

                $csv          = $resolver->exportar($concepto, $empresaId, 'csv');
                $temporales[] = $csv;
                $nombreZipCsv = $carpeta . '.csv';
                $zip->addFile($csv, $nombreZipCsv);
                $archivoIncluido = $nombreZipCsv;

                if ($nivelDetalle === 'integro') {
                    $pdf          = $resolver->exportar($concepto, $empresaId, 'pdf');
                    $temporales[] = $pdf;
                    $zip->addFile($pdf, $carpeta . '.pdf');
                }
            }
        } catch (Throwable $e) {
            $estadoResuelto = 'error_exportacion';
            $metricas       = ['error' => $e->getMessage()];
        }

        DcEntregaItem::create([
            'entrega_id'       => $entrega->id,
            'apartado_clave'   => $apartado->clave,
            'concepto_id'      => $concepto->id,
            'nivel_detalle'    => $nivelDetalle,
            'archivo_incluido' => $archivoIncluido,
            'estado_resuelto'  => $estadoResuelto,
            'metricas'         => $metricas,
        ]);

        return [
            'apartado_clave'  => $apartado->clave,
            'concepto_id'     => $concepto->id,
            'concepto'        => $concepto->nombre,
            'nivel_detalle'   => $nivelDetalle,
            'estado_resuelto' => $estadoResuelto,
            'archivo'         => $archivoIncluido,
        ];
    }
}
