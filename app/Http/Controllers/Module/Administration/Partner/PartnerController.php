<?php

namespace App\Http\Controllers\Module\Administration\Partner;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\administration\partner\PartnerDatatableHelper;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    private $helper;

    public function __construct(PartnerDatatableHelper $helper)
    {
        $this->data['model'] = 'App\Models\Partner';
        $this->data['url'] = 'meganet.module.administration.partner';
        $this->data['module'] = 'Partner';
        $this->helper = $helper;
    }

    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic('Partner');
        return view('meganet.module.administration.partner.listar', $this->data);
    }

    public function store(Request $request)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'],$request);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();

        unset($input['import']);

        $model = $this->data['model']::create($input);
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request);

        return $model;
    }

    public function update(Request $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'],$request);
        $model = $this->data['model']::find($id);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request,'sync');
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
}
