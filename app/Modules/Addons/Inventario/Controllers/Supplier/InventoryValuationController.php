<?php

namespace App\Modules\Addons\Inventario\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Services\SupplierService;

class InventoryValuationController extends Controller
{
    protected $supplierService;

    public function __construct(SupplierService $supplierService)
    {
        $this->supplierService = $supplierService;
    }

    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        return view('meganet.module.inventory.valuation.index', $this->data);
    }

    public function getData()
    {
        try {
            $data = $this->supplierService->getInventoryValuation();
            return response()->json(['success' => true, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener la valuación.'], 500);
        }
    }
}
