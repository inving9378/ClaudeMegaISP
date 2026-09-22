<?php

namespace App\Http\Controllers\Base;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CrudModalController extends Controller
{
    protected $helper;
    protected $crudValidationRequest;
    protected $data;
    public function __construct($helper, $crudValidationRequest)
    {
        $this->helper = $helper;
        $this->crudValidationRequest = $crudValidationRequest;
    }

    public function index(Request $request)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic(Str::after($this->data['model'], "App\\Models\\"));
        return view($this->data['url'] . '.listar', $this->data);
    }

    /**
     * CrudModalController no tiene ficha propia — alta/edición viven en un modal
     * sobre la lista (ver store()/update(), ambos devuelven JSON para el modal).
     * Varias rutas GET /editar/{id} quedaron registradas apuntando aquí desde
     * antes de que el módulo pasara a este patrón, y este controlador base nunca
     * tuvo un método edit() → 500 "Method ... does not exist" si alguien la
     * visitaba directo. En vez de dejar el 500, se manda a la lista real (mismo
     * módulo, donde el modal de edición sí vive).
     *
     * Firma con Request $request primero (aunque no se use aquí): RuleController
     * ya traía su PROPIO edit(Request $request, $id) real y funcionando — un
     * edit($id) aquí rompía esa firma (fatal "Declaration must be compatible").
     */
    public function edit(Request $request, $id)
    {
        $listUrl = preg_replace('#/editar/[^/]+/?$#', '', request()->url());
        return redirect($listUrl ?: '/');
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
}
