<?php


namespace App\Http\HelpersModule\module\administration\rol;

use App\Http\HelpersModule\module\HelperDatatable;
use Spatie\Permission\Models\Role;

class RolDatatableHelper extends HelperDatatable
{
    private $model, $model_helper, $columns;

    public function __construct(RolModelHelper $model_helper)
    {
        $this->model = Role::class;
        $this->model_helper = $model_helper;
        $this->columns = collect($this->model_helper::DATATABLE_FIELDS)->keys()->toArray();
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
                foreach ($this->columns as $val) {
                    $nestedData[$val] = $value->$val;
                }

                if ($this->isNotAdmin($value)) {
                    $nestedData['action'] = view('meganet.shared.table.actions',[
                        'id' => $id,
                        'module' => 'role',
                        'group' => 'role',
                        'submodule' => 'role'
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

    public function isNotAdmin($column){
        return $column->name != 'super-administrator';
    }
}
