<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoDocumentTemplate;
use App\Modules\Addons\Talento\Models\TalentoPuestoDocumentTemplate;
use Illuminate\Http\Request;

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
        $puestos = TalentoColaborador::query()
            ->whereNotNull('job_title')
            ->where('job_title', '!=', '')
            ->distinct()
            ->orderBy('job_title')
            ->pluck('job_title');

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
            'puesto' => 'required|string|max:100',
        ]);

        $templateIds = TalentoPuestoDocumentTemplate::where('puesto', $data['puesto'])
            ->pluck('template_id');

        return response()->json($templateIds);
    }

    // ── API: toggle asignación ──────────────────────────────────────────────

    public function toggle(Request $request)
    {
        $data = $request->validate([
            'puesto'      => 'required|string|max:100',
            'template_id' => 'required|integer|exists:talento_document_templates,id',
            'asignado'    => 'required|boolean',
        ]);

        if ($data['asignado']) {
            TalentoPuestoDocumentTemplate::firstOrCreate([
                'puesto'      => $data['puesto'],
                'template_id' => $data['template_id'],
            ]);
        } else {
            TalentoPuestoDocumentTemplate::where('puesto', $data['puesto'])
                ->where('template_id', $data['template_id'])
                ->delete();
        }

        return response()->json(['ok' => true, 'asignado' => (bool) $data['asignado']]);
    }
}
