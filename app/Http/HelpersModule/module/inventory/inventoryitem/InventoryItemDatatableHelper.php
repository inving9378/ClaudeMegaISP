<?php


namespace App\Http\HelpersModule\module\inventory\inventoryitem;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\InventoryItem;
use Illuminate\Support\Arr;

class InventoryItemDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(InventoryItem::class, 'InventoryItem');
    }

    public function ordering_query($start, $limit, $order, $dir, $filters = null)
    {
        if ($filters) {
            return $this->model::filters($this->columns, null, $filters)
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        }
        return $this->model::select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search, $filters = null)
    {
        return $this->model::filters($this->columns, $search, $filters)
            ->select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
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
                    if ($val == 'inventory_item_type_id') {
                        $value->inventory_item_type_id = $value->inventory_item_type->name ?? '';
                    }
                    if ($val == 'current_stock') {
                        $value->current_stock = $value->current_stock;
                    }
                    if ($val == 'inventory_store_id') {
                        $value->inventory_store_id = $value->location_item;
                    }
                    if ($val == 'zone') {
                        $value->zone = $value->zone_name;
                    }

                    $nestedData[$val] = $value->$val;
                }

                $info = [
                    'id' => $id,
                    'module' => $this->moduleName,
                    'group' => 'inventory_item',
                    'submodule' => 'inventory_item',
                    'current_stock' => $value->current_stock
                ];

                if ($type_modal_edit) $info = Arr::add($info, 'modal', $request['request']->modal['modal']);
                $nestedData['action'] = view('meganet.shared.table.module.inventory.inventory_item.actions' . $type_modal_edit, $info)->toHtml();
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
