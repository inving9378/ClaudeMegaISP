<?php


namespace App\Http\HelpersModule\module\setting\rules;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\CommissionRule;
use App\Models\DurationContract;
use App\Models\InventoryItemType;
use Illuminate\Support\Arr;

class RuleDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(CommissionRule::class, 'CommissionRule');
    }


    public function transform($request)
    {
        $data = array();

        $type_modal_edit = $this->includeButtonEditTypeModalIfIsRequested($request)
            ? '_type_modal' : '';

        $contracts = DurationContract::select('id', 'name')->get();

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

                //if ($type_modal_edit) $info = Arr::add($info, 'modal', $request['request']->modal['modal']);
                $nestedData['action'] = view('meganet.module.setting.rules.columns.actions' . $type_modal_edit, $info)->toHtml();

                $nestedData['sellers_count'] =  view('meganet.module.setting.rules.columns.sellers' . $type_modal_edit, ['sellers' => $value->sellers_count])->toHtml();
                $nestedData['name'] =  view('meganet.module.setting.rules.columns.name' . $type_modal_edit, ['id' => $value->id, 'name' => $value->name])->toHtml();
                $nestedData['monthly_bonus'] =  isset($value->monthly_bonus) ? view('meganet.module.setting.rules.columns.monthly_bonus' . $type_modal_edit, ['commission' => $value->monthly_bonus])->toHtml() : '';
                $nestedData['distributors_commission'] =  isset($value->distributors_commission) ? view('meganet.module.setting.rules.columns.distributors_commission' . $type_modal_edit, ['commission' => $value->distributors_commission, 'contracts' => $contracts])->toHtml() : '';

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
