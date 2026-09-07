<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Controllers;

use App\Exports\DocumentacionApartadoExport;
use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\Client;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcApartado;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Services\BitacoraService;
use App\Modules\Addons\DocumentacionCorporativa\Services\CompletitudService;
use App\Modules\Addons\DocumentacionCorporativa\Services\EmpresaContextService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Índice de los 14 apartados + tablero de completitud.
 *
 * Doble puerta de permisos, a propósito:
 *  - `check_route_permission` gatea la ENTRADA al módulo por
 *    `documentacion-corporativa.view` (config/route_permission.php).
 *  - Cada apartado se gatea AQUÍ por su propio `.apartado.{clave}.view`, porque
 *    el middleware mapea ruta→permiso y las 14 claves comparten una sola ruta.
 *
 * El rol `consejo` entra al módulo y ve 13 apartados; el XI (bancos) no aparece
 * en su índice ni le responde su detalle.
 */
class ExpedienteController extends Controller
{
    public function __construct(
        private EmpresaContextService $empresas,
        private CompletitudService $completitud,
        private BitacoraService $bitacora,
    ) {
    }

    public function index()
    {
        return view('addon-documentacion-corporativa::index', [
            'empresa'         => $this->empresas->actual(),
            'empresas'        => $this->empresas->activas(),
            'mostrarSelector' => $this->empresas->mostrarSelector(),
        ]);
    }

    /** Tablero: apartados visibles para ESTE usuario, con % y semáforo. */
    public function tablero(Request $request): JsonResponse
    {
        $empresa = $this->empresas->actual();

        $tablero = $this->completitud->tablero(
            $empresa->id,
            $request->boolean('refrescar')
        );

        $visibles = array_values(array_filter(
            $tablero['apartados'],
            fn (array $a) => $this->puedeVer($a['permiso'])
        ));

        // La bitácora se escribe ANTES de responder. Registra qué vio realmente
        // este usuario, no los 14 apartados que existen.
        $this->bitacora->ver($empresa->id, null, null, [
            'pantalla'           => 'tablero',
            'apartados_visibles' => array_column($visibles, 'clave'),
        ]);

        return response()->json([
            'empresa' => [
                'id'                => $empresa->id,
                'razon_social'      => $empresa->razon_social,
                'nombre_comercial'  => $empresa->nombre_comercial,
                'rfc'               => $empresa->rfc,
                'etiqueta'          => $empresa->etiqueta,
            ],
            'empresas'         => $this->empresas->activas()->map(fn ($e) => [
                'id' => $e->id, 'etiqueta' => $e->etiqueta,
            ])->values(),
            'mostrar_selector' => $this->empresas->mostrarSelector(),
            'apartados'        => $visibles,
            'global'           => $this->completitud->agregarGlobal($visibles),
            'calculado_at'     => $tablero['calculado_at'],
        ]);
    }

    /** Detalle de un apartado: sus conceptos ya resueltos. Siempre en vivo. */
    public function apartado(string $clave): JsonResponse
    {
        $empresa = $this->empresas->actual();

        $apartado = DcApartado::deEmpresa($empresa->id)
            ->activos()
            ->where('clave', mb_strtoupper($clave))
            ->firstOrFail();

        if (! $this->puedeVer($apartado->permiso())) {
            return response()->json([
                'message' => 'No tienes permiso para ver este apartado.',
            ], 403);
        }

        $resumen = $this->completitud->apartado($apartado, $empresa->id);

        $this->bitacora->ver($empresa->id, $apartado->id, null, [
            'pantalla' => 'apartado',
            'clave'    => $apartado->clave,
        ]);

        return response()->json($resumen);
    }

    /**
     * Exportación agregada de un apartado completo (PDF o Excel): TODOS sus
     * conceptos ya resueltos en un solo archivo, no uno por concepto.
     *
     * Mismo gate que `apartado()` — reusa el permiso `.apartado.{clave}.view`,
     * no crea uno nuevo. Reusa `CompletitudService::apartado()` (misma fuente
     * de datos que el detalle en vivo) para no duplicar el recorrido por
     * `ResolverFactory`.
     */
    public function exportarApartado(string $clave, Request $request)
    {
        $empresa = $this->empresas->actual();

        $apartado = DcApartado::deEmpresa($empresa->id)
            ->activos()
            ->where('clave', mb_strtoupper($clave))
            ->firstOrFail();

        if (! $this->puedeVer($apartado->permiso())) {
            return response()->json([
                'message' => 'No tienes permiso para ver este apartado.',
            ], 403);
        }

        $formato = mb_strtolower((string) $request->query('formato', 'pdf'));

        if (! in_array($formato, ['pdf', 'excel'], true)) {
            return response()->json([
                'message' => "Formato de exportación no soportado: '{$formato}'. Disponibles: pdf, excel.",
            ], 422);
        }

        $resumen = $this->completitud->apartado($apartado, $empresa->id);

        // Se registra ANTES de servir el archivo (regla del propio servicio):
        // si el registro falla, el archivo no sale.
        $this->bitacora->exportar($empresa->id, $apartado->id, null, [
            'pantalla' => 'apartado.exportar',
            'clave'    => $apartado->clave,
            'formato'  => $formato,
        ]);

        $nombreBase = 'dc-apartado-' . mb_strtolower($apartado->clave) . '-' . now()->format('Ymd-His') . '-' . Str::random(6);

        if ($formato === 'excel') {
            return Excel::download(
                new DocumentacionApartadoExport($apartado->clave, $resumen['conceptos']),
                "{$nombreBase}.xlsx"
            );
        }

        $pdf = Pdf::loadView('addon-documentacion-corporativa::export.apartado', [
            'apartado'  => $apartado,
            'conceptos' => $resumen['conceptos'],
            'generado'  => now(),
        ]);

        $destino = $this->rutaTemporalApartado($nombreBase);
        file_put_contents($destino, $pdf->output());

        return response()->download($destino, "{$nombreBase}.pdf")->deleteFileAfterSend(true);
    }

    /**
     * Detalle NOMINAL (con nombre) de la cartera de clientes — Apartado IV.
     *
     * Concepto APARTE del agregado ('clientes.cartera', `config.detalle=agregado`
     * en `FinanzasFuentes::cartera()`, fuera de esta fase por diseño): expone
     * identidad de clientes con saldo, así que exige, además del gate normal del
     * apartado, el permiso de descarga `documentacion-corporativa.documento.download`
     * (ya sembrado y asignado, no se crea aquí) + justificación de texto libre
     * ANTES de generarse — regla dura LFPDPPP del item padre #663.
     *
     * Reusa el mismo mecanismo de exportación PDF/Excel de `exportarApartado()`
     * (item #785), envolviendo la lista nominal como un único "concepto" — no
     * se duplica ninguna clase de export ni vista.
     */
    public function carteraDetalleNominal(Request $request)
    {
        $empresa = $this->empresas->actual();

        $apartado = DcApartado::deEmpresa($empresa->id)
            ->activos()
            ->where('clave', 'IV')
            ->first();

        if ($apartado && ! $this->puedeVer($apartado->permiso())) {
            return response()->json([
                'message' => 'No tienes permiso para ver este apartado.',
            ], 403);
        }

        if (! $this->puedeVer('documentacion-corporativa.documento.download')) {
            return response()->json([
                'message' => 'No tienes permiso para descargar el detalle nominal de cartera.',
            ], 403);
        }

        $justificacion = trim((string) $request->query('justificacion', ''));
        if ($justificacion === '') {
            return response()->json([
                'message' => 'La justificación es obligatoria para exportar datos personales de clientes.',
            ], 422);
        }

        $formato = mb_strtolower((string) $request->query('formato', 'excel'));
        if (! in_array($formato, ['pdf', 'excel'], true)) {
            return response()->json([
                'message' => "Formato de exportación no soportado: '{$formato}'. Disponibles: pdf, excel.",
            ], 422);
        }

        $concepto = $apartado
            ? DcConcepto::deEmpresa($empresa->id)->where('apartado_id', $apartado->id)->where('slug', 'cartera-de-clientes')->first()
            : null;

        $filas = Balance::query()
            ->where('balanceable_type', Client::class)
            ->where('amount', '<', 0)
            ->join('client_main_information as cmi', 'cmi.client_id', '=', 'balances.balanceable_id')
            ->orderBy('cmi.name')
            ->get([
                'balances.balanceable_id as cliente_id',
                DB::raw("TRIM(CONCAT(cmi.name, ' ', COALESCE(cmi.father_last_name, ''), ' ', COALESCE(cmi.mother_last_name, ''))) as nombre"),
                'balances.amount as saldo',
            ])
            ->map(fn ($r) => [
                'cliente_id' => $r->cliente_id,
                'nombre'     => $r->nombre,
                'saldo'      => round(abs((float) $r->saldo), 2),
            ])->all();

        // Se registra ANTES de servir el archivo (misma regla que exportarApartado):
        // la justificación queda en el contexto de la bitácora, append-only.
        $this->bitacora->exportar($empresa->id, $apartado?->id, $concepto?->id, [
            'pantalla'       => 'apartado.iv.cartera.detalle_nominal',
            'justificacion'  => $justificacion,
            'formato'        => $formato,
            'total_clientes' => count($filas),
        ]);

        $conceptos = [[
            'nombre'  => 'Cartera de clientes — detalle nominal',
            'datos'   => $filas,
            'mensaje' => $filas === [] ? 'Sin clientes con saldo negativo en este entorno.' : null,
        ]];

        $nombreBase = 'dc-cartera-detalle-nominal-' . now()->format('Ymd-His') . '-' . Str::random(6);

        if ($formato === 'excel') {
            return Excel::download(
                new DocumentacionApartadoExport('IV', $conceptos),
                "{$nombreBase}.xlsx"
            );
        }

        $pdf = Pdf::loadView('addon-documentacion-corporativa::export.apartado', [
            'apartado'  => $apartado ?? (object) [
                'clave'       => 'IV',
                'nombre'      => 'Cuentas por cobrar, por pagar y flujo',
                'descripcion' => 'Detalle nominal de cartera de clientes',
            ],
            'conceptos' => $conceptos,
            'generado'  => now(),
        ]);

        $destino = $this->rutaTemporalApartado($nombreBase);
        file_put_contents($destino, $pdf->output());

        return response()->download($destino, "{$nombreBase}.pdf")->deleteFileAfterSend(true);
    }

    /**
     * Acuse de avance en PDF con corte a una fecha (item #9990551): avance
     * global, avance por apartado, obligatorios faltantes y responsables —
     * evidencia frente a la mesa directiva. Reusa `CompletitudService::tablero()`
     * (misma fuente que el tablero en vivo, filtrada a lo que ESTE usuario puede
     * ver, igual que `tablero()`) y el patrón de export de `exportarApartado()`
     * (Pdf::loadView + descarga temporal).
     *
     * NO usa `PlantillaDocumentoService`: ese motor genera documentos-por-concepto
     * a partir de una plantilla del catálogo (`plantilla_id`), y este es un reporte
     * agregado ad-hoc sin plantilla asignada — no gana nada de complejidad extra
     * (item #840, "no unificar Plantillas").
     *
     * El "corte" es del ESTADO ACTUAL: no existe en el módulo ninguna tabla de
     * snapshots históricos de completitud, así que `fecha_corte` solo se imprime
     * como referencia declarada en el documento, no reconstruye avance pasado.
     */
    public function exportarAcuse(Request $request)
    {
        $empresa = $this->empresas->actual();

        $fechaCorte = $request->filled('fecha_corte')
            ? Carbon::parse($request->query('fecha_corte'))->endOfDay()
            : now();

        $tablero = $this->completitud->tablero($empresa->id, $request->boolean('refrescar'));

        $visibles = array_values(array_filter(
            $tablero['apartados'],
            fn (array $a) => $this->puedeVer($a['permiso'])
        ));

        $global = $this->completitud->agregarGlobal($visibles);

        // Se registra ANTES de servir el archivo (misma regla que exportarApartado).
        $this->bitacora->exportar($empresa->id, null, null, [
            'pantalla'            => 'acuse.exportar',
            'fecha_corte'         => $fechaCorte->toDateString(),
            'apartados_incluidos' => array_column($visibles, 'clave'),
        ]);

        $nombreBase = 'dc-acuse-avance-' . now()->format('Ymd-His') . '-' . Str::random(6);

        $pdf = Pdf::loadView('addon-documentacion-corporativa::export.acuse', [
            'empresa'    => $empresa,
            'apartados'  => $visibles,
            'global'     => $global,
            'fechaCorte' => $fechaCorte,
            'generado'   => now(),
        ]);

        $destino = $this->rutaTemporalApartado($nombreBase);
        file_put_contents($destino, $pdf->output());

        return response()->download($destino, "{$nombreBase}.pdf")->deleteFileAfterSend(true);
    }

    public function cambiarEmpresa(Request $request): JsonResponse
    {
        $validado = $request->validate(['empresa_id' => ['required', 'integer']]);

        if (! $this->empresas->cambiar((int) $validado['empresa_id'])) {
            return response()->json(['message' => 'Empresa no disponible.'], 422);
        }

        return response()->json(['empresa_id' => $this->empresas->actualId()]);
    }

    private function puedeVer(string $permiso): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can($permiso);
    }

    /** Mismo directorio temporal que usa `BaseResolver::rutaTemporal()` para exports por concepto. */
    private function rutaTemporalApartado(string $nombreBase): string
    {
        $dir = storage_path('app/documentacion_corporativa/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0770, true);
        }

        return $dir . '/' . $nombreBase . '.pdf';
    }
}
