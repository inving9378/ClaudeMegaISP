<?php

namespace App\Modules\Addons\Inventario\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Repository\ModuleRepository;
use App\Http\HelpersModule\module\inventory\supplier\SupplierDatatableHelper;
use App\Http\Requests\module\inventory\supplier\SupplierCreateRequest;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupplierController extends Controller
{
    protected $helper;
    protected $data;
    protected $crudValidationRequest;

    public function __construct(SupplierDatatableHelper $helper)
    {
        $this->helper = $helper;
        $this->crudValidationRequest = new SupplierCreateRequest();

        $this->data['model']  = 'App\Models\Supplier';
        $this->data['url']    = 'meganet.module.inventory.supplier';
        $this->data['module'] = 'Supplier';

        $this->includeLibraryDinamic(Str::after($this->data['model'], "App\\Models\\"));
    }

    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->data['id'] = $this->getModuleId('Supplier');
        return view($this->data['url'] . '.listar', $this->data);
    }

    public function create()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->data['id'] = $this->getModuleId('Supplier');
        $this->data['action'] = "add";
        return view($this->data['url'] . '.crear', $this->data);
    }

    public function getModuleId($module_name)
    {
        return (new ModuleRepository())->getModuleByName($module_name)->id;
    }

    public function store(Request $request)
    {
        $this->validate($request, $this->crudValidationRequest->storeRules(), $this->crudValidationRequest->storeMessageRules());
        try {
            $supplier = Supplier::create($request->only(['name', 'rfc', 'address', 'phone', 'email', 'status']));
            return response()->json(['success' => true, 'message' => 'Proveedor creado con éxito.', 'model' => $supplier], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ocurrió un error al procesar la solicitud.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, $this->crudValidationRequest->updateRules(), $this->crudValidationRequest->updateMessageRules());
        try {
            $supplier = Supplier::findOrFail($id);
            $supplier->update($request->only(['name', 'rfc', 'address', 'phone', 'email', 'status']));
            return response()->json(['success' => true, 'message' => 'Proveedor actualizado con éxito.', 'model' => $supplier], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ocurrió un error al procesar la solicitud.'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            Supplier::findOrFail($id)->delete();
            return response()->json(['success' => true, 'message' => 'Proveedor eliminado.'], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ocurrió un error al eliminar.'], 500);
        }
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }

    public function show($id)
    {
        $supplier = Supplier::findOrFail($id);
        $this->data['notifications'] = $this->userNotification();
        $this->data['supplier']      = $supplier;
        $this->data['supplier_id'] = $id;
        $this->data['id']            = $id;
        return view($this->data['url'] . '.show', $this->data);
    }

    public function getById($id)
    {
        try {
            $supplier = Supplier::with(['vendors' => fn($q) => $q->where('status', 'active')])->findOrFail($id);
            return response()->json(['success' => true, 'model' => $supplier], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Proveedor no encontrado.'], 404);
        }
    }

    public function getAll()
    {
        return response()->json(Supplier::active()->orderBy('name')->get(['id', 'name', 'status']));
    }
}
