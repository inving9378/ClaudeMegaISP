<?php

namespace App\Modules\Addons\Inventario\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\inventory\supplierproductprice\SupplierProductPriceDatatableHelper;
use App\Http\Requests\module\inventory\supplier_product_price\SupplierProductPriceCreateRequest;
use App\Models\SupplierProductPrice;
use App\Services\SupplierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SupplierProductPriceController extends Controller
{
    protected $supplierService;
    protected $data;
    protected $helper;
    protected $crudValidationRequest;

    public function __construct(SupplierService $supplierService, SupplierProductPriceDatatableHelper $helper)
    {
        $this->supplierService       = $supplierService;
        $this->crudValidationRequest = new SupplierProductPriceCreateRequest();
        $this->helper                = $helper;
    }

    public function getAll(Request $request, $supplierId)
    {
        $page = $request->page ?? 1;
        $pageSize = $request->pageSize ?? 50;

        $query = SupplierProductPrice::with('inventoryItem')
            ->where('supplier_id', $supplierId)
            ->where('is_active', true);

        $totalCount = $query->count();

        $items = $query->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get()
            ->map(fn($item) => [
                'id'                => $item->id,
                'inventory_item_id' => $item->inventory_item_id,
                'name'              => $item->inventoryItem?->name ?? '',
                'base_price'        => $item->base_price,
                'price'             => $item->price,
                'bulk_price'        => $item->bulk_price,
                'bulk_min_quantity' => $item->bulk_min_quantity,
                'is_active'         => $item->is_active,
            ]);

        return response()->json([
            'data'       => $items,
            'totalCount' => $totalCount,
        ]);
    }

    public function store(Request $request, $supplierId)
    {
        $request->merge(['supplier_id' => $supplierId]);
        $this->validate($request, $this->crudValidationRequest->storeRules(), $this->crudValidationRequest->storeMessageRules());
        try {
            $existing = SupplierProductPrice::where('supplier_id', $supplierId)
                ->where('inventory_item_id', $request->inventory_item_id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este producto ya existe en el catálogo del proveedor.'
                ], 422);
            }

            $price = $this->supplierService->upsertProductPrice(
                array_merge($request->all(), ['supplier_id' => $supplierId])
            );
            return response()->json([
                'success' => true,
                'message' => 'Precio guardado con éxito.',
                'model'   => $price
            ], 200);
        } catch (\Exception $e) {
            Log::error('SupplierProductPrice::store - ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al guardar el precio: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $supplierId, $priceId)
    {
        $this->validate($request, $this->crudValidationRequest->updateRules(), $this->crudValidationRequest->updateMessageRules());
        try {
            $price = SupplierProductPrice::where('supplier_id', $supplierId)->findOrFail($priceId);

            $data = array_merge($request->all(), [
                'supplier_id'       => $supplierId,
                'inventory_item_id' => $price->inventory_item_id,
            ]);

            $updated = $this->supplierService->upsertProductPrice($data);
            return response()->json([
                'success' => true,
                'message' => 'Precio actualizado con éxito.',
                'model'   => $updated
            ], 200);
        } catch (\Exception $e) {
            Log::error('SupplierProductPrice::update - ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al actualizar el precio: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($supplierId, $priceId)
    {
        try {
            $price = SupplierProductPrice::where('supplier_id', $supplierId)->findOrFail($priceId);
            $price->delete();
            return response()->json([
                'success' => true,
                'message' => 'Precio eliminado correctamente.'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'El precio no fue encontrado.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('SupplierProductPrice::destroy - ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al eliminar el precio: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPriceForItem(Request $request)
    {
        try {
            $request->validate([
                'supplier_id'       => 'required|exists:suppliers,id',
                'inventory_item_id' => 'required|exists:inventory_items,id',
            ]);

            $price = $this->supplierService->getCatalogPrice(
                $request->supplier_id,
                $request->inventory_item_id
            );

            if (!$price) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró precio para este producto con este proveedor.',
                    'data'    => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $price
            ], 200);
        } catch (\Exception $e) {
            Log::error('SupplierProductPrice::getPriceForItem - ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al obtener el precio.'
            ], 500);
        }
    }

    public function table(Request $request, $supplierId)
    {
        $this->helper->setSupplierId($supplierId);
        return $this->helper->fetch_datatable_data($request);
    }
}
