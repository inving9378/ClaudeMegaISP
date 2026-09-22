<?php

namespace App\Modules\Core\Localizacion\Controllers;

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

    /**
     * Alta/edición viven en un modal sobre la lista (ver store()/update()) —
     * este controlador nunca tuvo edit() y la ruta GET /editar/{id} quedó
     * registrada sin destino, dando 500. Nadie enlaza a ella desde el frontend;
     * si alguien la visita a mano, mandarlo a la lista real en vez de un 500.
     */
    public function edit($id)
    {
        return redirect('/administracion/sucursal');
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
