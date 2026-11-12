<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\Task;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderChecklistItem;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderData;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderMedia;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderType;
use App\Modules\Addons\Talento\Support\EsquemaCamposOrden;
use App\Modules\Addons\Talento\Support\OrdenEquipo;
use App\Modules\Addons\Talento\Support\OrdenReferencia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Translation layer para la app TalentoEquipo y el admin SPA.
 *
 * Capa 3: 4 endpoints READ leen de dos fuentes (work_orders + tasks tipo=campo).
 * Capa 4.2: admin LIST/SHOW/CREATE usan este service.
 * Capa 4.3: los 5 endpoints WRITE también pasan por aquí.
 */
class OrdenTrabajoUnifiedService
{
    // Task.status → OT status equivalente
    private const TASK_STATUS_MAP = [
        'ToDo'       => 'pending',
        'InProgress' => 'in_progress',
        'Done'       => 'completed',
    ];

    // ── Resolución de usuario ─────────────────────────────────────────────────

    private function getUserId(int $colaboradorId): ?int
    {
        return TalentoColaborador::where('id', $colaboradorId)
            ->value('user_id');
    }

    /**
     * Resuelve un Task tipo=campo asignado al colaborador dado.
     * Incluye el filtro whereNotNull('talento_type_id') para excluir tareas de soporte.
     */
    private function resolveTask(int $taskId, int $colaboradorId): ?Task
    {
        $userId = $this->getUserId($colaboradorId);
        if (! $userId) {
            return null;
        }

        return Task::where('id', $taskId)
            ->whereNotNull('talento_type_id')
            ->whereHas('users', fn($q) => $q->where('users.id', $userId))
            ->first();
    }

    // ── Snapshots de cliente ──────────────────────────────────────────────────

    /** Resuelve datos de cliente a partir de client_main_information.id */
    private function clientByInfoId(?int $infoId): array
    {
        if (! $infoId) {
            return ['name' => null, 'address' => null, 'phone' => null];
        }
        $info = DB::table('client_main_information')
            ->where('id', $infoId)
            ->first(['name', 'father_last_name', 'mother_last_name', 'address', 'phone']);
        if (! $info) {
            return ['name' => null, 'address' => null, 'phone' => null];
        }
        return [
            'name'    => trim(implode(' ', array_filter([
                $info->name,
                $info->father_last_name,
                $info->mother_last_name,
            ]))) ?: null,
            'address' => $info->address ?: null,
            'phone'   => $info->phone   ?: null,
        ];
    }

    /** Resuelve datos de cliente a partir de clients.id (como hacía otSummary para OTs) */
    private function clientByClientId(?int $clientId): array
    {
        if (! $clientId) {
            return ['name' => null, 'address' => null, 'phone' => null];
        }
        $info = DB::table('client_main_information')
            ->where('client_id', $clientId)
            ->first(['name', 'father_last_name', 'mother_last_name', 'address', 'phone']);
        if ($info) {
            return [
                'name'    => trim(implode(' ', array_filter([
                    $info->name,
                    $info->father_last_name,
                    $info->mother_last_name,
                ]))) ?: null,
                'address' => $info->address ?: null,
                'phone'   => $info->phone   ?: null,
            ];
        }
        // Fallback legacy
        $client = DB::table('clients')->where('id', $clientId)->first(['user_id']);
        if ($client?->user_id) {
            $user = DB::table('users')->where('id', $client->user_id)->first(['name', 'address']);
            return ['name' => $user?->name, 'address' => $user?->address, 'phone' => null];
        }
        return ['name' => null, 'address' => null, 'phone' => null];
    }

    // ── Formatters ────────────────────────────────────────────────────────────

    private function summaryFromWorkOrder(TalentoWorkOrder $ot): array
    {
        $client = $this->clientByClientId($ot->client_id);
        return [
            'id'        => $ot->id,
            'folio'     => $ot->id,
            'origen'    => 'work_order',   // discriminador de origen (tabla talento_work_orders)
            'tipo'      => $ot->type?->name ?? '—',
            'status'    => $ot->status,
            'cliente'   => $client['name'],
            'direccion' => $client['address'],
            'telefono'  => $client['phone'],
        ];
    }

    private function summaryFromTask(Task $task): array
    {
        // Una orden asignada desde la ficha de un prospecto no tiene cliente: el técnico (Mi día y
        // la app, que leen estas mismas llaves) recibe los datos del prospecto (2026-10-27).
        $esProspecto = ! $task->client_main_information_id && $task->crm_lead_id;
        $client = $esProspecto
            ? $this->prospectoContacto($task->crm_lead_id)
            : $this->clientByInfoId($task->client_main_information_id);
        // #1326: con una incidencia sin atender la orden está con el supervisor: la app la muestra como
        // «Incidencia» (no cuenta en el globo de pendientes ni se puede iniciar).
        $incidencia = in_array($task->status, ['ToDo', 'InProgress'], true)
            ? app(IncidenciasOrdenService::class)->abierta($task->id)
            : null;
        return [
            'id'           => $task->id,
            'folio'        => $task->id,
            'origen'       => 'task',         // discriminador de origen (tabla tasks)
            'tipo'         => $task->talentoType?->name ?? '—',
            'status'       => $incidencia ? 'incidencia' : (self::TASK_STATUS_MAP[$task->status] ?? $task->status),
            'incidencia'   => $incidencia ? ['motivo' => $incidencia['motivo_texto'], 'nota' => $incidencia['nota']] : null,
            'cliente'      => $client['name'],
            'direccion'    => $client['address'],
            'telefono'     => $client['phone'],
            'es_prospecto' => (bool) $esProspecto,
        ];
    }

    private function detailFromWorkOrder(TalentoWorkOrder $ot): array
    {
        $evidencias = DB::table('talento_work_order_media as m')
            ->leftJoin('talento_evidence_types as et', 'et.id', '=', 'm.evidence_type_id')
            ->where('m.work_order_id', $ot->id)
            ->orderByDesc('m.created_at')
            ->get([
                'm.id', 'm.created_at', 'm.potencia_dbm',
                'm.evidence_type_id as type_id', 'et.name as tipo_nombre',
                'm.watermark_applied', 'm.location_flagged', 'm.type as media_type',
            ])
            ->map(fn($e) => $this->conFotoFirmada($this->enrichEvidenciaWithTier($e)))
            ->toArray();

        $requeridas = DB::table('talento_ot_type_evidence_requirements as r')
            ->join('talento_evidence_types as et', 'et.id', '=', 'r.evidence_type_id')
            ->where('r.ot_type_id', $ot->type_id)
            ->whereNull('r.condition')
            ->get(['et.id', 'et.name as nombre', 'et.es_firma'])
            ->toArray();

        return array_merge($this->summaryFromWorkOrder($ot), [
            'notas'                   => $ot->notes,
            'nota_tecnico'            => $ot->nota_tecnico,
            'scheduled_at'            => $ot->scheduled_at,
            'potencia_referencia_dbm' => null,
            'evidencias'              => $evidencias,
            'evidencias_requeridas'   => $requeridas,
        ]);
    }

    private function detailFromTask(Task $task): array
    {
        $typeId = $task->talento_type_id;

        // Capa 4.3: evidencias guardadas en talento_work_order_media con tarea_id
        $evidencias = DB::table('talento_work_order_media as m')
            ->leftJoin('talento_evidence_types as et', 'et.id', '=', 'm.evidence_type_id')
            ->where('m.tarea_id', $task->id)
            ->orderByDesc('m.created_at')
            ->get([
                'm.id', 'm.created_at', 'm.potencia_dbm',
                'm.evidence_type_id as type_id', 'et.name as tipo_nombre',
                'm.watermark_applied', 'm.location_flagged', 'm.type as media_type',
            ])
            ->map(fn($e) => $this->conFotoFirmada($this->enrichEvidenciaWithTier($e)))
            ->toArray();

        $requeridas = $typeId
            ? DB::table('talento_ot_type_evidence_requirements as r')
                ->join('talento_evidence_types as et', 'et.id', '=', 'r.evidence_type_id')
                ->where('r.ot_type_id', $typeId)
                ->whereNull('r.condition')
                ->get(['et.id', 'et.name as nombre', 'et.es_firma'])
                ->toArray()
            : [];

        $scheduledAt = null;
        if ($task->start_date) {
            $scheduledAt = \App\Services\TaskScheduleService::start($task->start_date, $task->start_time)
                ->format('Y-m-d\\TH:i:s.000000\\Z');
        }

        return array_merge($this->summaryFromTask($task), [
            'notas'                   => $task->description,
            'nota_tecnico'            => $task->nota_tecnico,
            'scheduled_at'            => $scheduledAt,
            // Item #1158: la app avisa que está atrasada y ofrece reprogramarla, igual que «Mi día».
            'atrasada'                => $this->estaAtrasada($task, $scheduledAt),
            'fecha'                   => $scheduledAt ? substr($scheduledAt, 0, 10) : null,
            'potencia_referencia_dbm' => null,
            'evidencias'              => $evidencias,
            'evidencias_requeridas'   => $requeridas,
        ]);
    }

    private function estaAtrasada(Task $task, ?string $scheduledAt): bool
    {
        return config('talento.ordenes_atrasadas_en_mi_dia', true)
            && $scheduledAt !== null
            && in_array($task->status, AgendaOrdenesService::ESTADOS_ABIERTOS, true)
            && ! $task->validated_at
            && substr($scheduledAt, 0, 10) < now()->toDateString();
    }

    // ── API pública READ ──────────────────────────────────────────────────────

    /**
     * OTs del día (otsHoy).
     */
    public function summaryForHoy(int $colaboradorId): array
    {
        $today  = now()->toDateString();
        $userId = $this->getUserId($colaboradorId);

        $fromWorkOrders = TalentoWorkOrder::with(['type'])
            ->where('colaborador_id', $colaboradorId)
            ->whereDate('scheduled_at', $today)
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn($o) => $this->summaryFromWorkOrder($o))->toBase();

        $incluyeGenerales = (bool) config('unificacion.interruptores.mi_dia_tareas', false);

        $fromTasks = ($userId)
            ? Task::with(['talentoType'])
                ->when(! $incluyeGenerales, fn($q) => $q->whereNotNull('talento_type_id'))
                ->whereHas('users', fn($q) => $q->where('users.id', $userId))
                ->whereDate('start_date', $today)
                ->orderBy('start_date')
                ->orderBy('start_time')
                ->get()
                ->map(function ($t) use ($incluyeGenerales) {
                    $resumen = $this->summaryFromTask($t);
                    if (! $incluyeGenerales) {
                        return $resumen;
                    }
                    $inicio = \App\Services\TaskScheduleService::start($t->start_date, $t->start_time);
                    $resumen['scheduled_at']     = $inicio?->format('Y-m-d\\TH:i:s.000000\\Z');
                    $resumen['hora']             = $t->start_time ? $inicio?->format('H:i') : null;
                    $resumen['es_tarea_general'] = ! $t->talento_type_id;
                    if (! $t->talento_type_id) {
                        $resumen['tipo'] = 'Tarea';
                    }
                    return $resumen;
                })
            : collect();

        // Item #1158: lo que no se hizo en su día no se queda atrás — sigue arriba, en rojo
        // (`atrasada`), hasta que se termine o se reprograme con un motivo.
        $atrasadas = ($userId && config('talento.ordenes_atrasadas_en_mi_dia', true))
            ? app(AgendaOrdenesService::class)->atrasadasDe($userId, fn (Task $t) => $this->summaryFromTask($t))
            : collect();

        return $atrasadas->merge($fromWorkOrders)->merge($fromTasks)->values()->all();
    }

    /**
     * Calendario de la app (2026-11-05): órdenes y tareas de la persona que tocan los días entre
     * $desde y $hasta, con las mismas reglas que Scheduling › Calendario. Una tarea de varios días
     * sale aunque haya empezado antes del rango. Lo atrasado se ve siempre: si el rango incluye hoy,
     * también llega lo abierto de antes del rango, para que la app lo muestre al ver hoy.
     */
    public function calendarioDe(int $colaboradorId, string $desde, string $hasta): array
    {
        $userId = $this->getUserId($colaboradorId);
        $hoy = now()->toDateString();
        $incluyeAtrasadas = $hoy >= $desde && $hoy <= $hasta;

        $tareas = ($userId)
            ? Task::with(['talentoType'])
                ->whereHas('users', fn($q) => $q->where('users.id', $userId))
                ->where(fn($q) => $q->whereNull('archived')->orWhere('archived', false))
                ->whereNotIn('status', ['Archivado', 'Cancelled', 'Canceled', 'Cancelado', 'Cancelada'])
                ->whereDate('start_date', '<=', $hasta)
                ->where(function ($q) use ($desde, $incluyeAtrasadas) {
                    $q->whereDate('start_date', '>=', $desde)->orWhere('end_time', '>=', $desde);
                    if ($incluyeAtrasadas) {
                        $q->orWhereIn('status', AgendaOrdenesService::ESTADOS_ABIERTOS);
                    }
                })
                ->get()
                ->map(function (Task $t) use ($desde, $incluyeAtrasadas) {
                    [$inicio, $fin] = \App\Services\TaskScheduleService::ventana($t->start_date, $t->start_time, $t->end_time);
                    $abierta = in_array($t->status, AgendaOrdenesService::ESTADOS_ABIERTOS, true);
                    if (! $inicio || ($fin->toDateString() < $desde && ! ($incluyeAtrasadas && $abierta))) {
                        return null;
                    }
                    $resumen = $this->summaryFromTask($t);

                    return [
                        'id'        => $t->id,
                        'origen'    => 'task',
                        'es_orden'  => (bool) $t->talento_type_id,
                        'titulo'    => $t->title ?: ($t->talentoType?->name ?? 'Tarea'),
                        'prioridad' => $t->priority,
                        'estado'    => $t->status,
                        'atrasada'  => $abierta && $fin->lt(now()),
                        'inicio'    => $inicio->format('Y-m-d\\TH:i:s'),
                        'fin'       => $fin->format('Y-m-d\\TH:i:s'),
                        'cliente'   => $resumen['cliente'],
                        'direccion' => $resumen['direccion'] ?: $t->address,
                    ];
                })
                ->filter()
                ->toBase()
            : collect();

        // Tabla vieja de órdenes (en producción vacía): como en la agenda, no cuentan como atrasadas.
        $estadoAntigua = ['pending' => 'ToDo', 'in_progress' => 'InProgress', 'completed' => 'Done', 'validated' => 'Done', 'active' => 'Done'];
        $antiguas = TalentoWorkOrder::with(['type'])
            ->where('colaborador_id', $colaboradorId)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('scheduled_at')
            ->whereDate('scheduled_at', '>=', $desde)
            ->whereDate('scheduled_at', '<=', $hasta)
            ->get()
            ->map(function (TalentoWorkOrder $o) use ($estadoAntigua) {
                $resumen = $this->summaryFromWorkOrder($o);
                $inicio = \Carbon\Carbon::parse($o->scheduled_at)->format('Y-m-d\\TH:i:s');

                return [
                    'id'        => $o->id,
                    'origen'    => 'work_order',
                    'es_orden'  => true,
                    'titulo'    => $resumen['tipo'],
                    'prioridad' => null,
                    'estado'    => $estadoAntigua[$o->status] ?? 'ToDo',
                    'atrasada'  => false,
                    'inicio'    => $inicio,
                    'fin'       => $inicio,
                    'cliente'   => $resumen['cliente'],
                    'direccion' => $resumen['direccion'],
                ];
            })
            ->toBase();

        return $tareas->merge($antiguas)->sortBy('inicio')->values()->all();
    }

    /**
     * Reprogramar una orden propia desde «Mi día» (item #1158): solo órdenes asignadas a la persona.
     */
    public function reprogramarPropia(int $id, int $colaboradorId, string $fecha, string $motivo, ?int $actorId): array
    {
        $task = $this->resolveTask($id, $colaboradorId);
        if (! $task) {
            return ['ok' => false, 'mensaje' => 'Orden no encontrada.', 'codigo' => 404];
        }

        return app(AgendaOrdenesService::class)->reprogramar($task, $fecha, $motivo, $actorId);
    }

    /**
     * Delta de OTs (work orders + tasks de campo) modificadas desde $since, solo del colaborador.
     * Base del polling `GET /talento/api/cambios?since=` de la APK.
     */
    public function cambiosDesde(int $colaboradorId, \Carbon\Carbon $since): array
    {
        $userId = $this->getUserId($colaboradorId);

        $fromWorkOrders = TalentoWorkOrder::with(['type'])
            ->where('colaborador_id', $colaboradorId)
            ->where('updated_at', '>', $since)
            ->orderBy('updated_at')
            ->limit(200)
            ->get()
            ->map(fn($o) => $this->summaryFromWorkOrder($o))->toBase();

        $fromTasks = ($userId)
            ? Task::with(['talentoType'])
                ->where('tipo', 'campo')
                ->whereNotNull('talento_type_id')
                ->whereHas('users', fn($q) => $q->where('users.id', $userId))
                ->where('updated_at', '>', $since)
                ->orderBy('updated_at')
                ->limit(200)
                ->get()
                ->map(fn($t) => $this->summaryFromTask($t))
            : collect();

        return $fromWorkOrders->merge($fromTasks)->values()->all();
    }

    /**
     * Historial paginado (otHistorial).
     */
    public function historial(int $colaboradorId, ?string $estado, ?string $fecha, int $perPage = 20): array
    {
        $userId = $this->getUserId($colaboradorId);

        $woQuery = TalentoWorkOrder::with(['type'])
            ->where('colaborador_id', $colaboradorId);
        if ($estado) {
            $woQuery->where('status', $estado);
        }
        if ($fecha) {
            $woQuery->whereDate('scheduled_at', $fecha);
        }
        $workOrders = $woQuery->orderByDesc('scheduled_at')
            ->get()
            ->map(fn($o) => array_merge($this->summaryFromWorkOrder($o), [
                '_sort_key' => $o->scheduled_at ? $o->scheduled_at->timestamp : 0,
            ]))->toBase(); // force Support\Collection — empty Eloquent\Collection::merge() crashes on arrays

        $tasks = collect();
        if ($userId) {
            $taskQuery = Task::with(['talentoType'])
                ->whereHas('users', fn($q) => $q->where('users.id', $userId));
            if ($estado) {
                $taskStatus = array_search($estado, self::TASK_STATUS_MAP);
                if ($taskStatus !== false) {
                    $taskQuery->where('status', $taskStatus);
                } else {
                    $taskQuery->whereRaw('1=0');
                }
            }
            if ($fecha) {
                $taskQuery->whereDate('start_date', $fecha);
            }
            $tasks = $taskQuery->whereNotNull('talento_type_id')
                ->orderByDesc('start_date')
                ->orderByDesc('start_time')
                ->get()
                ->map(fn($t) => array_merge($this->summaryFromTask($t), [
                    '_sort_key' => $t->start_date ? strtotime($t->start_date) : 0,
                ]));
        }

        $all = $workOrders->merge($tasks)
            ->sortByDesc('_sort_key')
            ->map(function ($item) {
                unset($item['_sort_key']);
                return $item;
            })
            ->values();

        $total    = $all->count();
        $page     = max(1, (int) request()->query('page', 1));
        $lastPage = (int) ceil($total / $perPage) ?: 1;
        $page     = min($page, $lastPage);
        $data     = $all->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'data'         => $data,
            'current_page' => $page,
            'last_page'    => $lastPage,
            'total'        => $total,
        ];
    }

    /**
     * Detalle completo de una OT (otShow).
     */
    public function detail(int $id, int $colaboradorId): ?array
    {
        $ot = TalentoWorkOrder::with(['type'])
            ->where('id', $id)
            ->where('colaborador_id', $colaboradorId)
            ->first();
        if ($ot) {
            return $this->detailFromWorkOrder($ot);
        }

        $task = $this->resolveTask($id, $colaboradorId);
        if ($task) {
            $task->load('talentoType');
            return $this->detailFromTask($task);
        }

        return null;
    }

    /**
     * Lookup ligero para tiposEvidencia.
     * Devuelve stdClass{id, type_id, is_task} o null.
     */
    public function findLight(int $id, int $colaboradorId): ?object
    {
        $ot = TalentoWorkOrder::where('id', $id)
            ->where('colaborador_id', $colaboradorId)
            ->first(['id', 'type_id']);
        if ($ot) {
            return (object)['id' => $ot->id, 'type_id' => $ot->type_id, 'is_task' => false];
        }

        $task = $this->resolveTask($id, $colaboradorId);
        if ($task) {
            return (object)['id' => $task->id, 'type_id' => $task->talento_type_id, 'is_task' => true];
        }

        return null;
    }

    /**
     * UNI-T D2 — punto único de registro de transiciones de orden: evento (E2) + quién la hizo.
     * Detrás de unificacion.interruptores.transiciones_d2; apagado no hace nada.
     * $origen: 'task' | 'work_order'. $columnas: columnas de actor a llenar (assigned_by|completed_by|validated_by).
     */
    public function transicionar(string $origen, int $id, string $evento, ?string $de, ?string $a, ?int $actorId, ?string $motivo = null, array $columnas = [], ?array $datos = null): void
    {
        if (! config('unificacion.interruptores.transiciones_d2')) {
            return;
        }
        try {
            if ($columnas && $actorId) {
                $permitidas = array_intersect($columnas, ['assigned_by', 'completed_by', 'validated_by']);
                $tabla = $origen === 'task' ? 'tasks' : 'talento_work_orders';
                DB::table($tabla)->where('id', $id)->update(array_fill_keys($permitidas, $actorId));
            }
            \App\Modules\Addons\Talento\Services\TalentoOrderEventRecorder::registrar($origen, $id, $evento, $de, $a, $actorId, $motivo, $datos);
        } catch (\Throwable $e) {
            Log::warning('transicionar falló', ['origen' => $origen, 'id' => $id, 'evento' => $evento, 'err' => $e->getMessage()]);
        }
    }

    // ── API pública WRITE (Capa 4.3) ─────────────────────────────────────────

    /**
     * 4.3a — Iniciar una OT: pending → in_progress.
     */
    public function iniciar(int $id, int $colaboradorId): array
    {
        $wo = TalentoWorkOrder::where('id', $id)
            ->where('colaborador_id', $colaboradorId)
            ->first();

        if ($wo) {
            if ($wo->status !== 'pending') {
                return ['success' => false, 'message' => "La OT ya está en estado '{$wo->status}'.", 'status_code' => 422];
            }
            $wo->update(['status' => 'in_progress', 'started_at' => now()]);
            $this->transicionar('work_order', $wo->id, 'order.started', 'pending', 'in_progress', auth()->id());
            return ['success' => true, 'status' => $wo->status, 'started_at' => $wo->started_at];
        }

        $task = $this->resolveTask($id, $colaboradorId);
        if ($task) {
            if ($task->status !== 'ToDo') {
                $mapped = self::TASK_STATUS_MAP[$task->status] ?? $task->status;
                return ['success' => false, 'message' => "La OT ya está en estado '{$mapped}'.", 'status_code' => 422];
            }
            // #1326: con una incidencia sin atender la orden está con el supervisor.
            if (app(IncidenciasOrdenService::class)->abierta($task->id)) {
                return ['success' => false, 'message' => 'Esta orden está con tu supervisor por la incidencia que reportaste. Espera a que la regrese o la reprograme.', 'status_code' => 422];
            }
            $task->update(['status' => 'InProgress']);
            $this->transicionar('task', $task->id, 'order.started', 'pending', 'in_progress', auth()->id());
            return ['success' => true, 'status' => 'in_progress', 'started_at' => null];
        }

        return ['success' => false, 'message' => 'OT no encontrada.', 'status_code' => 404];
    }

    /**
     * 4.3b — Guardar nota del técnico.
     */
    public function guardarNota(int $id, int $colaboradorId, ?string $nota): array
    {
        $wo = TalentoWorkOrder::where('id', $id)
            ->where('colaborador_id', $colaboradorId)
            ->first();

        if ($wo) {
            $wo->update(['nota_tecnico' => $nota ?: null]);
            return ['success' => true, 'ok' => true];
        }

        $task = $this->resolveTask($id, $colaboradorId);
        if ($task) {
            $task->update(['nota_tecnico' => $nota ?: null]);
            return ['success' => true, 'ok' => true];
        }

        return ['success' => false, 'message' => 'OT no encontrada.', 'status_code' => 404];
    }

    /**
     * 4.3c — Reportar incidencia.
     * Tabla hija: tarea_id si fuente es task, work_order_id si es WO.
     * Nota: tasks no tienen status 'incidencia' en el enum; la respuesta devuelve
     * 'incidencia' para compatibilidad con la app pero el task queda en su estado actual.
     */
    public function reportarIncidencia(int $id, int $colaboradorId, array $data, int $userId): array
    {
        $wo = TalentoWorkOrder::where('id', $id)
            ->where('colaborador_id', $colaboradorId)
            ->first();

        if ($wo) {
            if (! in_array($wo->status, ['pending', 'in_progress'])) {
                return ['success' => false, 'message' => "No se puede reportar incidencia en estado '{$wo->status}'.", 'status_code' => 422];
            }
            $incidentId = DB::table('talento_work_order_incidents')->insertGetId([
                'client_uuid'   => $data['client_uuid'] ?? null,
                'work_order_id' => $wo->id,
                'tarea_id'      => null,
                'motivo'        => $data['motivo'],
                'nota'          => $data['nota'] ?? null ?: null,
                'lat'           => isset($data['lat']) ? (float)$data['lat'] : null,
                'lng'           => isset($data['lng']) ? (float)$data['lng'] : null,
                'server_at'     => now(),
                'created_by'    => $userId,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            $estadoPrevio = $wo->status;
            $wo->update(['status' => 'incidencia']);
            $this->transicionar('work_order', $wo->id, 'order.incident', $estadoPrevio, 'incidencia', $userId, $data['motivo'] ?? null);
            return ['success' => true, 'incident_id' => $incidentId, 'status' => 'incidencia'];
        }

        $task = $this->resolveTask($id, $colaboradorId);
        if ($task) {
            $mappedStatus = self::TASK_STATUS_MAP[$task->status] ?? $task->status;
            if (! in_array($mappedStatus, ['pending', 'in_progress'])) {
                return ['success' => false, 'message' => "No se puede reportar incidencia en estado '{$mappedStatus}'.", 'status_code' => 422];
            }
            $incidentId = DB::table('talento_work_order_incidents')->insertGetId([
                'client_uuid'   => $data['client_uuid'] ?? null,
                'work_order_id' => null,
                'tarea_id'      => $task->id,
                'motivo'        => $data['motivo'],
                'nota'          => $data['nota'] ?? null ?: null,
                'lat'           => isset($data['lat']) ? (float)$data['lat'] : null,
                'lng'           => isset($data['lng']) ? (float)$data['lng'] : null,
                'server_at'     => now(),
                'created_by'    => $userId,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            // #1326: la orden pasa al supervisor. Si ya estaba en curso vuelve a pendiente, para que el
            // supervisor la pueda editar, reasignar o eliminar con las mismas reglas de #1225.
            if ($task->status === 'InProgress') {
                $task->update(['status' => 'ToDo']);
            }
            $this->transicionar('task', $task->id, 'order.incident', $mappedStatus, 'pending', $userId, $data['motivo'] ?? null);
            return ['success' => true, 'incident_id' => $incidentId, 'status' => 'incidencia'];
        }

        return ['success' => false, 'message' => 'OT no encontrada.', 'status_code' => 404];
    }

    /**
     * 4.3d — Subir evidencia fotográfica.
     * Tabla hija: tarea_id si fuente es task, work_order_id si es WO.
     * Tasks no tienen lat/lng de destino → distance flagging omitido para tasks.
     */
    public function subirEvidencia(int $id, int $colaboradorId, array $data, UploadedFile $file, int $userId): array
    {
        $wo = TalentoWorkOrder::where('id', $id)
            ->where('colaborador_id', $colaboradorId)
            ->first();
        $task   = null;
        $isTask = false;

        if (! $wo) {
            $task = $this->resolveTask($id, $colaboradorId);
            if (! $task) {
                return ['success' => false, 'message' => 'OT no encontrada.', 'status_code' => 404];
            }
            $isTask = true;
        }

        $evTypeId = (int)$data['evidence_type_id'];
        $evType   = DB::table('talento_evidence_types')->where('id', $evTypeId)->first();

        if (config('unificacion.interruptores.evidencias_d6')) {
            $estado = $isTask ? (self::TASK_STATUS_MAP[$task->status] ?? $task->status) : $wo->status;
            if (! in_array($estado, ['pending', 'in_progress'], true)) {
                return ['success' => false, 'message' => "No se puede subir evidencia en estado '{$estado}'.", 'code' => 'ESTADO_INVALIDO', 'status_code' => 422];
            }
            $typeId = $isTask ? $task->talento_type_id : $wo->type_id;
            $enMatriz = DB::table('talento_ot_type_evidence_requirements')
                ->where('ot_type_id', $typeId)->where('evidence_type_id', $evTypeId)->exists();
            if (! $enMatriz && $evTypeId !== 15) {
                return ['success' => false, 'message' => 'Este tipo de evidencia no corresponde a este tipo de orden.', 'code' => 'TIPO_NO_EN_MATRIZ', 'status_code' => 422];
            }
        }

        if ($evType->requiere_justificacion && empty(trim($data['justificacion'] ?? ''))) {
            return [
                'success'    => false,
                'message'    => 'Este tipo de evidencia requiere una justificación.',
                'code'       => 'JUSTIFICACION_REQUERIDA',
                'status_code'=> 422,
            ];
        }

        if (! $evType->permite_varias) {
            $fkCol    = $isTask ? 'tarea_id' : 'work_order_id';
            $entityId = $isTask ? $task->id : $wo->id;
            $existe   = DB::table('talento_work_order_media')
                ->where($fkCol, $entityId)
                ->where('evidence_type_id', $evTypeId)
                ->exists();

            if ($existe) {
                return [
                    'success'    => false,
                    'message'    => "Ya existe evidencia de tipo '{$evType->name}' para esta OT.",
                    'code'       => 'TIPO_DUPLICADO',
                    'status_code'=> 422,
                ];
            }
        }

        $lat      = (float)$data['lat'];
        $lng      = (float)$data['lng'];
        $accuracy = isset($data['accuracy']) ? (float)$data['accuracy'] : null;
        $potencia = isset($data['potencia_dbm']) ? (float)$data['potencia_dbm'] : null;
        $isMock   = (bool)($data['is_mock_location'] ?? false);

        // Distance flagging solo para WOs (tasks no tienen lat/lng de destino)
        $locationFlagged = false;
        $distanceM       = null;
        if (! $isTask && $wo->latitude && $wo->longitude) {
            $distanceM = $this->haversineMeters($lat, $lng, (float)$wo->latitude, (float)$wo->longitude);
            $flaggedRadius = (float)(DB::table('settings')
                ->where('key', 'talento_media_flagged_radius_m')
                ->value('value') ?? 500);
            $locationFlagged = $distanceM > $flaggedRadius;
        }

        // Separar subdirectorios para evitar colisión entre WO ids y task ids
        $dirPrefix = $isTask ? 't_' : '';
        $entityId  = $isTask ? $task->id : $wo->id;
        $dir       = "talento/media/{$dirPrefix}{$entityId}";
        $name      = Str::uuid() . ($file->guessExtension() === 'png' ? '.png' : '.jpg');
        $path      = $file->storeAs($dir, $name, 'local');

        $mediaId = DB::table('talento_work_order_media')->insertGetId([
            'client_uuid'         => $data['client_uuid'] ?? null,
            'work_order_id'       => $isTask ? null : $wo->id,
            'tarea_id'            => $isTask ? $task->id : null,
            'evidence_type_id'    => $evTypeId,
            'type'                => 'completion',
            'file_path'           => $path,
            'captured_lat'        => $lat,
            'captured_lng'        => $lng,
            'captured_at'         => now(),
            'server_captured_at'  => now(),
            'captured_in_app'     => true,
            'watermark_applied'   => true,
            'location_flagged'    => $locationFlagged,
            'is_mock_location'    => $isMock,
            'location_distance_m' => $distanceM,
            'potencia_dbm'        => $potencia,
            'gps_accuracy_m'      => $accuracy,
            'justificacion'       => $data['justificacion'] ?? null ?: null,
            'source'              => $data['source'] ?? 'mobile',   // portal web manda 'portal_web'; app móvil no se afecta

            'created_by'          => $userId,
            'created_at'          => now(),
        ]);

        // Auto-start si aún está pendiente
        if ($isTask) {
            if ($task->status === 'ToDo') {
                $task->update(['status' => 'InProgress']);
            }
        } else {
            if ($wo->status === 'pending') {
                $wo->update(['status' => 'in_progress']);
            }
        }

        $saludRed = $potencia !== null ? $this->calcularSaludRed($potencia) : null;

        return ['success' => true, 'media_id' => $mediaId, 'salud_red' => $saludRed];
    }

    /**
     * 11-nov-2026 (#1347): las fotos ya subidas de una orden, para la pantalla de evidencias de la
     * app, y si todavía se pueden quitar (orden pendiente o en curso).
     */
    public function evidenciasParaCaptura(object $ot): array
    {
        $evidencias = DB::table('talento_work_order_media')
            ->where($ot->is_task ? 'tarea_id' : 'work_order_id', $ot->id)
            ->whereNotNull('evidence_type_id')
            ->orderBy('created_at')
            ->get(['id', 'evidence_type_id as type_id', 'created_at', 'potencia_dbm', 'type as media_type'])
            ->map(fn($e) => $this->conFotoFirmada($e))
            ->all();

        return ['evidencias' => $evidencias, 'puede_eliminar' => $this->evidenciaEditable($ot)];
    }

    /**
     * 11-nov-2026 (#1347): el técnico quita una foto suya que salió mal para tomarla de nuevo.
     * Solo con la orden pendiente o en curso; ya terminada no. Se borra el archivo y queda en
     * activity_log quién la quitó, de qué tipo era y cuándo se había tomado.
     */
    public function eliminarEvidencia(int $id, int $mediaId, int $colaboradorId, int $userId): array
    {
        $ot = $this->findLight($id, $colaboradorId);
        if (! $ot) {
            return ['success' => false, 'message' => 'OT no encontrada.', 'status_code' => 404];
        }

        $media = TalentoWorkOrderMedia::where('id', $mediaId)
            ->where($ot->is_task ? 'tarea_id' : 'work_order_id', $ot->id)
            ->whereNotNull('evidence_type_id')
            ->first();
        if (! $media || $media->isSensitive()) {
            return ['success' => false, 'message' => 'Evidencia no encontrada.', 'status_code' => 404];
        }

        if (! $this->evidenciaEditable($ot)) {
            return ['success' => false, 'message' => 'La orden ya se terminó; sus evidencias ya no se pueden quitar.', 'code' => 'ESTADO_INVALIDO', 'status_code' => 422];
        }

        $tipo = DB::table('talento_evidence_types')->where('id', $media->evidence_type_id)->value('name');
        $path = $media->file_path;
        $media->delete();
        Storage::disk('local')->delete($path);

        activity('talento')
            ->performedOn($ot->is_task ? Task::find($ot->id) : TalentoWorkOrder::find($ot->id))
            ->causedBy(User::find($userId))
            ->withProperties([
                'media_id' => $mediaId, 'tipo' => $tipo, 'tomada' => (string) $media->captured_at,
                'potencia_dbm' => $media->potencia_dbm, 'archivo' => $path,
            ])
            ->log('Evidencia quitada desde la app para tomarla de nuevo');

        return ['success' => true];
    }

    private function evidenciaEditable(object $ot): bool
    {
        $estado = $ot->is_task
            ? (self::TASK_STATUS_MAP[Task::where('id', $ot->id)->value('status')] ?? null)
            : TalentoWorkOrder::where('id', $ot->id)->value('status');

        return in_array($estado, ['pending', 'in_progress'], true);
    }

    /**
     * 4.3e — Completar una OT: in_progress → completed.
     * Valida evidencias requeridas y gate dBm. NO toca el ledger (eso es Capa 4.4).
     */
    public function completar(int $id, int $colaboradorId, array $data): array
    {
        $wo = TalentoWorkOrder::where('id', $id)
            ->where('colaborador_id', $colaboradorId)
            ->first();

        if ($wo) {
            if ($wo->status !== 'in_progress') {
                return ['success' => false, 'message' => "Solo se puede completar una OT en curso (estado actual: '{$wo->status}').", 'status_code' => 422];
            }
            $ev = $this->validarEvidencias($wo->type_id, 'work_order_id', $wo->id);
            if ($ev !== null) return $ev;

            $dbm = $this->verificarGateDbm($wo->type_id, 'work_order_id', $wo->id);
            if (isset($dbm['error'])) return $dbm['error'];

            $updates = ['status' => 'completed', 'completed_at' => now()];
            if (! empty($data['nota_tecnico'])) {
                $updates['nota_tecnico'] = $data['nota_tecnico'];
            }
            $wo->update($updates);
            $this->transicionar('work_order', $wo->id, 'order.completed', 'in_progress', 'completed', auth()->id(), null, ['completed_by']);

            return [
                'success'      => true,
                'status'       => 'completed',
                'completed_at' => $wo->completed_at,
                'dbm_tier'     => $dbm['tier'],
            ];
        }

        $task = $this->resolveTask($id, $colaboradorId);
        if ($task) {
            if ($task->status !== 'InProgress') {
                $mapped = self::TASK_STATUS_MAP[$task->status] ?? $task->status;
                return ['success' => false, 'message' => "Solo se puede completar una OT en curso (estado actual: '{$mapped}').", 'status_code' => 422];
            }
            $ev = $this->validarEvidencias($task->talento_type_id, 'tarea_id', $task->id);
            if ($ev !== null) return $ev;

            $dbm = $this->verificarGateDbm($task->talento_type_id, 'tarea_id', $task->id);
            if (isset($dbm['error'])) return $dbm['error'];

            $updates = ['status' => 'Done'];
            if (! config('unificacion.interruptores.validacion_separada')) {
                $updates['validated_at'] = now();
            }
            if (! empty($data['nota_tecnico'])) {
                $updates['nota_tecnico'] = $data['nota_tecnico'];
            }
            $task->update($updates);
            $task->refresh();
            $this->transicionar('task', $task->id, 'order.completed', 'in_progress', 'completed', auth()->id(), null, ['completed_by']);
            $this->cerrarTicketSiCorresponde($task);
            if ((int) $task->talento_type_id === 3) { // 3 = Cambio de equipo
                app(\App\Services\PppoeCsidReaprenderService::class)->alCerrarOtCambioEquipo($task);
            }

            return [
                'success'      => true,
                'status'       => 'completed',
                'completed_at' => $task->finish_at,
                'dbm_tier'     => $dbm['tier'],
            ];
        }

        return ['success' => false, 'message' => 'OT no encontrada.', 'status_code' => 404];
    }

    /**
     * G3b — Regla explícita (apagada por defecto): al completar la última orden abierta de un ticket,
     * el ticket pasa a «Cerrado». Con el interruptor apagado los estados son independientes.
     */
    private function cerrarTicketSiCorresponde($task): void
    {
        if (! config('unificacion.interruptores.cierre_orden_cierra_ticket_g3b') || ! $task->ticket_id) {
            return;
        }
        $abiertas = \App\Models\Task::where('ticket_id', $task->ticket_id)
            ->where('id', '!=', $task->id)
            ->whereNotIn('status', ['Done', 'Cancelled'])
            ->exists();
        if ($abiertas) {
            return;
        }
        $ticket = \App\Models\Ticket::find($task->ticket_id);
        if ($ticket && $ticket->estado !== 'Cerrado') {
            $ticket->update(['estado' => 'Cerrado']);
        }
    }

    // ── Admin SPA — LIST + SHOW ───────────────────────────────────────────────

    /** Resuelve datos de prospecto CRM a partir de crm_lead_information.id */
    private function prospectoByCrmLeadId(?int $crmLeadId): ?array
    {
        if (! $crmLeadId) {
            return null;
        }
        $info = DB::table('crm_lead_information')
            ->join('crm_main_information', 'crm_main_information.crm_id', '=', 'crm_lead_information.crm_id')
            ->where('crm_lead_information.id', $crmLeadId)
            ->first(['crm_main_information.name', 'crm_main_information.father_last_name', 'crm_main_information.mother_last_name']);
        if (! $info) {
            return null;
        }
        $nombre = trim(implode(' ', array_filter([
            $info->name,
            $info->father_last_name,
            $info->mother_last_name,
        ])));
        return ['id' => $crmLeadId, 'nombre' => $nombre ?: null];
    }

    /**
     * Nombre, dirección y teléfono del prospecto CRM con la misma forma que clientByInfoId().
     * Casi ningún prospecto tiene `address` armada: se compone de calle, números, colonia y municipio.
     */
    private function prospectoContacto(?int $crmLeadId): array
    {
        $vacio = ['name' => null, 'address' => null, 'phone' => null];
        if (! $crmLeadId) {
            return $vacio;
        }
        $info = DB::table('crm_lead_information as l')
            ->join('crm_main_information as m', 'm.crm_id', '=', 'l.crm_id')
            ->leftJoin('colonies as c', 'c.id', '=', 'm.colony_id')
            ->leftJoin('municipalities as mu', 'mu.id', '=', 'm.municipality_id')
            ->where('l.id', $crmLeadId)
            ->first([
                'm.name', 'm.father_last_name', 'm.mother_last_name', 'm.address', 'm.street',
                'm.external_number', 'm.internal_number', 'm.phone', 'm.phone2',
                'c.name as colonia', 'mu.name as municipio',
            ]);
        if (! $info) {
            return $vacio;
        }
        $calle = trim(implode(' ', array_filter([$info->street, $info->external_number, $info->internal_number])));
        return [
            'name'    => trim(implode(' ', array_filter([
                $info->name,
                $info->father_last_name,
                $info->mother_last_name,
            ]))) ?: null,
            'address' => $info->address ?: (implode(', ', array_filter([$calle, $info->colonia, $info->municipio])) ?: null),
            'phone'   => $info->phone ?: ($info->phone2 ?: null),
        ];
    }

    /** Cliente de la orden para Talento › Órdenes: id (para abrir su ficha) y nombre. */
    private function clienteResumen(?int $clientId): ?array
    {
        if (! $clientId) {
            return null;
        }
        $cliente = $this->clientByClientId($clientId);

        // #1228: también la dirección, para mostrar qué cambia al cambiar el cliente de una orden.
        return ['id' => $clientId, 'nombre' => $cliente['name'] ?: "Cliente #{$clientId}", 'direccion' => $cliente['address']];
    }

    /**
     * Item #1200 — clientes para el «Cliente de referencia» de una orden de planta externa.
     * Busca por número de cliente exacto, nombre completo o teléfono. Solo lo necesario para
     * elegirlo: número, nombre y dirección.
     */
    public function searchClientesReferencia(string $search, int $limit = 10): array
    {
        $search = trim($search);
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';

        return DB::table('client_main_information')
            ->whereNull('deleted_at')
            ->whereNotNull('client_id')
            ->where(function ($q) use ($search, $like) {
                if (ctype_digit(ltrim($search, '0')) && ltrim($search, '0') !== '') {
                    $q->orWhere('client_id', (int) ltrim($search, '0'));
                }
                $q->orWhereRaw("CONCAT_WS(' ', name, father_last_name, mother_last_name) LIKE ?", [$like])
                  ->orWhere('phone', 'like', $like);
            })
            // El número de cliente exacto va primero (los demás pueden coincidir por teléfono).
            ->orderByRaw('client_id = ? DESC', [(int) ltrim($search, '0')])
            ->orderBy('client_id')
            ->limit($limit)
            ->get(['client_id', 'name', 'father_last_name', 'mother_last_name', 'address'])
            ->map(fn ($r) => [
                'id'        => (int) $r->client_id,
                'nombre'    => trim(implode(' ', array_filter([$r->name, $r->father_last_name, $r->mother_last_name]))) ?: "Cliente #{$r->client_id}",
                'direccion' => $r->address ?: null,
            ])
            ->values()
            ->all();
    }

    /**
     * #9991204 (Fase A1 de #9991201) — prospectos CRM para el dropdown del modal
     * "Nueva Orden de Trabajo". Devuelve 2 grupos: los del propio técnico primero
     * (crm_techical_user_id = colaborador.user_id — decisión q3 de Irving) y el
     * resto del listado activo. "Activo" = decisión q1 de Irving: Nuevo, Contactado,
     * Interesado, Instalacion — EXCLUYE Ganado (ya es cliente) y Perdido (descartado).
     */
    public function searchProspectosCrm(?int $colaboradorId, ?string $search, int $limit = 10): array
    {
        $activeStatuses = ['Nuevo', 'Contactado', 'Interesado', 'Instalacion'];
        $select = ['cli.id', 'cmi.name', 'cmi.father_last_name', 'cmi.mother_last_name', 'cmi.phone', 'cli.crm_status'];

        $base = fn () => DB::table('crm_lead_information as cli')
            ->join('crm_main_information as cmi', 'cmi.crm_id', '=', 'cli.crm_id')
            ->whereIn('cli.crm_status', $activeStatuses);

        $applySearch = function ($q) use ($search) {
            if (! $search) {
                return;
            }
            $q->where(function ($w) use ($search) {
                $w->where('cmi.name', 'like', "%{$search}%")
                    ->orWhere('cmi.father_last_name', 'like', "%{$search}%")
                    ->orWhere('cmi.mother_last_name', 'like', "%{$search}%")
                    ->orWhere('cmi.phone', 'like', "%{$search}%");
            });
        };

        $userId = $colaboradorId ? TalentoColaborador::where('id', $colaboradorId)->value('user_id') : null;

        $propios = [];
        $propiosIds = [];
        if ($userId) {
            $q = $base()->where('cli.crm_techical_user_id', $userId);
            $applySearch($q);
            $rows = $q->orderByDesc('cli.id')->limit($limit)->get($select);
            $propiosIds = $rows->pluck('id')->all();
            $propios = $rows->map(fn ($r) => $this->formatProspecto($r))->all();
        }

        $q2 = $base();
        if ($propiosIds) {
            $q2->whereNotIn('cli.id', $propiosIds);
        }
        $applySearch($q2);
        $generales = $q2->orderByDesc('cli.id')->limit($limit)->get($select)
            ->map(fn ($r) => $this->formatProspecto($r))->all();

        return ['propios' => $propios, 'generales' => $generales];
    }

    private function formatProspecto(object $r): array
    {
        $nombre = trim(implode(' ', array_filter([$r->name, $r->father_last_name, $r->mother_last_name])));
        return [
            'id'       => $r->id,
            'nombre'   => $nombre ?: '(sin nombre)',
            'telefono' => $r->phone,
            'status'   => $r->crm_status,
        ];
    }

    /** #1325: [{colaborador_id, user_id, nombre}] de los auxiliares de una orden en equipo. */
    private function auxiliaresDeTarea(Task $task): array
    {
        $ids = OrdenEquipo::auxiliaresUserIds($task);
        if (! $ids) {
            return [];
        }
        $colaboradores = TalentoColaborador::whereIn('user_id', $ids)->pluck('id', 'user_id');

        return User::whereIn('id', $ids)->get(['id', 'name', 'father_last_name', 'mother_last_name'])
            ->sortBy(fn ($u) => array_search($u->id, $ids, true))
            ->map(fn ($u) => [
                'colaborador_id' => $colaboradores[$u->id] ?? null,
                'user_id'        => $u->id,
                'nombre'         => trim(implode(' ', array_filter([$u->name, $u->father_last_name, $u->mother_last_name]))),
            ])
            ->values()
            ->all();
    }

    private function adminItemFromTask(Task $task): array
    {
        // #1325: con órdenes en equipo, la persona de la orden es el responsable (no el primer asignado).
        $firstUser   = $task->users->firstWhere('id', OrdenEquipo::responsableUserId($task)) ?? $task->users->first();
        $colaborador = $firstUser
            ? TalentoColaborador::with('user')->where('user_id', $firstUser->id)->first()
            : null;

        $scheduledAt = null;
        if ($task->start_date) {
            $scheduledAt = \App\Services\TaskScheduleService::start($task->start_date, $task->start_time)
                ->format('Y-m-d H:i:s');
        }

        $type = $task->talentoType;

        return [
            'id'             => $task->id,
            'colaborador_id' => $colaborador?->id,
            'type_id'        => $task->talento_type_id,
            'points'         => $task->points,
            'is_billable'    => (bool) $task->is_billable,
            'status'         => self::TASK_STATUS_MAP[$task->status] ?? $task->status,
            'scheduled_at'   => $scheduledAt,
            // Item #1158: abierta y con su día ya pasado.
            'atrasada'       => $scheduledAt !== null
                && in_array($task->status, AgendaOrdenesService::ESTADOS_ABIERTOS, true)
                && substr($scheduledAt, 0, 10) < now()->toDateString(),
            'notes'          => $task->description,
            // Item #1200: en planta externa el título identifica la orden ("[Habilitar caja] Zona 3 · Caja 12").
            'title'          => $task->title,
            'nota_tecnico'   => $task->nota_tecnico,
            'validated_at'   => $task->validated_at,
            'validated_by'   => null,
            'created_at'     => $task->created_at,
            'updated_at'     => $task->updated_at,
            'colaborador'    => $colaborador ? [
                'id'      => $colaborador->id,
                'user_id' => $firstUser->id,
                'user'    => [
                    'id'                => $firstUser->id,
                    'name'              => $firstUser->name,
                    'father_last_name'  => $firstUser->father_last_name,
                    'mother_last_name'  => $firstUser->mother_last_name,
                ],
            ] : null,
            // #1325: auxiliares del responsable (vacío sin órdenes en equipo).
            'auxiliares'     => $this->auxiliaresDeTarea($task),
            // #1326: incidencia sin atender (la orden está con el supervisor).
            'incidencia'     => ! $task->trashed() && in_array($task->status, ['ToDo', 'InProgress'], true)
                ? app(IncidenciasOrdenService::class)->abierta($task->id)
                : null,
            'type' => $type ? [
                'id'                  => $type->id,
                'name'                => $type->name,
                'category'            => $type->category,
                'area'                => $type->area ?? TalentoWorkOrderType::AREA_CLIENTE,
                'points'              => $type->points,
                'is_billable'         => (bool) $type->is_billable,
                'requires_validation' => (bool) $type->requires_validation,
                'active'              => true,
            ] : null,
            'assigned_by'    => null,
            'work_activities' => [],
            'cliente'        => $this->clienteResumen($task->client_main_information_id
                ? DB::table('client_main_information')->where('id', $task->client_main_information_id)->value('client_id')
                : null),
            'prospecto'      => $this->prospectoByCrmLeadId($task->crm_lead_id),
            // #1225: con qué más está ligada (el aviso de eliminar dice que el prospecto y el ticket no se tocan).
            'ticket_id'      => $task->ticket_id ? (int) $task->ticket_id : null,
            // #1228: lo que es del cliente actual y no deja cambiarle el cliente (ticket, ONU, servicio).
            'vinculos'       => OrdenPendienteService::vinculosDelCliente($task),
            'eliminada'      => $task->trashed() ? [
                'cuando' => $task->deleted_at?->format('Y-m-d H:i'),
                'por'    => $task->eliminada_por
                    ? trim((string) DB::table('users')->where('id', $task->eliminada_por)->selectRaw("CONCAT_WS(' ', name, father_last_name) as n")->value('n')) ?: null
                    : null,
                'motivo' => $task->motivo_eliminacion,
            ] : null,
            '_source'        => 'task',
        ];
    }

    public function listForAdmin(array $filters, int $perPage = 25, int $page = 1): array
    {
        $colaboradorId = $filters['colaborador_id'] ?? null;
        $status        = $filters['status']         ?? null;
        $typeId        = $filters['type_id']        ?? null;
        $from          = $filters['from']           ?? null;
        $to            = $filters['to']             ?? null;
        $search        = $filters['search']         ?? null;
        $prospecto     = $filters['prospecto']      ?? null;
        // #1225: "Ver eliminadas" — solo las órdenes borradas (lógico) de `tasks`; el controller ya
        // revisó el permiso. Las antiguas no se eliminan desde aquí, así que no entran.
        $eliminadas    = ! empty($filters['eliminadas']);

        // Filtro "Buscar prospecto" (#9991201): TalentoWorkOrder no tiene crm_lead_id
        // (tabla sin uso, 0 filas — decisión ya tomada al construir #9991205/#9991206),
        // así que solo puede matchear tasks. Resolver primero los crm_lead_information.id
        // que matchean el texto, luego whereIn en el task query de abajo.
        $prospectoLeadIds = null;
        if ($prospecto) {
            $prospectoLeadIds = DB::table('crm_lead_information as cli')
                ->join('crm_main_information as cmi', 'cmi.crm_id', '=', 'cli.crm_id')
                ->where(function ($w) use ($prospecto) {
                    $w->where('cmi.name', 'like', "%{$prospecto}%")
                        ->orWhere('cmi.father_last_name', 'like', "%{$prospecto}%")
                        ->orWhere('cmi.mother_last_name', 'like', "%{$prospecto}%");
                })
                ->pluck('cli.id')
                ->all();
        }

        // David (29-sep, verificando Órdenes): colaborador_id ahora también
        // acepta un ARREGLO (IDOR real encontrado: talento.work_orders.view
        // lo tiene TECNICO directo, y sin scoping cualquiera veía las
        // órdenes de CUALQUIER colaborador con solo omitir el filtro). El
        // controller pasa un arreglo [yo + mi equipo] cuando quien pide no
        // es staff; whereIn cubre ambos casos, where() seguía cubriendo el
        // caso de un solo id (uso normal, ficha de un colaborador puntual).
        $woQuery = TalentoWorkOrder::with(['colaborador.user', 'type', 'assignedBy'])
            ->when($colaboradorId, fn($q, $v) => is_array($v) ? $q->whereIn('colaborador_id', $v) : $q->where('colaborador_id', $v))
            ->when($status,        fn($q, $v) => $q->where('status', $v))
            ->when($typeId,        fn($q, $v) => $q->where('type_id', $v))
            ->when($from,          fn($q, $v) => $q->where('scheduled_at', '>=', $v))
            ->when($to,            fn($q, $v) => $q->where('scheduled_at', '<=', $v . ' 23:59:59'))
            ->when($search, fn($q, $s) => $q->whereHas('colaborador.user', fn($u) => $u->where('name', 'like', "%$s%")))
            ->when($prospecto || $eliminadas, fn($q) => $q->whereRaw('1=0'));

        $workOrders = $woQuery->orderByDesc('scheduled_at')
            ->get()
            ->map(fn($o) => array_merge($o->toArray(), [
                '_source'  => 'work_order',
                // Sin fecha va por cuándo se creó: antes caía al final de la lista (2026-10-27).
                '_sort_ts' => $o->scheduled_at ? $o->scheduled_at->timestamp : (strtotime((string) $o->created_at) ?: 0),
            ]))->toBase();

        $taskQuery = Task::with(['talentoType', 'users'])
            ->when($eliminadas, fn($q) => $q->onlyTrashed())
            ->whereNotNull('talento_type_id')
            ->when($typeId, fn($q, $v) => $q->where('talento_type_id', $v))
            ->when($from,   fn($q, $v) => $q->whereDate('start_date', '>=', $v))
            ->when($to,     fn($q, $v) => $q->whereDate('start_date', '<=', $v))
            ->when($search, fn($q, $s) => $q->whereHas('users', fn($u) => $u->where('name', 'like', "%$s%")))
            ->when($prospectoLeadIds !== null, fn($q) => $q->whereIn('crm_lead_id', $prospectoLeadIds ?: [-1]));

        if ($status === 'incidencia') {
            // #1326: bandeja del supervisor (las antiguas no tienen este flujo).
            $workOrders = collect();
            app(IncidenciasOrdenService::class)->scopeConAbierta($taskQuery->whereIn('status', ['ToDo', 'InProgress']));
        } elseif ($status === 'atrasadas') {
            // Item #1158: pendientes o en curso cuyo día ya pasó (la tabla vieja no tiene filas).
            $workOrders = collect();
            app(AgendaOrdenesService::class)->scopeAtrasadas($taskQuery, now()->toDateString());
        } elseif ($status) {
            $taskStatus = array_search($status, self::TASK_STATUS_MAP);
            $taskQuery->when(
                $taskStatus !== false,
                fn($q) => $q->where('status', $taskStatus),
                fn($q) => $q->whereRaw('1=0')
            );
        }

        if ($colaboradorId) {
            $userIds = is_array($colaboradorId)
                ? TalentoColaborador::whereIn('id', $colaboradorId)->pluck('user_id')->filter()->all()
                : array_filter([$this->getUserId((int) $colaboradorId)]);
            $userIds
                ? $taskQuery->whereHas('users', fn($q) => $q->whereIn('users.id', $userIds))
                : $taskQuery->whereRaw('1=0');
        }

        $tasks = $taskQuery->orderByDesc('start_date')->orderByDesc('start_time')
            ->get()
            ->map(fn($t) => array_merge($this->adminItemFromTask($t), [
                '_sort_ts' => $t->start_date ? strtotime($t->start_date) : (strtotime((string) $t->created_at) ?: 0),
            ]));

        $all = $workOrders->merge($tasks)
            ->sortByDesc('_sort_ts')
            ->map(function ($item) {
                unset($item['_sort_ts']);
                return $item;
            })
            ->values();

        $total    = $all->count();
        $lastPage = (int) ceil($total / $perPage) ?: 1;
        $page     = min(max(1, $page), $lastPage);
        $data     = $all->slice(($page - 1) * $perPage, $perPage)->values()->all();

        // Las órdenes de la tabla vieja solo traen client_id: el nombre se resuelve solo para esta página.
        $data = array_map(fn ($item) => ($item['_source'] ?? null) === 'work_order'
            ? $item + ['cliente' => $this->clienteResumen($item['client_id'] ?? null)]
            : $item, $data);

        return [
            'data'         => $data,
            'current_page' => $page,
            'last_page'    => $lastPage,
            'total'        => $total,
            'per_page'     => $perPage,
        ];
    }

    /** $conEliminadas (#1225): también encuentra la orden borrada (lógico), para quien puede eliminar. */
    public function showForAdmin(int $id, ?string $origen = null, bool $conEliminadas = false): ?array
    {
        $ot = $origen === OrdenReferencia::ORIGEN_TAREA ? null : TalentoWorkOrder::with(['colaborador.user', 'type', 'assignedBy', 'validatedBy', 'workActivities.recordedBy'])
            ->find($id);
        if ($ot) {
            $res = $ot->toArray() + ['cliente' => $this->clienteResumen($ot->client_id)];
            if (app(OrderParticipantsService::class)->activo()) {
                $res['participantes'] = app(OrderParticipantsService::class)->listar('work_order', $ot->id);
            }
            return $res;
        }

        $task = $origen === OrdenReferencia::ORIGEN_ANTIGUA ? null : Task::with(['talentoType', 'users'])
            ->when($conEliminadas, fn ($q) => $q->withTrashed())
            ->whereNotNull('talento_type_id')
            ->find($id);
        if ($task) {
            $item = $this->adminItemFromTask($task);
            $item['_source'] = 'task';
            $item['reprogramaciones'] = app(AgendaOrdenesService::class)->reprogramacionesDe($task->id);
            $item += $this->datosDePlantilla($task->id);
            if (app(OrderParticipantsService::class)->activo()) {
                $item['participantes'] = app(OrderParticipantsService::class)->listar('task', $task->id);
            }
            return $item;
        }

        return null;
    }

    /**
     * Item #1200: datos capturados con la plantilla (con la copia del esquema con que nació la
     * orden) y su checklist de materiales. Vacío para las órdenes sin plantilla.
     */
    private function datosDePlantilla(int $taskId): array
    {
        $d = TalentoWorkOrderData::where('tarea_id', $taskId)->first();
        if (! $d) {
            return ['datos' => null, 'checklist' => []];
        }

        return [
            'datos' => [
                'template_id' => $d->template_id,
                'plantilla'   => $d->template?->name,
                'campos'      => $d->schema_snapshot ?? [],
                'valores'     => (object) ($d->values ?? []),
                // #1225: las notas del usuario, para editarlas sin el resto de la descripción.
                'notas'       => EsquemaCamposOrden::notasDe(Task::whereKey($taskId)->withTrashed()->value('description')),
            ],
            'checklist' => TalentoWorkOrderChecklistItem::where('tarea_id', $taskId)
                ->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'label', 'cantidad', 'unidad', 'checked', 'checked_at', 'checked_by'])
                ->toArray(),
        ];
    }

    // ── Admin SPA — WRITE ────────────────────────────────────────────────────────

    /** E1 Fase 3: con el interruptor encendido, las órdenes antiguas (L-) son de solo lectura. */
    private function ordenAntiguaBloqueada(): ?array
    {
        if (! config('unificacion.interruptores.ordenes_antiguas_solo_lectura')) {
            return null;
        }
        return ['success' => false, 'message' => 'Las órdenes antiguas son de solo lectura.', 'status_code' => 422];
    }

    public function actualizarAdmin(int $id, array $data, ?string $origen = null): array
    {
        $task = $origen === OrdenReferencia::ORIGEN_ANTIGUA ? null : Task::where('id', $id)->whereNotNull('talento_type_id')->first();
        if ($task) {
            $currentOtStatus = self::TASK_STATUS_MAP[$task->status] ?? $task->status;
            if (in_array($currentOtStatus, ['validated', 'cancelled'])) {
                return ['success' => false, 'message' => 'No se puede editar una orden validada o cancelada.', 'status_code' => 422];
            }

            if (config('unificacion.interruptores.ordenes_d5b') && $task->validated_at !== null
                && (isset($data['type_id']) || isset($data['colaborador_id']))) {
                return ['success' => false, 'message' => 'Una orden validada no cambia de tipo ni de responsable fuera del procedimiento de corrección.', 'status_code' => 422];
            }

            $updates = ['updated_by' => auth()->id() ?? 0, 'updated_at' => now()];

            if (isset($data['type_id'])) {
                $type = TalentoWorkOrderType::find($data['type_id']);
                if ($type) {
                    $updates['talento_type_id'] = $data['type_id'];
                    $updates['points']          = $type->points;
                    $updates['is_billable']     = $type->is_billable ? 1 : 0;
                }
            }
            if (isset($data['scheduled_at'])) {
                $dt = \Carbon\Carbon::parse($data['scheduled_at']);
                $updates = array_merge($updates, \App\Services\TaskScheduleService::fields($dt, $dt->copy()->addHour()));
            }
            if (array_key_exists('notes', $data)) {
                $updates['description'] = $data['notes'];
            }
            if (isset($data['client_id'])) {
                $updates['client_main_information_id'] = DB::table('client_main_information')
                    ->where('client_id', $data['client_id'])->value('id');
            }
            if (isset($data['olt_onu_id'])) {
                $updates['olt_onu_id'] = $data['olt_onu_id'];
            }
            if (isset($data['colaborador_id'])) {
                $userId = $this->getUserId((int) $data['colaborador_id']);
                if ($userId) {
                    $task->users()->sync([$userId]);
                    app(OrderParticipantsService::class)->fijar('task', $id, $userId);
                    $this->transicionar('task', $id, 'order.reassigned', null, null, auth()->id(), null, ['assigned_by'], ['colaborador_id' => (int) $data['colaborador_id']]);
                }
            }

            DB::table('tasks')->where('id', $id)->update($updates);
            return ['success' => true, 'data' => $this->showForAdmin($id, $origen)];
        }

        $wo = $origen === OrdenReferencia::ORIGEN_TAREA ? null : TalentoWorkOrder::find($id);
        if ($wo && !$wo->trashed()) {
            if ($bloq = $this->ordenAntiguaBloqueada()) {
                return $bloq;
            }
            if (in_array($wo->status, ['validated', 'cancelled'])) {
                return ['success' => false, 'message' => 'No se puede editar una orden validada o cancelada.', 'status_code' => 422];
            }
            if (isset($data['type_id']) && $data['type_id'] != $wo->type_id) {
                $type = TalentoWorkOrderType::find($data['type_id']);
                if ($type) {
                    $data['points']      = $type->points;
                    $data['is_billable'] = $type->is_billable;
                }
            }
            $wo->update($data);
            return ['success' => true, 'data' => $wo->fresh()->load('colaborador.user', 'type')];
        }

        return ['success' => false, 'message' => 'OT no encontrada.', 'status_code' => 404];
    }

    public function cambiarEstadoAdmin(int $id, string $newOtStatus, ?string $origen = null, ?string $motivo = null): array
    {
        $transitions = [
            'pending'     => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed'   => [],
            'validated'   => [],
            'cancelled'   => [],
        ];

        $otToTask = [
            'pending'     => 'ToDo',
            'in_progress' => 'InProgress',
            'completed'   => 'Done',
            'cancelled'   => 'Archivado',
        ];

        $task = $origen === OrdenReferencia::ORIGEN_ANTIGUA ? null : Task::where('id', $id)->whereNotNull('talento_type_id')->first();
        if ($task) {
            $currentOtStatus = self::TASK_STATUS_MAP[$task->status] ?? 'cancelled';
            if (!in_array($newOtStatus, $transitions[$currentOtStatus] ?? [])) {
                return ['success' => false, 'message' => "Transición inválida: {$currentOtStatus} → {$newOtStatus}", 'status_code' => 422];
            }
            // #1326: con una incidencia sin atender no se inicia; primero se atiende (Regresar al técnico,
            // Reprogramar, Editar o Eliminar).
            if ($newOtStatus === 'in_progress' && app(IncidenciasOrdenService::class)->abierta($task->id)) {
                return ['success' => false, 'message' => 'Esta orden tiene una incidencia sin atender: regrésala al técnico, reprográmala, edítala o elimínala.', 'status_code' => 422];
            }

            $excepcion = false;
            if ($newOtStatus === 'completed' && config('unificacion.interruptores.ordenes_d5b')) {
                $gate = $this->compuertaCierreAdmin((int) $task->talento_type_id, 'tarea_id', $id, $motivo);
                if (isset($gate['error'])) return $gate['error'];
                $excepcion = $gate['excepcion'];
            }

            $newTaskStatus = $otToTask[$newOtStatus] ?? 'ToDo';
            $updates = [
                'status'     => $newTaskStatus,
                'updated_at' => now(),
                'updated_by' => auth()->id() ?? 0,
            ];
            if ($newTaskStatus === 'Done') {
                $updates['finish_at'] = now()->toDateTimeString();
            }
            if ($newOtStatus === 'cancelled' && config('unificacion.interruptores.ordenes_d5b')) {
                $updates += ['archived' => 1, 'archived_at' => now(), 'archived_by' => auth()->id() ?? 0];
            }

            DB::table('tasks')->where('id', $id)->update($updates);
            $this->transicionar('task', $id, $this->eventoDeEstado($newOtStatus), $currentOtStatus, $newOtStatus, auth()->id(), $excepcion ? $motivo : null, $newOtStatus === 'completed' ? ['completed_by'] : [], $excepcion ? ['cierre_excepcion' => true] : null);
            return ['success' => true, 'data' => $this->showForAdmin($id, $origen)];
        }

        $wo = $origen === OrdenReferencia::ORIGEN_TAREA ? null : TalentoWorkOrder::find($id);
        if ($wo && !$wo->trashed()) {
            if ($bloq = $this->ordenAntiguaBloqueada()) {
                return $bloq;
            }
            if (!in_array($newOtStatus, $transitions[$wo->status] ?? [])) {
                return ['success' => false, 'message' => "Transición inválida: {$wo->status} → {$newOtStatus}", 'status_code' => 422];
            }
            $excepcion = false;
            if ($newOtStatus === 'completed' && config('unificacion.interruptores.ordenes_d5b')) {
                $gate = $this->compuertaCierreAdmin((int) $wo->type_id, 'work_order_id', (int) $wo->id, $motivo);
                if (isset($gate['error'])) return $gate['error'];
                $excepcion = $gate['excepcion'];
            }
            $timestamps = [
                'in_progress' => ['started_at'   => now()],
                'completed'   => ['completed_at' => now()],
            ];
            $estadoPrevio = $wo->status;
            $wo->update(array_merge(['status' => $newOtStatus], $timestamps[$newOtStatus] ?? []));
            $this->transicionar('work_order', $wo->id, $this->eventoDeEstado($newOtStatus), $estadoPrevio, $newOtStatus, auth()->id(), $excepcion ? $motivo : null, $newOtStatus === 'completed' ? ['completed_by'] : [], $excepcion ? ['cierre_excepcion' => true] : null);
            return ['success' => true, 'data' => $wo->fresh()];
        }

        return ['success' => false, 'message' => 'OT no encontrada.', 'status_code' => 404];
    }

    /**
     * D5b — Compuerta del cierre desde administración: evidencias y dBm como en completar(). Con el permiso
     * de excepción y un motivo se puede cerrar sin ellas (el evento queda con el motivo).
     * @return array{excepcion:bool}|array{error:array}
     */
    private function compuertaCierreAdmin(int $typeId, string $fkCol, int $entityId, ?string $motivo): array
    {
        $fallo = $this->validarEvidencias($typeId, $fkCol, $entityId);
        if ($fallo === null) {
            $dbm = $this->verificarGateDbm($typeId, $fkCol, $entityId);
            $fallo = $dbm['error'] ?? null;
        }
        if ($fallo === null) {
            return ['excepcion' => false];
        }
        $motivo = trim((string) $motivo);
        if ($motivo !== '' && auth()->user()?->can('talento.ordenes.cierre_excepcion')) {
            return ['excepcion' => true];
        }
        $fallo['message'] = ($fallo['message'] ?? 'Faltan evidencias.') . ' Cerrar sin ellas exige el permiso de excepción y un motivo.';
        return ['error' => $fallo];
    }

    private function eventoDeEstado(string $estado): string
    {
        return ['in_progress' => 'order.started', 'completed' => 'order.completed', 'cancelled' => 'order.cancelled', 'validated' => 'order.validated'][$estado] ?? 'order.status_changed';
    }

    /**
     * UNI-T D4b — ¿el usuario autenticado puede validar a estos ejecutores? Administración global
     * (super admin) siempre; el resto solo si supervisa (supervisor_id) a alguno. Apagado = sí.
     */
    private function puedeValidarEquipo(array $userIds): bool
    {
        if (! config('unificacion.interruptores.validacion_equipo_d4b')) {
            return true;
        }
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->hasAnyRole(['super-administrator', 'Super Administrador', 'DESARROLLADOR'])) {
            return true;
        }
        $miColab = TalentoColaborador::where('user_id', $user->id)->value('id');
        if (! $miColab || empty($userIds)) {
            return false;
        }

        return TalentoColaborador::whereIn('user_id', $userIds)->where('supervisor_id', $miColab)->exists();
    }

    /** UNI-T D4a — ¿quien valida es asignado, participante o ejecutor de la orden? Apagado = nunca. */
    private function esValidadorPropio(string $origen, int $id, array $userIds): bool
    {
        $yo = auth()->id();
        if (! $yo || ! config('unificacion.interruptores.validacion_no_propia_d4a')) {
            return false;
        }
        $ids = array_map('intval', $userIds);
        try {
            foreach (app(OrderParticipantsService::class)->listar($origen, $id) as $p) {
                $ids[] = (int) $p['user_id'];
            }
        } catch (\Throwable $e) {
            // participantes es solo un complemento; asignados/ejecutor ya cubren lo esencial
        }
        return in_array((int) $yo, $ids, true);
    }

    private function rechazoValidacionPropia(): array
    {
        return ['success' => false, 'message' => 'No puedes validar una orden en la que participas; la validación la hace otra persona (o una excepción administrativa con motivo).', 'status_code' => 422];
    }

    /**
     * UNI-T D4c — excepción administrativa a D4a: quien participa puede validar solo con el permiso
     * 'talento.excepcion_administrativa' y un motivo; queda en el historial. Devuelve el rechazo, o null si procede.
     */
    private function excepcionValidacionPropia(object $orden, ?string $motivo): ?array
    {
        $motivo = trim((string) $motivo);
        if ($motivo === '' || ! auth()->user()?->can('talento.excepcion_administrativa')) {
            return $this->rechazoValidacionPropia();
        }
        activity('talento')->performedOn($orden)->causedBy(auth()->user())
            ->withProperties(['motivo' => $motivo])
            ->log('Orden validada por un participante (excepción administrativa)');
        return null;
    }

    public function validarAdmin(int $id, ?string $origen = null, ?string $motivoExcepcion = null): array
    {
        $task = $origen === OrdenReferencia::ORIGEN_ANTIGUA ? null : Task::where('id', $id)->whereNotNull('talento_type_id')->first();
        if ($task) {
            // Idempotencia: validated_at ya seteado → no re-acreditar
            if ($task->validated_at !== null) {
                return ['success' => false, 'message' => 'OT ya validada.', 'status_code' => 422];
            }

            if ($this->esValidadorPropio('task', $id, $task->users->pluck('id')->all())
                && ($rechazo = $this->excepcionValidacionPropia($task, $motivoExcepcion))) {
                return $rechazo;
            }

            // Acepta InProgress o Done (Done = completada en términos de OT).
            // UNI-T D3b: con validacion_separada ON solo se valida lo ya completado (Done).
            $estadosValidables = config('unificacion.interruptores.validacion_separada') ? ['Done'] : ['InProgress', 'Done'];
            if (!in_array($task->status, $estadosValidables)) {
                return ['success' => false, 'message' => 'Solo se pueden validar órdenes completadas.', 'status_code' => 422];
            }

            if (! $this->puedeValidarEquipo($task->users->pluck('id')->all())) {
                return ['success' => false, 'message' => 'Solo puedes validar órdenes de tu equipo.', 'status_code' => 403];
            }

            $now = now();
            $colabTarea = $task->users->first() ? TalentoColaborador::where('user_id', $task->users->first()->id)->value('id') : null;
            $validatedAt = $colabTarea
                ? app(LiquidationService::class)->instantForLateWrite((int) $colabTarea, $now->copy(), 'task', (int) $task->id, (float) ($task->points ?? 0), auth()->id())
                : $now;
            DB::table('tasks')->where('id', $id)->update([
                'status'       => 'Done',
                'validated_at' => $validatedAt,
                'finish_at'    => $task->finish_at ?? $now->toDateTimeString(),
                'updated_at'   => $now,
                'updated_by'   => auth()->id() ?? 0,
            ]);
            $this->transicionar('task', $id, 'order.validated', 'completed', 'validated', auth()->id(), null, ['validated_by']);

            try {
                app(\App\Modules\Addons\Talento\Services\HealthBonusService::class)->evaluateTask($task->fresh());
            } catch (\Throwable $e) {
                Log::warning('Health bonus evaluation failed for task', ['task_id' => $id, 'err' => $e->getMessage()]);
            }

            // Roadmap interno Fase 11 (Capa 6.1, resuelto): WarrantyWindowService ahora
            // acepta parámetros desacoplados — ya no requiere un TalentoWorkOrder model.
            if ($task->is_billable) {
                $clientId = $task->client_main_information_id
                    ? DB::table('client_main_information')->where('id', $task->client_main_information_id)->value('client_id')
                    : null;
                if ($clientId) {
                    try {
                        // UNI-T D8b-3: con el interruptor encendido la garantía sigue al responsable principal.
                        $principalUid = app(\App\Modules\Addons\Talento\Services\ReconocimientoPorRolService::class)->responsablePrincipalUserId('task', (int) $id);
                        $firstUser = $principalUid ? (object) ['id' => $principalUid] : $task->users->first();
                        $colaboradorId = $firstUser ? TalentoColaborador::where('user_id', $firstUser->id)->value('id') : null;
                        if ($colaboradorId) {
                            app(\App\Modules\Addons\Talento\Services\WarrantyWindowService::class)
                                ->refreshWindow($colaboradorId, $clientId, $task->caja_id, null, $task->id);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Warranty window refresh failed for task', ['task_id' => $id, 'err' => $e->getMessage()]);
                    }
                }
            }

            return ['success' => true, 'data' => $this->showForAdmin($id, $origen)];
        }

        $wo = $origen === OrdenReferencia::ORIGEN_TAREA ? null : TalentoWorkOrder::find($id);
        if ($wo && !$wo->trashed()) {
            if ($bloq = $this->ordenAntiguaBloqueada()) {
                return $bloq;
            }
            $wUid = TalentoColaborador::where('id', $wo->colaborador_id)->value('user_id');
            if ($this->esValidadorPropio('work_order', (int) $wo->id, array_filter([$wUid]))
                && ($rechazo = $this->excepcionValidacionPropia($wo, $motivoExcepcion))) {
                return $rechazo;
            }
            if ($wo->status !== 'completed') {
                return ['success' => false, 'message' => 'Solo se pueden validar órdenes con estado completada.', 'status_code' => 422];
            }
            $woUid = TalentoColaborador::where('id', $wo->colaborador_id)->value('user_id');
            if (! $this->puedeValidarEquipo($woUid ? [(int) $woUid] : [])) {
                return ['success' => false, 'message' => 'Solo puedes validar órdenes de tu equipo.', 'status_code' => 403];
            }
            $wo->update([
                'status'       => 'validated',
                'validated_at' => app(LiquidationService::class)->instantForLateWrite(
                    (int) $wo->colaborador_id, now(), 'work_order', (int) $wo->id, (float) $wo->points, auth()->id()
                ),
                'validated_by' => auth()->id(),
            ]);
            $this->transicionar('work_order', $wo->id, 'order.validated', 'completed', 'validated', auth()->id(), null, ['validated_by']);
            try {
                app(\App\Modules\Addons\Talento\Services\HealthBonusService::class)->evaluate($wo->id);
            } catch (\Throwable $e) {
                Log::warning('Health bonus evaluation failed', ['order_id' => $wo->id, 'err' => $e->getMessage()]);
            }
            if ($wo->is_billable && $wo->client_id) {
                try {
                    app(\App\Modules\Addons\Talento\Services\WarrantyWindowService::class)->refreshWindowForOrder($wo->fresh());
                } catch (\Throwable $e) {
                    Log::warning('Warranty window refresh failed', ['order_id' => $wo->id, 'err' => $e->getMessage()]);
                }
            }
            return ['success' => true, 'data' => $wo->fresh()->load('validatedBy')];
        }

        return ['success' => false, 'message' => 'OT no encontrada.', 'status_code' => 404];
    }

    /**
     * UNI-T D3b — devolver una orden completada sin validar a "en curso", con motivo obligatorio.
     * Solo con validacion_separada ON; apagado no hace nada (comportamiento actual).
     */
    public function devolver(int $id, ?string $motivo, ?string $origen = null): array
    {
        if (! config('unificacion.interruptores.validacion_separada')) {
            return ['success' => false, 'message' => 'Devolver no está habilitado.', 'status_code' => 422];
        }
        $motivo = trim((string) $motivo);
        if ($motivo === '') {
            return ['success' => false, 'message' => 'El motivo es obligatorio.', 'status_code' => 422];
        }
        $task = $origen === OrdenReferencia::ORIGEN_ANTIGUA ? null : Task::where('id', $id)->whereNotNull('talento_type_id')->first();
        if (! $task) {
            return ['success' => false, 'message' => 'OT no encontrada.', 'status_code' => 404];
        }
        if ($task->validated_at !== null || $task->status !== 'Done') {
            return ['success' => false, 'message' => 'Solo se pueden devolver órdenes completadas sin validar.', 'status_code' => 422];
        }
        DB::table('tasks')->where('id', $id)->update([
            'status'     => 'InProgress',
            'updated_at' => now(),
            'updated_by' => auth()->id() ?? 0,
        ]);
        $this->transicionar('task', $id, 'order.returned', 'completed', 'in_progress', auth()->id(), $motivo);

        return ['success' => true, 'data' => $this->showForAdmin($id, $origen)];
    }

    public function agregarActividadAdmin(int $id, array $data): array
    {
        $task = Task::where('id', $id)->whereNotNull('talento_type_id')->first();
        if ($task) {
            // TalentoWorkOrderActivity.fillable no incluye tarea_id → usar DB::table
            $activityId = DB::table('talento_work_order_activities')->insertGetId([
                'work_order_id'    => null,
                'tarea_id'         => $task->id,
                'description'      => $data['description'],
                'duration_minutes' => $data['duration_minutes'] ?? 0,
                'recorded_by'      => auth()->id() ?? 0,
                'created_at'       => now(),
            ]);
            return ['success' => true, 'data' => (array) DB::table('talento_work_order_activities')->find($activityId)];
        }

        $wo = TalentoWorkOrder::find($id);
        if ($wo && !$wo->trashed()) {
            $activity = \App\Modules\Addons\Talento\Models\TalentoWorkOrderActivity::create([
                'work_order_id'    => $wo->id,
                'description'      => $data['description'],
                'duration_minutes' => $data['duration_minutes'] ?? 0,
                'recorded_by'      => auth()->id() ?? 0,
            ]);
            return ['success' => true, 'data' => $activity->toArray()];
        }

        return ['success' => false, 'message' => 'OT no encontrada.', 'status_code' => 404];
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    /**
     * Verifica que todas las evidencias obligatorias estén subidas.
     * @return array|null  null si OK; array de error si faltan evidencias
     */
    private function validarEvidencias(int $typeId, string $fkCol, int $entityId): ?array
    {
        $requeridos = DB::table('talento_ot_type_evidence_requirements')
            ->where('ot_type_id', $typeId)
            ->whereNull('condition')
            ->pluck('evidence_type_id')
            ->map(fn($v) => (int)$v);

        $subidos = DB::table('talento_work_order_media')
            ->where($fkCol, $entityId)
            ->whereNotNull('evidence_type_id')
            ->pluck('evidence_type_id')
            ->map(fn($v) => (int)$v)
            ->unique();

        // Un requisito de firma (es_firma) se satisface con una fila de firma en
        // talento_work_order_signatures (signer_type=client) O con media tipo firma.
        // Retrocompatible: la app móvil que sube la firma como media tipo 9 sigue pasando;
        // el portal la captura con signature-pad y también satisface el gate.
        $firmaRequeridos = DB::table('talento_evidence_types')
            ->whereIn('id', $requeridos->all())
            ->where('es_firma', true)
            ->pluck('id')
            ->map(fn($v) => (int)$v);

        if ($firmaRequeridos->isNotEmpty()) {
            $tieneFirmaCliente = DB::table('talento_work_order_signatures')
                ->where($fkCol, $entityId)
                ->where('signer_type', 'client')
                ->exists();
            if ($tieneFirmaCliente) {
                $subidos = $subidos->merge($firmaRequeridos)->unique();
            }
        }

        $faltantes = $requeridos->diff($subidos);

        if ($faltantes->isNotEmpty()) {
            $nombres = DB::table('talento_evidence_types')
                ->whereIn('id', $faltantes)
                ->pluck('name', 'id');

            return [
                'success'    => false,
                'message'    => 'Faltan evidencias obligatorias antes de completar la OT.',
                'code'       => 'EVIDENCIAS_FALTANTES',
                'faltantes'  => $faltantes->map(fn($evId) => [
                    'evidence_type_id' => $evId,
                    'name'             => $nombres[$evId] ?? "Tipo #{$evId}",
                ])->values()->all(),
                'status_code' => 422,
            ];
        }

        return null;
    }

    /**
     * Evalúa el gate de dBm si el tipo de OT lo requiere.
     * @return array  ['tier' => array|null] si OK; ['error' => array] si bloquea
     */
    private function verificarGateDbm(int $typeId, string $fkCol, int $entityId): array
    {
        $requiereDbm = DB::table('talento_ot_type_evidence_requirements')
            ->join('talento_evidence_types', 'talento_evidence_types.id', '=', 'talento_ot_type_evidence_requirements.evidence_type_id')
            ->where('talento_ot_type_evidence_requirements.ot_type_id', $typeId)
            ->whereNull('talento_ot_type_evidence_requirements.condition')
            ->where('talento_evidence_types.es_lectura_dbm', true)
            ->exists();

        if (! $requiereDbm) {
            return ['tier' => null];
        }

        $ultimoDbm = DB::table('talento_work_order_media')
            ->where($fkCol, $entityId)
            ->whereNotNull('potencia_dbm')
            ->orderByDesc('created_at')
            ->value('potencia_dbm');

        if ($ultimoDbm === null) {
            // D6: la medición es obligatoria cuando el tipo de orden la exige.
            if (config('unificacion.interruptores.evidencias_d6')) {
                return [
                    'error' => [
                        'success'     => false,
                        'message'     => 'Falta la lectura de potencia (dBm) obligatoria para completar la OT.',
                        'code'        => 'DBM_FALTANTE',
                        'status_code' => 422,
                    ],
                ];
            }
            return ['tier' => null];
        }

        $valor  = (float)$ultimoDbm;
        $umbral = $this->umbralDbm($valor);

        if ($umbral && $umbral->accion === 'bloquea') {
            return [
                'error' => [
                    'success'    => false,
                    'message'    => $umbral->mensaje,
                    'code'       => 'DBM_BLOQUEADO',
                    'categoria'  => $umbral->categoria,
                    'dbm'        => $valor,
                    'status_code'=> 422,
                ],
            ];
        }

        return [
            'tier' => $umbral
                ? ['categoria' => $umbral->categoria, 'mensaje' => $umbral->mensaje]
                : null,
        ];
    }

    /** El rango de talento_dbm_thresholds en que cae una lectura. */
    private function umbralDbm(float $valor): ?object
    {
        return DB::table('talento_dbm_thresholds')
            ->where(function ($q) use ($valor) {
                $q->whereNull('dbm_min')->orWhere('dbm_min', '<=', $valor);
            })
            ->where(function ($q) use ($valor) {
                $q->whereNull('dbm_max')->orWhere('dbm_max', '>=', $valor);
            })
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * Añade dbm_tier a un registro de evidencia si tiene potencia_dbm.
     * Permite que CierreScreen muestre el semáforo correctamente.
     */
    private function enrichEvidenciaWithTier(object $ev): object
    {
        if ($ev->potencia_dbm === null) {
            return $ev;
        }
        $umbral = DB::table('talento_dbm_thresholds')
            ->where(fn($q) => $q->whereNull('dbm_min')->orWhere('dbm_min', '<=', $ev->potencia_dbm))
            ->where(fn($q) => $q->whereNull('dbm_max')->orWhere('dbm_max', '>=', $ev->potencia_dbm))
            ->orderByDesc('id')
            ->first();

        $result = (array)$ev;
        $result['dbm_tier'] = $umbral ? (array)$umbral : null;
        return (object)$result;
    }

    /**
     * 11-nov-2026 (#1327): enlace firmado (1 hora) para que la app muestre la foto de la evidencia.
     * Los documentos sensibles (INE, comprobante de domicilio) no llevan enlace.
     */
    private function conFotoFirmada(object $ev): object
    {
        $sensible = in_array($ev->media_type ?? null, TalentoWorkOrderMedia::SENSITIVE_TYPES, true);
        $ev->foto_url = $sensible
            ? null
            : URL::temporarySignedRoute('talento.evidencia.foto.firmada', now()->addHour(), ['id' => $ev->id]);
        unset($ev->media_type);
        return $ev;
    }

    private function calcularSaludRed(float $potencia): array
    {
        $maxLoss    = (float)(DB::table('settings')->where('key', 'talento_health_bonus_max_loss_db')->value('value') ?? 1.0);
        $bonusMonto = (float)(DB::table('settings')->where('key', 'talento_health_bonus_amount')->value('value') ?? 30);
        $perdida    = abs($potencia);
        $bono       = $perdida <= $maxLoss ? $bonusMonto : 0;
        $umbral     = $this->umbralDbm($potencia);

        return [
            // La app muestra categoría y mensaje al guardar la lectura; son los del mismo rango que usa el cierre.
            'categoria'           => ['cumplio' => 'cumplió', 'baja_senal' => 'baja'][$umbral->categoria ?? '']
                                        ?? $umbral->categoria ?? 'sin clasificar',
            'mensaje'             => $umbral->mensaje ?? 'Lectura guardada.',
            'potencia_medida_dbm' => $potencia,
            'max_loss_db'         => $maxLoss,
            'perdida_estimada_db' => round($perdida, 2),
            'calidad'             => $perdida <= $maxLoss ? 'buena' : ($perdida <= $maxLoss * 3 ? 'aceptable' : 'deficiente'),
            'bono'                => $bono,
        ];
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dlat = deg2rad($lat1 - $lat2);
        $dlng = deg2rad($lng1 - $lng2);
        $a    = sin($dlat / 2) ** 2
              + cos(deg2rad($lat2)) * cos(deg2rad($lat1)) * sin($dlng / 2) ** 2;
        return 6371000 * 2 * asin(sqrt($a));
    }
}
