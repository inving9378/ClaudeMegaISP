<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcAccesoLog;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcApartado;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcDocumento;
use App\Modules\Addons\DocumentacionCorporativa\Services\BitacoraService;
use App\Modules\Addons\DocumentacionCorporativa\Services\EmpresaContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bitácora consultable/exportable (Fase 5c, item roadmap #760).
 *
 * Doble puerta igual que el resto del módulo: `check_route_permission` gatea
 * la entrada por `documentacion-corporativa.view`; VER estas pantallas se
 * gatea aquí con `.bitacora.view`, y EXPORTAR con `.bitacora.export` (más
 * fino que ver — no todo el que puede consultar debe poder sacar el CSV).
 *
 * La bitácora es de solo lectura desde aquí: `DcAccesoLog` es append-only
 * (ver el propio modelo), este controller nunca crea/edita/borra filas, salvo
 * la que `BitacoraService::exportar()` escribe para registrar el propio acto
 * de exportar — la bitácora se audita a sí misma.
 */
class BitacoraController extends Controller
{
    private const PERMISO_VER      = 'documentacion-corporativa.bitacora.view';
    private const PERMISO_EXPORTAR = 'documentacion-corporativa.bitacora.export';

    public function __construct(
        private EmpresaContextService $empresas,
        private BitacoraService $bitacora,
    ) {
    }

    /** Empresas disponibles para el selector del filtro (mismo criterio que el header). */
    public function empresasFiltro(): JsonResponse
    {
        $this->autorizar(self::PERMISO_VER);

        return response()->json(
            $this->empresas->activas()->map(fn ($e) => ['id' => $e->id, 'etiqueta' => $e->etiqueta])->values()
        );
    }

    /** Usuarios que realmente aparecen en la bitácora de la empresa filtrada (no una búsqueda global). */
    public function usuariosFiltro(Request $request): JsonResponse
    {
        $this->autorizar(self::PERMISO_VER);

        $empresaId = $this->empresaFiltrada($request);

        $usuarios = DcAccesoLog::deEmpresa($empresaId)
            ->whereNotNull('user_id')
            ->with('usuario:id,name,father_last_name,mother_last_name')
            ->get()
            ->pluck('usuario')
            ->filter()
            ->unique('id')
            ->map(fn ($u) => ['id' => $u->id, 'name' => trim("{$u->name} {$u->father_last_name} {$u->mother_last_name}")])
            ->values();

        return response()->json($usuarios);
    }

    public function index(Request $request): JsonResponse
    {
        $this->autorizar(self::PERMISO_VER);

        $registros = $this->filtrado($request)
            ->with('usuario:id,name,father_last_name,mother_last_name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 30));

        $this->adjuntarReferencias($registros->getCollection());

        return response()->json($registros);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $this->autorizar(self::PERMISO_EXPORTAR);

        $empresaId = $this->empresaFiltrada($request);
        $filtros   = $this->filtrosAplicados($request);

        // Se registra ANTES de servir el contenido (regla del propio servicio):
        // si el registro falla, el CSV no sale.
        $this->bitacora->exportar($empresaId, null, null, [
            'pantalla' => 'bitacora',
            'filtros'  => $filtros,
        ]);

        $query    = $this->filtrado($request)->with('usuario:id,name,father_last_name,mother_last_name');
        $filename = 'bitacora-accesos-' . now()->format('Ymd-His') . '.csv';

        return new StreamedResponse(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fecha', 'Usuario', 'Acción', 'Apartado', 'Documento', 'IP']);

            $query->orderByDesc('created_at')->orderByDesc('id')
                ->chunk(500, function (Collection $rows) use ($out) {
                    $this->adjuntarReferencias($rows);

                    foreach ($rows as $r) {
                        fputcsv($out, [
                            optional($r->created_at)->format('Y-m-d H:i:s'),
                            $r->usuario ? trim("{$r->usuario->name} {$r->usuario->father_last_name} {$r->usuario->mother_last_name}") : '',
                            $r->accion,
                            $r->apartado_clave ?? '',
                            $r->documento_nombre ?? '',
                            $r->ip ?? '',
                        ]);
                    }
                });

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function filtrado(Request $request)
    {
        $empresaId = $this->empresaFiltrada($request);

        return DcAccesoLog::deEmpresa($empresaId)
            ->when($request->integer('user_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->string('accion')->toString(), fn ($q, $v) => $q->where('accion', $v))
            ->when($request->date('fecha_desde'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->date('fecha_hasta'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
    }

    private function filtrosAplicados(Request $request): array
    {
        return array_filter([
            'empresa_id'  => $this->empresaFiltrada($request),
            'user_id'     => $request->integer('user_id') ?: null,
            'accion'      => $request->string('accion')->toString() ?: null,
            'fecha_desde' => $request->string('fecha_desde')->toString() ?: null,
            'fecha_hasta' => $request->string('fecha_hasta')->toString() ?: null,
        ], fn ($v) => $v !== null);
    }

    /** Empresa a filtrar: la del selector de este panel, o la actual del header si no se manda. */
    private function empresaFiltrada(Request $request): int
    {
        $solicitada = $request->integer('empresa_id');

        if ($solicitada && $this->empresas->activas()->contains('id', $solicitada)) {
            return $solicitada;
        }

        return $this->empresas->actualId();
    }

    /** Adjunta clave de apartado y nombre de documento en lote (no son relaciones Eloquent: ids nullable por diseño). */
    private function adjuntarReferencias(Collection $registros): void
    {
        $apartadoIds  = $registros->pluck('apartado_id')->filter()->unique();
        $documentoIds = $registros->pluck('documento_id')->filter()->unique();

        $apartados = $apartadoIds->isEmpty()
            ? collect()
            : DcApartado::whereIn('id', $apartadoIds)->pluck('clave', 'id');

        $documentos = $documentoIds->isEmpty()
            ? collect()
            : DcDocumento::whereIn('id', $documentoIds)->pluck('archivo_nombre_original', 'id');

        foreach ($registros as $r) {
            $r->apartado_clave    = $r->apartado_id ? ($apartados[$r->apartado_id] ?? null) : null;
            $r->documento_nombre  = $r->documento_id ? ($documentos[$r->documento_id] ?? null) : null;
        }
    }

    private function autorizar(string $permiso): void
    {
        abort_unless(
            auth()->user()?->can($permiso),
            403,
            'No tienes permiso para consultar la bitácora de accesos.'
        );
    }
}
