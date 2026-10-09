<?php

namespace App\Modules\Core\Clientes\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Clientes\Models\ClientAdditionalInformation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pantalla de revisión manual (sesión 2026-10-09, decisión de Irving): reconciliar
 * `box_nomenclator_old` (nomenclatura vieja, desordenada, 4,741 de 5,796 clientes la tienen)
 * con `box_nomenclator` (la nueva/real, solo 2,119 de 5,796) — no hay forma mecánica de
 * derivar la nueva a partir de la vieja (los valores viejos son texto libre sin patrón
 * fijo), así que esto es solo-lectura de la vieja + captura manual de la nueva, una por una.
 */
class NomenclaturaOnuController extends Controller
{
    public function vista()
    {
        abort_unless(auth()->user()?->can('client_edit_client'), 403);

        return view('core-clientes::nomenclatura-onu.index');
    }

    public function pendientes(Request $request)
    {
        abort_unless(auth()->user()?->can('client_edit_client'), 403);

        $perPage = (int) $request->input('per_page', 25);

        $query = DB::table('client_additional_information as cai')
            ->join('client_main_information as cmi', 'cmi.client_id', '=', 'cai.client_id')
            ->whereNotNull('cai.box_nomenclator_old')
            ->where('cai.box_nomenclator_old', '!=', '')
            ->where(function ($q) {
                $q->whereNull('cai.box_nomenclator')->orWhere('cai.box_nomenclator', '');
            })
            ->select([
                'cai.client_id',
                'cai.box_nomenclator_old',
                'cai.box_nomenclator',
                'cmi.name',
                'cmi.father_last_name',
                'cmi.mother_last_name',
            ])
            ->orderBy('cmi.name');

        if ($buscar = trim((string) $request->input('buscar', ''))) {
            $query->where(function ($q) use ($buscar) {
                $q->where('cmi.name', 'like', "%{$buscar}%")
                    ->orWhere('cmi.father_last_name', 'like', "%{$buscar}%")
                    ->orWhere('cai.box_nomenclator_old', 'like', "%{$buscar}%")
                    ->orWhere('cai.client_id', 'like', "%{$buscar}%");
            });
        }

        return response()->json($query->paginate($perPage));
    }

    public function actualizar(Request $request, int $clientId)
    {
        abort_unless(auth()->user()?->can('client_edit_client'), 403);

        $data = $request->validate([
            'box_nomenclator' => 'required|string|max:255',
            'tipo_nomenclatura' => 'nullable|in:wifi,fibra',
        ]);

        $info = ClientAdditionalInformation::where('client_id', $clientId)->firstOrFail();
        $info->box_nomenclator = $data['box_nomenclator'];
        if (array_key_exists('tipo_nomenclatura', $data)) {
            $info->tipo_nomenclatura = $data['tipo_nomenclatura'];
        }
        $info->save();

        return response()->json([
            'ok' => true,
            'box_nomenclator' => $info->box_nomenclator,
            'tipo_nomenclatura' => $info->tipo_nomenclatura,
        ]);
    }

    /**
     * Regla de bulk (sesión 2026-10-09, decisión de Irving): "si la nomenclatura vieja
     * contiene X, es wifi/fibra" — previsualiza cuántos/cuáles clientes coinciden ANTES de
     * tocar nada. Mismo scope que pendientes(): solo los que aún no tienen box_nomenclator.
     */
    public function reglaPrevisualizar(Request $request)
    {
        abort_unless(auth()->user()?->can('client_edit_client'), 403);

        $data = $request->validate([
            'patron' => 'required|string|min:1|max:255',
            'tipo' => 'required|in:wifi,fibra',
        ]);

        $query = $this->queryPendientesPorPatron($data['patron']);

        return response()->json([
            'total' => $query->count(),
            'muestra' => $query->orderBy('cmi.name')->limit(10)->get([
                'cai.client_id', 'cai.box_nomenclator_old', 'cmi.name', 'cmi.father_last_name',
            ]),
        ]);
    }

    /**
     * Aplica la regla a TODOS los que coinciden: copia box_nomenclator_old tal cual a
     * box_nomenclator (decisión de Irving — nunca inventa un valor nuevo) y marca el tipo.
     * Transacción única, todo o nada.
     */
    public function reglaAplicar(Request $request)
    {
        abort_unless(auth()->user()?->can('client_edit_client'), 403);

        $data = $request->validate([
            'patron' => 'required|string|min:1|max:255',
            'tipo' => 'required|in:wifi,fibra',
        ]);

        $afectados = DB::transaction(function () use ($data) {
            return $this->queryPendientesPorPatron($data['patron'])
                ->update([
                    'cai.box_nomenclator' => DB::raw('cai.box_nomenclator_old'),
                    'cai.tipo_nomenclatura' => $data['tipo'],
                    'cai.updated_at' => now(),
                ]);
        });

        return response()->json(['ok' => true, 'afectados' => $afectados]);
    }

    private function queryPendientesPorPatron(string $patron)
    {
        return DB::table('client_additional_information as cai')
            ->join('client_main_information as cmi', 'cmi.client_id', '=', 'cai.client_id')
            ->whereNotNull('cai.box_nomenclator_old')
            ->where('cai.box_nomenclator_old', '!=', '')
            ->where(function ($q) {
                $q->whereNull('cai.box_nomenclator')->orWhere('cai.box_nomenclator', '');
            })
            ->where('cai.box_nomenclator_old', 'like', '%' . $patron . '%');
    }
}
