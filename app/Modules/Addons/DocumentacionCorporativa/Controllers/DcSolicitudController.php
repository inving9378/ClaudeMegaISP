<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcSolicitud;
use App\Modules\Addons\DocumentacionCorporativa\Services\EmpresaContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Fase 5a (item roadmap #758) — registro de solicitudes de información
 * recibidas (apartado XIV): quién pidió qué apartados, cuándo y con qué
 * plazo. `InventarioResolver` ya lee esta tabla para el tablero de
 * completitud (concepto "Registro de solicitudes de información recibidas");
 * este controlador es el CRUD dedicado para capturarlas.
 *
 * Misma doble puerta que `RegistroEstructuradoController`/`ConcesionController`:
 * VER lo gatea el permiso del apartado dueño (`.apartado.xiv.view`);
 * ADMINISTRAR (crear/editar/eliminar) lo gatea `.solicitud.manage`, un
 * permiso propio — no se reusa `.registro.manage` porque ese permiso es de
 * los 5 recursos genéricos de `RegistroEstructuradoController`; dc_solicitudes
 * tiene su propio controlador con reglas y campos distintos (apartados[] JSON,
 * fecha_limite autocalculada).
 */
class DcSolicitudController extends Controller
{
    private const PERMISO_MANAGE = 'documentacion-corporativa.solicitud.manage';
    private const PERMISO_VER    = 'documentacion-corporativa.apartado.xiv.view';

    public function __construct(private EmpresaContextService $empresas)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->autorizarVer();
        $empresa = $this->empresas->actual();

        $query = DcSolicitud::deEmpresa($empresa->id)
            ->with('creador:id,name')
            ->orderByRaw('fecha_limite IS NULL, fecha_limite')
            ->orderByDesc('created_at');

        if ($estado = $request->string('estado')->toString()) {
            $query->where('estado', $estado);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizarManage();
        $empresa = $this->empresas->actual();

        $validado                       = $request->validate($this->reglas());
        $validado['empresa_id']         = $empresa->id;
        $validado['creado_por_user_id'] = auth()->id();

        $solicitud              = new DcSolicitud($validado);
        $solicitud->fecha_limite = $solicitud->calcularFechaLimite();
        $solicitud->save();

        return response()->json($solicitud->fresh('creador:id,name'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->autorizarManage();
        $empresa = $this->empresas->actual();

        $solicitud = DcSolicitud::deEmpresa($empresa->id)->findOrFail($id);
        $solicitud->fill($request->validate($this->reglas()));
        $solicitud->fecha_limite = $solicitud->calcularFechaLimite();
        $solicitud->save();

        return response()->json($solicitud->fresh('creador:id,name'));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->autorizarManage();
        $empresa = $this->empresas->actual();

        DcSolicitud::deEmpresa($empresa->id)->findOrFail($id)->delete();

        return response()->json(['deleted' => true]);
    }

    private function reglas(): array
    {
        return [
            'solicitante'      => ['required', 'string', 'max:255'],
            'caracter'         => ['nullable', 'string', 'max:255'],
            'fecha_recepcion'  => ['required', 'date'],
            'plazo_dias'       => ['nullable', 'integer', 'min:0'],
            'fecha_limite'     => ['nullable', 'date'],
            'apartados'        => ['required', 'array', 'min:1'],
            'apartados.*'      => [Rule::in(DcSolicitud::APARTADOS)],
            'estado'           => ['nullable', Rule::in(DcSolicitud::ESTADOS)],
            'notas'            => ['nullable', 'string'],
        ];
    }

    private function autorizarVer(): void
    {
        abort_unless(
            auth()->user()?->can(self::PERMISO_VER),
            403,
            'No tienes permiso para ver el apartado XIV.'
        );
    }

    private function autorizarManage(): void
    {
        abort_unless(
            auth()->user()?->can(self::PERMISO_MANAGE),
            403,
            'No tienes permiso para administrar solicitudes de información.'
        );
    }
}
