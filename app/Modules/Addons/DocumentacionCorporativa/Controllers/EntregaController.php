<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcEntrega;
use App\Modules\Addons\DocumentacionCorporativa\Services\BitacoraService;
use App\Modules\Addons\DocumentacionCorporativa\Services\EmpresaContextService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Fase 5b.3 (item roadmap #812) — descarga del ZIP y del acta de una
 * `DcEntrega` ya armada (Fase 5b.1 `EntregaService::generar` / Fase 5b.2
 * `ActaEntregaService::generar`).
 *
 * Gate reusando `documentacion-corporativa.entrega.create` (decisión
 * registrada, ver `circuito:reportar --tipo=decision` del item #812):
 * minimalismo — hoy nadie necesita distinguir "armar la entrega" de
 * "descargarla", así que no se crea un permiso `.entrega.download` propio.
 *
 * `ruta_zip`/`ruta_acta_pdf` son rutas ABSOLUTAS en disco (así las escriben
 * `EntregaService`/`ActaEntregaService`, vía `storage_path()` directo, no un
 * path relativo al disco `local`) — se sirven con `is_file()`/`response()->
 * download()` planos, sin pasar por `Storage::disk()`.
 *
 * El contador (`descargas_zip_count`/`descargas_acta_count`) y la bitácora
 * (`BitacoraService::descargar`, con `contexto=['entrega_id'=>...]` porque una
 * `DcEntrega` no es un `DcDocumento` — `documento_id` es nullable en
 * `dc_accesos_log`) se registran ANTES de servir el archivo: mismo criterio
 * que `DocumentoController::servirArchivo` y que la propia `BitacoraService`.
 */
class EntregaController extends Controller
{
    private const PERMISO = 'documentacion-corporativa.entrega.create';

    public function __construct(
        private EmpresaContextService $empresas,
        private BitacoraService $bitacora,
    ) {
    }

    public function descargarZip(int $id): BinaryFileResponse
    {
        return $this->servir($id, 'zip');
    }

    public function descargarActa(int $id): BinaryFileResponse
    {
        return $this->servir($id, 'acta');
    }

    private function servir(int $id, string $tipo): BinaryFileResponse
    {
        abort_unless(
            auth()->user()?->can(self::PERMISO),
            403,
            'No tienes permiso para descargar paquetes de entrega.'
        );

        $empresa = $this->empresas->actual();
        $entrega = DcEntrega::deEmpresa($empresa->id)->findOrFail($id);

        $ruta = $tipo === 'zip' ? $entrega->ruta_zip : $entrega->ruta_acta_pdf;
        abort_unless($ruta && is_file($ruta), 404, 'El archivo no existe en disco.');

        // Bitácora + contador ANTES de servir: si algo falla aquí, el archivo no sale.
        $this->bitacora->descargar($empresa->id, null, null, [
            'entrega_id' => $entrega->id,
            'tipo'       => $tipo,
        ]);
        $entrega->increment($tipo === 'zip' ? 'descargas_zip_count' : 'descargas_acta_count');

        $nombre = $tipo === 'zip' ? "entrega_{$entrega->id}.zip" : "acta_{$entrega->id}.pdf";

        return response()->download($ruta, $nombre);
    }
}
