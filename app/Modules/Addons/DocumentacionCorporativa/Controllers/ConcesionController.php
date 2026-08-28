<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcesion;
use App\Modules\Addons\DocumentacionCorporativa\Services\BitacoraService;
use App\Modules\Addons\DocumentacionCorporativa\Services\ConcesionCalendarioService;
use App\Modules\Addons\DocumentacionCorporativa\Services\EmpresaContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Apartado XIII — concesiones, permisos y calendario regulatorio.
 *
 * Misma doble puerta que `ExpedienteController`: la entrada al módulo ya la
 * gatea `check_route_permission` (`documentacion-corporativa.view`); aquí se
 * gatea VER (permiso propio del apartado XIII) y ADMINISTRAR (crear/editar
 * concesiones y sus pagos) por separado, porque un miembro del consejo puede
 * ver el apartado sin poder escribir en él.
 */
class ConcesionController extends Controller
{
    private const PERMISO_VER     = 'documentacion-corporativa.apartado.xiii.view';
    private const PERMISO_MANAGE  = 'documentacion-corporativa.concesion.manage';

    public function __construct(
        private EmpresaContextService $empresas,
        private ConcesionCalendarioService $calendarioService,
        private BitacoraService $bitacora,
    ) {
    }

    /** Calendario regulatorio: concesiones por escalón de alerta + pagos próximos. */
    public function calendario(): JsonResponse
    {
        $this->autorizarVer();
        $empresa  = $this->empresas->actual();
        $datos    = $this->calendarioService->calendario($empresa->id);

        $this->bitacora->ver($empresa->id, null, null, ['pantalla' => 'concesiones.calendario']);

        return response()->json($datos);
    }

    /** Lista completa (para la ficha "ver todas"), filtrable por tipo/estado. */
    public function index(Request $request): JsonResponse
    {
        $this->autorizarVer();
        $empresa = $this->empresas->actual();

        $query = DcConcesion::deEmpresa($empresa->id)
            ->with('responsable:id,name')
            ->orderBy('vigencia_fin');

        if ($tipo = $request->string('tipo')->toString()) {
            $query->where('tipo', $tipo);
        }
        if ($estado = $request->string('estado_tramite')->toString()) {
            $query->where('estado_tramite', $estado);
        }

        $concesiones = $query->get();

        $this->bitacora->ver($empresa->id, null, null, ['pantalla' => 'concesiones.index']);

        return response()->json(['data' => $concesiones]);
    }

    /** Ficha de una concesión: sus datos, obligaciones y sus pagos. */
    public function show(int $id): JsonResponse
    {
        $this->autorizarVer();
        $empresa = $this->empresas->actual();

        $concesion = DcConcesion::deEmpresa($empresa->id)
            ->with(['responsable:id,name', 'documento', 'pagos'])
            ->findOrFail($id);

        $this->bitacora->ver($empresa->id, null, null, [
            'pantalla'     => 'concesiones.ficha',
            'concesion_id' => $concesion->id,
        ]);

        return response()->json($concesion);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizarManage();
        $empresa = $this->empresas->actual();

        $validado = $request->validate($this->reglas());
        $validado['empresa_id'] = $empresa->id;

        $concesion = DcConcesion::create($validado);

        return response()->json($concesion->fresh('responsable'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->autorizarManage();
        $empresa = $this->empresas->actual();

        $concesion = DcConcesion::deEmpresa($empresa->id)->findOrFail($id);
        $concesion->update($request->validate($this->reglas()));

        return response()->json($concesion->fresh('responsable'));
    }

    /** Agrega una obligación de pago a la concesión. */
    public function storePago(Request $request, int $id): JsonResponse
    {
        $this->autorizarManage();
        $empresa = $this->empresas->actual();

        $concesion = DcConcesion::deEmpresa($empresa->id)->findOrFail($id);

        $validado = $request->validate([
            'concepto'           => ['required', 'string', 'max:255'],
            'periodo'            => ['nullable', 'string', 'max:120'],
            'monto'              => ['nullable', 'numeric', 'min:0'],
            'fecha_vencimiento'  => ['nullable', 'date'],
            'fecha_pago'         => ['nullable', 'date'],
        ]);
        $validado['empresa_id'] = $empresa->id;

        $pago = $concesion->pagos()->create($validado);

        return response()->json($pago, 201);
    }

    /** Marca un pago como cubierto hoy. */
    public function marcarPagado(int $id, int $pagoId): JsonResponse
    {
        $this->autorizarManage();
        $empresa = $this->empresas->actual();

        $pago = DcConcesion::deEmpresa($empresa->id)->findOrFail($id)
            ->pagos()->findOrFail($pagoId);

        $pago->update(['fecha_pago' => now()->toDateString()]);

        return response()->json($pago);
    }

    /** Candidatos a `responsable_user_id` para el formulario de alta/edición. */
    public function responsables(Request $request): JsonResponse
    {
        $this->autorizarManage();

        $search = trim((string) $request->input('search', ''));

        $usuarios = User::query()
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'client'))
            ->has('roles')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('father_last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'father_last_name', 'mother_last_name'])
            ->map(fn (User $u) => [
                'id'   => $u->id,
                'name' => trim("{$u->name} {$u->father_last_name} {$u->mother_last_name}"),
            ]);

        return response()->json($usuarios);
    }

    private function reglas(): array
    {
        return [
            'tipo'                 => ['required', Rule::in(DcConcesion::TIPOS)],
            'autoridad'            => ['nullable', 'string', 'max:255'],
            'folio'                => ['nullable', 'string', 'max:255'],
            'objeto'               => ['nullable', 'string'],
            'fecha_otorgamiento'   => ['nullable', 'date'],
            'vigencia_fin'         => ['required', 'date'],
            'obligaciones'         => ['nullable', 'string'],
            'responsable_user_id'  => ['required', 'integer', 'exists:users,id'],
            'estado_tramite'       => ['nullable', Rule::in(DcConcesion::ESTADOS_TRAMITE)],
            'documento_id'         => ['nullable', 'integer', 'exists:dc_documentos,id'],
        ];
    }

    private function autorizarVer(): void
    {
        abort_unless(
            auth()->user()?->can(self::PERMISO_VER),
            403,
            'No tienes permiso para ver este apartado.'
        );
    }

    private function autorizarManage(): void
    {
        abort_unless(
            auth()->user()?->can(self::PERMISO_MANAGE),
            403,
            'No tienes permiso para administrar concesiones.'
        );
    }
}
