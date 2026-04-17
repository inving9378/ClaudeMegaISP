<?php


namespace App\Http\HelpersModule\module\message\reminder;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\Reminder;
use Illuminate\Support\Arr;

class ReminderDatatableHelper extends HelperModuleDatatable
{

    public function __construct()
    {
        parent::__construct(Reminder::class, 'Reminder');
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

                $nestedData['action'] = view('meganet.shared.table.module.message.reminder.actions' , $info)->toHtml();
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
