<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\Task;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            ->where('tipo', 'campo')
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
            'tipo'      => $ot->type?->name ?? '—',
            'status'    => $ot->status,
            'cliente'   => $client['name'],
            'direccion' => $client['address'],
            'telefono'  => $client['phone'],
        ];
    }

    private function summaryFromTask(Task $task): array
    {
        $client = $this->clientByInfoId($task->client_main_information_id);
        return [
            'id'        => $task->id,
            'folio'     => $task->id,
            'tipo'      => $task->talentoType?->name ?? '—',
            'status'    => self::TASK_STATUS_MAP[$task->status] ?? $task->status,
            'cliente'   => $client['name'],
            'direccion' => $client['address'],
            'telefono'  => $client['phone'],
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
                'm.watermark_applied', 'm.location_flagged',
            ])
            ->map(fn($e) => $this->enrichEvidenciaWithTier($e))
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
                'm.watermark_applied', 'm.location_flagged',
            ])
            ->map(fn($e) => $this->enrichEvidenciaWithTier($e))
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
            $scheduledAt = $task->start_time
                ? $task->start_date . 'T' . $task->start_time . ':00.000000Z'
                : $task->start_date . 'T00:00:00.000000Z';
        }

        return array_merge($this->summaryFromTask($task), [
            'notas'                   => $task->description,
            'nota_tecnico'            => $task->nota_tecnico,
            'scheduled_at'            => $scheduledAt,
            'potencia_referencia_dbm' => null,
            'evidencias'              => $evidencias,
            'evidencias_requeridas'   => $requeridas,
        ]);
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

        $fromTasks = ($userId)
            ? Task::with(['talentoType'])
                ->where('tipo', 'campo')
                ->whereNotNull('talento_type_id')
                ->whereHas('users', fn($q) => $q->where('users.id', $userId))
                ->whereDate('start_date', $today)
                ->orderBy('start_date')
                ->orderBy('start_time')
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
                ->where('tipo', 'campo')
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
            return ['success' => true, 'status' => $wo->status, 'started_at' => $wo->started_at];
        }

        $task = $this->resolveTask($id, $colaboradorId);
        if ($task) {
            if ($task->status !== 'ToDo') {
                $mapped = self::TASK_STATUS_MAP[$task->status] ?? $task->status;
                return ['success' => false, 'message' => "La OT ya está en estado '{$mapped}'.", 'status_code' => 422];
            }
            $task->update(['status' => 'InProgress']);
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
            $wo->update(['status' => 'incidencia']);
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
        $name      = Str::uuid() . '.jpg';
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
            'source'              => 'mobile',
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

            $updates = ['status' => 'Done', 'validated_at' => now()];
            if (! empty($data['nota_tecnico'])) {
                $updates['nota_tecnico'] = $data['nota_tecnico'];
            }
            $task->update($updates);
            $task->refresh();

            return [
                'success'      => true,
                'status'       => 'completed',
                'completed_at' => $task->finish_at,
                'dbm_tier'     => $dbm['tier'],
            ];
        }

        return ['success' => false, 'message' => 'OT no encontrada.', 'status_code' => 404];
    }

    // ── Admin SPA — LIST + SHOW ───────────────────────────────────────────────

    private function adminItemFromTask(Task $task): array
    {
        $firstUser   = $task->users->first();
        $colaborador = $firstUser
            ? TalentoColaborador::with('user')->where('user_id', $firstUser->id)->first()
            : null;

        $scheduledAt = null;
        if ($task->start_date) {
            $scheduledAt = $task->start_date . ($task->start_time ? ' ' . $task->start_time : ' 00:00:00');
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
            'notes'          => $task->description,
            'nota_tecnico'   => $task->nota_tecnico,
            'validated_at'   => null,
            'validated_by'   => null,
            'created_at'     => $task->created_at,
            'updated_at'     => $task->updated_at,
            'colaborador'    => $colaborador ? [
                'id'      => $colaborador->id,
                'user_id' => $firstUser->id,
                'user'    => ['id' => $firstUser->id, 'name' => $firstUser->name],
            ] : null,
            'type' => $type ? [
                'id'                  => $type->id,
                'name'                => $type->name,
                'category'            => $type->category,
                'points'              => $type->points,
                'is_billable'         => (bool) $type->is_billable,
                'requires_validation' => (bool) $type->requires_validation,
                'active'              => true,
            ] : null,
            'assigned_by'    => null,
            'work_activities' => [],
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

        $woQuery = TalentoWorkOrder::with(['colaborador.user', 'type', 'assignedBy'])
            ->when($colaboradorId, fn($q, $v) => $q->where('colaborador_id', $v))
            ->when($status,        fn($q, $v) => $q->where('status', $v))
            ->when($typeId,        fn($q, $v) => $q->where('type_id', $v))
            ->when($from,          fn($q, $v) => $q->where('scheduled_at', '>=', $v))
            ->when($to,            fn($q, $v) => $q->where('scheduled_at', '<=', $v . ' 23:59:59'))
            ->when($search, fn($q, $s) => $q->whereHas('colaborador.user', fn($u) => $u->where('name', 'like', "%$s%")));

        $workOrders = $woQuery->orderByDesc('scheduled_at')
            ->get()
            ->map(fn($o) => array_merge($o->toArray(), [
                '_source'  => 'work_order',
                '_sort_ts' => $o->scheduled_at ? $o->scheduled_at->timestamp : 0,
            ]))->toBase();

        $taskQuery = Task::with(['talentoType', 'users'])
            ->where('tipo', 'campo')
            ->whereNotNull('talento_type_id')
            ->when($typeId, fn($q, $v) => $q->where('talento_type_id', $v))
            ->when($from,   fn($q, $v) => $q->whereDate('start_date', '>=', $v))
            ->when($to,     fn($q, $v) => $q->whereDate('start_date', '<=', $v))
            ->when($search, fn($q, $s) => $q->whereHas('users', fn($u) => $u->where('name', 'like', "%$s%")));

        if ($status) {
            $taskStatus = array_search($status, self::TASK_STATUS_MAP);
            $taskQuery->when(
                $taskStatus !== false,
                fn($q) => $q->where('status', $taskStatus),
                fn($q) => $q->whereRaw('1=0')
            );
        }

        if ($colaboradorId) {
            $userId = $this->getUserId((int)$colaboradorId);
            $userId
                ? $taskQuery->whereHas('users', fn($q) => $q->where('users.id', $userId))
                : $taskQuery->whereRaw('1=0');
        }

        $tasks = $taskQuery->orderByDesc('start_date')->orderByDesc('start_time')
            ->get()
            ->map(fn($t) => array_merge($this->adminItemFromTask($t), [
                '_sort_ts' => $t->start_date ? strtotime($t->start_date) : 0,
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

        return [
            'data'         => $data,
            'current_page' => $page,
            'last_page'    => $lastPage,
            'total'        => $total,
            'per_page'     => $perPage,
        ];
    }

    public function showForAdmin(int $id): ?array
    {
        $ot = TalentoWorkOrder::with(['colaborador.user', 'type', 'assignedBy', 'validatedBy', 'workActivities.recordedBy'])
            ->find($id);
        if ($ot) {
            return $ot->toArray();
        }

        $task = Task::with(['talentoType', 'users'])
            ->where('tipo', 'campo')
            ->whereNotNull('talento_type_id')
            ->find($id);
        if ($task) {
            $item = $this->adminItemFromTask($task);
            $item['_source'] = 'task';
            return $item;
        }

        return null;
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
            return ['tier' => null];
        }

        $valor  = (float)$ultimoDbm;
        $umbral = DB::table('talento_dbm_thresholds')
            ->where(function ($q) use ($valor) {
                $q->whereNull('dbm_min')->orWhere('dbm_min', '<=', $valor);
            })
            ->where(function ($q) use ($valor) {
                $q->whereNull('dbm_max')->orWhere('dbm_max', '>=', $valor);
            })
            ->orderBy('sort_order')
            ->first();

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

    private function calcularSaludRed(float $potencia): array
    {
        $maxLoss    = (float)(DB::table('settings')->where('key', 'talento_health_bonus_max_loss_db')->value('value') ?? 1.0);
        $bonusMonto = (float)(DB::table('settings')->where('key', 'talento_health_bonus_amount')->value('value') ?? 30);
        $perdida    = abs($potencia);
        $bono       = $perdida <= $maxLoss ? $bonusMonto : 0;

        return [
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
