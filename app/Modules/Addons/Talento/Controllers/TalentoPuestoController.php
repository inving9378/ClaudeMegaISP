<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoPuesto;
use Illuminate\Http\Request;

/**
 * Item roadmap #923 — Fase 2. Administración del catálogo de puestos (alta/edición/
 * activar-desactivar). Gateado por 'talento.puestos.manage' (solo super-administrator +
 * DESARROLLADOR, ver migración 2026_09_03_150300).
 */
class TalentoPuestoController extends Controller
{
    public function index()
    {
        return view('addon-talento::talento.puestos');
    }

    public function data()
    {
        $this->authorize('talento.puestos.manage');

        $puestos = TalentoPuesto::withCount('colaboradores')
            ->orderBy('nombre')
            ->get();

        return response()->json($puestos);
    }

    public function store(Request $request)
    {
        $this->authorize('talento.puestos.manage');

        $data = $request->validate([
            'nombre' => 'required|string|max:100|unique:talento_puestos,nombre',
            'activo' => 'sometimes|boolean',
        ]);

        $puesto = TalentoPuesto::create($data);

        return response()->json($puesto, 201);
    }

    public function update(Request $request, $id)
    {
        $this->authorize('talento.puestos.manage');

        $puesto = TalentoPuesto::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'sometimes|required|string|max:100|unique:talento_puestos,nombre,' . $puesto->id,
            'activo' => 'sometimes|boolean',
        ]);

        $puesto->update($data);

        return response()->json($puesto);
    }
}
