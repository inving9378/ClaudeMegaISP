<?php


namespace App\Http\HelpersModule\module\network;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Models\Module;
use App\Models\Network;

class NetworkDatatableHelper extends HelperDatatable
{
    private $model, $columns;


    public function __construct()
    {
        $this->model = Network::class;
        $moduleName = 'Network';
        $this->columns = Module::where('name', $moduleName)->first()->columnsDatatable->where('name', '!=', 'action')->pluck('name')->toArray();
    }

    public function count()
    {
        return $this->model::count();
    }

    public function ordering_query($start, $limit, $order, $dir)
    {
        return $this->model::select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search)
    {
        return $this->model::filters($this->columns, $search)
            ->select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function filtering_query($search)
    {
        return $this->model::filters($this->columns, $search)
            ->count();
    }

    public function transform($request)
    {
        $data = array();
        $buttons = $this->objectToArray(json_decode($request['request']->buttons));

        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val){
                    $nestedData[$val] = view('meganet.shared.table.column_network',[
                        'value' => $value,
                        'column' => $val,
                    ])->toHtml();
                }

                //TODO Revisar despues
                if ($this->isAdmin()) {
                    $nestedData['action'] = view('meganet.shared.table.actions_group_buttons', [
                        'id' => $id,
                        'buttons' => $buttons
                    ])->toHtml();
                }else{
                    $nestedData['action'] = '';
                }

                

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
    public function objectToArray($object)
    {
        return collect($object)->map(function ($item, $key) {
            $result = [];
            foreach ($item as $type => $values) {
                $result[$type] = [];
                foreach ($values as $key => $val) {
                    $result[$type][$key] = $val;
                }
            }
            return $result;
        })->toArray();
    }
    public function isAdmin(){
        return auth()->user()->isAdmin();
    }
}
