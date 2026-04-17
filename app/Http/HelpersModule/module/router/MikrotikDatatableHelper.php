<?php


namespace App\Http\HelpersModule\module\router;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Models\Mikrotik;
use App\Models\Module;

class MikrotikDatatableHelper extends HelperDatatable
{
    private $model;
    private $columns;
    public function __construct()
    {
        $this->model = Mikrotik::class;
        $moduleName = 'Mikrotik';
        $this->columns = Module::where('name', $moduleName)->first()->columnsDatatable->pluck('name')->toArray();
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

        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val){
                    $nestedData[$val] = $value->$val;
                }

                $nestedData['action'] = view('meganet.shared.table.actions',[
                    'id' => $id,
                    'module' => 'mikrotik'
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
