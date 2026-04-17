<?php

namespace App\Http\Controllers\Module\Inventory\InventoryMovement;


use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\inventory\inventorymovement\InventoryMovementDatatableHelper;
use App\Http\Repository\ModuleRepository;
use App\Http\Requests\module\inventory\inventory_movement\InventoryMovementCreateRequest;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

    public function store(Request $request)
    {
        /* TODO Revisar esta funcion despues */
        return response()->json([
            'success' => false,
            'message' => 'Ocurrió un error al procesar la solicitud',
        ], 500);

        $this->validate($request, $this->crudValidationRequest->storeRules(), $this->crudValidationRequest->storeMessageRules());
        try {
            $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
            $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
                $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
                $request->all();
             $inventoryService = new InventoryService();
            $inventoryService->updateInventoryStock($input['inventory_item_id'], $input['quantity'], $input['type']);
            $model = $this->data['model']::create($input);
            $this->saveRelationMultipleIfExist($this->data['model'], $model, $request);
            return response()->json([
                'success' => true,
                'message' => 'El movimiento se ha realizado con éxito.',
                'model' => $model
            ], 200);
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud',
            ], 500);
        }
    }
}
