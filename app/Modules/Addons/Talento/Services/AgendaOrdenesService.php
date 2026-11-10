<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\Task;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderType;
use App\Services\TaskScheduleService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agenda de órdenes de Talento (item #1158, pedido de Irving 2026-11-03).
 *
 * - carga(): las órdenes de un rango de días, por persona y con el color de su tipo, para el
 *   calendario chico de «Asignar trabajo» y la agenda de Talento › Órdenes.
 * - Atrasada = orden pendiente o en curso cuyo día ya pasó. No se queda atrás: se muestra en HOY,
 *   en rojo, con su fecha original, hasta que se termine o se reprograme con un motivo.
 * - reprogramar(): mueve la orden a un día de hoy en adelante y deja el motivo en el historial de
 *   la orden (talento_order_events, evento order.rescheduled), con quién la movió y de qué día a cuál.
 *   No la deja encimarse con otra orden abierta de la misma persona (ChoqueAgendaOrdenes, #1227).
 *
 * Solo órdenes de Talento en `tasks` (talento_type_id): la tabla vieja talento_work_orders no tiene
 * filas y las tareas generales de Scheduling no entran a la carga de los técnicos.
 */
class AgendaOrdenesService
{
    /** Estados de `tasks` que aún esperan trabajo: si su día ya pasó, la orden está atrasada. */
    public const ESTADOS_ABIERTOS = ['ToDo', 'InProgress'];

    private const ESTADO_OT = [
        'ToDo'       => 'pending',
        'InProgress' => 'in_progress',
        'Done'       => 'completed',
    ];

    /** Órdenes de Talento abiertas cuyo día es anterior a $hoy. */
    public function scopeAtrasadas(Builder $q, string $hoy): Builder
    {
        return $q->whereNotNull('talento_type_id')
            ->whereIn('status', self::ESTADOS_ABIERTOS)
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<', $hoy);
    }

    /**
     * Órdenes entre $desde y $hasta (días, inclusivos). Si hoy cae en el rango, las atrasadas se
     * cuentan en hoy. $colaboradorIds = null → todas las personas; [] → ninguna.
     */
    public function carga(string $desde, string $hasta, ?array $colaboradorIds = null): array
    {
        $hoy = now()->toDateString();
        $tipos = TalentoWorkOrderType::orderBy('id')->get();
        $tiposPorId = $tipos->keyBy('id');

        $userIds = null;
        if ($colaboradorIds !== null) {
            $userIds = TalentoColaborador::whereIn('id', $colaboradorIds)->pluck('user_id')->filter()->values()->all();
        }

        $incluyeAtrasadas = $hoy >= $desde && $hoy <= $hasta;

        $tasks = Task::with(['users:users.id,users.name,users.father_last_name'])
            ->whereNotNull('talento_type_id')
            ->where('status', '!=', 'Archivado')
            ->where(function ($w) use ($desde, $hasta, $hoy, $incluyeAtrasadas) {
                $w->where(fn ($r) => $r->whereDate('start_date', '>=', $desde)->whereDate('start_date', '<=', $hasta));
                if ($incluyeAtrasadas) {
                    $w->orWhere(fn ($a) => $this->scopeAtrasadas($a, $hoy));
                }
            })
            ->when($userIds !== null, fn ($q) => $userIds
                ? $q->whereHas('users', fn ($u) => $u->whereIn('users.id', $userIds))
                : $q->whereRaw('1=0'))
            ->get();

        $colaboradores = TalentoColaborador::whereIn('user_id', $tasks->flatMap(fn ($t) => $t->users->pluck('id'))->unique()->all())
            ->get(['id', 'user_id'])
            ->keyBy('user_id');
        $clientes = $this->nombresClientes($tasks->pluck('client_main_information_id')->filter()->unique()->all());
        $prospectos = $this->nombresProspectos($tasks->pluck('crm_lead_id')->filter()->unique()->all());

        $ordenes = $tasks->map(function (Task $t) use ($hoy, $tiposPorId, $colaboradores, $clientes, $prospectos) {
            $inicio = TaskScheduleService::start($t->start_date, $t->start_time);
            $fecha  = $inicio?->toDateString();
            $hora   = $inicio && $inicio->format('H:i') !== '00:00' ? $inicio->format('H:i') : null;
            $atrasada = $fecha !== null && $fecha < $hoy && in_array($t->status, self::ESTADOS_ABIERTOS, true);
            $tipo = $tiposPorId->get($t->talento_type_id);
            $usuario = $t->users->first();
            $esProspecto = ! $t->client_main_information_id && $t->crm_lead_id;

            return [
                'id'             => $t->id,
                'origen'         => 'task',
                'dia'            => $atrasada ? $hoy : $fecha,
                'fecha'          => $fecha,
                'hora'           => $hora,
                'tipo_id'        => $t->talento_type_id,
                'tipo'           => $tipo?->name ?? '—',
                'color'          => $atrasada ? TalentoWorkOrderType::COLOR_ATRASADA : ($tipo?->colorEfectivo() ?? '#616161'),
                'color_tipo'     => $tipo?->colorEfectivo() ?? '#616161',
                'estado'         => $t->validated_at ? 'validated' : (self::ESTADO_OT[$t->status] ?? $t->status),
                'atrasada'       => $atrasada,
                'dias_atraso'    => $atrasada ? Carbon::parse($fecha)->diffInDays(Carbon::parse($hoy)) : 0,
                'colaborador_id' => $usuario ? $colaboradores->get($usuario->id)?->id : null,
                'persona'        => $usuario ? trim($usuario->name . ' ' . $usuario->father_last_name) : 'Sin asignar',
                'cliente'        => $esProspecto
                    ? ($prospectos[$t->crm_lead_id] ?? null)
                    : ($clientes[$t->client_main_information_id] ?? null),
                'es_prospecto'   => (bool) $esProspecto,
            ];
        })
            ->toBase()
            ->concat($this->ordenesAntiguas($desde, $hasta, $colaboradorIds, $tiposPorId))
            ->filter(fn ($o) => $o['dia'] !== null)
            ->sortBy(fn ($o) => $o['dia'] . ' ' . ($o['atrasada'] ? '0' : '1') . ($o['hora'] ?? '99:99'))
            ->values();

        return [
            'hoy'            => $hoy,
            'desde'          => $desde,
            'hasta'          => $hasta,
            'color_atrasada' => TalentoWorkOrderType::COLOR_ATRASADA,
            'tipos'          => $tipos->map(fn ($t) => [
                'id'     => $t->id,
                'nombre' => $t->name,
                'color'  => $t->colorEfectivo(),
                'activo' => (bool) $t->active,
            ])->values()->all(),
            'personas'       => $ordenes->filter(fn ($o) => $o['colaborador_id'])
                ->unique('colaborador_id')
                ->map(fn ($o) => ['colaborador_id' => $o['colaborador_id'], 'nombre' => $o['persona']])
                ->sortBy('nombre')
                ->values()->all(),
            'ordenes'        => $ordenes->all(),
        ];
    }

    /**
     * Órdenes atrasadas de una persona para «Mi día»: mismas llaves que el resumen de hoy más la
     * marca de atraso. $resumen arma la tarjeta (OrdenTrabajoUnifiedService::summaryFromTask).
     */
    public function atrasadasDe(int $userId, callable $resumen): Collection
    {
        $hoy = now()->toDateString();

        return Task::with(['talentoType'])
            ->whereHas('users', fn ($q) => $q->where('users.id', $userId))
            ->where(fn ($q) => $this->scopeAtrasadas($q, $hoy))
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get()
            ->map(function (Task $t) use ($resumen, $hoy) {
                $fecha = TaskScheduleService::start($t->start_date, $t->start_time)?->toDateString();

                return $resumen($t) + [
                    'atrasada'    => true,
                    'fecha'       => $fecha,
                    'dias_atraso' => $fecha ? Carbon::parse($fecha)->diffInDays(Carbon::parse($hoy)) : 0,
                ];
            })
            // Colección simple: vacía, la de Eloquent no deja juntarle arreglos (merge pide getKey()).
            ->toBase();
    }

    /**
     * Mueve una orden abierta a otro día (de hoy en adelante) y deja el motivo en su historial.
     * $nuevaFecha: "YYYY-MM-DD" (conserva la hora que tenía) o "YYYY-MM-DD HH:mm".
     */
    public function reprogramar(Task $task, string $nuevaFecha, string $motivo, ?int $actorId): array
    {
        if (! $task->talento_type_id) {
            return ['ok' => false, 'mensaje' => 'Solo se reprograman órdenes de trabajo de Talento.', 'codigo' => 422];
        }
        if (! in_array($task->status, self::ESTADOS_ABIERTOS, true) || $task->validated_at) {
            return ['ok' => false, 'mensaje' => 'Solo se reprograma una orden pendiente o en curso.', 'codigo' => 422];
        }

        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5) {
            return ['ok' => false, 'mensaje' => 'Escribe por qué se mueve la orden.', 'codigo' => 422];
        }

        $anterior = TaskScheduleService::start($task->start_date, $task->start_time);
        $conHora  = (bool) preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $nuevaFecha);
        $nuevo    = Carbon::parse($nuevaFecha);
        if (! $conHora && $anterior) {
            $nuevo->setTime((int) $anterior->format('H'), (int) $anterior->format('i'));
        }

        if ($nuevo->toDateString() < now()->toDateString()) {
            return ['ok' => false, 'mensaje' => 'La nueva fecha debe ser de hoy en adelante.', 'codigo' => 422];
        }

        // Misma regla de choque de agenda que Crear y Editar (#1227), solo si el inicio cambia; la
        // orden no choca consigo misma. Aplica a los tres caminos: panel, «Mi día» del portal y app.
        if (! $anterior || $nuevo->format('Y-m-d H:i') !== $anterior->format('Y-m-d H:i')) {
            $choque = app(ChoqueAgendaOrdenes::class)->verificar($task->users()->first(), $nuevo, $task->id);
            if ($choque) {
                return $choque;
            }
        }

        $eraAtrasada = $anterior && $anterior->toDateString() < now()->toDateString();

        DB::transaction(function () use ($task, $nuevo, $motivo, $actorId, $anterior, $eraAtrasada) {
            DB::table('tasks')->where('id', $task->id)->update(TaskScheduleService::fields($nuevo, $nuevo->copy()->addHour()) + [
                'updated_by' => $actorId ?? 0,
                'updated_at' => now(),
            ]);

            TalentoOrderEventRecorder::registrar('task', $task->id, 'order.rescheduled', null, null, $actorId, $motivo, [
                'de'        => $anterior?->format('Y-m-d H:i'),
                'a'         => $nuevo->format('Y-m-d H:i'),
                'atrasada'  => $eraAtrasada,
            ]);
        });

        return ['ok' => true, 'fecha' => $nuevo->toDateString(), 'hora' => $nuevo->format('H:i') !== '00:00' ? $nuevo->format('H:i') : null];
    }

    /** Veces que se movió la orden de día, con quién, de qué fecha a cuál y por qué (más reciente primero). */
    public function reprogramacionesDe(int $taskId): array
    {
        $eventos = \App\Modules\Addons\Talento\Models\TalentoOrderEvent::deOrden('task', $taskId)
            ->where('evento', 'order.rescheduled')
            ->get();
        $nombres = DB::table('users')->whereIn('id', $eventos->pluck('actor_id')->filter()->unique()->all())
            ->get(['id', 'name', 'father_last_name'])
            ->mapWithKeys(fn ($u) => [$u->id => trim($u->name . ' ' . $u->father_last_name)]);

        return $eventos->reverse()->map(fn ($e) => [
            'cuando'   => $e->created_at?->format('Y-m-d H:i'),
            'quien'    => $nombres[$e->actor_id] ?? null,
            'de'       => $e->datos['de'] ?? null,
            'a'        => $e->datos['a'] ?? null,
            'atrasada' => (bool) ($e->datos['atrasada'] ?? false),
            'motivo'   => $e->motivo,
        ])->values()->all();
    }

    /**
     * Órdenes de la tabla vieja talento_work_orders (en producción vacía; en dev, datos de prueba).
     * Talento › Órdenes las lista, así que la agenda también; son de solo lectura: no cuentan como
     * atrasadas ni se reprograman.
     */
    private function ordenesAntiguas(string $desde, string $hasta, ?array $colaboradorIds, Collection $tiposPorId): Collection
    {
        $wos = \App\Modules\Addons\Talento\Models\TalentoWorkOrder::with(['colaborador.user:id,name,father_last_name'])
            ->whereNotNull('scheduled_at')
            ->whereDate('scheduled_at', '>=', $desde)
            ->whereDate('scheduled_at', '<=', $hasta)
            ->where('status', '!=', 'cancelled')
            ->when($colaboradorIds !== null, fn ($q) => $q->whereIn('colaborador_id', $colaboradorIds ?: [-1]))
            ->get();
        if ($wos->isEmpty()) {
            return collect();
        }

        $clientes = DB::table('client_main_information')->whereIn('client_id', $wos->pluck('client_id')->filter()->unique()->all())
            ->get(['client_id', 'name', 'father_last_name', 'mother_last_name'])
            ->mapWithKeys(fn ($r) => [$r->client_id => trim(implode(' ', array_filter([$r->name, $r->father_last_name, $r->mother_last_name]))) ?: null]);

        return $wos->map(function ($o) use ($tiposPorId, $clientes) {
            $inicio = Carbon::parse($o->scheduled_at);
            $tipo = $tiposPorId->get($o->type_id);
            $usuario = $o->colaborador?->user;

            return [
                'id'             => $o->id,
                'origen'         => 'work_order',
                'dia'            => $inicio->toDateString(),
                'fecha'          => $inicio->toDateString(),
                'hora'           => $inicio->format('H:i') !== '00:00' ? $inicio->format('H:i') : null,
                'tipo_id'        => $o->type_id,
                'tipo'           => $tipo?->name ?? '—',
                'color'          => $tipo?->colorEfectivo() ?? '#616161',
                'color_tipo'     => $tipo?->colorEfectivo() ?? '#616161',
                'estado'         => $o->status,
                'atrasada'       => false,
                'dias_atraso'    => 0,
                'colaborador_id' => $o->colaborador_id,
                'persona'        => $usuario ? trim($usuario->name . ' ' . $usuario->father_last_name) : 'Sin asignar',
                'cliente'        => $o->client_id ? ($clientes[$o->client_id] ?? null) : null,
                'es_prospecto'   => false,
            ];
        });
    }

    /** @return array<int,string> client_main_information.id → nombre */
    private function nombresClientes(array $ids): array
    {
        if (! $ids) {
            return [];
        }

        return DB::table('client_main_information')->whereIn('id', $ids)
            ->get(['id', 'name', 'father_last_name', 'mother_last_name'])
            ->mapWithKeys(fn ($r) => [$r->id => trim(implode(' ', array_filter([$r->name, $r->father_last_name, $r->mother_last_name]))) ?: null])
            ->all();
    }

    /** @return array<int,string> crm_lead_information.id → nombre */
    private function nombresProspectos(array $ids): array
    {
        if (! $ids) {
            return [];
        }

        return DB::table('crm_lead_information as l')
            ->join('crm_main_information as m', 'm.crm_id', '=', 'l.crm_id')
            ->whereIn('l.id', $ids)
            ->get(['l.id', 'm.name', 'm.father_last_name', 'm.mother_last_name'])
            ->mapWithKeys(fn ($r) => [$r->id => trim(implode(' ', array_filter([$r->name, $r->father_last_name, $r->mother_last_name]))) ?: null])
            ->all();
    }
}
