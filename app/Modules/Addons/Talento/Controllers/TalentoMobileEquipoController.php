<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoAttendance;
use App\Modules\Addons\Talento\Models\TalentoCajaBaseline;
use App\Modules\Addons\Talento\Models\TalentoCajaInspection;
use App\Modules\Addons\Talento\Models\TalentoCertification;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoCourse;
use App\Modules\Addons\Talento\Models\TalentoCredential;
use App\Modules\Addons\Talento\Models\TalentoDevice;
use App\Modules\Addons\Talento\Models\TalentoFund;
use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Addons\Talento\Models\TalentoLiquidation;
use App\Modules\Addons\Talento\Models\TalentoLoan;
use App\Modules\Addons\Talento\Models\TalentoPenalty;
use App\Modules\Addons\Talento\Models\TalentoPenaltyAppeal;
use App\Modules\Addons\Talento\Models\TalentoActivityReportParticipant;
use App\Modules\Addons\Talento\Models\TalentoRoute;
use App\Modules\Addons\Talento\Services\LiquidationService;
use App\Modules\Addons\Talento\Support\Actor;
use App\Modules\Addons\Talento\Controllers\TalentoEmployeeDocumentController;
use App\Modules\Addons\Talento\Controllers\TalentoAcademyController;
use App\Modules\Addons\Talento\Support\PayWeek;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API Sanctum para la app móvil TalentoEquipo (React Native) — David, 30-sep-2026:
 * "el contenido de Talento" completo, con el mismo criterio de toda la ficha web:
 * uno mismo ve lo suyo, el supervisor directo ve lo de su equipo + lo propio, staff
 * ve todo. Las 15 áreas que faltaban en la app (solo tenía Mi día/Órdenes/Mi semana
 * + el flujo de campo, que NO se tocan aquí).
 *
 * Patrón uniforme: cada endpoint recibe `?colaborador_id=` opcional (default: uno
 * mismo), se valida con puedeVer() (mismo criterio que TODOS los *Controller admin
 * de Talento: staffPermission() || self || subordinado directo — NUNCA se inventa
 * un criterio nuevo aquí, se copia el ya usado y probado en cada controller
 * hermano), y se devuelve solo lectura salvo donde se indica.
 */
class TalentoMobileEquipoController extends Controller
{
    private function actor(Request $r): Actor
    {
        return Actor::for($r->user());
    }

    /** Mismo criterio en TODOS los *Controller admin: staffPermission(s) || uno mismo || subordinado directo. */
    private function puedeVer(Request $r, $colaboradorId, string ...$staffPermissions): bool
    {
        foreach ($staffPermissions as $p) {
            if ($r->user()->can($p)) {
                return true;
            }
        }
        $propio = $this->actor($r)->talento();
        if (! $propio) {
            return false;
        }

        return (string) $propio->id === (string) $colaboradorId
            || $propio->subordinados()->where('id', $colaboradorId)->exists();
    }

    /** Resuelve el colaborador objetivo: ?colaborador_id= o, si no viene, uno mismo. Null = sin perfil/sin permiso. */
    private function resolverObjetivo(Request $r, string ...$staffPermissions): ?TalentoColaborador
    {
        $propio = $this->actor($r)->talento();
        $pedidoId = $r->query('colaborador_id');

        if (! $pedidoId) {
            return $propio;
        }
        if (! $this->puedeVer($r, $pedidoId, ...$staffPermissions)) {
            return null;
        }

        return TalentoColaborador::with('user')->find($pedidoId);
    }

    private function noPerfil()
    {
        return response()->json(['error' => 'Sin perfil de colaborador activo o sin permiso.'], 403);
    }

    // ── Mi equipo — switcher para supervisores/staff ────────────────────────────

    public function miEquipo(Request $request)
    {
        $propio = $this->actor($request)->talento();
        $esStaff = $request->user()->can('talento.employees.view');

        $equipo = collect();
        if ($propio) {
            $equipo = $propio->subordinados()->with('user')->get();
        } elseif ($esStaff) {
            // Staff sin ficha propia (admin puro): puede elegir cualquier colaborador.
            $equipo = TalentoColaborador::with('user')->where('status', 'active')->limit(200)->get();
        }

        return response()->json([
            'propio' => $propio ? ['id' => $propio->id, 'nombre' => $propio->user->name ?? '—'] : null,
            'es_supervisor_o_staff' => $propio?->subordinados()->exists() || $esStaff,
            'equipo' => $equipo->map(fn ($c) => [
                'id' => $c->id,
                'nombre' => $c->user->name ?? '—',
                'puesto' => $c->job_title,
                'departamento' => $c->department,
            ])->values(),
        ]);
    }

    // ── 1. Información ───────────────────────────────────────────────────────────

    public function informacion(Request $request)
    {
        $col = $this->resolverObjetivo($request, 'talento.employees.view');
        if (! $col) return $this->noPerfil();

        $col->loadMissing(['user', 'supervisor.user', 'puesto']);

        return response()->json([
            'nombre' => $col->user->name ?? '—',
            'email' => $col->user->email ?? null,
            'telefono' => $col->user->phone ?? null,
            'puesto' => optional($col->puesto)->name ?? $col->job_title,
            'departamento' => $col->department,
            'tipo' => $col->type,
            'status' => $col->status,
            'ingreso' => optional($col->hire_date)->toDateString(),
            'supervisor' => $col->supervisor?->user->name ?? null,
        ]);
    }

    // ── 2. Compensación (ventana actual, mismo criterio que dineroCuenta del Portal) ─

    public function compensacion(Request $request)
    {
        $col = $this->resolverObjetivo($request, 'talento.compensation.view', 'talento.compensation.manage');
        if (! $col) return $this->noPerfil();

        $w = PayWeek::current();

        $rows = TalentoLedgerEntry::where('colaborador_id', $col->id)
            ->where('period_start', $w['period_start'])
            ->where('period_end', $w['period_end'])
            ->selectRaw('concept, type, SUM(amount) AS total, COUNT(*) AS n')
            ->groupBy('concept', 'type')
            ->get();

        $credito = round((float) $rows->where('type', 'credit')->sum('total'), 2);
        $debito = round((float) $rows->where('type', 'debit')->sum('total'), 2);

        return response()->json([
            'period_start' => $w['period_start'],
            'period_end' => $w['period_end'],
            'conceptos' => $rows->map(fn ($r) => [
                'concepto' => $r->concept,
                'tipo' => $r->type,
                'subtotal' => round((float) $r->total, 2),
                'n' => (int) $r->n,
            ])->values(),
            'total_credito' => $credito,
            'total_debito' => $debito,
            'neto' => round($credito - $debito, 2),
            'desglose' => app(LiquidationService::class)->breakdown($col->id, $w),
        ]);
    }

    // ── 3. Liquidaciones (historial) ─────────────────────────────────────────────

    public function liquidaciones(Request $request)
    {
        $col = $this->resolverObjetivo($request, 'talento.liquidation.view');
        if (! $col) return $this->noPerfil();

        $liqs = TalentoLiquidation::where('colaborador_id', $col->id)
            ->orderByDesc('period_start')
            ->limit(52)
            ->get(['id', 'period_start', 'period_end', 'total_units', 'base_paid', 'overproduction_paid',
                'other_credits', 'other_debits', 'gross_pay', 'status', 'closed_at']);

        return response()->json(['liquidaciones' => $liqs]);
    }

    // ── 4. Asistencia (historial, no solo hoy) ───────────────────────────────────

    public function asistencia(Request $request)
    {
        // talento.attendance.view lo tiene TECNICO directo (para SU PROPIO historial) —
        // no sirve para distinguir staff, mismo motivo que talento.embajadores.view/
        // talento.custody.view/talento.devices.view más abajo. Solo talento.employees.view.
        $col = $this->resolverObjetivo($request, 'talento.employees.view');
        if (! $col) return $this->noPerfil();

        $historial = TalentoAttendance::where('colaborador_id', $col->id)
            ->orderByDesc('check_in_at')
            ->limit(60)
            ->get(['id', 'check_in_at', 'check_out_at', 'day_type', 'status',
                'check_in_flagged', 'check_in_flag_reason']);

        return response()->json(['historial' => $historial]);
    }

    // ── 5. Cajas ODB (lecturas baseline) ─────────────────────────────────────────

    public function cajas(Request $request)
    {
        $col = $this->resolverObjetivo($request, 'talento.caja.manage', 'talento.employees.view');
        if (! $col) return $this->noPerfil();

        $lecturas = TalentoCajaBaseline::where('registered_by', $col->id)
            ->orderByDesc('registered_at')
            ->limit(60)
            ->get(['id', 'caja_ref', 'baseline_power_dbm', 'registered_at', 'notes']);

        return response()->json(['lecturas' => $lecturas]);
    }

    // ── 6. Rutas (planta interna) ────────────────────────────────────────────────

    public function rutas(Request $request)
    {
        $col = $this->resolverObjetivo($request, 'talento.routes.view');
        if (! $col) return $this->noPerfil();

        $rutas = TalentoRoute::where('colaborador_id', $col->id)
            ->withCount('stops')
            ->orderByDesc('date')
            ->limit(30)
            ->get(['id', 'date', 'status']);

        return response()->json(['rutas' => $rutas]);
    }

    // ── 7. Proyectos (reportes de actividad externa) ─────────────────────────────

    public function proyectos(Request $request)
    {
        $col = $this->resolverObjetivo($request, 'talento.projects.view');
        if (! $col) return $this->noPerfil();

        // El colaborador participa en un reporte vía talento_activity_report_participants
        // (un reporte puede repartirse entre varios colaboradores) — NO via una columna
        // colaborador_id directa en talento_project_activity_reports (esa solo trae
        // reported_by = user_id de quien lo capturó).
        $reportes = TalentoActivityReportParticipant::where('colaborador_id', $col->id)
            ->with('report.projectActivity.project:id,name')
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->report_id,
                'proyecto' => optional(optional($p->report?->projectActivity)->project)->name,
                'cantidad_compartida' => (float) $p->quantity_share,
                'puntos' => (float) $p->points_earned,
                'status' => optional($p->report)->status,
                'fecha' => optional($p->report?->report_date)?->toDateString(),
            ]);

        return response()->json(['reportes' => $reportes]);
    }

    // ── 8. Calidad (inspecciones de caja) ────────────────────────────────────────

    public function calidad(Request $request)
    {
        $col = $this->resolverObjetivo($request, 'talento.quality.view', 'talento.quality.manage');
        if (! $col) return $this->noPerfil();

        $inspecciones = TalentoCajaInspection::where('inspected_by', $col->id)
            ->orderByDesc('created_at')
            ->limit(60)
            ->get(['id', 'caja_ref', 'overall_result', 'supervisor_validated', 'aesthetic_score',
                'power_measured', 'created_at']);

        return response()->json(['inspecciones' => $inspecciones]);
    }

    // ── 9. Penalizaciones (+ apelar) ─────────────────────────────────────────────

    public function penalizaciones(Request $request)
    {
        $col = $this->resolverObjetivo($request, 'talento.penalties.view');
        if (! $col) return $this->noPerfil();

        $penalizaciones = TalentoPenalty::where('colaborador_id', $col->id)
            ->with(['penaltyType:id,name,category', 'appeal'])
            ->orderByDesc('created_at')
            ->limit(60)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'tipo' => optional($p->penaltyType)->name,
                'monto' => round((float) $p->amount, 2),
                'status' => $p->status,
                'fecha' => optional($p->created_at)->toDateTimeString(),
                'notas' => $p->notes,
                'apelacion' => $p->appeal ? [
                    'id' => $p->appeal->id,
                    'razon' => $p->appeal->reason,
                    'decision' => $p->appeal->decision,
                    'decision_notas' => $p->appeal->decision_notes,
                ] : null,
                // Uno mismo (dueño de la penalización) puede apelar la suya — mismo
                // criterio ya usado en TalentoPenaltyController (self-apelar) — nunca
                // el supervisor apela EN NOMBRE de otro.
                'puede_apelar' => ! $p->appeal
                    && (string) ($this->actor($request)->talento()?->id) === (string) $p->colaborador_id,
            ]);

        return response()->json(['penalizaciones' => $penalizaciones]);
    }

    public function apelarPenalizacion(Request $request, int $penaltyId)
    {
        $data = $request->validate(['reason' => 'required|string|max:1000']);

        $penalty = TalentoPenalty::findOrFail($penaltyId);
        $propio = $this->actor($request)->talento();

        // Apelar la propia penalización es SIEMPRE de uno mismo — nunca del
        // supervisor en su nombre (mismo criterio que TalentoPenaltyController).
        abort_unless($propio && (string) $propio->id === (string) $penalty->colaborador_id, 403);
        abort_if(TalentoPenaltyAppeal::where('penalty_id', $penalty->id)->exists(), 422, 'Esta penalización ya tiene una apelación.');

        $appeal = TalentoPenaltyAppeal::create([
            'penalty_id' => $penalty->id,
            'appealed_by' => $request->user()->id,
            'reason' => $data['reason'],
        ]);

        return response()->json($appeal, 201);
    }

    // ── 10. Credenciales ──────────────────────────────────────────────────────────

    public function credenciales(Request $request)
    {
        $col = $this->resolverObjetivo($request, 'talento.credentials.view', 'talento.funds.view', 'talento.employees.view');
        if (! $col) return $this->noPerfil();

        $credenciales = TalentoCredential::where('colaborador_id', $col->id)
            ->orderByDesc('issued_at')
            ->get(['id', 'type', 'document_number', 'issued_at', 'expires_at', 'status']);

        return response()->json(['credenciales' => $credenciales]);
    }

    // ── 11. Documentos (solo lectura desde la app por ahora — firmar sigue en web) ─

    public function documentos(Request $request)
    {
        $col = $this->resolverObjetivo($request, 'talento.employees.view');
        if (! $col) return $this->noPerfil();

        $docs = DB::table('talento_employee_documents as d')
            ->join('talento_document_templates as t', 't.id', '=', 'd.template_id')
            ->where('d.colaborador_id', $col->id)
            ->select('d.id', 't.name as plantilla', 'd.status', 'd.signed_at', 'd.created_at')
            ->orderByDesc('d.created_at')
            ->limit(60)
            ->get();

        return response()->json(['documentos' => $docs]);
    }

    // ── 12. Academia (catálogo + certificaciones + progreso) ────────────────────

    public function academia(Request $request)
    {
        $col = $this->resolverObjetivo($request, 'talento.employees.view');
        if (! $col) return $this->noPerfil();

        $cursos = TalentoCourse::where('active', true)->orderBy('order')->get(['id', 'title', 'description', 'department']);

        $certificaciones = TalentoCertification::where('colaborador_id', $col->id)
            ->with('course:id,title')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'curso' => optional($c->course)->title,
                'badge' => $c->badge_label,
                'status' => $c->status,
                'certificado_en' => optional($c->certified_at)->toDateString(),
            ]);

        return response()->json(['cursos' => $cursos, 'certificaciones' => $certificaciones]);
    }

    // ── 13. Roles múltiples (vendedor/embajador) ─────────────────────────────────

    public function rolesMultiples(Request $request)
    {
        // talento.embajadores.view lo tiene TECNICO directo — NO sirve para distinguir
        // staff (mismo motivo documentado en TalentoEmbajadoresController::
        // puedeVerRolesMultiplesDe(), que usa SOLO talento.employees.view).
        $col = $this->resolverObjetivo($request, 'talento.employees.view');
        if (! $col) return $this->noPerfil();

        $seller = DB::table('sellers')->where('user_id', $col->user_id)->first();

        $ventas = $seller
            ? DB::table('client_main_information')->where('seller_id', $seller->id)->count()
            : 0;

        return response()->json([
            'es_vendedor' => (bool) $seller,
            'balance' => $seller ? round((float) $seller->balance, 2) : 0,
            'rango' => $seller->range ?? null,
            'clientes_asignados' => $ventas,
        ]);
    }

    // ── 14. Custodia (mismo criterio/forma que Portal::material, parametrizado) ──

    public function custodia(Request $request)
    {
        // talento.custody.view lo tiene TECNICO directo — NO sirve para distinguir staff
        // (mismo motivo que TalentoCustodiaController::puedeVerCustodiaDe(), SOLO talento.employees.view).
        $col = $this->resolverObjetivo($request, 'talento.employees.view');
        if (! $col) return $this->noPerfil();

        $inv = new \App\Services\InventoryService();
        $userId = $col->user_id;

        $itemView = fn ($item) => $item ? [
            'equipo' => $item->name,
            'tipo' => optional($item->inventory_item_type)->name,
            'categoria' => optional($item->inventory_item_type)->categoria,
        ] : ['equipo' => '—', 'tipo' => null, 'categoria' => null];

        $enCustodia = $inv->getItemsAcceptedByUser($userId)
            ->load('inventory_item.inventory_item_type')
            ->filter(fn ($s) => (float) $s->current_stock > 0)
            ->map(fn ($s) => array_merge($itemView($s->inventory_item), [
                'cantidad' => (int) $s->current_stock,
                'condicion' => $s->condition,
            ]))->values();

        return response()->json(['en_custodia' => $enCustodia]);
    }

    // ── 15. Dispositivos ──────────────────────────────────────────────────────────

    public function dispositivos(Request $request)
    {
        // talento.devices.view lo tiene TECNICO directo — NO sirve para distinguir staff
        // (mismo motivo que TalentoDeviceController::puedeVerDispositivosDe(), SOLO talento.employees.view).
        $col = $this->resolverObjetivo($request, 'talento.employees.view');
        if (! $col) return $this->noPerfil();

        $dispositivos = TalentoDevice::where('user_id', $col->user_id)
            ->orderByDesc('last_seen_at')
            ->get(['id', 'platform', 'label', 'approved', 'last_seen_at', 'revoked_at']);

        return response()->json(['dispositivos' => $dispositivos]);
    }
    // ── 16. Documentos — detalle con huecos/slots + firmar/completar (David, 1-oct) ──────
    // Delegación DIRECTA a TalentoEmployeeDocumentController (mismo servicio/lógica que la
    // ficha web — "no duplicar servicios compartidos", CLAUDE.md). Ese controller YA valida
    // uno-mismo/supervisor/staff por colaboradorId (esUnoMismoOSupervisorDe) — aquí solo se
    // resuelve CUÁL colaborador (self por default, ?colaborador_id= para supervisor/staff,
    // mismo patrón que el resto de este archivo) y se delega.

    public function documentoDetalle(Request $request)
    {
        $col = $this->resolverObjetivo($request, 'talento.employees.view');
        if (! $col) return $this->noPerfil();

        return app(TalentoEmployeeDocumentController::class)->forColaborador($col->id);
    }

    public function documentoFirmar(Request $request, int $docId)
    {
        $col = $this->resolverObjetivo($request, 'talento.employees.view');
        if (! $col) return $this->noPerfil();

        return app(TalentoEmployeeDocumentController::class)->sign($request, $col->id, $docId);
    }

    public function documentoCompletar(Request $request, int $docId)
    {
        $col = $this->resolverObjetivo($request, 'talento.employees.view');
        if (! $col) return $this->noPerfil();

        return app(TalentoEmployeeDocumentController::class)->completar($request, $col->id, $docId);
    }

    public function documentoFirmaImagen(Request $request, int $docId)
    {
        $col = $this->resolverObjetivo($request, 'talento.employees.view');
        if (! $col) return $this->noPerfil();

        return app(TalentoEmployeeDocumentController::class)->firma($request, $col->id, $docId);
    }

    // ── 17. Academia — curso completo + tomar/enviar examen (David, 1-oct) ───────────────
    // Misma delegación directa a TalentoAcademyController: examForStudent()/submitExam()/
    // myAttempts()/showCourse() ya son self-scoped (auth()->id()) y sin authorize() propio
    // (igual de abiertos en la web a cualquier colaborador autenticado) — se exponen tal cual.

    public function cursoDetalle(int $cursoId)
    {
        return app(TalentoAcademyController::class)->showCourse($cursoId);
    }

    public function examenTomar(int $examId)
    {
        return app(TalentoAcademyController::class)->examForStudent($examId);
    }

    public function examenEnviar(Request $request, int $examId)
    {
        return app(TalentoAcademyController::class)->submitExam($request, $examId);
    }

    public function examenIntentos(int $examId)
    {
        return app(TalentoAcademyController::class)->myAttempts($examId);
    }
}
