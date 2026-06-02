<?php

namespace App\Http\HelpersModule\module\inventory\supplierproductprice;

use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\SupplierProductPrice;

class SupplierProductPriceDatatableHelper extends HelperModuleDatatable
{
    protected $supplierId;

    public function __construct()
    {
        parent::__construct(SupplierProductPrice::class, 'SupplierProductPrice');
    }

    public function setSupplierId($supplierId): self
    {
        $this->supplierId = $supplierId;

        return $this;
    }

    /**
     * Fija el supplier_id como filtro para que el datatable solo
     * devuelva los precios del proveedor en contexto.
     * El scopeFilters de SupplierProductPrice consume $filter['supplier_id'].
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
                        'is_active'         => $value->is_active ? 'Activo' : 'Inactivo',
                        'base_price'        => number_format((float) $value->base_price, 2),
                        'price'             => number_format((float) $value->price, 2),
                        'bulk_price'        => number_format((float) $value->bulk_price, 2),
                        'inventory_item_id' => optional($value->inventoryItem)->name ?? $value->inventory_item_id,
                        default             => $value->$col ?? '',
                    };
                }

                $nestedData['action'] = view('meganet.shared.table.actions', [
                    'id'        => $id,
                    'module'    => 'SupplierProductPrice',
                    'group'     => 'supplierproductprice',
                    'submodule' => 'supplierproductprice',
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
