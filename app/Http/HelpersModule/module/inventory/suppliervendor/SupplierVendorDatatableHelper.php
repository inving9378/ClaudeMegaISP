<?php

namespace App\Http\HelpersModule\module\inventory\suppliervendor;

use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\SupplierVendor;

class SupplierVendorDatatableHelper extends HelperModuleDatatable
{
    protected $supplierId;

    public function __construct()
    {
        parent::__construct(SupplierVendor::class, 'SupplierVendor');
    }

    public function setSupplierId($supplierId): self
    {
        $this->supplierId = $supplierId;

        return $this;
    }

    /**
     * Inyecta el supplier_id como filtro fijo para que el datatable
     * solo devuelva los vendedores del proveedor en contexto.
     * El scopeFilters de SupplierVendor consume $filter['supplier_id'].
     */
    public function getFiltersFromRequest($request)
    {
        $filters = parent::getFiltersFromRequest($request);

        if (!empty($this->supplierId)) {
            $filters['supplier_id'] = $this->supplierId;
        }

        return $filters;
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

                $nestedData['action'] = view('meganet.shared.table.actions', [
                    'id'        => $id,
                    'module'    => 'SupplierVendor',
                    'group'     => 'suppliervendor',
                    'submodule' => 'suppliervendor',
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
