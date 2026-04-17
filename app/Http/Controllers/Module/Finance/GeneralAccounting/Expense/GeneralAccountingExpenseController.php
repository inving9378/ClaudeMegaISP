<?php

namespace App\Http\Controllers\Module\Finance\GeneralAccounting\Expense;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\finance\GeneralAccountingExpenseDatatableHelper;
use App\Services\Finance\GeneralAccounting\GeneralAccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GeneralAccountingExpenseController extends Controller
{

    private $helper;
    public function __construct(GeneralAccountingExpenseDatatableHelper $helper)
    {
        $model = 'GeneralAccountingExpense';
        $this->data['url'] = 'meganet.module.finance.general_accounting.expense';
        $this->data['module'] = 'GeneralAccountingExpense';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['group'] = 'finance';
        $this->helper = $helper;
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request, $this->data['model']);
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $generalAccountingService = new GeneralAccountingService();
            $data = [
                'amount' => $request->amount,
                'description' => $request->description,
                'created_by' => auth()->user()->id,
                'category' => 'Gasto Manual',
                'reference_number' => $generalAccountingService->generateReferenceNumber('EXP'),
            ];
            $model = $generalAccountingService->setNewGeneralAccountingExpenseByData($data);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'El costo de activación de pago se ha realizado con éxito.',
                'data' => $model
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al guardar Gasto: ' . $e->getMessage());
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ocurrio un error al procesar la solicitud',
            ], 500);
        }
    }
}
