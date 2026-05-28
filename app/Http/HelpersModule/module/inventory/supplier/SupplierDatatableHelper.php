<?php

namespace App\Http\HelpersModule\module\inventory\supplier;

use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\Supplier;

class SupplierDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(Supplier::class, 'Supplier');
    }

    public function transform($request)
    {
        $data = [];

        if (!empty($request['array'])) {
            foreach ($request['array'] as $value) {
                $id = $value->id;
                $nestedData = [];

                foreach ($this->columns as $col) {
                    $nestedData[$col] = match ($col) {
                        'status' => $value->status_name,
                        default  => $value->$col ?? '',
                    };
                }

                $nestedData['name'] = view('meganet.shared.table.module.inventory.supplier.name', [
                    'dir'  => "/inventory/supplier/show/$id",
                    'name' => $value->name,
                ])->toHtml();

                $nestedData['action'] = view('meganet.shared.table.module.inventory.supplier.actions', [
                    'id'        => $id,
                    'module'    => 'Supplier',
                    'group'     => 'supplier',
                    'submodule' => 'supplier',
                ])->toHtml();

                $data[] = $nestedData;
            }
        }

        return [
            'draw'            => intval($request['request']->input('draw')),
            'recordsTotal'    => intval($request['totalData']),
            'recordsFiltered' => intval($request['totalFiltered']),
            'data'            => $data,
        ];
    }
}
