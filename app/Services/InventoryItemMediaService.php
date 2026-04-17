<?php

namespace App\Services;

use App\Http\Repository\InventoryItemMediaRepository;

class InventoryItemMediaService
{
    public function createMediaToItem($id, $properties)
    {
        $data = [
            'inventory_item_id' => $id,
            'url' => $properties['path'],
            'name' => $properties['name'],
            'type' => $properties['type'],
            'size' => $properties['size'],
        ];
        return (new InventoryItemMediaRepository())->create($data);
    }

    public function createMediaToItemStock($id, $properties)
    {
        $data = [
            'inventory_item_stock_id' => $id,
            'url' => $properties['path'],
            'name' => $properties['name'],
            'type' => $properties['type'],
            'size' => $properties['size'],
        ];
        return (new InventoryItemMediaRepository())->create($data);
    }

    public function changeMediaToItemForItemStock($inventoryItemId, $inventoryStockId)
    {
        $mediaItem = (new InventoryItemMediaRepository())->getMediaByItem($inventoryItemId);
        foreach ($mediaItem as $item) {
            $item->update(['inventory_item_stock_id' => $inventoryStockId]);
            $item->update(['inventory_item_id' => null]);
            $item->save();
        }
    }
}
