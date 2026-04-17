<?php

namespace App\Http\Controllers\Module\Inventory\InventoryItem;


use App\Http\Controllers\Base\CrudModalController;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\HelpersModule\module\inventory\inventoryitem\InventoryItemDatatableHelper;
use App\Http\Repository\ClientRepository;
use App\Http\Repository\InventoryStoreRepository;
use App\Http\Repository\ModuleRepository;
use App\Http\Repository\UserRepository;
use App\Http\Requests\module\inventory\inventory_item\InventoryItemCreateRequest;
use App\Models\InventoryItemCustomModel;
use App\Services\FileUploadService;
use App\Services\InventoryItemMediaService;
use App\Services\InventoryService;
use App\Services\LogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InventoryItemController extends CrudModalController
{
    public function __construct(InventoryItemDatatableHelper $helper)
    {
        parent::__construct($helper, new InventoryItemCreateRequest());
        $this->data['model'] = 'App\Models\InventoryItem';
        $this->data['url'] = 'meganet.module.inventory.inventory_item';
        $this->data['module'] = 'InventoryItem';
        $this->data['module_id'] = $this->getModuleId();
    }

    public function getModuleId()
    {
        return (new ModuleRepository())->getModuleByName('InventoryItem')->id;
    }

    public function store(Request $request)
    {
        $this->validate($request, $this->crudValidationRequest->storeRules(), $this->crudValidationRequest->storeMessageRules());
        try {
            $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
            $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
                $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
                $request->all();

            $input['status_item'] = $request->status_item ?? ComunConstantsController::STATUS_ITEM_NEW;

            unset($input['inventory_store_id']);
            unset($input['store_zone_id']);
            $model = $this->data['model']::create($input);
            if ($request->image) {
                $this->saveMedia($model->id, $request);
            }
            $inventoryService = new InventoryService();
            $inventoryStore = (new InventoryStoreRepository())->getModelById($request->inventory_store_id);
            $to = get_class($inventoryStore);
            $from = 'App\Models\User';
            $fromId = auth()->user()->id;
            $storeZoneId = $request->store_zone_id;

            $inventoryService->addMovementInventoryItemByType($model->id, $request->initial_stock, ComunConstantsController::INVENTORY_MOVEMENT_TYPE_ENTRADA, 'Ingreso Inicial', $inventoryStore->id, $to, $fromId, $from, $storeZoneId, true);
            $inventoryService->updateQuantityInventoryStoreByZoneEntrada($model->id, $request->initial_stock, $inventoryStore->id, $to, $storeZoneId, true);
            $this->saveRelationMultipleIfExist($this->data['model'], $model, $request);

            return response()->json([
                'success' => true,
                'message' => 'Los datos se ha guardado con éxito.',
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

    public function storeCustom(Request $request)
    {
        try {
            $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
                $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
                $request->all();
            $input['status_item'] = $request->status_item ?? ComunConstantsController::STATUS_ITEM_NEW;
            unset($input['inventory_store_id']);
            unset($input['store_zone_id']);

            $serialsNumber = $request->serial_number;
            $serialsNumber = explode(',', $serialsNumber);
            $input['serial_number_enable'] = true;
            $input['status_item_enable'] = true;
            $inventoryItemCustomModel = InventoryItemCustomModel::find($request->inventory_item_custom_model_id);
            $input['inventory_item_type_id'] = $inventoryItemCustomModel->inventory_item_type->id;
            foreach ($serialsNumber as $serial) {
                $input['serial_number'] = $serial;
                $model = $this->data['model']::create($input);
                if ($request->image) {
                    $this->saveMedia($model->id, $request);
                }
                $inventoryService = new InventoryService();
                $inventoryStore = (new InventoryStoreRepository())->getModelById($request->inventory_store_id);
                $to = get_class($inventoryStore);
                $from = 'App\Models\User';
                $fromId = auth()->user()->id;
                $storeZoneId = $request->store_zone_id;
                $inventoryService->addMovementInventoryItemByType($model->id, 1, ComunConstantsController::INVENTORY_MOVEMENT_TYPE_ENTRADA, 'Ingreso Inicial', $inventoryStore->id, $to, $fromId, $from, $storeZoneId, true);
                $inventoryService->updateQuantityInventoryStoreByZoneEntrada($model->id, 1, $inventoryStore->id, $to, $storeZoneId, true);
                $this->saveRelationMultipleIfExist($this->data['model'], $model, $request);
            }


            return response()->json([
                'success' => true,
                'message' => 'Los datos se ha guardado con éxito.'
            ], 200);
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, $this->crudValidationRequest->updateRules(), $this->crudValidationRequest->updateMessageRules());
        try {
            $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
            $model = $this->data['model']::find($id);
            $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
                $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
                $request->except('initial_stock');
            $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');
            $logService = new LogService();
            $logService->log($model, 'Se ha actualizado el articulo');
            unset($input['inventory_store_id']);
            unset($input['store_zone_id']);

            $inventoryService = new InventoryService();
            $inventoryStore = (new InventoryStoreRepository())->getModelById($request->inventory_store_id);
            if ($inventoryStore) {
                $to = get_class($inventoryStore);
                $storeZoneId = $request->store_zone_id;
                $inventoryService->updateQuantityInventoryStoreByZoneEntrada($model->id, $request->initial_stock, $inventoryStore->id, $to, $storeZoneId, false, false);
            }

            $model->update($input);
            return response()->json([
                'success' => true,
                'message' => 'Los datos se ha actualizado con éxito.',
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


    public function saveMedia($inventoryItemId, $request)
    {
        $directory = 'inventory_item/' . $inventoryItemId . '/document';
        $fileUploadsService = new FileUploadService();
        $properties = $fileUploadsService->uploadImageAndReturnProperties($request->image, $directory);
        $inventoryItemMediaService = new InventoryItemMediaService();
        $inventoryItemMediaService->createMediaToItem($inventoryItemId, $properties);
    }


    public function addMovement(Request $request)
    {
        $mapFields = [
            "store_to_store_enable" => [
                "from" => "store_from",
                "to" => "store_to"
            ],
            "store_to_user_enable" => [
                "from" => "store_from",
                "to" => "user_to"
            ],
            "store_to_client_enable" => [
                "from" => "store_from",
                "to" => "client_to"
            ],
            "user_to_user_enable" => [
                "from" => "user_from",
                "to" => "user_to"
            ],
            "user_to_store_enable" => [
                "from" => "user_from",
                "to" => "store_to"
            ],
            "user_to_client_enable" => [
                "from" => "user_from",
                "to" => "client_to"
            ],
            "client_to_client_enable" => [
                "from" => "client_from",
                "to" => "client_to"
            ],
            "client_to_store_enable" => [
                "from" => "client_from",
                "to" => "store_to"
            ],
            "client_to_user_enable" => [
                "from" => "client_from",
                "to" => "user_to"
            ],
        ];

        $data = $this->validateMovement($request, $mapFields);
        $quantity = $data['quantity'];
        $inventoryItemId = $data['inventoryItemId'];
        $idFrom = $data['idFrom'];
        $idTo = $data['idTo'];
        $modelableFrom = $data['modelableFrom'];
        $modelableTo = $data['modelableTo'];

        $inventoryService = new InventoryService();
        return DB::transaction(function () use (
            $inventoryService,
            $inventoryItemId,
            $quantity,
            $idTo,
            $modelableTo,
            $idFrom,
            $modelableFrom
        ) {
            try {
                $status = ComunConstantsController::INVENTORY_MOVEMENT_PENDING;
                if ($modelableTo === 'App\Models\Client') {
                    $status = ComunConstantsController::INVENTORY_MOVEMENT_ACCEPTED;
                }
                if ($status === ComunConstantsController::INVENTORY_MOVEMENT_ACCEPTED) {
                    //salida
                    $movement = $inventoryService->addMovementInventoryItemByType(
                        $inventoryItemId,
                        $quantity,
                        ComunConstantsController::INVENTORY_MOVEMENT_TYPE_SALIDA,
                        'Salida',
                        $idTo,
                        $modelableTo,
                        $idFrom,
                        $modelableFrom,
                        null,
                        false,
                        $status
                    );
                    $inventoryService->transferStock(
                        $inventoryItemId,
                        $quantity,
                        $idFrom,
                        $modelableFrom,
                        $idTo,
                        $modelableTo
                    );
                } else {
                    //entrada
                    $movement = $inventoryService->addMovementInventoryItemByType(
                        $inventoryItemId,
                        $quantity,
                        ComunConstantsController::INVENTORY_MOVEMENT_TYPE_ENTRADA,
                        'Entrada',
                        $idTo,
                        $modelableTo,
                        $idFrom,
                        $modelableFrom,
                        null,
                        false,
                        $status
                    );
                    // Para usuarios: reservar (sin modificar stock aún)
                    $inventoryService->reserveStock(
                        $inventoryItemId,
                        $quantity,
                        $idFrom,
                        $modelableFrom,
                        $movement->id
                    );
                }
                $model = $this->data['model']::find($inventoryItemId);

                return response()->json([
                    'success' => true,
                    'message' => 'Los datos se ha actualizado con éxito.',
                    'model' => $model
                ]);
            } catch (\Exception $e) {
                // La transacción se revertirá automáticamente
                Log::error($e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Ocurrio un error al procesar la solicitud',
                    'error' => $e->getMessage() // Opcional: solo para desarrollo
                ], 500);
            }
        });
    }


    public function validateMovement($request, $mapFields)
    {
        $rules = [];
        $keyCountTrue = 0;
        $idTo = null;
        $idFrom = null;
        $typeFrom = null;
        $typeTo = null;
        $inventoryStockId = $request->id_item;

        // 1. DETERMINAR LA CANTIDAD REAL
        // Si existe custom_model_id, forzamos a 1, de lo contrario usamos el valor del request
        $quantity = $request->filled('inventory_item_custom_model_id') ? 1 : $request->quantity;

        // Validar que solo un movimiento esté habilitado
        foreach ($mapFields as $key => $value) {
            if ($request->$key == true) {
                $from = $value['from'];
                $to = $value['to'];
                $keyCountTrue++;
                $rules[$from] = 'required';
                $rules[$to] = 'required';
                $rules[$key] = [
                    'required',
                    function ($attribute, $value, $fail) use ($keyCountTrue) {
                        if ($keyCountTrue > 1) {
                            $fail("No puede seleccionar más de un tipo de movimiento.");
                        }
                    },
                ];
                $idFrom = $request->$from;
                $idTo = $request->$to;
                $typeFrom = str_replace('_from', '', $from);
                $typeTo = str_replace('_to', '', $to);
            }
        }

        if ($keyCountTrue == 0) {
            throw ValidationException::withMessages([
                'store_to_store_enable' => 'Debe seleccionar al menos un tipo de movimiento.'
            ]);
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Obtener el modelo de origen y destino
        $fromModel = $this->getModelByType($typeFrom, $idFrom);
        $toModel =  $this->getModelByType($typeTo, $idTo);

        $stockFrom = \App\Models\InventoryItemStock::where('id', $inventoryStockId)
            ->where('modelable_id', $idFrom)
            ->where('modelable_type', get_class($fromModel))
            ->first();

        $reservedQty = $stockFrom ? \App\Models\InventoryReservation::where('inventory_item_id', $stockFrom->inventory_item_id)
            ->where('modelable_id', $idFrom)
            ->where('modelable_type', get_class($fromModel))
            ->sum('quantity') : 0;

        $stockFromDisponible = $stockFrom ? ($stockFrom->current_stock - $reservedQty) : 0;

        // 2. PREPARAR DATOS PARA LA SEGUNDA VALIDACIÓN
        // Combinamos el request con la cantidad calculada para que el validador la vea
        $validationData = array_merge($request->all(), ['quantity' => $quantity]);

        $extraValidations = [
            'quantity' => [
                'required',
                'numeric',
                'min:1',
                function ($attribute, $value, $fail) use ($stockFromDisponible) {
                    if ($value > $stockFromDisponible) {
                        $fail("No hay suficiente stock para realizar la salida (Disponible: $stockFromDisponible).");
                    }
                },
            ],
            'store_to' => function ($attribute, $value, $fail) use ($idFrom, $fromModel) {
                if (get_class($fromModel) === 'App\Models\Store' && (int)$value === (int)$idFrom) {
                    $fail('Los almacenes deben ser diferentes.');
                }
            },
            'store_from' => function ($attribute, $value, $fail) use ($idTo, $toModel) {
                if (get_class($toModel) === 'App\Models\Store' && (int)$value === (int)$idTo) {
                    $fail('Los almacenes deben ser diferentes.');
                }
            },
        ];

        // Importante: Usamos $validationData en lugar de $request->all()
        $validator = Validator::make($validationData, $extraValidations);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return [
            'idFrom' => $idFrom,
            'modelableFrom' => get_class($fromModel),
            'idTo' => $idTo,
            'modelableTo' => get_class($toModel),
            'inventoryItemId' => $stockFrom->inventory_item_id ?? null,
            'quantity' => $quantity
        ];
    }

    private function getModelByType($type, $id)
    {
        switch ($type) {
            case 'user':
                return (new UserRepository())->getUserById($id);
            case 'store':
                return (new InventoryStoreRepository())->getModelById($id);
            case 'client':
                return (new ClientRepository())->getClientById($id);
            default:
                throw new \Exception("Tipo de modelo no reconocido: {$type}");
        }
    }
}
