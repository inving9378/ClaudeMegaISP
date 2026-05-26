<?php

namespace App\Services;

use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use App\Models\SupplierProductPrice;
use App\Models\SupplierProductPriceRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function createInvoiceWithItems(array $data): SupplierInvoice
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'];

            $invoice = SupplierInvoice::create([
                'supplier_id'        => $data['supplier_id'],
                'supplier_vendor_id' => $data['supplier_vendor_id'] ?? null,
                'invoice_number'     => $data['invoice_number'] ?? null,
                'date'               => $data['date'],
                'status'             => 'pending',
                'notes'              => $data['notes'] ?? null,
            ]);

            $total = 0;

            foreach ($items as $item) {
                $purchaseType = $item['purchase_type'] ?? 'unit';
                $quantity     = (float) $item['quantity'];
                $storePrice   = (float) $item['store_price'];
                $bulkQuantity = $item['bulk_quantity'] ?? null;

                $catalog = SupplierProductPrice::where('supplier_id', $invoice->supplier_id)
                    ->where('inventory_item_id', $item['inventory_item_id'])
                    ->first();

                if ($purchaseType === 'bulk') {
                    if (!$catalog || !$catalog->bulk_min_quantity) {
                        throw ValidationException::withMessages([
                            'items' => 'El producto no tiene precio por volumen configurado en el catálogo.',
                        ]);
                    }
                    if ($quantity < $catalog->bulk_min_quantity) {
                        throw ValidationException::withMessages([
                            'items' => "La cantidad ({$quantity}) es menor a la cantidad mínima por volumen ({$catalog->bulk_min_quantity}).",
                        ]);
                    }
                }

                $lineTotal = round($quantity * $storePrice, 2);

                $invoiceItem = SupplierInvoiceItem::create([
                    'supplier_invoice_id' => $invoice->id,
                    'inventory_item_id'   => $item['inventory_item_id'],
                    'purchase_type'       => $purchaseType,
                    'quantity'            => $quantity,
                    'store_price'         => $storePrice,
                    'total'               => $lineTotal,
                    'bulk_quantity'       => $purchaseType === 'bulk' ? $bulkQuantity : null,
                    'notes'               => $item['notes'] ?? null,
                ]);

                if ($purchaseType === 'other' && $catalog) {
                    $catalog->update(['price' => $storePrice]);
                    $this->recordPriceFromInvoice($catalog->fresh(), $invoice);
                }

                $total += $lineTotal;
            }

            $invoice->update(['total' => round($total, 2)]);

            return $invoice->load('items');
        });
    }

    public function upsertProductPrice(array $data): SupplierProductPrice
    {
        $stockId = $data['inventory_item_stock_id'] ?? null;

        if (!$stockId && !empty($data['inventory_item_id'])) {
            $stock = \App\Models\InventoryItemStock::where('inventory_item_id', $data['inventory_item_id'])
                ->orderBy('id')
                ->first();
            $stockId = $stock?->id;
        }

        return SupplierProductPrice::updateOrCreate(
            [
                'supplier_id'       => $data['supplier_id'],
                'inventory_item_id' => $data['inventory_item_id'],
            ],
            [
                'inventory_item_stock_id' => $stockId,
                'base_price'              => $data['base_price'],
                'bulk_price'              => $data['bulk_price'] ?? null,
                'bulk_min_quantity'       => $data['bulk_min_quantity'] ?? null,
                'is_active'               => $data['is_active'] ?? true,
            ]
        );
    }

    public function getCatalogPrice(int $supplierId, int $inventoryItemId): ?SupplierProductPrice
    {
        return SupplierProductPrice::where('supplier_id', $supplierId)
            ->where('inventory_item_id', $inventoryItemId)
            ->where('is_active', true)
            ->first();
    }

    public function generateInvoiceNumber(): string
    {
        $year      = now()->format('Y');
        $month     = now()->format('m');
        $microtime = substr(str_replace('.', '', microtime(true)), -8);
        $processId = getmypid() % 1000;
        $random    = random_int(1000, 9999);
        $uniqueId  = $processId . $microtime . $random;
        if (strlen($uniqueId) > 12) {
            $uniqueId = substr($uniqueId, -12);
        }
        return "SINV-{$year}{$month}-" . str_pad($uniqueId, 12, '0', STR_PAD_LEFT);
    }

    public function getInventoryValuation(): array
    {
        $stocks = \App\Models\InventoryItemStock::with(['inventory_item', 'modelable'])
            ->whereNotNull('unit_cost')
            ->get();

        $total    = 0;
        $byStore  = [];
        $byUser   = [];
        $byClient = [];

        foreach ($stocks as $stock) {
            $cost = ($stock->unit_cost ?? 0) * $stock->current_stock;
            $total += $cost;

            $type = $stock->modelable_type;

            if ($type === 'App\Models\InventoryStore') {
                $key = $stock->modelable->name ?? "Almacén #{$stock->modelable_id}";
                $byStore[$key] = ($byStore[$key] ?? 0) + $cost;
            } elseif ($type === 'App\Models\User') {
                $key = $stock->modelable->name ?? "Usuario #{$stock->modelable_id}";
                $byUser[$key] = ($byUser[$key] ?? 0) + $cost;
            } elseif ($type === 'App\Models\Client') {
                $key = $stock->modelable->client_main_information->name ?? "Cliente #{$stock->modelable_id}";
                $byClient[$key] = ($byClient[$key] ?? 0) + $cost;
            }
        }

        return [
            'total'     => round($total, 2),
            'by_store'  => $byStore,
            'by_user'   => $byUser,
            'by_client' => $byClient,
        ];
    }

    protected function recordPriceFromInvoice(SupplierProductPrice $price, SupplierInvoice $invoice): void
    {
        SupplierProductPriceRecord::create([
            'supplier_product_price_id' => $price->id,
            'base_price'                => $price->base_price,
            'price'                     => $price->price,
            'bulk_price'                => $price->bulk_price,
            'bulk_min_quantity'         => $price->bulk_min_quantity,
            'source'                    => 'invoice',
            'supplier_invoice_id'       => $invoice->id,
            'changed_by'                => auth()->id(),
        ]);
    }
}
