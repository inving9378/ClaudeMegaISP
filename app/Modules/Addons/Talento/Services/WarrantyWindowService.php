<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoResponsibilityWindow;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $expires = $now->copy()->addMonths($this->windowMonths());

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

    /** Meses de la ventana: configurable con el interruptor L1 encendido; 6 si no. */
    private function windowMonths(): int
    {
        if (! config('unificacion.interruptores.garantia_l1')) {
            return self::WINDOW_MONTHS;
        }
        return max(1, (int) config('unificacion.interruptores.garantia_l1_meses', self::WINDOW_MONTHS));
    }

    /**
     * UNI-T L1 — liga un ticket a la orden original del cliente si hay una ventana vigente. Idempotente
     * (un evento por ticket); sin efecto económico. Devuelve el evento o null si no aplica.
     */
    public function registrarEventoTicket(int $ticketId, int $clientId): ?object
    {
        if (! config('unificacion.interruptores.garantia_l1') || ! Schema::hasTable('talento_warranty_events')) {
            return null;
        }
        if (DB::table('talento_warranty_events')->where('ticket_id', $ticketId)->exists()) {
            return null;
        }
        $window = TalentoResponsibilityWindow::activeFor($clientId)->latest('starts_at')->first();
        if (! $window) {
            return null;
        }
        [$origen, $ordenId] = $window->tarea_id ? ['task', (int) $window->tarea_id] : ['work_order', (int) $window->source_work_order_id];
        $ref = \App\Modules\Addons\Talento\Support\OrdenReferencia::format($origen, $ordenId);
        $participantes = app(OrderParticipantsService::class)->listar($origen, $ordenId);
        $participantes = $participantes ?: [['user_id' => \App\Modules\Addons\Talento\Models\TalentoColaborador::where('id', $window->colaborador_id)->value('user_id'), 'rol' => 'principal']];

        $id = DB::table('talento_warranty_events')->insertGetId([
            'ticket_id' => $ticketId, 'client_id' => $clientId, 'window_id' => $window->id,
            'orden_origen' => $origen, 'orden_id' => $ordenId, 'orden_ref' => $ref,
            'participantes' => json_encode(array_map(fn ($p) => ['user_id' => $p['user_id'], 'rol' => $p['rol'] ?? 'participante'], $participantes)),
            'orden_validada_at' => $window->starts_at, 'ventana_expira_at' => $window->expires_at,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('talento_warranty_events')->find($id);
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
