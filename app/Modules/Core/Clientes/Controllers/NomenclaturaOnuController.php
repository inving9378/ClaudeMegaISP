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
        ]);

        $info = ClientAdditionalInformation::where('client_id', $clientId)->firstOrFail();
        $info->box_nomenclator = $data['box_nomenclator'];
        $info->save();

        return response()->json(['ok' => true, 'box_nomenclator' => $info->box_nomenclator]);
    }
}
