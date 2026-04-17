<?php

namespace App\Http\Controllers\Module\Administration\Sucursal;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\administration\sucursal\SucursalDatatableHelper;
use App\Models\Sucursal;
use Illuminate\Http\Request;

class SucursalController extends Controller
{
    private $helper;

    public function __construct(SucursalDatatableHelper $helper)
    {
        $this->data['model'] = 'App\Models\Sucursal';
        $this->data['url'] = 'meganet.module.administration.sucursal';
        $this->data['module'] = 'Sucursal';
        $this->helper = $helper;
    }

    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic('Sucursal');
        return view('meganet.module.administration.sucursal.listar', $this->data);
    }

    public function store(Request $request)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();

        $model = $this->data['model']::create($input);
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request);

        return $model;
    }

    public function update(Request $request, $id)
    {
        $model = $this->data['model']::find($id);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');
        return $model->update($input);
    }

    public function destroy($id)
    {
        return  $this->data['model']::findOrFail($id)->delete();
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }

    public function all(Request $request)
    {
        return response()->json(Sucursal::all());
    }
}
