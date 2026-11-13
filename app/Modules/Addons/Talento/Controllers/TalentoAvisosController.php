<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Modules\Addons\Talento\Models\TalentoAttendance;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoCourse;
use App\Modules\Addons\Talento\Models\TalentoLiquidation;
use App\Modules\Addons\Talento\Models\TalentoLoan;
use App\Modules\Addons\Talento\Models\TalentoPenalty;
use App\Modules\Addons\Talento\Models\TalentoProject;
use App\Modules\Addons\Talento\Models\TalentoWeeklyRuleEvaluation;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Services\CorreccionEvidenciasService;
use App\Modules\Addons\Talento\Services\RevisionOrdenesAppService;
use App\Modules\Addons\Talento\Services\TalentoWeeklyRuleEvaluationService;
use App\Modules\Addons\Talento\Support\PayWeek;
use App\Services\TaskScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Avisos de la app TalentoEquipo (3-nov-2026). Los avisos los arma el teléfono: la app pide este
 * resumen al abrirse y cada ~15 min en segundo plano, lo compara con lo que ya había visto y
 * avisa de lo nuevo. Aquí solo se responde, nunca se manda nada al teléfono.
 *
 * Solo lo propio (la persona en sesión) y solo lo que hace falta comparar: ids y una etiqueta
 * corta, sin montos (el aviso sale en la pantalla bloqueada). Las listas se acotan a lo reciente
 * para que la consulta siga siendo ligera.
 */
class TalentoAvisosController extends Controller
{
    public function estado(Request $request, TalentoWeeklyRuleEvaluationService $reglas)
    {
        $col = TalentoColaborador::with('user')
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();
        if (! $col) {
            return response()->json(['error' => 'Sin perfil de colaborador activo.'], 403);
        }

        return response()->json([
            'colaborador_id' => $col->id,
            'server_time' => now()->toIso8601String(),
            'tareas' => $this->tareas($col),
            'correcciones' => $this->correcciones($col),
            // 13-nov-2026: a quien revisa, las órdenes de su equipo que terminaron y esperan su revisión.
            'por_validar' => app(RevisionOrdenesAppService::class)->avisos($request->user()),
            'proyectos' => TalentoProject::where('lead_colaborador_id', $col->id)
                ->whereIn('status', ['planning', 'active', 'paused'])
                ->orderByDesc('id')
                ->limit(50)
                ->get(['id', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'nombre' => $p->name])
                ->values(),
            'asistencia' => $this->asistencia($col),
            'prestamos' => TalentoLoan::where('colaborador_id', $col->id)
                ->where('created_at', '>=', now()->subDays(180))
                ->orderByDesc('id')
                ->limit(30)
                ->get(['id', 'authorized'])
                ->map(fn ($l) => ['id' => $l->id, 'autorizado' => (bool) $l->authorized])
                ->values(),
            'penalizaciones' => TalentoPenalty::with('penaltyType:id,name')
                ->where('colaborador_id', $col->id)
                ->where('created_at', '>=', now()->subDays(60))
                ->orderByDesc('id')
                ->limit(50)
                ->get(['id', 'penalty_type_id'])
                ->map(fn ($p) => ['id' => $p->id, 'tipo' => $p->penaltyType?->name])
                ->values(),
            // El pago se avisa al CERRAR la semana: calculada todavía puede cambiar.
            'pagos' => TalentoLiquidation::where('colaborador_id', $col->id)
                ->where('status', 'closed')
                ->orderByDesc('period_start')
                ->limit(12)
                ->get(['id', 'period_start', 'period_end'])
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'semana_inicio' => $l->period_start?->toDateString(),
                    'semana_fin' => $l->period_end?->toDateString(),
                ])
                ->values(),
            'documentos' => DB::table('talento_employee_documents as d')
                ->join('talento_document_templates as t', 't.id', '=', 'd.template_id')
                ->where('d.colaborador_id', $col->id)
                ->whereNull('d.signed_at')
                ->orderByDesc('d.id')
                ->limit(50)
                ->get(['d.id', 't.name as nombre']),
            // Mismo filtro que la Academia de la app: los generales y los de sus roles.
            'cursos' => TalentoCourse::where('active', true)
                ->paraRoles($col->user?->getRoleNames() ?? [])
                ->orderByDesc('id')
                ->limit(100)
                ->get(['id', 'title'])
                ->map(fn ($c) => ['id' => $c->id, 'titulo' => $c->title])
                ->values(),
            // Resultado del domingo: solo lo que hay que saber (no llegó al mínimo o ganó el bono).
            'evaluaciones' => TalentoWeeklyRuleEvaluation::where('colaborador_id', $col->id)
                ->whereIn('outcome', ['penalty_pending', 'bonus'])
                ->where('week_start', '>=', now()->subWeeks(8)->toDateString())
                ->orderByDesc('id')
                ->get(['id', 'outcome', 'week_start', 'week_end'])
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'resultado' => $e->outcome,
                    'semana_inicio' => $e->week_start?->toDateString(),
                    'semana_fin' => $e->week_end?->toDateString(),
                ])
                ->values(),
            // Solo cuando la app lo pide (jueves por la tarde): medir la semana cuesta más.
            'objetivo' => $request->boolean('objetivo') ? [
                'semana_fin' => PayWeek::current()['end_instant']->toIso8601String(),
                'reglas' => $reglas->avanceEnCurso($col),
            ] : null,
        ]);
    }

    /** #1286: correcciones que el supervisor le pidió y todavía no envía (cada una se avisa una vez). */
    private function correcciones(TalentoColaborador $col): array
    {
        if (! CorreccionEvidenciasService::disponible()) {
            return [];
        }

        return DB::table('talento_orden_correcciones as c')
            ->join('tasks as t', 't.id', '=', 'c.tarea_id')
            ->join('task_user as tu', 'tu.task_id', '=', 't.id')
            ->leftJoin('talento_work_order_types as ty', 'ty.id', '=', 't.talento_type_id')
            ->where('tu.user_id', $col->user_id)
            ->whereNull('t.deleted_at')
            ->whereNull('c.cerrada_at')
            ->whereNull('c.enviada_at')
            ->orderByDesc('c.id')
            ->limit(30)
            ->get(['c.id', 'c.tarea_id', 'ty.name as tipo', 't.status', 't.validated_at'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'ot_id' => (int) $c->tarea_id,
                'titulo' => 'Orden #' . $c->tarea_id . ($c->tipo ? " · {$c->tipo}" : ''),
                // Devuelta por el supervisor (todavía sin validar) frente a corrección sobre la ya validada.
                'devuelta' => ! ($c->status === 'Done' && $c->validated_at),
            ])
            ->values()
            ->all();
    }

    /** Órdenes y tareas abiertas asignadas a la persona (mismo criterio que «Mi día»). */
    private function tareas(TalentoColaborador $col): array
    {
        $desde = now()->subDays(30);
        $generales = (bool) config('unificacion.interruptores.mi_dia_tareas', false);

        $tasks = Task::with('talentoType:id,name')
            ->when(! $generales, fn ($q) => $q->whereNotNull('talento_type_id'))
            ->whereHas('users', fn ($q) => $q->where('users.id', $col->user_id))
            ->whereIn('status', ['ToDo', 'InProgress'])
            ->where('created_at', '>=', $desde)
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'title', 'talento_type_id', 'start_date', 'start_time'])
            ->map(fn ($t) => [
                'id' => 't' . $t->id,
                'ot_id' => $t->talento_type_id ? $t->id : null,
                'titulo' => $t->talentoType?->name ?? ($t->title ?: 'Tarea'),
                'cuando' => self::cuando($t->start_date ? TaskScheduleService::start($t->start_date, $t->start_time) : null),
            ]);

        $ordenes = TalentoWorkOrder::with('type:id,name')
            ->where('colaborador_id', $col->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->where('created_at', '>=', $desde)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'type_id', 'scheduled_at'])
            ->map(fn ($o) => [
                'id' => 'w' . $o->id,
                'ot_id' => $o->id,
                'titulo' => $o->type?->name ?? 'Orden de trabajo',
                'cuando' => self::cuando($o->scheduled_at),
            ]);

        return $tasks->toBase()->merge($ordenes)->values()->all();
    }

    /**
     * Lo que la app necesita para avisar de la asistencia: el horario (para los recordatorios de
     * llegada y salida, que programa el teléfono), el registro de hoy, el que siga abierto y los
     * días recientes que quedaron «Para revisión» o como falta.
     */
    private function asistencia(TalentoColaborador $col): array
    {
        $horario = null;
        if ($col->shift_start && $col->shift_end) {
            $horario = [
                'entrada' => substr((string) $col->shift_start, 0, 5),
                'salida' => substr((string) $col->shift_end, 0, 5),
                // 1 = lunes … 7 = domingo, como se capturan en la ficha.
                'dias' => collect(explode(',', (string) $col->work_days))
                    ->map(fn ($d) => (int) trim($d))
                    ->filter(fn ($d) => $d >= 1 && $d <= 7)
                    ->values()
                    ->all(),
            ];
        }

        $hoy = TalentoAttendance::where('colaborador_id', $col->id)
            ->whereDate('check_in_at', now()->toDateString())
            ->orderByDesc('id')
            ->first(['id', 'check_in_at', 'check_out_at']);

        $abierta = TalentoAttendance::where('colaborador_id', $col->id)
            ->whereNull('check_out_at')
            ->whereIn('status', ['open', 'flagged'])
            ->where('check_in_at', '>=', now()->subHours(36))
            ->orderByDesc('id')
            ->first(['id', 'check_in_at', 'expected_end_at']);

        $recientes = TalentoAttendance::where('colaborador_id', $col->id)
            ->where('check_in_at', '>=', now()->subDays(14))
            ->where(fn ($q) => $q->where('check_in_flagged', true)->orWhere('day_type', 'absent'))
            ->orderByDesc('id')
            ->limit(30)
            ->get(['id', 'check_in_at', 'check_in_flagged', 'check_in_flag_reason', 'day_type'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'fecha' => $a->check_in_at?->toDateString(),
                'para_revision' => (bool) $a->check_in_flagged,
                'motivo' => $a->check_in_flag_reason,
                'falta' => $a->day_type === 'absent',
            ])
            ->values();

        return [
            'horario' => $horario,
            'hoy' => $hoy ? [
                'id' => $hoy->id,
                'llegada' => $hoy->check_in_at?->toIso8601String(),
                'salida' => $hoy->check_out_at?->toIso8601String(),
            ] : null,
            'abierta' => $abierta ? [
                'id' => $abierta->id,
                'llegada' => $abierta->check_in_at?->toIso8601String(),
                'salida_esperada' => $abierta->expected_end_at?->toIso8601String(),
            ] : null,
            'recientes' => $recientes,
        ];
    }

    /** «04/11 10:00», o solo el día si la orden no tiene hora (quedó a las 00:00). */
    private static function cuando($fecha): ?string
    {
        if (! $fecha) {
            return null;
        }
        $f = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);

        return $f->format($f->format('H:i') === '00:00' ? 'd/m' : 'd/m H:i');
    }
}
