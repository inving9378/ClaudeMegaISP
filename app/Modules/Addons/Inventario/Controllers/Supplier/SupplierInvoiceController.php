<?php

namespace App\Modules\Addons\Inventario\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\inventory\supplierinvoice\SupplierInvoiceDatatableHelper;
use App\Http\Requests\module\inventory\supplier_invoice\SupplierInvoiceCreateRequest;
use App\Models\SupplierInvoice;
use App\Services\Finance\GeneralAccounting\GeneralAccountingService;
use App\Services\InventoryService;
use App\Services\SupplierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierInvoiceController extends Controller
{
    protected $helper;
    protected $crudValidationRequest;
    protected $data;
    protected $supplierService;

    public function __construct(SupplierInvoiceDatatableHelper $helper, SupplierService $supplierService)
    {
        $this->helper                = $helper;
        $this->crudValidationRequest = new SupplierInvoiceCreateRequest();
        $this->supplierService       = $supplierService;

        $this->data['model']  = 'App\Models\SupplierInvoice';
        $this->data['url']    = 'meganet.module.inventory.supplier_invoice';
        $this->data['module'] = 'SupplierInvoice';

        $this->includeLibraryDinamic(Str::after($this->data['model'], "App\\Models\\"));
    }

    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        return view($this->data['url'] . '.listar', $this->data);
    }

    public function create()
    {
        $this->data['notifications'] = $this->userNotification();
        return view($this->data['url'] . '.create', $this->data);
    }

    public function store(Request $request)
    {
        $this->validate($request, $this->crudValidationRequest->storeRules(), $this->crudValidationRequest->storeMessageRules());
        try {
            $invoice = $this->supplierService->createInvoiceWithItems($request->all());
            return response()->json(['success' => true, 'message' => 'Factura creada con éxito.', 'model' => $invoice], 200);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first() ?? 'Datos inválidos.'], 422);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ocurrió un error al procesar la solicitud.'], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $invoice = SupplierInvoice::with(['supplier', 'supplierVendor', 'items.inventoryItem'])->findOrFail($id);
        if ($invoice->syncStatusFromMovements()) {
            $invoice->refresh();
            $invoice->load(['supplier', 'supplierVendor', 'items.inventoryItem']);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'invoice' => $invoice]);
        }

        $this->data['notifications'] = $this->userNotification();
        $this->data['invoice']       = $invoice;
        $this->data['id']            = $id;

        return view($this->data['url'] . '.show', $this->data);
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, $this->crudValidationRequest->updateRules(), $this->crudValidationRequest->updateMessageRules());
        try {
            $invoice = SupplierInvoice::findOrFail($id);

            if ($invoice->status === 'received') {
                return response()->json(['success' => false, 'message' => 'No se puede editar una factura ya recibida.'], 422);
            }

            $invoice->update($request->only(['invoice_number', 'date', 'supplier_vendor_id', 'notes', 'status']));
            return response()->json(['success' => true, 'message' => 'Factura actualizada con éxito.', 'model' => $invoice], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ocurrió un error al procesar la solicitud.'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $invoice = SupplierInvoice::findOrFail($id);
            if ($invoice->status === 'received') {
                return response()->json(['success' => false, 'message' => 'No se puede eliminar una factura ya recibida.'], 422);
            }
            $invoice->delete();
            return response()->json(['success' => true, 'message' => 'Factura eliminada.'], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ocurrió un error al eliminar.'], 500);
        }
    }

    public function getBySupplier(Request $request)
    {
        $supplierId = $request->supplier_id;
        $invoices = SupplierInvoice::with(['supplier', 'supplierVendor'])
            ->when($supplierId, fn($q) => $q->where('supplier_id', $supplierId))
            ->orderByDesc('date')
            ->get();
        return response()->json(['success' => true, 'data' => $invoices]);
    }

    public function generateNumber()
    {
        return response()->json(['success' => true, 'number' => $this->supplierService->generateInvoiceNumber()]);
    }

    public function deny(Request $request, $id)
    {
        try {
            $invoice = SupplierInvoice::findOrFail($id);

            if ($invoice->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Solo se pueden denegar facturas en estado Pendiente.'], 422);
            }
            $invoice->update(['status' => 'denied']);
            return response()->json(['success' => true, 'message' => 'Factura denegada.', 'model' => $invoice->fresh()], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ocurrio un error'], 500);
        }
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }

    public function showReceive($id)
    {
        return redirect("/inventory/supplier-invoice/show/$id");
    }

    public function receive(Request $request, $id)
    {
        $request->validate([
            'items'                         => 'required|array|min:1',
            'items.*.id'                    => 'required|integer',
            'items.*.inventory_store_id'    => 'required|integer|exists:inventory_stores,id',
            'items.*.quantity'              => 'required|numeric|min:0.01',
        ]);

        try {
            $invoice = SupplierInvoice::with('items.inventoryItem')->findOrFail($id);

            if ($invoice->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Solo se pueden distribuir facturas en estado Pendiente.'], 422);
            }

            $itemDistributions = collect($request->items)->groupBy('id');
            foreach ($invoice->items as $invoiceItem) {
                $distributions  = $itemDistributions->get($invoiceItem->id, collect());
                $distributedQty = $distributions->sum('quantity');
                if (abs($distributedQty - $invoiceItem->quantity) > 0.001) {
                    $itemName = $invoiceItem->inventoryItem->name ?? "ítem #{$invoiceItem->id}";
                    return response()->json([
                        'success' => false,
                        'message' => "La cantidad distribuida para \"{$itemName}\" ({$distributedQty}) no coincide con la cantidad de la factura ({$invoiceItem->quantity}).",
                    ], 422);
                }
            }

            $inventoryService = new InventoryService();

            DB::transaction(function () use ($invoice, $itemDistributions, $inventoryService) {
                $description  = 'Pedido de factura #' . ($invoice->invoice_number ?? $invoice->id) . ' — ' . ($invoice->supplier->name ?? '');
                $storeType    = 'App\Models\InventoryStore';
                $supplierType = 'App\Models\Supplier';

                foreach ($invoice->items as $invoiceItem) {
                    $distributions = $itemDistributions->get($invoiceItem->id, collect());
                    foreach ($distributions as $dist) {
                        $storeId = $dist['inventory_store_id'];
                        $qty     = $dist['quantity'];

                        $movement = $inventoryService->addMovementInventoryItemByType(
                            $invoiceItem->inventory_item_id,
                            $qty,
                            'Entrada',
                            $description,
                            $storeId,
                            $storeType,
                            $invoice->supplier_id,
                            $supplierType,
                            null,
                            false,
                            'pending'
                        );

                        $movement->update([
                            'supplier_invoice_id'      => $invoice->id,
                            'supplier_invoice_item_id' => $invoiceItem->id,
                        ]);
                    }
                }

                $invoice->update(['status' => 'dispatched']);

                $gaService = new GeneralAccountingService();
                $gaService->setNewGeneralAccountingExpenseByData([
                    'amount'           => $invoice->total,
                    'description'      => 'Factura proveedor #' . ($invoice->invoice_number ?? $invoice->id) . ' — ' . ($invoice->supplier->name ?? ''),
                    'created_by'       => auth()->user()?->id ?? 0,
                    'category'         => 'inversion',
                    'reference_number' => $gaService->generateReferenceNumber('INV'),
                ]);
            });

            return response()->json(['success' => true, 'message' => 'Factura distribuida. Los pedidos quedan pendientes de aceptación en cada almacén.', 'model' => $invoice->fresh()], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ocurrió un error al procesar la solicitud.'], 500);
        }
    }
}
