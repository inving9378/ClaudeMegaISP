<?php

namespace App\Http\Controllers\Module\Inventory\InventoryItemType;


use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\inventory\inventoryitemtype\InventoryItemTypeDatatableHelper;
use App\Http\Requests\module\base\CrudModalValidationRequest;
use App\Http\Requests\module\inventory\inventory_item_type\InventoryItemTypeCreateRequest;

class InventoryItemTypeController extends CrudModalController
{
    public function __construct(InventoryItemTypeDatatableHelper $helper)
    {
        parent::__construct($helper, new InventoryItemTypeCreateRequest());
        $this->data['model'] = 'App\Models\InventoryItemType';
        $this->data['url'] = 'meganet.module.inventory.inventory_item_type';
        $this->data['module'] = 'InventoryItemType';
    }
}
