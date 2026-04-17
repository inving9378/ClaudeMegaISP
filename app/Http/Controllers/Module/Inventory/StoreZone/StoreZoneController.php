<?php

namespace App\Http\Controllers\Module\Inventory\StoreZone;


use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\inventory\storezone\StoreZoneDatatableHelper;
use App\Http\Requests\module\inventory\store_zone\StoreZoneCreateRequest;
use App\Models\InventoryItemStoreZone;
use App\Models\InventoryStore;
use App\Models\StoreZone;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StoreZoneController extends Controller
{
    protected $helper;
    protected $crudValidationRequest;

    public function __construct(StoreZoneDatatableHelper $helper)
    {
        $this->crudValidationRequest = new StoreZoneCreateRequest();
        $this->helper = $helper;

        $this->data['model'] = 'App\Models\StoreZone';
        $this->data['url'] = 'meganet.module.inventory.store_zone';
        $this->data['module'] = 'StoreZone';

        $this->includeLibraryDinamic(Str::after($this->data['model'], "App\\Models\\"));
    }

    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        return view($this->data['url'] . '.listar', $this->data);
    }

    public function showZonesByStore($id)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->data['persistentFilters'] = $this->getPersistentFilters($id);
        return view($this->data['url'] . '.listar', $this->data);
    }

    public function getPersistentFilters($id)
    {
        $filters['store_id'] = [
            $id
        ];

        return $filters; // Devuelve un array directamente
    }


    public function store(Request $request)
    {
        $this->validate($request, $this->crudValidationRequest->storeRules(), $this->crudValidationRequest->storeMessageRules());
        try {
            $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
            $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
                $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
                $request->all();

            $model = $this->data['model']::create($input);
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

    public function update(Request $request, $id)
    {
        $this->validate($request, $this->crudValidationRequest->updateRules(), $this->crudValidationRequest->updateMessageRules());
        try {
            $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
            $model = $this->data['model']::find($id);
            $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
                $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
                $request->all();
            $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');
            $model = $model->update($input);
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

    public function destroy($id)
    {
        return $this->data['model']::findOrFail($id)->delete();
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }


    public function getStoreZonesByStore($id)
    {
        return $this->data['model']::where('store_id', $id)->get();
    }

    public function search(Request $request)
    {
        $perPage = (int)($request->per_page ?? 50);

        $zones = StoreZone::where(
            'store_id',
            $request->inventory_store_id
        )
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'data' => $zones->items(),
            'pagination' => [
                'current_page' => $zones->currentPage(),
                'has_more' => $zones->hasMorePages(),
            ],
        ]);
    }

    public function getById($id)
    {
        return $this->data['model']::findOrFail($id);
    }

    public function updateZone(Request $request)
    {
        try {
            DB::beginTransaction();
            $quantity = 0;
            $store = InventoryStore::find($request->inventory_store_id);
            if (!$store){
                throw new \Exception("Almacen no encontrado");
            }
            if ($request->filled('zone_old') && $request->zone_old != $request->store_zone_id) {
                $model = InventoryItemStoreZone::where('inventory_item_id', $request->item_id)
                    ->where('store_zone_id', $request->zone_old)
                    ->where('inventory_store_id', $request->inventory_store_id)
                    ->first();
                if ($model) {
                    $quantity = $model->quantity;
                }

                $to = get_class($store);
                $inventoryService = new InventoryService();
                $inventoryService->updateQuantityInventoryStoreByZoneEntrada($request->item_id, $quantity, $request->inventory_store_id, $to, $request->store_zone_id);
                $model->delete();
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Los datos se ha actualizado con éxito.',
                ], 200);
            } else {
                $to = get_class($store);
                $inventoryService = new InventoryService();
                $inventoryService->updateQuantityInventoryStoreByZoneEntrada($request->item_id, $quantity, $request->inventory_store_id, $to, $request->store_zone_id);
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Los datos se ha actualizado con éxito.',
                ], 200);
            }


        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud',
            ], 500);
        }
    }


}
