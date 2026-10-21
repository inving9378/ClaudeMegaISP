<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoDocumentTemplate;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoPuestoDocumentTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class TalentoPaqueteDocumentoController extends Controller
{
    // ── Vista admin ───────────────────────────────────────────────────────────

    public function index()
    {
        return view('addon-talento::talento.expediente_paquetes');
    }

    // ── API: catálogo ────────────────────────────────────────────────────────

    public function puestos()
    {
        // El puesto es el rol de Administración (2026-10-21): `id` es el nombre del rol, que es lo
        // que guarda la columna `puesto` del paquete.
        $puestos = Role::whereNotIn('name', TalentoColaborador::ROLES_QUE_NO_SON_PUESTO)
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($rol) => ['id' => $rol, 'nombre' => TalentoColaborador::nombrePuesto($rol)])
            ->values();

        return response()->json($puestos);
    }

    public function templates()
    {
        $templates = TalentoDocumentTemplate::active()
            ->orderBy('name')
            ->get(['id', 'name', 'category']);

        return response()->json($templates);
    }

    public function asignaciones(Request $request)
    {
        $data = $request->validate([
            'rol' => 'required|string|exists:roles,name',
        ]);

        $templateIds = TalentoPuestoDocumentTemplate::where('puesto', $data['rol'])
            ->pluck('template_id');

        return response()->json($templateIds);
    }

    // ── API: sincronizar paquete completo en una sola escritura ────────────

    public function sincronizar(Request $request)
    {
        $data = $request->validate([
            'rol'                => 'required|string|exists:roles,name',
            'template_ids'       => 'array',
            'template_ids.*'     => 'integer|exists:talento_document_templates,id',
        ]);

        $templateIdsDeseados = collect($data['template_ids'] ?? [])->unique()->values();

        $templateIdsFinales = DB::transaction(function () use ($data, $templateIdsDeseados) {
            $actuales = TalentoPuestoDocumentTemplate::where('puesto', $data['rol'])
                ->pluck('template_id');

            $aAgregar = $templateIdsDeseados->diff($actuales);
            $aQuitar  = $actuales->diff($templateIdsDeseados);

            foreach ($aAgregar as $templateId) {
                TalentoPuestoDocumentTemplate::firstOrCreate(
                    ['puesto' => $data['rol'], 'template_id' => $templateId]
                );
            }

            if ($aQuitar->isNotEmpty()) {
                TalentoPuestoDocumentTemplate::where('puesto', $data['rol'])
                    ->whereIn('template_id', $aQuitar)
                    ->delete();
            }

            return TalentoPuestoDocumentTemplate::where('puesto', $data['rol'])
                ->pluck('template_id');
        });

        return response()->json([
            'ok'           => true,
            'rol'          => $data['rol'],
            'template_ids' => $templateIdsFinales->values(),
        ]);
    }
}
