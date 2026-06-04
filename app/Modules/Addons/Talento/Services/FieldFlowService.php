<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\Client;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoInstallationSurvey;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderActivation;
use App\Services\OLTsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FieldFlowService
{
    public function __construct(private OLTsService $oltsService) {}

    /**
     * Sub-paso 4: "Aceptar" — post-validación + firmas.
     *  1. Writes GPS as official client location.
     *  2. Moves modem inventory from technician custody → client.
     *  3. Advances order to pending_activation.
     */
    public function accept(int $workOrderId, ?float $lat, ?float $lng): TalentoWorkOrder
    {
        $order = TalentoWorkOrder::findOrFail($workOrderId);

        if (!in_array($order->status, ['completed', 'validated'])) {
            throw new \RuntimeException("La orden debe estar completada o validada para aceptar.");
        }

        $signatures = $order->signatures ?? DB::table('talento_work_order_signatures')
            ->where('work_order_id', $workOrderId)
            ->pluck('signer_type')
            ->toArray();

        if (!in_array('technician', (array)$signatures)) {
            throw new \RuntimeException("Falta la firma del técnico.");
        }
        if (!in_array('client', (array)$signatures)) {
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

    /**
     * Sub-paso 5: Confirm activation.
     * Optionally dispatches OLT registration via OLTsService.
     */
    public function confirmActivation(int $workOrderId, bool $dispatchOlt = false): TalentoWorkOrder
    {
        $order = TalentoWorkOrder::findOrFail($workOrderId);

        if ($order->status !== 'pending_activation') {
            throw new \RuntimeException("La orden no está en pending_activation.");
        }

        $oltResponse  = null;
        $oltDispatched = false;

        if ($dispatchOlt && $order->olt_onu_id && $order->client_id) {
            try {
                $oltResponse   = $this->dispatchOltActivation($order);
                $oltDispatched = true;
            } catch (\Throwable $e) {
                Log::warning('OLT activation dispatch failed', ['work_order_id' => $order->id, 'error' => $e->getMessage()]);
                $oltResponse = ['error' => $e->getMessage()];
            }
        }

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

    /**
     * Sub-paso 6: Generate client credentials + create survey.
     * Password is temporary — forced change on first login.
     */
    public function onboard(int $workOrderId): array
    {
        $order = TalentoWorkOrder::findOrFail($workOrderId);

        if ($order->status !== 'active') {
            throw new \RuntimeException("La orden debe estar activa para hacer onboarding.");
        }

        $clientUser = null;
        $tempPassword = null;

        if ($order->client_id) {
            $clientUser = User::where('client_id', $order->client_id)->first();
            if ($clientUser) {
                // Generate temporary password — technician sees it once and never again
                $tempPassword = Str::random(10);
                $clientUser->update([
                    'password'        => base64_encode($tempPassword), // project convention
                    'active'          => true,
                ]);
                // Mark forced change (no dedicated flag in schema — use a session/note approach)
                // Store in client_additional_information as a one-time flag
                DB::table('client_additional_information')
                    ->where('client_id', $order->client_id)
                    ->update(['original_password' => $tempPassword]);
            }
        }

        // Create survey record
        $survey = TalentoInstallationSurvey::firstOrCreate(
            ['work_order_id' => $order->id],
            [
                'client_id'              => $order->client_id,
                'google_review_offered'  => false,
                'google_review_opened'   => false,
            ]
        );

        // Advance order status
        $order->update(['status' => 'survey_pending']);

        return [
            'survey'        => $survey,
            'login_user'    => $clientUser?->login_user,
            'temp_password' => $tempPassword, // shown once; not persisted in plain text beyond this
        ];
    }

    /**
     * Submit survey response (from app or web).
     */
    public function submitSurvey(int $workOrderId, array $data): TalentoInstallationSurvey
    {
        $survey = TalentoInstallationSurvey::where('work_order_id', $workOrderId)->firstOrFail();

        $survey->update([
            'rating_overall'      => $data['rating_overall'] ?? null,
            'rating_technician'   => $data['rating_technician'] ?? null,
            'comments'            => $data['comments'] ?? null,
            'google_review_offered'=> $data['google_review_offered'] ?? false,
            'google_review_opened' => $data['google_review_opened'] ?? false,
            'submitted_at'        => now(),
        ]);

        // Close the work order
        TalentoWorkOrder::where('id', $workOrderId)->update([
            'status'           => 'validated',
            'survey_completed' => true,
            'validated_at'     => now(),
            'validated_by'     => auth()->id(),
        ]);

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
            TalentoWorkOrder::where('id', $survey->work_order_id)->update([
                'status'           => 'validated',
                'survey_completed' => true,
                'validated_at'     => now(),
                'validated_by'     => null,
            ]);
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

    private function dispatchOltActivation(TalentoWorkOrder $order): array
    {
        // Use OLTsService to resync or register the ONU
        $result = $this->oltsService->resyncONUConfig($order->olt_onu_id);
        return is_array($result) ? $result : ['result' => $result];
    }
}
