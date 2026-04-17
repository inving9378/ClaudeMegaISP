<?php

namespace App\Services\Finance\Invoice;

use App\Http\Repository\ClientRepository;
use App\Models\Invoice;
use Carbon\Carbon;

class AvailablePeriodsService
{
    private int $maxPeriods = 1;

    /**
     * Obtener periodos disponibles para un cliente
     *
     * @param int $clientId
     * @return array
     */
    public function getAvailablePeriodsByClient(int $clientId): array
    {
        // 🔹 1. Traer todas las invoices del cliente en una sola query
        $invoices = Invoice::where('client_id', $clientId)
            ->orderBy('period', 'asc')
            ->get()
            ->keyBy('period');

        // 🔹 2. Determinar periodo inicial
        $startPeriod = $this->resolveStartPeriod($invoices);

        // 🔹 3. Construir periodos disponibles
        return $this->buildPeriods($clientId, $startPeriod, $invoices);
    }

    /**
     * Determina el periodo inicial según invoices del cliente
     */
    private function resolveStartPeriod($invoices): Carbon
    {
        if (empty($invoices)) {
            // Cliente sin invoices → usar mes actual
            return Carbon::now()->startOfMonth();
        }
        // Primera invoice NO pagada
        $firstUnpaid = $invoices->firstWhere(
            fn ($invoice) => $invoice->status !== Invoice::STATUS_PAID
        );

        if ($firstUnpaid) {
            return Carbon::createFromFormat('Y-m', $firstUnpaid->period);
        }

        // Última invoice pagada
        $lastPaid = $invoices->where('status', Invoice::STATUS_PAID)->last();

        if ($lastPaid) {
            return Carbon::createFromFormat('Y-m', $lastPaid->period)->addMonth();
        }

        // Cliente sin invoices → usar mes actual
        return Carbon::now()->startOfMonth();
    }

    /**
     * Construye los periodos disponibles
     */
    private function buildPeriods(int $clientId, Carbon $startPeriod, $invoices): array
    {
        $periods = [];

        // 🔹 Obtener costo de servicios solo una vez
        $clientRepository = new ClientRepository();
        $costAllServices = $clientRepository->getCostAllService($clientId);

        for ($i = 0; $i < $this->maxPeriods; $i++) {
            $current = $startPeriod->copy()->addMonths($i);
            $periodKey = $current->format('Y-m');
            $invoice = $invoices[$periodKey] ?? null;

            $periods[] = [
                'period'          => $periodKey,
                'pending_balance' => $invoice ? $invoice->pending_balance : $costAllServices,
                'has_invoice'     => (bool) $invoice,
            ];
        }

        return $periods;
    }
}
