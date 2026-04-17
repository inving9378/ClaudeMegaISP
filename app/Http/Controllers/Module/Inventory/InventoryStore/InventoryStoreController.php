<?php

namespace App\Http\Controllers\Module\Inventory\InventoryStore;


use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\inventory\inventorystore\InventoryStoreDatatableHelper;
use App\Http\Requests\module\inventory\inventory_store\InventoryStoreCreateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InventoryStoreController extends Controller
{
    protected $helper;
    protected $crudValidationRequest;
    public function __construct(InventoryStoreDatatableHelper $helper)
    {
        $this->crudValidationRequest = new InventoryStoreCreateRequest();
        $this->helper = $helper;

        $this->data['model'] = 'App\Models\InventoryStore';
        $this->data['url'] = 'meganet.module.inventory.inventory_store';
        $this->data['module'] = 'InventoryStore';

        $this->includeLibraryDinamic(Str::after($this->data['model'], "App\\Models\\"));
    }

    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        return view($this->data['url'] . '.listar', $this->data);
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
        return  $this->data['model']::findOrFail($id)->delete();
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }

    public function myStore($id)
    {
        $store = $this->data['model']::find($id);
        if (!$store) {
            return view('meganet.pages.404');
        }
        $responsableId = $store->user_id;
        if (auth()->user()->id == $responsableId || auth()->user()->isAdmin()) {
            $this->data['notifications'] = $this->userNotification();
            $this->data['id'] = $id;
            return view($this->data['url'] . '.my_store', $this->data);
        } else {
            return view('meganet.pages.403');
        }
    }

    public function getById($id)
    {
        try {
            $store = $this->data['model']::find($id);
            if (!$store) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontro el almacen',
                ], 404);
            }
            return response()->json([
                'success' => true,
                'message' => 'Los datos se ha obtenido con éxito.',
                'model' => $store
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud',
            ], 500);
        }
    }

    public function getAll()
    {
        return $this->data['model']::all();
    }
}
