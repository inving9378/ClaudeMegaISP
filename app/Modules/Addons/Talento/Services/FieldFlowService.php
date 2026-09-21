<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoInstallationSurvey;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderActivation;
use App\Modules\Addons\Talento\Support\FieldFlowEntity;
use App\Services\OLTsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Roadmap interno de Talento, Fase 10 (dual-source): los sub-pasos 4 (aceptar),
 * 5 (activar) y 6 (onboarding) ahora resuelven la orden como TalentoWorkOrder O
 * como Task (tipo=campo), mismo criterio que OrdenTrabajoUnifiedService.
 *
 * Las tablas hijas (talento_work_order_activations, talento_installation_surveys)
 * ya tenían `tarea_id` nullable con FK a tasks desde una migración previa (Capa
 * 4.1/4.3) — el hueco real era que los modelos Eloquent no lo declaraban en
 * $fillable y esta capa de servicio nunca lo usaba.
 *
 * Alcance deliberado de la rama task:
 * - Estado del sub-flujo (aceptada/activada/onboarding) se rastrea por la
 *   presencia y timestamps de la fila en talento_work_order_activations /
 *   talento_installation_surveys — NO se empuja a tasks.status. tasks.status es
 *   una tabla COMPARTIDA con otros módulos (Scheduling, CRM…) que solo conocen
 *   ToDo/InProgress/Done/Archivado; escribir ahí valores como "pending_activation"
 *   rompería esos consumidores en silencio.
 * - Custodia de módem (transferModemCustody) requiere `inventory_item_id`
 *   resuelto, que hoy solo existe en talento_work_orders — para tasks se omite,
 *   exactamente igual que ya se omite en la rama work_order cuando ese campo no
 *   está resuelto (mismo guard condicional, no es un caso especial nuevo).
 * - Activación OLT (dispatchOltActivation) SÍ funciona igual para tasks:
 *   solo necesita `olt_onu_id`, que tasks ya tiene como columna propia.
 */
class FieldFlowService
{
    public function __construct(
        private OLTsService $oltsService,
        private SignatureService $signatureService
    ) {}

    /**
     * Resuelve $id como TalentoWorkOrder o Task tipo=campo (dual-source).
     * @return array{origen:string,model:TalentoWorkOrder|Task}
     */
    private function resolveEntity(int $id): array
    {
        $resolved = FieldFlowEntity::resolve($id);
        if (! $resolved) {
            throw new \RuntimeException('Orden de trabajo no encontrada.');
        }
        return $resolved;
    }

    /** Resuelve clients.id a partir de tasks.client_main_information_id. */
    private function clientIdForTask(Task $task): ?int
    {
        return FieldFlowEntity::clientIdForTask($task);
    }

    // ── Sub-paso 4: Aceptar ──────────────────────────────────────────────────

    /**
     * Sub-paso 4: "Aceptar" — post-validación + firmas.
     *  1. Writes GPS as official client location.
     *  2. Moves modem inventory from technician custody → client (solo WO, ver nota de clase).
     *  3. Advances order to pending_activation / crea la fila de activación.
     */
    public function accept(int $id, ?float $lat, ?float $lng): TalentoWorkOrder|Task
    {
        ['origen' => $origen, 'model' => $entity] = $this->resolveEntity($id);

        return $origen === 'work_order'
            ? $this->acceptWorkOrder($entity, $lat, $lng)
            : $this->acceptTask($entity, $lat, $lng);
    }

    private function acceptWorkOrder(TalentoWorkOrder $order, ?float $lat, ?float $lng): TalentoWorkOrder
    {
        if (!in_array($order->status, ['completed', 'validated'])) {
            throw new \RuntimeException("La orden debe estar completada o validada para aceptar.");
        }

        $signatures = $this->signatureService->signerTypesFor('work_order', $order->id);
        if (!in_array('technician', $signatures)) {
            throw new \RuntimeException("Falta la firma del técnico.");
        }
        if (!in_array('client', $signatures)) {
            throw new \RuntimeException("Falta la firma del cliente.");
        }

        return DB::transaction(function () use ($order, $lat, $lng) {
            // 1. Write GPS as official client location
            if ($order->client_id && $lat && $lng) {
                $this->updateClientLocation($order->client_id, $lat, $lng);
            }

            // 2. Modem custody transfer via inventory_movement
            $movementId = null;
            if ($order->modem_sn && $order->inventory_item_id && $order->colaborador && $order->client_id) {
                $movementId = $this->transferModemCustody($order);
            }

            // 3. Advance order status
            $update = [
                'status'        => 'pending_activation',
                'accepted_at'   => now(),
                'accepted_by'   => auth()->id(),
                'activation_requested_at' => now(),
            ];
            if ($movementId) {
                $update['inventory_movement_id'] = $movementId;
            }
            if ($lat && $lng) {
                $update['latitude']  = $lat;
                $update['longitude'] = $lng;
            }
            $order->update($update);

            // Record activation row
            TalentoWorkOrderActivation::create([
                'work_order_id' => $order->id,
                'requested_at'  => now(),
            ]);

            return $order->fresh();
        });
    }

    private function acceptTask(Task $task, ?float $lat, ?float $lng): Task
    {
        if ($task->status !== 'Done') {
            throw new \RuntimeException("La orden debe estar completada o validada para aceptar.");
        }
        if (TalentoWorkOrderActivation::where('tarea_id', $task->id)->exists()) {
            throw new \RuntimeException("Esta orden ya fue aceptada.");
        }

        $signatures = $this->signatureService->signerTypesFor('task', $task->id);
        if (!in_array('technician', $signatures)) {
            throw new \RuntimeException("Falta la firma del técnico.");
        }
        if (!in_array('client', $signatures)) {
            throw new \RuntimeException("Falta la firma del cliente.");
        }

        return DB::transaction(function () use ($task, $lat, $lng) {
            $clientId = $this->clientIdForTask($task);
            if ($clientId && $lat && $lng) {
                $this->updateClientLocation($clientId, $lat, $lng);
            }

            // Custodia de módem omitida para tasks (ver nota de clase — sin
            // inventory_item_id resuelto no hay nada que transferir, mismo
            // guard condicional que ya existe para work_orders).

            TalentoWorkOrderActivation::create([
                'tarea_id'     => $task->id,
                'requested_at' => now(),
            ]);

            return $task->fresh();
        });
    }

    // ── Sub-paso 5: Activación ───────────────────────────────────────────────

    /**
     * Sub-paso 5: Confirm activation.
     * Optionally dispatches OLT registration via OLTsService.
     */
    public function confirmActivation(int $id, bool $dispatchOlt = false): TalentoWorkOrder|Task
    {
        ['origen' => $origen, 'model' => $entity] = $this->resolveEntity($id);

        return $origen === 'work_order'
            ? $this->confirmActivationWorkOrder($entity, $dispatchOlt)
            : $this->confirmActivationTask($entity, $dispatchOlt);
    }

    private function confirmActivationWorkOrder(TalentoWorkOrder $order, bool $dispatchOlt): TalentoWorkOrder
    {
        if ($order->status !== 'pending_activation') {
            throw new \RuntimeException("La orden no está en pending_activation.");
        }

        [$oltDispatched, $oltResponse] = $this->maybeDispatchOlt($dispatchOlt, $order->olt_onu_id, $order->client_id, $order->id);

        DB::transaction(function () use ($order, $oltDispatched, $oltResponse) {
            $order->update([
                'status'                   => 'active',
                'activation_confirmed_at'  => now(),
                'activation_by'            => auth()->id(),
            ]);

            TalentoWorkOrderActivation::where('work_order_id', $order->id)
                ->whereNull('activated_at')
                ->update([
                    'activated_by'   => auth()->id(),
                    'activated_at'   => now(),
                    'olt_dispatched' => $oltDispatched,
                    'olt_response'   => $oltResponse ? json_encode($oltResponse) : null,
                ]);
        });

        return $order->fresh();
    }

    private function confirmActivationTask(Task $task, bool $dispatchOlt): Task
    {
        $activation = TalentoWorkOrderActivation::where('tarea_id', $task->id)
            ->whereNull('activated_at')
            ->first();
        if (! $activation) {
            throw new \RuntimeException("La orden no está pendiente de activación (acéptala primero).");
        }

        $clientId = $this->clientIdForTask($task);
        [$oltDispatched, $oltResponse] = $this->maybeDispatchOlt($dispatchOlt, $task->olt_onu_id, $clientId, $task->id);

        DB::transaction(function () use ($activation, $oltDispatched, $oltResponse) {
            $activation->update([
                'activated_by'   => auth()->id(),
                'activated_at'   => now(),
                'olt_dispatched' => $oltDispatched,
                'olt_response'   => $oltResponse ? json_encode($oltResponse) : null,
            ]);
        });

        return $task->fresh();
    }

    /** @return array{0:bool,1:?array} [olt_dispatched, olt_response] */
    private function maybeDispatchOlt(bool $wanted, ?int $oltOnuId, ?int $clientId, int $entityId): array
    {
        if (! $wanted || ! $oltOnuId || ! $clientId) {
            return [false, null];
        }
        try {
            $response = $this->oltsService->resyncONUConfig($oltOnuId);
            return [true, is_array($response) ? $response : ['result' => $response]];
        } catch (\Throwable $e) {
            Log::warning('OLT activation dispatch failed', ['entity_id' => $entityId, 'error' => $e->getMessage()]);
            return [false, ['error' => $e->getMessage()]];
        }
    }

    // ── Sub-paso 6: Onboarding + encuesta ────────────────────────────────────

    /**
     * Sub-paso 6: Generate client credentials + create survey.
     * Password is temporary — forced change on first login.
     */
    public function onboard(int $id): array
    {
        ['origen' => $origen, 'model' => $entity] = $this->resolveEntity($id);

        return $origen === 'work_order'
            ? $this->onboardWorkOrder($entity)
            : $this->onboardTask($entity);
    }

    private function onboardWorkOrder(TalentoWorkOrder $order): array
    {
        if ($order->status !== 'active') {
            throw new \RuntimeException("La orden debe estar activa para hacer onboarding.");
        }

        [$clientUser, $tempPassword] = $this->generateClientCredentials($order->client_id);

        $survey = TalentoInstallationSurvey::firstOrCreate(
            ['work_order_id' => $order->id],
            [
                'client_id'              => $order->client_id,
                'google_review_offered'  => false,
                'google_review_opened'   => false,
            ]
        );

        $order->update(['status' => 'survey_pending']);

        return [
            'survey'        => $survey,
            'login_user'    => $clientUser?->login_user,
            'temp_password' => $tempPassword,
        ];
    }

    private function onboardTask(Task $task): array
    {
        $activation = TalentoWorkOrderActivation::where('tarea_id', $task->id)
            ->whereNotNull('activated_at')
            ->exists();
        if (! $activation) {
            throw new \RuntimeException("La orden debe estar activa para hacer onboarding.");
        }

        $clientId = $this->clientIdForTask($task);
        [$clientUser, $tempPassword] = $this->generateClientCredentials($clientId);

        $survey = TalentoInstallationSurvey::firstOrCreate(
            ['tarea_id' => $task->id],
            [
                'client_id'              => $clientId,
                'google_review_offered'  => false,
                'google_review_opened'   => false,
            ]
        );

        return [
            'survey'        => $survey,
            'login_user'    => $clientUser?->login_user,
            'temp_password' => $tempPassword,
        ];
    }

    /** @return array{0:?User,1:?string} [clientUser, tempPassword] */
    private function generateClientCredentials(?int $clientId): array
    {
        if (! $clientId) {
            return [null, null];
        }

        $clientUser = User::where('client_id', $clientId)->first();
        if (! $clientUser) {
            return [null, null];
        }

        // Generate temporary password — technician sees it once and never again
        $tempPassword = Str::random(10);
        $clientUser->update([
            'password' => base64_encode($tempPassword), // project convention
            'active'   => true,
        ]);
        DB::table('client_additional_information')
            ->where('client_id', $clientId)
            ->update(['original_password' => $tempPassword]);

        return [$clientUser, $tempPassword];
    }

    /**
     * Submit survey response (from app or web).
     */
    public function submitSurvey(int $id, array $data): TalentoInstallationSurvey
    {
        // work_order_id/tarea_id son secuencias de ids independientes — resolver
        // el origen real primero, nunca un OR ciego (dos ids iguales de tablas
        // distintas mezclarían encuestas de órdenes no relacionadas).
        $fkCol = TalentoWorkOrder::whereKey($id)->exists() ? 'work_order_id' : 'tarea_id';
        $survey = TalentoInstallationSurvey::where($fkCol, $id)->firstOrFail();

        $survey->update([
            'rating_overall'      => $data['rating_overall'] ?? null,
            'rating_technician'   => $data['rating_technician'] ?? null,
            'comments'            => $data['comments'] ?? null,
            'google_review_offered'=> $data['google_review_offered'] ?? false,
            'google_review_opened' => $data['google_review_opened'] ?? false,
            'submitted_at'        => now(),
        ]);

        if ($survey->work_order_id) {
            TalentoWorkOrder::where('id', $survey->work_order_id)->update([
                'status'           => 'validated',
                'survey_completed' => true,
                'validated_at'     => now(),
                'validated_by'     => auth()->id(),
            ]);
        } elseif ($survey->tarea_id) {
            DB::table('tasks')->where('id', $survey->tarea_id)->update([
                'status'       => 'Done',
                'validated_at' => now(),
                'updated_at'   => now(),
                'updated_by'   => auth()->id() ?? 0,
            ]);
        }

        return $survey->fresh();
    }

    /**
     * Auto-close survey after configured days without response.
     * Called by a scheduled command / cron.
     */
    public function autoCloseSurveys(): int
    {
        $days = (int) (DB::table('settings')->where('key', 'talento_survey_autoclose_days')->value('value') ?? 7);
        $cutoff = now()->subDays($days);

        $pending = TalentoInstallationSurvey::whereNull('submitted_at')
            ->where('auto_closed', false)
            ->where('created_at', '<=', $cutoff)
            ->get();

        foreach ($pending as $survey) {
            $survey->update(['auto_closed' => true]);

            if ($survey->work_order_id) {
                TalentoWorkOrder::where('id', $survey->work_order_id)->update([
                    'status'           => 'validated',
                    'survey_completed' => true,
                    'validated_at'     => now(),
                    'validated_by'     => null,
                ]);
            } elseif ($survey->tarea_id) {
                DB::table('tasks')->where('id', $survey->tarea_id)->update([
                    'status'       => 'Done',
                    'validated_at' => now(),
                    'updated_at'   => now(),
                ]);
            }
        }

        return $pending->count();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function updateClientLocation(int $clientId, float $lat, float $lng): void
    {
        DB::table('clients')->where('id', $clientId)->update([
            'updated_at' => now(),
        ]);
        // client_additional_information stores connection info; no lat/lng column.
        // Write coords to the work_order itself (already done in accept()).
        // Geo data for clients is stored via the order / attendance records.
    }

    private function transferModemCustody(TalentoWorkOrder $order): int
    {
        $colaboradorUserId = DB::table('talento_colaboradores')
            ->where('id', $order->colaborador_id)
            ->value('user_id');

        return DB::table('inventory_movements')->insertGetId([
            'inventory_item_id'       => $order->inventory_item_id,
            'type'                    => 'transfer',
            'quantity'                => 1,
            'description'             => "Entrega módem SN:{$order->modem_sn} — OT#{$order->id}",
            'movementable_from_type'  => 'App\\Models\\User',
            'movementable_from_id'    => $colaboradorUserId,
            'movementable_to_type'    => 'App\\Models\\Client',
            'movementable_to_id'      => $order->client_id,
            'status'                  => 'completed',
            'created_by'              => auth()->id(),
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);
    }
}
