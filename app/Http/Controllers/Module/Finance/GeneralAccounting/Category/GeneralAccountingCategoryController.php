<?php

namespace App\Http\Controllers\Module\Finance\GeneralAccounting\Category;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\finance\GeneralAccountingCategoryDatatableHelper;
use App\Models\GeneralAccountingCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GeneralAccountingCategoryController extends Controller
{

    private $helper;
    public function __construct(GeneralAccountingCategoryDatatableHelper $helper)
    {
        $model = 'GeneralAccountingCategory';
        $this->data['url'] = 'meganet.module.finance.general_accounting.category';
        $this->data['module'] = 'GeneralAccountingCategory';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['group'] = 'finance';
        $this->helper = $helper;
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type_id' => ['required', 'integer'],
                'name' => ['required', 'string', 'max:255'],
            ], [
                'name.required' => 'El nombre es obligatorio.',
                'type_id.required' => 'El tipo es obligatorio.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $exists = GeneralAccountingCategory::where('type_id', $request->type_id)
                ->whereRaw('LOWER(name) = ?', [strtolower($request->name)])
                ->exists();

            if ($exists) {
                // Simular un error de validación de Laravel
                $validator->errors()->add('name', 'Ya existe una categoría con ese nombre para este tipo.');

                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            $data = [
                'type_id' => $request->type_id,
                'name' => ucfirst(strtolower($request->name)), // normaliza formato
                'created_by' => auth()->id(),
            ];

            $model = GeneralAccountingCategory::create($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'La solicitud se ha procesado con éxito.',
                'data' => $model,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al guardar categoría contable: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud.',
            ], 500);
        }
    }
}
