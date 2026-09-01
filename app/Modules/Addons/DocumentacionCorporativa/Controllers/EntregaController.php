<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcApartado;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcEntrega;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcEntregaItem;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcSolicitud;
use App\Modules\Addons\DocumentacionCorporativa\Services\ActaEntregaService;
use App\Modules\Addons\DocumentacionCorporativa\Services\BitacoraService;
use App\Modules\Addons\DocumentacionCorporativa\Services\EmpresaContextService;
use App\Modules\Addons\DocumentacionCorporativa\Services\EntregaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Fase 5b.3 (item roadmap #812) — descarga del ZIP y del acta de una
 * `DcEntrega` ya armada (Fase 5b.1 `EntregaService::generar` / Fase 5b.2
 * `ActaEntregaService::generar`).
 *
 * Fase 5c.2a (item roadmap #834) — agrega `store()`/`index()`: armar una
 * entrega desde una `DcSolicitud` y consultar las ya armadas. Backend puro
 * (sin UI); Fase 5c.2b construye la pantalla contra estos dos endpoints.
 *
 * Gate reusando `documentacion-corporativa.entrega.create` (decisión
 * registrada, ver `circuito:reportar --tipo=decision` del item #812):
 * minimalismo — hoy nadie necesita distinguir "armar la entrega" de
 * "descargarla", así que no se crea un permiso `.entrega.download` propio.
 * `index()` (solo lectura) reusa el permiso del apartado dueño
 * (`.apartado.xiv.view`), mismo criterio que `DcSolicitudController::index`.
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
    private const PERMISO_CREATE = 'documentacion-corporativa.entrega.create';
    private const PERMISO_VER    = 'documentacion-corporativa.apartado.xiv.view';

    public function __construct(
        private EmpresaContextService $empresas,
        private BitacoraService $bitacora,
        private EntregaService $entregas,
        private ActaEntregaService $actas,
    ) {
    }

    /**
     * Arma una entrega (ZIP + acta) para una solicitud, con los apartados que
     * el usuario mande y pueda ver. Filtra `apartados[]` contra el permiso
     * real de cada apartado — un apartado sin permiso NUNCA entra, aunque el
     * cliente lo mande a mano. Si tras filtrar no queda ninguno, 422.
     *
     * Entregas parciales: N entregas por solicitud. Cuando la unión de
     * apartados ya entregados (en las entregas `generada` de esta solicitud)
     * cubre `dc_solicitud.apartados` completo, la solicitud pasa a
     * `entregada` (decisión ya tomada por Irving en el item padre #824).
     */
    public function store(Request $request, int $solicitudId): JsonResponse
    {
        abort_unless(
            auth()->user()?->can(self::PERMISO_CREATE),
            403,
            'No tienes permiso para armar entregas.'
        );

        $empresa   = $this->empresas->actual();
        $solicitud = DcSolicitud::deEmpresa($empresa->id)->findOrFail($solicitudId);

        $validado = $request->validate([
            'apartados'     => ['required', 'array', 'min:1'],
            'apartados.*'   => ['string'],
            'nivel_detalle' => ['required', Rule::in(DcEntregaItem::NIVELES)],
        ]);

        $apartadosPermitidos = collect($validado['apartados'])
            ->map(fn ($clave) => mb_strtoupper($clave))
            ->unique()
            ->filter(function (string $clave) use ($empresa) {
                $apartado = DcApartado::deEmpresa($empresa->id)->activos()
                    ->where('clave', $clave)->first();

                return $apartado && auth()->user()->can($apartado->permiso());
            })
            ->values();

        if ($apartadosPermitidos->isEmpty()) {
            return response()->json([
                'message' => 'Ninguno de los apartados solicitados es visible para tu usuario.',
            ], 422);
        }

        $entrega = $this->entregas->generar(
            $empresa->id,
            $apartadosPermitidos->all(),
            $validado['nivel_detalle'],
            $solicitud->id,
            auth()->id()
        );

        if ($entrega->estado === DcEntrega::ESTADO_GENERADA) {
            try {
                $this->actas->generar($entrega, $solicitud->solicitante, auth()->id());
            } catch (Throwable $e) {
                // El ZIP ya quedó sellado; el acta es best-effort — no tumba la entrega
                // ya generada (mismo espíritu que EntregaService: un fallo parcial no
                // debe perder lo que sí se logró).
            }

            $this->actualizarEstadoSolicitud($solicitud);
        }

        return response()->json($entrega->fresh('items'), 201);
    }

    /**
     * Lista las entregas de una solicitud para que el frontend (5c.2b) pinte
     * generando/generada/fallida y habilite los botones de descarga.
     */
    public function index(int $solicitudId): JsonResponse
    {
        abort_unless(
            auth()->user()?->can(self::PERMISO_VER),
            403,
            'No tienes permiso para ver el apartado XIV.'
        );

        $empresa   = $this->empresas->actual();
        $solicitud = DcSolicitud::deEmpresa($empresa->id)->findOrFail($solicitudId);

        $entregas = DcEntrega::deEmpresa($empresa->id)
            ->where('solicitud_id', $solicitud->id)
            ->with('items:entrega_id,apartado_clave')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DcEntrega $entrega) => [
                'id'                   => $entrega->id,
                'estado'               => $entrega->estado,
                'fecha_entrega'        => $entrega->fecha_entrega,
                'apartados'            => $entrega->items->pluck('apartado_clave')->unique()->values(),
                'descargas_zip_count'  => $entrega->descargas_zip_count,
                'descargas_acta_count' => $entrega->descargas_acta_count,
                'zip_listo'            => (bool) $entrega->ruta_zip,
                'acta_lista'           => (bool) $entrega->ruta_acta_pdf,
            ]);

        return response()->json(['data' => $entregas]);
    }

    /** Marca la solicitud `entregada` cuando sus apartados pedidos ya quedaron todos cubiertos. */
    private function actualizarEstadoSolicitud(DcSolicitud $solicitud): void
    {
        if (empty($solicitud->apartados)) {
            return;
        }

        $entregadosIds = DcEntrega::deEmpresa($solicitud->empresa_id)
            ->where('solicitud_id', $solicitud->id)
            ->where('estado', DcEntrega::ESTADO_GENERADA)
            ->pluck('id');

        $apartadosCubiertos = DcEntregaItem::whereIn('entrega_id', $entregadosIds)
            ->distinct()
            ->pluck('apartado_clave')
            ->all();

        if (empty(array_diff($solicitud->apartados, $apartadosCubiertos))) {
            $solicitud->update(['estado' => 'entregada']);
        }
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
            auth()->user()?->can(self::PERMISO_CREATE),
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
