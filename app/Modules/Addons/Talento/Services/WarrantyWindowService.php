<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoResponsibilityWindow;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WarrantyWindowService
{
    private const WINDOW_MONTHS = 6;

    /**
     * Called when a billable installation or repair order is validated.
     * Creates or refreshes the 6-month responsibility window for this
     * collaborator + client pair.
     */
    public function refreshWindow(TalentoWorkOrder $order): TalentoResponsibilityWindow
    {
        $now = Carbon::now();
        $expires = $now->copy()->addMonths(self::WINDOW_MONTHS);

        // Deactivate previous windows for this colaborador+client
        TalentoResponsibilityWindow::where('colaborador_id', $order->colaborador_id)
            ->where('client_id', $order->client_id)
            ->where('active', true)
            ->update(['active' => false]);

        return TalentoResponsibilityWindow::create([
            'colaborador_id'      => $order->colaborador_id,
            'client_id'           => $order->client_id,
            'caja_id'             => $order->caja_id,
            'source_work_order_id'=> $order->id,
            'starts_at'           => $now,
            'expires_at'          => $expires,
            'active'              => true,
        ]);
    }

    /**
     * When a warranty order is being created for a client, determine the
     * correct work order type based on whether an active responsibility
     * window exists.
     *
     * Returns ['type_id' => int, 'is_billable' => bool, 'window' => ?TalentoResponsibilityWindow]
     */
    public function classifyWarrantyOrder(int $clientId): array
    {
        $window = TalentoResponsibilityWindow::activeFor($clientId)->latest('expires_at')->first();

        if ($window) {
            // Within window → non-billable (technician's responsibility)
            $type = TalentoWorkOrderType::where('name', 'Garantía no pagable')->first();
            return [
                'type_id'     => $type?->id,
                'is_billable' => false,
                'points'      => 0,
                'window'      => $window,
            ];
        }

        // Outside window → billable (Meganet's natural warranty)
        $type = TalentoWorkOrderType::where('name', 'Garantía pagable')->first();
        return [
            'type_id'     => $type?->id,
            'is_billable' => true,
            'points'      => $type?->points ?? 1,
            'window'      => null,
        ];
    }

    /**
     * Supervisor reclassifies a non-billable warranty to billable with evidence.
     */
    public function override(int $workOrderId, int $responsibilityWindowId = null, string $reason = '', string $evidenceRef = null): void
    {
        $order = TalentoWorkOrder::findOrFail($workOrderId);

        $originalTypeName = DB::table('talento_work_order_types')->where('id', $order->type_id)->value('name');
        $billableType = TalentoWorkOrderType::where('name', 'Garantía pagable')->first();

        DB::table('talento_warranty_overrides')->insert([
            'work_order_id'             => $workOrderId,
            'responsibility_window_id'  => $responsibilityWindowId,
            'original_type'             => $originalTypeName ?? 'desconocido',
            'new_type'                  => 'Garantía pagable',
            'reason'                    => $reason,
            'evidence_ref'              => $evidenceRef,
            'overridden_by'             => auth()->id(),
            'created_at'                => now(),
        ]);

        if ($billableType) {
            $order->update([
                'type_id'     => $billableType->id,
                'is_billable' => true,
                'points'      => $billableType->points,
            ]);
        }
    }
}
