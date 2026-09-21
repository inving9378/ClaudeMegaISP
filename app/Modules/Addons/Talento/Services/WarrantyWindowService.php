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
     *
     * Roadmap interno Fase 11 (Capa 6.1): parámetros desacoplados en vez de
     * exigir un TalentoWorkOrder — así también sirve a las OTs que viven como
     * Task (dual-source). Exactamente uno de $sourceWorkOrderId/$sourceTaskId
     * debe venir con valor (mismo patrón work_order_id/tarea_id ya establecido
     * en las tablas hijas de campo).
     */
    public function refreshWindow(
        int $colaboradorId,
        int $clientId,
        ?int $cajaId,
        ?int $sourceWorkOrderId = null,
        ?int $sourceTaskId = null
    ): TalentoResponsibilityWindow {
        if (! $sourceWorkOrderId && ! $sourceTaskId) {
            throw new \InvalidArgumentException('refreshWindow requiere source_work_order_id o source_task_id.');
        }

        $now = Carbon::now();
        $expires = $now->copy()->addMonths(self::WINDOW_MONTHS);

        // Deactivate previous windows for this colaborador+client
        TalentoResponsibilityWindow::where('colaborador_id', $colaboradorId)
            ->where('client_id', $clientId)
            ->where('active', true)
            ->update(['active' => false]);

        return TalentoResponsibilityWindow::create([
            'colaborador_id'       => $colaboradorId,
            'client_id'            => $clientId,
            'caja_id'              => $cajaId,
            'source_work_order_id' => $sourceWorkOrderId,
            'tarea_id'             => $sourceTaskId,
            'starts_at'            => $now,
            'expires_at'           => $expires,
            'active'               => true,
        ]);
    }

    /** Conveniencia: refresca la ventana a partir de un TalentoWorkOrder ya validado. */
    public function refreshWindowForOrder(TalentoWorkOrder $order): TalentoResponsibilityWindow
    {
        return $this->refreshWindow($order->colaborador_id, $order->client_id, $order->caja_id, $order->id, null);
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
