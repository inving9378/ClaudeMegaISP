<?php

namespace App\Http\Controllers\Module\Inventory\InventoryItemCustom;

use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\inventory\inventoryItemcustom\InventoryItemCustomDatatableHelper;
use App\Http\Repository\ModuleRepository;
use App\Http\Requests\module\inventory\inventory_item_custom\InventoryItemCustomCreateRequest;
use App\Models\InventoryItem;
use App\Models\InventoryItemCustomModel;
use App\Models\InventoryStore;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Request;

class InventoryItemCustomController extends CrudModalController
{
    public function __construct(InventoryItemCustomDatatableHelper $helper)
    {
        parent::__construct($helper, new InventoryItemCustomCreateRequest());
        $this->data['model'] = 'App\Models\InventoryItemCustom';
        $this->data['url'] = 'meganet.module.inventory.inventory_item_custom';
        $this->data['module'] = 'InventoryItemCustom';
        $this->data['module_id'] = $this->getModuleId();
    }

    public function getItemsByCustomModelId(Request $request, $id)
    {
        $inventoryItemCustomModel = InventoryItemCustomModel::find($id);
        if (!$inventoryItemCustomModel) {
            return view('meganet.pages.404');
        }

        $this->data['persistentFilters'] = $this->getPersistentFilters($id, $request);
        $this->data['notifications'] = $this->userNotification();
        $this->data['custom_model_id'] = $id;
        $this->includeLibraryDinamic(Str::after($this->data['model'], "App\\Models\\"));
        return view($this->data['url'] . '.listar', $this->data);
    }

    public function getPersistentFilters($id, $request)
    {
        $filters['inventory_item_custom_model_id'] = [
            $id
        ];

        $storeId = $request->query('store-id');

        if ($storeId) {
            $store = InventoryStore::find($storeId);
            if ($store) {
                $filters['inventory_store_id'] = [
                    $storeId
                ];
            }
        }

        return $filters;
    }



    public function getModuleId()
    {
        return (new ModuleRepository())->getModuleByName('InventoryItemCustom')->id;
    }

    public function destroy($id)
    {
        return  $this->data['model']::findOrFail($id)->delete();
    }
}
