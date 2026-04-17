<?php


namespace App\Http\HelpersModule\module\administration\nomenclature;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\Nomenclature;
use Illuminate\Support\Arr;

class NomenclatureDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(Nomenclature::class, 'Nomenclature');
    }


    public function transform($request)
    {
        $data = array();

        $type_modal_edit = $this->includeButtonEditTypeModalIfIsRequested($request)
            ? '_type_modal' : '';

        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val) {
                    $nestedData[$val] = $value->$val;
                }

                $info = [
                    'id' => $id,
                    'module' => $this->moduleName,
                    'group' => strtolower($this->moduleName),
                    'submodule' => strtolower($this->moduleName)
                ];

                $nestedData['client_id'] = view('meganet.shared.table.module.nomenclature.client_id', [
                    'id' => $value->client_id
                ])->toHtml();

                if ($type_modal_edit) $info = Arr::add($info, 'modal', $request['request']->modal['modal']);
                $nestedData['action'] = view('meganet.shared.table.module.nomenclature.actions_type_modal', $info)->toHtml();
                $data[] = $nestedData;
            }
        }

        return [
            "draw" => intval($request['request']->input('draw')),
            "recordsTotal" => intval($request['totalData']),
            "recordsFiltered" => intval($request['totalFiltered']),
            "data" => $data
        ];
    }
}
