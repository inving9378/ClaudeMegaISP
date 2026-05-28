<?php

namespace App\Http\HelpersModule\module\inventory\suppliervendor;

use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\SupplierVendor;

class SupplierVendorDatatableHelper extends HelperModuleDatatable
{
    protected $supplierId = null;

    public function __construct()
    {
        parent::__construct(SupplierVendor::class, 'SupplierVendor');
    }

    public function setSupplierId($supplierId)
    {
        $this->supplierId = $supplierId;
    }

    protected function baseQuery()
    {
        $query = SupplierVendor::query();

        if ($this->supplierId !== null) {
            $query->where('supplier_id', $this->supplierId);
        }

        return $query;
    }

    public function count($filters = null)
    {
        $query = $this->baseQuery();

        if (!empty($filters)) {
            return $query->filters($this->columns, null, $filters)->count();
        }

        return $query->count();
    }

    public function ordering_query($start, $limit, $order, $dir, $filters = null)
    {
        $query = $this->baseQuery();

        if ($filters) {
            $query = $query->filters($this->columns, null, $filters);
        }

        return $query->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search, $filters = null)
    {
        $query = $this->baseQuery();

        $query = $query->filters($this->columns, $search, $filters);

        return $query->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function filtering_query($search, $filters = null)
    {
        $query = $this->baseQuery();

        return $query->filters($this->columns, $search, $filters)->count();
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
                        'status'      => $value->status_name,
                        'supplier_id' => $value->supplier->name ?? '',
                        default       => $value->$col ?? '',
                    };
                }

                $nestedData['action'] = view('meganet.shared.table.action_type_modal_with_parent_id', [
                    'id'          => $id,
                    'module'      => 'SupplierVendor',
                    'group'       => 'supplier',
                    'submodule'   => 'supplier_vendors',
                    'modal'       => 'crud-supplier-vendor',
                    'parent_id'   => $value->supplier_id,
                    'parent_route'=> 'inventory/supplier',
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
