<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoAttendance;
use App\Modules\Addons\Talento\Models\TalentoCajaBaseline;
use App\Modules\Addons\Talento\Models\TalentoCajaInspection;
use App\Modules\Addons\Talento\Models\TalentoCertification;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoCompensationRuleHistory;
use App\Modules\Addons\Talento\Models\TalentoConstructionStandard;
use App\Modules\Addons\Talento\Models\TalentoCourse;
use App\Modules\Addons\Talento\Models\TalentoCredential;
use App\Modules\Addons\Talento\Models\TalentoDevice;
use App\Modules\Addons\Talento\Models\TalentoFund;
use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Addons\Talento\Models\TalentoLiquidation;
use App\Modules\Addons\Talento\Models\TalentoLoan;
use App\Modules\Addons\Talento\Models\TalentoPenalty;
use App\Modules\Addons\Talento\Models\TalentoPenaltyAppeal;
use App\Modules\Addons\Talento\Models\TalentoPenaltyType;
use App\Modules\Addons\Talento\Models\TalentoActivityReportParticipant;
use App\Modules\Addons\Talento\Models\TalentoRoute;
use App\Modules\Addons\Talento\Services\LiquidationService;
use App\Modules\Addons\Talento\Support\Actor;
use App\Modules\Addons\Talento\Controllers\TalentoEmployeeDocumentController;
use App\Modules\Addons\Talento\Controllers\TalentoAcademyController;
use App\Modules\Addons\Talento\Controllers\TalentoCajaController;
use App\Modules\Addons\Talento\Controllers\TalentoQualityController;
use App\Modules\Addons\Talento\Controllers\TalentoProjectController;
use App\Modules\Addons\Talento\Controllers\TalentoDeviceController;
use App\Modules\Addons\Talento\Controllers\TalentoRouteController;
use App\Modules\Addons\Talento\Controllers\TalentoLiquidacionController;
use App\Modules\Addons\Talento\Controllers\PortalTecnicoController;
use Illuminate\Support\Facades\URL;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRGdImagePNG;
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

    private const ESTADO_PENALIZACION = [
        'applied'    => 'Aplicada',
        'appealed'   => 'Apelada',
        'overturned' => 'Revocada',
        'upheld'     => 'Mantenida',
    ];

    private const DECISION_APELACION = [
        'upheld'  => 'Mantenida',
        'overturned' => 'Revocada',
    ];

    private static function nombreDe($user): ?string
    {
        return $user ? trim($user->name . ' ' . ($user->father_last_name ?? '')) : null;
    }

    // ── Mi progreso: regla semanal (2026-10-21) ─────────────────────────────────

    /**
     * Resultado de las reglas semanales de la persona (o de alguien de su equipo con
     * colaborador_id): puntos validados / pagos / prospectos y ventas, si cumple, penalización
     * o bono y el monto. Mismos datos que "Mi progreso" del portal web (DashboardService::
     * reglaDesempeno).
     */
    public function progreso(Request $request, \App\Modules\Addons\Talento\Services\DashboardService $service)
    {
        $col = $this->resolverObjetivo($request, 'talento.employees.view');
        if (! $col) return $this->noPerfil();

        $col->loadMissing('user');

        return response()->json([
            'colaborador_id' => $col->id,
            'nombre'         => self::nombreDe($col->user),
            'track'          => $col->user ? Actor::for($col->user)->trackPrincipal() : 'generico',
            'regla'          => $service->reglaDesempeno($col->id),
        ]);
    }

    // ── Supervisor: penalizaciones de su equipo (2026-10-21) ─────────────────────
    // Mismos endpoints que el portal web; resuelven a la persona con $request->user().

    public function equipoPenalizaciones(Request $request)
    {
        return app(PortalTecnicoController::class)->equipoPenalizaciones($request);
    }

    public function equipoConfirmarPenalizacion(Request $request, int $id)
    {
        return app(PortalTecnicoController::class)->equipoConfirmarPenalizacion($request, $id);
    }

    public function equipoProgreso(Request $request, \App\Modules\Addons\Talento\Services\DashboardService $service)
    {
        return app(PortalTecnicoController::class)->miEquipoProgreso($request, $service);
    }

    // ── Mi equipo — switcher para supervisores/staff ────────────────────────────

    public function miEquipo(Request $request)
    {
        $propio = $this->actor($request)->talento();
        $esStaff = $request->user()->can('talento.employees.view');

        $equipo = collect();
        if ($propio) {
            $equipo = $propio->subordinados()->activos()->with('user.roles')->get();
        } elseif ($esStaff) {
            // Staff sin ficha propia (admin puro): puede elegir cualquier colaborador.
            $equipo = TalentoColaborador::with('user.roles')->activos()->limit(200)->get();
        }

        return response()->json([
            'propio' => $propio ? ['id' => $propio->id, 'nombre' => $propio->user->name ?? '—'] : null,
            // Supervisor = tiene equipo; "staff" solo el admin sin ficha (2026-10-21: el Mostrador ahora
            // tiene employees.view pero en la app es un colaborador más, sin selector vacío).
            'es_supervisor_o_staff' => $propio ? $propio->subordinados()->exists() : $esStaff,
            'equipo' => $equipo->map(fn ($c) => [
                'id' => $c->id,
                'nombre' => $c->user->name ?? '—',
                'puesto' => $c->puestoNombre(),
                'departamento' => $c->department,
            ])->values(),
        ]);
    }

    // ── 1. Información ───────────────────────────────────────────────────────────

    public function informacion(Request $request)
    {
        $col = $this->resolverObjetivo($request, 'talento.employees.view');
        if (! $col) return $this->noPerfil();

        $col->loadMissing(['user', 'supervisor.user']);

        return response()->json([
            'nombre' => $col->user->name ?? '—',
            'email' => $col->user->email ?? null,
            'telefono' => $col->user->phone ?? null,
            'puesto' => $col->puestoNombre(),
            'departamento' => $col->department,
            'tipo' => $col->type,
            'status' => $col->status,
            'ingreso' => optional($col->hire_date)->toDateString(),
            'supervisor' => $col->supervisor?->user->name ?? null,
            // David (2-oct): para la credencial visual en "Mi credencial". 29-oct: con enlace
            // firmado, para que cargue también desde fuera de la oficina (ver fotoFirmada).
            'foto' => $col->user ? URL::temporarySignedRoute(
                'talento.colaborador.foto.firmada', now()->addHour(), ['id' => $col->id]
            ) : null,
        ]);
    }

    // ── 2. Compensación (ventana actual, mismo criterio que dineroCuenta del Portal) ─

    public function compensacion(Request $request)
    {
        $col = $this->resolverObjetivo($request, 'talento.compensation.view', 'talento.compensation.manage');
        if (! $col) return $this->noPerfil();

        $w = PayWeek::current();

        $cuenta = app(\App\Modules\Addons\Talento\Services\CompensacionService::class)
            ->cuentaCorriente($col->id, $w);

        return response()->json(array_merge([
            'period_start' => $w['period_start'],
            'period_end' => $w['period_end'],
        ], $cuenta, [
            'desglose' => app(LiquidationService::class)->breakdown($col->id, $w),
        ], ($etapas = app(\App\Modules\Addons\Talento\Services\CompensacionService::class)->etapas($col->id, $w)) ? ['etapas' => $etapas] : [], [
            // 30-oct-2026: lo demás que muestra la web (Mi dinero y la pestaña Compensación).
            'fondo' => PortalTecnicoController::fondoDe($col->id, $w),
            'prestamos' => PortalTecnicoController::prestamosDe($col->id, $w),
            'regla' => TalentoCompensationRuleHistory::where('colaborador_id', $col->id)
                ->orderByDesc('assigned_at')
                ->get()
                ->map(fn ($h) => [
                    'assigned_at' => optional($h->assigned_at)->toDateString(),
                    'nombre' => $h->data['name'] ?? null,
                    'sueldo_base' => round((float) ($h->data['base_salary'] ?? 0), 2),
                    'cuota' => (int) ($h->data['weekly_quota_units'] ?? 0),
                    'periodo' => $h->data['period'] ?? 'weekly',
                ])
                ->values(),
        ]));
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
                'other_credits', 'other_debits', 'gross_pay', 'status', 'closed_at'])
            ->map(fn ($l) => array_merge($l->toArray(), [
                'period_start' => $l->period_start?->toDateString(),
                'period_end'   => $l->period_end?->toDateString(),
                'estado'       => $l->status === 'closed' ? 'Cerrada' : 'Borrador',
            ]));

        return response()->json([
            'liquidaciones' => $liqs,
            'colaborador_id' => $col->id,
            'nombre' => self::nombreCompleto($col->user?->toArray()),
            // 30-oct-2026: como en la web, el comprobante para quien ve la lista de pagos y
            // «Calcular» para quien lleva la nómina.
            'puede_comprobante' => $this->puedeComprobante($request),
            'puede_calcular' => $this->puedeCalcular($request),
        ]);
    }

    /**
     * La pantalla «Liquidaciones» de la web, para la app (30-oct-2026): el Mostrador ve cuánto cobra
     * cada quien en una semana; administración ve todas las liquidaciones. Es la misma consulta que
     * la web (TalentoLiquidacionController::data); aquí solo se dejan los datos que muestra la app.
     */
    public function liquidacionesLista(Request $request)
    {
        if (! $this->puedeComprobante($request)) {
            return response()->json(['disponible' => false]);
        }

        // La lista es la de todos, como en la web: lo de una sola persona va por /portal/liquidaciones.
        $request->query->remove('colaborador_id');
        $payload = app(TalentoLiquidacionController::class)->data($request)->getData(true);

        $payload['data'] = collect($payload['data'] ?? [])->map(fn ($l) => [
            'id' => $l['id'] ?? null,
            'colaborador_id' => $l['colaborador_id'],
            'nombre' => self::nombreCompleto($l['colaborador']['user'] ?? null) ?? "Colaborador #{$l['colaborador_id']}",
            'period_start' => substr((string) $l['period_start'], 0, 10),
            'period_end' => substr((string) $l['period_end'], 0, 10),
            'total_units' => $l['total_units'] ?? null,
            'base_paid' => $l['base_paid'] ?? null,
            'overproduction_paid' => $l['overproduction_paid'] ?? null,
            'gross_pay' => $l['gross_pay'] ?? 0,
            'status' => $l['status'] ?? null,
            'puede_cerrar' => (bool) ($l['puede_cerrar'] ?? false),
            'sin_regla' => (bool) ($l['sin_regla'] ?? false),
        ])->values();

        $payload['disponible'] = true;
        $payload['puede_calcular'] = $this->puedeCalcular($request);
        if ($payload['puede_calcular']) {
            $payload['colaboradores'] = TalentoColaborador::with('user')->activos()->get()
                ->map(fn ($c) => ['id' => $c->id, 'nombre' => self::nombreCompleto($c->user?->toArray()) ?? "Colaborador #{$c->id}"])
                ->sortBy(fn ($c) => mb_strtolower($c['nombre']))
                ->values();
        }

        return response()->json($payload);
    }

    /** Detalle de una liquidación (unidades, órdenes y movimientos). Mismos permisos que en la web. */
    public function liquidacionDetalle($id)
    {
        return app(TalentoLiquidacionController::class)->show($id);
    }

    /** «Calcular» desde la app: la misma acción de la web, con sus mismos permisos y reglas. */
    public function liquidacionCalcular(Request $request)
    {
        return app(TalentoLiquidacionController::class)->calcular($request);
    }

    /** «Cerrar» desde la app: la misma acción de la web. */
    public function liquidacionCerrar(Request $request, $id)
    {
        return app(TalentoLiquidacionController::class)->cerrar($request, $id);
    }

    /**
     * Enlace al comprobante de pago para abrirlo en el teléfono (30-oct-2026). Como la credencial:
     * el navegador del teléfono no lleva la sesión de la app, así que el enlace va firmado, dura
     * 10 minutos y lleva quién lo entrega (sale impreso en el comprobante).
     */
    public function liquidacionComprobanteUrl(Request $request)
    {
        abort_unless($this->puedeComprobante($request), 403);
        $data = $request->validate([
            'colaborador_id' => 'required|integer',
            'semana' => 'required|date_format:Y-m-d',
        ]);

        return response()->json([
            'url' => URL::temporarySignedRoute('talento.liquidacion.comprobante.firmado', now()->addMinutes(10), [
                'colaborador_id' => (int) $data['colaborador_id'],
                'semana' => $data['semana'],
                'entrega' => $request->user()->id,
            ]),
        ]);
    }

    /** Nombre con los dos apellidos, como en la lista de la web (hay nombres de pila repetidos). */
    private static function nombreCompleto(?array $u): ?string
    {
        $nombre = trim(implode(' ', array_filter([$u['name'] ?? null, $u['father_last_name'] ?? null, $u['mother_last_name'] ?? null])));

        return $nombre !== '' ? $nombre : null;
    }

    private function puedeComprobante(Request $request): bool
    {
        return $request->user()->canAny(['talento.liquidation.view', 'talento.liquidation.totales']);
    }

    /** Los mismos permisos que pide TalentoLiquidacionController::calcular(). */
    private function puedeCalcular(Request $request): bool
    {
        $u = $request->user();

        return $u->can('talento.liquidation.manage')
            && (! config('permission_action_checks.enforce', false) || $u->can('talento.liquidacion.calcular'));
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
            ->with(['penaltyType:id,name,category', 'appliedByColaborador.user:id,name,father_last_name',
                'ledgerEntries:id,reference_id,reference_type,period_start,period_end',
                'appeal.reviewedByUser:id,name,father_last_name', 'appeal.reviewedByColaborador.user:id,name,father_last_name'])
            ->orderByDesc('created_at')
            ->limit(60)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'tipo' => optional($p->penaltyType)->name,
                'monto' => round((float) $p->amount, 2),
                'status' => $p->status,
                'estado' => self::ESTADO_PENALIZACION[$p->status] ?? $p->status,
                'fecha' => optional($p->created_at)->toDateString(),
                'semana_inicio' => optional($p->ledgerEntries->first()?->period_start)->toDateString(),
                'semana_fin' => optional($p->ledgerEntries->first()?->period_end)->toDateString(),
                'aplicada_por' => self::nombreDe($p->appliedByColaborador?->user),
                'notas' => $p->notes,
                'apelacion' => $p->appeal ? [
                    'id' => $p->appeal->id,
                    'razon' => $p->appeal->reason,
                    'decision' => $p->appeal->decision,
                    'decision_texto' => self::DECISION_APELACION[$p->appeal->decision] ?? 'Pendiente',
                    'decision_notas' => $p->appeal->decision_notes,
                    'resolvio' => self::nombreDe($p->appeal->reviewedByUser ?? $p->appeal->reviewedByColaborador?->user),
                    'resuelta_el' => optional($p->appeal->resolved_at)->toDateString(),
                ] : null,
                // Uno mismo (dueño de la penalización) puede apelar la suya — mismo
                // criterio ya usado en TalentoPenaltyController (self-apelar) — nunca
                // el supervisor apela EN NOMBRE de otro.
                'puede_apelar' => ! $p->appeal && $p->status === 'applied'
                    && (string) ($this->actor($request)->talento()?->id) === (string) $p->colaborador_id,
            ]);

        return response()->json(['penalizaciones' => $penalizaciones]);
    }

    /**
     * Mismo flujo que la web (TalentoPenaltyController::submitAppeal): guarda la apelación a nombre
     * de la ficha, con fecha, y pasa la penalización a "apelada". La versión propia de la app
     * guardaba el id de usuario, sin fecha y sin cambiar el estado (2026-10-21).
     */
    public function apelarPenalizacion(Request $request, int $penaltyId)
    {
        return app(TalentoPenaltyController::class)->submitAppeal($request, $penaltyId);
    }

    // ── 10. Credenciales ──────────────────────────────────────────────────────────

    public function credenciales(Request $request)
    {
        $col = $this->resolverObjetivo($request, 'talento.credentials.view', 'talento.funds.view', 'talento.employees.view');
        if (! $col) return $this->noPerfil();

        // David (2-oct): "que lo que pone es driver_licence, valid..." -- la
        // app traducira esos valores crudos, pero ademas faltaban
        // days_until_expiry (ya calculado por el modelo) y el fondo de
        // renovacion asociado (TalentoFund, si existe) -- la UNICA pieza
        // extra que de verdad aplica a autoservicio de lectura, sin tocar
        // el "no autoservicio" de gestion (editar/subir documento sigue
        // siendo exclusivo de un supervisor/staff, sin cambio aqui).
        $credenciales = TalentoCredential::where('colaborador_id', $col->id)
            ->with('fund')
            ->orderByDesc('issued_at')
            ->get(['id', 'type', 'document_number', 'issued_at', 'expires_at', 'status', 'file_path'])
            ->map(fn (TalentoCredential $c) => [
                'id' => $c->id,
                'type' => $c->type,
                'document_number' => $c->document_number,
                'issued_at' => $c->issued_at,
                'expires_at' => $c->expires_at,
                'status' => $c->status,
                'days_until_expiry' => $c->daysUntilExpiry(),
                'has_document' => $c->getDecryptedPath() !== null,
                'fund' => $c->fund ? [
                    'purpose' => $c->fund->purpose,
                    'target_amount' => (float) $c->fund->target_amount,
                    'accumulated' => (float) $c->fund->accumulated,
                    'weekly_deduction' => (float) $c->fund->weekly_deduction,
                    'authorized' => (bool) $c->fund->authorized,
                    'status' => $c->fund->status,
                ] : null,
            ]);

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

        // Solo los generales y los de sus roles (2026-10-24).
        $cursos = TalentoCourse::where('active', true)
            ->paraRoles(\App\Models\User::find($col->user_id)?->getRoleNames() ?? [])
            ->orderBy('order')->get(['id', 'title', 'description', 'department', 'roles']);

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
            ->get(['id', 'device_key', 'platform', 'label', 'approved', 'last_seen_at', 'revoked_at']);

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
        $curso = app(TalentoAcademyController::class)->showCourse($cursoId)->getData(true);

        // 30-oct-2026: las imágenes de referencia de los materiales van con enlace firmado bajo
        // /talento/api, para que carguen también desde fuera de la oficina (en /storage nginx pide
        // su contraseña). Ver cursoImagenFirmada().
        foreach ($curso['materials'] ?? [] as $i => $material) {
            foreach (['reference_standard' => 'estandar', 'reference_penalty_type' => 'penalizacion'] as $clave => $tipo) {
                if (! empty($material[$clave]['reference_image_path'])) {
                    $curso['materials'][$i][$clave]['reference_image_url'] = URL::temporarySignedRoute(
                        'talento.curso.imagen.firmada', now()->addHour(), ['tipo' => $tipo, 'id' => $material[$clave]['id']]
                    );
                }
            }
        }

        return response()->json($curso);
    }

    public function cursoImagenFirmada(string $tipo, int $id)
    {
        $modelo = $tipo === 'estandar' ? TalentoConstructionStandard::class : TalentoPenaltyType::class;
        $ruta = $modelo::findOrFail($id)->reference_image_path;
        $disco = \Illuminate\Support\Facades\Storage::disk('public');
        abort_unless($ruta && $disco->exists($ruta), 404);

        return response()->file($disco->path($ruta), ['Cache-Control' => 'private, max-age=3600']);
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
    // ── 18. Cajas ODB — registrar baseline (David, 2-oct) ─────────────────────────
    // Delegacion directa a TalentoCajaController::store (misma regla: cualquier
    // tecnico activo puede registrar su propia lectura, sin permiso especial).

    public function cajaRegistrar(Request $request)
    {
        return app(TalentoCajaController::class)->store($request);
    }

    // ── 19. Calidad — registrar inspeccion de caja (con foto) (David, 2-oct) ─────
    // Delegacion directa a TalentoQualityController::storeInspection/runIaAnalysis.

    public function inspeccionRegistrar(Request $request)
    {
        return app(TalentoQualityController::class)->storeInspection($request);
    }

    public function inspeccionAnalizarIa(int $id)
    {
        return app(TalentoQualityController::class)->runIaAnalysis($id);
    }

    // ── 20. Proyectos — catalogo + detalle + reportar avance (David, 2-oct) ──────
    // Delegacion directa a TalentoProjectController (data/show/submitReport ya son
    // self-scoped: cualquier colaborador real puede verlos; submitReport ya limita
    // a quien reporta a reportar solo avance propio o de su equipo directo).

    public function proyectosCatalogo(Request $request)
    {
        return app(TalentoProjectController::class)->data($request);
    }

    public function proyectoDetalle(int $id)
    {
        return app(TalentoProjectController::class)->show($id);
    }

    public function proyectoReportar(Request $request, int $projectId, int $actId)
    {
        return app(TalentoProjectController::class)->submitReport($request, $projectId, $actId);
    }
    // ── 21. Rutas — detalle con paradas (David, 2-oct) ────────────────────────────
    // Delegacion directa a TalentoRouteController::show (self/supervisor/staff ya
    // resuelto ahi, con los pings del dia y las desviaciones detectadas).

    public function rutaDetalle(int $id)
    {
        return app(TalentoRouteController::class)->show($id);
    }

    // ── 22. Dispositivos — vincular el propio + link de descarga (David, 2-oct) ──
    // Delegacion a TalentoDeviceController::bind (self, ver fix de permiso ahi).

    public function dispositivoVincular(Request $request)
    {
        $col = $this->resolverObjetivo($request, 'talento.employees.view');
        if (! $col) return $this->noPerfil();

        return app(TalentoDeviceController::class)->bind($request, $col->id);
    }

    /**
     * Link FIRMADO (Laravel signed route, 10 min) a la credencial PDF -- el
     * telefono lo abre con Linking.openURL (su navegador normal, sin Bearer
     * token), por eso NO se sirve el PDF directo desde aqui.
     */
    public function credencialPdfUrl(Request $request)
    {
        $col = $this->resolverObjetivo($request, 'talento.employees.view');
        if (! $col) return $this->noPerfil();

        $url = URL::temporarySignedRoute(
            'talento.colaborador.pdf.firmado',
            now()->addMinutes(10),
            ['id' => $col->id]
        );

        return response()->json(['url' => $url]);
    }

    /**
     * QR del link de descarga del APK (release activa en talento_app_releases)
     * -- para escanear desde otro telefono y vincular la app ahi.
     */
    /**
     * Imagenes de la plantilla de credencial (frente/reverso/logo) -- las
     * MISMAS 3 filas compartidas que ya usa Vendedores (App\Models\
     * Credential, subidas en /configuracion/credencial). Sin fila = usar
     * los defaults estaticos (/images/gafete-front.png, etc.), igual que
     * hace InformationSeller.vue -- la app resuelve el fallback, aqui solo
     * se informa si hay o no fila personalizada.
     */
    public function credencialPlantilla()
    {
        $front = \App\Models\Credential::where('type', 'frontal')->value('name');
        $back = \App\Models\Credential::where('type', 'reverso')->value('name');
        $logo = \App\Models\Credential::where('type', 'logo')->value('name');

        // 29-oct-2026: la app 1.21 usa las *_url (bajo /talento/api, cargan desde fuera de la
        // oficina); las versiones anteriores siguen armando la dirección con front/back/logo.
        // ?v= cambia al subir otra plantilla, para que el teléfono no se quede con la anterior.
        $url = fn (string $tipo, ?string $nombre) => route('talento.api.credencial.plantilla', [
            'tipo' => $tipo,
            'v' => $nombre ? substr(md5($nombre), 0, 8) : 'base',
        ]);

        return response()->json([
            'front' => $front,
            'back' => $back,
            'logo' => $logo,
            'front_url' => $url('frontal', $front),
            'back_url' => $url('reverso', $back),
            'logo_url' => $url('logo', $logo),
        ]);
    }

    /**
     * Imagen de la plantilla del gafete para la app (2026-10-29): la subida en Credenciales o, si
     * no hay, el default estático — el mismo respaldo que usa InformationSeller.vue. Sin sesión:
     * son imágenes fijas de la empresa, sin datos de ningún colaborador.
     */
    public function credencialPlantillaImagen(string $tipo)
    {
        $defaults = [
            'frontal' => 'images/gafete-front.png',
            'reverso' => 'images/gafete-back.png',
            'logo' => 'images/logo_meganet.jpg',
        ];

        $nombre = \App\Models\Credential::where('type', $tipo)->value('name');
        $ruta = $nombre ? public_path('credencial/' . basename($nombre)) : null;
        if (! $ruta || ! is_file($ruta)) {
            $ruta = public_path($defaults[$tipo]);
        }

        return response()->file($ruta, ['Cache-Control' => 'public, max-age=86400']);
    }

    public function downloadQr()
    {
        $release = DB::table('talento_app_releases')->where('active', true)->orderByDesc('version_code')->first();
        abort_if(! $release, 404, 'No hay una version publicada.');

        // El QR ya NO apunta directo al .apk -- apunta a la pagina publica
        // descargarApp(), que detecta Android/iPhone/escritorio y manda a cada
        // quien por el camino correcto (ver nota en ese metodo). El host se
        // deriva del propio apk_url (NO de url()/APP_URL, que aqui vale la IP
        // LAN) para que quede en el mismo dominio publico alcanzable desde
        // fuera de la red -- ver memoria "APK de Talento vía DNS, no IP".
        $host = parse_url($release->apk_url, PHP_URL_HOST);
        $scheme = parse_url($release->apk_url, PHP_URL_SCHEME) ?: 'https';
        $landingUrl = "{$scheme}://{$host}/app/descargar";

        $opts = new QROptions(['outputInterface' => QRGdImagePNG::class, 'scale' => 8]);
        $dataUri = (new QRCode($opts))->render($landingUrl);

        return response()->json([
            'apk_url' => $release->apk_url,
            'landing_url' => $landingUrl,
            'version_name' => $release->version_name,
            'qr_data_uri' => $dataUri,
        ]);
    }

    /**
     * Pagina publica (sin auth) a la que apunta el QR y que se puede compartir
     * como link suelto. Detecta el User-Agent para mandar a cada quien por el
     * camino correcto: Android descarga directo, iPhone ve un aviso honesto de
     * "aun no disponible" (no existe build de iOS todavia -- requeriria Mac +
     * cuenta de Apple Developer, fuera de alcance de este cambio), y
     * escritorio/desconocido ve ambas opciones.
     */
    public function descargarApp(Request $request)
    {
        $release = DB::table('talento_app_releases')->where('active', true)->orderByDesc('version_code')->first();
        abort_if(! $release, 404, 'No hay una version publicada.');

        $ua = (string) $request->userAgent();
        $esIos = (bool) preg_match('/iPhone|iPad|iPod/i', $ua);
        $esAndroid = ! $esIos && (bool) preg_match('/Android/i', $ua);

        // Android: salta directo a la descarga real (un solo toque, igual que antes).
        if ($esAndroid) {
            return redirect()->away($release->apk_url);
        }

        $plataforma = $esIos ? 'ios' : 'desconocido';

        return view('addon-talento::talento.descargar-app', [
            'plataforma' => $plataforma,
            'apkUrl' => $release->apk_url,
            'versionName' => $release->version_name,
        ]);
    }
}
