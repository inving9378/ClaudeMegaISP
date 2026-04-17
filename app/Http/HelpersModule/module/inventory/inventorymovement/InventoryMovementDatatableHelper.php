<?php


namespace App\Http\HelpersModule\module\inventory\inventorymovement;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\InventoryMovement;
use App\Services\FormatDateService;
use Illuminate\Support\Arr;

class InventoryMovementDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(InventoryMovement::class, 'InventoryMovement');
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
                    if ($val == 'from') {
                        $value->from = $value->desde ?? '';
                    } else if ($val == 'to') {
                        $value->to = $value->hacia ?? '';
                    } else if ($val == 'created_at') {
                        $value->created_by = $value->user->name ?? '';
                    } else if ($val == 'status') {
                        $value->status = $value->status_name ?? '';
                    } else if ($val == 'inventory_item_id') {
                        $value->inventory_item_id = $value->inventory_item->name ?? '';
                    }


                    $nestedData[$val] = $value->$val;
                }

                $info = [
                    'id' => $id,
                    'module' => $this->moduleName,
                    'group' => 'inventory_movement',
                    'submodule' => 'inventory_movement'
                ];

                $nestedData['created_at'] = view('meganet.shared.table.column_timestamp', [
                    'value' => $value,
                    'column' => 'created_at',
                ])->toHtml();

                $nestedData['updated_at'] = view('meganet.shared.table.column_timestamp', [
                    'value' => $value,
                    'column' => 'updated_at',
                ])->toHtml();

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
