<?php


namespace App\Http\HelpersModule\module\inventory\inventorystore;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\InventoryStore;
use Illuminate\Support\Arr;

class InventoryStoreDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(InventoryStore::class, 'InventoryStore');
    }

    public function count($filters = null)
    {
        if (!empty($filters)) {
            $query = $this->model::filters($this->columns, null, $filters);
            if (!auth()->user()->isAdmin() && !auth()->user()->isSuperAdmin()) {
                $query->where('user_id', auth()->user()->id);
            }
            return $query->count();
        }
        $query = $this->model::select('*');
        if (!auth()->user()->isAdmin() && !auth()->user()->isSuperAdmin()) {
            $query->where('user_id', auth()->user()->id);
        }
        return $query->count();
    }

    public function ordering_query($start, $limit, $order, $dir, $filters = null)
    {
        if ($filters) {
            $query = $this->model::filters($this->columns, null, $filters)
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir);

            if (!auth()->user()->isAdmin() && !auth()->user()->isSuperAdmin()) {
                $query->where('user_id', auth()->user()->id);
            }
            return $query->get();
        }
        $query = $this->model::select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir);

        if (!auth()->user()->isAdmin() && !auth()->user()->isSuperAdmin()) {
            $query->where('user_id', auth()->user()->id);
        }
        return $query->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search, $filters = null)
    {
        return $this->model::filters($this->columns, $search, $filters)
            ->select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir);

        if (!auth()->user()->isAdmin() && !auth()->user()->isSuperAdmin()) {
            $query->where('user_id', auth()->user()->id);
        }

        return $query->get();
    }

    public function filtering_query($search)
    {
        $query = $this->model::filters($this->columns, $search);
        if (!auth()->user()->isAdmin() && !auth()->user()->isSuperAdmin()) {
            $query->where('user_id', auth()->user()->id);
        }
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
                    if ($val == 'user_id') {
                        $value->user_id = $value->user->name ?? '';
                    }
                    $nestedData[$val] = $value->$val;
                }

                $info = [
                    'id' => $id,
                    'module' => $this->moduleName,
                    'group' => 'inventory_store',
                    'submodule' => 'inventory_store',
                ];

                if ($type_modal_edit) $info = Arr::add($info, 'modal', $request['request']->modal['modal']);
                $nestedData['name'] = view('meganet.shared.table.module.inventory.inventory_store.name', [
                    'dir' => "/inventory/inventory_store/my-store/$id",
                    'name' => $value->name
                ])->toHtml();
                $nestedData['action'] = view('meganet.shared.table.module.inventory.inventory_store.actions' . $type_modal_edit, $info)->toHtml();
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
