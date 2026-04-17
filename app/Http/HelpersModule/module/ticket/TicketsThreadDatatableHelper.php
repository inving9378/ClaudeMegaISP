<?php


namespace App\Http\HelpersModule\module\ticket;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Models\Module;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class TicketsThreadDatatableHelper extends HelperDatatable
{
    private $model;
    private $columns;
    public function __construct()
    {
        $this->model = Ticket::query();
        $moduleName = 'TicketThread';
        $this->columns = Module::where('name', $moduleName)->first()->columnsDatatable->pluck('name')->toArray();
    }

    public function count($filters)
    {
       if (count($filters)){
           $this->queryCustomFilter($this->model, $filters)->count();
       }
       return $this->model->count();
    }

    public function ordering_query($start, $limit, $order, $dir, $filters)
    {
        if (count($filters)){
            return $this->queryCustomFilter($this->model, $filters)
                    ->select('*')
                    ->offset($start)
                    ->limit($limit)
                    ->orderBy($order, $dir)
                    ->get();
        }

        return $this->model->select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search, $filters)
    {
        if (count($filters)){
            return $this->queryCustomFilter($this->model, $filters)
                ->filters($this->columns, $search)
                ->select('*')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        }

        return $this->model->filters($this->columns, $search)
            ->select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function filtering_query($search, $filters)
    {
        if (count($filters)){
            return $this->queryCustomFilter($this->model, $filters)
                ->filters($this->columns, $search)
                ->count();
        }
        return $this->model->filters($this->columns, $search)
            ->count();
    }

    public function queryCustomFilter($model, $filters){
        foreach ($filters as $val){
            $value = collect($val)->values();
            $field = collect($val)->keys();

            if ($this->hasFieldStatusAndValueNuevo($field[0], $value[0])){
                $model = $this->queryStatusNuevo($model, [
                    'Nuevo', 'Trabajo en curso', 'Esperando al cliente', 'Esperando al agente'
                ]);
            }elseif ($this->hasFieldStatusAndValueCerrado($field[0], $value[0])){
                $model = $this->queryStatusNuevo($model, [
                    'Cerrado', 'Resuelto'
                ]);
            }else{
                $model = $model->where($field[0], $value[0]);
            }
        }
        return $model;
    }

    public function hasFieldStatusAndValueNuevo($field, $value){
        return $field == 'status' && $value == 'Nuevo';
    }

    public function hasFieldStatusAndValueCerrado($field, $value){
        return $field == 'status' && $value == 'Cerrado';
    }

    public function queryStatusNuevo($model, $values){
        return $model->whereIn('status', $values);
    }

    public function transform($request)
    {
        $data = array();
        $buttons = $this->objectToArray(json_decode($request['request']->buttons));

        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val){
                    $nestedData[$val] = view('meganet.shared.table.column_ticket',[
                        'value' => $value,
                        'column' => $val,
                    ])->toHtml();
                }


                if ($this->isNotAdmin($value)) {
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

    public function transformFilter($object)
    {
        return collect($object)->map(function ($item, $key) {
            $result = [];
            foreach ($item as $type => $values) {
                $result[$type] = $values;
            }
            return $result;
        })->toArray();
    }

    public function isNotAdmin($column){
        return $column->name != 'Administrador';
    }
}
