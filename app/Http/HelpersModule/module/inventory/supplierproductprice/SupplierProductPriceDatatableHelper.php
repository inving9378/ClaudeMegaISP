<?php
namespace App\Http\HelpersModule\module\inventory\supplierproductprice;

use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\SupplierProductPrice;

class SupplierProductPriceDatatableHelper extends HelperModuleDatatable
{
    protected $supplierId = null;

    public function __construct()
    {
        parent::__construct(SupplierProductPrice::class, 'SupplierProductPrice');
    }

    public function setSupplierId($supplierId)
    {
        $this->supplierId = $supplierId;
    }

    protected function baseQuery()
    {
        $query = SupplierProductPrice::with([
            'inventoryItemStock.medias',
            'inventoryItem.medias'
        ]);
        if ($this->supplierId !== null) {
            $query->where('supplier_id', $this->supplierId);
        }
        return $query;
    }

    public function count($filters = null)
    {
        $query = $this->baseQuery();
        return !empty($filters) ? $query->filters($this->columns, null, $filters)->count() : $query->count();
    }

    public function ordering_query($start, $limit, $order, $dir, $filters = null)
    {
        $query = $this->baseQuery();
        if ($filters) {
            $query = $query->filters($this->columns, null, $filters);
        }
        return $query->offset($start)->limit($limit)->orderBy($order, $dir)->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search, $filters = null)
    {
        $query = $this->baseQuery();
        $query = $query->filters($this->columns, $search, $filters);
        return $query->offset($start)->limit($limit)->orderBy($order, $dir)->get();
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
                        'is_active'         => $value->is_active ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>',
                        'inventory_item_id' => $value->inventoryItem->name ?? '—',
                        'base_price'        => $value->base_price ? number_format($value->base_price, 2) : '—',
                        'price'             => $value->price !== null ? number_format($value->price, 2) : '—',
                        'bulk_price'        => $value->bulk_price ? number_format($value->bulk_price, 2) : '—',
                        'bulk_min_quantity' => $value->bulk_min_quantity ?? '—',
                        default             => $value->$col ?? '',
                    };
                }

                $imageUrl = null;

                $stock = $value->inventoryItemStock;
                if ($stock && $stock->relationLoaded('medias') && $stock->medias->isNotEmpty()) {
                    $firstMedia = $stock->medias->sortBy('order')->first();
                    $imageUrl = $firstMedia?->url;
                }

                if (!$imageUrl && $value->inventoryItem) {
                    $item = $value->inventoryItem;
                    if ($item->relationLoaded('medias') && $item->medias->isNotEmpty()) {
                        $firstMedia = $item->medias->sortBy('order')->first();
                        $imageUrl = $firstMedia?->url;
                    }
                }

                $nestedData['media'] = view('meganet.shared.table.module.inventory.supplier.product_media', [
                    'id'        => $id,
                    'url_image' => $imageUrl,
                ])->toHtml();
                $nestedData['action'] = view('meganet.shared.table.module.inventory.supplierproductprice.actions', [
                    'id' => $id,
                ])->toHtml();
                $data[] = $nestedData;
            }
        }
        return [
            'draw'            => intval($request['request']->input('draw')),
            'recordsTotal'    => intval($request['totalData']),
            'recordsFiltered' => intval($request['totalFiltered']),
            'data'            => $data
        ];
    }
}
