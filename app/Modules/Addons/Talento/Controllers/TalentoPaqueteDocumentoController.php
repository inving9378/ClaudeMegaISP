<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoDocumentTemplate;
use App\Modules\Addons\Talento\Models\TalentoPuesto;
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
        // Item #923: reapuntado del texto libre `job_title` al catálogo `talento_puestos`.
        $puestos = TalentoPuesto::where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

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
            'puesto_id' => 'required|integer|exists:talento_puestos,id',
        ]);

        $templateIds = TalentoPuestoDocumentTemplate::where('puesto_id', $data['puesto_id'])
            ->pluck('template_id');

        return response()->json($templateIds);
    }

    // ── API: toggle asignación ──────────────────────────────────────────────

    public function toggle(Request $request)
    {
        $data = $request->validate([
            'puesto_id'   => 'required|integer|exists:talento_puestos,id',
            'template_id' => 'required|integer|exists:talento_document_templates,id',
            'asignado'    => 'required|boolean',
        ]);

        if ($data['asignado']) {
            // Dual-write (item #923): `puesto` (string) se conserva como snapshot legible hasta
            // la contracción que retire la columna vieja; `puesto_id` es la fuente de verdad.
            $nombre = TalentoPuesto::whereKey($data['puesto_id'])->value('nombre');

            TalentoPuestoDocumentTemplate::firstOrCreate(
                ['puesto_id' => $data['puesto_id'], 'template_id' => $data['template_id']],
                ['puesto' => $nombre]
            );
        } else {
            TalentoPuestoDocumentTemplate::where('puesto_id', $data['puesto_id'])
                ->where('template_id', $data['template_id'])
                ->delete();
        }

        return response()->json(['ok' => true, 'asignado' => (bool) $data['asignado']]);
    }
}
