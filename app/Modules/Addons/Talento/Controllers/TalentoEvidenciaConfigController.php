<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TalentoEvidenciaConfigController extends Controller
{
    // ── Vista admin ───────────────────────────────────────────────────────────

    public function index()
    {
        return view('addon-talento::talento.config_evidencias');
    }

    // ── API: catálogo completo ────────────────────────────────────────────────

    public function catalogo()
    {
        $otTypes = DB::table('talento_work_order_types')
            ->orderBy('id')
            ->get(['id', 'name', 'points'])
            ->map(fn($r) => [
                'id'     => $r->id,
                'name'   => $r->name,
                'points' => (int) $r->points,
            ]);

        $evTypes = DB::table('talento_evidence_types')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'permite_varias', 'requiere_justificacion', 'es_lectura_dbm', 'es_firma'])
            ->map(fn($r) => [
                'id'                    => $r->id,
                'name'                  => $r->name,
                'permite_varias'        => (bool) $r->permite_varias,
                'requiere_justificacion'=> (bool) $r->requiere_justificacion,
                'es_lectura_dbm'        => (bool) $r->es_lectura_dbm,
                'es_firma'              => (bool) $r->es_firma,
            ]);

        // Matriz: set de "ot_type_id-evidence_type_id" marcados como obligatorios
        $reqs = DB::table('talento_ot_type_evidence_requirements')
            ->whereNull('condition')     // solo los "siempre obligatorios" para esta UI
            ->get(['ot_type_id', 'evidence_type_id'])
            ->map(fn($r) => [
                'ot_type_id'       => $r->ot_type_id,
                'evidence_type_id' => $r->evidence_type_id,
            ]);

        return response()->json([
            'ot_types'     => $otTypes,
            'ev_types'     => $evTypes,
            'requirements' => $reqs,
        ]);
    }

    // ── API: toggle obligatoria ───────────────────────────────────────────────

    public function toggle(Request $request)
    {
        $data = $request->validate([
            'work_order_type_id' => 'required|integer|exists:talento_work_order_types,id',
            'evidence_type_id'   => 'required|integer|exists:talento_evidence_types,id',
            'obligatoria'        => 'required|boolean',
        ]);

        $otTypeId = (int) $data['work_order_type_id'];
        $evTypeId = (int) $data['evidence_type_id'];
        $set      = (bool) $data['obligatoria'];

        if ($set) {
            // Crear si no existe (idempotente)
            $exists = DB::table('talento_ot_type_evidence_requirements')
                ->where('ot_type_id', $otTypeId)
                ->where('evidence_type_id', $evTypeId)
                ->whereNull('condition')
                ->exists();

            if (! $exists) {
                DB::table('talento_ot_type_evidence_requirements')->insert([
                    'ot_type_id'       => $otTypeId,
                    'evidence_type_id' => $evTypeId,
                    'condition'        => null,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
        } else {
            // Borrar solo la fila sin condición (las condicionales como 'cambio_equipo' no se tocan)
            DB::table('talento_ot_type_evidence_requirements')
                ->where('ot_type_id', $otTypeId)
                ->where('evidence_type_id', $evTypeId)
                ->whereNull('condition')
                ->delete();
        }

        return response()->json(['ok' => true, 'obligatoria' => $set]);
    }
}
