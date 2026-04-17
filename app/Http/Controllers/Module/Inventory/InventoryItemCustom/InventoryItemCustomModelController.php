<?php

namespace App\Http\Controllers\Module\Inventory\InventoryItemCustom;

use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\inventory\inventoryItemcustom\InventoryItemCustomModelDatatableHelper;
use App\Http\Repository\ModuleRepository;
use App\Http\Requests\module\inventory\inventory_item_custom\InventoryItemCustomModelCreateRequest;

class InventoryItemCustomModelController extends CrudModalController
{
    public function __construct(InventoryItemCustomModelDatatableHelper $helper)
    {
        parent::__construct($helper, new InventoryItemCustomModelCreateRequest());
        $this->data['model'] = 'App\Models\InventoryItemCustomModel';
        $this->data['url'] = 'meganet.module.inventory.inventory_item_custom.inventory_item_custom_model';
        $this->data['module'] = 'InventoryItemCustomModel';
        $this->data['module_id'] = $this->getModuleId();
    }

   public function getModuleId()
    {
        return (new ModuleRepository())->getModuleByName('InventoryItemCustomModel')->id;
    }

    public function destroy($id)
    {
        return  $this->data['model']::findOrFail($id)->delete();
    }

}
