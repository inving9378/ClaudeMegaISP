<?php

namespace App\Modules\Addons\Inventario\Controllers\InventoryMovement;


use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\inventory\inventorymovement\InventoryMovementDatatableHelper;
use App\Http\Repository\ModuleRepository;
use App\Http\Requests\module\inventory\inventory_movement\InventoryMovementCreateRequest;

class InventoryMovementController extends CrudModalController
{
    public function __construct(InventoryMovementDatatableHelper $helper)
    {
        parent::__construct($helper, new InventoryMovementCreateRequest());
        $this->data['model'] = 'App\Models\InventoryMovement';
        $this->data['url'] = 'meganet.module.inventory.inventory_movement';
        $this->data['module'] = 'InventoryMovement';
        $this->data['module_id'] = $this->getModuleId();
    }

    public function getModuleId()
    {
        return (new ModuleRepository())->getModuleByName('InventoryMovement')->id;
    }
}
