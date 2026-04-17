<?php


namespace App\Http\HelpersModule\module\inventory\inventoryitemstock;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\Client;
use App\Models\InventoryItemStock;
use App\Models\InventoryItemStoreZone;
use App\Models\InventoryStore;
use App\Models\StoreZone;
use App\Models\User;
use Illuminate\Support\Arr;

class InventoryItemStockDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(InventoryItemStock::class, 'InventoryItemStock');
    }

    public function count($filters = null)
    {
        if (!empty($filters)) {
            $query = $this->model::filters($this->columns, null, $filters);            
            return $query->count();
        }
        $query = $this->model::select('*');
        $user = auth()->user();

        if (!($user->isAdmin() || $user->isSuperAdmin())) {

            $query->where(function ($q) use ($user) {

                // Caso 1: pertenece al usuario
                $q->where('modelable_type', User::class)
                    ->where('modelable_id', $user->id);

                // Caso 2: usuario responsable de un almacén
                $almacen = $this->authUserEsResponsableDeAlmacenDevuelveAlmacen();

                if ($almacen) {
                    $q->orWhere(function ($q2) use ($almacen) {
                        $q2->where('modelable_type', InventoryStore::class)
                            ->where('modelable_id', $almacen->id);
                    });
                }
            });
        }
        return $query->count();
    }


    public function ordering_query($start, $limit, $order, $dir, $filters = null)
    {
        // 1. Base query (con o sin filtros)
        $query = $filters
            ? $this->model::filters($this->columns, null, $filters)
            : $this->model::query();

        $query->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir);
        return $query->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search, $filters = null)
    {
        $query = $this->model::filters($this->columns, $search, $filters)
            ->select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir);

        return $query->get();
    }

    public function filtering_query($search)
    {
        $query = $this->model::filters($this->columns, $search);

        return $query->count();
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
                    $zone = $this->getZoneName($value);
                    if ($val == 'inventory_item_name') {
                        $value->inventory_item_name = $value->inventory_item->name ?? '';
                    }
                    if ($val == 'inventory_item_description') {
                        $value->inventory_item_description = $value->inventory_item->description ?? '';
                    }

                    if ($val == 'location') {
                        $value->location = $this->getNameModelable($value->modelable) ?? '';
                    }
                    if ($val == 'type') {
                        $value->type = $value->inventory_item->inventory_item_type->name ?? '';
                    }

                    if ($val == 'status_item') {
                        $value->status_item = $value->inventory_item->status_item ?? '';
                    }
                    if ($val == 'serial_number') {
                        $value->serial_number = $value->inventory_item->serial_number ?? '';
                    }
                    if ($val == 'zone') {
                        $value->zone = $zone['zone_name'] ?? '';
                    }
                    if ($val == 'reserved_stock') {
                        $value->reserved_stock = $value->reserved_stock ?? 0;
                    }
                    $nestedData[$val] = $value->$val;
                }

                $info = [
                    'id' => $id,
                    'module' => $this->moduleName,
                    'group' => 'inventory_item',
                    'submodule' => 'inventory_item',
                    'current_stock' => $value->current_stock,
                    'id_item' => $value->inventory_item->id,
                    'url_image' => $value->first_image_url,
                    'is_custom' => $value->inventory_item->inventory_item_custom_model_id
                ];

                if ($type_modal_edit) $info = Arr::add($info, 'modal', $request['request']->modal['modal']);
                if (!empty($type_modal_edit)) {
                    $nestedData['action'] = view('meganet.shared.table.module.inventory.inventory_item.actions' . $type_modal_edit, $info)->toHtml();
                }
                $nestedData['media'] = view('meganet.shared.table.module.inventory.inventory_item.media', $info)->toHtml();
                if ($value->modelable instanceof InventoryStore) {
                    $nestedData['zone'] = view('meganet.shared.table.module.inventory.inventory_item.zone', array_merge($info, ['store_id' => $value->modelable->id, 'zone_name' => $zone['zone_name'], 'zone_id' => $zone['zone_id']]))->toHtml();
                }

                $nestedData['created_at'] = view('meganet.shared.table.column_timestamp', [
                    'value' => $value,
                    'column' => 'created_at',
                ])->toHtml();

                $nestedData['updated_at'] = view('meganet.shared.table.column_timestamp', [
                    'value' => $value,
                    'column' => 'updated_at',
                ])->toHtml();

                $tdClass = $this->getTdClass($value);
                $nestedData['class_table_row'] = [
                    'td' => $tdClass
                ];


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


    private function getZoneName($inventoryItemStock)
    {
        $arrayReturn = [];
        $zoneName = '';
        $storeZoneId = null;
        if ($inventoryItemStock->modelable_type == 'App\Models\InventoryStore') {
            $storeId = $inventoryItemStock->modelable_id;
            $inventoryItemId = $inventoryItemStock->inventory_item_id;
            $inventoryItemStoreZone = InventoryItemStoreZone::where('inventory_item_id', $inventoryItemId)->where('inventory_store_id', $storeId)->first();
            $storeZoneId = $inventoryItemStoreZone ? $inventoryItemStoreZone->store_zone_id : null;
            if ($storeZoneId) {
                $storeZone = StoreZone::find($storeZoneId);
                $zoneName = $storeZone ? $storeZone->name : '';
            }
        }
        return [
            'zone_name' => $zoneName,
            'zone_id' => $storeZoneId
        ];
    }


    public function authUserEsResponsableDeAlmacenDevuelveAlmacen()
    {
        $inventory_store = InventoryStore::where('user_id', auth()->user()->id)->first();
        return $inventory_store ? $inventory_store : false;
    }


    public function getTdClass($value)
    {
        $currentStock = $value->current_stock;
        $middleLimit = $value->inventory_item->middle_limit;
        $highLimit = $value->inventory_item->high_limit;

        return match (true) {
            $currentStock < $middleLimit => 'td_inventory_item_limit_low',
            $currentStock < $highLimit => 'td_inventory_item_limit_middle',
            default => 'td_inventory_item_limit_hight'
        };
    }

    public function getNameModelable($modelable)
    {
        if ($modelable instanceof User) {
            return $modelable->name;
        }

        if ($modelable instanceof Client) {
            return $modelable->client_main_information->name;
        }

        if ($modelable instanceof InventoryStore) {
            return $modelable->name;
        }
    }
}
