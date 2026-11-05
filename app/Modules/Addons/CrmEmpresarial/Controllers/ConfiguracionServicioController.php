<?php

namespace App\Modules\Addons\CrmEmpresarial\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\CrmEmpresarial\Models\CrmConfiguracionServicio;
use App\Modules\Addons\CrmEmpresarial\Models\CrmExpediente;
use App\Modules\Addons\CrmEmpresarial\Services\ConfiguradorServicioService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class ConfiguracionServicioController extends Controller
{
    public function index(int $expediente)
    {
        CrmExpediente::findOrFail($expediente);

        return response()->json(CrmConfiguracionServicio::where('expediente_id', $expediente)->orderBy('id')->get());
    }

    public function pantalla(int $expediente)
    {
        CrmExpediente::findOrFail($expediente);

        return view('addon-crm-empresarial::servicios', ['expedienteId' => $expediente]);
    }

    /** Datos de apoyo de la UI: IVA vigente, catálogo con precio de lista, sitios y si el usuario aprueba precios. */
    public function contexto(Request $r, int $expediente, ConfiguradorServicioService $svc)
    {
        CrmExpediente::findOrFail($expediente);
        $cat = fn (string $tabla, string $tipo) => \Illuminate\Support\Facades\DB::table($tabla)
            ->select('id', 'title', 'price')->orderBy('title')->get()
            ->map(fn ($x) => ['tipo' => $tipo, 'id' => $x->id, 'nombre' => $x->title, 'precio_lista' => (float) $x->price]);

        try {
            $iva = $svc->ivaPct();
        } catch (\Illuminate\Validation\ValidationException) {
            $iva = null;
        }

        return response()->json([
            'iva_pct' => $iva,
            'puede_aprobar' => (bool) $r->user()?->can(ConfiguradorServicioService::PERMISO_APROBAR),
            'puede_editar' => (bool) $r->user()?->can('crm-empresarial.expediente.manage'),
            'catalogo' => $cat('customs', 'custom')->concat($cat('internets', 'internet'))->values(),
            'sitios' => \App\Modules\Addons\CrmEmpresarial\Models\CrmExpedienteSitio::where('expediente_id', $expediente)->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $r, int $expediente, ConfiguradorServicioService $svc)
    {
        CrmExpediente::findOrFail($expediente);

        return $this->guardar($r, $svc, $expediente, null, 201);
    }

    public function update(Request $r, int $expediente, int $id, ConfiguradorServicioService $svc)
    {
        $c = CrmConfiguracionServicio::where('expediente_id', $expediente)->findOrFail($id);

        return $this->guardar($r, $svc, $expediente, $c, 200);
    }

    public function destroy(Request $r, int $expediente, int $id)
    {
        abort_unless($r->user()?->can('crm-empresarial.expediente.manage'), 403);
        CrmConfiguracionServicio::where('expediente_id', $expediente)->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    private function guardar(Request $r, ConfiguradorServicioService $svc, int $expediente, ?CrmConfiguracionServicio $c, int $status)
    {
        abort_unless($r->user()?->can('crm-empresarial.expediente.manage'), 403);
        $datos = $r->validate([
            'servicio_tipo' => 'nullable|in:custom,internet', 'servicio_id' => 'nullable|integer',
            'sitio_id' => 'nullable|integer|exists:crm_expediente_sitios,id',
            'precio_aprobado' => 'nullable|numeric|min:0', 'justificacion_precio' => 'nullable|string',
            'plazo_meses' => 'nullable|integer|min:0', 'ipv4_solicitadas' => 'nullable|integer|min:0',
            'red_conmutacion' => 'nullable|in:automatica,manual',
            'fecha_estimada_entrega' => 'nullable|date', 'vigencia_propuesta' => 'nullable|date',
        ] + array_fill_keys(array_diff(ConfiguradorServicioService::EDITABLES, [
            'servicio_tipo', 'servicio_id', 'sitio_id', 'precio_aprobado', 'justificacion_precio', 'plazo_meses',
            'ipv4_solicitadas', 'red_conmutacion', 'fecha_estimada_entrega', 'vigencia_propuesta',
        ]), 'nullable'));

        try {
            return response()->json($svc->guardar($expediente, $datos, $r->user(), $c), $status);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }
}
