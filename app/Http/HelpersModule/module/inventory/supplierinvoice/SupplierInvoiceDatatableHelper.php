<?php

namespace App\Http\HelpersModule\module\inventory\supplierinvoice;

use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\SupplierInvoice;

class SupplierInvoiceDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(SupplierInvoice::class, 'SupplierInvoice');
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
                        'status'             => $value->status_name,
                        'date'               => optional($value->date)->format('d/m/Y'),
                        'total'              => number_format((float) $value->total, 2),
                        'supplier_id'        => optional($value->supplier)->name ?? $value->supplier_id,
                        'supplier_vendor_id' => optional($value->supplierVendor)->full_name ?? $value->supplier_vendor_id,
                        default              => $value->$col ?? '',
                    };
                }

                $nestedData['action'] = view('meganet.shared.table.actions', [
                    'id'        => $id,
                    'module'    => 'SupplierInvoice',
                    'group'     => 'supplierinvoice',
                    'submodule' => 'supplierinvoice',
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
