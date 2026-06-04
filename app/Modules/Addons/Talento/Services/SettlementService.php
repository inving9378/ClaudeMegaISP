<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\InventoryStore;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoFund;
use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Addons\Talento\Models\TalentoLoan;
use App\Modules\Addons\Talento\Models\TalentoSettlement;
use App\Modules\Addons\Talento\Models\TalentoSettlementItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SettlementService
{
    /**
     * Construir (o reconstruir) un borrador de finiquito.
     * Lee el ledger, préstamos, fondos y custodia sin modificar nada aún.
     */
    public function draft(int $colaboradorId, ?string $settlementDate = null): TalentoSettlement
    {
        $colaborador = TalentoColaborador::with('user')->findOrFail($colaboradorId);
        $date        = $settlementDate ? Carbon::parse($settlementDate) : now();

        $existing = TalentoSettlement::where('colaborador_id', $colaboradorId)
            ->where('status', 'draft')
            ->first();

        if ($existing && $existing->status === 'closed') {
            throw new \RuntimeException('El finiquito de este colaborador ya está cerrado.');
        }

        // ── Créditos a favor del colaborador ─────────────────────────────
        // Suma de todos los credits del ledger (salario, sobreproducción, bonos, reversiones…)
        $totalCredits = (float) TalentoLedgerEntry::where('colaborador_id', $colaboradorId)
            ->where('type', 'credit')
            ->whereNotIn('concept', ['fund_return']) // los fund_return los escribiremos en close()
            ->sum('amount');

        // ── Débitos en su contra ──────────────────────────────────────────
        // Todos los débitos ya registrados en el ledger (penalizaciones, fondos, abonos préstamo…)
        $totalDebitsLedger = (float) TalentoLedgerEntry::where('colaborador_id', $colaboradorId)
            ->where('type', 'debit')
            ->sum('amount');

        // Saldo vivo de préstamos activos (balance pendiente que no ha sido descontado aún)
        $activeLoanBalance = (float) TalentoLoan::where('colaborador_id', $colaboradorId)
            ->where('status', 'active')
            ->sum('balance');

        // ── Fondos a devolver (ahorro no gastado) ────────────────────────
        $fundsToReturn = (float) TalentoFund::where('colaborador_id', $colaboradorId)
            ->whereIn('status', ['accumulating', 'ready'])
            ->sum('accumulated');

        // ── Custodia de material ──────────────────────────────────────────
        $custody = $this->getCustody($colaborador->user_id);

        // Estimación de cargos por material (se actualizan en updateItem)
        $materialDebits = (float) ($existing
            ? $existing->items()->where('disposition', '!=', 'returned')->sum('debit_amount')
            : 0);

        $grossCredits  = round($totalCredits + $fundsToReturn, 2);
        $grossDebits   = round($totalDebitsLedger + $activeLoanBalance + $materialDebits, 2);
        $netSettlement = round($grossCredits - $grossDebits, 2);

        $detail = [
            'ledger_credits'       => round($totalCredits, 2),
            'funds_to_return'      => round($fundsToReturn, 2),
            'ledger_debits'        => round($totalDebitsLedger, 2),
            'active_loan_balance'  => round($activeLoanBalance, 2),
            'material_debits'      => round($materialDebits, 2),
            'custody_items_count'  => count($custody),
        ];

        $settlement = TalentoSettlement::updateOrCreate(
            ['colaborador_id' => $colaboradorId, 'status' => 'draft'],
            [
                'settlement_date' => $date->toDateString(),
                'gross_credits'   => $grossCredits,
                'gross_debits'    => $grossDebits,
                'net_settlement'  => $netSettlement,
                'detail'          => $detail,
                'created_by'      => auth()->id(),
            ]
        );

        // Poblar items de custodia si no existen aún
        if ($settlement->items()->count() === 0 && count($custody) > 0) {
            foreach ($custody as $stock) {
                TalentoSettlementItem::create([
                    'settlement_id'     => $settlement->id,
                    'stock_id'          => $stock->stock_id,
                    'inventory_item_id' => $stock->item_id,
                    'item_name'         => $stock->item_name,
                    'unit_cost'         => $stock->unit_cost ?? 0,
                    'current_stock'     => $stock->current_stock,
                    'disposition'       => 'returned', // default optimista
                    'debit_amount'      => 0,
                ]);
            }
        }

        return $settlement->fresh(['items']);
    }

    /**
     * Actualizar disposición de un ítem de material (returned/damaged/missing)
     * y recalcular debit_amount + totales del finiquito.
     */
    public function updateItem(TalentoSettlementItem $item, string $disposition, ?float $debitAmount, ?string $notes): TalentoSettlement
    {
        if ($item->settlement->status === 'closed') {
            throw new \RuntimeException('El finiquito ya está cerrado.');
        }

        $calcDebit = match ($disposition) {
            'returned' => 0.0,
            'damaged'  => $debitAmount ?? round((float)$item->unit_cost * (float)$item->current_stock * 0.5, 2),
            'missing'  => $debitAmount ?? round((float)$item->unit_cost * (float)$item->current_stock, 2),
            default    => 0.0,
        };

        $item->update([
            'disposition'  => $disposition,
            'debit_amount' => $calcDebit,
            'notes'        => $notes,
        ]);

        // Recalcular totales del finiquito
        return $this->recalculate($item->settlement);
    }

    /**
     * Cerrar el finiquito (IRREVERSIBLE).
     * Escribe asientos de cierre en el ledger:
     *   - fund_return (crédito) por fondos no gastados
     *   - Ajusta neto
     * Marca préstamos pendientes como saldados si hay dinero suficiente.
     * Mueve material devuelto de regreso al almacén (inventory_item_stocks).
     */
    public function close(TalentoSettlement $settlement): TalentoSettlement
    {
        if ($settlement->status === 'closed') {
            throw new \RuntimeException('El finiquito ya está cerrado.');
        }

        return DB::transaction(function () use ($settlement) {
            $colaboradorId = $settlement->colaborador_id;
            $today         = now()->toDateString();
            $colaborador   = TalentoColaborador::with('user')->find($colaboradorId);

            // 1. Escribir fund_return credits (fondos de ahorro no gastados)
            $fundsToReturn = TalentoFund::where('colaborador_id', $colaboradorId)
                ->whereIn('status', ['accumulating', 'ready'])
                ->get();

            foreach ($fundsToReturn as $fund) {
                $amount = (float)$fund->accumulated;
                if ($amount <= 0) continue;

                TalentoLedgerEntry::create([
                    'colaborador_id' => $colaboradorId,
                    'type'           => 'credit',
                    'concept'        => 'fund_return',
                    'amount'         => $amount,
                    'reference_type' => TalentoFund::class,
                    'reference_id'   => $fund->id,
                    'period_start'   => $today,
                    'period_end'     => $today,
                    'notes'          => "Devolución fondo {$fund->purpose} en finiquito",
                    'created_by'     => auth()->id(),
                ]);
                $fund->update(['status' => 'spent']); // marcado como aplicado en el finiquito
            }

            // 2. Marcar préstamos activos con saldo pendiente como parte del cálculo
            //    (el descuento ya está en gross_debits como active_loan_balance)
            TalentoLoan::where('colaborador_id', $colaboradorId)
                ->where('status', 'active')
                ->update(['status' => 'paid']); // saldados al cerrar el finiquito

            // 3. Procesar material: devuelto → mover de regreso al almacén
            foreach ($settlement->items as $item) {
                if ($item->disposition === 'returned' && $item->current_stock > 0) {
                    $this->returnStockToWarehouse($item, $colaborador);
                }
            }

            // 4. Recalcular final (ahora fund_return está en el ledger)
            $settlement = $this->recalculate($settlement->fresh());

            $settlement->update([
                'status'    => 'closed',
                'closed_at' => now(),
            ]);

            return $settlement->fresh(['items']);
        });
    }

    // ── Privados ──────────────────────────────────────────────────────────────

    private function getCustody(int $userId): \Illuminate\Support\Collection
    {
        return collect(DB::table('inventory_item_stocks as s')
            ->join('inventory_items as i', 'i.id', '=', 's.inventory_item_id')
            ->leftJoin('inventory_categories as c', 'c.id', '=', 'i.category_id')
            ->where('s.modelable_type', 'App\\Models\\User')
            ->where('s.modelable_id', $userId)
            ->whereNull('s.deleted_at')
            ->where('s.current_stock', '>', 0)
            ->select('s.id as stock_id', 'i.id as item_id', 'i.name as item_name',
                     'i.sku', 's.current_stock', 's.unit_cost', 'c.name as category')
            ->get());
    }

    private function recalculate(TalentoSettlement $settlement): TalentoSettlement
    {
        $colaboradorId = $settlement->colaborador_id;

        $totalCredits = (float) TalentoLedgerEntry::where('colaborador_id', $colaboradorId)
            ->where('type', 'credit')
            ->sum('amount');

        $totalDebitsLedger = (float) TalentoLedgerEntry::where('colaborador_id', $colaboradorId)
            ->where('type', 'debit')
            ->sum('amount');

        $activeLoanBalance = (float) TalentoLoan::where('colaborador_id', $colaboradorId)
            ->where('status', 'active')
            ->sum('balance');

        $materialDebits = (float) $settlement->items()
            ->where('disposition', '!=', 'returned')
            ->sum('debit_amount');

        $grossCredits  = round($totalCredits, 2);
        $grossDebits   = round($totalDebitsLedger + $activeLoanBalance + $materialDebits, 2);
        $netSettlement = round($grossCredits - $grossDebits, 2);

        $settlement->update([
            'gross_credits'  => $grossCredits,
            'gross_debits'   => $grossDebits,
            'net_settlement' => $netSettlement,
            'detail'         => array_merge($settlement->detail ?? [], [
                'ledger_credits'      => $totalCredits,
                'ledger_debits'       => $totalDebitsLedger,
                'active_loan_balance' => $activeLoanBalance,
                'material_debits'     => $materialDebits,
            ]),
        ]);

        return $settlement->fresh(['items']);
    }

    private function returnStockToWarehouse(TalentoSettlementItem $item, TalentoColaborador $colaborador): void
    {
        // Reducir stock del usuario
        DB::table('inventory_item_stocks')
            ->where('id', $item->stock_id)
            ->update(['current_stock' => 0, 'updated_at' => now()]);

        // Registrar movimiento de regreso al almacén
        DB::table('inventory_movements')->insert([
            'inventory_item_id'     => $item->inventory_item_id,
            'type'                  => 'transfer',
            'quantity'              => $item->current_stock,
            'description'           => "Devolución finiquito — {$colaborador->user?->name}",
            'movementable_from_type'=> 'App\\Models\\User',
            'movementable_from_id'  => $colaborador->user_id,
            'movementable_to_type'  => null,
            'movementable_to_id'    => null,
            'status'                => 'completed',
            'created_by'            => auth()->id(),
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }
}
