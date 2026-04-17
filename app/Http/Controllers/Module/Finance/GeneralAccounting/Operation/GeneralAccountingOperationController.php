<?php

namespace App\Http\Controllers\Module\Finance\GeneralAccounting\Operation;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\finance\GeneralAccountingOperationDatatableHelper;
use App\Models\GeneralAccountingCategory;
use App\Services\Finance\GeneralAccounting\GeneralAccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GeneralAccountingOperationController extends Controller
{

    private $helper;
    public function __construct(GeneralAccountingOperationDatatableHelper $helper)
    {
        $model = 'GeneralAccountingOperation';
        $this->data['url'] = 'meganet.module.finance.general_accounting.operation';
        $this->data['module'] = 'GeneralAccountingOperation';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['group'] = 'finance';
        $this->helper = $helper;
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $generalAccountingService = new GeneralAccountingService();
            $category = GeneralAccountingCategory::find($request->general_accounting_category_id);
            $type = $category->general_accounting_type->type;
            $data = [
                'amount' => $request->amount,
                'description' => $request->description,
                'created_by' => auth()->user()->id,
                'general_accounting_category_id' => $request->general_accounting_category_id,
                'operation_date' => $request->operation_date
            ];
            $model = $generalAccountingService->setNewGeneralAccountingOperationByData($data);

            if ($type === 'income') {
                $data = [
                    'amount' => $request->amount,
                    'description' => $request->description,
                    'created_by' => auth()->user()->id,
                    'category' => $category->name,
                    'reference_number' => $generalAccountingService->generateReferenceNumber(),
                    'operation_id' => $model->id,
                    'operation_date' => $request->operation_date
                ];
                $generalAccountingService->setNewGeneralAccountingIncomeByData($data);
            }

            if ($type === 'expense') {
                $data = [
                    'amount' => $request->amount,
                    'description' => $request->description,
                    'created_by' => auth()->user()->id,
                    'category' => $category->name,
                    'reference_number' => $generalAccountingService->generateReferenceNumber('EXP'),
                    'operation_id' => $model->id,
                    'operation_date' => $request->operation_date
                ];
                $generalAccountingService->setNewGeneralAccountingExpenseByData($data);
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'La solicitud se ha procesado con exito.',
                'data' => $model,
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

    public function update (Request $request, $id)
    {
        $model = $this->data['model']::find($id);
        if (!$model) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontro el registro solicitado',
            ], 404);
        }
        try {
            DB::beginTransaction();
            $data = [
                'amount' => $request->amount,
                'description' => $request->description,
                'updated_by' => auth()->user()->id,
                'operation_date' => $request->operation_date
            ];
            $model->update($data);
            $type = $model->general_accounting_category->general_accounting_type->type;
            if ($type === 'expense') {
                $data = [
                    'amount' => $request->amount,
                    'description' => $request->description,
                    'operation_date' => $request->operation_date
                ];
                $model->general_accounting_expense->update($data);
            }

            if ($type === 'income') {
                $data = [
                    'amount' => $request->amount,
                    'description' => $request->description,
                    'operation_date' => $request->operation_date
                ];
                $model->general_accounting_income->update($data);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'La solicitud se ha procesado con exito.',
                'data' => $model,
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
