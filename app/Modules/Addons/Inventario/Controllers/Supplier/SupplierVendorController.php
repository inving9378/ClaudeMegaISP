<?php

namespace App\Modules\Addons\Inventario\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\inventory\suppliervendor\SupplierVendorDatatableHelper;
use App\Http\Requests\module\inventory\supplier_vendor\SupplierVendorCreateRequest;
use App\Models\Supplier;
use App\Models\SupplierVendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Repository\ModuleRepository;
use Illuminate\Support\Str;

class SupplierVendorController extends Controller
{
    protected $crudValidationRequest;
    protected $data;
    protected $helper;

    public function __construct(SupplierVendorDatatableHelper $helper)
    {
        $this->helper = $helper;
        $this->crudValidationRequest = new SupplierVendorCreateRequest();

        $this->data['model']  = 'App\Models\SupplierVendor';
        $this->data['url']    = 'meganet.module.inventory.supplier_vendor';
        $this->data['module'] = 'SupplierVendor';

        $this->includeLibraryDinamic(Str::after($this->data['model'], "App\\Models\\"));
    }

    public function index($supplierId)
    {
        $supplier = Supplier::findOrFail($supplierId);
        $vendors  = $supplier->vendors()->orderBy('name')->get();
        return response()->json(['success' => true, 'data' => $vendors], 200);
    }

    public function create($supplierId){
        $this->data['id'] = $this->getModuleId();
        $this->data['supplier_id'] = $supplierId;
        $this->data['action'] = 'add';

        return view($this->data['url'] . '.crear', $this->data);
    }

    public function getModuleId()
    {
        return (new ModuleRepository())->getModuleByName($this->data['module'])->id;
    }

    public function store(Request $request, $supplierId)
    {
        $request->merge(['supplier_id' => intval($supplierId)]);
        $this->validate($request, $this->crudValidationRequest->storeRules(), $this->crudValidationRequest->storeMessageRules());
        $_data = $request->only(['name', 'last_name', 'phone', 'email', 'position', 'status', 'supplier_id']);

        try {
            $vendor = SupplierVendor::create($_data);
            return response()->json(['success' => true, 'message' => 'Vendedor creado con éxito.', 'model' => $vendor], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ocurrió un error al procesar la solicitud.'], 500);
        }
    }

    public function update(Request $request, $supplierId, $vendorId)
    {
        $request->merge(['supplier_id' => intval($supplierId)]);
        $this->validate($request, $this->crudValidationRequest->updateRules(), $this->crudValidationRequest->updateMessageRules());
        try {
            $vendor = SupplierVendor::where('supplier_id', $supplierId)->findOrFail($vendorId);
            $vendor->update($request->only(['name', 'last_name', 'phone', 'email', 'position', 'status', 'supplier_id']));
            return response()->json(['success' => true, 'message' => 'Vendedor actualizado con éxito.', 'model' => $vendor], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ocurrió un error al procesar la solicitud.'], 500);
        }
    }

    public function destroy($supplierId, $vendorId)
    {
        try {
            SupplierVendor::where('supplier_id', $supplierId)->findOrFail($vendorId)->delete();
            return response()->json(['success' => true, 'message' => 'Vendedor eliminado.'], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ocurrió un error al eliminar.'], 500);
        }
    }

    public function table(Request $request, $supplierId)
    {
        $this->helper->setSupplierId($supplierId);
        return $this->helper->fetch_datatable_data($request);
    }

    public function getBySupplier($supplierId)
    {
        $vendors = SupplierVendor::where('supplier_id', $supplierId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'last_name', 'phone', 'email', 'position']);
        return response()->json(['success' => true, 'data' => $vendors]);
    }
}
