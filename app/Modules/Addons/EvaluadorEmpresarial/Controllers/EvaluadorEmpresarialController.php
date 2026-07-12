<?php

namespace App\Modules\Addons\EvaluadorEmpresarial\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\EvaluadorEmpresarial\Mail\EvaluacionEmpresarialMail;
use App\Modules\Addons\EvaluadorEmpresarial\Models\EvaluacionEmpresarial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvaluadorEmpresarialController extends Controller
{
    // ─────────────────────────────────────────
    // RUTAS PÚBLICAS (acceso sin auth)
    // ─────────────────────────────────────────

    /** GET /evaluador-empresarial */
    public function index()
    {
        return view('addon-evaluador-empresarial::evaluador-empresarial');
    }

    /** POST /evaluador-empresarial/guardar */
    public function guardar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre_contacto'   => 'required|string|max:120',
            'empresa'           => 'required|string|max:120',
            'email_contacto'    => 'nullable|email|max:150',
            'whatsapp_contacto' => 'nullable|string|max:20',
            'cargo'             => 'nullable|string|max:80',
            'puntaje_total'     => 'required|integer|min:0|max:9999',
            'categoria'         => 'required|in:BASICO,PROFESIONAL,CORPORATIVO,ENTERPRISE',
            'plan_recomendado'  => 'nullable|string|max:100',
            'respuestas_json'   => 'required|array',
            'score_criticidad'  => 'nullable|integer|min:0',
            'score_redundancia' => 'nullable|integer|min:0',
            'score_ancho_banda' => 'nullable|integer|min:0',
            'score_sla'         => 'nullable|integer|min:0',
            'canal_origen'      => 'nullable|in:whatsapp,email,directo,vendedor',
            'vendedor_id'       => 'nullable|integer|exists:users,id',
        ]);

        try {
            $evaluacion = EvaluacionEmpresarial::create(array_merge($validated, [
                'completado_at' => now(),
                'canal_origen'  => $validated['canal_origen'] ?? 'directo',
            ]));

            return response()->json([
                'success'     => true,
                'id'          => $evaluacion->id,
                'token'       => $evaluacion->token_publico,
                'lead_creado' => false,
                'mensaje'     => 'Evaluación guardada correctamente',
            ], 201);

        } catch (\Throwable $e) {
            Log::error('EvaluadorEmpresarial: Error al guardar', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'mensaje' => 'Error interno'], 500);
        }
    }

    /**
     * POST /evaluador-empresarial/enviar-email
     *
     * El destinatario NUNCA se toma del request (evitaba que la ruta pública se usara como
     * relay de email a cualquier dirección arbitraria). Se envía SIEMPRE al email_contacto ya
     * capturado en la propia evaluación.
     */
    public function enviarEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'evaluacion_id' => 'required|integer|exists:evaluaciones_empresariales,id',
        ]);

        try {
            $evaluacion = EvaluacionEmpresarial::findOrFail($validated['evaluacion_id']);

            if (empty($evaluacion->email_contacto)) {
                return response()->json(['success' => false, 'mensaje' => 'La evaluación no tiene email de contacto'], 422);
            }

            Mail::to($evaluacion->email_contacto)
                ->send(new EvaluacionEmpresarialMail($evaluacion));

            return response()->json(['success' => true, 'mensaje' => 'Email enviado correctamente']);

        } catch (\Throwable $e) {
            Log::error('EvaluadorEmpresarial: Error enviando email', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'mensaje' => 'No se pudo enviar el email'], 500);
        }
    }

    /** GET /evaluador-empresarial/resultado/{token} — vista pública del resultado */
    public function resultado(string $token)
    {
        $evaluacion = EvaluacionEmpresarial::where('token_publico', $token)->firstOrFail();

        return view('addon-evaluador-empresarial::evaluador-resultado', [
            'evaluacion' => $evaluacion,
        ]);
    }

    // ─────────────────────────────────────────
    // RUTAS STAFF (auth + check_route_permission)
    // ─────────────────────────────────────────

    /** GET /admin/evaluaciones-panel */
    public function panel()
    {
        return view('addon-evaluador-empresarial::evaluaciones-panel');
    }

    /** GET /admin/evaluaciones — DataTable JSON */
    public function listar(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'fecha_desde'  => 'nullable|date',
            'fecha_hasta'  => 'nullable|date',
            'categoria'    => 'nullable|in:BASICO,PROFESIONAL,CORPORATIVO,ENTERPRISE',
            'vendedor_id'  => 'nullable|integer|exists:users,id',
            'canal_origen' => 'nullable|in:whatsapp,email,directo,vendedor',
            'per_page'     => 'nullable|integer|min:5|max:200',
        ]);

        $query = EvaluacionEmpresarial::query()
            ->with('vendedor:id,name')
            ->select([
                'id', 'nombre_contacto', 'empresa', 'email_contacto', 'whatsapp_contacto',
                'puntaje_total', 'categoria', 'plan_recomendado', 'canal_origen',
                'vendedor_id', 'lead_id', 'completado_at', 'created_at',
            ]);

        if (!empty($filtros['fecha_desde'])) {
            $query->whereDate('created_at', '>=', $filtros['fecha_desde']);
        }
        if (!empty($filtros['fecha_hasta'])) {
            $query->whereDate('created_at', '<=', $filtros['fecha_hasta']);
        }
        if (!empty($filtros['categoria'])) {
            $query->where('categoria', $filtros['categoria']);
        }
        if (!empty($filtros['vendedor_id'])) {
            $query->where('vendedor_id', $filtros['vendedor_id']);
        }
        if (!empty($filtros['canal_origen'])) {
            $query->where('canal_origen', $filtros['canal_origen']);
        }

        $perPage = $filtros['per_page'] ?? 20;
        $paginado = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $paginado->items(),
            'meta'    => [
                'total'        => $paginado->total(),
                'current_page' => $paginado->currentPage(),
                'last_page'    => $paginado->lastPage(),
                'per_page'     => $paginado->perPage(),
            ],
        ]);
    }

    /** GET /admin/evaluaciones/{id} */
    public function mostrar(int $id): JsonResponse
    {
        $evaluacion = EvaluacionEmpresarial::with('vendedor:id,name')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $evaluacion,
        ]);
    }

    /** GET /admin/evaluaciones/estadisticas */
    public function estadisticas(): JsonResponse
    {
        $porCategoria = EvaluacionEmpresarial::query()
            ->select('categoria', DB::raw('COUNT(*) as total'), DB::raw('AVG(puntaje_total) as promedio_puntaje'))
            ->groupBy('categoria')
            ->get();

        $totales = [
            'total_evaluaciones'    => EvaluacionEmpresarial::count(),
            'completadas'           => EvaluacionEmpresarial::completadas()->count(),
            'convertidas_a_lead'    => EvaluacionEmpresarial::whereNotNull('lead_id')->count(),
            'promedio_puntaje'      => round((float) EvaluacionEmpresarial::avg('puntaje_total'), 2),
        ];

        $porCanal = EvaluacionEmpresarial::query()
            ->select('canal_origen', DB::raw('COUNT(*) as total'))
            ->groupBy('canal_origen')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'totales'       => $totales,
                'por_categoria' => $porCategoria,
                'por_canal'     => $porCanal,
            ],
        ]);
    }

    /** GET /admin/evaluaciones/exportar — CSV streaming */
    public function exportar(Request $request): StreamedResponse
    {
        $filtros = $request->validate([
            'fecha_desde'  => 'nullable|date',
            'fecha_hasta'  => 'nullable|date',
            'categoria'    => 'nullable|in:BASICO,PROFESIONAL,CORPORATIVO,ENTERPRISE',
            'vendedor_id'  => 'nullable|integer|exists:users,id',
            'canal_origen' => 'nullable|in:whatsapp,email,directo,vendedor',
        ]);

        $query = EvaluacionEmpresarial::query()->with('vendedor:id,name');

        if (!empty($filtros['fecha_desde'])) {
            $query->whereDate('created_at', '>=', $filtros['fecha_desde']);
        }
        if (!empty($filtros['fecha_hasta'])) {
            $query->whereDate('created_at', '<=', $filtros['fecha_hasta']);
        }
        if (!empty($filtros['categoria'])) {
            $query->where('categoria', $filtros['categoria']);
        }
        if (!empty($filtros['vendedor_id'])) {
            $query->where('vendedor_id', $filtros['vendedor_id']);
        }
        if (!empty($filtros['canal_origen'])) {
            $query->where('canal_origen', $filtros['canal_origen']);
        }

        $filename = 'evaluaciones_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'ID', 'Fecha', 'Empresa', 'Contacto', 'Cargo', 'Email', 'WhatsApp',
                'Categoría', 'Puntaje', 'Plan Recomendado',
                'Score Criticidad', 'Score Redundancia', 'Score Ancho Banda', 'Score SLA',
                'Canal', 'Vendedor', 'Lead ID',
            ]);

            $query->orderByDesc('created_at')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->id,
                        optional($row->created_at)->format('Y-m-d H:i'),
                        $row->empresa,
                        $row->nombre_contacto,
                        $row->cargo,
                        $row->email_contacto,
                        $row->whatsapp_contacto,
                        $row->categoria,
                        $row->puntaje_total,
                        $row->plan_recomendado,
                        $row->score_criticidad,
                        $row->score_redundancia,
                        $row->score_ancho_banda,
                        $row->score_sla,
                        $row->canal_origen,
                        $row->vendedor->name ?? null,
                        $row->lead_id,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
