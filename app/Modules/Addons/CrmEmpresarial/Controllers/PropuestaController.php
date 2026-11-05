<?php

namespace App\Modules\Addons\CrmEmpresarial\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\CrmEmpresarial\Models\CrmExpediente;
use App\Modules\Addons\CrmEmpresarial\Models\CrmPropuesta;
use App\Modules\Addons\CrmEmpresarial\Models\CrmPropuestaVersion;
use App\Modules\Addons\CrmEmpresarial\Services\PropuestaService;
use Illuminate\Http\Request;

class PropuestaController extends Controller
{
    /** Permiso requerido por estado destino (el resto cae en .create). */
    private const PERMISO_DESTINO = ['aprobada' => 'aprobar', 'enviada' => 'enviar'];

    public function index(Request $r, int $expediente)
    {
        $this->can($r, 'view');
        CrmExpediente::findOrFail($expediente);

        return response()->json(CrmPropuesta::where('expediente_id', $expediente)->with('versiones.historial')->orderByDesc('id')->get());
    }

    /** Para la pestaña del prospecto: resuelve el expediente por crm_id y entrega propuestas + permisos del usuario. */
    public function porCrm(Request $r, int $crm)
    {
        $this->can($r, 'view');
        $exp = CrmExpediente::where('crm_id', $crm)->first();
        $permisos = [];
        foreach (['view', 'create', 'aprobar', 'enviar'] as $a) {
            $permisos[$a] = (bool) $r->user()->can("crm-empresarial.propuestas.$a");
        }

        return response()->json([
            'expediente_id' => $exp?->id,
            'permisos' => $permisos,
            'transiciones' => PropuestaService::TRANSICIONES,
            'propuestas' => $exp ? CrmPropuesta::where('expediente_id', $exp->id)->with('versiones.historial')->orderByDesc('id')->get() : [],
        ]);
    }

    public function store(Request $r, int $expediente, PropuestaService $svc)
    {
        $this->can($r, 'create');
        $p = $svc->crear(CrmExpediente::findOrFail($expediente), $r->user()->id, $r->integer('vigencia_dias') ?: null);

        return response()->json($p->load('versiones'), 201);
    }

    public function nuevaVersion(Request $r, int $id, PropuestaService $svc)
    {
        $this->can($r, 'create');
        $d = $r->validate(['motivo' => 'required|string', 'vigencia_dias' => 'nullable|integer|min:1']);
        $v = $svc->nuevaVersion(CrmPropuesta::findOrFail($id), $r->only(PropuestaService::CAMPOS_EDITABLES), $d['motivo'], $r->user()->id, $d['vigencia_dias'] ?? null);

        return response()->json($v, 201);
    }

    public function editar(Request $r, int $id, PropuestaService $svc)
    {
        $this->can($r, 'create');

        return response()->json($svc->editarBorrador(CrmPropuestaVersion::findOrFail($id), $r->only(PropuestaService::CAMPOS_EDITABLES), $r->user()->id));
    }

    public function transicion(Request $r, int $id, PropuestaService $svc)
    {
        $d = $r->validate(['a' => 'required|in:' . implode(',', PropuestaService::ESTADOS), 'motivo' => 'nullable|string']);
        $this->can($r, self::PERMISO_DESTINO[$d['a']] ?? 'create');

        return response()->json($svc->transicionar(CrmPropuestaVersion::findOrFail($id), $d['a'], $r->user()->id, $d['motivo'] ?? null));
    }

    private function can(Request $r, string $accion): void
    {
        abort_unless($r->user()?->can("crm-empresarial.propuestas.$accion"), 403);
    }
}
