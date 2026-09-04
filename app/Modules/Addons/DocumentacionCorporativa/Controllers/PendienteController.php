<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcApartado;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcPendiente;
use App\Modules\Addons\DocumentacionCorporativa\Services\EmpresaContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Bandeja de pendientes — quién debe conseguir qué, y para cuándo.
 *
 * Misma doble puerta que `ConcesionController`: la entrada al módulo ya la
 * gatea `check_route_permission` (`documentacion-corporativa.view`); VER la
 * bandeja se filtra por los apartados que el usuario puede ver (mismo criterio
 * que `CompletitudService::tablero`); ADMINISTRAR (crear/editar/cambiar estado)
 * se gatea con el permiso propio `documentacion-corporativa.pendiente.assign`.
 */
class PendienteController extends Controller
{
    private const PERMISO_ASSIGN = 'documentacion-corporativa.pendiente.assign';

    public function __construct(private EmpresaContextService $empresas)
    {
    }

    /** Bandeja global: pendientes de los apartados que este usuario puede ver. */
    public function index(Request $request): JsonResponse
    {
        $empresa = $this->empresas->actual();

        $apartadosVisibles = DcApartado::deEmpresa($empresa->id)->activos()->get()
            ->filter(fn (DcApartado $a) => $this->puedeVer($a->permiso()))
            ->pluck('id');

        $query = DcPendiente::deEmpresa($empresa->id)
            ->whereHas('concepto', fn ($q) => $q->whereIn('apartado_id', $apartadosVisibles))
            ->with(['concepto.apartado', 'responsable:id,name'])
            ->orderByRaw('fecha_compromiso IS NULL, fecha_compromiso')
            ->orderByDesc('created_at');

        if ($responsableId = $request->integer('responsable_user_id')) {
            $query->where('responsable_user_id', $responsableId);
        }

        if ($estado = $request->string('estado')->toString()) {
            $query->where('estado', $estado);
        }

        return response()->json(['data' => $query->get()]);
    }

    /** Ficha de un pendiente (para prellenar el formulario de edición). */
    public function show(int $id): JsonResponse
    {
        $this->autorizarAssign();
        $empresa = $this->empresas->actual();

        $pendiente = DcPendiente::deEmpresa($empresa->id)
            ->with(['concepto.apartado', 'responsable:id,name'])
            ->findOrFail($id);

        return response()->json($pendiente);
    }

    /** Crea un pendiente de gestión para un concepto. */
    public function store(Request $request): JsonResponse
    {
        $this->autorizarAssign();
        $empresa = $this->empresas->actual();

        $validado = $request->validate($this->reglas());

        $concepto = DcConcepto::deEmpresa($empresa->id)->findOrFail($validado['concepto_id']);
        $validado['empresa_id'] = $empresa->id;

        $pendiente = DcPendiente::create($validado);

        return response()->json($pendiente->fresh(['concepto.apartado', 'responsable:id,name']), 201);
    }

    /** Edita responsable/fecha/comentarios/estado de un pendiente existente. */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->autorizarAssign();
        $empresa = $this->empresas->actual();

        $pendiente = DcPendiente::deEmpresa($empresa->id)->findOrFail($id);
        $pendiente->update($request->validate($this->reglas(soloEdicion: true)));

        return response()->json($pendiente->fresh(['concepto.apartado', 'responsable:id,name']));
    }

    /** Candidatos a `responsable_user_id` para el formulario de asignación. */
    public function responsables(Request $request): JsonResponse
    {
        $this->autorizarAssign();

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

    private function reglas(bool $soloEdicion = false): array
    {
        $reglas = [
            'responsable_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'fecha_compromiso'    => ['nullable', 'date'],
            'comentarios'         => ['nullable', 'string'],
            'estado'              => ['nullable', Rule::in(DcPendiente::ESTADOS)],
        ];

        if (! $soloEdicion) {
            $reglas['concepto_id'] = ['required', 'integer', 'exists:dc_conceptos,id'];
        }

        return $reglas;
    }

    private function puedeVer(string $permiso): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can($permiso);
    }

    private function autorizarAssign(): void
    {
        abort_unless(
            auth()->user()?->can(self::PERMISO_ASSIGN),
            403,
            'No tienes permiso para asignar responsables de pendientes.'
        );
    }
}
