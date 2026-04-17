<?php


namespace App\Http\HelpersModule\module\inventory\inventoryitemtype;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\InventoryItemType;
use Illuminate\Support\Arr;

class InventoryItemTypeDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(InventoryItemType::class, 'InventoryItemType');
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
                    if ($val == 'type') {
                        $value->type = $value->type_name ?? '';
                    }
                    $nestedData[$val] = $value->$val;
                }

                $info = [
                    'id' => $id,
                    'module' => $this->moduleName,
                    'group' => 'inventory_item_type',
                    'submodule' => 'inventory_item_type',
                ];

                if ($type_modal_edit) $info = Arr::add($info, 'modal', $request['request']->modal['modal']);
                $nestedData['action'] = view('meganet.shared.table.actions' . $type_modal_edit, $info)->toHtml();
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
