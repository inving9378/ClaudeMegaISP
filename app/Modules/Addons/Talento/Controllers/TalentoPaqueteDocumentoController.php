<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoDocumentTemplate;
use App\Modules\Addons\Talento\Models\TalentoPuesto;
use App\Modules\Addons\Talento\Models\TalentoPuestoDocumentTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    // ── API: sincronizar paquete completo en una sola escritura ────────────

    public function sincronizar(Request $request)
    {
        $data = $request->validate([
            'puesto_id'          => 'required|integer|exists:talento_puestos,id',
            'template_ids'       => 'array',
            'template_ids.*'     => 'integer|exists:talento_document_templates,id',
        ]);

        $templateIdsDeseados = collect($data['template_ids'] ?? [])->unique()->values();

        $templateIdsFinales = DB::transaction(function () use ($data, $templateIdsDeseados) {
            $nombre = TalentoPuesto::whereKey($data['puesto_id'])->value('nombre');

            $actuales = TalentoPuestoDocumentTemplate::where('puesto_id', $data['puesto_id'])
                ->pluck('template_id');

            $aAgregar = $templateIdsDeseados->diff($actuales);
            $aQuitar  = $actuales->diff($templateIdsDeseados);

            foreach ($aAgregar as $templateId) {
                // Dual-write (item #923): igual que `toggle`, conserva `puesto` (string) como
                // snapshot legible hasta la contracción que retire la columna vieja.
                TalentoPuestoDocumentTemplate::firstOrCreate(
                    ['puesto_id' => $data['puesto_id'], 'template_id' => $templateId],
                    ['puesto' => $nombre]
                );
            }

            if ($aQuitar->isNotEmpty()) {
                TalentoPuestoDocumentTemplate::where('puesto_id', $data['puesto_id'])
                    ->whereIn('template_id', $aQuitar)
                    ->delete();
            }

            return TalentoPuestoDocumentTemplate::where('puesto_id', $data['puesto_id'])
                ->pluck('template_id');
        });

        return response()->json([
            'ok'           => true,
            'puesto_id'    => (int) $data['puesto_id'],
            'template_ids' => $templateIdsFinales->values(),
        ]);
    }
}
