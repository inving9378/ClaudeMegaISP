<?php

namespace App\Http\Controllers\Module\Inventory\InventoryItemStock;


use App\Http\Controllers\Base\CrudModalController;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\HelpersModule\module\inventory\inventoryitemstock\InventoryItemStockDatatableHelper;
use App\Http\Repository\InventoryItemMediaRepository;
use App\Http\Repository\InventoryItemStockRepository;
use App\Http\Repository\InventoryMovementRepository;
use App\Http\Repository\ModuleRepository;
use App\Http\Requests\module\inventory\inventory_item_stock\InventoryItemStockCreateRequest;
use App\Models\InventoryItemMedia;
use App\Models\InventoryReservation;
use App\Services\FileUploadService;
use App\Services\InventoryItemMediaService;
use App\Services\InventoryService;
use App\Services\LogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InventoryItemStockController extends CrudModalController
{
    public function __construct(InventoryItemStockDatatableHelper $helper)
    {
        parent::__construct($helper, new InventoryItemStockCreateRequest());
        $this->data['model'] = 'App\Models\InventoryItemStock';
        $this->data['url'] = 'meganet.module.inventory.inventory_item_stock';
        $this->data['module'] = 'InventoryItemStock';
        $this->data['module_id'] = $this->getModuleId();
    }
    public function getModuleId()
    {
        return (new ModuleRepository())->getModuleByName('InventoryItemStock')->id;
    }

    public function changeStock(Request $request)
    {
        $model = $this->data['model']::find($request->id_item);
        $currentStock = $model->current_stock;
        $inventoryItemId = $model->inventory_item_id;
        $quantity = $request->quantity_change_stock;
        $modelableTo = $model->modelable_type;
        $idTo = $model->modelable_id;
        $modelableFrom = "App\Models\User";
        $idFrom = auth()->user()->id;
        $typeChange = $request->type_change_stock;
        $validator = Validator::make($request->all(), [
            'type_change_stock' => 'required',
            'id_item' => 'required',
            'quantity_change_stock' => [
                'required',
                function ($attribute, $value, $fail) use ($currentStock, $typeChange) {
                    if ($value > $currentStock && $typeChange == 'decrement') {
                        $fail("No hay suficiente stock. Solo hay {$currentStock} unidades disponibles.");
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        try {
            $inventoryService = new InventoryService();
            if ($request->type_change_stock == 'increment') {
                $inventoryService->addMovementInventoryItemByType($inventoryItemId, $quantity, ComunConstantsController::INVENTORY_MOVEMENT_TYPE_ENTRADA, 'Ajuste Stock Aumento por ' . auth()->user()->name, $idTo, $modelableTo, $idFrom, $modelableFrom, null, false, 'accepted');
            }

            if ($request->type_change_stock == 'decrement') {
                $inventoryService->addMovementInventoryItemByType($inventoryItemId, $quantity, ComunConstantsController::INVENTORY_MOVEMENT_TYPE_SALIDA, 'Ajuste Stock Disminucion por ' . auth()->user()->name, $idTo, $modelableTo, $idFrom, $modelableFrom, null, false, 'accepted');
            }

            $model->save();

            $inventoryService->updateInventoryStock($inventoryItemId, $quantity, $typeChange === 'increment' ? ComunConstantsController::INVENTORY_MOVEMENT_TYPE_ENTRADA : ComunConstantsController::INVENTORY_MOVEMENT_TYPE_SALIDA, $idTo, $modelableTo, $idTo, $modelableTo);

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

    public function getItemsByUser($id, Request $request)
    {
        $inventoryService = new InventoryService();
        try {
            $tab = $request->tab;
            if ($tab != null) {
                $itemsPending = [];
                $itemsAccepted = [];
                $lastActions = [];
                if ($tab == 'pending') {
                    $itemsPending = $inventoryService->getItemsPendingByUser($id);
                }
                if ($tab == 'accepted') {
                    $itemsAccepted = $inventoryService->getItemsAcceptedByUser($id);
                }
                if ($tab == 'last_actions') {
                    $lastActions = $inventoryService->getLastActionsByUser($id);
                }
                return response()->json([
                    'success' => true,
                    'message' => 'Los datos se han cargado con éxito.',
                    'pending' => $itemsPending,
                    'accepted' => $itemsAccepted,
                    'last_actions' => $lastActions
                ], 200);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Ocurrio un error al procesar la solicitud',
            ], 500);
        }
    }

    public function getItemsByClient($id)
    {
        $inventoryService = new InventoryService();
        try {
            $itemsAccepted = $inventoryService->getItemsAcceptedByClient($id);
            return response()->json([
                'success' => true,
                'message' => 'Los datos se han cargado con éxito.',
                'accepted' => $itemsAccepted
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Ocurrio un error al procesar la solicitud',
            ], 500);
        }
    }

    public function getItemsByStore($id)
    {
        $inventoryService = new InventoryService();
        try {
            $itemsPending = $inventoryService->getItemsPendingByStore($id);
            $itemsAccepted = $inventoryService->getItemsAcceptedByStore($id);
            return response()->json([
                'success' => true,
                'message' => 'Los datos se han cargado con éxito.',
                'pending' => $itemsPending,
                'accepted' => $itemsAccepted
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Ocurrio un error al procesar la solicitud',
            ], 500);
        }
    }

    public function acceptItemByMovement($id)
    {
        $inventoryMovementRepository = new InventoryMovementRepository();
        $model = $inventoryMovementRepository->getModelById($id);
        $this->validateAcceptMovement($model);
        try {
            DB::beginTransaction();
            if ($model) {
                $inventoryService = new InventoryService();
                // Validar que el movimiento esté pendiente
                if ($model->status !== ComunConstantsController::INVENTORY_MOVEMENT_PENDING) {
                    throw new \Exception("El movimiento ya fue procesado.");
                }
                $inventoryService->transferStock(
                    $model->inventory_item_id,
                    $model->quantity,
                    $model->movementable_from_id,
                    $model->movementable_from_type,
                    $model->movementable_to_id,
                    $model->movementable_to_type
                );

                // Liberar reserva (eliminar registro)
                $inventoryService->releaseReservation($model->id);
                // Actualizar estado
                $model->update(['status' => ComunConstantsController::INVENTORY_MOVEMENT_ACCEPTED]);
                DB::commit();
                return response()->json(['success' => true]);
            }
            DB::rollBack();
            return response()->json([
                'success' => true,
                'message' => 'Los datos se han aceptado con éxito.'
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::info($th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ocurrio un error al procesar la solicitud',
            ], 500);
        }
    }

    public function validateAcceptMovement($model)
    {
        if (!$this->isInitialCharge($model)) {
            $inventoryItemStockRepository = new InventoryItemStockRepository();
            $inventoryItemStock = $inventoryItemStockRepository->getModelByItemModelableTypeAndModelableId(
                $model->inventory_item_id,
                $model->movementable_from_type,
                $model->movementable_from_id
            );
            $reservedQty = InventoryReservation::where('inventory_item_id', $model->inventory_item_id)
                ->where('modelable_id', $model->movementable_from_id)
                ->where('modelable_type', $model->movementable_from_type)
                ->sum('quantity');

            $currentStock = $inventoryItemStock ? $inventoryItemStock->current_stock : 0;
            $available = $currentStock - $reservedQty;
            //falla cuando $currentStock es menor que $model->quantity
            if ($available < $model->quantity && $model->type === ComunConstantsController::INVENTORY_MOVEMENT_TYPE_SALIDA) {
                throw ValidationException::withMessages([
                    'quantity' => "No hay suficiente stock. Solo hay {$currentStock} unidades disponibles. Por Favor contacte con el Responsable de Almacén o La persona que le asigno estos articulos",
                ]);
            }
        }
    }

    public function isInitialCharge($model)
    {
        return $model->description == "Ingreso Inicial";
    }

    public function rejectItemByMovement(Request $request, $id)
    {
        $inventoryMovementRepository = new InventoryMovementRepository();
        $model = $inventoryMovementRepository->getModelById($id);
        if (!$model) {
            return response()->json([
                'success' => false,
                'message' => 'Ocurrio un error al procesar la solicitud',
            ], 500);
        }
        try {
            if ($model->status !== ComunConstantsController::INVENTORY_MOVEMENT_PENDING) {
                throw new \Exception("El movimiento ya fue procesado.");
            }
            $inventoryService = new InventoryService();
            $inventoryService->releaseReservation($model->id);
            $model->update([
                'status' => ComunConstantsController::INVENTORY_MOVEMENT_REJECTED,
                'description' => $request->reason
            ]);
            $logService = new LogService();
            $logService->log($model, 'Articulo Rechazado por la siguiente razon: ' . $request->reason);
            return response()->json([
                'success' => true,
                'message' => 'Los articulos fueron rechazados con éxito.'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Ocurrio un error al procesar la solicitud',
            ], 500);
        }
    }

    public function getMedia($id)
    {

        try {
            $model = $this->data['model']::find($id);
            if (!$model) {
                return response()->json([
                    'success' => false,
                    'message' => 'Articulo no Encontrado',
                ], 404);
            }

            $inventoryItemMediaRepository = new InventoryItemMediaRepository();
            $media = $inventoryItemMediaRepository->getMediaByItemStock($id);


            return response()->json([
                'success' => true,
                'message' => 'Los datos se han cargado con éxito.',
                'media' => $media
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Ocurrio un error al procesar la solicitud',
            ], 500);
        }
    }

    public function uploadMedia(Request $request)
    {
        $request->validate([
            'item_id' => 'required|exists:inventory_item_stocks,id',
            'files.*' => 'required|image|mimes:jpeg,png,gif|max:5120' // 5MB
        ]);

        $item = $this->data['model']::find($request->item_id);
        $uploadedFiles = [];
        $fileUploadService = new FileUploadService();
        $inventoryMediaService = new InventoryItemMediaService();

        foreach ($request->file('files') as $file) {
            $directory = 'inventory_item_stock/' . $item->id . '/document';

            $properties = $fileUploadService->uploadImageAndReturnProperties($file, $directory);

            if ($properties === false) {
                throw new \Exception("Failed to upload image");
            }

            $media = $inventoryMediaService->createMediaToItemStock($item->id, $properties);

            $uploadedFiles[] = $media;
        }

        return response()->json([
            'success' => true,
            'message' => 'Archivos subidos correctamente',
            'files' => $uploadedFiles
        ]);
    }

    public function deleteMedia($mediaId)
    {
        try {
            $media = InventoryItemMedia::findOrFail($mediaId);
            $fileUploadService = new FileUploadService();

            // Convertir la URL a ruta relativa (ajusta según tu estructura)
            $relativePath = str_replace(
                ['/storage/uploads/', '/storage/'],
                ['public/uploads/', 'public/'],
                $media->url
            );

            // Eliminar archivo físico
            if (Storage::exists($relativePath)) {
                Storage::delete($relativePath);
            }

            // Eliminar registro de la base de datos
            $media->delete();

            return response()->json([
                'success' => true,
                'message' => 'Archivo eliminado correctamente'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'El recurso multimedia no fue encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }
}
