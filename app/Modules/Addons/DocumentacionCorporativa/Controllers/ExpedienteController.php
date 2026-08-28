<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcApartado;
use App\Modules\Addons\DocumentacionCorporativa\Services\BitacoraService;
use App\Modules\Addons\DocumentacionCorporativa\Services\CompletitudService;
use App\Modules\Addons\DocumentacionCorporativa\Services\EmpresaContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
